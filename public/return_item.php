<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireLogin($pdo);
Loans::expirePending($pdo);

$id = (int) ($_GET['id'] ?? $_POST['item_id'] ?? 0);
$item = $id > 0 ? Items::findById($pdo, $id) : null;
$loan = $item ? Loans::activeLoanForItem($pdo, (int) $item['id']) : null;

if (!$item || !$loan || !Items::visibleToUser($item, Auth::isAdminPlus($currentUser))) {
    http_response_code(404);
    $pageTitle = 'Not found';
    render_header($pageTitle, $currentUser);
    echo '<section class="card"><p class="bad">Active loan not found.</p></section>';
    render_footer();
    exit;
}

$canReturn = ((int) $loan['borrower_user_id'] === (int) $currentUser['id']) || Auth::isAdminPlus($currentUser);
$includes = (int) $item['is_kit'] === 1 ? Items::kitIncludes($pdo, (int) $item['id']) : [];
$error = '';
$mode = (string) ($_POST['mode'] ?? 'same_device');
$witnessCallsign = strtoupper(trim((string) ($_POST['witness_callsign'] ?? '')));
$notes = trim((string) ($_POST['notes'] ?? ''));
$adminOverride = Auth::isAdminPlus($currentUser) && !empty($_POST['admin_override']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (!$canReturn) {
            throw new RuntimeException('Only the borrower or an Admin+ can return this item.');
        }

        $checked = array_map('intval', $_POST['kit_line'] ?? []);
        $master = !empty($_POST['kit_master']);
        Loans::assertKitConfirmed($item, $includes, $checked, $master);
        $kitVerified = (int) $item['is_kit'] === 1 ? true : false;

        $pdo->beginTransaction();

        if ($adminOverride) {
            Loans::returnImmediate(
                $pdo,
                $item,
                $currentUser,
                $loan,
                null,
                true,
                $kitVerified || (int) $item['is_kit'] === 0,
                $notes !== '' ? $notes : null
            );
            $pdo->commit();
            redirect('item.php?id=' . (int) $item['id']);
        }

        if ($mode === 'remote') {
            $witness = Loans::findUserByCallsign($pdo, $witnessCallsign);
            if (!$witness) {
                throw new RuntimeException('Witness callsign not found.');
            }
            Loans::createRemoteRequest(
                $pdo,
                'loan_return',
                $item,
                $currentUser,
                $currentUser,
                $witness,
                (int) $loan['id'],
                $kitVerified || (int) $item['is_kit'] === 0,
                $notes !== '' ? $notes : null
            );
            $pdo->commit();
            redirect('witness.php?sent=1');
        }

        $witness = Loans::findUserByCallsign($pdo, $witnessCallsign);
        if (!$witness) {
            throw new RuntimeException('Witness callsign not found.');
        }
        Loans::verifyWitnessPassword($witness, (string) ($_POST['witness_password'] ?? ''));
        Loans::returnImmediate(
            $pdo,
            $item,
            $currentUser,
            $loan,
            $witness,
            false,
            $kitVerified || (int) $item['is_kit'] === 0,
            $notes !== '' ? $notes : null
        );
        $pdo->commit();
        redirect('item.php?id=' . (int) $item['id']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$pageTitle = 'Return item';
render_header($pageTitle, $currentUser, 'Return · ' . $item['public_id']);
?>
<section class="card">
  <h2><?= e($item['description']) ?></h2>
  <p class="note">
    <?= e($item['public_id']) ?> · on loan to <?= e($loan['borrower_callsign']) ?>
    since <?= e($loan['loaned_at']) ?>
  </p>
  <?php if ($error !== ''): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>

  <?php if (!$canReturn): ?>
    <p class="bad">Only the borrower or an Admin+ can return this item.</p>
  <?php else: ?>
  <form method="post" action="return_item.php?id=<?= (int) $item['id'] ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">

    <label for="notes">Notes (optional)</label>
    <input id="notes" name="notes" type="text" value="<?= e($notes) ?>">

    <?php if ((int) $item['is_kit'] === 1): ?>
      <h3>Kit verification</h3>
      <?php if ($includes): ?>
        <ul class="check-list">
          <?php foreach ($includes as $line): ?>
            <li>
              <label class="check">
                <input type="checkbox" name="kit_line[]" value="<?= (int) $line['id'] ?>">
                <?= e($line['line_label']) ?>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <label class="check">
        <input type="checkbox" name="kit_master" value="1" required>
        I verified all included items are present
      </label>
    <?php endif; ?>

    <h3>Witness method</h3>
    <label class="check">
      <input type="radio" name="mode" value="same_device" <?= $mode !== 'remote' ? 'checked' : '' ?>>
      Same device (primary)
    </label>
    <label class="check">
      <input type="radio" name="mode" value="remote" <?= $mode === 'remote' ? 'checked' : '' ?>>
      Remote request (expires in <?= (int) Loans::PENDING_HOURS ?> hours)
    </label>

    <label for="witness_callsign">Witness callsign</label>
    <input id="witness_callsign" name="witness_callsign" type="text" value="<?= e($witnessCallsign) ?>">

    <label for="witness_password">Witness password (same device only)</label>
    <input id="witness_password" name="witness_password" type="password" autocomplete="off">

    <?php if (Auth::isAdminPlus($currentUser)): ?>
      <label class="check">
        <input type="checkbox" name="admin_override" value="1" <?= $adminOverride ? 'checked' : '' ?>>
        Admin override — complete without witness (logged)
      </label>
    <?php endif; ?>

    <button type="submit">Complete return</button>
  </form>
  <?php endif; ?>
</section>
<script>
  (function () {
    var radios = document.querySelectorAll('input[name="mode"]');
    var pass = document.getElementById('witness_password');
    function sync() {
      var remote = document.querySelector('input[name="mode"]:checked');
      if (!remote || !pass) return;
      pass.disabled = remote.value === 'remote';
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  })();
</script>
<?php render_footer(); ?>

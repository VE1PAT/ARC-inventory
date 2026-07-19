<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireLogin($pdo);
Loans::expirePending($pdo);

$id = (int) ($_GET['id'] ?? $_POST['item_id'] ?? 0);
$item = $id > 0 ? Items::findById($pdo, $id) : null;
if (!$item || !Items::visibleToUser($item, Auth::isAdminPlus($currentUser))) {
    http_response_code(404);
    $pageTitle = 'Not found';
    render_header($pageTitle, $currentUser);
    echo '<section class="card"><p class="bad">Item not found.</p></section>';
    render_footer();
    exit;
}

$includes = (int) $item['is_kit'] === 1 ? Items::kitIncludes($pdo, (int) $item['id']) : [];
$error = '';
$mode = (string) ($_POST['mode'] ?? 'same_device');
$borrowerCallsign = strtoupper(trim((string) ($_POST['borrower_callsign'] ?? $currentUser['callsign'])));
$witnessCallsign = strtoupper(trim((string) ($_POST['witness_callsign'] ?? '')));
$notes = trim((string) ($_POST['notes'] ?? ''));
$adminOverride = Auth::isAdminPlus($currentUser) && !empty($_POST['admin_override']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if ($item['status'] !== 'available' || (int) $item['not_for_loan'] === 1) {
            throw new RuntimeException('This item cannot be loaned right now.');
        }

        $borrower = Loans::findUserByCallsign($pdo, $borrowerCallsign);
        if (!$borrower || !(int) $borrower['is_active'] || !empty($borrower['locked_at'])) {
            throw new RuntimeException('Borrower must be an active member with a login.');
        }

        $checked = array_map('intval', $_POST['kit_line'] ?? []);
        $master = !empty($_POST['kit_master']);
        Loans::assertKitConfirmed($item, $includes, $checked, $master);
        $kitVerified = (int) $item['is_kit'] === 1 ? true : false;

        $pdo->beginTransaction();

        if ($adminOverride) {
            Loans::loanOutImmediate(
                $pdo,
                $item,
                $currentUser,
                $borrower,
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
                'loan_out',
                $item,
                $currentUser,
                $borrower,
                $witness,
                null,
                $kitVerified || (int) $item['is_kit'] === 0,
                $notes !== '' ? $notes : null
            );
            $pdo->commit();
            redirect('witness.php?sent=1');
        }

        // Same-device witness
        $witness = Loans::findUserByCallsign($pdo, $witnessCallsign);
        if (!$witness) {
            throw new RuntimeException('Witness callsign not found.');
        }
        Loans::verifyWitnessPassword($witness, (string) ($_POST['witness_password'] ?? ''));
        Loans::loanOutImmediate(
            $pdo,
            $item,
            $currentUser,
            $borrower,
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

$pageTitle = 'Loan item';
render_header($pageTitle, $currentUser, 'Loan · ' . $item['public_id']);
?>
<section class="card">
  <h2><?= e($item['description']) ?></h2>
  <p class="note"><?= e($item['public_id']) ?> · <?= e(Items::statusLabel((string) $item['status'])) ?></p>
  <?php if ($error !== ''): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>

  <?php if ($item['status'] !== 'available' || (int) $item['not_for_loan'] === 1): ?>
    <p class="bad">This item cannot be loaned.</p>
    <a class="button" href="item.php?id=<?= (int) $item['id'] ?>">Back</a>
  <?php else: ?>
  <form method="post" action="loan.php?id=<?= (int) $item['id'] ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">

    <label for="borrower_callsign">Borrower callsign</label>
    <input id="borrower_callsign" name="borrower_callsign" type="text" required
           value="<?= e($borrowerCallsign) ?>">

    <label for="notes">Notes (optional)</label>
    <input id="notes" name="notes" type="text" value="<?= e($notes) ?>">

    <?php if ((int) $item['is_kit'] === 1): ?>
      <h3>Kit verification</h3>
      <?php if ($includes): ?>
        <ul class="check-list">
          <?php foreach ($includes as $line): ?>
            <li>
              <label class="check">
                <input type="checkbox" name="kit_line[]" value="<?= (int) $line['id'] ?>"
                  <?= in_array((int) $line['id'], array_map('intval', $_POST['kit_line'] ?? []), true) ? 'checked' : '' ?>>
                <?= e($line['line_label']) ?>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <label class="check">
        <input type="checkbox" name="kit_master" value="1" <?= !empty($_POST['kit_master']) ? 'checked' : '' ?> required>
        I verified all included items are present
      </label>
    <?php endif; ?>

    <h3>Witness method</h3>
    <label class="check">
      <input type="radio" name="mode" value="same_device" <?= $mode !== 'remote' ? 'checked' : '' ?>>
      Same device (primary) — witness enters callsign + password here
    </label>
    <label class="check">
      <input type="radio" name="mode" value="remote" <?= $mode === 'remote' ? 'checked' : '' ?>>
      Remote — send in-app request (expires in <?= (int) Loans::PENDING_HOURS ?> hours)
    </label>

    <div id="witness_fields">
      <label for="witness_callsign">Witness callsign</label>
      <input id="witness_callsign" name="witness_callsign" type="text"
             value="<?= e($witnessCallsign) ?>">

      <label for="witness_password">Witness password (same device only)</label>
      <input id="witness_password" name="witness_password" type="password" autocomplete="off">
    </div>

    <?php if (Auth::isAdminPlus($currentUser)): ?>
      <label class="check">
        <input type="checkbox" name="admin_override" value="1" <?= $adminOverride ? 'checked' : '' ?>>
        Admin override — complete without witness (logged)
      </label>
    <?php endif; ?>

    <button type="submit">Complete loan</button>
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
      pass.closest('label') ? null : null;
      var label = pass.previousElementSibling;
      if (label) label.style.opacity = remote.value === 'remote' ? '0.5' : '1';
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  })();
</script>
<?php render_footer(); ?>

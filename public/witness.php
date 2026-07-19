<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireLogin($pdo);
Loans::expirePending($pdo);

$error = '';
$message = '';

if (!empty($_GET['sent'])) {
    $message = 'Witness request sent. It expires in ' . Loans::PENDING_HOURS . ' hours.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $approve = ($_POST['action'] ?? '') === 'approve';
    try {
        $pdo->beginTransaction();
        $request = Loans::findRequest($pdo, $requestId);
        if (!$request) {
            throw new RuntimeException('Request not found.');
        }
        Loans::resolveRequest($pdo, $request, $currentUser, $approve);
        $pdo->commit();
        $message = $approve ? 'Request approved. Inventory updated.' : 'Request declined.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$pending = Loans::pendingForWitness($pdo, (int) $currentUser['id']);

$pageTitle = 'Pending witness';
render_header($pageTitle, $currentUser);
?>
<section class="card">
  <h2>Requests waiting for you</h2>
  <?php if ($message !== ''): ?><p class="ok"><?= e($message) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>

  <?php if (!$pending): ?>
    <p class="note">No pending witness requests.</p>
  <?php else: ?>
    <ul class="plain-list">
      <?php foreach ($pending as $row): ?>
        <li>
          <div>
            <strong><?= e($row['description']) ?></strong>
            <div class="note">
              <?= e($row['public_id']) ?> ·
              <?= $row['action_type'] === 'loan_out' ? 'Loan out' : 'Return' ?> ·
              by <?= e($row['actor_callsign']) ?> ·
              subject <?= e($row['subject_callsign']) ?>
            </div>
            <div class="note">Expires <?= e($row['expires_at']) ?></div>
          </div>
          <div class="btn-row">
            <form method="post" action="witness.php">
              <?= csrf_field() ?>
              <input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit">Approve</button>
            </form>
            <form method="post" action="witness.php">
              <?= csrf_field() ?>
              <input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="action" value="decline">
              <button type="submit" class="button-secondary">Decline</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php render_footer(); ?>

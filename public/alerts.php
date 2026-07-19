<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireRole($pdo, ['admin', 'superuser']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'mark_read') {
        $id = (int) ($_POST['alert_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE security_alerts SET is_read = 1 WHERE id = :id');
            $stmt->execute([':id' => $id]);
        }
    }

    if ($action === 'mark_all_read') {
        $pdo->exec('UPDATE security_alerts SET is_read = 1 WHERE is_read = 0');
    }

    if ($action === 'unlock') {
        $callsign = strtoupper(trim((string) ($_POST['callsign'] ?? '')));
        if ($callsign !== '') {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE callsign = :c LIMIT 1');
            $stmt->execute([':c' => $callsign]);
            $row = $stmt->fetch();
            if ($row) {
                Auth::unlock($pdo, (int) $row['id']);
                Auth::addSecurityAlert(
                    $pdo,
                    'account_unlocked',
                    $callsign,
                    'Account unlocked by ' . $currentUser['callsign'] . ': ' . $callsign
                );
            }
        }
    }

    redirect('alerts.php');
}

$alerts = $pdo->query(
    'SELECT id, alert_type, callsign, message, is_read, created_at
     FROM security_alerts
     ORDER BY created_at DESC, id DESC
     LIMIT 100'
)->fetchAll();

$locked = $pdo->query(
    "SELECT id, callsign, locked_at, failed_login_count
     FROM users
     WHERE locked_at IS NOT NULL
     ORDER BY locked_at DESC"
)->fetchAll();

$pageTitle = 'Security alerts';
render_header($pageTitle, $currentUser);
?>
<section class="card">
  <h2>Locked accounts</h2>
  <?php if (!$locked): ?>
    <p class="note">No locked accounts right now.</p>
  <?php else: ?>
    <ul class="plain-list">
      <?php foreach ($locked as $u): ?>
        <li>
          <strong><?= e($u['callsign']) ?></strong>
          · locked <?= e($u['locked_at']) ?>
          · fails <?= (int) $u['failed_login_count'] ?>
          <form method="post" action="alerts.php" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unlock">
            <input type="hidden" name="callsign" value="<?= e($u['callsign']) ?>">
            <button type="submit">Unlock</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="card">
  <div class="row-between">
    <h2>Alert log</h2>
    <form method="post" action="alerts.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_all_read">
      <button type="submit" class="button-secondary">Mark all read</button>
    </form>
  </div>

  <?php if (!$alerts): ?>
    <p class="note">No alerts yet.</p>
  <?php else: ?>
    <ul class="plain-list">
      <?php foreach ($alerts as $a): ?>
        <li class="<?= (int) $a['is_read'] === 0 ? 'unread' : '' ?>">
          <div>
            <strong><?= e($a['alert_type']) ?></strong>
            <?php if ($a['callsign']): ?> · <?= e($a['callsign']) ?><?php endif; ?>
            <div class="note"><?= e($a['created_at']) ?></div>
            <div><?= e($a['message']) ?></div>
          </div>
          <?php if ((int) $a['is_read'] === 0): ?>
            <form method="post" action="alerts.php" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="mark_read">
              <input type="hidden" name="alert_id" value="<?= (int) $a['id'] ?>">
              <button type="submit" class="button-secondary">Mark read</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php render_footer(); ?>

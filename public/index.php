<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$appName = app_config()['app_name'] ?? 'ARC Inventory';
$dbOk = false;
$dbError = '';
$userCount = 0;
$installed = false;
$club = 'ARC Inventory';
$website = '';

try {
    $pdo = db();
    $dbOk = true;
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $installed = Settings::isInstalled($pdo);
    $club = club_name();
    $website = club_website();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($club) ?> · <?= e($appName) ?></title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <header class="top">
    <div class="wrap">
      <h1><?= e($club) ?></h1>
      <p><?= e($appName) ?><?php if ($website !== ''): ?> · <?= e($website) ?><?php endif; ?></p>
    </div>
  </header>

  <main class="wrap">
    <section class="card">
      <h2>Setup status</h2>
      <?php if ($dbOk): ?>
        <p class="ok">Database connection: OK</p>
        <p>Users in database: <strong><?= (int) $userCount ?></strong></p>
        <?php if ($installed): ?>
          <p class="ok">Club setup: complete</p>
        <?php else: ?>
          <p class="bad">Club setup: not finished</p>
          <p class="note">Run the installer to set club name, website, app URL, and superusers.</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="bad">Database connection: FAILED</p>
        <p class="note"><?= e($dbError) ?></p>
        <p>Import <code>sql/001_schema.sql</code> (and <code>sql/002_settings.sql</code> if needed), then check <code>config/config.php</code>.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Quick links</h2>
      <ul>
        <li><a href="install.php">First-time club setup</a> (name, website, superusers)</li>
        <li><a href="setup_superuser.php">Recovery: create / reset a superuser</a></li>
      </ul>
      <p class="note">On a public host, remove or lock the setup pages after go-live.</p>
    </section>
  </main>
</body>
</html>

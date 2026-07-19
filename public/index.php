<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$appName = htmlspecialchars(app_config()['app_name'] ?? 'ARC Inventory', ENT_QUOTES, 'UTF-8');
$dbOk = false;
$dbError = '';
$userCount = 0;

try {
    $pdo = db();
    $dbOk = true;
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $appName ?></title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <header class="top">
    <div class="wrap">
      <h1><?= $appName ?></h1>
      <p>Halifax Amateur Radio Club · Inventory</p>
    </div>
  </header>

  <main class="wrap">
    <section class="card">
      <h2>Local setup status</h2>
      <?php if ($dbOk): ?>
        <p class="ok">Database connection: OK</p>
        <p>Users in database: <strong><?= $userCount ?></strong></p>
        <p class="note">Next we will build login, search, loans, and witness flows on this foundation.</p>
      <?php else: ?>
        <p class="bad">Database connection: FAILED</p>
        <p class="note"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></p>
        <p>Create the database from <code>sql/001_schema.sql</code>, then confirm <code>config/config.php</code>.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Quick links</h2>
      <ul>
        <li><a href="setup_superuser.php">Create / reset first superuser</a> (local setup helper)</li>
        <li><a href="../design-spec.html">Locked design spec</a> (open via file or copy into public if needed)</li>
      </ul>
    </section>
  </main>
</body>
</html>

<?php
declare(strict_types=1);

/**
 * First-time club setup: club identity + initial superusers.
 * Safe to re-open later only if you intentionally need to change branding
 * or add/reset a superuser during early setup. Lock/remove on public hosts after go-live.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$message = '';
$error = '';
$installed = false;
$dbOk = false;

try {
    $pdo = db();
    $dbOk = true;
    $installed = Settings::isInstalled($pdo);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$defaults = [
    'club_name' => $dbOk ? (Settings::get($pdo, 'club_name', '') ?? '') : '',
    'club_website' => $dbOk ? (Settings::get($pdo, 'club_website', '') ?? '') : '',
    'app_base_url' => $dbOk
        ? (Settings::get($pdo, 'app_base_url', app_config()['base_url'] ?? '') ?? '')
        : (app_config()['base_url'] ?? ''),
    'callsign1' => '',
    'callsign2' => '',
];

if ($dbOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $clubName = trim((string) ($_POST['club_name'] ?? ''));
    $clubWebsite = trim((string) ($_POST['club_website'] ?? ''));
    $appBaseUrl = rtrim(trim((string) ($_POST['app_base_url'] ?? '')), '/');

    $c1 = strtoupper(trim((string) ($_POST['callsign1'] ?? '')));
    $p1 = (string) ($_POST['password1'] ?? '');
    $p1c = (string) ($_POST['password1_confirm'] ?? '');

    $c2 = strtoupper(trim((string) ($_POST['callsign2'] ?? '')));
    $p2 = (string) ($_POST['password2'] ?? '');
    $p2c = (string) ($_POST['password2_confirm'] ?? '');

    $defaults = [
        'club_name' => $clubName,
        'club_website' => $clubWebsite,
        'app_base_url' => $appBaseUrl,
        'callsign1' => $c1,
        'callsign2' => $c2,
    ];

    if ($clubName === '') {
        $error = 'Club name is required.';
    } elseif ($appBaseUrl === '' || !preg_match('#^https?://#i', $appBaseUrl)) {
        $error = 'App base URL must start with http:// or https://';
    } elseif ($clubWebsite !== '' && !preg_match('#^https?://#i', $clubWebsite)) {
        $error = 'Club website must start with http:// or https:// (or leave blank).';
    } elseif ($c1 === '' || $p1 === '') {
        $error = 'At least one superuser callsign and password are required.';
    } elseif ($p1 !== $p1c) {
        $error = 'Superuser 1 password and confirmation do not match.';
    } elseif (strlen($p1) < 8) {
        $error = 'Superuser 1 password must be at least 8 characters.';
    } elseif ($c2 !== '' && ($p2 === '' || $p2 !== $p2c || strlen($p2) < 8)) {
        $error = 'Superuser 2 was started but password is missing, too short, or does not match.';
    } elseif ($c2 !== '' && $c2 === $c1) {
        $error = 'Superuser callsigns must be different.';
    } else {
        try {
            $pdo->beginTransaction();

            Settings::set($pdo, 'club_name', $clubName);
            Settings::set($pdo, 'club_website', $clubWebsite);
            Settings::set($pdo, 'app_base_url', $appBaseUrl);
            Settings::set($pdo, 'setup_completed_at', gmdate('c'));

            $upsert = $pdo->prepare(
                'INSERT INTO users (callsign, password_hash, role, is_active, failed_login_count, locked_at)
                 VALUES (:c, :h, :r, 1, 0, NULL)
                 ON DUPLICATE KEY UPDATE
                   password_hash = VALUES(password_hash),
                   role = VALUES(role),
                   is_active = 1,
                   failed_login_count = 0,
                   locked_at = NULL'
            );

            $upsert->execute([
                ':c' => $c1,
                ':h' => password_hash($p1, PASSWORD_DEFAULT),
                ':r' => 'superuser',
            ]);

            if ($c2 !== '') {
                $upsert->execute([
                    ':c' => $c2,
                    ':h' => password_hash($p2, PASSWORD_DEFAULT),
                    ':r' => 'superuser',
                ]);
            }

            $pdo->commit();
            $installed = true;
            $message = 'Club setup saved. Create a second superuser now if you skipped it — avoid a single point of failure.';
            if ($c2 !== '') {
                $message = 'Club setup saved with two superusers. You can return to the status page.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Install · ARC Inventory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <header class="top">
    <div class="wrap">
      <h1>First-time setup</h1>
      <p>ARC Inventory · one install per club</p>
    </div>
  </header>

  <main class="wrap">
    <section class="card">
      <?php if (!$dbOk): ?>
        <p class="bad">Database connection failed.</p>
        <p class="note"><?= e($error) ?></p>
        <p>Import <code>sql/001_schema.sql</code> and <code>sql/002_settings.sql</code>, then check <code>config/config.php</code>.</p>
      <?php else: ?>
        <?php if ($message): ?><p class="ok"><?= e($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>
        <?php if ($installed): ?>
          <p class="note">This install already has club settings and at least one superuser. Re-submitting updates branding and listed superuser passwords.</p>
        <?php endif; ?>

        <form method="post" action="">
          <h2>Club identity</h2>
          <label for="club_name">Club name</label>
          <input id="club_name" name="club_name" type="text" required
                 placeholder="Example Amateur Radio Club"
                 value="<?= e($defaults['club_name']) ?>">

          <label for="club_website">Club website URL</label>
          <input id="club_website" name="club_website" type="url"
                 placeholder="https://example-arc.org"
                 value="<?= e($defaults['club_website']) ?>">

          <label for="app_base_url">Inventory app base URL</label>
          <input id="app_base_url" name="app_base_url" type="url" required
                 placeholder="https://inventory.example-arc.org"
                 value="<?= e($defaults['app_base_url']) ?>">
          <p class="note">Where this app will be opened in a browser (local or live).</p>

          <h2>Superuser 1 (required)</h2>
          <label for="callsign1">Callsign</label>
          <input id="callsign1" name="callsign1" type="text" required autocomplete="username"
                 value="<?= e($defaults['callsign1']) ?>">

          <label for="password1">Password</label>
          <input id="password1" name="password1" type="password" required autocomplete="new-password">

          <label for="password1_confirm">Confirm password</label>
          <input id="password1_confirm" name="password1_confirm" type="password" required autocomplete="new-password">

          <h2>Superuser 2 (recommended)</h2>
          <p class="note">Avoid a single point of failure. Leave blank only for temporary local testing.</p>
          <label for="callsign2">Callsign</label>
          <input id="callsign2" name="callsign2" type="text" autocomplete="username"
                 value="<?= e($defaults['callsign2']) ?>">

          <label for="password2">Password</label>
          <input id="password2" name="password2" type="password" autocomplete="new-password">

          <label for="password2_confirm">Confirm password</label>
          <input id="password2_confirm" name="password2_confirm" type="password" autocomplete="new-password">

          <button type="submit">Save club setup</button>
        </form>
      <?php endif; ?>

      <p><a class="button" href="index.php">Back to status</a></p>
    </section>
  </main>
</body>
</html>

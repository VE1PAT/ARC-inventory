<?php
declare(strict_types=1);

/**
 * Recovery helper: create or reset a superuser.
 * Prefer install.php for first-time club setup.
 * Remove or protect this file on the public internet after go-live.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $callsign = strtoupper(trim((string) ($_POST['callsign'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($callsign === '' || $password === '') {
        $error = 'Callsign and password are required.';
    } elseif ($password !== $confirm) {
        $error = 'Password and confirmation do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $pdo = db();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = 'INSERT INTO users (callsign, password_hash, role, is_active, failed_login_count, locked_at)
                    VALUES (:c, :h, :r, 1, 0, NULL)
                    ON DUPLICATE KEY UPDATE
                      password_hash = VALUES(password_hash),
                      role = VALUES(role),
                      is_active = 1,
                      failed_login_count = 0,
                      locked_at = NULL';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':c' => $callsign,
                ':h' => $hash,
                ':r' => 'superuser',
            ]);
            $message = 'Superuser saved for ' . $callsign . '.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recovery superuser · <?= e(club_name()) ?></title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <header class="top">
    <div class="wrap">
      <h1>Create / reset superuser</h1>
      <p><?= e(club_name()) ?></p>
    </div>
  </header>
  <main class="wrap">
    <section class="card">
      <?php if ($message): ?><p class="ok"><?= e($message) ?></p><?php endif; ?>
      <?php if ($error): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>

      <p class="note">For a new club install, use <a href="install.php">install.php</a> instead (club name + website + superusers).</p>

      <form method="post" action="">
        <label for="callsign">Callsign</label>
        <input id="callsign" name="callsign" type="text" required autocomplete="username">

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">

        <label for="confirm">Confirm password</label>
        <input id="confirm" name="confirm" type="password" required autocomplete="new-password">

        <button type="submit">Save superuser</button>
      </form>
      <p><a class="button" href="index.php">Back to status</a></p>
    </section>
  </main>
</body>
</html>

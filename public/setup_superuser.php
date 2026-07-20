<?php
declare(strict_types=1);

/**
 * Recovery helper: create or reset a superuser.
 * Prefer install.php for first-time club setup.
 * After install, only a logged-in superuser may use this page.
 */
require dirname(__DIR__) . '/src/bootstrap.php';

$message = '';
$error = '';
$pdo = db();
$installed = Settings::isInstalled($pdo);
$currentUser = Auth::check($pdo);

if ($installed && ($currentUser === null || $currentUser['role'] !== 'superuser')) {
    $pageTitle = 'Recovery';
    render_header($pageTitle, $currentUser, 'Superuser recovery');
    echo '<section class="card">';
    echo '<p class="bad">Only a logged-in superuser can use this recovery page after install.</p>';
    echo '<p><a class="button" href="login.php">Log in</a></p>';
    echo '</section>';
    render_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
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
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = 'INSERT INTO users (callsign, password_hash, role, is_active, failed_login_count, locked_at, must_change_password)
                    VALUES (:c, :h, :r, 1, 0, NULL, 0)
                    ON DUPLICATE KEY UPDATE
                      password_hash = VALUES(password_hash),
                      role = VALUES(role),
                      is_active = 1,
                      failed_login_count = 0,
                      locked_at = NULL,
                      must_change_password = 0';
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

$pageTitle = 'Recovery';
render_header($pageTitle, $currentUser, 'Create / reset superuser');
?>
<section class="card">
  <?php if ($message): ?><p class="ok"><?= e($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>

  <p class="note">For a new club install, use <a href="install.php">install.php</a> instead.</p>

  <form method="post" action="setup_superuser.php">
    <?= csrf_field() ?>
    <label for="callsign">Callsign</label>
    <input id="callsign" name="callsign" type="text" required autocomplete="username">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="new-password">

    <label for="confirm">Confirm password</label>
    <input id="confirm" name="confirm" type="password" required autocomplete="new-password">

    <button type="submit">Save superuser</button>
  </form>
  <p><a class="button" href="home.php">Home</a></p>
</section>
<?php render_footer(); ?>

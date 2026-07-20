<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = null;
try {
    $pdo = db();
    if (!Settings::isInstalled($pdo)) {
        redirect('install.php');
    }
    if (Auth::check($pdo)) {
        redirect('home.php');
    }
} catch (Throwable $e) {
    // Fall through to show error on page if DB is down.
}

$error = '';
$callsign = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $callsign = strtoupper(trim((string) ($_POST['callsign'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!$pdo instanceof PDO) {
        $error = 'Database is not available. Check config and MySQL.';
    } else {
        $result = Auth::attempt($pdo, $callsign, $password);
        if ($result['ok']) {
            redirect('home.php');
        }
        $error = (string) $result['error'];
    }
}

$pageTitle = 'Log in';
$currentUser = null;
render_header($pageTitle, null, 'Log in');
?>
<section class="card">
  <?php if ($error !== ''): ?>
    <p class="bad"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" action="login.php" autocomplete="on">
    <?= csrf_field() ?>

    <label for="callsign">Callsign</label>
    <input id="callsign" name="callsign" type="text" required
           autocomplete="username" autocapitalize="characters"
           value="<?= e($callsign) ?>">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required
           autocomplete="current-password">

    <button type="submit">Log in</button>
  </form>
</section>

<section class="card">
  <h2>Need an account?</h2>
  <p>
    There is no public sign-up. Ask a club Admin or Superuser
    (often the membership chair) to create your login after you are a confirmed member.
  </p>
  <p><a href="help.php">More about getting access</a></p>
</section>
<?php render_footer(); ?>


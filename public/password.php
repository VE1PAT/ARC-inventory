<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireLogin($pdo, true);
$forced = !empty($currentUser['must_change_password']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        Users::changeOwnPassword(
            $pdo,
            $currentUser,
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );
        redirect('home.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Password';
$pageHeading = $forced ? 'Set a new password' : 'Change password';
render_header($pageTitle, $currentUser, $pageHeading);
?>
<section class="card">
  <?php if ($forced): ?>
    <p class="bad">You must set a new password before using the inventory app.</p>
    <p class="note">An admin gave you a temporary password. Choose one only you know (at least 8 characters).</p>
  <?php else: ?>
    <p class="note">Choose a new password (at least 8 characters).</p>
  <?php endif; ?>

  <?php if ($error !== ''): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>
  <?php if ($message !== ''): ?><p class="ok"><?= e($message) ?></p><?php endif; ?>

  <form method="post" action="password.php" autocomplete="off">
    <?= csrf_field() ?>

    <?php if (!$forced): ?>
      <label for="current_password">Current password</label>
      <input id="current_password" name="current_password" type="password" required
             autocomplete="current-password">
    <?php else: ?>
      <label for="current_password">Current (temporary) password</label>
      <input id="current_password" name="current_password" type="password"
             autocomplete="current-password"
             placeholder="Optional if you just logged in">
    <?php endif; ?>

    <label for="new_password">New password</label>
    <input id="new_password" name="new_password" type="password" required
           autocomplete="new-password" minlength="8">

    <label for="confirm_password">Confirm new password</label>
    <input id="confirm_password" name="confirm_password" type="password" required
           autocomplete="new-password" minlength="8">

    <button type="submit"><?= $forced ? 'Save and continue' : 'Update password' ?></button>
  </form>
</section>
<?php render_footer(); ?>

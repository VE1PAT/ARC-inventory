<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
if (!Settings::isInstalled($pdo)) {
    redirect('install.php');
}

$currentUser = Auth::requireLogin($pdo);
$unread = Auth::isAdminPlus($currentUser) ? Auth::unreadAlertCount($pdo) : 0;

$pageTitle = 'Home';
render_header($pageTitle, $currentUser, 'Home');
?>
<section class="card">
  <h2>Search inventory</h2>
  <form method="get" action="home.php" class="search-form">
    <label for="q">Keyword</label>
    <input id="q" name="q" type="text" placeholder="Description, model, serial, location…"
           value="<?= e((string) ($_GET['q'] ?? '')) ?>">
    <button type="submit">Search</button>
  </form>
  <p class="note">Full search will return results from multiple fields in the next build step.</p>
</section>

<section class="card">
  <h2>Quick actions</h2>
  <ul class="action-list">
    <li><a class="button block" href="home.php">My loans</a> <span class="note">(coming next)</span></li>
    <li><a class="button block" href="home.php">Pending witness</a> <span class="note">(coming next)</span></li>
    <?php if (Auth::isAdminPlus($currentUser)): ?>
      <li><a class="button block" href="alerts.php">Security alerts<?php if ($unread > 0): ?> · <?= (int) $unread ?> new<?php endif; ?></a></li>
    <?php endif; ?>
  </ul>
</section>

<section class="card">
  <h2>Signed in</h2>
  <p>
    <strong><?= e($currentUser['callsign']) ?></strong>
    · <?= e(role_label($currentUser['role'])) ?>
  </p>
  <p class="note">Use Help anytime if you are unsure about loans or witnessing.</p>
</section>
<?php render_footer(); ?>

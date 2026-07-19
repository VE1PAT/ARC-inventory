<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
if (!Settings::isInstalled($pdo)) {
    redirect('install.php');
}

$currentUser = Auth::requireLogin($pdo);
$unread = Auth::isAdminPlus($currentUser) ? Auth::unreadAlertCount($pdo) : 0;
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    redirect('search.php?q=' . rawurlencode($q));
}

$pageTitle = 'Home';
render_header($pageTitle, $currentUser, 'Home');
?>
<section class="card">
  <h2>Search inventory</h2>
  <form method="get" action="search.php" class="search-form">
    <label for="q">Keyword</label>
    <input id="q" name="q" type="text"
           placeholder="ID, description, model, serial, location…"
           value="">
    <button type="submit">Search</button>
  </form>
  <p class="note"><a href="search.php">Browse all items</a></p>
</section>

<section class="card">
  <h2>Quick actions</h2>
  <ul class="action-list">
    <li><a class="button block" href="search.php">Search / browse</a></li>
    <?php if (Auth::isAdminPlus($currentUser)): ?>
      <li><a class="button block" href="item_edit.php">Add item</a></li>
      <li><a class="button block" href="alerts.php">Security alerts<?php if ($unread > 0): ?> · <?= (int) $unread ?> new<?php endif; ?></a></li>
    <?php endif; ?>
    <li><a class="button block button-secondary" href="home.php">My loans</a> <span class="note">(next)</span></li>
    <li><a class="button block button-secondary" href="home.php">Pending witness</a> <span class="note">(next)</span></li>
  </ul>
</section>

<section class="card">
  <h2>Signed in</h2>
  <p>
    <strong><?= e($currentUser['callsign']) ?></strong>
    · <?= e(role_label($currentUser['role'])) ?>
  </p>
</section>
<?php render_footer(); ?>

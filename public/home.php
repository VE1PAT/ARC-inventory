<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
if (!Settings::isInstalled($pdo)) {
    redirect('install.php');
}

$currentUser = Auth::requireLogin($pdo);
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    redirect('search.php?q=' . rawurlencode($q));
}

$unread = Auth::isAdminPlus($currentUser) ? Auth::unreadAlertCount($pdo) : 0;
$pendingWitness = Loans::pendingCountForWitness($pdo, (int) $currentUser['id']);
$myLoanCount = count(Loans::myLoans($pdo, (int) $currentUser['id']));

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
    <li>
      <a class="button block" href="my_loans.php">
        My loans<?php if ($myLoanCount > 0): ?> · <?= (int) $myLoanCount ?><?php endif; ?>
      </a>
    </li>
    <li>
      <a class="button block" href="witness.php">
        Pending witness<?php if ($pendingWitness > 0): ?> · <?= (int) $pendingWitness ?><?php endif; ?>
      </a>
    </li>
    <?php if (Auth::isAdminPlus($currentUser)): ?>
      <li><a class="button block" href="item_edit.php">Add item</a></li>
      <li><a class="button block" href="import.php">CSV import</a></li>
      <li><a class="button block" href="members.php">Members</a></li>
      <li><a class="button block" href="reports.php">Reports</a></li>
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
</section>
<?php render_footer(); ?>

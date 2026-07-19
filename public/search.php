<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireLogin($pdo);
$adminView = Auth::isAdminPlus($currentUser);

$q = trim((string) ($_GET['q'] ?? ''));
$results = Items::search($pdo, $q, $adminView);

$pageTitle = 'Search';
render_header($pageTitle, $currentUser, $q !== '' ? 'Search results' : 'Browse inventory');
?>
<section class="card">
  <form method="get" action="search.php" class="search-form">
    <label for="q">Keyword</label>
    <input id="q" name="q" type="text" autofocus
           placeholder="ID, description, model, serial, location, borrower…"
           value="<?= e($q) ?>">
    <button type="submit">Search</button>
  </form>
  <?php if (!$adminView): ?>
    <p class="note">Sold and disposed items are hidden. Admins can still see them.</p>
  <?php else: ?>
    <p class="note">Admin view includes sold and disposed items.</p>
  <?php endif; ?>
</section>

<section class="card">
  <h2><?= $q === '' ? 'All items' : 'Results' ?> · <?= count($results) ?></h2>
  <?php if (!$results): ?>
    <p class="note">No items found.</p>
  <?php else: ?>
    <ul class="result-list">
      <?php foreach ($results as $item): ?>
        <li>
          <a href="item.php?id=<?= (int) $item['id'] ?>">
            <strong><?= e($item['description']) ?></strong>
            <span class="meta">
              <?= e($item['public_id']) ?>
              · <?= e(Items::typeLabel((string) $item['item_type'])) ?>
              · <?= e(Items::statusLabel((string) $item['status'])) ?>
              <?php if ((int) $item['not_for_loan'] === 1): ?> · Not for loan<?php endif; ?>
              <?php if ((int) $item['is_kit'] === 1): ?> · Kit<?php endif; ?>
              <?php if (!empty($item['borrower_callsign'])): ?> · With <?= e($item['borrower_callsign']) ?><?php endif; ?>
            </span>
            <?php if (!empty($item['location'])): ?>
              <span class="meta">Location: <?= e($item['location']) ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php if ($adminView): ?>
<section class="card">
  <a class="button block" href="item_edit.php">Add item</a>
</section>
<?php endif; ?>
<?php render_footer(); ?>

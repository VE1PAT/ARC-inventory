<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireLogin($pdo);
$loans = Loans::myLoans($pdo, (int) $currentUser['id']);

$pageTitle = 'My loans';
render_header($pageTitle, $currentUser);
?>
<section class="card">
  <h2>Items on loan to you</h2>
  <?php if (!$loans): ?>
    <p class="note">You have no active loans.</p>
  <?php else: ?>
    <ul class="result-list">
      <?php foreach ($loans as $row): ?>
        <li>
          <a href="item.php?id=<?= (int) $row['item_id'] ?>">
            <strong><?= e($row['description']) ?></strong>
            <span class="meta">
              <?= e($row['public_id']) ?>
              · since <?= e($row['loaned_at']) ?>
              <?php if ((int) $row['is_kit'] === 1): ?> · Kit<?php endif; ?>
            </span>
          </a>
          <a class="button" href="return_item.php?id=<?= (int) $row['item_id'] ?>">Return</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php render_footer(); ?>

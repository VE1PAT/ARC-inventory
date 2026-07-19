<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireLogin($pdo);
$adminView = Auth::isAdminPlus($currentUser);

$id = (int) ($_GET['id'] ?? 0);
$item = $id > 0 ? Items::findById($pdo, $id) : null;

if (!$item || !Items::visibleToUser($item, $adminView)) {
    http_response_code(404);
    $pageTitle = 'Not found';
    render_header($pageTitle, $currentUser);
    echo '<section class="card"><p class="bad">Item not found.</p><p><a class="button" href="search.php">Back to search</a></p></section>';
    render_footer();
    exit;
}

$borrower = Items::currentBorrower($pdo, (int) $item['id']);
$includes = (int) $item['is_kit'] === 1 ? Items::kitIncludes($pdo, (int) $item['id']) : [];

$canLoan = $adminView || (
    (int) $item['not_for_loan'] === 0
    && $item['status'] === 'available'
);

$pageTitle = $item['public_id'];
render_header($pageTitle, $currentUser, $item['description']);
?>
<section class="card">
  <?php if (!empty($item['photo_path'])): ?>
    <img class="item-photo" src="<?= e($item['photo_path']) ?>" alt="Photo of <?= e($item['description']) ?>">
  <?php endif; ?>

  <h2><?= e($item['description']) ?></h2>
  <p>
    <span class="pill"><?= e(Items::statusLabel((string) $item['status'])) ?></span>
    <?php if ((int) $item['not_for_loan'] === 1): ?><span class="pill muted">Not for loan</span><?php endif; ?>
    <?php if ((int) $item['is_kit'] === 1): ?><span class="pill">Kit</span><?php endif; ?>
  </p>

  <dl class="detail-list">
    <div><dt>System ID</dt><dd><?= e($item['public_id']) ?></dd></div>
    <?php if (!empty($item['club_id'])): ?>
      <div><dt>Club ID</dt><dd><?= e($item['club_id']) ?></dd></div>
    <?php endif; ?>
    <div><dt>Type</dt><dd><?= e(Items::typeLabel((string) $item['item_type'])) ?></dd></div>
    <?php if (!empty($item['manufacturer'])): ?>
      <div><dt>Manufacturer</dt><dd><?= e($item['manufacturer']) ?></dd></div>
    <?php endif; ?>
    <?php if (!empty($item['model'])): ?>
      <div><dt>Model</dt><dd><?= e($item['model']) ?></dd></div>
    <?php endif; ?>
    <?php if (!empty($item['serial_number'])): ?>
      <div><dt>Serial</dt><dd><?= e($item['serial_number']) ?></dd></div>
    <?php endif; ?>
    <?php if (!empty($item['location'])): ?>
      <div><dt>Location</dt><dd><?= e($item['location']) ?></dd></div>
    <?php endif; ?>
    <?php if (!empty($item['condition_note'])): ?>
      <div><dt>Condition</dt><dd><?= e($item['condition_note']) ?></dd></div>
    <?php endif; ?>
    <?php if (!empty($item['source_note'])): ?>
      <div><dt>Source</dt><dd><?= e($item['source_note']) ?></dd></div>
    <?php endif; ?>
    <?php if ($borrower): ?>
      <div><dt>On loan to</dt><dd><?= e($borrower['callsign']) ?> since <?= e($borrower['loaned_at']) ?></dd></div>
    <?php endif; ?>
  </dl>

  <?php if (!empty($item['notes'])): ?>
    <h3>Notes</h3>
    <p><?= nl2br(e($item['notes'])) ?></p>
  <?php endif; ?>
</section>

<?php if ((int) $item['is_kit'] === 1): ?>
<section class="card">
  <h2>Kit includes</h2>
  <?php if (!$includes): ?>
    <p class="note">No include lines yet.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($includes as $line): ?>
        <li><?= e($line['line_label']) ?></li>
      <?php endforeach; ?>
    </ul>
    <p class="note">On loan and return, every line must be verified with a confirmation checkbox.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
  <h2>Actions</h2>
  <?php if ($canLoan && $item['status'] === 'available' && (int) $item['not_for_loan'] === 0): ?>
    <p class="note">Loan / return with Witness will be the next build step.</p>
    <button type="button" class="button block" disabled>Loan (coming next)</button>
  <?php elseif ($item['status'] === 'on_loan'): ?>
    <p class="note">Return with Witness will be the next build step.</p>
    <button type="button" class="button block" disabled>Return (coming next)</button>
  <?php elseif ((int) $item['not_for_loan'] === 1): ?>
    <p class="note">This item is marked <strong>Not for loan</strong> (inventoried fixed / station gear).</p>
  <?php endif; ?>

  <?php if ($adminView): ?>
    <a class="button block" href="item_edit.php?id=<?= (int) $item['id'] ?>">Edit item</a>
  <?php endif; ?>
  <a class="button block button-secondary" href="search.php">Back to search</a>
</section>
<?php render_footer(); ?>

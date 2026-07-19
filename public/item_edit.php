<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireRole($pdo, ['admin', 'superuser']);

$id = (int) ($_GET['id'] ?? 0);
$item = $id > 0 ? Items::findById($pdo, $id) : null;
$isEdit = $item !== null;

$error = '';
$kitText = $isEdit ? Items::kitLinesToText(Items::kitIncludes($pdo, (int) $item['id'])) : '';

$form = $isEdit ? [
    'club_id' => (string) ($item['club_id'] ?? ''),
    'item_type' => (string) $item['item_type'],
    'description' => (string) $item['description'],
    'manufacturer' => (string) ($item['manufacturer'] ?? ''),
    'model' => (string) ($item['model'] ?? ''),
    'serial_number' => (string) ($item['serial_number'] ?? ''),
    'location' => (string) ($item['location'] ?? ''),
    'condition_note' => (string) ($item['condition_note'] ?? ''),
    'source_note' => (string) ($item['source_note'] ?? ''),
    'notes' => (string) ($item['notes'] ?? ''),
    'status' => (string) $item['status'],
    'not_for_loan' => (int) $item['not_for_loan'] === 1,
    'is_kit' => (int) $item['is_kit'] === 1,
] : [
    'club_id' => '',
    'item_type' => 'radio',
    'description' => '',
    'manufacturer' => '',
    'model' => '',
    'serial_number' => '',
    'location' => '',
    'condition_note' => '',
    'source_note' => '',
    'notes' => '',
    'status' => 'available',
    'not_for_loan' => false,
    'is_kit' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form = [
        'club_id' => (string) ($_POST['club_id'] ?? ''),
        'item_type' => (string) ($_POST['item_type'] ?? 'other'),
        'description' => (string) ($_POST['description'] ?? ''),
        'manufacturer' => (string) ($_POST['manufacturer'] ?? ''),
        'model' => (string) ($_POST['model'] ?? ''),
        'serial_number' => (string) ($_POST['serial_number'] ?? ''),
        'location' => (string) ($_POST['location'] ?? ''),
        'condition_note' => (string) ($_POST['condition_note'] ?? ''),
        'source_note' => (string) ($_POST['source_note'] ?? ''),
        'notes' => (string) ($_POST['notes'] ?? ''),
        'status' => (string) ($_POST['status'] ?? 'available'),
        'not_for_loan' => !empty($_POST['not_for_loan']),
        'is_kit' => !empty($_POST['is_kit']),
    ];
    $kitText = (string) ($_POST['kit_includes'] ?? '');

    $data = Items::normalize($form);
    if ($data['description'] === '') {
        $error = 'Description is required.';
    } elseif ($isEdit && $item['status'] === 'on_loan' && $data['status'] !== 'on_loan') {
        // Allow admin status fix, but warn via allowing it — loans module will reconcile later.
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();
            $kitLines = Items::parseKitLines($kitText);

            if ($isEdit) {
                Items::update($pdo, $item, $data, (int) $currentUser['id'], $kitLines);
                $itemId = (int) $item['id'];
            } else {
                $itemId = Items::create($pdo, $data, (int) $currentUser['id'], $kitLines);
            }

            if (!empty($_FILES['photo'])) {
                Items::savePhoto($pdo, $itemId, $_FILES['photo'], (int) $currentUser['id']);
            }

            $pdo->commit();
            redirect('item.php?id=' . $itemId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit item' : 'Add item';
render_header($pageTitle, $currentUser);
?>
<section class="card">
  <?php if ($error !== ''): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>
  <?php if ($isEdit): ?>
    <p class="note">System ID: <strong><?= e($item['public_id']) ?></strong> (auto-generated, not editable)</p>
  <?php else: ?>
    <p class="note">A unique system ID is created automatically when you save.</p>
  <?php endif; ?>

  <form method="post" action="item_edit.php<?= $isEdit ? '?id=' . (int) $item['id'] : '' ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <label for="description">Description</label>
    <input id="description" name="description" type="text" required value="<?= e($form['description']) ?>">

    <label for="item_type">Type</label>
    <select id="item_type" name="item_type">
      <?php foreach (Items::TYPES as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $form['item_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="status">Status</label>
    <select id="status" name="status">
      <?php foreach (Items::STATUSES as $value => $label): ?>
        <?php
          // "On loan" is set by the loan workflow; only show it when already on loan.
          if ($value === 'on_loan' && (!$isEdit || $item['status'] !== 'on_loan')) {
              continue;
          }
        ?>
        <option value="<?= e($value) ?>" <?= $form['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="note">Use Loan/Return (next step) for normal lending. Sold/Disposed need no money fields — add a note if useful.</p>

    <label class="check">
      <input type="checkbox" name="not_for_loan" value="1" <?= $form['not_for_loan'] ? 'checked' : '' ?>>
      Not for loan (fixed / station gear)
    </label>

    <label class="check">
      <input type="checkbox" name="is_kit" value="1" id="is_kit" <?= $form['is_kit'] ? 'checked' : '' ?>>
      This is a kit / Go-Kit
    </label>

    <div id="kit_block">
      <label for="kit_includes">Kit includes (one item per line)</label>
      <textarea id="kit_includes" name="kit_includes" rows="6" placeholder="Radio&#10;Battery&#10;Charger&#10;Antenna"><?= e($kitText) ?></textarea>
    </div>

    <label for="club_id">Club ID (optional)</label>
    <input id="club_id" name="club_id" type="text" value="<?= e($form['club_id']) ?>">

    <label for="manufacturer">Manufacturer</label>
    <input id="manufacturer" name="manufacturer" type="text" value="<?= e($form['manufacturer']) ?>">

    <label for="model">Model</label>
    <input id="model" name="model" type="text" value="<?= e($form['model']) ?>">

    <label for="serial_number">Serial number</label>
    <input id="serial_number" name="serial_number" type="text" value="<?= e($form['serial_number']) ?>">

    <label for="location">Location</label>
    <input id="location" name="location" type="text" value="<?= e($form['location']) ?>">

    <label for="condition_note">Condition</label>
    <input id="condition_note" name="condition_note" type="text" value="<?= e($form['condition_note']) ?>">

    <label for="source_note">Source / donor note</label>
    <input id="source_note" name="source_note" type="text" placeholder="Purchased / SK estate / donation…"
           value="<?= e($form['source_note']) ?>">

    <label for="notes">Notes</label>
    <textarea id="notes" name="notes" rows="4"><?= e($form['notes']) ?></textarea>

    <label for="photo">Photo (optional)</label>
    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
    <?php if ($isEdit && !empty($item['photo_path'])): ?>
      <p class="note">Current photo will be replaced if you choose a new file.</p>
      <img class="item-photo small" src="<?= e($item['photo_path']) ?>" alt="Current photo">
    <?php endif; ?>

    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create item' ?></button>
  </form>
</section>

<script>
  (function () {
    var box = document.getElementById('is_kit');
    var block = document.getElementById('kit_block');
    var type = document.getElementById('item_type');
    function sync() {
      var show = box.checked || (type && type.value === 'kit');
      block.style.display = show ? 'block' : 'none';
      if (type && type.value === 'kit') box.checked = true;
    }
    box.addEventListener('change', sync);
    if (type) type.addEventListener('change', sync);
    sync();
  })();
</script>
<?php render_footer(); ?>

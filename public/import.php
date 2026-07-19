<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireRole($pdo, ['admin', 'superuser']);

if (isset($_GET['template'])) {
    Csv::download('inventory-import-template.csv', Import::HEADERS, Import::templateRows());
}

$message = '';
$error = '';
$errors = [];
$created = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $rows = Csv::readUploaded($_FILES['csv'] ?? []);
        if (!$rows) {
            throw new RuntimeException('CSV contained no data rows.');
        }
        // Per-row create/commit so one bad line does not block the rest.
        $result = Import::importRows($pdo, $rows, (int) $currentUser['id']);
        $created = (int) $result['created'];
        $errors = $result['errors'];
        $message = 'Import finished. Created ' . $created . ' item(s).';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'CSV import';
render_header($pageTitle, $currentUser);
?>
<section class="card">
  <h2>Bulk import</h2>
  <p>Download the CSV template (opens in LibreOffice Calc), fill rows, then upload.</p>
  <p><a class="button" href="import.php?template=1">Download CSV template</a></p>
  <p class="note">
    Required column: <code>description</code>.
    Kit includes: separate with <code>|</code> or <code>;</code>.
    Status <code>on_loan</code> in the file is treated as <code>available</code> (use Loan in the app).
  </p>
</section>

<section class="card">
  <h2>Upload CSV</h2>
  <?php if ($message !== ''): ?><p class="ok"><?= e($message) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="bad"><?= e($error) ?></p><?php endif; ?>
  <?php if ($errors): ?>
    <p class="bad">Some rows failed:</p>
    <ul>
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" action="import.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="csv">CSV file</label>
    <input id="csv" name="csv" type="file" accept=".csv,text/csv" required>
    <button type="submit">Import</button>
  </form>
</section>
<?php render_footer(); ?>

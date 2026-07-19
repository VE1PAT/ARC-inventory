<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireRole($pdo, ['admin', 'superuser']);

$report = (string) ($_GET['report'] ?? '');
$csv = isset($_GET['csv']);
$status = (string) ($_GET['status'] ?? '');
$notForLoan = (string) ($_GET['not_for_loan'] ?? '');
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');

if ($report !== '' && $csv) {
    if ($report === 'on_loan') {
        $rows = Reports::currentlyOnLoan($pdo);
        Csv::download('report-on-loan.csv', [
            'public_id', 'description', 'item_type', 'location', 'borrower_callsign', 'loaned_at',
        ], $rows);
    }
    if ($report === 'inventory') {
        $nfl = $notForLoan === '' ? null : ($notForLoan === '1');
        $rows = Reports::inventoryByStatus($pdo, $status !== '' ? $status : null, $nfl);
        Csv::download('report-inventory.csv', [
            'public_id', 'club_id', 'item_type', 'description', 'manufacturer', 'model',
            'serial_number', 'location', 'status', 'not_for_loan', 'is_kit', 'source_note',
        ], $rows);
    }
    if ($report === 'aging') {
        $rows = Reports::aging($pdo, 12);
        Csv::download('report-aging-12-months.csv', [
            'public_id', 'description', 'item_type', 'location', 'status', 'last_loaned_at',
        ], $rows);
    }
    if ($report === 'sold') {
        $rows = Reports::soldDisposed($pdo, $from !== '' ? $from : null, $to !== '' ? $to : null);
        Csv::download('report-sold-disposed.csv', [
            'public_id', 'description', 'item_type', 'status', 'source_note', 'notes', 'updated_at',
        ], $rows);
    }
}

$pageTitle = 'Reports';
$heading = match ($report) {
    'on_loan' => 'Currently on loan',
    'inventory' => 'Inventory by status',
    'aging' => 'Aging / not loaned in 12 months',
    'sold' => 'Sold / disposed',
    'summary' => 'Monthly / AGM summary',
    default => 'Reports',
};
render_header($pageTitle, $currentUser, $heading);

if ($report === ''):
?>
<section class="card">
  <h2>Available reports</h2>
  <ul class="action-list">
    <li><a class="button block" href="reports.php?report=summary">Monthly / AGM summary</a></li>
    <li><a class="button block" href="reports.php?report=on_loan">Currently on loan</a></li>
    <li><a class="button block" href="reports.php?report=inventory">Inventory by status</a></li>
    <li><a class="button block" href="reports.php?report=aging">Aging (no loan in 12 months)</a></li>
    <li><a class="button block" href="reports.php?report=sold">Sold / disposed</a></li>
  </ul>
  <p class="note">Each detail report can download CSV for LibreOffice / spreadsheets.</p>
</section>
<?php
render_footer();
exit;
endif;

if ($report === 'summary') {
    $summary = Reports::summary($pdo);
    ?>
    <section class="card report-hero">
      <h2><?= e(club_name()) ?></h2>
      <p class="note">Inventory summary for meetings / AGM presentation</p>
      <div class="stat-grid">
        <div><strong><?= (int) $summary['total_items'] ?></strong><span>Total items</span></div>
        <div><strong><?= (int) $summary['on_loan'] ?></strong><span>On loan now</span></div>
        <div><strong><?= (int) $summary['not_for_loan'] ?></strong><span>Not for loan</span></div>
        <div><strong><?= (int) $summary['kits'] ?></strong><span>Kits</span></div>
        <div><strong><?= (int) $summary['donation_like'] ?></strong><span>Donation / estate-tagged</span></div>
        <div><strong><?= (int) $summary['active_members'] ?></strong><span>Active logins</span></div>
      </div>
    </section>
    <section class="card">
      <h2>By status</h2>
      <ul class="plain-list">
        <?php foreach ($summary['by_status'] as $row): ?>
          <li>
            <strong><?= e(Items::statusLabel((string) $row['status'])) ?></strong>
            <span><?= (int) $row['cnt'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="card">
      <h2>Recent ledger activity</h2>
      <ul class="plain-list">
        <?php foreach ($summary['recent'] as $row): ?>
          <li>
            <div>
              <strong><?= e($row['event_type']) ?></strong>
              <div class="note"><?= e($row['created_at']) ?></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <p><a class="button button-secondary" href="reports.php">All reports</a></p>
    </section>
    <?php
    render_footer();
    exit;
}

$csvUrl = 'reports.php?report=' . rawurlencode($report) . '&csv=1';
if ($report === 'inventory') {
    $csvUrl .= '&status=' . rawurlencode($status) . '&not_for_loan=' . rawurlencode($notForLoan);
}
if ($report === 'sold') {
    $csvUrl .= '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to);
}
?>
<section class="card">
  <div class="row-between">
    <h2><?= e($heading) ?></h2>
    <a class="button" href="<?= e($csvUrl) ?>">Download CSV</a>
  </div>

  <?php if ($report === 'inventory'): ?>
    <form method="get" action="reports.php" class="filter-form">
      <input type="hidden" name="report" value="inventory">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <?php foreach (Items::STATUSES as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="not_for_loan">Not for loan</label>
      <select id="not_for_loan" name="not_for_loan">
        <option value="" <?= $notForLoan === '' ? 'selected' : '' ?>>All</option>
        <option value="1" <?= $notForLoan === '1' ? 'selected' : '' ?>>Not for loan only</option>
        <option value="0" <?= $notForLoan === '0' ? 'selected' : '' ?>>Loanable only</option>
      </select>
      <button type="submit">Apply</button>
    </form>
  <?php endif; ?>

  <?php if ($report === 'sold'): ?>
    <form method="get" action="reports.php" class="filter-form">
      <input type="hidden" name="report" value="sold">
      <label for="from">From</label>
      <input id="from" name="from" type="date" value="<?= e($from) ?>">
      <label for="to">To</label>
      <input id="to" name="to" type="date" value="<?= e($to) ?>">
      <button type="submit">Apply</button>
    </form>
  <?php endif; ?>

  <?php
    $rows = match ($report) {
        'on_loan' => Reports::currentlyOnLoan($pdo),
        'inventory' => Reports::inventoryByStatus(
            $pdo,
            $status !== '' ? $status : null,
            $notForLoan === '' ? null : ($notForLoan === '1')
        ),
        'aging' => Reports::aging($pdo, 12),
        'sold' => Reports::soldDisposed($pdo, $from !== '' ? $from : null, $to !== '' ? $to : null),
        default => [],
    };
  ?>

  <p class="note"><?= count($rows) ?> row(s)</p>
  <?php if (!$rows): ?>
    <p>No rows for this report.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <?php foreach (array_keys($rows[0]) as $col): ?>
              <th><?= e(str_replace('_', ' ', (string) $col)) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <?php foreach ($row as $value): ?>
                <td><?= e((string) $value) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <p><a class="button button-secondary" href="reports.php">All reports</a></p>
</section>
<?php render_footer(); ?>

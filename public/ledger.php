<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireRole($pdo, ['admin', 'superuser']);

$filters = [
    'event_type' => trim((string) ($_GET['event_type'] ?? '')),
    'callsign' => trim((string) ($_GET['callsign'] ?? '')),
    'item_q' => trim((string) ($_GET['item_q'] ?? '')),
    'from' => trim((string) ($_GET['from'] ?? '')),
    'to' => trim((string) ($_GET['to'] ?? '')),
];

$rows = Ledger::search($pdo, $filters);
$eventTypes = Ledger::distinctEventTypes($pdo);

if (isset($_GET['csv'])) {
    $csvRows = [];
    foreach ($rows as $row) {
        $csvRows[] = [
            'created_at' => $row['created_at'],
            'event_type' => $row['event_type'],
            'public_id' => $row['public_id'] ?? '',
            'description' => $row['description'] ?? '',
            'actor_callsign' => $row['actor_callsign'] ?? '',
            'witness_callsign' => $row['witness_callsign'] ?? '',
            'details' => Ledger::detailsSummary($row['details_json'] ?? null),
        ];
    }
    Csv::download('ledger.csv', [
        'created_at', 'event_type', 'public_id', 'description',
        'actor_callsign', 'witness_callsign', 'details',
    ], $csvRows);
}

$pageTitle = 'Ledger';
render_header($pageTitle, $currentUser);
?>
<section class="card">
  <div class="row-between">
    <h2>Accountability ledger</h2>
    <a class="button" href="ledger.php?<?= e(http_build_query(array_merge($filters, ['csv' => '1']))) ?>">Download CSV</a>
  </div>
  <p class="note">Append-only history. Rows are never edited or deleted.</p>

  <form method="get" action="ledger.php" class="filter-form">
    <label for="event_type">Event</label>
    <select id="event_type" name="event_type">
      <option value="">All</option>
      <?php foreach ($eventTypes as $type): ?>
        <option value="<?= e((string) $type) ?>" <?= $filters['event_type'] === $type ? 'selected' : '' ?>>
          <?= e(Ledger::eventLabel((string) $type)) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="callsign">Actor / witness callsign</label>
    <input id="callsign" name="callsign" type="text" value="<?= e($filters['callsign']) ?>">

    <label for="item_q">Item ID / description</label>
    <input id="item_q" name="item_q" type="text" value="<?= e($filters['item_q']) ?>">

    <label for="from">From</label>
    <input id="from" name="from" type="date" value="<?= e($filters['from']) ?>">

    <label for="to">To</label>
    <input id="to" name="to" type="date" value="<?= e($filters['to']) ?>">

    <button type="submit">Filter</button>
  </form>
</section>

<section class="card">
  <h2>Results · <?= count($rows) ?></h2>
  <?php if (!$rows): ?>
    <p class="note">No ledger rows match.</p>
  <?php else: ?>
    <ul class="plain-list">
      <?php foreach ($rows as $row): ?>
        <li>
          <div>
            <strong><?= e(Ledger::eventLabel((string) $row['event_type'])) ?></strong>
            <div class="note"><?= e($row['created_at']) ?></div>
            <?php if (!empty($row['public_id'])): ?>
              <div>
                <a href="item.php?id=<?= (int) $row['item_id'] ?>">
                  <?= e($row['public_id']) ?> · <?= e((string) $row['description']) ?>
                </a>
              </div>
            <?php endif; ?>
            <div class="note">
              <?php if (!empty($row['actor_callsign'])): ?>Actor <?= e($row['actor_callsign']) ?><?php endif; ?>
              <?php if (!empty($row['witness_callsign'])): ?>
                · Witness <?= e($row['witness_callsign']) ?>
              <?php endif; ?>
            </div>
            <?php
              $summary = Ledger::detailsSummary($row['details_json'] ?? null);
              if ($summary !== ''):
            ?>
              <div class="note"><?= e($summary) ?></div>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php render_footer(); ?>

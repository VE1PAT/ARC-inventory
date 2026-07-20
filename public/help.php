<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::check($pdo);
$guest = $currentUser === null;

$pageTitle = 'Help';
$pageHeading = $guest ? 'How to get access' : 'Help';
render_header($pageTitle, $currentUser, $pageHeading);
?>

<?php if ($guest): ?>
<section class="card">
  <h2>Need an account?</h2>
  <p>
    Ask a club <strong>Admin</strong> or <strong>Superuser</strong>
    (often the membership chair) to create your login after you are confirmed as a club member.
  </p>
  <p>There is <strong>no public sign-up</strong>. Only callsign and password are stored here — not your personal membership details.</p>
  <p><a class="button" href="login.php">Back to log in</a></p>
</section>

<section class="card">
  <h2>Logging in</h2>
  <p>Use your club callsign and the password an admin set for you.</p>
</section>

<section class="card">
  <h2>Locked account</h2>
  <p>
    After 3 failed login attempts your account locks.
    Contact a club Admin or Superuser to unlock it.
  </p>
</section>
<?php else: ?>

<section class="card">
  <h2>Logging in</h2>
  <p>Use your club callsign and the password an admin set for you. Accounts are not created by self sign-up.</p>
</section>

<section class="card">
  <h2>Locked account</h2>
  <p>After 3 failed login attempts your account locks. An Admin or Superuser is notified in <strong>Alerts</strong> and can unlock you.</p>
</section>

<section class="card">
  <h2>Search</h2>
  <p>Use one keyword box on Home or Search. It looks across system ID, club ID, description, manufacturer, model, serial, location, notes, source, and current borrower callsign.</p>
</section>

<section class="card">
  <h2>Not for loan</h2>
  <p>Some gear is inventoried but never loaned (repeaters, tower sections, club servers). Admins mark these <strong>Not for loan</strong>. You can still view them.</p>
</section>

<section class="card">
  <h2>Kits / Go-Kits</h2>
  <p>A kit has an includes list. When loan/return is enabled, you must confirm every included item is present before the transaction can finish.</p>
</section>

<section class="card">
  <h2>Loans and Witness</h2>
  <p>To loan or return an item, a second club member (the <strong>Witness</strong>) must confirm.</p>
  <ul>
    <li><strong>Same device (primary):</strong> witness types callsign + password on the same phone/laptop.</li>
    <li><strong>Remote:</strong> pick a witness callsign; they approve under <strong>Witness</strong> within 48 hours.</li>
  </ul>
  <p>Witness cannot be the borrower/returner. Kits require checking every include line plus the confirmation checkbox.</p>
</section>

<section class="card">
  <h2>Admin override</h2>
  <p>Admins and Superusers may complete a loan/return without a witness when needed. That override is written to the ledger.</p>
</section>

<section class="card">
  <h2>Need an account?</h2>
  <p>Ask a club Admin or Superuser (often the membership chair) to create your login after you are confirmed as a club member.</p>
</section>

<?php if (Auth::isAdminPlus($currentUser)): ?>
<section class="card">
  <h2>Admin tools</h2>
  <ul>
    <li><strong>Members</strong> — create logins (callsign only), set roles, deactivate, unlock.</li>
    <li><strong>Import</strong> — download CSV template, bulk-add items (LibreOffice-friendly).</li>
    <li><strong>Reports</strong> — on-loan list, aging, sold/disposed, and a large AGM/monthly summary.</li>
    <li><strong>Ledger</strong> — append-only accountability log (filter by event, callsign, item, dates; CSV download).</li>
  </ul>
</section>
<?php endif; ?>

<?php endif; ?>
<?php render_footer(); ?>

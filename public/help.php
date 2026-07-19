<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$pdo = db();
$currentUser = Auth::requireLogin($pdo);

$pageTitle = 'Help';
render_header($pageTitle, $currentUser);
?>
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
  <p>When loan and return screens are ready, a second club member (the <strong>Witness</strong>) must confirm on the same device, or remotely in the app within 48 hours.</p>
</section>

<section class="card">
  <h2>Need an account?</h2>
  <p>Ask a club Admin or Superuser (often the membership chair) to create your login after you are confirmed as a club member.</p>
</section>
<?php render_footer(); ?>

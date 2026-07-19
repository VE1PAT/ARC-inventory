<?php
declare(strict_types=1);
/** @var array|null $currentUser */
/** @var string $pageTitle */
/** @var string|null $pageHeading */

$club = club_name();
$title = ($pageTitle ?? 'Home') . ' · ' . $club;
$heading = $pageHeading ?? ($pageTitle ?? $club);
$alertCount = 0;
$pendingWitness = 0;
if (!empty($currentUser)) {
    try {
        $pendingWitness = Loans::pendingCountForWitness(db(), (int) $currentUser['id']);
        if (Auth::isAdminPlus($currentUser)) {
            $alertCount = Auth::unreadAlertCount(db());
        }
    } catch (Throwable $e) {
        $alertCount = 0;
        $pendingWitness = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <header class="top">
    <div class="wrap top-row">
      <div>
        <h1><?= e($club) ?></h1>
        <p><?= e($heading) ?></p>
      </div>
      <?php if (!empty($currentUser)): ?>
        <div class="user-chip">
          <strong><?= e($currentUser['callsign']) ?></strong>
          <span><?= e(role_label($currentUser['role'])) ?></span>
        </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($currentUser)): ?>
      <nav class="wrap nav">
        <a href="home.php">Home</a>
        <a href="search.php">Search</a>
        <a href="my_loans.php">My loans</a>
        <a href="witness.php">Witness<?php if ($pendingWitness > 0): ?> (<?= (int) $pendingWitness ?>)<?php endif; ?></a>
        <?php if (Auth::isAdminPlus($currentUser)): ?>
          <a href="item_edit.php">Add</a>
          <a href="alerts.php">Alerts<?php if ($alertCount > 0): ?> (<?= (int) $alertCount ?>)<?php endif; ?></a>
        <?php endif; ?>
        <a href="help.php">Help</a>
        <a href="logout.php">Log out</a>
      </nav>
    <?php endif; ?>
  </header>
  <main class="wrap">

<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $pdo = db();
    if (!Settings::isInstalled($pdo)) {
        redirect('install.php');
    }
    if (Auth::check($pdo)) {
        redirect('home.php');
    }
    redirect('login.php');
} catch (Throwable $e) {
    $pageTitle = 'Setup status';
    $currentUser = null;
    render_header($pageTitle, null, 'Setup needed');
    echo '<section class="card">';
    echo '<p class="bad">Database connection failed.</p>';
    echo '<p class="note">' . e($e->getMessage()) . '</p>';
    echo '<p>Check <code>config/config.php</code> and import <code>sql/001_schema.sql</code>.</p>';
    echo '</section>';
    render_footer();
}

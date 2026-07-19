<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/config.php';

if (!is_readable($configPath)) {
    http_response_code(500);
    echo '<h1>ARC Inventory — setup needed</h1>';
    echo '<p>Copy <code>config/config.example.php</code> to <code>config/config.php</code> and edit database settings.</p>';
    echo '<p>See <code>README.md</code> for the full command list.</p>';
    exit;
}

/** @var array $config */
$config = require $configPath;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Ledger.php';
require_once __DIR__ . '/Items.php';
require_once __DIR__ . '/helpers.php';

session_name($config['security']['session_name'] ?? 'arc_inventory_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function app_config(): array
{
    global $config;
    return $config;
}

function db(): PDO
{
    return Database::connection(app_config()['db']);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function club_name(): string
{
    try {
        return Settings::get(db(), 'club_name', 'ARC Inventory') ?? 'ARC Inventory';
    } catch (Throwable $e) {
        return 'ARC Inventory';
    }
}

function club_website(): string
{
    try {
        return Settings::get(db(), 'club_website', '') ?? '';
    } catch (Throwable $e) {
        return '';
    }
}

function render_header(string $pageTitle, ?array $currentUser = null, ?string $pageHeading = null): void
{
    require dirname(__DIR__) . '/templates/layout_header.php';
}

function render_footer(): void
{
    require dirname(__DIR__) . '/templates/layout_footer.php';
}

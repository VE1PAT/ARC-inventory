<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/config.php';
$examplePath = dirname(__DIR__) . '/config/config.example.php';

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

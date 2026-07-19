<?php
/**
 * Copy this file to config.php and adjust for your machine / server.
 * config.php is gitignored — never commit real passwords.
 */
declare(strict_types=1);

return [
    'app_name' => 'ARC Inventory',
    'base_url' => 'http://localhost/arc-inventory/public',

    // XAMPP default MySQL is often user "root" with empty password.
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'arc_inventory',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],

    // Login lockout
    'security' => [
        'max_failed_logins' => 3,
        'session_name' => 'arc_inventory_session',
    ],
];

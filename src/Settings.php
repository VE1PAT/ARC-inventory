<?php
declare(strict_types=1);

final class Settings
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public static function all(PDO $pdo): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];
        try {
            $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (Throwable $e) {
            // Table may not exist yet before 002 migration.
            self::$cache = [];
        }

        return self::$cache;
    }

    public static function get(PDO $pdo, string $key, ?string $default = null): ?string
    {
        $all = self::all($pdo);
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(PDO $pdo, string $key, string $value): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([':k' => $key, ':v' => $value]);
        self::$cache = null;
    }

    public static function isInstalled(PDO $pdo): bool
    {
        $club = self::get($pdo, 'club_name');
        if ($club === null || trim($club) === '') {
            return false;
        }

        try {
            $count = (int) $pdo->query(
                "SELECT COUNT(*) FROM users WHERE role = 'superuser' AND is_active = 1"
            )->fetchColumn();
            return $count >= 1;
        } catch (Throwable $e) {
            return false;
        }
    }
}

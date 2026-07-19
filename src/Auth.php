<?php
declare(strict_types=1);

final class Auth
{
    public static function attempt(PDO $pdo, string $callsign, string $password): array
    {
        $callsign = strtoupper(trim($callsign));
        $maxFails = (int) (app_config()['security']['max_failed_logins'] ?? 3);

        if ($callsign === '' || $password === '') {
            return ['ok' => false, 'error' => 'Enter callsign and password.'];
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE callsign = :c LIMIT 1');
        $stmt->execute([':c' => $callsign]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['ok' => false, 'error' => 'Invalid callsign or password.'];
        }

        if (!(int) $user['is_active']) {
            return ['ok' => false, 'error' => 'This account is inactive. Contact a club admin.'];
        }

        if (!empty($user['locked_at'])) {
            return ['ok' => false, 'error' => 'This account is locked after failed logins. An admin must unlock it.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $fails = (int) $user['failed_login_count'] + 1;
            if ($fails >= $maxFails) {
                $lock = $pdo->prepare(
                    'UPDATE users
                     SET failed_login_count = :f, locked_at = NOW()
                     WHERE id = :id'
                );
                $lock->execute([':f' => $fails, ':id' => $user['id']]);

                self::addSecurityAlert(
                    $pdo,
                    'account_locked',
                    $callsign,
                    'Account locked after ' . $maxFails . ' failed login attempts: ' . $callsign
                );

                return ['ok' => false, 'error' => 'Account locked after too many failed attempts. An admin has been notified.'];
            }

            $upd = $pdo->prepare('UPDATE users SET failed_login_count = :f WHERE id = :id');
            $upd->execute([':f' => $fails, ':id' => $user['id']]);

            $left = $maxFails - $fails;
            return [
                'ok' => false,
                'error' => 'Invalid callsign or password. ' . $left . ' attempt(s) remaining before lockout.',
            ];
        }

        $clear = $pdo->prepare(
            'UPDATE users SET failed_login_count = 0, locked_at = NULL WHERE id = :id'
        );
        $clear->execute([':id' => $user['id']]);

        self::loginSession($user);
        return ['ok' => true, 'user' => self::publicUser($user)];
    }

    public static function loginSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['callsign'] = (string) $user['callsign'];
        $_SESSION['role'] = (string) $user['role'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(PDO $pdo): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, callsign, role, is_active, locked_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !(int) $user['is_active'] || !empty($user['locked_at'])) {
            self::logout();
            return null;
        }

        $_SESSION['callsign'] = (string) $user['callsign'];
        $_SESSION['role'] = (string) $user['role'];
        return self::publicUser($user);
    }

    public static function requireLogin(PDO $pdo): array
    {
        $user = self::check($pdo);
        if ($user === null) {
            redirect('login.php');
        }
        return $user;
    }

    public static function requireRole(PDO $pdo, array $roles): array
    {
        $user = self::requireLogin($pdo);
        if (!in_array($user['role'], $roles, true)) {
            http_response_code(403);
            echo '<h1>Not allowed</h1><p><a href="home.php">Back home</a></p>';
            exit;
        }
        return $user;
    }

    public static function isAdminPlus(array $user): bool
    {
        return in_array($user['role'], ['admin', 'superuser'], true);
    }

    public static function unlock(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare(
            'UPDATE users SET failed_login_count = 0, locked_at = NULL WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
    }

    public static function addSecurityAlert(PDO $pdo, string $type, ?string $callsign, string $message): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO security_alerts (alert_type, callsign, message)
             VALUES (:t, :c, :m)'
        );
        $stmt->execute([
            ':t' => $type,
            ':c' => $callsign,
            ':m' => $message,
        ]);
    }

    public static function unreadAlertCount(PDO $pdo): int
    {
        return (int) $pdo->query(
            'SELECT COUNT(*) FROM security_alerts WHERE is_read = 0'
        )->fetchColumn();
    }

    /** @return array{id:int,callsign:string,role:string} */
    private static function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'callsign' => (string) $user['callsign'],
            'role' => (string) $user['role'],
        ];
    }
}

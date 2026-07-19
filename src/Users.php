<?php
declare(strict_types=1);

final class Users
{
    public static function all(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT id, callsign, role, is_active, failed_login_count, locked_at, created_at, updated_at
             FROM users
             ORDER BY callsign ASC'
        )->fetchAll();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, callsign, role, is_active, failed_login_count, locked_at, created_at, updated_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function countSuperusers(PDO $pdo, bool $activeOnly = true): int
    {
        $sql = "SELECT COUNT(*) FROM users WHERE role = 'superuser'";
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        return (int) $pdo->query($sql)->fetchColumn();
    }

    public static function create(
        PDO $pdo,
        array $actor,
        string $callsign,
        string $password,
        string $role,
        bool $isActive = true
    ): int {
        $callsign = strtoupper(trim($callsign));
        $role = self::normalizeRole($role);
        self::assertCanAssignRole($pdo, $actor, $role, null);

        if ($callsign === '' || strlen($password) < 8) {
            throw new RuntimeException('Callsign and password (8+ characters) are required.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (callsign, password_hash, role, is_active, failed_login_count, locked_at)
             VALUES (:c, :h, :r, :a, 0, NULL)'
        );
        try {
            $stmt->execute([
                ':c' => $callsign,
                ':h' => password_hash($password, PASSWORD_DEFAULT),
                ':r' => $role,
                ':a' => $isActive ? 1 : 0,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                throw new RuntimeException('That callsign already exists.');
            }
            throw $e;
        }

        $id = (int) $pdo->lastInsertId();
        Ledger::write($pdo, 'user_created', null, (int) $actor['id'], null, null, [
            'callsign' => $callsign,
            'role' => $role,
            'is_active' => $isActive,
        ]);
        return $id;
    }

    public static function update(
        PDO $pdo,
        array $actor,
        array $target,
        string $role,
        bool $isActive,
        ?string $newPassword
    ): void {
        $role = self::normalizeRole($role);
        self::assertCanAssignRole($pdo, $actor, $role, $target);

        if ((int) $target['id'] === (int) $actor['id'] && !$isActive) {
            throw new RuntimeException('You cannot deactivate your own account.');
        }

        if (
            $target['role'] === 'superuser'
            && ($role !== 'superuser' || !$isActive)
            && self::countSuperusers($pdo) <= 1
            && (int) $target['is_active'] === 1
        ) {
            throw new RuntimeException('Cannot remove or demote the last active superuser.');
        }

        $sql = 'UPDATE users SET role = :r, is_active = :a';
        $params = [
            ':r' => $role,
            ':a' => $isActive ? 1 : 0,
            ':id' => $target['id'],
        ];

        if ($newPassword !== null && $newPassword !== '') {
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.');
            }
            $sql .= ', password_hash = :h, failed_login_count = 0, locked_at = NULL';
            $params[':h'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $sql .= ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Ledger::write($pdo, 'user_updated', null, (int) $actor['id'], null, null, [
            'callsign' => $target['callsign'],
            'changes' => [
                'role' => ['from' => $target['role'], 'to' => $role],
                'is_active' => ['from' => (int) $target['is_active'], 'to' => $isActive ? 1 : 0],
                'password_reset' => $newPassword !== null && $newPassword !== '',
            ],
        ]);
    }

    public static function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        if (!in_array($role, ['member', 'admin', 'superuser'], true)) {
            throw new RuntimeException('Invalid role.');
        }
        return $role;
    }

    public static function assertCanAssignRole(PDO $pdo, array $actor, string $role, ?array $target): void
    {
        if ($actor['role'] === 'superuser') {
            return;
        }
        if ($actor['role'] !== 'admin') {
            throw new RuntimeException('Not allowed.');
        }
        if ($role === 'superuser') {
            throw new RuntimeException('Only a superuser can assign the superuser role.');
        }
        if ($target && $target['role'] === 'superuser') {
            throw new RuntimeException('Only a superuser can change another superuser.');
        }
    }

    /** @return list<string> */
    public static function rolesForActor(array $actor): array
    {
        if ($actor['role'] === 'superuser') {
            return ['member', 'admin', 'superuser'];
        }
        return ['member', 'admin'];
    }
}

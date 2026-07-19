<?php
declare(strict_types=1);

final class Loans
{
    public const PENDING_HOURS = 48;

    public static function expirePending(PDO $pdo): void
    {
        $pdo->exec(
            "UPDATE witness_requests
             SET status = 'expired', resolved_at = NOW()
             WHERE status = 'pending' AND expires_at < NOW()"
        );
    }

    public static function activeLoanForItem(PDO $pdo, int $itemId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT l.*, u.callsign AS borrower_callsign
             FROM loans l
             INNER JOIN users u ON u.id = l.borrower_user_id
             WHERE l.item_id = :i AND l.is_active = 1
             ORDER BY l.id DESC
             LIMIT 1'
        );
        $stmt->execute([':i' => $itemId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findUserById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, callsign, role, is_active, locked_at, password_hash
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findUserByCallsign(PDO $pdo, string $callsign): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, callsign, role, is_active, locked_at, password_hash
             FROM users WHERE callsign = :c LIMIT 1'
        );
        $stmt->execute([':c' => strtoupper(trim($callsign))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function assertCanBeWitness(?array $witness, int $subjectUserId): void
    {
        if (!$witness || !(int) $witness['is_active'] || !empty($witness['locked_at'])) {
            throw new RuntimeException('Witness must be an active club member with a login.');
        }
        if ((int) $witness['id'] === $subjectUserId) {
            throw new RuntimeException('Witness cannot be the same person as the borrower/returner.');
        }
    }

    public static function verifyWitnessPassword(array $witness, string $password): void
    {
        if ($password === '' || !password_verify($password, $witness['password_hash'])) {
            throw new RuntimeException('Witness password is incorrect.');
        }
    }

    public static function assertKitConfirmed(array $item, array $includes, array $checkedIds, bool $masterConfirm): void
    {
        if ((int) $item['is_kit'] !== 1) {
            return;
        }
        if ($includes) {
            foreach ($includes as $line) {
                if (!in_array((int) $line['id'], $checkedIds, true)) {
                    throw new RuntimeException('Check every kit include line before continuing.');
                }
            }
        }
        if (!$masterConfirm) {
            throw new RuntimeException('Confirm that you verified all included items are present.');
        }
    }

    public static function childItemBlocked(PDO $pdo, int $childItemId): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM kit_includes ki
             INNER JOIN items kit ON kit.id = ki.kit_item_id
             WHERE ki.child_item_id = :c AND kit.status = 'on_loan'
             LIMIT 1"
        );
        $stmt->execute([':c' => $childItemId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function pendingForItem(PDO $pdo, int $itemId, string $actionType, ?int $ignoreRequestId = null): ?array
    {
        self::expirePending($pdo);
        $sql = "SELECT * FROM witness_requests
                WHERE item_id = :i AND action_type = :a AND status = 'pending'";
        if ($ignoreRequestId !== null) {
            $sql .= ' AND id <> :ignore';
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $params = [':i' => $itemId, ':a' => $actionType];
        if ($ignoreRequestId !== null) {
            $params[':ignore'] = $ignoreRequestId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function loanOutImmediate(
        PDO $pdo,
        array $item,
        array $actor,
        array $borrower,
        ?array $witness,
        bool $adminOverride,
        bool $kitVerified,
        ?string $notes,
        ?int $ignorePendingRequestId = null
    ): int {
        if ((int) $item['not_for_loan'] === 1) {
            throw new RuntimeException('This item is marked Not for loan.');
        }
        if ($item['status'] !== 'available') {
            throw new RuntimeException('Item is not available to loan.');
        }
        if (self::activeLoanForItem($pdo, (int) $item['id'])) {
            throw new RuntimeException('Item already has an active loan.');
        }
        if (self::childItemBlocked($pdo, (int) $item['id'])) {
            throw new RuntimeException('This item is part of a kit that is currently on loan.');
        }
        if (self::pendingForItem($pdo, (int) $item['id'], 'loan_out', $ignorePendingRequestId)) {
            throw new RuntimeException('There is already a pending loan witness request for this item.');
        }

        if ($adminOverride) {
            if (!Auth::isAdminPlus($actor)) {
                throw new RuntimeException('Only Admin+ can override witness.');
            }
        } else {
            if (!$witness) {
                throw new RuntimeException('Witness is required.');
            }
            self::assertCanBeWitness($witness, (int) $borrower['id']);
        }

        $loanStmt = $pdo->prepare(
            'INSERT INTO loans (item_id, borrower_user_id, loaned_at, is_active)
             VALUES (:i, :b, NOW(), 1)'
        );
        $loanStmt->execute([
            ':i' => $item['id'],
            ':b' => $borrower['id'],
        ]);
        $loanId = (int) $pdo->lastInsertId();

        $upd = $pdo->prepare("UPDATE items SET status = 'on_loan' WHERE id = :id AND status = 'available'");
        $upd->execute([':id' => $item['id']]);
        if ($upd->rowCount() !== 1) {
            throw new RuntimeException('Could not loan item (already taken).');
        }

        Ledger::write(
            $pdo,
            $adminOverride ? 'loan_out_admin_override' : 'loan_out',
            (int) $item['id'],
            (int) $actor['id'],
            $witness ? (int) $witness['id'] : null,
            $loanId,
            [
                'borrower' => $borrower['callsign'],
                'kit_verified' => $kitVerified,
                'admin_override' => $adminOverride,
                'notes' => $notes,
            ]
        );

        return $loanId;
    }

    public static function returnImmediate(
        PDO $pdo,
        array $item,
        array $actor,
        array $loan,
        ?array $witness,
        bool $adminOverride,
        bool $kitVerified,
        ?string $notes,
        ?int $ignorePendingRequestId = null
    ): void {
        if ($item['status'] !== 'on_loan' || !(int) $loan['is_active']) {
            throw new RuntimeException('Item is not on an active loan.');
        }

        $borrowerId = (int) $loan['borrower_user_id'];
        $isBorrower = (int) $actor['id'] === $borrowerId;
        if (!$isBorrower && !Auth::isAdminPlus($actor)) {
            throw new RuntimeException('Only the borrower or an Admin+ can start a return.');
        }

        if ($adminOverride) {
            if (!Auth::isAdminPlus($actor)) {
                throw new RuntimeException('Only Admin+ can override witness.');
            }
        } else {
            if (!$witness) {
                throw new RuntimeException('Witness is required.');
            }
            self::assertCanBeWitness($witness, (int) $actor['id']);
        }

        if (self::pendingForItem($pdo, (int) $item['id'], 'loan_return', $ignorePendingRequestId)) {
            throw new RuntimeException('There is already a pending return witness request for this item.');
        }

        $close = $pdo->prepare(
            'UPDATE loans SET is_active = 0, returned_at = NOW()
             WHERE id = :id AND is_active = 1'
        );
        $close->execute([':id' => $loan['id']]);
        if ($close->rowCount() !== 1) {
            throw new RuntimeException('Could not close loan.');
        }

        $upd = $pdo->prepare("UPDATE items SET status = 'available' WHERE id = :id AND status = 'on_loan'");
        $upd->execute([':id' => $item['id']]);

        Ledger::write(
            $pdo,
            $adminOverride ? 'loan_return_admin_override' : 'loan_return',
            (int) $item['id'],
            (int) $actor['id'],
            $witness ? (int) $witness['id'] : null,
            (int) $loan['id'],
            [
                'borrower' => $loan['borrower_callsign'] ?? null,
                'kit_verified' => $kitVerified,
                'admin_override' => $adminOverride,
                'notes' => $notes,
            ]
        );
    }

    public static function createRemoteRequest(
        PDO $pdo,
        string $actionType,
        array $item,
        array $actor,
        array $subjectUser,
        array $witness,
        ?int $loanId,
        bool $kitVerified,
        ?string $notes
    ): int {
        self::assertCanBeWitness($witness, (int) $subjectUser['id']);

        if ($actionType === 'loan_out') {
            if ($item['status'] !== 'available' || (int) $item['not_for_loan'] === 1) {
                throw new RuntimeException('Item is not available to loan.');
            }
            if (self::pendingForItem($pdo, (int) $item['id'], 'loan_out')) {
                throw new RuntimeException('A pending loan request already exists for this item.');
            }
        } else {
            if ($item['status'] !== 'on_loan') {
                throw new RuntimeException('Item is not on loan.');
            }
            if (self::pendingForItem($pdo, (int) $item['id'], 'loan_return')) {
                throw new RuntimeException('A pending return request already exists for this item.');
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO witness_requests (
                action_type, item_id, actor_user_id, subject_user_id, witness_user_id, loan_id,
                status, kit_verified, admin_override, notes, expires_at
             ) VALUES (
                :a, :i, :actor, :subject, :w, :l, \'pending\', :k, 0, :n,
                DATE_ADD(NOW(), INTERVAL ' . (int) self::PENDING_HOURS . ' HOUR)
             )'
        );
        $stmt->execute([
            ':a' => $actionType,
            ':i' => $item['id'],
            ':actor' => $actor['id'],
            ':subject' => $subjectUser['id'],
            ':w' => $witness['id'],
            ':l' => $loanId,
            ':k' => $kitVerified ? 1 : 0,
            ':n' => $notes,
        ]);
        $id = (int) $pdo->lastInsertId();

        Ledger::write(
            $pdo,
            'witness_request_created',
            (int) $item['id'],
            (int) $actor['id'],
            (int) $witness['id'],
            $loanId,
            [
                'request_id' => $id,
                'action_type' => $actionType,
                'subject' => $subjectUser['callsign'],
                'expires_hours' => self::PENDING_HOURS,
            ]
        );

        return $id;
    }

    public static function pendingForWitness(PDO $pdo, int $witnessUserId): array
    {
        self::expirePending($pdo);
        $stmt = $pdo->prepare(
            "SELECT wr.*, i.public_id, i.description, a.callsign AS actor_callsign, s.callsign AS subject_callsign
             FROM witness_requests wr
             INNER JOIN items i ON i.id = wr.item_id
             INNER JOIN users a ON a.id = wr.actor_user_id
             INNER JOIN users s ON s.id = wr.subject_user_id
             WHERE wr.witness_user_id = :w AND wr.status = 'pending'
             ORDER BY wr.expires_at ASC, wr.id ASC"
        );
        $stmt->execute([':w' => $witnessUserId]);
        return $stmt->fetchAll();
    }

    public static function pendingCountForWitness(PDO $pdo, int $witnessUserId): int
    {
        self::expirePending($pdo);
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM witness_requests
             WHERE witness_user_id = :w AND status = 'pending'"
        );
        $stmt->execute([':w' => $witnessUserId]);
        return (int) $stmt->fetchColumn();
    }

    public static function myLoans(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            'SELECT l.*, i.public_id, i.description, i.is_kit, i.status
             FROM loans l
             INNER JOIN items i ON i.id = l.item_id
             WHERE l.borrower_user_id = :u AND l.is_active = 1
             ORDER BY l.loaned_at DESC'
        );
        $stmt->execute([':u' => $userId]);
        return $stmt->fetchAll();
    }

    public static function findRequest(PDO $pdo, int $id): ?array
    {
        self::expirePending($pdo);
        $stmt = $pdo->prepare('SELECT * FROM witness_requests WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function resolveRequest(PDO $pdo, array $request, array $witnessUser, bool $approve): void
    {
        if ($request['status'] !== 'pending') {
            throw new RuntimeException('This request is no longer pending.');
        }
        if ((int) $request['witness_user_id'] !== (int) $witnessUser['id']) {
            throw new RuntimeException('This request is not assigned to you.');
        }

        $requestId = (int) $request['id'];

        if (!$approve) {
            $stmt = $pdo->prepare(
                "UPDATE witness_requests
                 SET status = 'declined', resolved_at = NOW()
                 WHERE id = :id AND status = 'pending'"
            );
            $stmt->execute([':id' => $requestId]);
            Ledger::write(
                $pdo,
                'witness_request_declined',
                (int) $request['item_id'],
                (int) $request['actor_user_id'],
                (int) $witnessUser['id'],
                $request['loan_id'] !== null ? (int) $request['loan_id'] : null,
                ['request_id' => $requestId]
            );
            return;
        }

        $item = Items::findById($pdo, (int) $request['item_id']);
        if (!$item) {
            throw new RuntimeException('Item no longer exists.');
        }

        $actor = self::findUserById($pdo, (int) $request['actor_user_id']);
        $subject = self::findUserById($pdo, (int) ($request['subject_user_id'] ?? $request['actor_user_id']));
        if (!$actor || !$subject) {
            throw new RuntimeException('Request members not found.');
        }

        $loanId = $request['loan_id'] !== null ? (int) $request['loan_id'] : null;

        if ($request['action_type'] === 'loan_out') {
            $loanId = self::loanOutImmediate(
                $pdo,
                $item,
                $actor,
                $subject,
                $witnessUser,
                false,
                (int) $request['kit_verified'] === 1,
                $request['notes'],
                $requestId
            );
        } else {
            $loan = self::activeLoanForItem($pdo, (int) $item['id']);
            if (!$loan) {
                throw new RuntimeException('No active loan to return.');
            }
            self::returnImmediate(
                $pdo,
                $item,
                $actor,
                $loan,
                $witnessUser,
                false,
                (int) $request['kit_verified'] === 1,
                $request['notes'],
                $requestId
            );
            $loanId = (int) $loan['id'];
        }

        $stmt = $pdo->prepare(
            "UPDATE witness_requests
             SET status = 'approved', resolved_at = NOW(), loan_id = COALESCE(:l, loan_id)
             WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([':l' => $loanId, ':id' => $requestId]);

        Ledger::write(
            $pdo,
            'witness_request_approved',
            (int) $request['item_id'],
            (int) $request['actor_user_id'],
            (int) $witnessUser['id'],
            $loanId,
            ['request_id' => $requestId]
        );
    }
}

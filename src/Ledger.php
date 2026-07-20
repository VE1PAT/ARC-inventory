<?php
declare(strict_types=1);

final class Ledger
{
    public static function write(
        PDO $pdo,
        string $eventType,
        ?int $itemId,
        ?int $actorUserId,
        ?int $witnessUserId = null,
        ?int $loanId = null,
        ?array $details = null
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO ledger (event_type, item_id, actor_user_id, witness_user_id, loan_id, details_json)
             VALUES (:e, :i, :a, :w, :l, :d)'
        );
        $stmt->execute([
            ':e' => $eventType,
            ':i' => $itemId,
            ':a' => $actorUserId,
            ':w' => $witnessUserId,
            ':l' => $loanId,
            ':d' => $details === null ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            'item_created' => 'Item created',
            'item_updated' => 'Item updated',
            'metadata_changed' => 'Details changed',
            'status_change' => 'Status changed',
            'photo_updated' => 'Photo updated',
            'loan_out' => 'Loaned out',
            'loan_out_admin_override' => 'Loaned out (admin override)',
            'loan_return' => 'Returned',
            'loan_return_admin_override' => 'Returned (admin override)',
            'witness_request_created' => 'Witness request created',
            'witness_request_approved' => 'Witness request approved',
            'witness_request_declined' => 'Witness request declined',
            'user_created' => 'User created',
            'user_updated' => 'User updated',
            'password_changed' => 'Password changed',
            'csv_import' => 'CSV import',
            default => $eventType,
        };
    }

    public static function forItem(PDO $pdo, int $itemId, int $limit = 50): array
    {
        $stmt = $pdo->prepare(
            'SELECT l.*,
                    a.callsign AS actor_callsign,
                    w.callsign AS witness_callsign,
                    i.public_id,
                    i.description
             FROM ledger l
             LEFT JOIN users a ON a.id = l.actor_user_id
             LEFT JOIN users w ON w.id = l.witness_user_id
             LEFT JOIN items i ON i.id = l.item_id
             WHERE l.item_id = :id
             ORDER BY l.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([':id' => $itemId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array{event_type?:string,callsign?:string,item_q?:string,from?:string,to?:string} $filters
     */
    public static function search(PDO $pdo, array $filters, int $limit = 200): array
    {
        $sql = 'SELECT l.*,
                       a.callsign AS actor_callsign,
                       w.callsign AS witness_callsign,
                       i.public_id,
                       i.description
                FROM ledger l
                LEFT JOIN users a ON a.id = l.actor_user_id
                LEFT JOIN users w ON w.id = l.witness_user_id
                LEFT JOIN items i ON i.id = l.item_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['event_type'])) {
            $sql .= ' AND l.event_type = :e';
            $params[':e'] = $filters['event_type'];
        }
        if (!empty($filters['callsign'])) {
            $sql .= ' AND (a.callsign LIKE :c OR w.callsign LIKE :c2)';
            $like = '%' . strtoupper(trim($filters['callsign'])) . '%';
            $params[':c'] = $like;
            $params[':c2'] = $like;
        }
        if (!empty($filters['item_q'])) {
            $sql .= ' AND (i.public_id LIKE :iq OR i.description LIKE :iq2 OR i.club_id LIKE :iq3)';
            $like = '%' . $filters['item_q'] . '%';
            $params[':iq'] = $like;
            $params[':iq2'] = $like;
            $params[':iq3'] = $like;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND l.created_at >= :from';
            $params[':from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND l.created_at <= :to';
            $params[':to'] = $filters['to'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY l.id DESC LIMIT ' . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function distinctEventTypes(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT DISTINCT event_type FROM ledger ORDER BY event_type ASC'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function detailsSummary(?string $json): string
    {
        if ($json === null || $json === '') {
            return '';
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return '';
        }

        $bits = [];
        if (!empty($data['borrower'])) {
            $bits[] = 'borrower ' . $data['borrower'];
        }
        if (!empty($data['admin_override'])) {
            $bits[] = 'admin override';
        }
        if (array_key_exists('kit_verified', $data) && $data['kit_verified']) {
            $bits[] = 'kit verified';
        }
        if (!empty($data['notes'])) {
            $bits[] = 'note: ' . $data['notes'];
        }
        if (!empty($data['description'])) {
            $bits[] = $data['description'];
        }
        if (!empty($data['callsign'])) {
            $bits[] = $data['callsign'];
        }
        if (!empty($data['changes']) && is_array($data['changes'])) {
            $changed = array_keys($data['changes']);
            if ($changed) {
                $bits[] = 'fields: ' . implode(', ', $changed);
            }
        }
        if (isset($data['created'])) {
            $bits[] = 'created ' . $data['created'];
        }
        return implode(' · ', $bits);
    }
}

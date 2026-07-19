<?php
declare(strict_types=1);

final class Reports
{
    public static function currentlyOnLoan(PDO $pdo): array
    {
        return $pdo->query(
            "SELECT i.public_id, i.description, i.item_type, i.location,
                    u.callsign AS borrower_callsign, l.loaned_at
             FROM loans l
             INNER JOIN items i ON i.id = l.item_id
             INNER JOIN users u ON u.id = l.borrower_user_id
             WHERE l.is_active = 1
             ORDER BY l.loaned_at ASC, i.description ASC"
        )->fetchAll();
    }

    public static function inventoryByStatus(PDO $pdo, ?string $status = null, ?bool $notForLoan = null): array
    {
        $sql = 'SELECT public_id, club_id, item_type, description, manufacturer, model,
                       serial_number, location, status, not_for_loan, is_kit, source_note
                FROM items WHERE 1=1';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' AND status = :s';
            $params[':s'] = $status;
        }
        if ($notForLoan !== null) {
            $sql .= ' AND not_for_loan = :n';
            $params[':n'] = $notForLoan ? 1 : 0;
        }
        $sql .= ' ORDER BY status ASC, description ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function aging(PDO $pdo, int $months = 12): array
    {
        $stmt = $pdo->prepare(
            "SELECT i.public_id, i.description, i.item_type, i.location, i.status,
                    (
                      SELECT MAX(l.loaned_at) FROM loans l WHERE l.item_id = i.id
                    ) AS last_loaned_at
             FROM items i
             WHERE i.not_for_loan = 0
               AND i.status NOT IN ('sold', 'disposed')
               AND (
                 NOT EXISTS (SELECT 1 FROM loans l WHERE l.item_id = i.id)
                 OR NOT EXISTS (
                   SELECT 1 FROM loans l
                   WHERE l.item_id = i.id
                     AND l.loaned_at >= DATE_SUB(NOW(), INTERVAL :m MONTH)
                 )
               )
             ORDER BY last_loaned_at IS NULL DESC, last_loaned_at ASC, i.description ASC"
        );
        $stmt->bindValue(':m', $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function soldDisposed(PDO $pdo, ?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT i.public_id, i.description, i.item_type, i.status, i.source_note, i.notes, i.updated_at
                FROM items i
                WHERE i.status IN ('sold', 'disposed')";
        $params = [];
        if ($from) {
            $sql .= ' AND i.updated_at >= :from';
            $params[':from'] = $from . ' 00:00:00';
        }
        if ($to) {
            $sql .= ' AND i.updated_at <= :to';
            $params[':to'] = $to . ' 23:59:59';
        }
        $sql .= ' ORDER BY i.updated_at DESC, i.description ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function summary(PDO $pdo): array
    {
        $byStatus = $pdo->query(
            'SELECT status, COUNT(*) AS cnt FROM items GROUP BY status ORDER BY status'
        )->fetchAll();
        $notForLoan = (int) $pdo->query(
            'SELECT COUNT(*) FROM items WHERE not_for_loan = 1 AND status NOT IN (\'sold\', \'disposed\')'
        )->fetchColumn();
        $onLoan = (int) $pdo->query(
            'SELECT COUNT(*) FROM loans WHERE is_active = 1'
        )->fetchColumn();
        $kits = (int) $pdo->query(
            'SELECT COUNT(*) FROM items WHERE is_kit = 1 AND status NOT IN (\'sold\', \'disposed\')'
        )->fetchColumn();
        $skish = (int) $pdo->query(
            "SELECT COUNT(*) FROM items
             WHERE status NOT IN ('sold', 'disposed')
               AND (
                 source_note LIKE '%SK%'
                 OR source_note LIKE '%silent key%'
                 OR source_note LIKE '%estate%'
                 OR source_note LIKE '%donat%'
               )"
        )->fetchColumn();
        $members = (int) $pdo->query(
            'SELECT COUNT(*) FROM users WHERE is_active = 1'
        )->fetchColumn();
        $recent = $pdo->query(
            'SELECT event_type, created_at, details_json
             FROM ledger
             ORDER BY id DESC
             LIMIT 15'
        )->fetchAll();

        return [
            'by_status' => $byStatus,
            'not_for_loan' => $notForLoan,
            'on_loan' => $onLoan,
            'kits' => $kits,
            'donation_like' => $skish,
            'active_members' => $members,
            'recent' => $recent,
            'total_items' => (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn(),
        ];
    }
}

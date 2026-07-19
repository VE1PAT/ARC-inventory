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
}

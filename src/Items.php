<?php
declare(strict_types=1);

final class Items
{
    public const TYPES = [
        'radio' => 'Radio',
        'antenna' => 'Antenna',
        'book' => 'Book',
        'software' => 'Software',
        'cable' => 'Cable',
        'tool' => 'Tool',
        'kit' => 'Kit / Go-Kit',
        'station' => 'Station / fixed gear',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'available' => 'Available',
        'on_loan' => 'On loan',
        'maintenance' => 'Maintenance',
        'sold' => 'Sold',
        'disposed' => 'Disposed',
    ];

    public static function generatePublicId(PDO $pdo): string
    {
        for ($i = 0; $i < 8; $i++) {
            $id = 'ARC-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare('SELECT id FROM items WHERE public_id = :p LIMIT 1');
            $stmt->execute([':p' => $id]);
            if (!$stmt->fetch()) {
                return $id;
            }
        }
        return 'ARC-' . strtoupper(bin2hex(random_bytes(6)));
    }

    public static function findById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByPublicId(PDO $pdo, string $publicId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM items WHERE public_id = :p LIMIT 1');
        $stmt->execute([':p' => $publicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function currentBorrower(PDO $pdo, int $itemId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.callsign, l.loaned_at, l.id AS loan_id
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

    public static function kitIncludes(PDO $pdo, int $kitItemId): array
    {
        $stmt = $pdo->prepare(
            'SELECT ki.*, ci.public_id AS child_public_id, ci.description AS child_description
             FROM kit_includes ki
             LEFT JOIN items ci ON ci.id = ki.child_item_id
             WHERE ki.kit_item_id = :k
             ORDER BY ki.sort_order ASC, ki.id ASC'
        );
        $stmt->execute([':k' => $kitItemId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array>
     */
    public static function search(PDO $pdo, string $query, bool $adminView, int $limit = 100): array
    {
        $query = trim($query);
        $params = [];
        $where = [];

        if (!$adminView) {
            $where[] = "i.status NOT IN ('sold', 'disposed')";
        }

        if ($query !== '') {
            // Native PDO prepares disallow reusing the same named placeholder.
            $like = '%' . $query . '%';
            $fields = [
                'public_id', 'club_id', 'description', 'manufacturer', 'model',
                'serial_number', 'location', 'source_note', 'notes', 'item_type',
                'condition_note',
            ];
            $parts = [];
            foreach ($fields as $i => $field) {
                $key = ':q' . $i;
                $parts[] = 'i.' . $field . ' LIKE ' . $key;
                $params[$key] = $like;
            }
            $parts[] = 'EXISTS (
                SELECT 1 FROM loans l
                INNER JOIN users u ON u.id = l.borrower_user_id
                WHERE l.item_id = i.id AND l.is_active = 1 AND u.callsign LIKE :q_borrower
            )';
            $params[':q_borrower'] = $like;
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        $sql = 'SELECT i.*,
                       (
                         SELECT u.callsign FROM loans l
                         INNER JOIN users u ON u.id = l.borrower_user_id
                         WHERE l.item_id = i.id AND l.is_active = 1
                         ORDER BY l.id DESC LIMIT 1
                       ) AS borrower_callsign
                FROM items i';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY i.description ASC, i.id ASC LIMIT ' . (int) $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function visibleToUser(array $item, bool $adminView): bool
    {
        if ($adminView) {
            return true;
        }
        return !in_array($item['status'], ['sold', 'disposed'], true);
    }

    public static function normalize(array $input): array
    {
        $type = (string) ($input['item_type'] ?? 'other');
        if (!array_key_exists($type, self::TYPES)) {
            $type = 'other';
        }

        $status = (string) ($input['status'] ?? 'available');
        if (!array_key_exists($status, self::STATUSES)) {
            $status = 'available';
        }

        $isKit = !empty($input['is_kit']) || $type === 'kit';

        return [
            'club_id' => self::nullIfBlank($input['club_id'] ?? null),
            'item_type' => $type,
            'description' => trim((string) ($input['description'] ?? '')),
            'manufacturer' => self::nullIfBlank($input['manufacturer'] ?? null),
            'model' => self::nullIfBlank($input['model'] ?? null),
            'serial_number' => self::nullIfBlank($input['serial_number'] ?? null),
            'location' => self::nullIfBlank($input['location'] ?? null),
            'condition_note' => self::nullIfBlank($input['condition_note'] ?? null),
            'source_note' => self::nullIfBlank($input['source_note'] ?? null),
            'notes' => self::nullIfBlank($input['notes'] ?? null),
            'status' => $status,
            'not_for_loan' => !empty($input['not_for_loan']) ? 1 : 0,
            'is_kit' => $isKit ? 1 : 0,
        ];
    }

    public static function create(PDO $pdo, array $data, int $actorUserId, array $kitLines = []): int
    {
        $publicId = self::generatePublicId($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO items (
                public_id, club_id, item_type, description, manufacturer, model, serial_number,
                location, condition_note, source_note, notes, status, not_for_loan, is_kit
             ) VALUES (
                :public_id, :club_id, :item_type, :description, :manufacturer, :model, :serial_number,
                :location, :condition_note, :source_note, :notes, :status, :not_for_loan, :is_kit
             )'
        );
        $stmt->execute([
            ':public_id' => $publicId,
            ':club_id' => $data['club_id'],
            ':item_type' => $data['item_type'],
            ':description' => $data['description'],
            ':manufacturer' => $data['manufacturer'],
            ':model' => $data['model'],
            ':serial_number' => $data['serial_number'],
            ':location' => $data['location'],
            ':condition_note' => $data['condition_note'],
            ':source_note' => $data['source_note'],
            ':notes' => $data['notes'],
            ':status' => $data['status'],
            ':not_for_loan' => $data['not_for_loan'],
            ':is_kit' => $data['is_kit'],
        ]);
        $id = (int) $pdo->lastInsertId();

        if ((int) $data['is_kit'] === 1) {
            self::replaceKitIncludes($pdo, $id, $kitLines);
        }

        Ledger::write($pdo, 'item_created', $id, $actorUserId, null, null, [
            'public_id' => $publicId,
            'description' => $data['description'],
            'status' => $data['status'],
        ]);

        return $id;
    }

    public static function update(PDO $pdo, array $before, array $data, int $actorUserId, array $kitLines = []): void
    {
        $id = (int) $before['id'];

        // Do not allow non-loan flows to set on_loan here; loans will own that later.
        if ($data['status'] === 'on_loan' && $before['status'] !== 'on_loan') {
            $data['status'] = $before['status'];
        }

        $stmt = $pdo->prepare(
            'UPDATE items SET
                club_id = :club_id,
                item_type = :item_type,
                description = :description,
                manufacturer = :manufacturer,
                model = :model,
                serial_number = :serial_number,
                location = :location,
                condition_note = :condition_note,
                source_note = :source_note,
                notes = :notes,
                status = :status,
                not_for_loan = :not_for_loan,
                is_kit = :is_kit
             WHERE id = :id'
        );
        $stmt->execute([
            ':club_id' => $data['club_id'],
            ':item_type' => $data['item_type'],
            ':description' => $data['description'],
            ':manufacturer' => $data['manufacturer'],
            ':model' => $data['model'],
            ':serial_number' => $data['serial_number'],
            ':location' => $data['location'],
            ':condition_note' => $data['condition_note'],
            ':source_note' => $data['source_note'],
            ':notes' => $data['notes'],
            ':status' => $data['status'],
            ':not_for_loan' => $data['not_for_loan'],
            ':is_kit' => $data['is_kit'],
            ':id' => $id,
        ]);

        if ((int) $data['is_kit'] === 1) {
            self::replaceKitIncludes($pdo, $id, $kitLines);
        } else {
            $del = $pdo->prepare('DELETE FROM kit_includes WHERE kit_item_id = :k');
            $del->execute([':k' => $id]);
        }

        $changes = [];
        foreach ($data as $key => $value) {
            $old = $before[$key] ?? null;
            if ((string) $old !== (string) $value) {
                $changes[$key] = ['from' => $old, 'to' => $value];
            }
        }

        if ($changes) {
            $event = isset($changes['status']) ? 'status_change' : 'metadata_changed';
            if (isset($changes['status']) && count($changes) > 1) {
                $event = 'item_updated';
            } elseif (!isset($changes['status'])) {
                $event = 'metadata_changed';
            }
            Ledger::write($pdo, $event, $id, $actorUserId, null, null, ['changes' => $changes]);
        }
    }

    public static function replaceKitIncludes(PDO $pdo, int $kitItemId, array $lines): void
    {
        $del = $pdo->prepare('DELETE FROM kit_includes WHERE kit_item_id = :k');
        $del->execute([':k' => $kitItemId]);

        $ins = $pdo->prepare(
            'INSERT INTO kit_includes (kit_item_id, line_label, child_item_id, sort_order)
             VALUES (:k, :l, NULL, :s)'
        );
        $sort = 0;
        foreach ($lines as $line) {
            $label = trim((string) $line);
            if ($label === '') {
                continue;
            }
            $ins->execute([':k' => $kitItemId, ':l' => $label, ':s' => $sort]);
            $sort++;
        }
    }

    public static function parseKitLines(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }
        $parts = preg_split('/\r\n|\r|\n/', $text) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn($l) => $l !== ''));
    }

    public static function kitLinesToText(array $includes): string
    {
        $lines = [];
        foreach ($includes as $row) {
            $lines[] = (string) $row['line_label'];
        }
        return implode("\n", $lines);
    }

    public static function savePhoto(PDO $pdo, int $itemId, array $file, int $actorUserId): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Photo upload failed.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Photo must be JPG, PNG, WEBP, or GIF.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new RuntimeException('Photo must be 5 MB or smaller.');
        }

        $dir = dirname(__DIR__) . '/public/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create uploads folder.');
        }

        $name = 'item_' . $itemId . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Could not save uploaded photo.');
        }

        $rel = 'uploads/' . $name;
        $before = self::findById($pdo, $itemId);
        $stmt = $pdo->prepare('UPDATE items SET photo_path = :p WHERE id = :id');
        $stmt->execute([':p' => $rel, ':id' => $itemId]);

        if ($before && !empty($before['photo_path'])) {
            $old = dirname(__DIR__) . '/public/' . str_replace(['..', '\\'], '', (string) $before['photo_path']);
            if (is_file($old)) {
                @unlink($old);
            }
        }

        Ledger::write($pdo, 'photo_updated', $itemId, $actorUserId, null, null, [
            'photo_path' => $rel,
        ]);

        return $rel;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}

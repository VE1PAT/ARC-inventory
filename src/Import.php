<?php
declare(strict_types=1);

final class Import
{
    public const HEADERS = [
        'club_id',
        'item_type',
        'description',
        'manufacturer',
        'model',
        'serial_number',
        'location',
        'condition_note',
        'source_note',
        'notes',
        'status',
        'not_for_loan',
        'is_kit',
        'kit_includes',
    ];

    public static function templateRows(): array
    {
        return [[
            'club_id' => '',
            'item_type' => 'radio',
            'description' => 'Example HT',
            'manufacturer' => 'Yaesu',
            'model' => 'FT-60R',
            'serial_number' => '',
            'location' => 'Club shack',
            'condition_note' => 'Good',
            'source_note' => 'Donation',
            'notes' => 'Sample row — delete before import',
            'status' => 'available',
            'not_for_loan' => '0',
            'is_kit' => '0',
            'kit_includes' => '',
        ]];
    }

    public static function importRows(PDO $pdo, array $rows, int $actorUserId): array
    {
        $created = 0;
        $errors = [];

        foreach ($rows as $row) {
            $line = (int) ($row['_line'] ?? 0);
            try {
                $truthy = static function (string $v): bool {
                    $v = strtolower(trim($v));
                    return in_array($v, ['1', 'true', 'yes', 'y'], true);
                };

                $data = Items::normalize([
                    'club_id' => $row['club_id'] ?? '',
                    'item_type' => $row['item_type'] ?? 'other',
                    'description' => $row['description'] ?? '',
                    'manufacturer' => $row['manufacturer'] ?? '',
                    'model' => $row['model'] ?? '',
                    'serial_number' => $row['serial_number'] ?? '',
                    'location' => $row['location'] ?? '',
                    'condition_note' => $row['condition_note'] ?? '',
                    'source_note' => $row['source_note'] ?? '',
                    'notes' => $row['notes'] ?? '',
                    'status' => $row['status'] ?? 'available',
                    'not_for_loan' => $truthy((string) ($row['not_for_loan'] ?? '0')),
                    'is_kit' => $truthy((string) ($row['is_kit'] ?? '0')),
                ]);

                if ($data['description'] === '') {
                    throw new RuntimeException('description is required');
                }
                if ($data['status'] === 'on_loan') {
                    $data['status'] = 'available';
                }

                $kitLines = [];
                $kitRaw = trim((string) ($row['kit_includes'] ?? ''));
                if ($kitRaw !== '') {
                    $data['is_kit'] = 1;
                    $parts = preg_split('/\s*\|\s*|\s*;\s*/', $kitRaw) ?: [];
                    $kitLines = array_values(array_filter(array_map('trim', $parts)));
                }

                Items::create($pdo, $data, $actorUserId, $kitLines);
                $created++;
            } catch (Throwable $e) {
                $errors[] = 'Line ' . $line . ': ' . $e->getMessage();
            }
        }

        Ledger::write($pdo, 'csv_import', null, $actorUserId, null, null, [
            'created' => $created,
            'error_count' => count($errors),
        ]);

        return ['created' => $created, 'errors' => $errors];
    }
}

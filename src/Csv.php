<?php
declare(strict_types=1);

final class Csv
{
    public static function download(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new RuntimeException('Could not open output stream.');
        }

        // UTF-8 BOM helps LibreOffice/Excel detect encoding.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $key) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    public static function readUploaded(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('CSV upload failed.');
        }
        $path = (string) $file['tmp_name'];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not read CSV file.');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new RuntimeException('CSV has no header row.');
        }
        $headers = array_map(static function ($h) {
            $h = strtolower(trim((string) $h));
            // Strip UTF-8 BOM from first header if present.
            return ltrim($h, "\xEF\xBB\xBF");
        }, $headers);

        $rows = [];
        $lineNo = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $lineNo++;
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $name) {
                $row[$name] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }
            $row['_line'] = $lineNo;
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }
}

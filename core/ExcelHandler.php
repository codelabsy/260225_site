<?php
/**
 * CSV import/export handler (pure PHP, no Composer dependencies).
 */

class ExcelHandler
{
    /**
     * Parse a CSV file and return rows as an array of associative arrays.
     * First row is treated as headers.
     */
    public static function parseCSV(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);

        // Remove UTF-8 BOM if present
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        // Detect and convert encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'EUC-KR', 'CP949', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tempFile, $content);

        $handle = fopen($tempFile, 'r');
        if ($handle === false) {
            unlink($tempFile);
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            unlink($tempFile);
            return [];
        }

        // Trim header values
        $headers = array_map('trim', $headers);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) === 1 && $row[0] === null) {
                continue; // skip empty lines
            }

            // Pad or trim row to match header count
            $row = array_pad($row, count($headers), '');
            $row = array_slice($row, 0, count($headers));

            $rows[] = array_combine($headers, array_map('trim', $row));
        }

        fclose($handle);
        unlink($tempFile);

        return $rows;
    }

    /**
     * Export data as CSV download with UTF-8 BOM for Excel compatibility.
     */
    public static function exportCSV(array $headers, array $data, string $filename): void
    {
        $filename = preg_replace('/[^a-zA-Z0-9가-힣_\-.]/', '_', $filename);
        if (!str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Excel
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers);

        foreach ($data as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = $row[$header] ?? '';
            }
            fputcsv($output, $values);
        }

        fclose($output);
        exit;
    }
}

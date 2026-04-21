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

        // Skip metadata rows until we find the actual header row
        // Header row contains known column names like 키워드, 상호명, 연락처 etc.
        $knownHeaders = ['키워드', '상호명', '업체명', '연락처', '전화번호', '핸드폰번호', '대표자', '상품명', 'url', 'URL', 'company_name', 'phone'];
        $headers = null;
        while (($candidate = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $trimmed = array_map('trim', $candidate);
            $nonEmpty = array_filter($trimmed, fn($v) => $v !== '');
            // Check if this row contains any known header name
            if (!empty($nonEmpty) && count(array_intersect($trimmed, $knownHeaders)) > 0) {
                $headers = $trimmed;
                break;
            }
        }

        if ($headers === null) {
            fclose($handle);
            unlink($tempFile);
            return [];
        }

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
     * Parse an .xlsx file (first worksheet) and return rows as a 2D array
     * (0-indexed columns). Caller decides whether first row is header.
     */
    public static function parseXLSXRaw(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive extension is not available.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [];
        }

        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $xml = @simplexml_load_string($ssXml);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                        continue;
                    }
                    $buf = '';
                    foreach ($si->r as $r) {
                        $buf .= (string)$r->t;
                    }
                    $sharedStrings[] = $buf;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, 'xl/worksheets/sheet') === 0 && substr($name, -4) === '.xml') {
                    $sheetXml = $zip->getFromName($name);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $xml = @simplexml_load_string($sheetXml);
        if ($xml === false) {
            return [];
        }

        $allRows = [];
        foreach ($xml->sheetData->row as $rowEl) {
            $rowData = [];
            $maxCol = 0;
            foreach ($rowEl->c as $c) {
                $ref = (string)$c['r'];
                $colIdx = self::xlsxColumnIndex($ref);
                $type = isset($c['t']) ? (string)$c['t'] : '';
                $value = '';
                if ($type === 's') {
                    $idx = (int)$c->v;
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string)($c->is->t ?? '');
                } elseif ($type === 'b') {
                    $value = ((int)$c->v === 1) ? 'TRUE' : 'FALSE';
                } else {
                    $value = (string)$c->v;
                }
                $rowData[$colIdx] = is_string($value) ? trim($value) : $value;
                if ($colIdx > $maxCol) {
                    $maxCol = $colIdx;
                }
            }
            $filled = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $filled[] = $rowData[$i] ?? '';
            }
            $allRows[] = $filled;
        }
        return $allRows;
    }

    /**
     * Parse an .xlsx file and return rows as an array of associative arrays
     * keyed by the header (first non-empty) row.
     */
    public static function parseXLSX(string $filePath): array
    {
        $allRows = self::parseXLSXRaw($filePath);
        if (empty($allRows)) {
            return [];
        }

        $headers = null;
        $startIdx = 0;
        foreach ($allRows as $idx => $r) {
            $nonEmpty = array_filter($r, fn($v) => $v !== '');
            if (!empty($nonEmpty)) {
                $headers = array_map('trim', $r);
                $startIdx = $idx + 1;
                break;
            }
        }

        if ($headers === null) {
            return [];
        }

        $result = [];
        $headerCount = count($headers);
        for ($i = $startIdx; $i < count($allRows); $i++) {
            $r = $allRows[$i];
            $r = array_pad($r, $headerCount, '');
            $r = array_slice($r, 0, $headerCount);
            if (count(array_filter($r, fn($v) => $v !== '')) === 0) {
                continue;
            }
            $result[] = array_combine($headers, $r);
        }

        return $result;
    }

    /**
     * Convert xlsx cell reference like "B12" to zero-based column index (1).
     */
    private static function xlsxColumnIndex(string $ref): int
    {
        $col = 0;
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $ch = $ref[$i];
            if ($ch >= 'A' && $ch <= 'Z') {
                $col = $col * 26 + (ord($ch) - ord('A') + 1);
            } elseif ($ch >= 'a' && $ch <= 'z') {
                $col = $col * 26 + (ord($ch) - ord('a') + 1);
            } else {
                break;
            }
        }
        return $col - 1;
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

<?php
/**
 * Coupang DB upload API.
 * POST: multipart/form-data with CSV or XLSX file.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/ExcelHandler.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../models/CoupangDB.php';

/**
 * Convert a 2D array (rows of cells) to associative rows.
 * Detects header row by matching against known header names; falls back to
 * positional mapping (Coupang-specific 10/15 column layout) for headerless files.
 */
function coupang_rows_to_assoc(array $allRows, array $headerMarkers, array $positional10, array $positional15): array
{
    $firstIdx = null;
    foreach ($allRows as $i => $r) {
        if (count(array_filter($r, fn($v) => $v !== '')) > 0) {
            $firstIdx = $i;
            break;
        }
    }
    if ($firstIdx === null) {
        return [];
    }
    $firstRow = $allRows[$firstIdx];
    $hasHeader = count(array_intersect(array_map('trim', $firstRow), $headerMarkers)) > 0;

    if ($hasHeader) {
        $headers = array_map('trim', $firstRow);
        $startIdx = $firstIdx + 1;
    } else {
        $colCount = count($firstRow);
        $headers = ($colCount >= 15) ? $positional15 : $positional10;
        while (count($headers) < $colCount) {
            $headers[] = '_ignore_' . count($headers);
        }
        $startIdx = $firstIdx;
    }

    $result = [];
    $headerCount = count($headers);
    for ($i = $startIdx; $i < count($allRows); $i++) {
        $r = $allRows[$i];
        if (count(array_filter($r, fn($v) => $v !== '')) === 0) {
            continue;
        }
        $r = array_pad($r, $headerCount, '');
        $r = array_slice($r, 0, $headerCount);
        $r = array_map(fn($v) => is_string($v) ? trim($v) : $v, $r);
        $result[] = array_combine($headers, $r);
    }
    return $result;
}

function coupang_csv_raw_rows(string $filePath): array
{
    $content = file_get_contents($filePath);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    $encoding = mb_detect_encoding($content, ['UTF-8', 'EUC-KR', 'CP949', 'ISO-8859-1'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tempFile, $content);
    $handle = fopen($tempFile, 'r');
    $rows = [];
    if ($handle) {
        while (($r = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($r) === 1 && $r[0] === null) continue;
            $rows[] = array_map(fn($v) => is_string($v) ? trim($v) : $v, $r);
        }
        fclose($handle);
    }
    unlink($tempFile);
    return $rows;
}

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Permission::verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF 토큰이 유효하지 않습니다.']);
    exit;
}

$user = Auth::user();

try {

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '파일 업로드에 실패했습니다.']);
    exit;
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['csv', 'xlsx'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSV 또는 XLSX 파일만 업로드 가능합니다.']);
    exit;
}

// Coupang-specific positional layout for headerless files.
// 10-col form: 상호명, 연락처, 이메일, 주소1, 주소2, 대표자명, 사업자등록번호, 상품명, 상품ID, 검색키워드
// 15-col form: + 평점수, 추천수, 비추천수, 추천비율(%), 수집일시
$positional10 = ['company_name', 'phone', 'email', 'address', 'address_detail', 'representative', 'business_number', 'product_name', 'product_id', 'keyword'];
$positional15 = array_merge($positional10, ['rating_count', 'recommend_count', 'not_recommend_count', 'recommend_ratio', 'collected_at']);

// Column names that indicate a header row is present.
$headerMarkers = ['상호명', '업체명', 'company_name', '연락처', '전화번호', 'phone', '대표자', '대표자명', '상품명', '상품ID', '검색키워드', '키워드'];

if ($ext === 'xlsx') {
    $rawRows = ExcelHandler::parseXLSXRaw($file['tmp_name']);
    $rows = coupang_rows_to_assoc($rawRows, $headerMarkers, $positional10, $positional15);
} else {
    // CSV: use parseCSV first (supports metadata-row skip + header detection).
    // If it returns nothing (likely headerless), fallback to raw reader.
    $rows = ExcelHandler::parseCSV($file['tmp_name']);
    if (empty($rows)) {
        $rawRows = coupang_csv_raw_rows($file['tmp_name']);
        $rows = coupang_rows_to_assoc($rawRows, $headerMarkers, $positional10, $positional15);
    }
}

if (empty($rows)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '파일에 데이터가 없습니다.']);
    exit;
}

$uploadDir = __DIR__ . '/../../data/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$savedName = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
$savedPath = $uploadDir . '/' . $savedName;
move_uploaded_file($file['tmp_name'], $savedPath);

$db = Database::getInstance();

$db->execute(
    'INSERT INTO upload_histories (user_id, file_name, file_path, total_count, duplicate_count, success_count) VALUES (?, ?, ?, ?, 0, 0)',
    [$user['id'], $file['name'], $savedPath, count($rows)]
);
$uploadHistoryId = (int)$db->lastInsertId();

// Coupang column mapping
$columnMap = [
    '상호명'         => 'company_name',
    '업체명'         => 'company_name',
    'company_name'   => 'company_name',
    '대표자'         => 'representative',
    '대표자명'       => 'representative',
    'representative' => 'representative',
    '연락처'         => 'phone',
    '전화번호'       => 'phone',
    'phone'          => 'phone',
    '이메일'         => 'email',
    'email'          => 'email',
    '주소1'          => 'address',
    '주소'           => 'address',
    '주소2'          => 'address_detail',
    '사업자등록번호'  => 'business_number',
    '상품명'         => 'product_name',
    '상품ID'         => 'product_id',
    '상품id'         => 'product_id',
    '검색키워드'     => 'keyword',
    '키워드'         => 'keyword',
    '평점수'         => 'rating_count',
    '추천수'         => 'recommend_count',
    '비추천수'       => 'not_recommend_count',
    '추천비율(%)'    => 'recommend_ratio',
    '추천비율'       => 'recommend_ratio',
    '수집일시'       => 'collected_at',
    '비고'           => 'notes',
    // Identity mappings so positional headerless layout passes through
    'address'            => 'address',
    'address_detail'     => 'address_detail',
    'business_number'    => 'business_number',
    'product_name'       => 'product_name',
    'product_id'         => 'product_id',
    'keyword'            => 'keyword',
    'rating_count'       => 'rating_count',
    'recommend_count'    => 'recommend_count',
    'not_recommend_count' => 'not_recommend_count',
    'recommend_ratio'    => 'recommend_ratio',
    'collected_at'       => 'collected_at',
    'notes'              => 'notes',
];

$mappedRows = [];
$duplicates = [];
$seenPhones = [];

foreach ($rows as $row) {
    $mapped = [];
    foreach ($row as $header => $value) {
        $dbField = $columnMap[$header] ?? null;
        if ($dbField !== null && $value !== '') {
            $mapped[$dbField] = trim((string)$value);
        }
    }

    $phone = $mapped['phone'] ?? '';
    $phone = preg_replace('/[\s\-]/', '', $phone);

    if (empty($phone)) {
        continue;
    }

    if (!preg_match('/^010/', $phone)) {
        continue;
    }

    $mapped['phone'] = $phone;

    foreach (['rating_count', 'recommend_count', 'not_recommend_count', 'recommend_ratio'] as $numField) {
        if (isset($mapped[$numField]) && $mapped[$numField] !== '') {
            $mapped[$numField] = (int)$mapped[$numField];
        }
    }

    // Convert Excel serial date to Y-m-d H:i:s
    if (isset($mapped['collected_at']) && $mapped['collected_at'] !== '') {
        $raw = $mapped['collected_at'];
        if (is_numeric($raw)) {
            $ts = (int)round(((float)$raw - 25569) * 86400);
            $mapped['collected_at'] = gmdate('Y-m-d H:i:s', $ts);
        }
    }

    if (CoupangDB::checkDuplicate($phone) || isset($seenPhones[$phone])) {
        $duplicates[] = [
            'phone' => $phone,
            'company_name' => $mapped['company_name'] ?? '',
            'representative' => $mapped['representative'] ?? '',
        ];
        continue;
    }

    $seenPhones[$phone] = true;
    $mapped['upload_history_id'] = $uploadHistoryId;
    $mapped['user_id'] = $user['id'];
    $mappedRows[] = $mapped;
}

$successCount = 0;
if (!empty($mappedRows)) {
    $db->beginTransaction();
    try {
        foreach ($mappedRows as $mRow) {
            CoupangDB::create($mRow);
            $successCount++;
        }

        $db->execute(
            'UPDATE upload_histories SET total_count = ?, duplicate_count = ?, success_count = ? WHERE id = ?',
            [count($rows), count($duplicates), $successCount, $uploadHistoryId]
        );

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        error_log('Coupang upload error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
        exit;
    }
} else {
    $db->execute(
        'UPDATE upload_histories SET total_count = ?, duplicate_count = ?, success_count = 0 WHERE id = ?',
        [count($rows), count($duplicates), $uploadHistoryId]
    );
}

Logger::log(
    ACTION_COUPANG_UPLOAD,
    TARGET_COUPANG,
    $uploadHistoryId,
    "엑셀 업로드: 전체 " . count($rows) . "건, 성공 {$successCount}건, 중복 " . count($duplicates) . "건"
);

echo json_encode([
    'success' => true,
    'data' => [
        'total' => count($rows),
        'success_count' => $successCount,
        'duplicate_count' => count($duplicates),
        'duplicates' => $duplicates,
    ],
], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('Coupang upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '파일 처리 중 오류가 발생했습니다.']);
    exit;
}

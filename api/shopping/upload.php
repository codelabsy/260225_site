<?php
/**
 * Shopping DB upload API.
 * POST: multipart/form-data with CSV file.
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
require_once __DIR__ . '/../../models/ShoppingDB.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다.']);
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

if ($ext !== 'csv') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSV 파일만 업로드 가능합니다.']);
    exit;
}

// Parse CSV
$rows = ExcelHandler::parseCSV($file['tmp_name']);

if (empty($rows)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '파일에 데이터가 없습니다.']);
    exit;
}

// Save uploaded file
$uploadDir = __DIR__ . '/../../data/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$savedName = date('Ymd_His') . '_' . uniqid() . '.csv';
$savedPath = $uploadDir . '/' . $savedName;
move_uploaded_file($file['tmp_name'], $savedPath);

$db = Database::getInstance();

// Create upload history record (will be updated with final counts after processing)
$db->execute(
    'INSERT INTO upload_histories (user_id, file_name, file_path, total_count, duplicate_count, success_count) VALUES (?, ?, ?, ?, 0, 0)',
    [$user['id'], $file['name'], $savedPath, count($rows)]
);
$uploadHistoryId = (int)$db->lastInsertId();

// Map CSV columns to DB fields
// Expected columns: 상호명, 담당자명/이름, 연락처/전화번호, 기타1, 기타2, 기타3
$mappedRows = [];
$duplicates = [];
$seenPhones = [];

foreach ($rows as $row) {
    // Try common column name variations
    $phone = $row['연락처'] ?? $row['전화번호'] ?? $row['phone'] ?? $row['휴대폰'] ?? '';
    $phone = preg_replace('/[\s\-]/', '', trim($phone));

    if (empty($phone)) {
        continue;
    }

    // Check duplicate (DB + intra-batch)
    if (ShoppingDB::checkDuplicate($phone) || isset($seenPhones[$phone])) {
        $duplicates[] = [
            'phone' => $phone,
            'company_name' => $row['상호명'] ?? $row['company_name'] ?? $row['업체명'] ?? '',
            'contact_name' => $row['담당자명'] ?? $row['이름'] ?? $row['contact_name'] ?? $row['대표자'] ?? '',
        ];
        continue;
    }

    $seenPhones[$phone] = true;

    $mappedRows[] = [
        'phone' => $phone,
        'company_name' => $row['상호명'] ?? $row['company_name'] ?? $row['업체명'] ?? '',
        'contact_name' => $row['담당자명'] ?? $row['이름'] ?? $row['contact_name'] ?? $row['대표자'] ?? '',
        'extra_field_1' => $row['기타1'] ?? $row['extra_field_1'] ?? $row['비고'] ?? null,
        'extra_field_2' => $row['기타2'] ?? $row['extra_field_2'] ?? $row['주소'] ?? null,
        'extra_field_3' => $row['기타3'] ?? $row['extra_field_3'] ?? $row['업종'] ?? null,
        'upload_history_id' => $uploadHistoryId,
    ];
}

// Bulk insert
$successCount = 0;
if (!empty($mappedRows)) {
    $db->beginTransaction();
    try {
        foreach ($mappedRows as $mRow) {
            $db->execute(
                'INSERT INTO shopping_db (user_id, company_name, contact_name, phone, status, upload_history_id, extra_field_1, extra_field_2, extra_field_3)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    null,
                    $mRow['company_name'],
                    $mRow['contact_name'],
                    $mRow['phone'],
                    SHOPPING_DEFAULT_STATUS,
                    $uploadHistoryId,
                    $mRow['extra_field_1'],
                    $mRow['extra_field_2'],
                    $mRow['extra_field_3'],
                ]
            );
            $successCount++;
        }

        // Update upload history
        $db->execute(
            'UPDATE upload_histories SET total_count = ?, duplicate_count = ?, success_count = ? WHERE id = ?',
            [count($rows), count($duplicates), $successCount, $uploadHistoryId]
        );

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        http_response_code(500);
        error_log('Shopping upload error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
        exit;
    }
} else {
    // Update upload history even if no new records
    $db->execute(
        'UPDATE upload_histories SET total_count = ?, duplicate_count = ?, success_count = 0 WHERE id = ?',
        [count($rows), count($duplicates), $uploadHistoryId]
    );
}

Logger::log(
    ACTION_SHOPPING_UPLOAD,
    TARGET_SHOPPING,
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
    error_log('Shopping upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '파일 처리 중 오류가 발생했습니다.']);
    exit;
}

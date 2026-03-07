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

// Column name mapping: Korean Excel headers → DB field names
$columnMap = [
    '상호명'         => 'company_name',
    '업체명'         => 'company_name',
    'company_name'   => 'company_name',
    '대표자'         => 'representative',
    '담당자명'       => 'representative',
    '이름'           => 'store_name',
    'representative' => 'representative',
    '연락처'         => 'phone',
    '전화번호'       => 'phone',
    'phone'          => 'phone',
    '핸드폰번호'     => 'mobile_phone',
    '휴대폰'         => 'mobile_phone',
    'mobile_phone'   => 'mobile_phone',
    '키워드'         => 'keyword',
    '페이지'         => 'page_number',
    '직구여부'       => 'is_overseas',
    '상품명'         => 'product_name',
    'url'            => 'store_url',
    'URL'            => 'store_url',
    '스토어'         => 'store',
    '리뷰수'         => 'review_count',
    '찜수'           => 'bookmark_count',
    '등급'           => 'grade',
    '서비스'         => 'service',
    '스토어아이디'   => 'store_id',
    '스토어설명'     => 'store_description',
    '이메일'         => 'email',
    'email'          => 'email',
    '사업자등록번호'  => 'business_number',
    '사업장소재지'    => 'address',
    '주소'           => 'address',
    '통신판매업번호'  => 'ecommerce_number',
    '10대'           => 'age_10s',
    '20대'           => 'age_20s',
    '30대'           => 'age_30s',
    '40대'           => 'age_40s',
    '50대'           => 'age_50s',
    '60대'           => 'age_60s',
    '남자'           => 'gender_male',
    '여자'           => 'gender_female',
    '톡톡주소'       => 'talktalk_url',
    '비고'           => 'notes',
];

$mappedRows = [];
$duplicates = [];
$seenPhones = [];

foreach ($rows as $row) {
    // Map Korean headers to DB field names
    $mapped = [];
    foreach ($row as $header => $value) {
        $dbField = $columnMap[$header] ?? null;
        if ($dbField !== null && $value !== '') {
            $mapped[$dbField] = trim($value);
        }
    }

    // Get phone (try phone first, then mobile_phone)
    $phone = $mapped['phone'] ?? '';
    $phone = preg_replace('/[\s\-]/', '', $phone);
    $mobilePhone = $mapped['mobile_phone'] ?? '';
    $mobilePhone = preg_replace('/[\s\-]/', '', $mobilePhone);

    // Use mobile_phone as phone if phone is empty
    if (empty($phone) && !empty($mobilePhone)) {
        $phone = $mobilePhone;
    }

    if (empty($phone)) {
        continue;
    }

    $mapped['phone'] = $phone;
    if (!empty($mobilePhone)) {
        $mapped['mobile_phone'] = $mobilePhone;
    }

    // Cast numeric fields
    if (isset($mapped['review_count'])) {
        $mapped['review_count'] = (int)$mapped['review_count'];
    }
    if (isset($mapped['bookmark_count'])) {
        $mapped['bookmark_count'] = (int)$mapped['bookmark_count'];
    }

    // Check duplicate (DB + intra-batch)
    if (ShoppingDB::checkDuplicate($phone) || isset($seenPhones[$phone])) {
        $duplicates[] = [
            'phone' => $phone,
            'company_name' => $mapped['company_name'] ?? '',
            'representative' => $mapped['representative'] ?? '',
        ];
        continue;
    }

    $seenPhones[$phone] = true;
    $mapped['upload_history_id'] = $uploadHistoryId;
    $mappedRows[] = $mapped;
}

// Bulk insert
$successCount = 0;
if (!empty($mappedRows)) {
    $db->beginTransaction();
    try {
        foreach ($mappedRows as $mRow) {
            ShoppingDB::create($mRow);
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

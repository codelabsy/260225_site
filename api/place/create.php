<?php
/**
 * Place DB Create API endpoint.
 * POST: Creates a new place DB record (admin only).
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../models/PlaceDB.php';

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Permission::verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF 토큰이 유효하지 않습니다.']);
    exit;
}

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '관리자만 등록할 수 있습니다.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$companyName = trim($input['company_name'] ?? '');
$phone = trim($input['phone'] ?? '');
$region = trim($input['region'] ?? '');
$registerDate = trim($input['register_date'] ?? date('Y-m-d'));
$source = trim($input['source'] ?? '');
$initialMemo = trim($input['initial_memo'] ?? '');

if (!Validator::required($companyName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '상호명을 입력해주세요.']);
    exit;
}

if (!Validator::required($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '연락처를 입력해주세요.']);
    exit;
}

if (!empty($registerDate) && !Validator::date($registerDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '등록일자 형식이 올바르지 않습니다. (YYYY-MM-DD)']);
    exit;
}

// Check duplicate phone (warning only)
$db = Database::getInstance();
$duplicate = $db->fetch(
    'SELECT id, company_name, phone FROM place_db WHERE phone = ?',
    [preg_replace('/[\s\-]/', '', $phone)]
);

$warning = null;
if ($duplicate) {
    $warning = '동일한 연락처(' . $phone . ')가 이미 등록되어 있습니다. (상호: ' . $duplicate['company_name'] . ')';
}

// Create record
try {
    $id = PlaceDB::create([
        'company_name'  => $companyName,
        'phone'         => preg_replace('/[\s\-]/', '', $phone),
        'region'        => $region ?: null,
        'register_date' => $registerDate,
        'source'        => $source ?: null,
        'initial_memo'  => $initialMemo ?: null,
    ]);

    Logger::log(
        ACTION_PLACE_CREATE,
        TARGET_PLACE,
        $id,
        '플레이스DB 등록: ' . $companyName
    );

    $response = [
        'success' => true,
        'message' => 'DB가 등록되었습니다.',
        'data'    => ['id' => $id],
    ];

    if ($warning) {
        $response['warning'] = $warning;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Place create error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
}

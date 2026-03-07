<?php
/**
 * ERP Update API endpoint.
 * POST: Update an existing company and its details.
 * Admin only.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../models/Company.php';

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
    echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '잘못된 요청 데이터입니다.']);
    exit;
}

$id = (int)($input['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 ID입니다.']);
    exit;
}

// Get existing data for logging
$existing = Company::find($id);
if (!$existing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '업체를 찾을 수 없습니다.']);
    exit;
}

// Validate
$errors = [];

if (isset($input['register_date']) && !Validator::date($input['register_date'])) {
    $errors[] = '등록일 형식이 올바르지 않습니다.';
}

if (isset($input['product_name']) && !Validator::required($input['product_name'])) {
    $errors[] = '상품명은 필수입니다.';
}

if (isset($input['company_name']) && !Validator::required($input['company_name'])) {
    $errors[] = '상호명은 필수입니다.';
}

if (!empty($input['email']) && !Validator::email($input['email'])) {
    $errors[] = '이메일 형식이 올바르지 않습니다.';
}

$dateFields = ['sales_register_date', 'work_start_date', 'work_end_date', 'contract_start', 'contract_end'];
foreach ($dateFields as $field) {
    if (!empty($input[$field]) && !Validator::date($input[$field])) {
        $errors[] = $field . ' 날짜 형식이 올바르지 않습니다.';
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Build update data
$data = [];
$companyFields = ['user_id', 'register_date', 'product_name', 'company_name', 'payment_amount', 'invoice_amount', 'execution_cost', 'registrant_position'];
$detailFields = ['sales_register_date', 'work_start_date', 'work_end_date', 'contract_start', 'contract_end', 'business_name', 'ceo_name', 'phone', 'payment_type', 'business_number', 'work_keywords', 'work_content', 'email', 'naver_account', 'detail_execution_cost'];

foreach (array_merge($companyFields, $detailFields) as $field) {
    if (array_key_exists($field, $input)) {
        $value = $input[$field];
        if (in_array($field, ['payment_amount', 'invoice_amount', 'execution_cost', 'detail_execution_cost'])) {
            $value = (float)$value;
        } elseif ($field === 'user_id') {
            $value = !empty($value) ? (int)$value : null;
        } elseif (is_string($value)) {
            $value = trim($value);
        }
        $data[$field] = $value;
    }
}

try {
    Company::update($id, $data);

    // Log changes
    $oldValue = json_encode($existing, JSON_UNESCAPED_UNICODE);
    $newValue = json_encode($data, JSON_UNESCAPED_UNICODE);

    Logger::log(
        ACTION_COMPANY_UPDATE,
        TARGET_COMPANY,
        $id,
        '업체 수정: ' . ($data['company_name'] ?? $existing['company_name']),
        $oldValue,
        $newValue
    );

    echo json_encode([
        'success' => true,
        'message' => '업체 정보가 수정되었습니다.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '업체 수정 중 오류가 발생했습니다.']);
}

<?php
/**
 * ERP Create API endpoint.
 * POST: Create a new company with details.
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

// Validate required fields
$errors = [];

if (!Validator::required($input['register_date'] ?? '')) {
    $errors[] = '등록일은 필수입니다.';
} elseif (!Validator::date($input['register_date'])) {
    $errors[] = '등록일 형식이 올바르지 않습니다. (YYYY-MM-DD)';
}

if (!Validator::required($input['product_name'] ?? '')) {
    $errors[] = '상품명은 필수입니다.';
}

if (!Validator::required($input['company_name'] ?? '')) {
    $errors[] = '상호명은 필수입니다.';
}

if (!empty($input['email']) && !Validator::email($input['email'])) {
    $errors[] = '이메일 형식이 올바르지 않습니다.';
}

// Validate optional date fields
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

// Sanitize numeric values
$paymentAmount = (float)($input['payment_amount'] ?? 0);
$invoiceAmount = (float)($input['invoice_amount'] ?? 0);
$executionCost = (float)($input['execution_cost'] ?? 0);
$detailExecutionCost = (float)($input['detail_execution_cost'] ?? 0);

$data = [
    'user_id' => !empty($input['user_id']) ? (int)$input['user_id'] : null,
    'register_date' => $input['register_date'],
    'product_name' => trim($input['product_name']),
    'company_name' => trim($input['company_name']),
    'payment_amount' => $paymentAmount,
    'invoice_amount' => $invoiceAmount,
    'execution_cost' => $executionCost,
    'registrant_position' => trim($input['registrant_position'] ?? ''),
    'sales_register_date' => $input['sales_register_date'] ?? null,
    'work_start_date' => $input['work_start_date'] ?? null,
    'work_end_date' => $input['work_end_date'] ?? null,
    'contract_start' => $input['contract_start'] ?? null,
    'contract_end' => $input['contract_end'] ?? null,
    'business_name' => trim($input['business_name'] ?? ''),
    'ceo_name' => trim($input['ceo_name'] ?? ''),
    'phone' => trim($input['phone'] ?? ''),
    'payment_type' => trim($input['payment_type'] ?? ''),
    'business_number' => trim($input['business_number'] ?? ''),
    'work_keywords' => trim($input['work_keywords'] ?? ''),
    'work_content' => trim($input['work_content'] ?? ''),
    'email' => trim($input['email'] ?? ''),
    'naver_account' => trim($input['naver_account'] ?? ''),
    'detail_execution_cost' => $detailExecutionCost,
];

try {
    $companyId = Company::create($data);

    Logger::log(
        ACTION_COMPANY_CREATE,
        TARGET_COMPANY,
        $companyId,
        '업체 등록: ' . $data['company_name']
    );

    echo json_encode([
        'success' => true,
        'message' => '업체가 등록되었습니다.',
        'data' => ['id' => $companyId],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '업체 등록 중 오류가 발생했습니다.']);
}

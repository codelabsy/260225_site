<?php
/**
 * ERP Carry-over API endpoint.
 * POST: Copy a company record to a new period.
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
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 ID입니다.']);
    exit;
}

try {
    $newId = Company::carryOver($id);

    Logger::log(
        ACTION_COMPANY_CARRYOVER,
        TARGET_COMPANY,
        $id,
        '업체 이월: ID ' . $id . ' -> ' . $newId
    );

    echo json_encode([
        'success' => true,
        'message' => '이월 처리가 완료되었습니다.',
        'data' => ['new_id' => $newId],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('ERP carry-over error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
}

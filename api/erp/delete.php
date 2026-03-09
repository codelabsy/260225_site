<?php
/**
 * ERP Delete API endpoint.
 * POST: Soft-delete a company (set is_active = 0).
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

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 ID입니다.']);
    exit;
}

$existing = Company::find($id);
if (!$existing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '업체를 찾을 수 없습니다.']);
    exit;
}

// 관리자이거나 자기 데이터만 삭제 가능
$currentUser = Auth::user();
if (!Auth::isAdmin() && (int)($existing['user_id'] ?? 0) !== (int)$currentUser['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '본인의 데이터만 삭제할 수 있습니다.']);
    exit;
}

try {
    Company::delete($id);

    Logger::log(
        ACTION_COMPANY_DELETE,
        TARGET_COMPANY,
        $id,
        '업체 삭제: ' . ($existing['company_name'] ?? '')
    );

    echo json_encode([
        'success' => true,
        'message' => '업체가 삭제되었습니다.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('ERP delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '삭제 중 오류가 발생했습니다.']);
}

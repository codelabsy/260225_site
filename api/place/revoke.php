<?php
/**
 * Place DB Revoke API endpoint.
 * POST: Revokes (unassigns) place records from users (admin only).
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
    echo json_encode(['success' => false, 'message' => '관리자만 회수할 수 있습니다.']);
    exit;
}

$admin = Auth::user();
$input = json_decode(file_get_contents('php://input'), true);

$ids = $input['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '회수할 항목을 선택해주세요.']);
    exit;
}

try {
    $count = PlaceDB::revoke($ids, (int) $admin['id']);

    Logger::log(
        ACTION_PLACE_REVOKE,
        TARGET_PLACE,
        null,
        $count . '건 회수',
        json_encode($ids),
        null
    );

    echo json_encode([
        'success' => true,
        'message' => $count . '건이 회수되었습니다.',
        'count'   => $count,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Place revoke error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
}

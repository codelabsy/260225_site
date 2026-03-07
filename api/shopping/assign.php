<?php
/**
 * Shopping DB assign API (admin only).
 * POST: {ids: [...], user_id}
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
require_once __DIR__ . '/../../models/ShoppingDB.php';
require_once __DIR__ . '/../../models/User.php';

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
$input = json_decode(file_get_contents('php://input'), true);

$ids = $input['ids'] ?? [];
$targetUserId = (int)($input['user_id'] ?? 0);

if (empty($ids) || !is_array($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '배분할 DB를 선택해주세요.']);
    exit;
}

if ($targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '배분할 직원을 선택해주세요.']);
    exit;
}

// Verify target user exists
$targetUser = User::find($targetUserId);
if (!$targetUser) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '존재하지 않는 직원입니다.']);
    exit;
}

try {
    $count = ShoppingDB::assign($ids, $targetUserId, $user['id']);

    Logger::log(
        ACTION_SHOPPING_ASSIGN,
        TARGET_SHOPPING,
        null,
        "{$targetUser['name']}에게 {$count}건 배분",
        null,
        json_encode(['ids' => $ids, 'user_id' => $targetUserId], JSON_UNESCAPED_UNICODE)
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'count' => $count,
            'user_name' => $targetUser['name'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Shopping assign error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
}

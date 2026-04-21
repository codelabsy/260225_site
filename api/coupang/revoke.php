<?php
/**
 * Coupang DB revoke API (admin only).
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
require_once __DIR__ . '/../../models/CoupangDB.php';

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

if (empty($ids) || !is_array($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '회수할 DB를 선택해주세요.']);
    exit;
}

try {
    $count = CoupangDB::revoke($ids, $user['id']);

    Logger::log(
        ACTION_COUPANG_REVOKE,
        TARGET_COUPANG,
        null,
        "{$count}건 회수",
        json_encode(['ids' => $ids], JSON_UNESCAPED_UNICODE),
        null
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'count' => $count,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Coupang revoke error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
}

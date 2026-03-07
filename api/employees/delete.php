<?php
/**
 * Employee delete (deactivate) API endpoint.
 * POST: JSON {id}
 * Admin only. Soft deletes and revokes assigned DBs.
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
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../core/Database.php';

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

if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

$id = (int) $input['id'];

$user = User::find($id);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '직원을 찾을 수 없습니다.']);
    exit;
}

// Prevent deactivating admin
if ($user['role'] === ROLE_ADMIN) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '관리자 계정은 비활성화할 수 없습니다.']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    // Deactivate user
    User::deactivate($id);

    // Revoke assigned shopping DBs
    $shoppingCount = $db->execute(
        "UPDATE shopping_db SET user_id = NULL, updated_at = datetime('now', 'localtime') WHERE user_id = ?",
        [$id]
    );

    // Revoke assigned place DBs
    $placeCount = $db->execute(
        "UPDATE place_db SET user_id = NULL, updated_at = datetime('now', 'localtime') WHERE user_id = ?",
        [$id]
    );

    Logger::log(
        ACTION_USER_DELETE,
        TARGET_USER,
        $id,
        '직원 비활성화: ' . $user['name'] . ' (쇼핑DB ' . $shoppingCount . '건, 플레이스DB ' . $placeCount . '건 회수)'
    );

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => '직원이 비활성화되었습니다. (배정 DB ' . ($shoppingCount + $placeCount) . '건 회수)',
    ]);
} catch (Exception $e) {
    $db->rollback();
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '직원 비활성화 중 오류가 발생했습니다.']);
}

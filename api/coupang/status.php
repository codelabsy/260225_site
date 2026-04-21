<?php
/**
 * Coupang DB status update API.
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

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Permission::verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF 토큰이 유효하지 않습니다.']);
    exit;
}

$user = Auth::user();
$input = json_decode(file_get_contents('php://input'), true);

$id = (int)($input['id'] ?? 0);
$status = trim($input['status'] ?? '');

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID가 필요합니다.']);
    exit;
}

if (!in_array($status, COUPANG_STATUSES)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 상태입니다.']);
    exit;
}

$record = CoupangDB::find($id);
if (!$record) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '데이터를 찾을 수 없습니다.']);
    exit;
}

if (!Auth::isAdmin() && (int)$record['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

$oldStatus = $record['status'];

try {
    CoupangDB::updateStatus($id, $status, $user['id']);

    Logger::log(
        ACTION_COUPANG_STATUS,
        TARGET_COUPANG,
        $id,
        "상태 변경: {$oldStatus} -> {$status}",
        $oldStatus,
        $status
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $id,
            'old_status' => $oldStatus,
            'new_status' => $status,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Coupang status update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
}

<?php
/**
 * Place DB Memo API endpoint.
 * POST: Creates a new memo for a place record.
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
require_once __DIR__ . '/../../models/Memo.php';

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

$user = Auth::user();
$input = json_decode(file_get_contents('php://input'), true);

$targetId = (int) ($input['target_id'] ?? 0);
$content = trim($input['content'] ?? '');

if ($targetId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 ID입니다.']);
    exit;
}

if (empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '메모 내용을 입력해주세요.']);
    exit;
}

// Check permission
$record = PlaceDB::find($targetId);
if (!$record) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '레코드를 찾을 수 없습니다.']);
    exit;
}

if (!Auth::isAdmin() && (int) $record['user_id'] !== (int) $user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

try {
    $memoId = Memo::create(TARGET_PLACE, $targetId, (int) $user['id'], $content);

    Logger::log(
        ACTION_MEMO_CREATE,
        TARGET_PLACE,
        $targetId,
        '메모 작성'
    );

    echo json_encode([
        'success' => true,
        'message' => '메모가 저장되었습니다.',
        'data'    => [
            'id'         => $memoId,
            'user_name'  => $user['name'],
            'content'    => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Place memo error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * Coupang DB memo API.
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
require_once __DIR__ . '/../../models/Memo.php';

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

$targetId = (int)($input['target_id'] ?? 0);
$content = trim($input['content'] ?? '');

if ($targetId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID가 필요합니다.']);
    exit;
}

if ($content === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '메모 내용을 입력해주세요.']);
    exit;
}

$record = CoupangDB::find($targetId);
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

try {
    $memoId = Memo::create(TARGET_COUPANG, $targetId, $user['id'], $content);

    Logger::log(
        ACTION_MEMO_CREATE,
        TARGET_COUPANG,
        $targetId,
        '쿠팡DB 메모 작성'
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $memoId,
            'user_name' => $user['name'],
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Coupang memo error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
}

<?php
/**
 * Coupang DB delete API.
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

$ids = [];
if (!empty($input['ids']) && is_array($input['ids'])) {
    $ids = array_map('intval', $input['ids']);
} elseif (!empty($input['id'])) {
    $ids = [(int)$input['id']];
}

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '삭제할 항목을 선택해주세요.']);
    exit;
}

try {
    $deleted = 0;
    $denied = 0;

    foreach ($ids as $id) {
        $record = CoupangDB::find($id);
        if (!$record) continue;

        CoupangDB::delete($id);
        $deleted++;
    }

    Logger::log(
        ACTION_COUPANG_DELETE,
        TARGET_COUPANG,
        $ids[0],
        "쿠팡DB 삭제: {$deleted}건"
    );

    echo json_encode([
        'success' => true,
        'message' => $deleted . '건이 삭제되었습니다.',
        'data' => ['deleted' => $deleted, 'denied' => $denied],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Coupang delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '삭제 중 오류가 발생했습니다.']);
}

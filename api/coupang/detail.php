<?php
/**
 * Coupang DB detail API.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../models/CoupangDB.php';
require_once __DIR__ . '/../../models/StatusHistory.php';
require_once __DIR__ . '/../../models/Memo.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user = Auth::user();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID가 필요합니다.']);
    exit;
}

try {
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

    $statusHistories = StatusHistory::getByTarget(TARGET_COUPANG, $id);
    $memos = Memo::getByTarget(TARGET_COUPANG, $id);

    echo json_encode([
        'success' => true,
        'data' => [
            'record' => $record,
            'status_histories' => $statusHistories,
            'memos' => $memos,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

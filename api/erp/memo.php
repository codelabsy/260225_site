<?php
/**
 * ERP Memo API endpoint.
 * POST: Create a memo for a company.
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

$targetId = (int)($input['target_id'] ?? 0);
$content = trim($input['content'] ?? '');

if ($targetId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 대상 ID입니다.']);
    exit;
}

if (empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '메모 내용을 입력하세요.']);
    exit;
}

// Check company exists and permission
$company = Company::find($targetId);
if (!$company) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '업체를 찾을 수 없습니다.']);
    exit;
}

if (!Permission::canAccessData((int)$company['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '접근 권한이 없습니다.']);
    exit;
}

try {
    $memoId = Memo::create('company', $targetId, (int)$user['id'], $content);

    Logger::log(
        ACTION_MEMO_CREATE,
        TARGET_COMPANY,
        $targetId,
        '메모 작성: ' . mb_substr($content, 0, 50, 'UTF-8')
    );

    echo json_encode([
        'success' => true,
        'message' => '메모가 저장되었습니다.',
        'data' => [
            'id' => $memoId,
            'user_name' => $user['name'],
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '메모 저장 중 오류가 발생했습니다.']);
}

<?php
/**
 * ERP Detail API endpoint.
 * GET: Returns company detail with memos.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../models/Company.php';
require_once __DIR__ . '/../../models/Memo.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 ID입니다.']);
    exit;
}

try {
    $company = Company::find($id);
    if (!$company) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '업체를 찾을 수 없습니다.']);
        exit;
    }

    // Permission check: admin or own data
    if (!Permission::canAccessData((int)$company['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '접근 권한이 없습니다.']);
        exit;
    }

    // Get memos
    $memos = Memo::getByTarget('company', $id);

    echo json_encode([
        'success' => true,
        'data' => [
            'company' => $company,
            'memos' => $memos,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

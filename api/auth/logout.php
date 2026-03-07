<?php
/**
 * Logout API endpoint.
 * POST request.
 * Returns JSON response.
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

try {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Permission::verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF 토큰이 유효하지 않습니다.']);
        exit;
    }

    $user = Auth::user();
    if ($user) {
        Logger::log(ACTION_LOGOUT, TARGET_USER, $user['id'], $user['name'] . ' 로그아웃');
    }

    Auth::logout();

    echo json_encode([
        'success' => true,
        'message' => '로그아웃 되었습니다.',
        'redirect' => '/login.php',
    ]);
} catch (Exception $e) {
    error_log('Logout error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

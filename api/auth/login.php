<?php
/**
 * Login API endpoint.
 * POST: username, password
 * Returns JSON response.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Logger.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '아이디와 비밀번호를 입력해주세요.']);
        exit;
    }

    $user = Auth::login($username, $password);

    if ($user) {
        Logger::log(ACTION_LOGIN, TARGET_USER, $user['id'], $user['name'] . ' 로그인');
        echo json_encode([
            'success' => true,
            'message' => '로그인 성공',
            'redirect' => '/dashboard.php',
        ]);
    } else {
        http_response_code(401);
        Logger::log(ACTION_LOGIN, null, null, '로그인 실패: ' . $username);
        echo json_encode([
            'success' => false,
            'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
        ]);
    }
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

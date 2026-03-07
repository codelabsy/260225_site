<?php
/**
 * Employee create API endpoint.
 * POST: JSON {username, password, name, position, phone, email, incentive_rate}
 * Admin only.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Validator.php';
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

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

// Validation
$errors = [];

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$name = trim($input['name'] ?? '');
$position = trim($input['position'] ?? '');
$phone = trim($input['phone'] ?? '');
$email = trim($input['email'] ?? '');
$incentiveRate = floatval($input['incentive_rate'] ?? 0);

if (!Validator::username($username)) {
    $errors[] = '아이디는 4~20자 영문/숫자만 가능합니다.';
}

if (!Validator::password($password)) {
    $errors[] = '비밀번호는 8자 이상이어야 합니다.';
}

if (!Validator::required($name)) {
    $errors[] = '이름은 필수 항목입니다.';
}

if ($phone && !Validator::phone($phone)) {
    $errors[] = '연락처 형식이 올바르지 않습니다.';
}

if ($email && !Validator::email($email)) {
    $errors[] = '이메일 형식이 올바르지 않습니다.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors]);
    exit;
}

// Username duplicate check
$existing = User::findByUsername($username);
if ($existing) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => '이미 사용 중인 아이디입니다.']);
    exit;
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    $userId = User::create([
        'username' => $username,
        'password' => $password,
        'name' => $name,
        'role' => ROLE_EMPLOYEE,
        'position' => $position ?: null,
        'phone' => $phone ?: null,
        'email' => $email ?: null,
    ]);

    // Insert incentive rate
    $db->execute(
        "INSERT INTO employee_incentives (user_id, incentive_rate) VALUES (?, ?)",
        [$userId, $incentiveRate]
    );

    Logger::log(
        ACTION_USER_CREATE,
        TARGET_USER,
        $userId,
        '직원 생성: ' . $name . ' (' . $username . ')'
    );

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => '직원이 생성되었습니다.',
        'data' => ['id' => $userId],
    ]);
} catch (Exception $e) {
    $db->rollback();
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '직원 생성 중 오류가 발생했습니다.']);
}

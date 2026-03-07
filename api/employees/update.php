<?php
/**
 * Employee update API endpoint.
 * POST: JSON {id, name, position, phone, email, password(optional), is_active, incentive_rate}
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

if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

$id = (int) $input['id'];

// Find existing user
$user = User::find($id);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => '직원을 찾을 수 없습니다.']);
    exit;
}

// Validation
$errors = [];

$name = trim($input['name'] ?? $user['name']);
$position = trim($input['position'] ?? '');
$phone = trim($input['phone'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$isActive = isset($input['is_active']) ? (int) $input['is_active'] : $user['is_active'];
$incentiveRate = isset($input['incentive_rate']) ? floatval($input['incentive_rate']) : null;

if (!Validator::required($name)) {
    $errors[] = '이름은 필수 항목입니다.';
}

if ($password && !Validator::password($password)) {
    $errors[] = '비밀번호는 8자 이상이어야 합니다.';
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

$db = Database::getInstance();

try {
    $db->beginTransaction();

    // Build old values for logging
    $oldValues = [
        'name' => $user['name'],
        'position' => $user['position'],
        'phone' => $user['phone'],
        'email' => $user['email'],
        'is_active' => $user['is_active'],
    ];

    // Get old incentive rate
    $oldIncentive = $db->fetch(
        "SELECT incentive_rate FROM employee_incentives WHERE user_id = ?",
        [$id]
    );
    $oldValues['incentive_rate'] = $oldIncentive ? $oldIncentive['incentive_rate'] : 0;

    // Update user
    $updateData = [
        'name' => $name,
        'position' => $position ?: null,
        'phone' => $phone ?: null,
        'email' => $email ?: null,
        'is_active' => $isActive,
    ];

    if ($password) {
        $updateData['password'] = $password;
    }

    User::update($id, $updateData);

    // Update/Insert incentive rate
    if ($incentiveRate !== null) {
        $existing = $db->fetch("SELECT id FROM employee_incentives WHERE user_id = ?", [$id]);
        if ($existing) {
            $db->execute(
                "UPDATE employee_incentives SET incentive_rate = ?, updated_at = datetime('now', 'localtime') WHERE user_id = ?",
                [$incentiveRate, $id]
            );
        } else {
            $db->execute(
                "INSERT INTO employee_incentives (user_id, incentive_rate) VALUES (?, ?)",
                [$id, $incentiveRate]
            );
        }
    }

    // Build new values for logging
    $newValues = [
        'name' => $name,
        'position' => $position ?: null,
        'phone' => $phone ?: null,
        'email' => $email ?: null,
        'is_active' => $isActive,
        'incentive_rate' => $incentiveRate ?? $oldValues['incentive_rate'],
    ];

    if ($password) {
        $newValues['password'] = '(변경됨)';
        $oldValues['password'] = '(이전값)';
    }

    Logger::log(
        ACTION_USER_UPDATE,
        TARGET_USER,
        $id,
        '직원 수정: ' . $name,
        json_encode($oldValues, JSON_UNESCAPED_UNICODE),
        json_encode($newValues, JSON_UNESCAPED_UNICODE)
    );

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => '직원 정보가 수정되었습니다.',
    ]);
} catch (Exception $e) {
    $db->rollback();
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '직원 수정 중 오류가 발생했습니다.']);
}

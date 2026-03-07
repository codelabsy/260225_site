<?php
/**
 * Employee list API endpoint.
 * GET: Returns all employees with incentive rates.
 * Admin only.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';

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

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();

    $showAll = isset($_GET['all']) && $_GET['all'] === '1';

    $sql = "SELECT u.id, u.username, u.name, u.role, u.position, u.phone, u.email,
                   u.is_active, u.created_at, u.updated_at,
                   COALESCE(ei.incentive_rate, 0) AS incentive_rate
            FROM users u
            LEFT JOIN employee_incentives ei ON ei.user_id = u.id
            WHERE u.role = 'EMPLOYEE'";

    if (!$showAll) {
        $sql .= " AND u.is_active = 1";
    }

    $sql .= " ORDER BY u.name ASC";

    $employees = $db->fetchAll($sql);

    echo json_encode([
        'success' => true,
        'data' => $employees,
        'total' => count($employees),
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

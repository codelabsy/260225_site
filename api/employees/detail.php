<?php
/**
 * Employee detail API endpoint.
 * GET: ?id=N
 * Admin only. Returns employee info + incentive + sales summary + assigned DB counts.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';

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

$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '직원 ID가 필요합니다.']);
    exit;
}

try {
    $db = Database::getInstance();

    // Employee info with incentive
    $employee = $db->fetch(
        "SELECT u.*, COALESCE(ei.incentive_rate, 0) AS incentive_rate
         FROM users u
         LEFT JOIN employee_incentives ei ON ei.user_id = u.id
         WHERE u.id = ?",
        [$id]
    );

    if (!$employee) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '직원을 찾을 수 없습니다.']);
        exit;
    }

    // Remove password from response
    unset($employee['password']);

    // Sales summary (companies)
    $salesSummary = $db->fetch(
        "SELECT COUNT(*) AS total_count,
                COALESCE(SUM(payment_amount), 0) AS total_payment,
                COALESCE(SUM(net_margin), 0) AS total_margin
         FROM companies
         WHERE user_id = ? AND is_active = 1",
        [$id]
    );

    // Assigned DB counts
    $shoppingCount = $db->fetch(
        "SELECT COUNT(*) AS cnt FROM shopping_db WHERE user_id = ?",
        [$id]
    );

    $placeCount = $db->fetch(
        "SELECT COUNT(*) AS cnt FROM place_db WHERE user_id = ?",
        [$id]
    );

    $employee['sales_summary'] = $salesSummary;
    $employee['shopping_db_count'] = (int) $shoppingCount['cnt'];
    $employee['place_db_count'] = (int) $placeCount['cnt'];

    echo json_encode([
        'success' => true,
        'data' => $employee,
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

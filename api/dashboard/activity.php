<?php
/**
 * Dashboard Recent Activity API
 * GET /api/dashboard/activity.php
 *
 * Returns recent activity log entries for the dashboard.
 * Admin only.
 *
 * Parameters:
 *   limit - Number of entries (default 20, max 100)
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../models/ActivityLog.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin only']);
    exit;
}

try {
    $limit = isset($_GET['limit']) ? max(1, min((int) $_GET['limit'], 100)) : 20;

    $logs = ActivityLog::all([
        'limit' => $limit,
    ]);

    echo json_encode([
        'success' => true,
        'data' => $logs,
    ]);
} catch (Exception $e) {
    error_log('Dashboard activity error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

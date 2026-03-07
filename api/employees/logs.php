<?php
/**
 * Activity logs API endpoint.
 * GET: ?page=1&size=50&user_id=N&action=ACTION&from=YYYY-MM-DD&to=YYYY-MM-DD
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
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../models/ActivityLog.php';

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

$page = max(1, (int) ($_GET['page'] ?? 1));
$size = (int) ($_GET['size'] ?? 50);
if (!in_array($size, PAGE_SIZES)) {
    $size = DEFAULT_PAGE_SIZE;
}

$filters = [];

if (!empty($_GET['user_id'])) {
    $filters['user_id'] = (int) $_GET['user_id'];
}

if (!empty($_GET['action'])) {
    $filters['action'] = $_GET['action'];
}

if (!empty($_GET['from']) || !empty($_GET['to'])) {
    $filters['period'] = [];
    if (!empty($_GET['from'])) {
        $filters['period']['from'] = $_GET['from'];
    }
    if (!empty($_GET['to'])) {
        $filters['period']['to'] = $_GET['to'];
    }
}

try {
    // Get total count for pagination
    $db = Database::getInstance();
    $where = [];
    $params = [];

    if (!empty($filters['user_id'])) {
        $where[] = 'al.user_id = ?';
        $params[] = $filters['user_id'];
    }

    if (!empty($filters['action'])) {
        $where[] = 'al.action = ?';
        $params[] = $filters['action'];
    }

    if (!empty($filters['period']['from'])) {
        $where[] = 'al.created_at >= ?';
        $params[] = $filters['period']['from'];
    }

    if (!empty($filters['period']['to'])) {
        $where[] = 'al.created_at <= ?';
        $params[] = $filters['period']['to'] . ' 23:59:59';
    }

    $whereClause = '';
    if (!empty($where)) {
        $whereClause = 'WHERE ' . implode(' AND ', $where);
    }

    $totalRow = $db->fetch(
        "SELECT COUNT(*) AS cnt FROM activity_logs al $whereClause",
        $params
    );
    $total = (int) $totalRow['cnt'];
    $totalPages = max(1, ceil($total / $size));
    $offset = ($page - 1) * $size;

    // Fetch logs with pagination
    $filters['limit'] = $size;
    $filters['offset'] = $offset;
    $logs = ActivityLog::all($filters);

    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'page' => $page,
            'size' => $size,
            'total' => $total,
            'total_pages' => $totalPages,
        ],
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

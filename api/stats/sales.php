<?php
/**
 * Sales Statistics API
 * GET /api/stats/sales.php
 *
 * Parameters:
 *   type         - daily | monthly | yearly
 *   period_start - Start date (YYYY-MM-DD)
 *   period_end   - End date (YYYY-MM-DD)
 *   user_id      - Filter by user (admin only)
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../config/constants.php';

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

$user = Auth::user();
$isAdmin = Auth::isAdmin();

try {
    $db = Database::getInstance();

    $type = $_GET['type'] ?? 'monthly';
$periodStart = $_GET['period_start'] ?? date('Y-01-01');
$periodEnd = $_GET['period_end'] ?? date('Y-12-31');
$filterUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;

// Non-admin can only see own data
if (!$isAdmin) {
    $filterUserId = $user['id'];
}

$where = ['c.is_active = 1'];
$params = [];

if ($filterUserId) {
    $where[] = 'c.user_id = ?';
    $params[] = $filterUserId;
}

$where[] = 'c.register_date >= ?';
$params[] = $periodStart;
$where[] = 'c.register_date <= ?';
$params[] = $periodEnd;

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Group by expression
switch ($type) {
    case 'daily':
        $groupExpr = "c.register_date";
        $labelExpr = "c.register_date";
        break;
    case 'yearly':
        $groupExpr = "strftime('%Y', c.register_date)";
        $labelExpr = "strftime('%Y', c.register_date) || '년'";
        break;
    case 'monthly':
    default:
        $groupExpr = "strftime('%Y-%m', c.register_date)";
        $labelExpr = "strftime('%Y-%m', c.register_date)";
        $type = 'monthly';
        break;
}

$rows = $db->fetchAll(
    "SELECT
        $groupExpr AS period_key,
        $labelExpr AS label,
        COALESCE(SUM(c.payment_amount), 0) AS sales,
        COALESCE(SUM(c.execution_cost), 0) AS execution_cost,
        COALESCE(SUM(c.vat), 0) AS vat,
        COALESCE(SUM(c.net_margin), 0) AS margin,
        COALESCE(SUM(c.invoice_amount), 0) AS invoice,
        COUNT(*) AS count
     FROM companies c
     $whereClause
     GROUP BY $groupExpr
     ORDER BY period_key ASC",
    $params
);

$data = array_map(function ($row) {
    return [
        'period' => $row['period_key'],
        'label' => $row['label'],
        'sales' => (float) $row['sales'],
        'execution_cost' => (float) $row['execution_cost'],
        'vat' => (float) $row['vat'],
        'margin' => (float) $row['margin'],
        'invoice' => (float) $row['invoice'],
        'count' => (int) $row['count'],
    ];
}, $rows);

// Totals
$totals = $db->fetch(
    "SELECT
        COALESCE(SUM(c.payment_amount), 0) AS sales,
        COALESCE(SUM(c.execution_cost), 0) AS execution_cost,
        COALESCE(SUM(c.vat), 0) AS vat,
        COALESCE(SUM(c.net_margin), 0) AS margin,
        COALESCE(SUM(c.invoice_amount), 0) AS invoice,
        COUNT(*) AS count
     FROM companies c
     $whereClause",
    $params
);

    echo json_encode([
        'success' => true,
        'type' => $type,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'data' => $data,
        'totals' => [
            'sales' => (float) $totals['sales'],
            'execution_cost' => (float) $totals['execution_cost'],
            'vat' => (float) $totals['vat'],
            'margin' => (float) $totals['margin'],
            'invoice' => (float) $totals['invoice'],
            'count' => (int) $totals['count'],
        ],
    ]);
} catch (Exception $e) {
    error_log('Stats sales error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

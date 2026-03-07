<?php
/**
 * Employee Performance Statistics API
 * GET /api/stats/employee.php
 *
 * Admin only. Returns per-employee sales, margin, contracts, call counts.
 *
 * Parameters:
 *   period_start - Start date (YYYY-MM-DD)
 *   period_end   - End date (YYYY-MM-DD)
 *   year         - Filter year (shorthand, default current year)
 *   month        - Filter month (optional)
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

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin only']);
    exit;
}

try {
    $db = Database::getInstance();

    // Period determination
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? null;
$periodStart = $_GET['period_start'] ?? null;
$periodEnd = $_GET['period_end'] ?? null;

if (!$periodStart) {
    $periodStart = $month
        ? sprintf('%s-%s-01', $year, str_pad($month, 2, '0', STR_PAD_LEFT))
        : "$year-01-01";
}
if (!$periodEnd) {
    $periodEnd = $month
        ? date('Y-m-t', strtotime($periodStart))
        : "$year-12-31";
}

// Get all active employees
$employees = $db->fetchAll(
    "SELECT id, name, username, position FROM users WHERE is_active = 1 AND role = 'EMPLOYEE' ORDER BY name ASC"
);

$data = [];
foreach ($employees as $emp) {
    // Sales & margin
    $sales = $db->fetch(
        "SELECT
            COALESCE(SUM(c.payment_amount), 0) AS sales,
            COALESCE(SUM(c.net_margin), 0) AS margin,
            COALESCE(SUM(c.execution_cost), 0) AS execution_cost,
            COALESCE(SUM(c.invoice_amount), 0) AS invoice,
            COUNT(*) AS company_count
         FROM companies c
         WHERE c.is_active = 1 AND c.user_id = ?
           AND c.register_date >= ? AND c.register_date <= ?",
        [$emp['id'], $periodStart, $periodEnd]
    );

    // Contract completions (status changed to '계약완료')
    $contracts = $db->fetch(
        "SELECT COUNT(*) AS cnt FROM status_histories
         WHERE user_id = ? AND new_status = '계약완료'
           AND created_at >= ? AND created_at <= ?",
        [$emp['id'], $periodStart, $periodEnd . ' 23:59:59']
    );

    // Call count (all status changes)
    $calls = $db->fetch(
        "SELECT COUNT(*) AS cnt FROM status_histories
         WHERE user_id = ?
           AND created_at >= ? AND created_at <= ?",
        [$emp['id'], $periodStart, $periodEnd . ' 23:59:59']
    );

    // Incentive rate
    $incentive = $db->fetch(
        "SELECT incentive_rate FROM employee_incentives WHERE user_id = ?",
        [$emp['id']]
    );
    $incentiveRate = $incentive ? (float) $incentive['incentive_rate'] : 0;

    // Sales target
    $target = $db->fetch(
        "SELECT target_amount FROM sales_targets WHERE user_id = ? AND year = ?" .
        ($month ? " AND month = ?" : ""),
        $month ? [$emp['id'], (int) $year, (int) $month] : [$emp['id'], (int) $year]
    );
    $targetAmount = $target ? (float) $target['target_amount'] : 0;

    $salesAmount = (float) $sales['sales'];
    $marginAmount = (float) $sales['margin'];

    $data[] = [
        'user_id' => (int) $emp['id'],
        'user_name' => $emp['name'],
        'position' => $emp['position'],
        'sales' => $salesAmount,
        'margin' => $marginAmount,
        'execution_cost' => (float) $sales['execution_cost'],
        'invoice' => (float) $sales['invoice'],
        'company_count' => (int) $sales['company_count'],
        'contract_count' => (int) ($contracts['cnt'] ?? 0),
        'call_count' => (int) ($calls['cnt'] ?? 0),
        'incentive_rate' => $incentiveRate,
        'incentive_amount' => round($marginAmount * $incentiveRate / 100),
        'target_amount' => $targetAmount,
        'achievement_rate' => $targetAmount > 0 ? round($salesAmount / $targetAmount * 100, 1) : 0,
    ];
}

// Sort by sales descending
usort($data, function ($a, $b) {
    return $b['sales'] <=> $a['sales'];
});

    echo json_encode([
        'success' => true,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'data' => $data,
    ]);
} catch (Exception $e) {
    error_log('Stats employee error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

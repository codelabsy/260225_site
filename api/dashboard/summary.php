<?php
/**
 * Dashboard Summary API
 * GET /api/dashboard/summary.php
 *
 * Returns aggregated sales/margin/invoice data for dashboard widgets.
 * Admin sees all data; employees see only their own.
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

    $today = date('Y-m-d');
    $year = date('Y');
    $month = date('m');

    // Build user filter
    $userFilter = '';
    $params = [];
    if (!$isAdmin) {
        $userFilter = 'AND c.user_id = ?';
        $params[] = $user['id'];
    }

    // Today's sales, invoice, margin
    $todayParams = array_merge([$today], $params);
    $todaySummary = $db->fetch(
        "SELECT
            COALESCE(SUM(c.payment_amount), 0) AS today_sales,
            COALESCE(SUM(c.invoice_amount), 0) AS today_invoice,
            COALESCE(SUM(c.net_margin), 0) AS today_margin,
            COALESCE(SUM(c.execution_cost), 0) AS today_execution_cost,
            COALESCE(SUM(c.vat), 0) AS today_vat,
            COUNT(*) AS today_count
         FROM companies c
         WHERE c.is_active = 1 AND c.register_date = ? $userFilter",
        $todayParams
    );

    // This month's sales, invoice, margin
    $monthParams = array_merge([$year, $month], $params);
    $monthSummary = $db->fetch(
        "SELECT
            COALESCE(SUM(c.payment_amount), 0) AS month_sales,
            COALESCE(SUM(c.invoice_amount), 0) AS month_invoice,
            COALESCE(SUM(c.net_margin), 0) AS month_margin,
            COALESCE(SUM(c.execution_cost), 0) AS month_execution_cost,
            COALESCE(SUM(c.vat), 0) AS month_vat,
            COUNT(*) AS month_count
         FROM companies c
         WHERE c.is_active = 1
           AND strftime('%Y', c.register_date) = ?
           AND strftime('%m', c.register_date) = ?
           $userFilter",
        $monthParams
    );

    // This year's totals
    $yearParams = array_merge([$year], $params);
    $yearSummary = $db->fetch(
        "SELECT
            COALESCE(SUM(c.payment_amount), 0) AS year_sales,
            COALESCE(SUM(c.invoice_amount), 0) AS year_invoice,
            COALESCE(SUM(c.net_margin), 0) AS year_margin,
            COALESCE(SUM(c.execution_cost), 0) AS year_execution_cost,
            COALESCE(SUM(c.vat), 0) AS year_vat,
            COUNT(*) AS year_count
         FROM companies c
         WHERE c.is_active = 1
           AND strftime('%Y', c.register_date) = ?
           $userFilter",
        $yearParams
    );

    // Today's new DB entries (shopping + place)
    $shoppingUserFilter = '';
    $placeUserFilter = '';
    $shoppingParams = [$today];
    $placeParams = [$today];
    if (!$isAdmin) {
        $shoppingUserFilter = 'AND s.user_id = ?';
        $placeUserFilter = 'AND p.user_id = ?';
        $shoppingParams[] = $user['id'];
        $placeParams[] = $user['id'];
    }

    $newShopping = $db->fetch(
        "SELECT COUNT(*) AS cnt FROM shopping_db s WHERE date(s.created_at) = ? $shoppingUserFilter",
        $shoppingParams
    );

    $newPlace = $db->fetch(
        "SELECT COUNT(*) AS cnt FROM place_db p WHERE date(p.created_at) = ? $placeUserFilter",
        $placeParams
    );

    $todayNewDb = ($newShopping['cnt'] ?? 0) + ($newPlace['cnt'] ?? 0);

    // Today's contracts (status = '계약완료' changed today)
    $contractParams = [$today];
    if (!$isAdmin) {
        $contractParams[] = $user['id'];
    }
    $todayContracts = $db->fetch(
        "SELECT COUNT(*) AS cnt FROM status_histories sh
         WHERE date(sh.created_at) = ? AND sh.new_status = '계약완료'"
        . (!$isAdmin ? ' AND sh.user_id = ?' : ''),
        $contractParams
    );

    // Employee incentive & target (for employee dashboard)
    $incentiveRate = 0;
    $targetAmount = 0;
    if (!$isAdmin) {
        $incentive = $db->fetch(
            "SELECT incentive_rate FROM employee_incentives WHERE user_id = ?",
            [$user['id']]
        );
        $incentiveRate = $incentive ? (float) $incentive['incentive_rate'] : 0;

        $target = $db->fetch(
            "SELECT target_amount FROM sales_targets WHERE user_id = ? AND year = ? AND month = ?",
            [$user['id'], (int) $year, (int) $month]
        );
        $targetAmount = $target ? (float) $target['target_amount'] : 0;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'today_sales' => (float) $todaySummary['today_sales'],
            'today_invoice' => (float) $todaySummary['today_invoice'],
            'today_margin' => (float) $todaySummary['today_margin'],
            'today_execution_cost' => (float) $todaySummary['today_execution_cost'],
            'today_vat' => (float) $todaySummary['today_vat'],
            'today_count' => (int) $todaySummary['today_count'],
            'month_sales' => (float) $monthSummary['month_sales'],
            'month_invoice' => (float) $monthSummary['month_invoice'],
            'month_margin' => (float) $monthSummary['month_margin'],
            'month_execution_cost' => (float) $monthSummary['month_execution_cost'],
            'month_vat' => (float) $monthSummary['month_vat'],
            'month_count' => (int) $monthSummary['month_count'],
            'year_sales' => (float) $yearSummary['year_sales'],
            'year_invoice' => (float) $yearSummary['year_invoice'],
            'year_margin' => (float) $yearSummary['year_margin'],
            'year_execution_cost' => (float) $yearSummary['year_execution_cost'],
            'year_vat' => (float) $yearSummary['year_vat'],
            'year_count' => (int) $yearSummary['year_count'],
            'today_new_db' => (int) $todayNewDb,
            'today_contracts' => (int) ($todayContracts['cnt'] ?? 0),
            'incentive_rate' => $incentiveRate,
            'target_amount' => $targetAmount,
        ],
    ]);
} catch (Exception $e) {
    error_log('Dashboard summary error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

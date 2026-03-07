<?php
/**
 * Dashboard Chart Data API
 * GET /api/dashboard/chart.php
 *
 * Parameters:
 *   type   - monthly_sales | employee_comparison | status_distribution
 *   year   - Filter year (default: current year)
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

    $type = $_GET['type'] ?? 'monthly_sales';
    $year = $_GET['year'] ?? date('Y');
    $year = (string) (int) $year; // sanitize

    $data = [];

    switch ($type) {

        case 'monthly_sales':
        // 12-month sales & margin trend
        $userFilter = '';
        $params = [$year];
        if (!$isAdmin) {
            $userFilter = 'AND c.user_id = ?';
            $params[] = $user['id'];
        }

        $rows = $db->fetchAll(
            "SELECT
                strftime('%m', c.register_date) AS month,
                COALESCE(SUM(c.payment_amount), 0) AS sales,
                COALESCE(SUM(c.net_margin), 0) AS margin,
                COALESCE(SUM(c.invoice_amount), 0) AS invoice,
                COALESCE(SUM(c.execution_cost), 0) AS execution_cost,
                COUNT(*) AS count
             FROM companies c
             WHERE c.is_active = 1
               AND strftime('%Y', c.register_date) = ?
               $userFilter
             GROUP BY strftime('%m', c.register_date)
             ORDER BY month ASC",
            $params
        );

        // Fill all 12 months
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad($m, 2, '0', STR_PAD_LEFT);
            $monthlyData[$key] = [
                'month' => $m,
                'label' => $m . '월',
                'sales' => 0,
                'margin' => 0,
                'invoice' => 0,
                'execution_cost' => 0,
                'count' => 0,
            ];
        }

        foreach ($rows as $row) {
            $key = $row['month'];
            $monthlyData[$key] = [
                'month' => (int) $row['month'],
                'label' => ((int) $row['month']) . '월',
                'sales' => (float) $row['sales'],
                'margin' => (float) $row['margin'],
                'invoice' => (float) $row['invoice'],
                'execution_cost' => (float) $row['execution_cost'],
                'count' => (int) $row['count'],
            ];
        }

        $data = array_values($monthlyData);
        break;

    case 'employee_comparison':
        // Admin only: employee comparison
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin only']);
            exit;
        }

        $rows = $db->fetchAll(
            "SELECT
                u.id AS user_id,
                u.name AS user_name,
                COALESCE(SUM(c.payment_amount), 0) AS sales,
                COALESCE(SUM(c.net_margin), 0) AS margin,
                COUNT(c.id) AS contract_count
             FROM users u
             LEFT JOIN companies c ON c.user_id = u.id
                AND c.is_active = 1
                AND strftime('%Y', c.register_date) = ?
             WHERE u.is_active = 1 AND u.role = 'EMPLOYEE'
             GROUP BY u.id
             ORDER BY sales DESC",
            [$year]
        );

        // Add call count (status changes)
        foreach ($rows as &$row) {
            $callCount = $db->fetch(
                "SELECT COUNT(*) AS cnt FROM status_histories
                 WHERE user_id = ? AND strftime('%Y', created_at) = ?",
                [$row['user_id'], $year]
            );
            $row['call_count'] = (int) ($callCount['cnt'] ?? 0);
            $row['sales'] = (float) $row['sales'];
            $row['margin'] = (float) $row['margin'];
            $row['contract_count'] = (int) $row['contract_count'];
        }
        unset($row);

        $data = $rows;
        break;

    case 'status_distribution':
        // Admin only: shopping + place DB status distribution
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin only']);
            exit;
        }

        $shoppingStatuses = $db->fetchAll(
            "SELECT status, COUNT(*) AS count FROM shopping_db GROUP BY status ORDER BY count DESC"
        );

        $placeStatuses = $db->fetchAll(
            "SELECT status, COUNT(*) AS count FROM place_db GROUP BY status ORDER BY count DESC"
        );

        $data = [
            'shopping' => array_map(function ($r) {
                return ['status' => $r['status'], 'count' => (int) $r['count']];
            }, $shoppingStatuses),
            'place' => array_map(function ($r) {
                return ['status' => $r['status'], 'count' => (int) $r['count']];
            }, $placeStatuses),
        ];
        break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid chart type']);
            exit;
    }

    echo json_encode([
        'success' => true,
        'type' => $type,
        'year' => $year,
        'data' => $data,
    ]);
} catch (Exception $e) {
    error_log('Dashboard chart error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

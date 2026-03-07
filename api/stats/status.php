<?php
/**
 * Status Distribution Statistics API
 * GET /api/stats/status.php
 *
 * Admin only. Returns status distribution for shopping_db and place_db.
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
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

    // Shopping DB status counts
    $shoppingStatuses = $db->fetchAll(
        "SELECT status, COUNT(*) AS count FROM shopping_db GROUP BY status ORDER BY count DESC"
    );
    $shoppingTotal = 0;
    $shoppingData = [];
    foreach ($shoppingStatuses as $row) {
        $shoppingData[] = ['status' => $row['status'], 'count' => (int) $row['count']];
        $shoppingTotal += (int) $row['count'];
    }

    // Place DB status counts
    $placeStatuses = $db->fetchAll(
        "SELECT status, COUNT(*) AS count FROM place_db GROUP BY status ORDER BY count DESC"
    );
    $placeTotal = 0;
    $placeData = [];
    foreach ($placeStatuses as $row) {
        $placeData[] = ['status' => $row['status'], 'count' => (int) $row['count']];
        $placeTotal += (int) $row['count'];
    }

    // Combined status counts
    $allStatuses = [];
    foreach ($shoppingData as $item) {
        $allStatuses[$item['status']] = ($allStatuses[$item['status']] ?? 0) + $item['count'];
    }
    foreach ($placeData as $item) {
        $allStatuses[$item['status']] = ($allStatuses[$item['status']] ?? 0) + $item['count'];
    }
    arsort($allStatuses);
    $combinedData = [];
    foreach ($allStatuses as $status => $count) {
        $combinedData[] = ['status' => $status, 'count' => $count];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'shopping' => [
                'items' => $shoppingData,
                'total' => $shoppingTotal,
            ],
            'place' => [
                'items' => $placeData,
                'total' => $placeTotal,
            ],
            'combined' => [
                'items' => $combinedData,
                'total' => $shoppingTotal + $placeTotal,
            ],
        ],
    ]);
} catch (Exception $e) {
    error_log('Stats status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

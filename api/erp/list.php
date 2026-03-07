<?php
/**
 * ERP List API endpoint.
 * GET: Returns filtered company list with summary.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../models/Company.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user = Auth::user();
$isAdmin = Auth::isAdmin();

try {

// Build filters
$filters = [];

if (!empty($_GET['period_start'])) {
    $filters['date_from'] = $_GET['period_start'];
}
if (!empty($_GET['period_end'])) {
    $filters['date_to'] = $_GET['period_end'];
}
if (!empty($_GET['payment_type'])) {
    $filters['payment_type'] = $_GET['payment_type'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Sorting
$allowedSorts = [
    'register_date' => 'c.register_date',
    'company_name' => 'c.company_name',
    'payment_amount' => 'c.payment_amount',
    'execution_cost' => 'c.execution_cost',
    'net_margin' => 'c.net_margin',
    'created_at' => 'c.created_at',
];
$sortKey = $_GET['sort'] ?? 'register_date';
$filters['sort'] = $allowedSorts[$sortKey] ?? 'c.register_date';
$filters['order'] = ($_GET['order'] ?? 'DESC');

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE);
if (!in_array($pageSize, PAGE_SIZES)) {
    $pageSize = DEFAULT_PAGE_SIZE;
}

// User filter: admin can filter by user_id, employee sees only own
if ($isAdmin && !empty($_GET['user_id'])) {
    $filters['user_id'] = (int)$_GET['user_id'];
} elseif (!$isAdmin) {
    $filters['user_id'] = (int)$user['id'];
}

// Get summary (without pagination)
$summary = Company::getSummary($filters);

// Add pagination
$filters['limit'] = $pageSize;
$filters['offset'] = ($page - 1) * $pageSize;

// Get items
$items = Company::all($filters);

$total = (int)($summary['total_count'] ?? 0);

echo json_encode([
    'success' => true,
    'data' => [
        'items' => $items,
        'total' => $total,
        'summary' => [
            'total_payment' => (float)($summary['total_payment'] ?? 0),
            'total_invoice' => (float)($summary['total_invoice'] ?? 0),
            'total_execution_cost' => (float)($summary['total_execution_cost'] ?? 0),
            'total_vat' => (float)($summary['total_vat'] ?? 0),
            'total_net_margin' => (float)($summary['total_net_margin'] ?? 0),
            'total_count' => $total,
        ],
        'page' => $page,
        'page_size' => $pageSize,
    ],
], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

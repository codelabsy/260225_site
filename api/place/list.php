<?php
/**
 * Place DB List API endpoint.
 * GET: Returns filtered place list with status counts.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../models/PlaceDB.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user = Auth::user();
$isAdmin = Auth::isAdmin();

// Build filters
$filters = [];

if (!empty($_GET['status']) && in_array($_GET['status'], PLACE_STATUSES)) {
    $filters['status'] = $_GET['status'];
}

if (!empty($_GET['period_start'])) {
    $filters['period']['from'] = $_GET['period_start'];
}
if (!empty($_GET['period_end'])) {
    $filters['period']['to'] = $_GET['period_end'];
}

if (!empty($_GET['region'])) {
    $filters['region'] = $_GET['region'];
}

if (!empty($_GET['source'])) {
    $filters['source'] = $_GET['source'];
}

if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// User filter: admin can filter by user_id, employee sees only own
if ($isAdmin && isset($_GET['unassigned']) && $_GET['unassigned'] === '1') {
    $filters['unassigned'] = true;
} elseif ($isAdmin && !empty($_GET['user_id'])) {
    $filters['user_id'] = (int) $_GET['user_id'];
} elseif (!$isAdmin) {
    $filters['user_id'] = (int) $user['id'];
}

// Sorting
$allowedSorts = [
    'register_date' => 'p.register_date',
    'company_name'  => 'p.company_name',
    'phone'         => 'p.phone',
    'status'        => 'p.status',
    'region'        => 'p.region',
    'created_at'    => 'p.created_at',
    'updated_at'    => 'p.updated_at',
];
$sortKey = $_GET['sort'] ?? 'register_date';
$filters['sort'] = $allowedSorts[$sortKey] ?? 'p.register_date';
$filters['order'] = $_GET['order'] ?? 'DESC';

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = (int) ($_GET['page_size'] ?? DEFAULT_PAGE_SIZE);
if (!in_array($pageSize, PAGE_SIZES)) {
    $pageSize = DEFAULT_PAGE_SIZE;
}

// Get total count (without pagination) for status counts
$countFilters = $filters;
unset($countFilters['status']); // Remove status to get all status counts

try {

$db = Database::getInstance();

// Build base WHERE for status counts
$baseWhere = [];
$baseParams = [];

if (isset($filters['unassigned']) && $filters['unassigned']) {
    $baseWhere[] = 'p.user_id IS NULL';
} elseif (!$isAdmin) {
    $baseWhere[] = 'p.user_id = ?';
    $baseParams[] = (int) $user['id'];
} elseif (!empty($filters['user_id'])) {
    $baseWhere[] = 'p.user_id = ?';
    $baseParams[] = (int) $filters['user_id'];
}
if (!empty($filters['region'])) {
    $baseWhere[] = 'p.region = ?';
    $baseParams[] = $filters['region'];
}
if (!empty($filters['source'])) {
    $baseWhere[] = 'p.source = ?';
    $baseParams[] = $filters['source'];
}
if (!empty($filters['search'])) {
    $search = '%' . $filters['search'] . '%';
    $baseWhere[] = '(p.company_name LIKE ? OR p.phone LIKE ? OR p.region LIKE ?)';
    $baseParams[] = $search;
    $baseParams[] = $search;
    $baseParams[] = $search;
}
if (!empty($filters['period']['from'])) {
    $baseWhere[] = 'p.register_date >= ?';
    $baseParams[] = $filters['period']['from'];
}
if (!empty($filters['period']['to'])) {
    $baseWhere[] = 'p.register_date <= ?';
    $baseParams[] = $filters['period']['to'];
}

$baseWhereClause = !empty($baseWhere) ? 'WHERE ' . implode(' AND ', $baseWhere) : '';

// Status counts
$statusCountsQuery = $db->fetchAll(
    "SELECT status, COUNT(*) as cnt FROM place_db p $baseWhereClause GROUP BY status",
    $baseParams
);
$statusCounts = ['total' => 0];
foreach (PLACE_STATUSES as $s) {
    $statusCounts[$s] = 0;
}
foreach ($statusCountsQuery as $row) {
    $statusCounts[$row['status']] = (int) $row['cnt'];
    $statusCounts['total'] += (int) $row['cnt'];
}

// Get total with current status filter
$totalWhere = $baseWhere;
$totalParams = $baseParams;
if (!empty($filters['status'])) {
    $totalWhere[] = 'p.status = ?';
    $totalParams[] = $filters['status'];
}
$totalWhereClause = !empty($totalWhere) ? 'WHERE ' . implode(' AND ', $totalWhere) : '';
$totalRow = $db->fetch("SELECT COUNT(*) as cnt FROM place_db p $totalWhereClause", $totalParams);
$total = (int) ($totalRow['cnt'] ?? 0);

// Add pagination
$filters['limit'] = $pageSize;
$filters['offset'] = ($page - 1) * $pageSize;

// Get items
$items = PlaceDB::all($filters);

echo json_encode([
    'success' => true,
    'data' => [
        'items'         => $items,
        'total'         => $total,
        'page'          => $page,
        'page_size'     => $pageSize,
        'status_counts' => $statusCounts,
    ],
], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

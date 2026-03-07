<?php
/**
 * Shopping DB list API.
 * GET: filters (status, period_start, period_end, user_id, search, sort, order, page, page_size)
 * Admin sees all; employee sees only assigned records.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../models/ShoppingDB.php';
require_once __DIR__ . '/../../models/User.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user = Auth::user();
$isAdmin = Auth::isAdmin();

// Parse filters
$status      = trim($_GET['status'] ?? '');
$periodStart = trim($_GET['period_start'] ?? '');
$periodEnd   = trim($_GET['period_end'] ?? '');
$userId      = trim($_GET['user_id'] ?? '');
$search      = trim($_GET['search'] ?? '');
$sort        = trim($_GET['sort'] ?? 's.created_at');
$order       = trim($_GET['order'] ?? 'DESC');
$page        = max(1, (int)($_GET['page'] ?? 1));
$pageSize    = (int)($_GET['page_size'] ?? DEFAULT_PAGE_SIZE);

if (!in_array($pageSize, PAGE_SIZES)) {
    $pageSize = DEFAULT_PAGE_SIZE;
}

$filters = [];

if ($status !== '' && in_array($status, SHOPPING_STATUSES)) {
    $filters['status'] = $status;
}

if ($periodStart !== '' || $periodEnd !== '') {
    $filters['period'] = [];
    if ($periodStart !== '') {
        $filters['period']['from'] = $periodStart;
    }
    if ($periodEnd !== '') {
        $filters['period']['to'] = $periodEnd;
    }
}

if ($search !== '') {
    $filters['search'] = $search;
}

// Employee restriction: only see own assigned records
if (!$isAdmin) {
    $filters['user_id'] = $user['id'];
} elseif ($userId !== '') {
    if ($userId === 'unassigned') {
        $filters['unassigned'] = true;
    } else {
        $filters['user_id'] = (int)$userId;
    }
}

$filters['sort'] = $sort;
$filters['order'] = $order;

try {

// Get total count using COUNT(*) query instead of loading all data
$db = Database::getInstance();
$countWhere = [];
$countParams = [];

if (!$isAdmin) {
    $countWhere[] = 's.user_id = ?';
    $countParams[] = $user['id'];
} elseif ($userId !== '' && $userId !== 'unassigned') {
    $countWhere[] = 's.user_id = ?';
    $countParams[] = (int)$userId;
} elseif ($userId === 'unassigned') {
    $countWhere[] = 's.user_id IS NULL';
}

if ($status !== '' && in_array($status, SHOPPING_STATUSES)) {
    $countWhere[] = 's.status = ?';
    $countParams[] = $status;
}

if ($periodStart !== '') {
    $countWhere[] = 's.created_at >= ?';
    $countParams[] = $periodStart;
}
if ($periodEnd !== '') {
    $countWhere[] = 's.created_at <= ?';
    $countParams[] = $periodEnd . ' 23:59:59';
}
if ($search !== '') {
    $searchParam = '%' . $search . '%';
    $countWhere[] = '(s.company_name LIKE ? OR s.representative LIKE ? OR s.phone LIKE ? OR s.store_name LIKE ?)';
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
}

$totalWhereClause = !empty($countWhere) ? 'WHERE ' . implode(' AND ', $countWhere) : '';
$totalRow = $db->fetch("SELECT COUNT(*) as cnt FROM shopping_db s $totalWhereClause", $countParams);
$total = (int)($totalRow['cnt'] ?? 0);

// Apply pagination
$filters['limit'] = $pageSize;
$filters['offset'] = ($page - 1) * $pageSize;
$items = ShoppingDB::all($filters);

// Get status counts (without status filter to show all status counts)
$statusCountWhere = [];
$statusCountParams = [];

if (!$isAdmin) {
    $statusCountWhere[] = 's.user_id = ?';
    $statusCountParams[] = $user['id'];
} elseif ($userId !== '' && $userId !== 'unassigned') {
    $statusCountWhere[] = 's.user_id = ?';
    $statusCountParams[] = (int)$userId;
} elseif ($userId === 'unassigned') {
    $statusCountWhere[] = 's.user_id IS NULL';
}

if ($periodStart !== '') {
    $statusCountWhere[] = 's.created_at >= ?';
    $statusCountParams[] = $periodStart;
}
if ($periodEnd !== '') {
    $statusCountWhere[] = 's.created_at <= ?';
    $statusCountParams[] = $periodEnd . ' 23:59:59';
}
if ($search !== '') {
    $searchParam = '%' . $search . '%';
    $statusCountWhere[] = '(s.company_name LIKE ? OR s.representative LIKE ? OR s.phone LIKE ? OR s.store_name LIKE ?)';
    $statusCountParams[] = $searchParam;
    $statusCountParams[] = $searchParam;
    $statusCountParams[] = $searchParam;
    $statusCountParams[] = $searchParam;
}

$countWhereClause = '';
if (!empty($statusCountWhere)) {
    $countWhereClause = 'WHERE ' . implode(' AND ', $statusCountWhere);
}

$statusCountsRaw = $db->fetchAll(
    "SELECT status, COUNT(*) as cnt FROM shopping_db s $countWhereClause GROUP BY status",
    $statusCountParams
);

$statusCounts = [];
foreach (SHOPPING_STATUSES as $s) {
    $statusCounts[$s] = 0;
}
$totalAll = 0;
foreach ($statusCountsRaw as $row) {
    $statusCounts[$row['status']] = (int)$row['cnt'];
    $totalAll += (int)$row['cnt'];
}
$statusCounts['전체'] = $totalAll;

echo json_encode([
    'success' => true,
    'data' => [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'page_size' => $pageSize,
        'status_counts' => $statusCounts,
    ],
], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

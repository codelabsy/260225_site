<?php
/**
 * Excel (CSV) Export API
 * GET /api/export/excel.php
 *
 * Admin only. Exports filtered data as CSV download.
 *
 * Parameters:
 *   type         - companies | shopping | place | stats
 *   period_start - Start date filter
 *   period_end   - End date filter
 *   user_id      - Filter by user
 *   status       - Filter by status (shopping/place)
 *   search       - Search keyword
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/ExcelHandler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../models/Company.php';
require_once __DIR__ . '/../../models/ShoppingDB.php';
require_once __DIR__ . '/../../models/PlaceDB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!Auth::check()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

if (!Auth::isAdmin()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '관리자 권한이 필요합니다.']);
    exit;
}

$type = $_GET['type'] ?? '';
$periodStart = $_GET['period_start'] ?? null;
$periodEnd = $_GET['period_end'] ?? null;
$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$status = $_GET['status'] ?? null;
$search = $_GET['search'] ?? null;

$db = Database::getInstance();

try {

switch ($type) {

    case 'companies':
        $filters = [];
        if ($userId) $filters['user_id'] = $userId;
        if ($search) $filters['search'] = $search;
        if ($periodStart) $filters['date_from'] = $periodStart;
        if ($periodEnd) $filters['date_to'] = $periodEnd;

        $rows = Company::all($filters);

        $headers = ['등록일', '담당자', '상품명', '업체명', '결제금액', '계산서발행금액', '실행비', '부가세', '순마진', '직급', '사업자명', '대표자', '연락처', '결제방식', '사업자번호'];
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                '등록일' => $row['register_date'] ?? '',
                '담당자' => $row['user_name'] ?? '',
                '상품명' => $row['product_name'] ?? '',
                '업체명' => $row['company_name'] ?? '',
                '결제금액' => $row['payment_amount'] ?? 0,
                '계산서발행금액' => $row['invoice_amount'] ?? 0,
                '실행비' => $row['execution_cost'] ?? 0,
                '부가세' => $row['vat'] ?? 0,
                '순마진' => $row['net_margin'] ?? 0,
                '직급' => $row['registrant_position'] ?? '',
                '사업자명' => $row['business_name'] ?? '',
                '대표자' => $row['ceo_name'] ?? '',
                '연락처' => $row['phone'] ?? '',
                '결제방식' => $row['payment_type'] ?? '',
                '사업자번호' => $row['business_number'] ?? '',
            ];
        }

        Logger::log(ACTION_EXCEL_DOWNLOAD, TARGET_COMPANY, null, 'ERP 매출 데이터 다운로드 (' . count($data) . '건)');
        ExcelHandler::exportCSV($headers, $data, 'ERP매출_' . date('Ymd_His') . '.csv');
        break;

    case 'shopping':
        $filters = [];
        if ($userId) $filters['user_id'] = $userId;
        if ($status) $filters['status'] = $status;
        if ($search) $filters['search'] = $search;
        if ($periodStart || $periodEnd) {
            $filters['period'] = [];
            if ($periodStart) $filters['period']['from'] = $periodStart;
            if ($periodEnd) $filters['period']['to'] = $periodEnd;
        }

        $rows = ShoppingDB::all($filters);

        $headers = ['ID', '배정직원', '키워드', '상호명', '대표자', '연락처', '핸드폰번호', '이메일', '사업자등록번호', '스토어명', '등급', '상태', '등록일', '수정일'];
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'ID' => $row['id'],
                '배정직원' => $row['user_name'] ?? '',
                '키워드' => $row['keyword'] ?? '',
                '상호명' => $row['company_name'] ?? '',
                '대표자' => $row['representative'] ?? '',
                '연락처' => $row['phone'] ?? '',
                '핸드폰번호' => $row['mobile_phone'] ?? '',
                '이메일' => $row['email'] ?? '',
                '사업자등록번호' => $row['business_number'] ?? '',
                '스토어명' => $row['store_name'] ?? '',
                '등급' => $row['grade'] ?? '',
                '상태' => $row['status'] ?? '',
                '등록일' => $row['created_at'] ?? '',
                '수정일' => $row['updated_at'] ?? '',
            ];
        }

        Logger::log(ACTION_EXCEL_DOWNLOAD, TARGET_SHOPPING, null, '쇼핑DB 다운로드 (' . count($data) . '건)');
        ExcelHandler::exportCSV($headers, $data, '쇼핑DB_' . date('Ymd_His') . '.csv');
        break;

    case 'place':
        $filters = [];
        if ($userId) $filters['user_id'] = $userId;
        if ($status) $filters['status'] = $status;
        if ($search) $filters['search'] = $search;
        if ($periodStart || $periodEnd) {
            $filters['period'] = [];
            if ($periodStart) $filters['period']['from'] = $periodStart;
            if ($periodEnd) $filters['period']['to'] = $periodEnd;
        }

        $rows = PlaceDB::all($filters);

        $headers = ['ID', '담당자', '업체명', '전화번호', '지역', '등록일', '출처', '상태', '초기메모'];
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'ID' => $row['id'],
                '담당자' => $row['user_name'] ?? '',
                '업체명' => $row['company_name'] ?? '',
                '전화번호' => $row['phone'] ?? '',
                '지역' => $row['region'] ?? '',
                '등록일' => $row['register_date'] ?? '',
                '출처' => $row['source'] ?? '',
                '상태' => $row['status'] ?? '',
                '초기메모' => $row['initial_memo'] ?? '',
            ];
        }

        Logger::log(ACTION_EXCEL_DOWNLOAD, TARGET_PLACE, null, '플레이스DB 다운로드 (' . count($data) . '건)');
        ExcelHandler::exportCSV($headers, $data, '플레이스DB_' . date('Ymd_His') . '.csv');
        break;

    case 'stats':
        // Export sales statistics
        $statsType = $_GET['stats_type'] ?? 'monthly';
        $year = $_GET['year'] ?? date('Y');

        $where = ['c.is_active = 1'];
        $params = [];
        if ($userId) {
            $where[] = 'c.user_id = ?';
            $params[] = $userId;
        }
        if ($periodStart) {
            $where[] = 'c.register_date >= ?';
            $params[] = $periodStart;
        } else {
            $where[] = "strftime('%Y', c.register_date) = ?";
            $params[] = (string) $year;
        }
        if ($periodEnd) {
            $where[] = 'c.register_date <= ?';
            $params[] = $periodEnd;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $groupExpr = $statsType === 'daily'
            ? "c.register_date"
            : "strftime('%Y-%m', c.register_date)";

        $rows = $db->fetchAll(
            "SELECT
                $groupExpr AS period,
                COALESCE(SUM(c.payment_amount), 0) AS sales,
                COALESCE(SUM(c.execution_cost), 0) AS execution_cost,
                COALESCE(SUM(c.vat), 0) AS vat,
                COALESCE(SUM(c.net_margin), 0) AS margin,
                COALESCE(SUM(c.invoice_amount), 0) AS invoice,
                COUNT(*) AS count
             FROM companies c
             $whereClause
             GROUP BY $groupExpr
             ORDER BY period ASC",
            $params
        );

        $headers = ['기간', '매출액', '실행비', '부가세', '순마진', '계산서발행', '건수'];
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                '기간' => $row['period'],
                '매출액' => $row['sales'],
                '실행비' => $row['execution_cost'],
                '부가세' => $row['vat'],
                '순마진' => $row['margin'],
                '계산서발행' => $row['invoice'],
                '건수' => $row['count'],
            ];
        }

        Logger::log(ACTION_EXCEL_DOWNLOAD, null, null, '통계 데이터 다운로드 (' . count($data) . '건)');
        ExcelHandler::exportCSV($headers, $data, '통계_' . date('Ymd_His') . '.csv');
        break;

    default:
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid export type. Use: companies, shopping, place, stats']);
        exit;
}

} catch (Exception $e) {
    error_log('Excel export error: ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}

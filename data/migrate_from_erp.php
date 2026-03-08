<?php
/**
 * Migration script: Old ERP (MySQL) → New CRM (SQLite)
 * CLI only. Run: php data/migrate_from_erp.php
 *
 * Source: MySQL bami database on 1.234.5.197
 * Target: SQLite data/crm.sqlite
 *
 * Phases:
 *   1. Users (bs_users → users + employee_incentives)
 *   2. Companies (bs_agentManage → companies + company_details)
 *   3. Place DB (bs_customer_info → place_db)
 *   4. Memos (bs_agent_memo + inline memos + bs_customer_memo → memos)
 *   5. Attendance (bs_attendance → attendance)
 *   6. Login logs (bs_connect → login_logs)
 *   7. Verification
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only.';
    exit;
}

// ============================================
// Configuration
// ============================================

$mysqlHost = '1.234.5.197';
$mysqlUser = 'root';
$mysqlPass = '@wjstmd158899';
$mysqlDb   = 'bami';

$sqlitePath = __DIR__ . '/crm.sqlite';

// Temporary password for all migrated users (bcrypt)
$tempPassword = password_hash('changeme123!', PASSWORD_BCRYPT);

// ============================================
// Lookup Maps (from old system reference tables)
// ============================================

$paymentTypeMap = [
    1  => '현금입금',
    3  => 'BC카드',
    4  => '국민카드',
    5  => '현대카드',
    6  => '삼성카드',
    7  => '신한카드',
    8  => '농협카드',
    9  => '하나카드',
    10 => '롯데카드',
    15 => '기업카드',
    16 => '우리카드',
    17 => '우체국카드',
];

$bankMap = [
    1  => '국민은행',
    2  => '신한은행',
    10 => '하나은행',
    11 => '농협은행',
];

$levelMap = [
    1  => '사원',
    33 => '대리',
    77 => '경리/회계',
    99 => '관리자',
];

$customerStateMap = [
    1 => '일반',
    2 => '부재',
    3 => '재통',
    4 => '가망',
    5 => '계약완료',
];

// ============================================
// Connect to databases
// ============================================

echo "=== ERP → CRM Migration ===\n\n";

// MySQL
try {
    $mysql = new PDO(
        "mysql:host=$mysqlHost;dbname=$mysqlDb;charset=utf8mb4",
        $mysqlUser,
        $mysqlPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "[OK] MySQL connected\n";
} catch (Exception $e) {
    die("[FAIL] MySQL connection failed: " . $e->getMessage() . "\n");
}

// SQLite
try {
    $sqlite = new PDO("sqlite:$sqlitePath");
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $sqlite->exec('PRAGMA journal_mode = WAL');
    $sqlite->exec('PRAGMA foreign_keys = OFF'); // Temporarily off for migration
    echo "[OK] SQLite connected\n\n";
} catch (Exception $e) {
    die("[FAIL] SQLite connection failed: " . $e->getMessage() . "\n");
}

// ============================================
// Clear existing data (fresh migration)
// ============================================

echo "Clearing existing data...\n";
$tablesToClear = [
    'login_logs', 'attendance', 'memos', 'status_histories',
    'company_details', 'companies', 'place_db', 'db_assignments',
    'upload_histories', 'activity_logs', 'employee_incentives',
    'sales_targets', 'users',
];
foreach ($tablesToClear as $table) {
    $sqlite->exec("DELETE FROM $table");
}
$sqlite->exec("DELETE FROM sqlite_sequence");
echo "[OK] Cleared all tables\n\n";

// User ID mapping: old_id → new_id
$userIdMap = [];
// Orphan created_id fallback
$fallbackUserId = null;

// ============================================
// Phase 1: Users
// ============================================

echo "--- Phase 1: Users ---\n";

$users = $mysql->query("SELECT * FROM bs_users ORDER BY id")->fetchAll();
echo "Source: " . count($users) . " users\n";

$stmtUser = $sqlite->prepare("
    INSERT INTO users (username, password, name, role, position, phone, mobile_phone, email,
        user_type, base_salary, memo, delete_auth, modify_auth, move_auth,
        agent_id, team_id, division, msn, include_check, login_count, last_login,
        old_system_id, is_active, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmtIncentive = $sqlite->prepare("
    INSERT INTO employee_incentives (user_id, incentive_rate) VALUES (?, ?)
");

$sqlite->beginTransaction();

$userCount = 0;
foreach ($users as $u) {
    $oldId = (int)$u['id'];

    // Determine role
    $role = ($u['level'] == 99 && $u['utype'] == 99) ? 'ADMIN' : 'EMPLOYEE';

    // Determine position from level
    $position = $levelMap[(int)$u['level']] ?? '사원';

    // Determine is_active: u_state=1 means active in old system
    // But most active users have u_state=0, so set all as active
    $isActive = 1;

    // Clean memo (remove HTML line breaks)
    $memo = $u['memo'];
    if ($memo !== null) {
        $memo = str_replace(['<br />', '<br/>', '<br>'], "\n", $memo);
        $memo = trim($memo);
    }

    // Handle phone (hp field)
    $phone = trim($u['tel'] ?? '');
    $mobilePhone = trim($u['hp'] ?? '');

    // Ensure unique username
    $username = $u['login_id'];

    $stmtUser->execute([
        $username,
        $tempPassword,
        $u['name'],
        $role,
        $position,
        $phone ?: null,
        $mobilePhone ?: null,
        ($u['email'] && $u['email'] !== 'NULL') ? $u['email'] : null,
        (string)$u['utype'],
        (float)($u['normal_price'] ?? 0),
        $memo ?: null,
        (int)($u['deleteAuth'] ?? 0),
        (int)($u['modifyAuth'] ?? 0),
        (int)($u['moveAuth'] ?? 0),
        $u['agent_id'] !== null ? (int)$u['agent_id'] : null,
        $u['team_id'] !== null ? (int)$u['team_id'] : null,
        $u['division'] ?? null,
        $u['msn'] ?? null,
        (int)($u['include_check'] ?? 0),
        (int)($u['login_count'] ?? 0),
        ($u['last_login'] && $u['last_login'] !== '0000-00-00 00:00:00') ? $u['last_login'] : null,
        $oldId,
        $isActive,
        $u['created_at'] ?? date('Y-m-d H:i:s'),
        $u['updated_at'] ?? $u['created_at'] ?? date('Y-m-d H:i:s'),
    ]);

    $newId = (int)$sqlite->lastInsertId();
    $userIdMap[$oldId] = $newId;

    // Track admin user (전승호) as fallback for orphans
    if ($username === 'admin') {
        $fallbackUserId = $newId;
    }

    // Create incentive record based on agent_id (actually stores incentive rate %)
    $incentiveRate = (float)($u['agent_id'] ?? 0);
    if ($incentiveRate > 0) {
        $stmtIncentive->execute([$newId, $incentiveRate]);
    }

    $userCount++;
}

$sqlite->commit();
echo "[OK] Migrated $userCount users\n";

// If no admin found, use first user
if ($fallbackUserId === null) {
    $fallbackUserId = reset($userIdMap) ?: 1;
}

echo "Fallback user ID for orphans: $fallbackUserId\n\n";

// Helper to resolve old user ID → new user ID
function resolveUserId(int $oldCreatedId, array &$userIdMap, int $fallbackUserId): int
{
    if (isset($userIdMap[$oldCreatedId])) {
        return $userIdMap[$oldCreatedId];
    }
    return $fallbackUserId;
}

// ============================================
// Phase 2: Companies (bs_agentManage)
// ============================================

echo "--- Phase 2: Companies (bs_agentManage) ---\n";

$agents = $mysql->query("SELECT * FROM bs_agentManage ORDER BY id")->fetchAll();
echo "Source: " . count($agents) . " records\n";

$stmtCompany = $sqlite->prepare("
    INSERT INTO companies (user_id, register_date, product_name, company_name,
        payment_amount, invoice_amount, execution_cost, vat, net_margin,
        registrant_position, old_system_id, is_active, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
");

$stmtDetail = $sqlite->prepare("
    INSERT INTO company_details (company_id, contract_term, business_name, ceo_name, phone,
        payment_type, business_number, work_keywords, work_content, email, naver_account,
        outsourcing_cost, keyword_cost, pg_name, tax_type, bank,
        card_number, card_owner, card_owner_birth, card_validity, card_installment,
        making_type, making_bigo, vpn_id, blog_id, blog_pass,
        naver_ad_id, naver_ad_pass, daum_ad_id, daum_ad_pass,
        target_url, making_url, created_level, updated_by, updated_level,
        old_system_id, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$sqlite->beginTransaction();

$companyCount = 0;
$companyIdMap = []; // old bs_agentManage.id → new companies.id

foreach ($agents as $a) {
    $oldId = (int)$a['id'];

    // Resolve user
    $createdId = (int)($a['created_id'] ?? 0);
    $userId = resolveUserId($createdId, $userIdMap, $fallbackUserId);

    // Product name (goodsName) - resolve from bs_service code if numeric
    $productName = trim($a['goodsName'] ?? '') ?: '미지정';

    // Company name
    $companyName = trim($a['agent_nm'] ?? '') ?: '미등록';

    // Amounts
    $paymentAmt = (float)($a['payment_Amt'] ?? 0);
    $outsourcingAmt = (float)($a['outSourcing_Amt'] ?? 0);
    $keywordAmt = (float)($a['keyword_Amt'] ?? 0);
    $executionCost = $outsourcingAmt; // main execution cost
    $vat = round($paymentAmt / 11, 0);
    $netMargin = $paymentAmt - $executionCost - $vat;

    // Register date
    $registerDate = $a['contract_date'] ?? $a['created_at'] ?? date('Y-m-d');
    if (strlen($registerDate) > 10) {
        $registerDate = substr($registerDate, 0, 10);
    }
    if ($registerDate === '0000-00-00') {
        $registerDate = substr($a['created_at'] ?? date('Y-m-d H:i:s'), 0, 10);
    }

    // Resolve level to position
    $createdLevel = $levelMap[(int)($a['created_level'] ?? 0)] ?? '사원';

    // Resolve payment_type from lookup
    $paymentTypeId = (int)($a['payment_type'] ?? 0);
    $paymentType = $paymentTypeMap[$paymentTypeId] ?? '';

    // Resolve bank from lookup
    $bankId = (int)($a['bank'] ?? 0);
    $bank = $bankMap[$bankId] ?? '';

    // Updated by
    $updatedId = (int)($a['updated_id'] ?? 0);
    $updatedBy = $updatedId > 0 ? resolveUserId($updatedId, $userIdMap, $fallbackUserId) : null;
    $updatedLevel = $levelMap[(int)($a['updated_level'] ?? 0)] ?? null;

    // Timestamps
    $createdAt = $a['created_at'] ?? date('Y-m-d H:i:s');
    $updatedAt = $a['updated_at'] ?? $createdAt;
    if ($createdAt === '0000-00-00 00:00:00') $createdAt = date('Y-m-d H:i:s');
    if ($updatedAt === '0000-00-00 00:00:00') $updatedAt = $createdAt;

    $stmtCompany->execute([
        $userId,
        $registerDate,
        $productName,
        $companyName,
        $paymentAmt,
        0, // invoice_amount (no equivalent in old system)
        $executionCost,
        $vat,
        $netMargin,
        $createdLevel,
        $oldId,
        $createdAt,
        $updatedAt,
    ]);

    $newCompanyId = (int)$sqlite->lastInsertId();
    $companyIdMap[$oldId] = $newCompanyId;

    $stmtDetail->execute([
        $newCompanyId,
        $a['contract_term'] ?? null,
        $a['agent_nm'] ?? null,
        $a['agent_ceo'] ?? null,
        $a['agent_phone'] ?? null,
        $paymentType,
        $a['agent_number'] ?? null,
        $a['keyword'] ?? null,
        $a['content'] ?? null,
        $a['email'] ?? null,
        null, // naver_account
        $outsourcingAmt,
        $keywordAmt,
        $a['pg_name'] ?? null,
        $a['tax_type'] ?? null,
        $bank,
        $a['card_number'] ?? null,
        $a['card_owner'] ?? null,
        $a['card_owner_birth'] ?? null,
        $a['card_validity'] ?? null,
        $a['card_installment'] ?? null,
        $a['making_type'] ?? null,
        $a['making_bigo'] ?? null,
        $a['vpn_id'] ?? null,
        $a['blog_id'] ?? null,
        $a['blog_pass'] ?? null,
        $a['naver_add_id'] ?? null,
        $a['naver_add_pass'] ?? null,
        $a['daum_add_id'] ?? null,
        $a['daum_add_pass'] ?? null,
        $a['target_url'] ?? null,
        $a['making_url'] ?? null,
        $createdLevel,
        $updatedBy,
        $updatedLevel,
        $oldId,
        $createdAt,
        $updatedAt,
    ]);

    $companyCount++;

    if ($companyCount % 500 === 0) {
        echo "  ... $companyCount records\n";
    }
}

$sqlite->commit();
echo "[OK] Migrated $companyCount companies\n\n";

// ============================================
// Phase 3: Place DB (bs_customer_info)
// ============================================

echo "--- Phase 3: Place DB (bs_customer_info) ---\n";

$customers = $mysql->query("SELECT * FROM bs_customer_info ORDER BY id")->fetchAll();
echo "Source: " . count($customers) . " records\n";

$stmtPlace = $sqlite->prepare("
    INSERT INTO place_db (user_id, company_name, phone, region, register_date, source, status,
        initial_memo, request_content, contract_date, payment_amount, old_system_id,
        created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$sqlite->beginTransaction();

$placeCount = 0;
$placeIdMap = []; // old bs_customer_info.id → new place_db.id

foreach ($customers as $c) {
    $oldId = (int)$c['id'];

    // Resolve user
    $createdId = (int)($c['created_id'] ?? 0);
    $userId = $createdId > 0 ? resolveUserId($createdId, $userIdMap, $fallbackUserId) : null;

    // Resolve status from customer_state
    $stateId = (int)($c['customer_state'] ?? 1);
    $status = $customerStateMap[$stateId] ?? '일반';

    // Company name
    $companyName = trim($c['company_name'] ?? '') ?: '미등록';

    // Phone
    $phone = trim($c['contact_phone'] ?? '') ?: '-';

    // Register date
    $registerDate = $c['created_at'] ?? date('Y-m-d');
    if (strlen($registerDate) > 10) {
        $registerDate = substr($registerDate, 0, 10);
    }
    if ($registerDate === '0000-00-00') {
        $registerDate = date('Y-m-d');
    }

    // Contract date
    $contractDate = $c['contract_date'] ?? null;
    if ($contractDate === '0000-00-00') $contractDate = null;

    // Timestamps
    $createdAt = $c['created_at'] ?? date('Y-m-d H:i:s');
    $updatedAt = $c['updated_at'] ?? $createdAt;
    if ($createdAt === '0000-00-00 00:00:00') $createdAt = date('Y-m-d H:i:s');
    if ($updatedAt === '0000-00-00 00:00:00') $updatedAt = $createdAt;

    $stmtPlace->execute([
        $userId,
        $companyName,
        $phone,
        $c['area'] ?? null,
        $registerDate,
        $c['source_path'] ?? null,
        $status,
        null, // initial_memo
        $c['request_content'] ?? null,
        $contractDate,
        (float)($c['payment_amount'] ?? 0),
        $oldId,
        $createdAt,
        $updatedAt,
    ]);

    $newPlaceId = (int)$sqlite->lastInsertId();
    $placeIdMap[$oldId] = $newPlaceId;
    $placeCount++;
}

$sqlite->commit();
echo "[OK] Migrated $placeCount place records\n\n";

// ============================================
// Phase 4: Memos
// ============================================

echo "--- Phase 4: Memos ---\n";

$sqlite->beginTransaction();

$stmtMemo = $sqlite->prepare("
    INSERT INTO memos (target_type, target_id, user_id, content, is_visible, old_system_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

// 4a. bs_agent_memo → memos (company type)
$agentMemos = $mysql->query("SELECT * FROM bs_agent_memo ORDER BY id")->fetchAll();
echo "Source: " . count($agentMemos) . " agent memos\n";

$memoCount = 0;
foreach ($agentMemos as $m) {
    $agentManageId = (int)($m['agent_manage_id'] ?? 0);
    $targetId = $companyIdMap[$agentManageId] ?? null;
    if ($targetId === null) continue; // Skip if company not found

    $createdId = (int)($m['created_id'] ?? 0);
    $userId = $createdId > 0 ? resolveUserId($createdId, $userIdMap, $fallbackUserId) : $fallbackUserId;

    $content = trim($m['memo_content'] ?? '');
    if ($content === '') continue;

    // Clean HTML
    $content = str_replace(['<br />', '<br/>', '<br>'], "\n", $content);
    $content = strip_tags($content);
    $content = trim($content);
    if ($content === '') continue;

    $isVisible = (int)($m['is_visible'] ?? 1);

    $createdAt = $m['created_at'] ?? date('Y-m-d H:i:s');
    if ($createdAt === '0000-00-00 00:00:00') $createdAt = date('Y-m-d H:i:s');

    $stmtMemo->execute([
        'company',
        $targetId,
        $userId,
        $content,
        $isVisible,
        (int)$m['id'],
        $createdAt,
    ]);
    $memoCount++;
}
echo "[OK] Migrated $memoCount agent memos\n";

// 4b. Inline memos from bs_agentManage.memo field
$inlineMemos = $mysql->query(
    "SELECT id, memo, created_id, created_at FROM bs_agentManage WHERE memo IS NOT NULL AND memo != '' ORDER BY id"
)->fetchAll();
echo "Source: " . count($inlineMemos) . " inline memos\n";

$inlineCount = 0;
foreach ($inlineMemos as $m) {
    $oldId = (int)$m['id'];
    $targetId = $companyIdMap[$oldId] ?? null;
    if ($targetId === null) continue;

    $content = trim($m['memo'] ?? '');
    if ($content === '') continue;

    // Clean HTML
    $content = str_replace(['<br />', '<br/>', '<br>'], "\n", $content);
    $content = strip_tags($content);
    $content = trim($content);
    if ($content === '') continue;

    $createdId = (int)($m['created_id'] ?? 0);
    $userId = $createdId > 0 ? resolveUserId($createdId, $userIdMap, $fallbackUserId) : $fallbackUserId;

    $createdAt = $m['created_at'] ?? date('Y-m-d H:i:s');
    if ($createdAt === '0000-00-00 00:00:00') $createdAt = date('Y-m-d H:i:s');

    // Mark inline memos with a prefix for identification
    $content = "[인라인메모]\n" . $content;

    $stmtMemo->execute([
        'company',
        $targetId,
        $userId,
        $content,
        1,
        null, // no separate old_system_id for inline
        $createdAt,
    ]);
    $inlineCount++;
}
echo "[OK] Migrated $inlineCount inline memos\n";

// 4c. bs_customer_memo → memos (place type)
$customerMemos = $mysql->query("SELECT * FROM bs_customer_memo ORDER BY id")->fetchAll();
echo "Source: " . count($customerMemos) . " customer memos\n";

$custMemoCount = 0;
foreach ($customerMemos as $m) {
    $customerId = (int)($m['customer_id'] ?? 0);
    $targetId = $placeIdMap[$customerId] ?? null;
    if ($targetId === null) continue;

    $createdId = (int)($m['created_id'] ?? 0);
    $userId = $createdId > 0 ? resolveUserId($createdId, $userIdMap, $fallbackUserId) : $fallbackUserId;

    $content = trim($m['memo_content'] ?? '');
    if ($content === '') continue;

    // Clean HTML
    $content = str_replace(['<br />', '<br/>', '<br>'], "\n", $content);
    $content = strip_tags($content);
    $content = trim($content);
    if ($content === '') continue;

    $isVisible = (int)($m['is_visible'] ?? 1);

    $createdAt = $m['created_at'] ?? date('Y-m-d H:i:s');
    if ($createdAt === '0000-00-00 00:00:00') $createdAt = date('Y-m-d H:i:s');

    $stmtMemo->execute([
        'place',
        $targetId,
        $userId,
        $content,
        $isVisible,
        (int)$m['id'],
        $createdAt,
    ]);
    $custMemoCount++;
}
echo "[OK] Migrated $custMemoCount customer memos\n\n";

$sqlite->commit();

// ============================================
// Phase 5: Attendance
// ============================================

echo "--- Phase 5: Attendance ---\n";

$attendance = $mysql->query("SELECT * FROM bs_attendance ORDER BY id")->fetchAll();
echo "Source: " . count($attendance) . " records\n";

$stmtAttendance = $sqlite->prepare("
    INSERT INTO attendance (user_id, attendance_date, check_in, check_out, old_system_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
");

$sqlite->beginTransaction();

$attendCount = 0;
foreach ($attendance as $a) {
    $memberId = (int)($a['member_id'] ?? 0);
    $userId = $memberId > 0 ? resolveUserId($memberId, $userIdMap, $fallbackUserId) : $fallbackUserId;

    $attendDate = $a['attendance_date'] ?? null;
    if (!$attendDate || $attendDate === '0000-00-00') continue;

    $checkIn = $a['on_date'] ?? null;
    $checkOut = $a['off_date'] ?? null;
    if ($checkIn === '0000-00-00 00:00:00') $checkIn = null;
    if ($checkOut === '0000-00-00 00:00:00') $checkOut = null;

    $stmtAttendance->execute([
        $userId,
        $attendDate,
        $checkIn,
        $checkOut,
        (int)$a['id'],
        $attendDate . ' 00:00:00',
    ]);
    $attendCount++;
}

$sqlite->commit();
echo "[OK] Migrated $attendCount attendance records\n\n";

// ============================================
// Phase 6: Login Logs (bs_connect)
// ============================================

echo "--- Phase 6: Login Logs (bs_connect) ---\n";

// Fetch in batches for large table
$totalConnect = $mysql->query("SELECT COUNT(*) as cnt FROM bs_connect")->fetch()['cnt'];
echo "Source: $totalConnect records\n";

$batchSize = 5000;
$offset = 0;
$loginCount = 0;

$stmtLogin = $sqlite->prepare("
    INSERT INTO login_logs (user_id, login_id, login_type, login_ip, old_system_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
");

// Build login_id → user_id map for faster lookup
$loginIdMap = [];
foreach ($users as $u) {
    $newId = $userIdMap[(int)$u['id']] ?? null;
    if ($newId !== null) {
        $loginIdMap[$u['login_id']] = $newId;
    }
}

while ($offset < $totalConnect) {
    $sqlite->beginTransaction();

    $batch = $mysql->query("SELECT * FROM bs_connect ORDER BY id LIMIT $batchSize OFFSET $offset")->fetchAll();
    if (empty($batch)) break;

    foreach ($batch as $l) {
        $loginId = $l['login_id'] ?? '';
        $userId = $loginIdMap[$loginId] ?? $fallbackUserId;

        $createdAt = $l['created_at'] ?? date('Y-m-d H:i:s');
        if ($createdAt === '0000-00-00 00:00:00') $createdAt = date('Y-m-d H:i:s');

        $stmtLogin->execute([
            $userId,
            $loginId,
            $l['login_type'] ?? null,
            $l['login_ip'] ?? null,
            (int)$l['id'],
            $createdAt,
        ]);
        $loginCount++;
    }

    $sqlite->commit();
    $offset += $batchSize;
    echo "  ... $loginCount / $totalConnect\n";
}

echo "[OK] Migrated $loginCount login logs\n\n";

// ============================================
// Re-enable foreign keys
// ============================================

$sqlite->exec('PRAGMA foreign_keys = ON');

// ============================================
// Phase 7: Verification
// ============================================

echo "=== Verification ===\n\n";

// Count comparisons
$checks = [
    ['bs_users', 'users', count($users)],
    ['bs_agentManage', 'companies', count($agents)],
    ['bs_customer_info', 'place_db', count($customers)],
    ['bs_attendance', 'attendance', count($attendance)],
];

$allOk = true;
foreach ($checks as [$srcTable, $dstTable, $srcCount]) {
    $dstCount = (int)$sqlite->query("SELECT COUNT(*) FROM $dstTable")->fetchColumn();
    $status = ($dstCount >= $srcCount) ? 'OK' : 'MISMATCH';
    if ($status !== 'OK') $allOk = false;
    echo "[$status] $srcTable ($srcCount) → $dstTable ($dstCount)\n";
}

// Memos: compare total
$srcMemoTotal = count($agentMemos) + count($inlineMemos) + count($customerMemos);
$dstMemoTotal = (int)$sqlite->query("SELECT COUNT(*) FROM memos")->fetchColumn();
$memoStatus = ($dstMemoTotal > 0) ? 'OK' : 'WARN';
echo "[$memoStatus] Memos: source ~$srcMemoTotal → memos ($dstMemoTotal) [some empty memos skipped]\n";

// Login logs
$dstLoginCount = (int)$sqlite->query("SELECT COUNT(*) FROM login_logs")->fetchColumn();
echo "[" . ($dstLoginCount >= $totalConnect ? 'OK' : 'MISMATCH') . "] bs_connect ($totalConnect) → login_logs ($dstLoginCount)\n";

// Company details
$dstDetailCount = (int)$sqlite->query("SELECT COUNT(*) FROM company_details")->fetchColumn();
echo "[" . ($dstDetailCount === count($agents) ? 'OK' : 'MISMATCH') . "] company_details: $dstDetailCount (should be " . count($agents) . ")\n";

// Employee incentives
$dstIncentiveCount = (int)$sqlite->query("SELECT COUNT(*) FROM employee_incentives")->fetchColumn();
echo "[OK] employee_incentives: $dstIncentiveCount records\n";

// Sum verification for companies
$srcSumRow = $mysql->query("SELECT SUM(payment_Amt) as total FROM bs_agentManage")->fetch();
$dstSumRow = $sqlite->query("SELECT SUM(payment_amount) as total FROM companies")->fetch();
$srcSum = (float)$srcSumRow['total'];
$dstSum = (float)$dstSumRow['total'];
$sumMatch = abs($srcSum - $dstSum) < 1;
echo "[" . ($sumMatch ? 'OK' : 'MISMATCH') . "] Payment sum: source=$srcSum → target=$dstSum\n";

echo "\n=== Migration Complete ===\n";
if ($allOk) {
    echo "All checks passed!\n";
} else {
    echo "WARNING: Some checks had mismatches. Review above.\n";
}

echo "\nIMPORTANT: All migrated users have temporary password 'changeme123!'\n";
echo "Users should change their passwords on first login.\n";

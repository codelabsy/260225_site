<?php
/**
 * Database initialization script.
 * Creates all tables, indexes, triggers, and default admin account.
 * Safe to run multiple times (IF NOT EXISTS).
 */

// Prevent web access - CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

// ============================================
// Tables
// ============================================

$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'EMPLOYEE' CHECK(role IN ('ADMIN', 'EMPLOYEE')),
    position TEXT,
    phone TEXT,
    mobile_phone TEXT,
    email TEXT,
    user_type TEXT,
    base_salary REAL DEFAULT 0,
    memo TEXT,
    delete_auth INTEGER DEFAULT 0,
    modify_auth INTEGER DEFAULT 0,
    move_auth INTEGER DEFAULT 0,
    agent_id INTEGER,
    team_id INTEGER,
    division TEXT,
    msn TEXT,
    include_check INTEGER DEFAULT 0,
    login_count INTEGER DEFAULT 0,
    last_login TEXT,
    old_system_id INTEGER,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    register_date TEXT NOT NULL,
    product_name TEXT NOT NULL,
    company_name TEXT NOT NULL,
    payment_amount REAL NOT NULL DEFAULT 0,
    invoice_amount REAL DEFAULT 0,
    execution_cost REAL NOT NULL DEFAULT 0,
    vat_included INTEGER NOT NULL DEFAULT 1,
    vat REAL NOT NULL DEFAULT 0,
    net_margin REAL NOT NULL DEFAULT 0,
    registrant_position TEXT,
    carried_from_id INTEGER REFERENCES companies(id) ON DELETE SET NULL,
    old_system_id INTEGER,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS company_details (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL UNIQUE REFERENCES companies(id) ON DELETE CASCADE,
    sales_register_date TEXT,
    work_start_date TEXT,
    work_end_date TEXT,
    contract_start TEXT,
    contract_end TEXT,
    contract_term TEXT,
    business_name TEXT,
    ceo_name TEXT,
    phone TEXT,
    payment_type TEXT,
    business_number TEXT,
    work_keywords TEXT,
    work_content TEXT,
    email TEXT,
    naver_account TEXT,
    detail_execution_cost REAL DEFAULT 0,
    outsourcing_cost REAL DEFAULT 0,
    keyword_cost REAL DEFAULT 0,
    pg_name TEXT,
    tax_type TEXT,
    bank TEXT,
    card_number TEXT,
    card_owner TEXT,
    card_owner_birth TEXT,
    card_validity TEXT,
    card_installment TEXT,
    making_type TEXT,
    making_bigo TEXT,
    vpn_id TEXT,
    blog_id TEXT,
    blog_pass TEXT,
    naver_ad_id TEXT,
    naver_ad_pass TEXT,
    daum_ad_id TEXT,
    daum_ad_pass TEXT,
    target_url TEXT,
    making_url TEXT,
    created_level TEXT,
    updated_by INTEGER,
    updated_level TEXT,
    old_system_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS upload_histories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    file_name TEXT NOT NULL,
    file_path TEXT,
    total_count INTEGER NOT NULL DEFAULT 0,
    duplicate_count INTEGER NOT NULL DEFAULT 0,
    success_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS shopping_db (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    company_name TEXT,
    representative TEXT,
    phone TEXT NOT NULL,
    mobile_phone TEXT,
    status TEXT NOT NULL DEFAULT '안함' CHECK(status IN ('부재', '재통', '가망', '계약완료', '안함')),
    upload_history_id INTEGER REFERENCES upload_histories(id) ON DELETE SET NULL,
    keyword TEXT,
    page_number TEXT,
    is_overseas TEXT,
    product_name TEXT,
    store_url TEXT,
    store_name TEXT,
    store TEXT,
    review_count INTEGER,
    bookmark_count INTEGER,
    grade TEXT,
    service TEXT,
    store_id TEXT,
    store_description TEXT,
    email TEXT,
    business_number TEXT,
    address TEXT,
    ecommerce_number TEXT,
    age_10s TEXT,
    age_20s TEXT,
    age_30s TEXT,
    age_40s TEXT,
    age_50s TEXT,
    age_60s TEXT,
    gender_male TEXT,
    gender_female TEXT,
    talktalk_url TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS place_db (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    company_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    region TEXT,
    register_date TEXT NOT NULL,
    source TEXT,
    status TEXT NOT NULL DEFAULT '일반' CHECK(status IN ('일반', '부재', '재통', '가망', '거절', '계약완료')),
    initial_memo TEXT,
    request_content TEXT,
    contract_date TEXT,
    payment_amount REAL DEFAULT 0,
    old_system_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS memos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    target_type TEXT NOT NULL CHECK(target_type IN ('company', 'shopping', 'place')),
    target_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    content TEXT NOT NULL,
    is_visible INTEGER NOT NULL DEFAULT 1,
    old_system_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS status_histories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    target_type TEXT NOT NULL CHECK(target_type IN ('shopping', 'place')),
    target_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    old_status TEXT,
    new_status TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    target_type TEXT,
    target_id INTEGER,
    description TEXT,
    old_value TEXT,
    new_value TEXT,
    ip_address TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS db_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    db_type TEXT NOT NULL CHECK(db_type IN ('shopping', 'place')),
    db_id INTEGER NOT NULL,
    from_user_id INTEGER REFERENCES users(id),
    to_user_id INTEGER REFERENCES users(id),
    action TEXT NOT NULL CHECK(action IN ('assign', 'revoke')),
    admin_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    attendance_date TEXT NOT NULL,
    check_in TEXT,
    check_out TEXT,
    old_system_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS login_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    login_id TEXT,
    login_type TEXT,
    login_ip TEXT,
    old_system_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS employee_incentives (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    incentive_rate REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS sales_targets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    year INTEGER NOT NULL,
    month INTEGER,
    target_amount REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");

// ============================================
// Indexes
// ============================================

$indexes = [
    // users
    'CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username)',
    'CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)',
    'CREATE INDEX IF NOT EXISTS idx_users_is_active ON users(is_active)',

    // companies
    'CREATE INDEX IF NOT EXISTS idx_companies_user_id ON companies(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_companies_register_date ON companies(register_date)',
    'CREATE INDEX IF NOT EXISTS idx_companies_company_name ON companies(company_name)',
    'CREATE INDEX IF NOT EXISTS idx_companies_created_at ON companies(created_at)',

    // company_details
    'CREATE UNIQUE INDEX IF NOT EXISTS idx_company_details_company_id ON company_details(company_id)',
    'CREATE INDEX IF NOT EXISTS idx_company_details_business_number ON company_details(business_number)',
    'CREATE INDEX IF NOT EXISTS idx_company_details_ceo_name ON company_details(ceo_name)',

    // shopping_db
    'CREATE INDEX IF NOT EXISTS idx_shopping_user_id ON shopping_db(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_shopping_phone ON shopping_db(phone)',
    'CREATE INDEX IF NOT EXISTS idx_shopping_status ON shopping_db(status)',
    'CREATE INDEX IF NOT EXISTS idx_shopping_created_at ON shopping_db(created_at)',
    'CREATE INDEX IF NOT EXISTS idx_shopping_company_name ON shopping_db(company_name)',

    // place_db
    'CREATE INDEX IF NOT EXISTS idx_place_user_id ON place_db(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_place_phone ON place_db(phone)',
    'CREATE INDEX IF NOT EXISTS idx_place_status ON place_db(status)',
    'CREATE INDEX IF NOT EXISTS idx_place_region ON place_db(region)',
    'CREATE INDEX IF NOT EXISTS idx_place_register_date ON place_db(register_date)',
    'CREATE INDEX IF NOT EXISTS idx_place_company_name ON place_db(company_name)',

    // memos
    'CREATE INDEX IF NOT EXISTS idx_memos_target ON memos(target_type, target_id)',
    'CREATE INDEX IF NOT EXISTS idx_memos_user_id ON memos(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_memos_created_at ON memos(created_at)',

    // status_histories
    'CREATE INDEX IF NOT EXISTS idx_status_hist_target ON status_histories(target_type, target_id)',
    'CREATE INDEX IF NOT EXISTS idx_status_hist_user_id ON status_histories(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_status_hist_created_at ON status_histories(created_at)',

    // upload_histories
    'CREATE INDEX IF NOT EXISTS idx_upload_hist_user_id ON upload_histories(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_upload_hist_created_at ON upload_histories(created_at)',

    // activity_logs
    'CREATE INDEX IF NOT EXISTS idx_activity_user_id ON activity_logs(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_activity_action ON activity_logs(action)',
    'CREATE INDEX IF NOT EXISTS idx_activity_target ON activity_logs(target_type, target_id)',
    'CREATE INDEX IF NOT EXISTS idx_activity_created_at ON activity_logs(created_at)',

    // db_assignments
    'CREATE INDEX IF NOT EXISTS idx_assign_db ON db_assignments(db_type, db_id)',
    'CREATE INDEX IF NOT EXISTS idx_assign_from ON db_assignments(from_user_id)',
    'CREATE INDEX IF NOT EXISTS idx_assign_to ON db_assignments(to_user_id)',
    'CREATE INDEX IF NOT EXISTS idx_assign_created_at ON db_assignments(created_at)',

    // employee_incentives
    'CREATE UNIQUE INDEX IF NOT EXISTS idx_incentives_user_id ON employee_incentives(user_id)',

    // sales_targets
    'CREATE UNIQUE INDEX IF NOT EXISTS idx_targets_user_year ON sales_targets(user_id, year, month)',

    // attendance
    'CREATE INDEX IF NOT EXISTS idx_attendance_user_id ON attendance(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(attendance_date)',

    // login_logs
    'CREATE INDEX IF NOT EXISTS idx_login_logs_user_id ON login_logs(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_login_logs_created_at ON login_logs(created_at)',

    // old_system_id tracking
    'CREATE INDEX IF NOT EXISTS idx_users_old_id ON users(old_system_id)',
    'CREATE INDEX IF NOT EXISTS idx_companies_old_id ON companies(old_system_id)',
    'CREATE INDEX IF NOT EXISTS idx_company_details_old_id ON company_details(old_system_id)',
    'CREATE INDEX IF NOT EXISTS idx_place_old_id ON place_db(old_system_id)',
];

foreach ($indexes as $sql) {
    $pdo->exec($sql);
}

// ============================================
// Triggers (net margin auto-calculation)
// ============================================

// Check if triggers already exist before creating
$existingTriggers = [];
$result = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'trigger'");
foreach ($result as $row) {
    $existingTriggers[] = $row['name'];
}

if (!in_array('trg_companies_calc_insert', $existingTriggers)) {
    $pdo->exec("
    CREATE TRIGGER trg_companies_calc_insert
    AFTER INSERT ON companies
    BEGIN
        UPDATE companies
        SET vat = CASE WHEN NEW.vat_included = 1 THEN ROUND(NEW.execution_cost / 11, 0) ELSE 0 END,
            invoice_amount = CASE WHEN NEW.vat_included = 1 THEN NEW.payment_amount - ROUND(NEW.payment_amount / 11, 0) ELSE NEW.payment_amount END,
            net_margin = NEW.payment_amount - NEW.execution_cost - CASE WHEN NEW.vat_included = 1 THEN ROUND(NEW.execution_cost / 11, 0) ELSE 0 END
        WHERE id = NEW.id;
    END
    ");
}

if (!in_array('trg_companies_calc_update', $existingTriggers)) {
    $pdo->exec("
    CREATE TRIGGER trg_companies_calc_update
    AFTER UPDATE OF payment_amount, execution_cost, vat_included ON companies
    BEGIN
        UPDATE companies
        SET vat = CASE WHEN NEW.vat_included = 1 THEN ROUND(NEW.execution_cost / 11, 0) ELSE 0 END,
            invoice_amount = CASE WHEN NEW.vat_included = 1 THEN NEW.payment_amount - ROUND(NEW.payment_amount / 11, 0) ELSE NEW.payment_amount END,
            net_margin = NEW.payment_amount - NEW.execution_cost - CASE WHEN NEW.vat_included = 1 THEN ROUND(NEW.execution_cost / 11, 0) ELSE 0 END,
            updated_at = datetime('now', 'localtime')
        WHERE id = NEW.id;
    END
    ");
}

// ============================================
// Default admin account
// ============================================

$existing = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetch();
if (!$existing) {
    $hash = password_hash('admin1234', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password, name, role, is_active) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute(['admin', $hash, 'Administrator', 'ADMIN', 1]);
    echo "Admin account created.\n";
} else {
    echo "Admin account already exists.\n";
}

echo "Database initialization complete.\n";

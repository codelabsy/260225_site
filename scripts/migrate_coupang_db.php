<?php
/**
 * Coupang DB 도입 마이그레이션.
 * - coupang_db 테이블 + 인덱스 생성
 * - memos / status_histories / db_assignments CHECK 제약에 'coupang' 추가
 *
 * 실행 방법 (서버 SSH):
 *   php scripts/migrate_coupang_db.php
 *
 * 안전: IF NOT EXISTS 기반. 중복 실행 가능.
 */

// CLI 또는 관리자 웹 접근 허용.
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../core/Auth.php';
    if (!Auth::check() || !Auth::isAdmin()) {
        http_response_code(403);
        echo 'Admin auth required.';
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function table_sql(PDO $pdo, string $name): ?string
{
    $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    return $row ? $row['sql'] : null;
}

echo "== Coupang DB 마이그레이션 시작 ==\n";

// 1. coupang_db 테이블 생성
$pdo->exec("
CREATE TABLE IF NOT EXISTS coupang_db (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    company_name TEXT,
    representative TEXT,
    phone TEXT NOT NULL,
    mobile_phone TEXT,
    status TEXT NOT NULL DEFAULT '대기중' CHECK(status IN ('대기중', '부재', '재통', '가망', '계약완료', '거절')),
    upload_history_id INTEGER REFERENCES upload_histories(id) ON DELETE SET NULL,
    keyword TEXT,
    product_name TEXT,
    product_id TEXT,
    email TEXT,
    business_number TEXT,
    address TEXT,
    address_detail TEXT,
    rating_count INTEGER,
    recommend_count INTEGER,
    not_recommend_count INTEGER,
    recommend_ratio INTEGER,
    collected_at TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)
");
echo "  [OK] coupang_db 테이블\n";

$indexes = [
    'CREATE INDEX IF NOT EXISTS idx_coupang_user_id ON coupang_db(user_id)',
    'CREATE INDEX IF NOT EXISTS idx_coupang_phone ON coupang_db(phone)',
    'CREATE INDEX IF NOT EXISTS idx_coupang_status ON coupang_db(status)',
    'CREATE INDEX IF NOT EXISTS idx_coupang_created_at ON coupang_db(created_at)',
    'CREATE INDEX IF NOT EXISTS idx_coupang_company_name ON coupang_db(company_name)',
];
foreach ($indexes as $sql) {
    $pdo->exec($sql);
}
echo "  [OK] coupang_db 인덱스\n";

// 2. CHECK 제약 업데이트 (memos / status_histories / db_assignments)
// writable_schema 방식으로 sqlite_master의 SQL 문자열을 직접 교체.
// 이미 'coupang'이 포함된 경우 건너뜀.

function update_check_constraint(PDO $pdo, string $table, string $from, string $to): bool
{
    $current = table_sql($pdo, $table);
    if ($current === null) {
        echo "  [SKIP] {$table} (테이블 없음)\n";
        return false;
    }
    if (strpos($current, $to) !== false) {
        echo "  [SKIP] {$table} (이미 업데이트됨)\n";
        return false;
    }
    if (strpos($current, $from) === false) {
        echo "  [WARN] {$table} (예상 CHECK 구문을 찾지 못함)\n";
        echo "         현재 SQL: " . $current . "\n";
        return false;
    }
    $newSql = str_replace($from, $to, $current);

    $pdo->exec('PRAGMA writable_schema = ON');
    $stmt = $pdo->prepare("UPDATE sqlite_master SET sql = ? WHERE type='table' AND name=?");
    $stmt->execute([$newSql, $table]);
    $pdo->exec('PRAGMA writable_schema = OFF');

    echo "  [OK] {$table} CHECK 업데이트\n";
    return true;
}

$updated = 0;
$updated += (int) update_check_constraint(
    $pdo,
    'memos',
    "CHECK(target_type IN ('company', 'shopping', 'place'))",
    "CHECK(target_type IN ('company', 'shopping', 'coupang', 'place'))"
);
$updated += (int) update_check_constraint(
    $pdo,
    'status_histories',
    "CHECK(target_type IN ('shopping', 'place'))",
    "CHECK(target_type IN ('shopping', 'coupang', 'place'))"
);
$updated += (int) update_check_constraint(
    $pdo,
    'db_assignments',
    "CHECK(db_type IN ('shopping', 'place'))",
    "CHECK(db_type IN ('shopping', 'coupang', 'place'))"
);

if ($updated > 0) {
    // writable_schema로 수정한 스키마는 VACUUM 이후 안정적으로 반영됨
    $pdo->exec('VACUUM');
    echo "  [OK] VACUUM 완료\n";
}

echo "== 마이그레이션 완료 ==\n";

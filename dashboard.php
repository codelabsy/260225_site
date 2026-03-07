<?php
/**
 * Dashboard page entry point.
 */

require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Permission.php';

Permission::requireLogin();

$pageTitle = '대시보드';
$currentPage = 'dashboard';

$user = Auth::user();
$isAdmin = Auth::isAdmin();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/components/widget-card.php';

// SVG Icons
$iconSales = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
$iconMargin = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>';
$iconInvoice = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>';
$iconNewDb = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>';
$iconContract = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
$iconTarget = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>';
?>

<!-- Dashboard Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">대시보드</h1>
        <p class="text-sm text-gray-500 mt-1">
            <?= htmlspecialchars($user['name']) ?>님 환영합니다
            <?php if ($isAdmin): ?>
            <span class="badge badge-primary ml-2">관리자</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-3">
        <select id="dashboard-year" class="form-select w-32">
            <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?>
            <option value="<?= $y ?>" <?= $y == (int) date('Y') ? 'selected' : '' ?>><?= $y ?>년</option>
            <?php endfor; ?>
        </select>
        <?php if ($isAdmin): ?>
        <button onclick="location.href='/api/export/excel.php?type=companies&year=' + document.getElementById('dashboard-year').value"
                class="btn btn-outline btn-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            엑셀 다운로드
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- ===================== ADMIN DASHBOARD ===================== -->

<!-- Widget Cards Row -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div id="widget-today-sales">
        <?= renderWidgetCard('금일 매출', '0원', $iconSales, '', ['color' => 'blue', 'subtitle' => '계산서: 0원']) ?>
    </div>
    <div id="widget-month-sales">
        <?= renderWidgetCard('금월 매출', '0원', $iconSales, '', ['color' => 'green', 'subtitle' => '계산서: 0원']) ?>
    </div>
    <div id="widget-today-margin">
        <?= renderWidgetCard('금일 순마진', '0원', $iconMargin, '', ['color' => 'purple', 'subtitle' => '실행비: 0원']) ?>
    </div>
    <div id="widget-month-margin">
        <?= renderWidgetCard('금월 순마진', '0원', $iconMargin, '', ['color' => 'yellow', 'subtitle' => '실행비: 0원']) ?>
    </div>
</div>

<!-- Sub Widget Row -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div id="widget-invoice">
        <?= renderWidgetCard('계산서 발행금액 (월)', '0원', $iconInvoice, '', ['color' => 'blue']) ?>
    </div>
    <div id="widget-new-db">
        <?= renderWidgetCard('금일 신규 DB', '0건', $iconNewDb, '', ['color' => 'green']) ?>
    </div>
    <div id="widget-contracts">
        <?= renderWidgetCard('금일 계약완료', '0건', $iconContract, '', ['color' => 'red']) ?>
    </div>
</div>

<!-- Charts Row: Monthly Sales & Employee Comparison -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Monthly Sales Trend -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3>월별 매출 추이</h3>
        </div>
        <div class="card-body">
            <canvas id="chart-monthly-sales" height="280"></canvas>
        </div>
    </div>

    <!-- Employee Comparison -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3>직원별 실적 비교</h3>
        </div>
        <div class="card-body">
            <canvas id="chart-employee-comparison" height="280"></canvas>
        </div>
    </div>
</div>

<!-- Bottom Row: Status Distribution & Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Status Distribution -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3>상태별 DB 현황</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-2 gap-4">
                <div class="max-h-[280px]">
                    <p class="text-sm font-medium text-gray-500 mb-2 text-center">쇼핑DB</p>
                    <canvas id="chart-status-shopping"></canvas>
                </div>
                <div class="max-h-[280px]">
                    <p class="text-sm font-medium text-gray-500 mb-2 text-center">플레이스DB</p>
                    <canvas id="chart-status-place"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Log -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3>최근 활동 로그</h3>
            <a href="/activity-log.php" class="text-xs text-blue-600 hover:text-blue-800">전체보기</a>
        </div>
        <div class="card-body p-0">
            <div id="recent-activity" class="divide-y divide-gray-100 max-h-[400px] overflow-y-auto">
                <div class="p-8 text-center text-gray-400 text-sm">로딩 중...</div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===================== EMPLOYEE DASHBOARD ===================== -->

<!-- Widget Cards Row -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div id="widget-today-sales">
        <?= renderWidgetCard('금일 매출', '0원', $iconSales, '', ['color' => 'blue']) ?>
    </div>
    <div id="widget-month-sales">
        <?= renderWidgetCard('금월 매출', '0원', $iconSales, '', ['color' => 'green']) ?>
    </div>
    <div id="widget-today-margin">
        <?= renderWidgetCard('금일 순마진', '0원', $iconMargin, '', ['color' => 'purple']) ?>
    </div>
    <div id="widget-month-margin">
        <?= renderWidgetCard('금월 순마진', '0원', $iconMargin, '', ['color' => 'yellow']) ?>
    </div>
</div>

<!-- Target & Incentive -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <!-- Monthly Target Progress -->
    <div class="card">
        <div class="card-header">
            <h3>월 목표 달성률</h3>
        </div>
        <div class="card-body">
            <div class="flex items-end justify-between mb-2">
                <span id="target-current" class="text-2xl font-bold text-gray-900">0원</span>
                <span id="target-amount" class="text-sm text-gray-500">목표: 0원</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                <div id="target-bar" class="h-full bg-blue-600 rounded-full transition-all duration-1000 ease-out" style="width: 0%"></div>
            </div>
            <div class="flex items-center justify-between mt-2">
                <span id="target-rate" class="text-sm font-semibold text-blue-600">0%</span>
                <span id="target-remaining" class="text-xs text-gray-400">남은 금액: 0원</span>
            </div>
        </div>
    </div>

    <!-- Incentive Calculation -->
    <div class="card">
        <div class="card-header">
            <h3>인센티브 예상</h3>
        </div>
        <div class="card-body">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">금월 순마진</span>
                    <span id="incentive-margin" class="text-sm font-medium">0원</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">인센티브율</span>
                    <span id="incentive-rate" class="text-sm font-medium">0%</span>
                </div>
                <hr class="border-gray-200">
                <div class="flex justify-between">
                    <span class="text-sm font-semibold text-gray-700">예상 인센티브</span>
                    <span id="incentive-amount" class="text-lg font-bold text-green-600">0원</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Sales Chart -->
<div class="card mb-6">
    <div class="card-header">
        <h3>월별 매출 추이</h3>
    </div>
    <div class="card-body">
        <canvas id="chart-monthly-sales" height="300"></canvas>
    </div>
</div>

<?php endif; ?>

<script src="/assets/js/dashboard.js"></script>
<?php require_once __DIR__ . '/templates/footer.php'; ?>

<?php
/**
 * Statistics page content.
 * Tabs: Sales, Status (admin), Employee (admin)
 */

$user = Auth::user();
$isAdmin = Auth::isAdmin();

require_once __DIR__ . '/../../templates/header.php';
?>

<!-- Stats Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">통계</h1>
        <p class="text-sm text-gray-500 mt-1">기간별, 상태별, 직원별 실적 분석</p>
    </div>
    <?php if ($isAdmin): ?>
    <button id="btn-excel-download" class="btn btn-outline btn-sm">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        엑셀 다운로드
    </button>
    <?php endif; ?>
</div>

<!-- Tab Navigation -->
<div class="border-b border-gray-200 mb-6">
    <nav class="flex space-x-4" aria-label="Tabs">
        <button class="stats-tab active px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600" data-tab="sales">
            기간별 매출 통계
        </button>
        <?php if ($isAdmin): ?>
        <button class="stats-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="status">
            상태별 통계
        </button>
        <button class="stats-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300" data-tab="employee">
            직원별 실적 비교
        </button>
        <?php endif; ?>
    </nav>
</div>

<!-- ========== SALES TAB ========== -->
<div id="tab-sales" class="stats-tab-content">
    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="form-label">집계 단위</label>
                    <select id="sales-type" class="form-select w-32">
                        <option value="daily">일별</option>
                        <option value="monthly" selected>월별</option>
                        <option value="yearly">연별</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">시작일</label>
                    <input type="date" id="sales-start" class="form-input w-40" value="<?= date('Y') ?>-01-01">
                </div>
                <div>
                    <label class="form-label">종료일</label>
                    <input type="date" id="sales-end" class="form-input w-40" value="<?= date('Y-m-d') ?>">
                </div>
                <?php if ($isAdmin): ?>
                <div>
                    <label class="form-label">담당자</label>
                    <select id="sales-user" class="form-select w-40">
                        <option value="">전체</option>
                    </select>
                </div>
                <?php endif; ?>
                <button id="btn-sales-search" class="btn btn-primary btn-sm">조회</button>
            </div>
        </div>
    </div>

    <!-- Sales Chart -->
    <div class="card mb-6">
        <div class="card-header">
            <h3>매출 추이 차트</h3>
        </div>
        <div class="card-body">
            <canvas id="chart-sales-trend" height="320"></canvas>
        </div>
    </div>

    <!-- Sales Data Table -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3>상세 데이터</h3>
            <div id="sales-totals" class="text-sm text-gray-500"></div>
        </div>
        <div class="card-body p-0">
            <div class="table-container overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-4 py-3 text-left font-medium text-gray-600">기간</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">매출액</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">실행비</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">부가세</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">순마진</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">계산서</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">건수</th>
                        </tr>
                    </thead>
                    <tbody id="sales-table-body">
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">조회 버튼을 눌러주세요</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- ========== STATUS TAB ========== -->
<div id="tab-status" class="stats-tab-content hidden">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Shopping Status -->
        <div class="card">
            <div class="card-header">
                <h3>쇼핑DB 상태 분포</h3>
            </div>
            <div class="card-body">
                <div class="max-h-[260px]">
                    <canvas id="chart-status-shopping-stats"></canvas>
                </div>
                <div id="status-shopping-legend" class="mt-4 space-y-2"></div>
            </div>
        </div>

        <!-- Place Status -->
        <div class="card">
            <div class="card-header">
                <h3>플레이스DB 상태 분포</h3>
            </div>
            <div class="card-body">
                <div class="max-h-[260px]">
                    <canvas id="chart-status-place-stats"></canvas>
                </div>
                <div id="status-place-legend" class="mt-4 space-y-2"></div>
            </div>
        </div>

        <!-- Combined Status -->
        <div class="card">
            <div class="card-header">
                <h3>통합 상태 분포</h3>
            </div>
            <div class="card-body">
                <div class="max-h-[260px]">
                    <canvas id="chart-status-combined"></canvas>
                </div>
                <div id="status-combined-legend" class="mt-4 space-y-2"></div>
            </div>
        </div>
    </div>

    <!-- Status Data Table -->
    <div class="card">
        <div class="card-header">
            <h3>상태별 건수 상세</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-container overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-4 py-3 text-left font-medium text-gray-600">상태</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">쇼핑DB</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">플레이스DB</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">합계</th>
                        </tr>
                    </thead>
                    <tbody id="status-table-body">
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">로딩 중...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========== EMPLOYEE TAB ========== -->
<div id="tab-employee" class="stats-tab-content hidden">
    <!-- Filters -->
    <div class="card mb-6">
        <div class="card-body">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="form-label">년도</label>
                    <select id="emp-year" class="form-select w-28">
                        <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == (int) date('Y') ? 'selected' : '' ?>><?= $y ?>년</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">월</label>
                    <select id="emp-month" class="form-select w-24">
                        <option value="">전체</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == (int) date('m') ? 'selected' : '' ?>><?= $m ?>월</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button id="btn-emp-search" class="btn btn-primary btn-sm">조회</button>
            </div>
        </div>
    </div>

    <!-- Employee Chart -->
    <div class="card mb-6">
        <div class="card-header">
            <h3>직원별 실적 비교</h3>
        </div>
        <div class="card-body">
            <canvas id="chart-employee-stats" height="350"></canvas>
        </div>
    </div>

    <!-- Employee Data Table -->
    <div class="card">
        <div class="card-header">
            <h3>직원별 상세 실적</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-container overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-4 py-3 text-left font-medium text-gray-600">직원명</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">매출액</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">순마진</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">계약완료</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">콜 수</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">인센티브율</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">예상 인센티브</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">목표</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600">달성률</th>
                        </tr>
                    </thead>
                    <tbody id="employee-table-body">
                        <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">조회 버튼을 눌러주세요</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    window.__isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
</script>
<script src="/assets/js/stats.js"></script>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

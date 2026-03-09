<?php
/**
 * ERP Sales Management - List Page.
 * 3-column layout: left filter | center table | right detail panel.
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../models/User.php';

Permission::requireLogin();

$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$pageTitle = 'ERP 매출관리';
$currentPage = 'erp';

// Get users for assignee filter (admin only)
$users = $isAdmin ? User::all() : [];

include __DIR__ . '/../../templates/header.php';
?>

<div class="flex gap-4 h-[calc(100vh-140px)]">
    <!-- Left Sidebar: Filters -->
    <aside id="erp-filter-panel" class="w-64 flex-shrink-0 bg-white rounded-lg border border-gray-200 shadow-sm p-4 overflow-y-auto">
        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
            <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            필터
        </h3>

        <!-- Period Filter -->
        <div class="mb-4">
            <label class="form-label text-xs text-gray-500">기간</label>
            <input type="date" id="filter-period-start" class="form-input text-sm py-1.5 mb-2">
            <div class="text-center text-gray-400 text-xs mb-2">~</div>
            <input type="date" id="filter-period-end" class="form-input text-sm py-1.5">
        </div>

        <?php if ($isAdmin): ?>
        <!-- Assignee Filter (Admin only) -->
        <div class="mb-4">
            <label class="form-label text-xs text-gray-500">담당자</label>
            <select id="filter-user" class="form-select text-sm py-1.5">
                <option value="">전체</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Payment Type Filter -->
        <div class="mb-4">
            <label class="form-label text-xs text-gray-500">결제구분</label>
            <select id="filter-payment-type" class="form-select text-sm py-1.5">
                <option value="">전체</option>
                <option value="카드">카드</option>
                <option value="현금">현금</option>
                <option value="계좌이체">계좌이체</option>
                <option value="기타">기타</option>
            </select>
        </div>

        <!-- Search Input -->
        <div class="mb-4">
            <label class="form-label text-xs text-gray-500">검색</label>
            <input type="text" id="filter-search" class="form-input text-sm py-1.5"
                   placeholder="상호명, 대표자, 연락처, 사업자번호">
        </div>

        <!-- Buttons -->
        <div class="flex gap-2">
            <button type="button" onclick="ERP.applyFilters()" class="btn btn-primary text-sm py-1.5 flex-1">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                적용
            </button>
            <button type="button" onclick="ERP.resetFilters()" class="btn btn-outline text-sm py-1.5 flex-1">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                초기화
            </button>
        </div>
    </aside>

    <!-- Center: Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <!-- Summary Cards -->
        <div class="grid grid-cols-5 gap-3 mb-4">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-3">
                <div class="text-xs text-gray-500 mb-1">총 매출</div>
                <div id="summary-payment" class="text-lg font-bold text-gray-900">0</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-3">
                <div class="text-xs text-gray-500 mb-1">총 계산서발행</div>
                <div id="summary-invoice" class="text-lg font-bold text-blue-600">0</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-3">
                <div class="text-xs text-gray-500 mb-1">총 실행비</div>
                <div id="summary-execution" class="text-lg font-bold text-orange-600">0</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-3">
                <div class="text-xs text-gray-500 mb-1">총 부가세</div>
                <div id="summary-vat" class="text-lg font-bold text-purple-600">0</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-3">
                <div class="text-xs text-gray-500 mb-1">총 순마진</div>
                <div id="summary-margin" class="text-lg font-bold text-green-600">0</div>
            </div>
        </div>

        <!-- Table Header with Actions -->
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm text-gray-500">
                총 <span id="total-count" class="font-semibold text-gray-700">0</span>건
            </div>
            <?php if ($isAdmin): ?>
            <a href="<?= BASE_URL ?>/pages/erp/create.php" class="btn btn-primary text-sm py-1.5">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                업체 등록
            </a>
            <?php endif; ?>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden flex-1 flex flex-col">
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left" id="erp-table">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-3 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap cursor-pointer hover:bg-gray-100" data-sort="register_date">
                                등록일 <span class="sort-icon">&#8597;</span>
                            </th>
                            <th class="px-3 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">상품명</th>
                            <th class="px-3 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">상호명</th>
                            <th class="px-3 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">담당자</th>
                            <th class="px-3 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap text-right cursor-pointer hover:bg-gray-100" data-sort="payment_amount">
                                결제금액 <span class="sort-icon">&#8597;</span>
                            </th>
                            <th class="px-3 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap text-right">계산서발행</th>
                            <th class="px-3 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap text-right cursor-pointer hover:bg-gray-100" data-sort="execution_cost">
                                실행비 <span class="sort-icon">&#8597;</span>
                            </th>
                            <th class="px-3 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap text-right">부가세</th>
                            <th class="px-3 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap text-right cursor-pointer hover:bg-gray-100" data-sort="net_margin">
                                순마진 <span class="sort-icon">&#8597;</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="erp-table-body" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-gray-400 text-sm">
                                <div class="spinner mx-auto mb-2"></div>
                                데이터를 불러오는 중...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div id="erp-pagination" class="mt-3 flex items-center justify-between">
            <div class="flex items-center space-x-2 text-sm text-gray-600">
                <label>표시개수:</label>
                <select id="page-size-select" class="form-select rounded-md border-gray-300 text-sm py-1.5 pl-3 pr-8" onchange="ERP.changePageSize(this.value)">
                    <option value="50">50개</option>
                    <option value="100">100개</option>
                    <option value="300">300개</option>
                    <option value="500">500개</option>
                    <option value="1000">1000개</option>
                </select>
            </div>
            <nav id="pagination-nav" class="flex items-center space-x-1"></nav>
            <div id="pagination-info" class="text-sm text-gray-500"></div>
        </div>
    </div>
</div>

<!-- Right Side Detail Panel -->
<div id="detail-backdrop" class="side-panel-backdrop" onclick="ERP.closeDetail()"></div>
<div id="detail-panel" class="side-panel" style="width: 520px; top: 56px; height: calc(100vh - 56px);">
    <!-- Panel Header -->
    <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
        <h3 class="text-lg font-semibold text-gray-900" id="detail-title">업체 상세정보</h3>
        <div class="flex items-center gap-2">
            <?php if ($isAdmin): ?>
            <button type="button" id="btn-edit-company" class="btn btn-outline btn-sm" onclick="ERP.openEditMode()">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                수정
            </button>
            <button type="button" id="btn-carryover" class="btn btn-outline btn-sm" onclick="ERP.carryOver()">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                이월
            </button>
            <button type="button" id="btn-delete-company" class="btn btn-outline btn-sm text-red-600 border-red-300 hover:bg-red-50" onclick="ERP.deleteCompany()">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                삭제
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-ghost btn-icon" onclick="ERP.closeDetail()">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <!-- Panel Body -->
    <div id="detail-content" class="p-6 overflow-y-auto" style="height: calc(100% - 65px);">
        <div class="text-center text-gray-400 py-12">업체를 선택하세요.</div>
    </div>
</div>

<script>
    window.ERP_CONFIG = {
        isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
        currentUserId: <?= (int)$currentUser['id'] ?>,
        users: <?= json_encode(array_map(function($u) {
            return ['id' => $u['id'], 'name' => $u['name']];
        }, $users), JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="<?= BASE_URL ?>/assets/js/erp.js"></script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

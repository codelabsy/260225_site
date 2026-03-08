<?php
/**
 * Place DB List page - 3-column layout.
 * Left: Filters | Center: Table | Right: Detail panel
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/PlaceDB.php';

Permission::requireLogin();

$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$employees = $isAdmin ? User::getEmployees() : [];

// Get distinct regions and sources for filter dropdowns
$db = Database::getInstance();
$regions = $db->fetchAll("SELECT DISTINCT region FROM place_db WHERE region IS NOT NULL AND region != '' ORDER BY region ASC");
$sources = $db->fetchAll("SELECT DISTINCT source FROM place_db WHERE source IS NOT NULL AND source != '' ORDER BY source ASC");

include __DIR__ . '/../../templates/header.php';
?>

<!-- 3-Column Layout -->
<div class="flex gap-0 -mx-4 -mt-6" style="height: calc(100vh - 56px);">

    <!-- Left Sidebar: Filters -->
    <aside id="filter-sidebar" class="w-60 flex-shrink-0 bg-white border-r border-gray-200 overflow-y-auto">
        <div class="p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                필터
            </h3>

            <!-- Status Filter -->
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-500 mb-2">상태</label>
                <div class="space-y-1" id="status-filter">
                    <button type="button" class="status-filter-btn active w-full flex items-center justify-between px-3 py-1.5 text-sm rounded-md transition-colors" data-status="">
                        <span>전체</span>
                        <span class="status-count badge badge-secondary text-xs" data-status-count="total">0</span>
                    </button>
                    <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-1.5 text-sm rounded-md transition-colors" data-status="부재">
                        <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span>부재</span>
                        <span class="status-count badge badge-danger text-xs" data-status-count="부재">0</span>
                    </button>
                    <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-1.5 text-sm rounded-md transition-colors" data-status="재통">
                        <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-yellow-500 mr-2"></span>재통</span>
                        <span class="status-count badge badge-warning text-xs" data-status-count="재통">0</span>
                    </button>
                    <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-1.5 text-sm rounded-md transition-colors" data-status="가망">
                        <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-blue-500 mr-2"></span>가망</span>
                        <span class="status-count badge badge-primary text-xs" data-status-count="가망">0</span>
                    </button>
                    <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-1.5 text-sm rounded-md transition-colors" data-status="거절">
                        <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-gray-600 mr-2"></span>거절</span>
                        <span class="status-count badge badge-secondary text-xs" data-status-count="거절">0</span>
                    </button>
                    <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-1.5 text-sm rounded-md transition-colors" data-status="계약완료">
                        <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>계약완료</span>
                        <span class="status-count badge badge-success text-xs" data-status-count="계약완료">0</span>
                    </button>
                </div>
            </div>

            <!-- Period Filter -->
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">기간</label>
                <input type="date" id="filter-period-start" class="form-input text-xs py-1.5 mb-1 w-full" placeholder="시작일">
                <input type="date" id="filter-period-end" class="form-input text-xs py-1.5 w-full" placeholder="종료일">
            </div>

            <!-- Region Filter -->
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">지역</label>
                <select id="filter-region" class="form-select text-xs py-1.5 w-full">
                    <option value="">전체</option>
                    <?php foreach ($regions as $r): ?>
                    <option value="<?= htmlspecialchars($r['region']) ?>"><?= htmlspecialchars($r['region']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Source Filter -->
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">알게된 경로</label>
                <select id="filter-source" class="form-select text-xs py-1.5 w-full">
                    <option value="">전체</option>
                    <?php foreach ($sources as $s): ?>
                    <option value="<?= htmlspecialchars($s['source']) ?>"><?= htmlspecialchars($s['source']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($isAdmin): ?>
            <!-- Assignee Filter (admin only) -->
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">담당자</label>
                <select id="filter-user" class="form-select text-xs py-1.5 w-full">
                    <option value="">전체</option>
                    <option value="unassigned">미배정</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Search -->
            <div class="mb-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">검색</label>
                <input type="text" id="filter-search" class="form-input text-xs py-1.5 w-full" placeholder="상호명, 연락처">
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2">
                <button type="button" id="btn-apply-filter" class="btn btn-primary btn-sm flex-1">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    적용
                </button>
                <button type="button" id="btn-reset-filter" class="btn btn-outline btn-sm flex-1">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    초기화
                </button>
            </div>
        </div>
    </aside>

    <!-- Center: Main Table -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <!-- Action Bar -->
        <div class="bg-white border-b border-gray-200 px-4 py-2.5 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold text-gray-800">플레이스 DB</h2>
                <span id="total-count-badge" class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">0건</span>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($isAdmin): ?>
                <button type="button" id="btn-create" class="btn btn-primary btn-sm" onclick="openModal('modal-create')">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    DB 등록
                </button>
                <button type="button" id="btn-assign" class="btn btn-outline btn-sm" onclick="openAssignModal()">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    배분
                </button>
                <button type="button" id="btn-revoke" class="btn btn-outline btn-sm text-red-600 border-red-200 hover:bg-red-50" onclick="revokeSelected()">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    회수
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table Container -->
        <div class="flex-1 overflow-auto" id="table-container">
            <table id="place-table" class="w-full text-left">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="w-10 px-3 py-2.5">
                            <input type="checkbox" id="check-all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900 whitespace-nowrap" data-sort="company_name">
                            상호명 <span class="sort-icon"></span>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900 whitespace-nowrap" data-sort="phone">
                            연락처 <span class="sort-icon"></span>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900 whitespace-nowrap" data-sort="region">
                            지역 <span class="sort-icon"></span>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-gray-600 whitespace-nowrap">
                            경로
                        </th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900 whitespace-nowrap" data-sort="status">
                            상태 <span class="sort-icon"></span>
                        </th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-gray-600 whitespace-nowrap">
                            배정직원
                        </th>
                        <th class="px-3 py-2.5 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900 whitespace-nowrap" data-sort="register_date">
                            등록일 <span class="sort-icon"></span>
                        </th>
                    </tr>
                </thead>
                <tbody id="place-tbody" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">
                            <div class="spinner mx-auto mb-2"></div>
                            데이터를 불러오는 중...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="bg-white border-t border-gray-200 px-4 py-2.5 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <select id="page-size-select" class="form-select text-xs py-1 pl-2 pr-6 rounded border-gray-300">
                    <?php foreach (PAGE_SIZES as $size): ?>
                    <option value="<?= $size ?>" <?= $size === DEFAULT_PAGE_SIZE ? 'selected' : '' ?>><?= $size ?>개</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <nav id="pagination-container" class="flex items-center space-x-1">
            </nav>
            <div id="page-info" class="text-xs text-gray-500">
            </div>
        </div>
    </div>

    <!-- Right: Detail Panel -->
    <aside id="detail-panel" class="w-96 flex-shrink-0 bg-white border-l border-gray-200 overflow-y-auto hidden">
        <div id="detail-content">
            <div class="p-6 text-center text-gray-400 text-sm">
                행을 클릭하면 상세정보가 표시됩니다.
            </div>
        </div>
    </aside>
</div>

<!-- Create Modal -->
<div id="modal-create" class="modal-overlay hidden fixed inset-0 z-[100] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-6">
        <div class="modal-backdrop fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('modal-create')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl max-w-lg w-full transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">플레이스 DB 등록</h3>
                <button type="button" onclick="closeModal('modal-create')" class="text-gray-400 hover:text-gray-600 transition-colors rounded-lg p-1 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <form id="form-create" onsubmit="return false;">
                    <div class="form-group">
                        <label class="form-label">상호명 <span class="text-red-500">*</span></label>
                        <input type="text" name="company_name" class="form-input" placeholder="상호명을 입력하세요" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">연락처 <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" class="form-input" placeholder="연락처를 입력하세요" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">지역</label>
                            <input type="text" name="region" class="form-input" placeholder="예: 서울, 경기">
                        </div>
                        <div class="form-group">
                            <label class="form-label">등록일자</label>
                            <input type="date" name="register_date" class="form-input" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">알게된 경로</label>
                        <input type="text" name="source" class="form-input" placeholder="예: 네이버, 인스타그램, 소개">
                    </div>
                    <div class="form-group">
                        <label class="form-label">메모</label>
                        <textarea name="initial_memo" class="form-textarea" rows="3" placeholder="초기 메모를 입력하세요"></textarea>
                    </div>
                </form>
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-create')">취소</button>
                <button type="button" class="btn btn-primary" id="btn-submit-create" onclick="submitCreate()">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    등록
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div id="modal-assign" class="modal-overlay hidden fixed inset-0 z-[100] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-6">
        <div class="modal-backdrop fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('modal-assign')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">DB 배분</h3>
                <button type="button" onclick="closeModal('modal-assign')" class="text-gray-400 hover:text-gray-600 transition-colors rounded-lg p-1 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 mb-4">
                    선택된 <strong id="assign-count">0</strong>건을 배분할 직원을 선택하세요.
                </p>
                <div class="form-group">
                    <label class="form-label">배분 대상 직원</label>
                    <select id="assign-user-select" class="form-select">
                        <option value="">직원을 선택하세요</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-assign')">취소</button>
                <button type="button" class="btn btn-primary" onclick="submitAssign()">배분</button>
            </div>
        </div>
    </div>
</div>

<script>
// Pass server data to JS
window.PLACE_CONFIG = {
    isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
    userId: <?= (int) $currentUser['id'] ?>,
    statuses: <?= json_encode(PLACE_STATUSES, JSON_UNESCAPED_UNICODE) ?>,
    pageSizes: <?= json_encode(PAGE_SIZES) ?>,
    defaultPageSize: <?= DEFAULT_PAGE_SIZE ?>
};
</script>
<script src="/assets/js/place.js"></script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

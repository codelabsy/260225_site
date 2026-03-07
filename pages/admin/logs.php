<?php
/**
 * Activity log page.
 * Admin only. Loaded via /activity-log.php entry point.
 */

require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../models/User.php';

// Get all users for filter dropdown
$allUsers = User::all(false);
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">활동 로그</h1>
        <p class="text-sm text-gray-500 mt-1">시스템 내 모든 활동 기록을 확인할 수 있습니다.</p>
    </div>
</div>

<!-- Filters -->
<div class="card card-body mb-6">
    <form id="log-filter-form" class="filter-form" onsubmit="return false;">
        <div class="flex flex-wrap items-end gap-4">
            <div class="form-group mb-0">
                <label class="form-label">시작일</label>
                <input type="date" id="filter-from" class="form-input" style="width:160px;">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">종료일</label>
                <input type="date" id="filter-to" class="form-input" style="width:160px;">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">행위자</label>
                <select id="filter-user" class="form-select" style="width:160px;">
                    <option value="">전체</option>
                    <?php foreach ($allUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">행위 유형</label>
                <select id="filter-action" class="form-select" style="width:180px;">
                    <option value="">전체</option>
                    <?php foreach (ACTIONS as $action): ?>
                    <option value="<?= htmlspecialchars($action) ?>"><?= htmlspecialchars($action) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="loadLogs(1)" class="btn btn-primary">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    검색
                </button>
                <button type="button" onclick="resetLogFilters()" class="btn btn-outline">초기화</button>
            </div>
        </div>
    </form>
</div>

<!-- Log Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600" style="width:160px;">일시</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600" style="width:120px;">행위자</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600" style="width:150px;">행위</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600" style="width:100px;">대상</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600">설명</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 text-center" style="width:80px;">상세</th>
                </tr>
            </thead>
            <tbody id="log-table-body" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-gray-400 text-sm">
                        로딩 중...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div id="log-pagination" class="mt-4"></div>

<script src="/assets/js/admin.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initLogPage === 'function') {
        initLogPage();
    }
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

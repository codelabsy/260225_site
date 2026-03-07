<?php
/**
 * Shopping DB list page.
 * 3-column layout: left filter | center table | right detail panel
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../models/User.php';

Permission::requireLogin();

$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$employees = User::all(true);

$pageTitle = '쇼핑DB';
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="flex gap-4 h-[calc(100vh-140px)]">
    <!-- Left Sidebar: Filters -->
    <div class="w-64 flex-shrink-0 bg-white rounded-lg border border-gray-200 shadow-sm overflow-y-auto">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">필터</h3>
        </div>

        <!-- Status Filter -->
        <div class="p-4 border-b border-gray-100">
            <label class="block text-xs font-medium text-gray-500 mb-2">상태</label>
            <div class="space-y-1" id="status-filter">
                <button type="button" class="status-filter-btn active w-full flex items-center justify-between px-3 py-2 rounded-md text-sm transition-colors" data-status="">
                    <span>전체</span>
                    <span class="badge badge-secondary text-xs" id="count-all">0</span>
                </button>
                <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-2 rounded-md text-sm transition-colors" data-status="부재">
                    <span class="flex items-center"><span class="status-dot status-dot-danger"></span>부재</span>
                    <span class="badge badge-secondary text-xs" id="count-부재">0</span>
                </button>
                <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-2 rounded-md text-sm transition-colors" data-status="재통">
                    <span class="flex items-center"><span class="status-dot status-dot-warning"></span>재통</span>
                    <span class="badge badge-secondary text-xs" id="count-재통">0</span>
                </button>
                <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-2 rounded-md text-sm transition-colors" data-status="가망">
                    <span class="flex items-center"><span class="status-dot status-dot-primary"></span>가망</span>
                    <span class="badge badge-secondary text-xs" id="count-가망">0</span>
                </button>
                <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-2 rounded-md text-sm transition-colors" data-status="계약완료">
                    <span class="flex items-center"><span class="status-dot status-dot-success"></span>계약완료</span>
                    <span class="badge badge-secondary text-xs" id="count-계약완료">0</span>
                </button>
                <button type="button" class="status-filter-btn w-full flex items-center justify-between px-3 py-2 rounded-md text-sm transition-colors" data-status="안함">
                    <span class="flex items-center"><span class="status-dot status-dot-muted"></span>안함</span>
                    <span class="badge badge-secondary text-xs" id="count-안함">0</span>
                </button>
            </div>
        </div>

        <!-- Period Filter -->
        <div class="p-4 border-b border-gray-100">
            <label class="block text-xs font-medium text-gray-500 mb-2">기간</label>
            <div class="space-y-2">
                <input type="date" id="filter-period-start" class="form-input text-sm w-full" placeholder="시작일">
                <input type="date" id="filter-period-end" class="form-input text-sm w-full" placeholder="종료일">
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Assignee Filter (Admin only) -->
        <div class="p-4 border-b border-gray-100">
            <label class="block text-xs font-medium text-gray-500 mb-2">담당자</label>
            <select id="filter-user" class="form-select text-sm w-full">
                <option value="">전체</option>
                <option value="unassigned">미배정</option>
                <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Search -->
        <div class="p-4 border-b border-gray-100">
            <label class="block text-xs font-medium text-gray-500 mb-2">검색</label>
            <input type="text" id="filter-search" class="form-input text-sm w-full" placeholder="상호명, 연락처, 이름">
        </div>

        <!-- Action Buttons -->
        <div class="p-4 space-y-2">
            <button type="button" id="btn-apply-filter" class="btn btn-primary w-full text-sm">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                적용
            </button>
            <button type="button" id="btn-reset-filter" class="btn btn-outline w-full text-sm">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                초기화
            </button>
        </div>
    </div>

    <!-- Center: Table Area -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Action Bar -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-gray-900">쇼핑 DB</h2>
                <span class="text-sm text-gray-500" id="total-count-label">총 0건</span>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($isAdmin): ?>
                <button type="button" id="btn-upload" class="btn btn-primary text-sm" onclick="openModal('modal-upload')">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    엑셀 업로드
                </button>
                <button type="button" id="btn-assign" class="btn btn-outline text-sm" onclick="openAssignModal()">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    배분
                </button>
                <button type="button" id="btn-revoke" class="btn btn-outline text-sm" onclick="openRevokeModal()">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    회수
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm flex-1 overflow-hidden flex flex-col">
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left" id="shopping-table">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="w-10 px-3 py-3">
                                <input type="checkbox" class="table-check-all rounded border-gray-300 text-blue-600 focus:ring-blue-500" data-table="shopping-table">
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900" data-sort="s.company_name">
                                상호명 <span class="sort-icon"></span>
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900" data-sort="s.contact_name">
                                담당자명 <span class="sort-icon"></span>
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600">연락처</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900" data-sort="s.status">
                                상태 <span class="sort-icon"></span>
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600">배정직원</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900" data-sort="s.created_at">
                                등록일 <span class="sort-icon"></span>
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-gray-900" data-sort="s.updated_at">
                                최근활동 <span class="sort-icon"></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="shopping-tbody" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">
                                데이터를 불러오는 중...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="border-t border-gray-200 px-4 py-3" id="pagination-area">
            </div>
        </div>
    </div>

    <!-- Right: Detail Panel -->
    <div id="detail-panel" class="w-96 flex-shrink-0 bg-white rounded-lg border border-gray-200 shadow-sm overflow-y-auto hidden">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between z-10">
            <h3 class="text-sm font-semibold text-gray-900">상세 정보</h3>
            <button type="button" onclick="closeDetailPanel()" class="text-gray-400 hover:text-gray-600 transition-colors rounded-lg p-1 hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Detail Content -->
        <div id="detail-content">
            <div class="px-4 py-8 text-center text-gray-400 text-sm">
                행을 클릭하면 상세 정보가 표시됩니다.
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div id="modal-upload" class="modal-overlay hidden fixed inset-0 z-[100] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-6">
        <div class="modal-backdrop fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('modal-upload')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl max-w-lg w-full transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">엑셀(CSV) 업로드</h3>
                <button type="button" onclick="closeModal('modal-upload')" class="text-gray-400 hover:text-gray-600 transition-colors rounded-lg p-1 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <!-- Drag & Drop Area -->
                <div id="upload-dropzone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors cursor-pointer"
                     onclick="document.getElementById('upload-file-input').click()">
                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <p class="text-sm text-gray-600 mb-1">CSV 파일을 여기에 드래그하거나 클릭하여 선택</p>
                    <p class="text-xs text-gray-400">CSV 형식만 지원됩니다</p>
                    <input type="file" id="upload-file-input" accept=".csv" class="hidden">
                </div>

                <!-- Selected file name -->
                <div id="upload-file-info" class="hidden mt-3 p-3 bg-gray-50 rounded-md flex items-center justify-between">
                    <span class="text-sm text-gray-700" id="upload-file-name"></span>
                    <button type="button" onclick="clearUploadFile()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Progress -->
                <div id="upload-progress" class="hidden mt-3">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <div class="spinner"></div>
                        <span>업로드 중...</span>
                    </div>
                    <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                        <div id="upload-progress-bar" class="bg-blue-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Result -->
                <div id="upload-result" class="hidden mt-4">
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <button type="button" onclick="closeModal('modal-upload')" class="btn btn-outline text-sm">닫기</button>
                <button type="button" id="btn-do-upload" class="btn btn-primary text-sm" onclick="doUpload()">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    업로드
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
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
                <p class="text-sm text-gray-600 mb-3">
                    선택된 <strong id="assign-count">0</strong>건의 DB를 배분합니다.
                </p>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">배분할 직원</label>
                    <select id="assign-user-select" class="form-select w-full text-sm">
                        <option value="">직원을 선택하세요</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> (<?= $emp['role'] === ROLE_ADMIN ? '관리자' : '직원' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <button type="button" onclick="closeModal('modal-assign')" class="btn btn-outline text-sm">취소</button>
                <button type="button" id="btn-do-assign" class="btn btn-primary text-sm" onclick="doAssign()">배분</button>
            </div>
        </div>
    </div>
</div>

<!-- Revoke Modal -->
<div id="modal-revoke" class="modal-overlay hidden fixed inset-0 z-[100] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-6">
        <div class="modal-backdrop fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('modal-revoke')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">DB 회수</h3>
                <button type="button" onclick="closeModal('modal-revoke')" class="text-gray-400 hover:text-gray-600 transition-colors rounded-lg p-1 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <p class="text-sm text-gray-600">
                    선택된 <strong id="revoke-count">0</strong>건의 DB를 회수하시겠습니까?
                </p>
                <p class="text-xs text-gray-400 mt-2">회수된 DB는 미배정 상태가 됩니다.</p>
            </div>
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <button type="button" onclick="closeModal('modal-revoke')" class="btn btn-outline text-sm">취소</button>
                <button type="button" id="btn-do-revoke" class="btn btn-danger text-sm" onclick="doRevoke()">회수</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Pass server-side data to JS
    window.SHOPPING_CONFIG = {
        isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
        currentUserId: <?= $currentUser['id'] ?>,
        statuses: <?= json_encode(SHOPPING_STATUSES, JSON_UNESCAPED_UNICODE) ?>,
    };
</script>
<script src="/assets/js/shopping.js"></script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

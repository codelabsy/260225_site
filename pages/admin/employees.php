<?php
/**
 * Employee management page.
 * Admin only. Loaded via /employees.php entry point.
 */

require_once __DIR__ . '/../../templates/header.php';
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">직원 관리</h1>
        <p class="text-sm text-gray-500 mt-1">직원 계정을 생성, 수정, 관리할 수 있습니다.</p>
    </div>
    <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" id="show-inactive" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>비활성 직원 표시</span>
        </label>
        <button onclick="openCreateModal()" class="btn btn-primary">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            직원 추가
        </button>
    </div>
</div>

<!-- Employee Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6" id="employee-stats">
    <div class="card card-body">
        <div class="text-sm text-gray-500">전체 직원</div>
        <div class="text-2xl font-bold text-gray-900 mt-1" id="stat-total">0</div>
    </div>
    <div class="card card-body">
        <div class="text-sm text-gray-500">활성 직원</div>
        <div class="text-2xl font-bold text-green-600 mt-1" id="stat-active">0</div>
    </div>
    <div class="card card-body">
        <div class="text-sm text-gray-500">비활성 직원</div>
        <div class="text-2xl font-bold text-red-500 mt-1" id="stat-inactive">0</div>
    </div>
</div>

<!-- Employee Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600">이름</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600">아이디</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600">직급</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600">연락처</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600">이메일</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 text-right">인센티브%</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 text-center">상태</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600">가입일</th>
                    <th class="px-4 py-3 text-sm font-semibold text-gray-600 text-center">관리</th>
                </tr>
            </thead>
            <tbody id="employee-table-body" class="divide-y divide-gray-100">
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-gray-400 text-sm">
                        로딩 중...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Employee Modal -->
<div id="modal-create" class="modal-overlay hidden fixed inset-0 z-[100] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-6">
        <div class="modal-backdrop fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('modal-create')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl max-w-lg w-full transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">직원 추가</h3>
                <button type="button" onclick="closeModal('modal-create')" class="text-gray-400 hover:text-gray-600 transition-colors rounded-lg p-1 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="form-create" onsubmit="return handleCreate(event)">
                <div class="px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">아이디 <span class="text-red-500">*</span></label>
                            <input type="text" name="username" class="form-input" placeholder="4~20자 영문/숫자" required>
                            <p class="form-helper">4~20자 영문/숫자만 가능</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label">비밀번호 <span class="text-red-500">*</span></label>
                            <input type="password" name="password" class="form-input" placeholder="8자 이상" required>
                            <p class="form-helper">8자 이상</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">이름 <span class="text-red-500">*</span></label>
                            <input type="text" name="name" class="form-input" placeholder="이름 입력" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">직급</label>
                            <input type="text" name="position" class="form-input" placeholder="예: 사원, 팀장">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">연락처</label>
                            <input type="text" name="phone" class="form-input" placeholder="010-0000-0000">
                        </div>
                        <div class="form-group">
                            <label class="form-label">이메일</label>
                            <input type="email" name="email" class="form-input" placeholder="email@example.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">인센티브 비율 (%)</label>
                        <input type="number" name="incentive_rate" class="form-input" placeholder="0" min="0" max="100" step="0.1" value="0">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                    <button type="button" onclick="closeModal('modal-create')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-primary" id="btn-create-submit">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        생성
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div id="modal-edit" class="modal-overlay hidden fixed inset-0 z-[100] overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-6">
        <div class="modal-backdrop fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('modal-edit')"></div>
        <div class="modal-content relative bg-white rounded-lg shadow-xl max-w-lg w-full transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">직원 수정</h3>
                <button type="button" onclick="closeModal('modal-edit')" class="text-gray-400 hover:text-gray-600 transition-colors rounded-lg p-1 hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="form-edit" onsubmit="return handleUpdate(event)">
                <input type="hidden" name="id" id="edit-id">
                <div class="px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">아이디</label>
                            <input type="text" id="edit-username" class="form-input bg-gray-100" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">비밀번호</label>
                            <input type="password" name="password" class="form-input" placeholder="변경 시에만 입력">
                            <p class="form-helper">비워두면 기존 비밀번호 유지</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">이름 <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="edit-name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">직급</label>
                            <input type="text" name="position" id="edit-position" class="form-input">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">연락처</label>
                            <input type="text" name="phone" id="edit-phone" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">이메일</label>
                            <input type="email" name="email" id="edit-email" class="form-input">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-group">
                            <label class="form-label">인센티브 비율 (%)</label>
                            <input type="number" name="incentive_rate" id="edit-incentive" class="form-input" min="0" max="100" step="0.1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">상태</label>
                            <select name="is_active" id="edit-is-active" class="form-select">
                                <option value="1">활성</option>
                                <option value="0">비활성</option>
                            </select>
                        </div>
                    </div>

                    <!-- Employee Detail Info -->
                    <div id="edit-detail-info" class="hidden">
                        <div class="border-t border-gray-200 pt-4 mt-2">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">담당 현황</h4>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="bg-blue-50 rounded-lg p-3 text-center">
                                    <div class="text-xs text-gray-500">담당 매출</div>
                                    <div class="text-sm font-bold text-blue-600 mt-1" id="detail-sales-count">0건</div>
                                </div>
                                <div class="bg-green-50 rounded-lg p-3 text-center">
                                    <div class="text-xs text-gray-500">쇼핑DB</div>
                                    <div class="text-sm font-bold text-green-600 mt-1" id="detail-shopping-count">0건</div>
                                </div>
                                <div class="bg-purple-50 rounded-lg p-3 text-center">
                                    <div class="text-xs text-gray-500">플레이스DB</div>
                                    <div class="text-sm font-bold text-purple-600 mt-1" id="detail-place-count">0건</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                    <button type="button" onclick="closeModal('modal-edit')" class="btn btn-outline">취소</button>
                    <button type="submit" class="btn btn-primary" id="btn-edit-submit">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        저장
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/admin.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initEmployeePage === 'function') {
        initEmployeePage();
    }
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

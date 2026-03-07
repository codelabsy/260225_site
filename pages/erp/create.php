<?php
/**
 * ERP Sales Management - Create/Edit Page.
 * Admin only.
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../models/User.php';

Permission::requireAdmin();

$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$pageTitle = '업체 등록';
$currentPage = 'erp';

// Get employees for assignee dropdown
$users = User::all();

include __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="/erp.php" class="btn btn-ghost btn-icon">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900">업체 등록</h1>
        </div>
    </div>

    <form id="erp-create-form" onsubmit="return ERP.submitCreateForm(event)">
        <!-- Basic Info Section -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-4">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700 flex items-center">
                    <svg class="w-4 h-4 mr-1.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    기본 정보
                </h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">담당자</label>
                        <select name="user_id" id="field-user-id" class="form-select">
                            <option value="">선택하세요</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">등록일 <span class="text-red-500">*</span></label>
                        <input type="date" name="register_date" id="field-register-date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">상품명 <span class="text-red-500">*</span></label>
                        <input type="text" name="product_name" id="field-product-name" class="form-input" placeholder="상품명을 입력하세요" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">상호명 <span class="text-red-500">*</span></label>
                        <input type="text" name="company_name" id="field-company-name" class="form-input" placeholder="상호명을 입력하세요" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">결제금액</label>
                        <input type="text" name="payment_amount" id="field-payment-amount" class="form-input text-right" placeholder="0" oninput="ERP.calcAmounts()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">계산서발행금액</label>
                        <input type="text" name="invoice_amount" id="field-invoice-amount" class="form-input text-right" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">실행비</label>
                        <input type="text" name="execution_cost" id="field-execution-cost" class="form-input text-right" placeholder="0" oninput="ERP.calcAmounts()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">직위</label>
                        <input type="text" name="registrant_position" id="field-registrant-position" class="form-input" placeholder="직위">
                    </div>
                </div>

                <!-- Calculated Fields -->
                <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-100">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-xs text-gray-500">부가세 (자동계산)</span>
                            <div id="calc-vat" class="text-lg font-bold text-purple-600">0</div>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">순마진 (자동계산)</span>
                            <div id="calc-margin" class="text-lg font-bold text-green-600">0</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Info Section -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-4">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-700 flex items-center">
                    <svg class="w-4 h-4 mr-1.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    상세 정보
                </h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">영업등록일</label>
                        <input type="date" name="sales_register_date" id="field-sales-register-date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">결제구분</label>
                        <select name="payment_type" id="field-payment-type" class="form-select">
                            <option value="">선택하세요</option>
                            <option value="카드">카드</option>
                            <option value="현금">현금</option>
                            <option value="계좌이체">계좌이체</option>
                            <option value="기타">기타</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">작업 시작일</label>
                        <input type="date" name="work_start_date" id="field-work-start-date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">작업 종료일</label>
                        <input type="date" name="work_end_date" id="field-work-end-date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">계약 시작일</label>
                        <input type="date" name="contract_start" id="field-contract-start" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">계약 종료일</label>
                        <input type="date" name="contract_end" id="field-contract-end" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">사업자명</label>
                        <input type="text" name="business_name" id="field-business-name" class="form-input" placeholder="사업자명">
                    </div>
                    <div class="form-group">
                        <label class="form-label">대표자</label>
                        <input type="text" name="ceo_name" id="field-ceo-name" class="form-input" placeholder="대표자명">
                    </div>
                    <div class="form-group">
                        <label class="form-label">연락처</label>
                        <input type="text" name="phone" id="field-phone" class="form-input" placeholder="010-0000-0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">사업자번호</label>
                        <input type="text" name="business_number" id="field-business-number" class="form-input" placeholder="000-00-00000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">이메일</label>
                        <input type="email" name="email" id="field-email" class="form-input" placeholder="example@email.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">네이버 계정</label>
                        <input type="text" name="naver_account" id="field-naver-account" class="form-input" placeholder="네이버 계정">
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">작업 키워드</label>
                        <input type="text" name="work_keywords" id="field-work-keywords" class="form-input" placeholder="키워드를 입력하세요">
                    </div>
                    <div class="form-group col-span-2">
                        <label class="form-label">작업 내용</label>
                        <textarea name="work_content" id="field-work-content" class="form-textarea" rows="3" placeholder="작업 내용을 입력하세요"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">상세 실행비</label>
                        <input type="text" name="detail_execution_cost" id="field-detail-execution-cost" class="form-input text-right" placeholder="0">
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center justify-end gap-3 mb-8">
            <a href="/erp.php" class="btn btn-outline">취소</a>
            <button type="submit" id="btn-submit" class="btn btn-primary btn-lg">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                등록하기
            </button>
        </div>
    </form>
</div>

<script src="/assets/js/erp.js"></script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

/**
 * ERP Sales Management - JavaScript Module
 */

(function () {
    'use strict';

    const ERP = {};

    // State
    let currentPage = 1;
    let currentPageSize = 50;
    let currentSort = 'register_date';
    let currentOrder = 'DESC';
    let currentDetailId = null;

    /* ======================================================================
       List: Load & Render
       ====================================================================== */

    ERP.loadList = function () {
        const params = new URLSearchParams();
        params.set('page', currentPage);
        params.set('page_size', currentPageSize);
        params.set('sort', currentSort);
        params.set('order', currentOrder);

        // Filters
        const periodStart = document.getElementById('filter-period-start');
        const periodEnd = document.getElementById('filter-period-end');
        const paymentType = document.getElementById('filter-payment-type');
        const search = document.getElementById('filter-search');
        const userId = document.getElementById('filter-user');

        if (periodStart && periodStart.value) params.set('period_start', periodStart.value);
        if (periodEnd && periodEnd.value) params.set('period_end', periodEnd.value);
        if (paymentType && paymentType.value) params.set('payment_type', paymentType.value);
        if (search && search.value) params.set('search', search.value);
        if (userId && userId.value) params.set('user_id', userId.value);

        apiRequest('/api/erp/list.php?' + params.toString(), 'GET')
            .then(function (res) {
                if (res.success) {
                    renderTable(res.data.items);
                    renderSummary(res.data.summary);
                    renderPagination(res.data.page, res.data.total, res.data.page_size);
                } else {
                    showToast(res.message || '데이터를 불러오는데 실패했습니다.', 'error');
                }
            })
            .catch(function () {
                showToast('데이터를 불러오는 중 오류가 발생했습니다.', 'error');
            });
    };

    function renderTable(items) {
        var tbody = document.getElementById('erp-table-body');
        if (!tbody) return;

        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-12 text-center text-gray-400 text-sm">데이터가 없습니다.</td></tr>';
            return;
        }

        var html = '';
        items.forEach(function (item, idx) {
            var netMarginClass = (parseFloat(item.net_margin) || 0) >= 0 ? 'text-green-600' : 'text-red-600';
            html += '<tr class="' + (idx % 2 === 1 ? 'bg-gray-50/50' : 'bg-white') + ' hover:bg-blue-50/50 transition-colors cursor-pointer" data-id="' + item.id + '" onclick="ERP.openDetail(' + item.id + ')">';
            html += '<td class="px-3 py-2.5 text-xs text-gray-700 whitespace-nowrap">' + escapeHtml(item.register_date || '') + '</td>';
            html += '<td class="px-3 py-2.5 text-xs text-gray-700 whitespace-nowrap text-truncate max-w-[120px]">' + escapeHtml(item.product_name || '') + '</td>';
            html += '<td class="px-3 py-2.5 text-xs text-gray-900 font-medium whitespace-nowrap text-truncate max-w-[140px]">' + escapeHtml(item.company_name || '') + '</td>';
            html += '<td class="px-3 py-2.5 text-xs text-gray-600 whitespace-nowrap">' + escapeHtml(item.user_name || '-') + '</td>';
            html += '<td class="px-3 py-2.5 text-xs text-gray-700 text-right whitespace-nowrap">' + formatNumber(item.payment_amount) + '</td>';
            html += '<td class="px-3 py-2.5 text-xs text-gray-700 text-right whitespace-nowrap">' + formatNumber(item.invoice_amount) + '</td>';
            html += '<td class="px-3 py-2.5 text-xs text-gray-700 text-right whitespace-nowrap">' + formatNumber(item.execution_cost) + '</td>';
            html += '<td class="px-3 py-2.5 text-xs text-gray-700 text-right whitespace-nowrap">' + formatNumber(item.vat) + '</td>';
            html += '<td class="px-3 py-2.5 text-xs font-medium text-right whitespace-nowrap ' + netMarginClass + '">' + formatNumber(item.net_margin) + '</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
    }

    function renderSummary(summary) {
        setTextContent('summary-payment', formatNumber(summary.total_payment));
        setTextContent('summary-invoice', formatNumber(summary.total_invoice));
        setTextContent('summary-execution', formatNumber(summary.total_execution_cost));
        setTextContent('summary-vat', formatNumber(summary.total_vat));
        setTextContent('summary-margin', formatNumber(summary.total_net_margin));
        setTextContent('total-count', summary.total_count || 0);
    }

    function renderPagination(page, total, pageSize) {
        var totalPages = Math.max(1, Math.ceil(total / pageSize));
        var nav = document.getElementById('pagination-nav');
        var info = document.getElementById('pagination-info');

        if (info) {
            info.textContent = page + ' / ' + totalPages + ' 페이지';
        }

        if (!nav) return;

        var html = '';

        // Prev
        html += '<button class="pagination-btn ' + (page <= 1 ? 'pagination-btn-disabled' : '') + '" onclick="ERP.goToPage(' + (page - 1) + ')" ' + (page <= 1 ? 'disabled' : '') + '>';
        html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>';
        html += '</button>';

        // Page numbers
        var range = 2;
        var startPage = Math.max(1, page - range);
        var endPage = Math.min(totalPages, page + range);

        if (startPage > 1) {
            html += '<button class="pagination-btn" onclick="ERP.goToPage(1)">1</button>';
            if (startPage > 2) html += '<span class="pagination-ellipsis">...</span>';
        }

        for (var i = startPage; i <= endPage; i++) {
            html += '<button class="pagination-btn ' + (i === page ? 'pagination-btn-active' : '') + '" onclick="ERP.goToPage(' + i + ')">' + i + '</button>';
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += '<span class="pagination-ellipsis">...</span>';
            html += '<button class="pagination-btn" onclick="ERP.goToPage(' + totalPages + ')">' + totalPages + '</button>';
        }

        // Next
        html += '<button class="pagination-btn ' + (page >= totalPages ? 'pagination-btn-disabled' : '') + '" onclick="ERP.goToPage(' + (page + 1) + ')" ' + (page >= totalPages ? 'disabled' : '') + '>';
        html += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
        html += '</button>';

        nav.innerHTML = html;
    }

    /* ======================================================================
       Filters
       ====================================================================== */

    ERP.applyFilters = function () {
        currentPage = 1;
        ERP.loadList();
    };

    ERP.resetFilters = function () {
        var fields = ['filter-period-start', 'filter-period-end', 'filter-payment-type', 'filter-search', 'filter-user'];
        fields.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        currentPage = 1;
        currentSort = 'register_date';
        currentOrder = 'DESC';
        ERP.loadList();
    };

    ERP.goToPage = function (page) {
        currentPage = page;
        ERP.loadList();
    };

    ERP.changePageSize = function (size) {
        currentPageSize = parseInt(size, 10);
        currentPage = 1;
        ERP.loadList();
    };

    /* ======================================================================
       Sorting
       ====================================================================== */

    function initSortHeaders() {
        var headers = document.querySelectorAll('#erp-table th[data-sort]');
        headers.forEach(function (th) {
            th.addEventListener('click', function () {
                var sortKey = th.dataset.sort;
                if (currentSort === sortKey) {
                    currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    currentSort = sortKey;
                    currentOrder = 'DESC';
                }
                currentPage = 1;
                ERP.loadList();
            });
        });
    }

    /* ======================================================================
       Detail Panel
       ====================================================================== */

    ERP.openDetail = function (id) {
        currentDetailId = id;
        var panel = document.getElementById('detail-panel');
        var backdrop = document.getElementById('detail-backdrop');
        if (panel) panel.classList.add('open');
        if (backdrop) backdrop.classList.add('open');

        var content = document.getElementById('detail-content');
        if (content) {
            content.innerHTML = '<div class="text-center py-12"><div class="spinner mx-auto mb-2"></div><span class="text-sm text-gray-400">불러오는 중...</span></div>';
        }

        apiRequest('/api/erp/detail.php?id=' + id, 'GET')
            .then(function (res) {
                if (res.success) {
                    renderDetail(res.data.company, res.data.memos);
                } else {
                    content.innerHTML = '<div class="text-center text-red-500 py-12">' + escapeHtml(res.message || '오류 발생') + '</div>';
                }
            })
            .catch(function () {
                content.innerHTML = '<div class="text-center text-red-500 py-12">데이터를 불러오는 중 오류가 발생했습니다.</div>';
            });
    };

    ERP.closeDetail = function () {
        var panel = document.getElementById('detail-panel');
        var backdrop = document.getElementById('detail-backdrop');
        if (panel) panel.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
        currentDetailId = null;
    };

    function renderDetail(company, memos) {
        var content = document.getElementById('detail-content');
        if (!content) return;

        var title = document.getElementById('detail-title');
        if (title) title.textContent = company.company_name || '업체 상세정보';

        var netMarginClass = (parseFloat(company.net_margin) || 0) >= 0 ? 'text-green-600' : 'text-red-600';

        var html = '';

        // Basic Info
        html += '<div class="mb-6">';
        html += '<h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">';
        html += '<svg class="w-4 h-4 mr-1.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        html += '기본 정보</h4>';
        html += '<div class="bg-gray-50 rounded-lg p-4 space-y-2">';
        html += infoRow('등록일', company.register_date);
        html += infoRow('상품명', company.product_name);
        html += infoRow('상호명', company.company_name);
        html += infoRow('담당자', company.user_name || '-');
        html += infoRow('직위', company.registrant_position);
        html += '</div>';
        html += '</div>';

        // Financial Info
        html += '<div class="mb-6">';
        html += '<h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">';
        html += '<svg class="w-4 h-4 mr-1.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        html += '금액 정보</h4>';
        html += '<div class="grid grid-cols-2 gap-3">';
        html += amountCard('결제금액', company.payment_amount, 'text-gray-900');
        html += amountCard('계산서발행', company.invoice_amount, 'text-blue-600');
        html += amountCard('실행비', company.execution_cost, 'text-orange-600');
        html += amountCard('상세실행비', company.detail_execution_cost, 'text-orange-500');
        html += amountCard('부가세', company.vat, 'text-purple-600');
        html += amountCard('순마진', company.net_margin, netMarginClass);
        html += '</div>';
        html += '</div>';

        // Detail Info
        html += '<div class="mb-6">';
        html += '<h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">';
        html += '<svg class="w-4 h-4 mr-1.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>';
        html += '상세 정보</h4>';
        html += '<div class="bg-gray-50 rounded-lg p-4 space-y-2">';
        html += infoRow('영업등록일', company.sales_register_date);
        html += infoRow('결제구분', company.payment_type);
        html += infoRow('작업기간', formatDateRange(company.work_start_date, company.work_end_date));
        html += infoRow('계약기간', formatDateRange(company.contract_start, company.contract_end));
        html += infoRow('사업자명', company.business_name);
        html += infoRow('대표자', company.ceo_name);
        html += infoRow('연락처', company.phone);
        html += infoRow('사업자번호', company.business_number);
        html += infoRow('이메일', company.email);
        html += infoRow('네이버 계정', company.naver_account);
        html += infoRow('작업 키워드', company.work_keywords);
        if (company.work_content) {
            html += '<div class="pt-2">';
            html += '<span class="text-xs text-gray-500">작업 내용</span>';
            html += '<p class="text-sm text-gray-700 mt-1 whitespace-pre-wrap">' + escapeHtml(company.work_content) + '</p>';
            html += '</div>';
        }
        html += '</div>';
        html += '</div>';

        // Carried from info
        if (company.carried_from_id) {
            html += '<div class="mb-6 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">';
            html += '<div class="flex items-center text-xs text-yellow-700">';
            html += '<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            html += '이월 원본 ID: ' + company.carried_from_id;
            html += '</div>';
            html += '</div>';
        }

        // Memo Section
        html += '<div class="mb-6">';
        html += '<h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">';
        html += '<svg class="w-4 h-4 mr-1.5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>';
        html += '메모</h4>';

        // Memo Input
        html += '<div class="flex gap-2 mb-3">';
        html += '<textarea id="detail-memo-input" class="form-textarea w-full text-sm py-2 px-3 resize-none" rows="2" placeholder="메모를 입력하세요..."></textarea>';
        html += '<button type="button" class="btn btn-primary self-end text-sm py-2 px-4 whitespace-nowrap flex-shrink-0" onclick="ERP.saveMemo()">';
        html += '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>저장</button>';
        html += '</div>';

        // Memo List
        html += '<div id="detail-memo-list" class="border border-gray-200 rounded-lg divide-y divide-gray-100 max-h-60 overflow-y-auto">';
        if (!memos || memos.length === 0) {
            html += '<div class="px-4 py-6 text-center text-gray-400 text-sm memo-empty">등록된 메모가 없습니다.</div>';
        } else {
            memos.forEach(function (memo) {
                html += memoItem(memo);
            });
        }
        html += '</div>';
        html += '</div>';

        content.innerHTML = html;
    }

    function infoRow(label, value) {
        return '<div class="flex items-center justify-between">' +
            '<span class="text-xs text-gray-500">' + escapeHtml(label) + '</span>' +
            '<span class="text-sm text-gray-700">' + escapeHtml(value || '-') + '</span>' +
            '</div>';
    }

    function amountCard(label, value, colorClass) {
        return '<div class="bg-white border border-gray-200 rounded-lg p-3">' +
            '<div class="text-xs text-gray-500 mb-1">' + escapeHtml(label) + '</div>' +
            '<div class="text-sm font-bold ' + colorClass + '">' + formatNumber(value) + '</div>' +
            '</div>';
    }

    function memoItem(memo) {
        return '<div class="memo-item px-4 py-3 hover:bg-gray-50/50 transition-colors">' +
            '<div class="flex items-center justify-between mb-1">' +
            '<span class="text-xs font-medium text-gray-700">' + escapeHtml(memo.user_name || '') + '</span>' +
            '<span class="text-xs text-gray-400">' + escapeHtml(memo.created_at || '') + '</span>' +
            '</div>' +
            '<p class="text-sm text-gray-600 whitespace-pre-wrap">' + escapeHtml(memo.content || '') + '</p>' +
            '</div>';
    }

    function formatDateRange(start, end) {
        if (!start && !end) return '-';
        return (start || '?') + ' ~ ' + (end || '?');
    }

    /* ======================================================================
       Memo
       ====================================================================== */

    ERP.saveMemo = function () {
        if (!currentDetailId) return;
        var input = document.getElementById('detail-memo-input');
        var content = input ? input.value.trim() : '';

        if (!content) {
            showToast('메모 내용을 입력하세요.', 'error');
            return;
        }

        apiRequest('/api/erp/memo.php', 'POST', {
            target_id: currentDetailId,
            content: content
        }).then(function (res) {
            if (res.success) {
                input.value = '';
                showToast('메모가 저장되었습니다.', 'success');

                var list = document.getElementById('detail-memo-list');
                var emptyEl = list ? list.querySelector('.memo-empty') : null;
                if (emptyEl) emptyEl.remove();

                if (list) {
                    list.insertAdjacentHTML('afterbegin', memoItem(res.data));
                }
            } else {
                showToast(res.message || '메모 저장에 실패했습니다.', 'error');
            }
        }).catch(function () {
            showToast('메모 저장 중 오류가 발생했습니다.', 'error');
        });
    };

    /* ======================================================================
       Edit Mode (inline in detail panel)
       ====================================================================== */

    ERP.openEditMode = function () {
        if (!currentDetailId) return;

        apiRequest('/api/erp/detail.php?id=' + currentDetailId, 'GET')
            .then(function (res) {
                if (res.success) {
                    renderEditForm(res.data.company);
                }
            })
            .catch(function (err) {
                showToast(err.message || '오류가 발생했습니다.', 'error');
            });
    };

    function renderEditForm(company) {
        var content = document.getElementById('detail-content');
        if (!content) return;

        var title = document.getElementById('detail-title');
        if (title) title.textContent = '업체 수정';

        var config = window.ERP_CONFIG || {};
        var users = config.users || [];

        var html = '<form id="edit-form" onsubmit="return ERP.submitEditForm(event)">';
        html += '<input type="hidden" name="id" value="' + company.id + '">';

        // Basic fields
        html += '<div class="space-y-3 mb-6">';
        html += '<h4 class="text-sm font-semibold text-gray-700">기본 정보</h4>';

        html += formField('담당자', 'select', 'edit-user-id', company.user_id, users);
        html += formField('등록일', 'date', 'edit-register-date', company.register_date);
        html += formField('상품명', 'text', 'edit-product-name', company.product_name);
        html += formField('상호명', 'text', 'edit-company-name', company.company_name);
        html += formField('결제금액', 'number', 'edit-payment-amount', company.payment_amount);
        html += formField('계산서발행금액', 'number', 'edit-invoice-amount', company.invoice_amount);
        html += formField('실행비', 'number', 'edit-execution-cost', company.execution_cost);
        html += formField('직위', 'text', 'edit-registrant-position', company.registrant_position);
        html += '</div>';

        // Detail fields
        html += '<div class="space-y-3 mb-6">';
        html += '<h4 class="text-sm font-semibold text-gray-700">상세 정보</h4>';

        html += formField('영업등록일', 'date', 'edit-sales-register-date', company.sales_register_date);
        html += formField('결제구분', 'select-payment', 'edit-payment-type', company.payment_type);
        html += formField('작업 시작일', 'date', 'edit-work-start-date', company.work_start_date);
        html += formField('작업 종료일', 'date', 'edit-work-end-date', company.work_end_date);
        html += formField('계약 시작일', 'date', 'edit-contract-start', company.contract_start);
        html += formField('계약 종료일', 'date', 'edit-contract-end', company.contract_end);
        html += formField('사업자명', 'text', 'edit-business-name', company.business_name);
        html += formField('대표자', 'text', 'edit-ceo-name', company.ceo_name);
        html += formField('연락처', 'text', 'edit-phone', company.phone);
        html += formField('사업자번호', 'text', 'edit-business-number', company.business_number);
        html += formField('이메일', 'email', 'edit-email', company.email);
        html += formField('네이버 계정', 'text', 'edit-naver-account', company.naver_account);
        html += formField('작업 키워드', 'text', 'edit-work-keywords', company.work_keywords);
        html += formField('작업 내용', 'textarea', 'edit-work-content', company.work_content);
        html += formField('상세 실행비', 'number', 'edit-detail-execution-cost', company.detail_execution_cost);
        html += '</div>';

        // Submit buttons
        html += '<div class="flex gap-2 sticky bottom-0 bg-white py-3 border-t border-gray-200">';
        html += '<button type="button" class="btn btn-outline flex-1" onclick="ERP.openDetail(' + company.id + ')">취소</button>';
        html += '<button type="submit" class="btn btn-primary flex-1">저장</button>';
        html += '</div>';

        html += '</form>';
        content.innerHTML = html;
    }

    function formField(label, type, id, value, options) {
        var val = value || '';
        var html = '<div>';
        html += '<label class="form-label text-xs">' + escapeHtml(label) + '</label>';

        if (type === 'select' && options) {
            html += '<select id="' + id + '" class="form-select text-sm py-1.5">';
            html += '<option value="">선택하세요</option>';
            options.forEach(function (opt) {
                html += '<option value="' + opt.id + '"' + (String(opt.id) === String(val) ? ' selected' : '') + '>' + escapeHtml(opt.name) + '</option>';
            });
            html += '</select>';
        } else if (type === 'select-payment') {
            html += '<select id="' + id + '" class="form-select text-sm py-1.5">';
            html += '<option value="">선택하세요</option>';
            ['카드', '현금', '계좌이체', '기타'].forEach(function (t) {
                html += '<option value="' + t + '"' + (t === val ? ' selected' : '') + '>' + t + '</option>';
            });
            html += '</select>';
        } else if (type === 'textarea') {
            html += '<textarea id="' + id + '" class="form-textarea text-sm py-1.5" rows="3">' + escapeHtml(val) + '</textarea>';
        } else if (type === 'number') {
            html += '<input type="text" id="' + id + '" class="form-input text-sm py-1.5 text-right" value="' + formatNumber(val) + '" oninput="ERP.formatAmountInput(this)">';
        } else {
            html += '<input type="' + type + '" id="' + id + '" class="form-input text-sm py-1.5" value="' + escapeHtml(val) + '">';
        }

        html += '</div>';
        return html;
    }

    ERP.submitEditForm = function (e) {
        e.preventDefault();

        var data = {
            id: currentDetailId,
            user_id: getVal('edit-user-id'),
            register_date: getVal('edit-register-date'),
            product_name: getVal('edit-product-name'),
            company_name: getVal('edit-company-name'),
            payment_amount: parseAmount(getVal('edit-payment-amount')),
            invoice_amount: parseAmount(getVal('edit-invoice-amount')),
            execution_cost: parseAmount(getVal('edit-execution-cost')),
            registrant_position: getVal('edit-registrant-position'),
            sales_register_date: getVal('edit-sales-register-date'),
            payment_type: getVal('edit-payment-type'),
            work_start_date: getVal('edit-work-start-date'),
            work_end_date: getVal('edit-work-end-date'),
            contract_start: getVal('edit-contract-start'),
            contract_end: getVal('edit-contract-end'),
            business_name: getVal('edit-business-name'),
            ceo_name: getVal('edit-ceo-name'),
            phone: getVal('edit-phone'),
            business_number: getVal('edit-business-number'),
            email: getVal('edit-email'),
            naver_account: getVal('edit-naver-account'),
            work_keywords: getVal('edit-work-keywords'),
            work_content: getVal('edit-work-content'),
            detail_execution_cost: parseAmount(getVal('edit-detail-execution-cost')),
        };

        apiRequest('/api/erp/update.php', 'POST', data)
            .then(function (res) {
                if (res.success) {
                    showToast('업체 정보가 수정되었습니다.', 'success');
                    ERP.openDetail(currentDetailId);
                    ERP.loadList();
                } else {
                    showToast(res.message || '수정에 실패했습니다.', 'error');
                }
            })
            .catch(function () {
                showToast('수정 중 오류가 발생했습니다.', 'error');
            });

        return false;
    };

    /* ======================================================================
       Carry Over
       ====================================================================== */

    ERP.carryOver = function () {
        if (!currentDetailId) return;

        confirmAction('이 업체를 이월 처리하시겠습니까?\n새로운 업체 레코드가 생성됩니다.').then(function (confirmed) {
            if (!confirmed) return;

            apiRequest('/api/erp/carry-over.php', 'POST', {
                id: currentDetailId
            }).then(function (res) {
                if (res.success) {
                    showToast('이월 처리가 완료되었습니다. (새 ID: ' + res.data.new_id + ')', 'success');
                    ERP.loadList();
                    ERP.openDetail(res.data.new_id);
                } else {
                    showToast(res.message || '이월 처리에 실패했습니다.', 'error');
                }
            }).catch(function () {
                showToast('이월 처리 중 오류가 발생했습니다.', 'error');
            });
        });
    };

    /* ======================================================================
       Create Form
       ====================================================================== */

    ERP.submitCreateForm = function (e) {
        e.preventDefault();

        var data = {
            user_id: getVal('field-user-id'),
            register_date: getVal('field-register-date'),
            product_name: getVal('field-product-name'),
            company_name: getVal('field-company-name'),
            payment_amount: parseAmount(getVal('field-payment-amount')),
            invoice_amount: parseAmount(getVal('field-invoice-amount')),
            execution_cost: parseAmount(getVal('field-execution-cost')),
            registrant_position: getVal('field-registrant-position'),
            sales_register_date: getVal('field-sales-register-date'),
            payment_type: getVal('field-payment-type'),
            work_start_date: getVal('field-work-start-date'),
            work_end_date: getVal('field-work-end-date'),
            contract_start: getVal('field-contract-start'),
            contract_end: getVal('field-contract-end'),
            business_name: getVal('field-business-name'),
            ceo_name: getVal('field-ceo-name'),
            phone: getVal('field-phone'),
            business_number: getVal('field-business-number'),
            email: getVal('field-email'),
            naver_account: getVal('field-naver-account'),
            work_keywords: getVal('field-work-keywords'),
            work_content: getVal('field-work-content'),
            detail_execution_cost: parseAmount(getVal('field-detail-execution-cost')),
        };

        // Validate required
        if (!data.register_date) { showToast('등록일을 입력하세요.', 'error'); return false; }
        if (!data.product_name) { showToast('상품명을 입력하세요.', 'error'); return false; }
        if (!data.company_name) { showToast('상호명을 입력하세요.', 'error'); return false; }

        var btn = document.getElementById('btn-submit');
        if (btn) btn.disabled = true;

        apiRequest('/api/erp/create.php', 'POST', data)
            .then(function (res) {
                if (res.success) {
                    showToast('업체가 등록되었습니다.', 'success');
                    setTimeout(function () {
                        window.location.href = '/erp.php';
                    }, 1000);
                } else {
                    showToast(res.message || '등록에 실패했습니다.', 'error');
                    if (btn) btn.disabled = false;
                }
            })
            .catch(function () {
                showToast('등록 중 오류가 발생했습니다.', 'error');
                if (btn) btn.disabled = false;
            });

        return false;
    };

    /* ======================================================================
       Amount Calculation (Real-time)
       ====================================================================== */

    ERP.calcAmounts = function () {
        var paymentEl = document.getElementById('field-payment-amount');
        var executionEl = document.getElementById('field-execution-cost');
        var vatEl = document.getElementById('calc-vat');
        var marginEl = document.getElementById('calc-margin');

        if (!paymentEl || !vatEl || !marginEl) return;

        var payment = parseAmount(paymentEl.value);
        var execution = parseAmount(executionEl ? executionEl.value : '0');
        var vat = Math.round(payment / 11);
        var margin = payment - execution - vat;

        vatEl.textContent = formatNumber(vat);
        marginEl.textContent = formatNumber(margin);

        if (margin >= 0) {
            marginEl.className = 'text-lg font-bold text-green-600';
        } else {
            marginEl.className = 'text-lg font-bold text-red-600';
        }
    };

    ERP.formatAmountInput = function (input) {
        var value = input.value.replace(/[^\d\-]/g, '');
        var num = parseInt(value, 10);
        if (!isNaN(num)) {
            input.value = num.toLocaleString('ko-KR');
        } else if (value === '' || value === '-') {
            input.value = value;
        }
    };

    /* ======================================================================
       Utility Helpers
       ====================================================================== */

    function getVal(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function parseAmount(str) {
        if (!str) return 0;
        var cleaned = String(str).replace(/,/g, '');
        var num = parseFloat(cleaned);
        return isNaN(num) ? 0 : num;
    }

    function setTextContent(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    /* ======================================================================
       Init on DOM Ready
       ====================================================================== */

    document.addEventListener('DOMContentLoaded', function () {
        // Only init list on list page
        if (document.getElementById('erp-table')) {
            initSortHeaders();
            ERP.loadList();

            // Enter key in search field
            var searchInput = document.getElementById('filter-search');
            if (searchInput) {
                searchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        ERP.applyFilters();
                    }
                });
            }
        }

        // Init amount calculation on create page
        if (document.getElementById('erp-create-form')) {
            // Add comma formatting to amount inputs
            var amountFields = ['field-payment-amount', 'field-invoice-amount', 'field-execution-cost', 'field-detail-execution-cost'];
            amountFields.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', function () {
                        ERP.formatAmountInput(this);
                    });
                }
            });
        }

        // ESC closes detail panel
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && currentDetailId) {
                ERP.closeDetail();
            }
        });
    });

    // Export
    window.ERP = ERP;

})();

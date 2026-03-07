/**
 * Place DB Management - JavaScript Module
 */

(function () {
    'use strict';

    // ========================================================================
    // State
    // ========================================================================

    const state = {
        filters: {
            status: '',
            period_start: '',
            period_end: '',
            region: '',
            source: '',
            user_id: '',
            unassigned: '',
            search: '',
            sort: 'register_date',
            order: 'DESC',
            page: 1,
            page_size: window.PLACE_CONFIG.defaultPageSize,
        },
        items: [],
        total: 0,
        statusCounts: {},
        selectedId: null,
        loading: false,
    };

    // ========================================================================
    // Status badge helpers
    // ========================================================================

    const STATUS_COLORS = {
        '부재': { bg: 'bg-red-100', text: 'text-red-700', dot: 'bg-red-500' },
        '재통': { bg: 'bg-yellow-100', text: 'text-yellow-700', dot: 'bg-yellow-500' },
        '가망': { bg: 'bg-blue-100', text: 'text-blue-700', dot: 'bg-blue-500' },
        '거절': { bg: 'bg-gray-200', text: 'text-gray-700', dot: 'bg-gray-600' },
        '계약완료': { bg: 'bg-green-100', text: 'text-green-700', dot: 'bg-green-500' },
    };

    function getStatusBadgeHtml(status, placeId) {
        const color = STATUS_COLORS[status] || { bg: 'bg-gray-100', text: 'text-gray-600' };
        const clickable = placeId ? `onclick="event.stopPropagation(); PlaceModule.openStatusDropdown(${placeId}, this)" style="cursor:pointer"` : '';
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${color.bg} ${color.text} status-badge" ${clickable} data-place-id="${placeId || ''}">${escapeHtml(status)}</span>`;
    }

    // ========================================================================
    // Data Loading
    // ========================================================================

    async function loadList() {
        if (state.loading) return;
        state.loading = true;

        const tbody = document.getElementById('place-tbody');
        tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm"><div class="spinner mx-auto mb-2"></div>데이터를 불러오는 중...</td></tr>`;

        try {
            const params = new URLSearchParams();
            Object.entries(state.filters).forEach(([key, val]) => {
                if (val !== '' && val !== null && val !== undefined) {
                    params.set(key, val);
                }
            });

            const data = await apiRequest('/api/place/list.php?' + params.toString());

            if (data.success) {
                state.items = data.data.items;
                state.total = data.data.total;
                state.statusCounts = data.data.status_counts;
                state.filters.page = data.data.page;
                state.filters.page_size = data.data.page_size;

                renderTable();
                renderPagination();
                updateStatusCounts();
                updateTotalBadge();
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-12 text-center text-red-400 text-sm">데이터를 불러오지 못했습니다.</td></tr>`;
        } finally {
            state.loading = false;
        }
    }

    // ========================================================================
    // Rendering
    // ========================================================================

    function renderTable() {
        const tbody = document.getElementById('place-tbody');

        if (state.items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">데이터가 없습니다.</td></tr>`;
            return;
        }

        let html = '';
        state.items.forEach((item, idx) => {
            const isSelected = state.selectedId === item.id;
            const rowClass = isSelected
                ? 'bg-blue-50 border-l-2 border-blue-500'
                : (idx % 2 === 1 ? 'bg-gray-50/50' : 'bg-white');

            html += `
            <tr class="${rowClass} hover:bg-blue-50/70 transition-colors cursor-pointer"
                data-id="${item.id}" onclick="PlaceModule.selectRow(${item.id})">
                <td class="w-10 px-3 py-2 checkbox-cell" onclick="event.stopPropagation(); window._handleCheckboxCell(event, this);">
                    <input type="checkbox" class="row-check rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                           value="${item.id}">
                </td>
                <td class="px-3 py-2 text-sm text-gray-800 font-medium">${escapeHtml(item.company_name || '')}</td>
                <td class="px-3 py-2 text-sm text-gray-600">${escapeHtml(formatPhone(item.phone || ''))}</td>
                <td class="px-3 py-2 text-sm text-gray-600">${escapeHtml(item.region || '-')}</td>
                <td class="px-3 py-2 text-sm text-gray-600">${escapeHtml(item.source || '-')}</td>
                <td class="px-3 py-2 text-sm relative">${getStatusBadgeHtml(item.status, item.id)}</td>
                <td class="px-3 py-2 text-sm text-gray-600">${escapeHtml(item.user_name || '미배정')}</td>
                <td class="px-3 py-2 text-sm text-gray-500">${escapeHtml(item.register_date || '')}</td>
            </tr>`;
        });

        tbody.innerHTML = html;

        // Sync check-all state
        document.getElementById('check-all').checked = false;
    }

    function renderPagination() {
        const container = document.getElementById('pagination-container');
        const pageInfo = document.getElementById('page-info');
        const totalPages = Math.max(1, Math.ceil(state.total / state.filters.page_size));
        const currentPage = state.filters.page;

        pageInfo.textContent = `${currentPage} / ${totalPages} 페이지`;

        let html = '';

        // Prev
        const prevDisabled = currentPage <= 1;
        html += `<button class="pagination-btn ${prevDisabled ? 'pagination-btn-disabled' : ''}"
                  ${prevDisabled ? 'disabled' : ''} onclick="PlaceModule.goToPage(${currentPage - 1})">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                 </button>`;

        // Page numbers
        const range = 2;
        const startPage = Math.max(1, currentPage - range);
        const endPage = Math.min(totalPages, currentPage + range);

        if (startPage > 1) {
            html += `<button class="pagination-btn" onclick="PlaceModule.goToPage(1)">1</button>`;
            if (startPage > 2) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-btn ${i === currentPage ? 'pagination-btn-active' : ''}"
                      onclick="PlaceModule.goToPage(${i})">${i}</button>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
            html += `<button class="pagination-btn" onclick="PlaceModule.goToPage(${totalPages})">${totalPages}</button>`;
        }

        // Next
        const nextDisabled = currentPage >= totalPages;
        html += `<button class="pagination-btn ${nextDisabled ? 'pagination-btn-disabled' : ''}"
                  ${nextDisabled ? 'disabled' : ''} onclick="PlaceModule.goToPage(${currentPage + 1})">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                 </button>`;

        container.innerHTML = html;
    }

    function updateStatusCounts() {
        const counts = state.statusCounts;
        document.querySelectorAll('[data-status-count]').forEach(el => {
            const key = el.dataset.statusCount;
            el.textContent = formatNumber(counts[key] || 0);
        });
    }

    function updateTotalBadge() {
        document.getElementById('total-count-badge').textContent = formatNumber(state.total) + '건';
    }

    // ========================================================================
    // Filters
    // ========================================================================

    function applyFilters() {
        state.filters.status = getActiveStatus();
        state.filters.period_start = document.getElementById('filter-period-start').value;
        state.filters.period_end = document.getElementById('filter-period-end').value;
        state.filters.region = document.getElementById('filter-region').value;
        state.filters.source = document.getElementById('filter-source').value;
        state.filters.search = document.getElementById('filter-search').value.trim();

        if (window.PLACE_CONFIG.isAdmin) {
            const userVal = document.getElementById('filter-user').value;
            if (userVal === 'unassigned') {
                state.filters.user_id = '';
                state.filters.unassigned = '1';
            } else {
                state.filters.user_id = userVal;
                state.filters.unassigned = '';
            }
        }

        state.filters.page = 1;
        loadList();
    }

    function resetFilters() {
        state.filters.status = '';
        state.filters.period_start = '';
        state.filters.period_end = '';
        state.filters.region = '';
        state.filters.source = '';
        state.filters.user_id = '';
        state.filters.unassigned = '';
        state.filters.search = '';
        state.filters.sort = 'register_date';
        state.filters.order = 'DESC';
        state.filters.page = 1;

        // Reset UI
        document.getElementById('filter-period-start').value = '';
        document.getElementById('filter-period-end').value = '';
        document.getElementById('filter-region').value = '';
        document.getElementById('filter-source').value = '';
        document.getElementById('filter-search').value = '';
        if (window.PLACE_CONFIG.isAdmin) {
            document.getElementById('filter-user').value = '';
        }

        // Reset status buttons
        document.querySelectorAll('.status-filter-btn').forEach(btn => {
            btn.classList.remove('active', 'bg-blue-50', 'text-blue-700', 'font-medium');
        });
        const allBtn = document.querySelector('.status-filter-btn[data-status=""]');
        if (allBtn) {
            allBtn.classList.add('active', 'bg-blue-50', 'text-blue-700', 'font-medium');
        }

        loadList();
    }

    function getActiveStatus() {
        const activeBtn = document.querySelector('.status-filter-btn.active');
        return activeBtn ? activeBtn.dataset.status : '';
    }

    // ========================================================================
    // Row Selection & Detail Panel
    // ========================================================================

    function selectRow(id) {
        state.selectedId = id;

        // Highlight selected row
        document.querySelectorAll('#place-tbody tr').forEach(tr => {
            if (parseInt(tr.dataset.id) === id) {
                tr.className = tr.className.replace(/bg-white|bg-gray-50\/50/, '').trim();
                tr.classList.add('bg-blue-50', 'border-l-2', 'border-blue-500');
            } else {
                tr.classList.remove('bg-blue-50', 'border-l-2', 'border-blue-500');
            }
        });

        // Show detail panel
        const panel = document.getElementById('detail-panel');
        panel.classList.remove('hidden');

        loadDetail(id);
    }

    async function loadDetail(id) {
        const content = document.getElementById('detail-content');
        content.innerHTML = `<div class="p-6 text-center text-gray-400 text-sm"><div class="spinner mx-auto mb-2"></div>로딩 중...</div>`;

        try {
            const data = await apiRequest('/api/place/detail.php?id=' + id);
            if (data.success) {
                renderDetail(data.data);
            }
        } catch (err) {
            content.innerHTML = `<div class="p-6 text-center text-red-400 text-sm">상세정보를 불러오지 못했습니다.</div>`;
        }
    }

    function renderDetail(data) {
        const record = data.record;
        const history = data.status_history || [];
        const memos = data.memos || [];
        const isAdmin = window.PLACE_CONFIG.isAdmin;

        let html = `
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-800">상세정보</h3>
            <button onclick="PlaceModule.closeDetail()" class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Basic Info -->
        <div class="p-4 border-b border-gray-100">
            <h4 class="text-lg font-bold text-gray-900 mb-1">${escapeHtml(record.company_name)}</h4>
            <div class="space-y-1.5 text-sm">
                <div class="flex items-center text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <a href="tel:${escapeHtml(record.phone)}" class="text-blue-600 hover:underline">${escapeHtml(formatPhone(record.phone))}</a>
                </div>
                <div class="flex items-center text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    ${escapeHtml(record.region || '-')}
                </div>
                <div class="flex items-center text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    경로: ${escapeHtml(record.source || '-')}
                </div>
                <div class="flex items-center text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    담당: ${escapeHtml(record.user_name || '미배정')}
                </div>
                <div class="flex items-center text-gray-600">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    등록일: ${escapeHtml(record.register_date || '-')}
                </div>
            </div>
            ${record.initial_memo ? `<div class="mt-2 p-2 bg-gray-50 rounded text-xs text-gray-600">${escapeHtml(record.initial_memo)}</div>` : ''}
        </div>

        <!-- Status Change -->
        <div class="p-4 border-b border-gray-100">
            <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">상태 변경</h4>
            <div class="flex flex-wrap gap-1.5">`;

        const statuses = window.PLACE_CONFIG.statuses;
        statuses.forEach(s => {
            const color = STATUS_COLORS[s] || {};
            const isActive = record.status === s;
            html += `<button class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors ${isActive ? color.bg + ' ' + color.text + ' ring-2 ring-offset-1 ring-current' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}"
                      onclick="PlaceModule.changeStatus(${record.id}, '${s}')" ${isActive ? 'disabled' : ''}>${escapeHtml(s)}</button>`;
        });

        html += `
            </div>
        </div>

        <!-- Status History -->
        <div class="p-4 border-b border-gray-100">
            <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">상태 변경 이력</h4>
            <div class="space-y-2 max-h-40 overflow-y-auto">`;

        if (history.length === 0) {
            html += `<p class="text-xs text-gray-400 text-center py-2">변경 이력이 없습니다.</p>`;
        } else {
            history.forEach(h => {
                html += `
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="text-gray-500">${escapeHtml(h.user_name || '')}</span>
                        ${getStatusBadgeHtml(h.old_status || '-', null)}
                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        ${getStatusBadgeHtml(h.new_status, null)}
                    </div>
                    <span class="text-gray-400 whitespace-nowrap">${escapeHtml(formatDateTime(h.created_at))}</span>
                </div>`;
            });
        }

        html += `
            </div>
        </div>

        <!-- Memos -->
        <div class="p-4">
            <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">메모</h4>
            <div class="flex gap-2 mb-3">
                <textarea id="detail-memo-input" class="form-textarea text-xs py-1.5 flex-1 resize-none" rows="2" placeholder="메모를 입력하세요..."></textarea>
                <button class="btn btn-primary btn-sm self-end flex-shrink-0" onclick="PlaceModule.saveMemo(${record.id})">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </button>
            </div>
            <div id="detail-memo-list" class="space-y-2 max-h-60 overflow-y-auto">`;

        if (memos.length === 0) {
            html += `<p class="text-xs text-gray-400 text-center py-2 memo-empty">등록된 메모가 없습니다.</p>`;
        } else {
            memos.forEach(m => {
                html += renderMemoItem(m);
            });
        }

        html += `
            </div>
        </div>`;

        document.getElementById('detail-content').innerHTML = html;
    }

    function renderMemoItem(memo) {
        return `
        <div class="bg-gray-50 rounded-md p-2.5">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-gray-700">${escapeHtml(memo.user_name || '')}</span>
                <span class="text-[10px] text-gray-400">${escapeHtml(formatDateTime(memo.created_at))}</span>
            </div>
            <p class="text-xs text-gray-600 whitespace-pre-wrap">${escapeHtml(memo.content || '')}</p>
        </div>`;
    }

    function closeDetail() {
        state.selectedId = null;
        document.getElementById('detail-panel').classList.add('hidden');

        // Remove highlight
        document.querySelectorAll('#place-tbody tr').forEach(tr => {
            tr.classList.remove('bg-blue-50', 'border-l-2', 'border-blue-500');
        });
    }

    // ========================================================================
    // Actions
    // ========================================================================

    async function changeStatus(id, status) {
        try {
            const data = await apiRequest('/api/place/status.php', 'POST', { id, status });
            if (data.success) {
                showToast(data.message, 'success');
                loadList();
                loadDetail(id);
            } else {
                showToast(data.message || '상태 변경 실패', 'error');
            }
        } catch (err) {
            showToast('상태 변경 중 오류가 발생했습니다.', 'error');
        }
    }

    function openStatusDropdown(placeId, el) {
        // Close any existing dropdown
        closeStatusDropdown();

        const statuses = window.PLACE_CONFIG.statuses;
        const rect = el.getBoundingClientRect();

        const dropdown = document.createElement('div');
        dropdown.id = 'status-dropdown';
        dropdown.className = 'fixed z-[200] bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[120px]';
        dropdown.style.top = (rect.bottom + 4) + 'px';
        dropdown.style.left = rect.left + 'px';

        statuses.forEach(s => {
            const color = STATUS_COLORS[s] || {};
            dropdown.innerHTML += `
            <button class="w-full text-left px-3 py-1.5 text-xs hover:bg-gray-50 flex items-center gap-2 transition-colors"
                    onclick="PlaceModule.changeStatus(${placeId}, '${s}'); PlaceModule.closeStatusDropdown();">
                <span class="w-2 h-2 rounded-full ${color.dot || 'bg-gray-400'}"></span>
                ${escapeHtml(s)}
            </button>`;
        });

        document.body.appendChild(dropdown);

        // Close on click outside
        setTimeout(() => {
            document.addEventListener('click', handleDropdownClose);
        }, 0);
    }

    function closeStatusDropdown() {
        const existing = document.getElementById('status-dropdown');
        if (existing) existing.remove();
        document.removeEventListener('click', handleDropdownClose);
    }

    function handleDropdownClose(e) {
        const dropdown = document.getElementById('status-dropdown');
        if (dropdown && !dropdown.contains(e.target)) {
            closeStatusDropdown();
        }
    }

    async function submitCreate() {
        const form = document.getElementById('form-create');
        const formData = new FormData(form);

        const payload = {
            company_name: formData.get('company_name'),
            phone: formData.get('phone'),
            region: formData.get('region'),
            register_date: formData.get('register_date'),
            source: formData.get('source'),
            initial_memo: formData.get('initial_memo'),
        };

        if (!payload.company_name) {
            showToast('상호명을 입력해주세요.', 'error');
            return;
        }
        if (!payload.phone) {
            showToast('연락처를 입력해주세요.', 'error');
            return;
        }

        const btn = document.getElementById('btn-submit-create');
        btn.disabled = true;

        try {
            const data = await apiRequest('/api/place/create.php', 'POST', payload);
            if (data.success) {
                if (data.warning) {
                    showToast(data.warning, 'warning', 5000);
                }
                showToast(data.message, 'success');
                closeModal('modal-create');
                form.reset();
                form.querySelector('[name="register_date"]').value = new Date().toISOString().split('T')[0];
                loadList();
            } else {
                showToast(data.message || '등록 실패', 'error');
            }
        } catch (err) {
            showToast('등록 중 오류가 발생했습니다.', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    function getSelectedIds() {
        const checkboxes = document.querySelectorAll('#place-tbody .row-check:checked');
        return Array.from(checkboxes).map(cb => parseInt(cb.value));
    }

    function openAssignModal() {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            showToast('배분할 항목을 선택해주세요.', 'warning');
            return;
        }
        document.getElementById('assign-count').textContent = ids.length;
        openModal('modal-assign');
    }

    async function submitAssign() {
        const ids = getSelectedIds();
        const userId = document.getElementById('assign-user-select').value;

        if (ids.length === 0) {
            showToast('배분할 항목을 선택해주세요.', 'warning');
            return;
        }
        if (!userId) {
            showToast('배분할 직원을 선택해주세요.', 'warning');
            return;
        }

        try {
            const data = await apiRequest('/api/place/assign.php', 'POST', {
                ids: ids,
                user_id: parseInt(userId),
            });
            if (data.success) {
                showToast(data.message, 'success');
                closeModal('modal-assign');
                document.getElementById('assign-user-select').value = '';
                loadList();
            } else {
                showToast(data.message || '배분 실패', 'error');
            }
        } catch (err) {
            showToast('배분 중 오류가 발생했습니다.', 'error');
        }
    }

    async function revokeSelected() {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            showToast('회수할 항목을 선택해주세요.', 'warning');
            return;
        }

        const confirmed = await confirmAction(ids.length + '건을 회수하시겠습니까?');
        if (!confirmed) return;

        try {
            const data = await apiRequest('/api/place/revoke.php', 'POST', { ids: ids });
            if (data.success) {
                showToast(data.message, 'success');
                loadList();
            } else {
                showToast(data.message || '회수 실패', 'error');
            }
        } catch (err) {
            showToast('회수 중 오류가 발생했습니다.', 'error');
        }
    }

    async function saveMemo(targetId) {
        const input = document.getElementById('detail-memo-input');
        const content = input.value.trim();

        if (!content) {
            showToast('메모 내용을 입력하세요.', 'error');
            return;
        }

        try {
            const data = await apiRequest('/api/place/memo.php', 'POST', {
                target_id: targetId,
                content: content,
            });

            if (data.success) {
                input.value = '';
                showToast('메모가 저장되었습니다.', 'success');

                // Prepend new memo
                const listEl = document.getElementById('detail-memo-list');
                const emptyEl = listEl.querySelector('.memo-empty');
                if (emptyEl) emptyEl.remove();

                const memoHtml = renderMemoItem(data.data);
                listEl.insertAdjacentHTML('afterbegin', memoHtml);
            } else {
                showToast(data.message || '메모 저장 실패', 'error');
            }
        } catch (err) {
            showToast('메모 저장 중 오류가 발생했습니다.', 'error');
        }
    }

    // ========================================================================
    // Sorting
    // ========================================================================

    function handleSort(column) {
        if (state.filters.sort === column) {
            state.filters.order = state.filters.order === 'ASC' ? 'DESC' : 'ASC';
        } else {
            state.filters.sort = column;
            state.filters.order = 'DESC';
        }
        state.filters.page = 1;
        updateSortIcons();
        loadList();
    }

    function updateSortIcons() {
        document.querySelectorAll('[data-sort]').forEach(th => {
            const icon = th.querySelector('.sort-icon');
            if (th.dataset.sort === state.filters.sort) {
                icon.innerHTML = state.filters.order === 'ASC'
                    ? '<svg class="w-3 h-3 inline ml-0.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>'
                    : '<svg class="w-3 h-3 inline ml-0.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>';
            } else {
                icon.innerHTML = '';
            }
        });
    }

    // ========================================================================
    // Pagination
    // ========================================================================

    function goToPage(page) {
        const totalPages = Math.max(1, Math.ceil(state.total / state.filters.page_size));
        if (page < 1 || page > totalPages) return;
        state.filters.page = page;
        loadList();
    }

    // ========================================================================
    // Utility
    // ========================================================================

    function formatPhone(phone) {
        if (!phone) return '';
        const cleaned = phone.replace(/[^\d]/g, '');
        if (cleaned.length === 11) {
            return cleaned.replace(/(\d{3})(\d{4})(\d{4})/, '$1-$2-$3');
        } else if (cleaned.length === 10) {
            return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
        }
        return phone;
    }

    function formatDateTime(dt) {
        if (!dt) return '';
        // Convert "2024-01-15 14:30:00" to "01/15 14:30"
        const parts = dt.split(' ');
        if (parts.length < 2) return dt;
        const dateParts = parts[0].split('-');
        const timeParts = parts[1].split(':');
        if (dateParts.length < 3 || timeParts.length < 2) return dt;
        return dateParts[1] + '/' + dateParts[2] + ' ' + timeParts[0] + ':' + timeParts[1];
    }

    // ========================================================================
    // Event Bindings
    // ========================================================================

    function init() {
        // Status filter buttons
        document.querySelectorAll('.status-filter-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.status-filter-btn').forEach(b => {
                    b.classList.remove('active', 'bg-blue-50', 'text-blue-700', 'font-medium');
                });
                this.classList.add('active', 'bg-blue-50', 'text-blue-700', 'font-medium');
                applyFilters();
            });
        });

        // Set initial active status
        const allBtn = document.querySelector('.status-filter-btn[data-status=""]');
        if (allBtn) allBtn.classList.add('bg-blue-50', 'text-blue-700', 'font-medium');

        // Apply/Reset buttons
        document.getElementById('btn-apply-filter').addEventListener('click', applyFilters);
        document.getElementById('btn-reset-filter').addEventListener('click', resetFilters);

        // Search on Enter
        document.getElementById('filter-search').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });

        // Sort headers
        document.querySelectorAll('[data-sort]').forEach(th => {
            th.addEventListener('click', function () {
                handleSort(this.dataset.sort);
            });
        });

        // Check all
        document.getElementById('check-all').addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('#place-tbody .row-check');
            checkboxes.forEach(cb => { cb.checked = this.checked; });
        });

        // Page size
        document.getElementById('page-size-select').addEventListener('change', function () {
            state.filters.page_size = parseInt(this.value);
            state.filters.page = 1;
            loadList();
        });

        // Initial sort icon
        updateSortIcons();

        // Load data
        loadList();
    }

    // ========================================================================
    // Public API
    // ========================================================================

    window.PlaceModule = {
        selectRow,
        closeDetail,
        changeStatus,
        openStatusDropdown,
        closeStatusDropdown,
        saveMemo,
        goToPage,
    };

    // Also expose for inline onclick handlers
    window.submitCreate = submitCreate;
    window.openAssignModal = openAssignModal;
    window.submitAssign = submitAssign;
    window.revokeSelected = revokeSelected;

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

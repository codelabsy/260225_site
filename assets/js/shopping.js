/**
 * Shopping DB - JavaScript Module
 */

(function () {
    'use strict';

    const CONFIG = window.SHOPPING_CONFIG || {};

    /* ======================================================================
       State
       ====================================================================== */

    let currentFilters = {
        status: '',
        period_start: '',
        period_end: '',
        user_id: '',
        unassigned: '',
        search: '',
        sort: 's.created_at',
        order: 'DESC',
        page: 1,
        page_size: 50,
    };

    let currentDetailId = null;
    let selectedFile = null;

    /* ======================================================================
       Status Badge Helpers
       ====================================================================== */

    const STATUS_CLASSES = {
        '부재':   'bg-red-100 text-red-700 border-red-200',
        '재통':   'bg-yellow-100 text-yellow-700 border-yellow-200',
        '가망':   'bg-blue-100 text-blue-700 border-blue-200',
        '계약완료': 'bg-green-100 text-green-700 border-green-200',
        '안함':   'bg-gray-100 text-gray-600 border-gray-200',
    };

    function statusBadgeHtml(status, id) {
        const cls = STATUS_CLASSES[status] || 'bg-gray-100 text-gray-600 border-gray-200';
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border cursor-pointer status-badge ' + cls + '" ' +
               'data-id="' + id + '" data-status="' + escapeHtml(status) + '">' +
               escapeHtml(status) + '</span>';
    }

    /* ======================================================================
       List Loading
       ====================================================================== */

    function loadList() {
        const params = new URLSearchParams();
        Object.entries(currentFilters).forEach(([k, v]) => {
            if (v !== '' && v !== null && v !== undefined) {
                params.set(k, v);
            }
        });

        apiRequest('/api/shopping/list.php?' + params.toString())
            .then(function (data) {
                if (!data.success) {
                    showToast(data.message || '목록 조회에 실패했습니다.', 'error');
                    return;
                }
                renderTable(data.data.items);
                renderPagination(data.data.page, data.data.total, data.data.page_size);
                updateStatusCounts(data.data.status_counts);
                document.getElementById('total-count-label').textContent = '총 ' + formatNumber(data.data.total) + '건';
            })
            .catch(function (err) {
                console.error('List load error:', err);
            });
    }

    /* ======================================================================
       Table Rendering
       ====================================================================== */

    function renderTable(items) {
        const tbody = document.getElementById('shopping-tbody');
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-12 text-center text-gray-400 text-sm">데이터가 없습니다.</td></tr>';
            return;
        }

        let html = '';
        items.forEach(function (item, idx) {
            const isActive = currentDetailId === item.id;
            html += '<tr class="' + (idx % 2 === 1 ? 'bg-gray-50/50' : 'bg-white') +
                    ' hover:bg-blue-50/50 transition-colors cursor-pointer' +
                    (isActive ? ' bg-blue-50' : '') + '" ' +
                    'data-id="' + item.id + '" onclick="window.shoppingOpenDetail(' + item.id + ')">';

            // Checkbox
            html += '<td class="w-10 px-3 py-3 checkbox-cell" onclick="event.stopPropagation(); window._handleCheckboxCell(event, this);">' +
                    '<input type="checkbox" class="table-check-row rounded border-gray-300 text-blue-600 focus:ring-blue-500" ' +
                    'value="' + item.id + '" data-table="shopping-table">' +
                    '</td>';

            // Company name
            html += '<td class="px-4 py-3 text-sm text-gray-700 max-w-[160px] truncate">' +
                    escapeHtml(item.company_name || '-') + '</td>';

            // Contact name
            html += '<td class="px-4 py-3 text-sm text-gray-700">' +
                    escapeHtml(item.contact_name || '-') + '</td>';

            // Phone
            html += '<td class="px-4 py-3 text-sm text-gray-700 font-mono">' +
                    escapeHtml(item.phone || '-') + '</td>';

            // Status badge
            html += '<td class="px-4 py-3 text-sm" onclick="event.stopPropagation()">' +
                    statusBadgeHtml(item.status, item.id) + '</td>';

            // Assigned user
            html += '<td class="px-4 py-3 text-sm text-gray-700">' +
                    escapeHtml(item.user_name || '미배정') + '</td>';

            // Created date
            html += '<td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">' +
                    escapeHtml(item.created_at ? item.created_at.substring(0, 10) : '-') + '</td>';

            // Last activity
            html += '<td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">' +
                    escapeHtml(item.updated_at ? item.updated_at.substring(0, 16) : '-') + '</td>';

            html += '</tr>';
        });

        tbody.innerHTML = html;
    }

    /* ======================================================================
       Pagination
       ====================================================================== */

    function renderPagination(page, total, pageSize) {
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        const area = document.getElementById('pagination-area');

        let html = '<div class="flex flex-col sm:flex-row items-center justify-between gap-3">';

        // Page size selector
        html += '<div class="flex items-center space-x-2 text-sm text-gray-600">';
        html += '<label>표시개수:</label>';
        html += '<select class="form-select rounded-md border-gray-300 text-sm py-1.5 pl-3 pr-8" onchange="window.shoppingChangePageSize(this.value)">';
        [50, 100, 300, 500, 1000].forEach(function (size) {
            html += '<option value="' + size + '"' + (size === pageSize ? ' selected' : '') + '>' + size + '개</option>';
        });
        html += '</select></div>';

        // Page links
        html += '<nav class="flex items-center space-x-1">';

        // Prev
        if (page > 1) {
            html += '<button class="pagination-btn" onclick="window.shoppingGoPage(' + (page - 1) + ')">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>';
        } else {
            html += '<span class="pagination-btn pagination-btn-disabled">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></span>';
        }

        // Page numbers
        var range = 2;
        var startPage = Math.max(1, page - range);
        var endPage = Math.min(totalPages, page + range);

        if (startPage > 1) {
            html += '<button class="pagination-btn" onclick="window.shoppingGoPage(1)">1</button>';
            if (startPage > 2) html += '<span class="pagination-ellipsis">...</span>';
        }

        for (var i = startPage; i <= endPage; i++) {
            html += '<button class="pagination-btn' + (i === page ? ' pagination-btn-active' : '') + '" onclick="window.shoppingGoPage(' + i + ')">' + i + '</button>';
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += '<span class="pagination-ellipsis">...</span>';
            html += '<button class="pagination-btn" onclick="window.shoppingGoPage(' + totalPages + ')">' + totalPages + '</button>';
        }

        // Next
        if (page < totalPages) {
            html += '<button class="pagination-btn" onclick="window.shoppingGoPage(' + (page + 1) + ')">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>';
        } else {
            html += '<span class="pagination-btn pagination-btn-disabled">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>';
        }

        html += '</nav>';

        // Page info
        html += '<div class="text-sm text-gray-500">' + page + ' / ' + totalPages + ' 페이지</div>';
        html += '</div>';

        area.innerHTML = html;
    }

    /* ======================================================================
       Status Counts Badge Update
       ====================================================================== */

    function updateStatusCounts(counts) {
        if (!counts) return;
        var allEl = document.getElementById('count-all');
        if (allEl) allEl.textContent = formatNumber(counts['전체'] || 0);

        ['부재', '재통', '가망', '계약완료', '안함'].forEach(function (s) {
            var el = document.getElementById('count-' + s);
            if (el) el.textContent = formatNumber(counts[s] || 0);
        });
    }

    /* ======================================================================
       Filter Handling
       ====================================================================== */

    function applyFilters() {
        currentFilters.status = getActiveStatus();
        currentFilters.period_start = document.getElementById('filter-period-start').value;
        currentFilters.period_end = document.getElementById('filter-period-end').value;

        var userEl = document.getElementById('filter-user');
        if (userEl && userEl.value === 'unassigned') {
            currentFilters.user_id = '';
            currentFilters.unassigned = '1';
        } else {
            currentFilters.user_id = userEl ? userEl.value : '';
            currentFilters.unassigned = '';
        }

        currentFilters.search = document.getElementById('filter-search').value.trim();
        currentFilters.page = 1;
        loadList();
    }

    function resetFilters() {
        // Reset UI
        document.querySelectorAll('.status-filter-btn').forEach(function (btn) {
            btn.classList.remove('active', 'bg-blue-50', 'text-blue-700', 'font-medium');
        });
        var allBtn = document.querySelector('.status-filter-btn[data-status=""]');
        if (allBtn) allBtn.classList.add('active', 'bg-blue-50', 'text-blue-700', 'font-medium');

        document.getElementById('filter-period-start').value = '';
        document.getElementById('filter-period-end').value = '';

        var userEl = document.getElementById('filter-user');
        if (userEl) userEl.value = '';

        document.getElementById('filter-search').value = '';

        currentFilters = {
            status: '',
            period_start: '',
            period_end: '',
            user_id: '',
            unassigned: '',
            search: '',
            sort: 's.created_at',
            order: 'DESC',
            page: 1,
            page_size: currentFilters.page_size,
        };

        loadList();
    }

    function getActiveStatus() {
        var activeBtn = document.querySelector('.status-filter-btn.active');
        return activeBtn ? activeBtn.dataset.status : '';
    }

    /* ======================================================================
       Status Filter Clicks
       ====================================================================== */

    function initStatusFilter() {
        document.getElementById('status-filter').addEventListener('click', function (e) {
            var btn = e.target.closest('.status-filter-btn');
            if (!btn) return;

            document.querySelectorAll('.status-filter-btn').forEach(function (b) {
                b.classList.remove('active', 'bg-blue-50', 'text-blue-700', 'font-medium');
            });
            btn.classList.add('active', 'bg-blue-50', 'text-blue-700', 'font-medium');

            currentFilters.status = btn.dataset.status;
            currentFilters.page = 1;
            loadList();
        });
    }

    /* ======================================================================
       Sorting
       ====================================================================== */

    function initSorting() {
        document.querySelectorAll('[data-sort]').forEach(function (th) {
            th.addEventListener('click', function () {
                var sort = this.dataset.sort;
                if (currentFilters.sort === sort) {
                    currentFilters.order = currentFilters.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    currentFilters.sort = sort;
                    currentFilters.order = 'ASC';
                }
                currentFilters.page = 1;
                loadList();
            });
        });
    }

    /* ======================================================================
       Quick Status Change (Badge Click)
       ====================================================================== */

    function initQuickStatusChange() {
        document.getElementById('shopping-tbody').addEventListener('click', function (e) {
            var badge = e.target.closest('.status-badge');
            if (!badge) return;

            e.stopPropagation();
            var id = badge.dataset.id;
            var currentStatus = badge.dataset.status;

            // Show dropdown
            showStatusDropdown(badge, id, currentStatus);
        });
    }

    function showStatusDropdown(anchor, id, currentStatus) {
        // Remove existing
        var existing = document.getElementById('status-dropdown');
        if (existing) existing.remove();

        var rect = anchor.getBoundingClientRect();
        var statuses = CONFIG.statuses || ['부재', '재통', '가망', '계약완료', '안함'];

        var div = document.createElement('div');
        div.id = 'status-dropdown';
        div.className = 'fixed z-50 bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[120px]';
        div.style.top = (rect.bottom + 4) + 'px';
        div.style.left = rect.left + 'px';

        statuses.forEach(function (status) {
            var cls = STATUS_CLASSES[status] || '';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left px-3 py-2 text-sm hover:bg-gray-50 flex items-center gap-2' +
                            (status === currentStatus ? ' font-semibold' : '');
            btn.innerHTML = '<span class="w-2 h-2 rounded-full ' + getStatusDotClass(status) + '"></span>' + escapeHtml(status);
            btn.addEventListener('click', function () {
                changeStatus(id, status);
                div.remove();
            });
            div.appendChild(btn);
        });

        document.body.appendChild(div);

        // Close on click outside
        function closeDropdown(e) {
            if (!div.contains(e.target)) {
                div.remove();
                document.removeEventListener('click', closeDropdown);
            }
        }
        setTimeout(function () {
            document.addEventListener('click', closeDropdown);
        }, 0);
    }

    function getStatusDotClass(status) {
        switch (status) {
            case '부재': return 'bg-red-500';
            case '재통': return 'bg-yellow-500';
            case '가망': return 'bg-blue-500';
            case '계약완료': return 'bg-green-500';
            case '안함': return 'bg-gray-400';
            default: return 'bg-gray-400';
        }
    }

    function changeStatus(id, status) {
        apiRequest('/api/shopping/status.php', 'POST', { id: parseInt(id), status: status })
            .then(function (data) {
                if (data.success) {
                    showToast('상태가 변경되었습니다.', 'success');
                    loadList();
                    if (currentDetailId === parseInt(id)) {
                        loadDetail(id);
                    }
                } else {
                    showToast(data.message || '상태 변경에 실패했습니다.', 'error');
                }
            })
            .catch(function () {
                showToast('상태 변경 중 오류가 발생했습니다.', 'error');
            });
    }

    /* ======================================================================
       Detail Panel
       ====================================================================== */

    function openDetail(id) {
        currentDetailId = parseInt(id);
        var panel = document.getElementById('detail-panel');
        panel.classList.remove('hidden');
        loadDetail(id);
    }

    function closeDetailPanel() {
        currentDetailId = null;
        var panel = document.getElementById('detail-panel');
        panel.classList.add('hidden');
    }

    function loadDetail(id) {
        apiRequest('/api/shopping/detail.php?id=' + id)
            .then(function (data) {
                if (!data.success) {
                    showToast(data.message || '상세 조회에 실패했습니다.', 'error');
                    return;
                }
                renderDetail(data.data);
            })
            .catch(function () {
                showToast('상세 조회 중 오류가 발생했습니다.', 'error');
            });
    }

    function renderDetail(data) {
        var record = data.record;
        var histories = data.status_histories || [];
        var memos = data.memos || [];

        var html = '';

        // Basic info
        html += '<div class="p-4 border-b border-gray-100">';
        html += '<h4 class="text-xs font-semibold text-gray-500 uppercase mb-3">기본 정보</h4>';
        html += '<dl class="space-y-2 text-sm">';
        html += '<div class="flex justify-between"><dt class="text-gray-500">상호명</dt><dd class="font-medium text-gray-900">' + escapeHtml(record.company_name || '-') + '</dd></div>';
        html += '<div class="flex justify-between"><dt class="text-gray-500">담당자명</dt><dd class="font-medium text-gray-900">' + escapeHtml(record.contact_name || '-') + '</dd></div>';
        html += '<div class="flex justify-between"><dt class="text-gray-500">연락처</dt><dd class="font-medium text-gray-900 font-mono">' + escapeHtml(record.phone || '-') + '</dd></div>';
        html += '<div class="flex justify-between"><dt class="text-gray-500">배정직원</dt><dd class="font-medium text-gray-900">' + escapeHtml(record.user_name || '미배정') + '</dd></div>';
        html += '<div class="flex justify-between"><dt class="text-gray-500">등록일</dt><dd class="text-gray-700">' + escapeHtml(record.created_at || '-') + '</dd></div>';

        if (record.extra_field_1) {
            html += '<div class="flex justify-between"><dt class="text-gray-500">기타1</dt><dd class="text-gray-700">' + escapeHtml(record.extra_field_1) + '</dd></div>';
        }
        if (record.extra_field_2) {
            html += '<div class="flex justify-between"><dt class="text-gray-500">기타2</dt><dd class="text-gray-700">' + escapeHtml(record.extra_field_2) + '</dd></div>';
        }
        if (record.extra_field_3) {
            html += '<div class="flex justify-between"><dt class="text-gray-500">기타3</dt><dd class="text-gray-700">' + escapeHtml(record.extra_field_3) + '</dd></div>';
        }
        html += '</dl></div>';

        // Status change buttons
        html += '<div class="p-4 border-b border-gray-100">';
        html += '<h4 class="text-xs font-semibold text-gray-500 uppercase mb-3">상태 변경</h4>';
        html += '<div class="flex flex-wrap gap-2">';
        var statuses = CONFIG.statuses || ['부재', '재통', '가망', '계약완료', '안함'];
        statuses.forEach(function (s) {
            var isActive = record.status === s;
            var dotClass = getStatusDotClass(s);
            html += '<button type="button" class="btn btn-sm ' + (isActive ? 'btn-primary' : 'btn-outline') + ' text-xs" ' +
                    'onclick="window.shoppingChangeStatus(' + record.id + ', \'' + s + '\')">' +
                    '<span class="w-2 h-2 rounded-full ' + dotClass + ' mr-1"></span>' + escapeHtml(s) +
                    '</button>';
        });
        html += '</div></div>';

        // Status history
        html += '<div class="p-4 border-b border-gray-100">';
        html += '<h4 class="text-xs font-semibold text-gray-500 uppercase mb-3">상태 변경 히스토리</h4>';
        if (histories.length === 0) {
            html += '<p class="text-sm text-gray-400">변경 이력이 없습니다.</p>';
        } else {
            html += '<div class="space-y-2 max-h-48 overflow-y-auto">';
            histories.forEach(function (h) {
                html += '<div class="flex items-start gap-2 text-xs">';
                html += '<div class="w-1.5 h-1.5 rounded-full bg-gray-300 mt-1.5 flex-shrink-0"></div>';
                html += '<div class="flex-1">';
                html += '<div class="flex items-center gap-1">';
                html += '<span class="font-medium text-gray-700">' + escapeHtml(h.user_name || '') + '</span>';
                html += '<span class="text-gray-400">' + escapeHtml(h.created_at || '') + '</span>';
                html += '</div>';
                html += '<div class="text-gray-600">' + escapeHtml(h.old_status || '-') + ' &rarr; ' + escapeHtml(h.new_status) + '</div>';
                html += '</div></div>';
            });
            html += '</div>';
        }
        html += '</div>';

        // Memos
        html += '<div class="p-4">';
        html += '<h4 class="text-xs font-semibold text-gray-500 uppercase mb-3">메모</h4>';

        // Memo input
        html += '<div class="flex gap-2 mb-3">';
        html += '<textarea id="detail-memo-input" class="form-textarea flex-1 text-sm rounded-md border-gray-300 resize-none" rows="2" placeholder="메모를 입력하세요..."></textarea>';
        html += '<button type="button" class="btn btn-primary self-end text-xs py-2 px-3" onclick="window.shoppingSaveMemo(' + record.id + ')">저장</button>';
        html += '</div>';

        // Memo list
        html += '<div id="detail-memo-list" class="space-y-2 max-h-64 overflow-y-auto">';
        if (memos.length === 0) {
            html += '<p class="text-sm text-gray-400 text-center py-4">등록된 메모가 없습니다.</p>';
        } else {
            memos.forEach(function (m) {
                html += '<div class="bg-gray-50 rounded-md p-3">';
                html += '<div class="flex items-center justify-between mb-1">';
                html += '<span class="text-xs font-medium text-gray-700">' + escapeHtml(m.user_name || '') + '</span>';
                html += '<span class="text-xs text-gray-400">' + escapeHtml(m.created_at || '') + '</span>';
                html += '</div>';
                html += '<p class="text-sm text-gray-600 whitespace-pre-wrap">' + escapeHtml(m.content || '') + '</p>';
                html += '</div>';
            });
        }
        html += '</div>';
        html += '</div>';

        document.getElementById('detail-content').innerHTML = html;
    }

    /* ======================================================================
       Memo Save
       ====================================================================== */

    function saveMemo(targetId) {
        var inputEl = document.getElementById('detail-memo-input');
        if (!inputEl) return;

        var content = inputEl.value.trim();
        if (!content) {
            showToast('메모 내용을 입력하세요.', 'error');
            return;
        }

        apiRequest('/api/shopping/memo.php', 'POST', {
            target_id: targetId,
            content: content,
        }).then(function (data) {
            if (data.success) {
                showToast('메모가 저장되었습니다.', 'success');
                loadDetail(targetId);
            } else {
                showToast(data.message || '메모 저장에 실패했습니다.', 'error');
            }
        }).catch(function () {
            showToast('메모 저장 중 오류가 발생했습니다.', 'error');
        });
    }

    /* ======================================================================
       Assign / Revoke
       ====================================================================== */

    function openAssignModal() {
        var ids = getSelectedRows('shopping-table');
        if (ids.length === 0) {
            showToast('배분할 DB를 선택해주세요.', 'warning');
            return;
        }
        document.getElementById('assign-count').textContent = ids.length;
        openModal('modal-assign');
    }

    function doAssign() {
        var ids = getSelectedRows('shopping-table');
        var userSelect = document.getElementById('assign-user-select');
        var userId = userSelect ? userSelect.value : '';

        if (ids.length === 0) {
            showToast('배분할 DB를 선택해주세요.', 'warning');
            return;
        }
        if (!userId) {
            showToast('배분할 직원을 선택해주세요.', 'warning');
            return;
        }

        apiRequest('/api/shopping/assign.php', 'POST', {
            ids: ids.map(Number),
            user_id: parseInt(userId),
        }).then(function (data) {
            if (data.success) {
                showToast((data.data ? data.data.user_name : '') + '에게 ' + (data.data ? data.data.count : 0) + '건 배분되었습니다.', 'success');
                closeModal('modal-assign');
                loadList();
            } else {
                showToast(data.message || '배분에 실패했습니다.', 'error');
            }
        }).catch(function () {
            showToast('배분 중 오류가 발생했습니다.', 'error');
        });
    }

    function openRevokeModal() {
        var ids = getSelectedRows('shopping-table');
        if (ids.length === 0) {
            showToast('회수할 DB를 선택해주세요.', 'warning');
            return;
        }
        document.getElementById('revoke-count').textContent = ids.length;
        openModal('modal-revoke');
    }

    function doRevoke() {
        var ids = getSelectedRows('shopping-table');
        if (ids.length === 0) {
            showToast('회수할 DB를 선택해주세요.', 'warning');
            return;
        }

        apiRequest('/api/shopping/revoke.php', 'POST', {
            ids: ids.map(Number),
        }).then(function (data) {
            if (data.success) {
                showToast((data.data ? data.data.count : 0) + '건 회수되었습니다.', 'success');
                closeModal('modal-revoke');
                loadList();
            } else {
                showToast(data.message || '회수에 실패했습니다.', 'error');
            }
        }).catch(function () {
            showToast('회수 중 오류가 발생했습니다.', 'error');
        });
    }

    /* ======================================================================
       Upload (Modal)
       ====================================================================== */

    function initUpload() {
        var dropzone = document.getElementById('upload-dropzone');
        var fileInput = document.getElementById('upload-file-input');

        if (!dropzone || !fileInput) return;

        ['dragenter', 'dragover'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.add('border-blue-400', 'bg-blue-50');
            });
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropzone.classList.remove('border-blue-400', 'bg-blue-50');
            });
        });

        dropzone.addEventListener('drop', function (e) {
            var files = e.dataTransfer.files;
            if (files.length > 0) handleFileSelect(files[0]);
        });

        fileInput.addEventListener('change', function (e) {
            if (e.target.files.length > 0) handleFileSelect(e.target.files[0]);
        });
    }

    function handleFileSelect(file) {
        if (!file.name.toLowerCase().endsWith('.csv')) {
            showToast('CSV 파일만 업로드 가능합니다.', 'error');
            return;
        }
        selectedFile = file;
        var nameEl = document.getElementById('upload-file-name');
        nameEl.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + 'KB)';
        document.getElementById('upload-file-info').classList.remove('hidden');
    }

    function clearUploadFile() {
        selectedFile = null;
        var fileInput = document.getElementById('upload-file-input');
        if (fileInput) fileInput.value = '';
        document.getElementById('upload-file-info').classList.add('hidden');
    }

    function doUpload() {
        if (!selectedFile) {
            showToast('파일을 선택해주세요.', 'error');
            return;
        }

        var progressEl = document.getElementById('upload-progress');
        var resultEl = document.getElementById('upload-result');
        var btn = document.getElementById('btn-do-upload');

        progressEl.classList.remove('hidden');
        resultEl.classList.add('hidden');
        btn.disabled = true;

        var formData = new FormData();
        formData.append('file', selectedFile);

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfHeaders = {};
        if (csrfMeta) {
            csrfHeaders['X-CSRF-Token'] = csrfMeta.getAttribute('content');
        }

        fetch('/api/shopping/upload.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: csrfHeaders,
            body: formData,
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (data) {
            progressEl.classList.add('hidden');

            if (data.success) {
                var d = data.data;
                var html = '<div class="p-4 rounded-lg bg-green-50 border border-green-200">';
                html += '<h4 class="text-sm font-semibold text-green-800 mb-2">업로드 완료</h4>';
                html += '<div class="grid grid-cols-3 gap-3 text-center">';
                html += '<div><p class="text-xl font-bold text-gray-800">' + d.total + '</p><p class="text-xs text-gray-500">전체</p></div>';
                html += '<div><p class="text-xl font-bold text-green-600">' + d.success_count + '</p><p class="text-xs text-gray-500">성공</p></div>';
                html += '<div><p class="text-xl font-bold text-red-600">' + d.duplicate_count + '</p><p class="text-xs text-gray-500">중복</p></div>';
                html += '</div>';

                if (d.duplicates && d.duplicates.length > 0) {
                    html += '<div class="mt-3 max-h-32 overflow-y-auto">';
                    html += '<p class="text-xs font-medium text-gray-600 mb-1">중복 목록:</p>';
                    d.duplicates.forEach(function (dup) {
                        html += '<div class="text-xs text-gray-500 bg-white p-1.5 rounded mb-1">' +
                                escapeHtml(dup.company_name || '-') + ' / ' + escapeHtml(dup.phone) + '</div>';
                    });
                    html += '</div>';
                }

                html += '</div>';
                resultEl.innerHTML = html;
                resultEl.classList.remove('hidden');
                showToast('업로드가 완료되었습니다.', 'success');

                // Refresh list
                setTimeout(function () {
                    loadList();
                    clearUploadFile();
                }, 1000);
            } else {
                showToast(data.message || '업로드에 실패했습니다.', 'error');
            }

            btn.disabled = false;
        })
        .catch(function () {
            progressEl.classList.add('hidden');
            showToast('업로드 중 오류가 발생했습니다.', 'error');
            btn.disabled = false;
        });
    }

    /* ======================================================================
       Search on Enter key
       ====================================================================== */

    function initSearchEnter() {
        var searchInput = document.getElementById('filter-search');
        if (searchInput) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyFilters();
                }
            });
        }
    }

    /* ======================================================================
       Pagination Helpers (global)
       ====================================================================== */

    window.shoppingGoPage = function (page) {
        currentFilters.page = page;
        loadList();
    };

    window.shoppingChangePageSize = function (size) {
        currentFilters.page_size = parseInt(size);
        currentFilters.page = 1;
        loadList();
    };

    window.shoppingOpenDetail = openDetail;
    window.shoppingChangeStatus = changeStatus;
    window.shoppingSaveMemo = saveMemo;
    window.closeDetailPanel = closeDetailPanel;
    window.openAssignModal = openAssignModal;
    window.doAssign = doAssign;
    window.openRevokeModal = openRevokeModal;
    window.doRevoke = doRevoke;
    window.doUpload = doUpload;
    window.clearUploadFile = clearUploadFile;

    /* ======================================================================
       Init
       ====================================================================== */

    document.addEventListener('DOMContentLoaded', function () {
        // Set default active status filter style
        var defaultBtn = document.querySelector('.status-filter-btn[data-status=""]');
        if (defaultBtn) defaultBtn.classList.add('bg-blue-50', 'text-blue-700', 'font-medium');

        initStatusFilter();
        initSorting();
        initQuickStatusChange();
        initUpload();
        initSearchEnter();

        // Bind apply/reset buttons
        var applyBtn = document.getElementById('btn-apply-filter');
        if (applyBtn) applyBtn.addEventListener('click', applyFilters);

        var resetBtn = document.getElementById('btn-reset-filter');
        if (resetBtn) resetBtn.addEventListener('click', resetFilters);

        // Initial load
        loadList();
    });

})();

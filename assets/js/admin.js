/**
 * ERP+CRM - Admin Module JavaScript
 * Employee management & Activity log functions.
 */

(function () {
    'use strict';

    /* ======================================================================
       Employee Management
       ====================================================================== */

    let employeeData = [];

    /**
     * Initialize employee management page.
     */
    function initEmployeePage() {
        loadEmployees();

        // Show inactive toggle
        const toggleInactive = document.getElementById('show-inactive');
        if (toggleInactive) {
            toggleInactive.addEventListener('change', function () {
                loadEmployees();
            });
        }
    }

    /**
     * Load employee list from API.
     */
    async function loadEmployees() {
        const showInactive = document.getElementById('show-inactive');
        const showAll = showInactive && showInactive.checked ? '1' : '0';

        try {
            const result = await apiRequest('/api/employees/list.php?all=' + showAll);
            if (result.success) {
                employeeData = result.data;
                renderEmployeeTable(result.data);
                updateEmployeeStats(result.data);
            }
        } catch (err) {
            showToast('직원 목록을 불러오는데 실패했습니다.', 'error');
        }
    }

    /**
     * Render employee table rows.
     */
    function renderEmployeeTable(employees) {
        const tbody = document.getElementById('employee-table-body');
        if (!tbody) return;

        if (employees.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-12 text-center text-gray-400 text-sm">등록된 직원이 없습니다.</td></tr>';
            return;
        }

        tbody.innerHTML = employees.map(function (emp) {
            const statusBadge = emp.is_active == 1
                ? '<span class="badge badge-success"><span class="status-dot status-dot-success"></span>활성</span>'
                : '<span class="badge badge-danger"><span class="status-dot status-dot-danger"></span>비활성</span>';

            const createdDate = emp.created_at ? emp.created_at.substring(0, 10) : '-';

            return '<tr class="hover:bg-blue-50/50 transition-colors cursor-pointer" onclick="openEditModal(' + emp.id + ')">' +
                '<td class="px-4 py-3 text-sm font-medium text-gray-900">' + escapeHtml(emp.name) + '</td>' +
                '<td class="px-4 py-3 text-sm text-gray-600">' + escapeHtml(emp.username) + '</td>' +
                '<td class="px-4 py-3 text-sm text-gray-600">' + escapeHtml(emp.position || '-') + '</td>' +
                '<td class="px-4 py-3 text-sm text-gray-600">' + escapeHtml(emp.phone || '-') + '</td>' +
                '<td class="px-4 py-3 text-sm text-gray-600">' + escapeHtml(emp.email || '-') + '</td>' +
                '<td class="px-4 py-3 text-sm text-gray-600 text-right">' + parseFloat(emp.incentive_rate || 0).toFixed(1) + '%</td>' +
                '<td class="px-4 py-3 text-sm text-center">' + statusBadge + '</td>' +
                '<td class="px-4 py-3 text-sm text-gray-500">' + createdDate + '</td>' +
                '<td class="px-4 py-3 text-sm text-center">' +
                    '<div class="flex items-center justify-center gap-1">' +
                        (emp.is_active == 1
                            ? '<button onclick="event.stopPropagation(); deactivateEmployee(' + emp.id + ', \'' + escapeHtml(emp.name).replace(/'/g, "\\'") + '\')" class="btn btn-sm btn-ghost text-red-500 hover:text-red-700 hover:bg-red-50" title="비활성화"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg></button>'
                            : '<button onclick="event.stopPropagation(); reactivateEmployee(' + emp.id + ', \'' + escapeHtml(emp.name).replace(/'/g, "\\'") + '\')" class="btn btn-sm btn-ghost text-green-500 hover:text-green-700 hover:bg-green-50" title="재활성화"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>'
                        ) +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    /**
     * Update stats cards.
     */
    function updateEmployeeStats(employees) {
        const total = employees.length;
        const active = employees.filter(function (e) { return e.is_active == 1; }).length;
        const inactive = total - active;

        var el;
        el = document.getElementById('stat-total');
        if (el) el.textContent = total;
        el = document.getElementById('stat-active');
        if (el) el.textContent = active;
        el = document.getElementById('stat-inactive');
        if (el) el.textContent = inactive;
    }

    /**
     * Open create modal.
     */
    function openCreateModal() {
        var form = document.getElementById('form-create');
        if (form) form.reset();
        openModal('modal-create');
    }

    /**
     * Handle employee creation.
     */
    async function handleCreate(e) {
        e.preventDefault();

        var form = document.getElementById('form-create');
        var btn = document.getElementById('btn-create-submit');
        if (btn) btn.disabled = true;

        var data = {
            username: form.querySelector('[name="username"]').value.trim(),
            password: form.querySelector('[name="password"]').value,
            name: form.querySelector('[name="name"]').value.trim(),
            position: form.querySelector('[name="position"]').value.trim(),
            phone: form.querySelector('[name="phone"]').value.trim(),
            email: form.querySelector('[name="email"]').value.trim(),
            incentive_rate: parseFloat(form.querySelector('[name="incentive_rate"]').value) || 0,
        };

        try {
            var result = await apiRequest('/api/employees/create.php', 'POST', data);
            if (result.success) {
                showToast(result.message, 'success');
                closeModal('modal-create');
                loadEmployees();
            } else {
                showToast(result.message, 'error');
            }
        } catch (err) {
            showToast(err.message || '직원 생성에 실패했습니다.', 'error');
        } finally {
            if (btn) btn.disabled = false;
        }

        return false;
    }

    /**
     * Open edit modal with employee data.
     */
    async function openEditModal(id) {
        document.getElementById('edit-id').value = id;

        // Find employee from cached data
        var emp = employeeData.find(function (e) { return e.id == id; });
        if (emp) {
            document.getElementById('edit-username').value = emp.username;
            document.getElementById('edit-name').value = emp.name;
            document.getElementById('edit-position').value = emp.position || '';
            document.getElementById('edit-phone').value = emp.phone || '';
            document.getElementById('edit-email').value = emp.email || '';
            document.getElementById('edit-incentive').value = emp.incentive_rate || 0;
            document.getElementById('edit-is-active').value = emp.is_active;
        }

        // Clear password
        var passField = document.querySelector('#form-edit [name="password"]');
        if (passField) passField.value = '';

        openModal('modal-edit');

        // Load detail info
        try {
            var result = await apiRequest('/api/employees/detail.php?id=' + id);
            if (result.success) {
                var detail = result.data;
                var detailDiv = document.getElementById('edit-detail-info');
                if (detailDiv) {
                    detailDiv.classList.remove('hidden');
                    document.getElementById('detail-sales-count').textContent =
                        (detail.sales_summary ? detail.sales_summary.total_count : 0) + '건';
                    document.getElementById('detail-shopping-count').textContent =
                        (detail.shopping_db_count || 0) + '건';
                    document.getElementById('detail-place-count').textContent =
                        (detail.place_db_count || 0) + '건';
                }
            }
        } catch (err) {
            // Silently ignore detail load failure
        }
    }

    /**
     * Handle employee update.
     */
    async function handleUpdate(e) {
        e.preventDefault();

        var form = document.getElementById('form-edit');
        var btn = document.getElementById('btn-edit-submit');
        if (btn) btn.disabled = true;

        var data = {
            id: parseInt(document.getElementById('edit-id').value),
            name: document.getElementById('edit-name').value.trim(),
            position: document.getElementById('edit-position').value.trim(),
            phone: document.getElementById('edit-phone').value.trim(),
            email: document.getElementById('edit-email').value.trim(),
            incentive_rate: parseFloat(document.getElementById('edit-incentive').value) || 0,
            is_active: parseInt(document.getElementById('edit-is-active').value),
        };

        var password = form.querySelector('[name="password"]').value;
        if (password) {
            data.password = password;
        }

        try {
            var result = await apiRequest('/api/employees/update.php', 'POST', data);
            if (result.success) {
                showToast(result.message, 'success');
                closeModal('modal-edit');
                loadEmployees();
            } else {
                showToast(result.message, 'error');
            }
        } catch (err) {
            showToast(err.message || '직원 수정에 실패했습니다.', 'error');
        } finally {
            if (btn) btn.disabled = false;
        }

        return false;
    }

    /**
     * Deactivate an employee.
     */
    async function deactivateEmployee(id, name) {
        var confirmed = await confirmAction(name + ' 직원을 비활성화하시겠습니까?\n배정된 DB가 모두 회수됩니다.');
        if (!confirmed) return;

        try {
            var result = await apiRequest('/api/employees/delete.php', 'POST', { id: id });
            if (result.success) {
                showToast(result.message, 'success');
                loadEmployees();
            } else {
                showToast(result.message, 'error');
            }
        } catch (err) {
            showToast(err.message || '비활성화에 실패했습니다.', 'error');
        }
    }

    /**
     * Reactivate an employee.
     */
    async function reactivateEmployee(id, name) {
        var confirmed = await confirmAction(name + ' 직원을 재활성화하시겠습니까?');
        if (!confirmed) return;

        try {
            var result = await apiRequest('/api/employees/update.php', 'POST', {
                id: id,
                is_active: 1,
            });
            if (result.success) {
                showToast('직원이 재활성화되었습니다.', 'success');
                loadEmployees();
            } else {
                showToast(result.message, 'error');
            }
        } catch (err) {
            showToast(err.message || '재활성화에 실패했습니다.', 'error');
        }
    }

    /* ======================================================================
       Activity Log
       ====================================================================== */

    var logCurrentPage = 1;
    var logPageSize = 50;

    /**
     * Initialize activity log page.
     */
    function initLogPage() {
        loadLogs(1);
    }

    /**
     * Load activity logs with current filters.
     */
    async function loadLogs(page) {
        if (page) logCurrentPage = page;

        var params = new URLSearchParams();
        params.set('page', logCurrentPage);
        params.set('size', logPageSize);

        var from = document.getElementById('filter-from');
        var to = document.getElementById('filter-to');
        var userId = document.getElementById('filter-user');
        var action = document.getElementById('filter-action');

        if (from && from.value) params.set('from', from.value);
        if (to && to.value) params.set('to', to.value);
        if (userId && userId.value) params.set('user_id', userId.value);
        if (action && action.value) params.set('action', action.value);

        try {
            var result = await apiRequest('/api/employees/logs.php?' + params.toString());
            if (result.success) {
                renderLogTable(result.data);
                renderLogPagination(result.pagination);
            }
        } catch (err) {
            showToast('활동 로그를 불러오는데 실패했습니다.', 'error');
        }
    }

    /**
     * Render activity log table rows.
     */
    function renderLogTable(logs) {
        var tbody = document.getElementById('log-table-body');
        if (!tbody) return;

        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-12 text-center text-gray-400 text-sm">활동 기록이 없습니다.</td></tr>';
            return;
        }

        tbody.innerHTML = logs.map(function (log, index) {
            var actionBadge = getActionBadge(log.action);
            var targetLabel = log.target_type ? escapeHtml(log.target_type) + (log.target_id ? '#' + log.target_id : '') : '-';
            var hasDetail = log.old_value || log.new_value;
            var detailId = 'log-detail-' + index;

            var row = '<tr class="hover:bg-blue-50/50 transition-colors">' +
                '<td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">' + escapeHtml(log.created_at || '-') + '</td>' +
                '<td class="px-4 py-3 text-sm text-gray-700 font-medium">' + escapeHtml(log.user_name || '시스템') + '</td>' +
                '<td class="px-4 py-3 text-sm">' + actionBadge + '</td>' +
                '<td class="px-4 py-3 text-sm text-gray-500">' + targetLabel + '</td>' +
                '<td class="px-4 py-3 text-sm text-gray-600">' + escapeHtml(log.description || '-') + '</td>' +
                '<td class="px-4 py-3 text-sm text-center">' +
                    (hasDetail
                        ? '<button onclick="toggleLogDetail(\'' + detailId + '\')" class="btn btn-sm btn-ghost text-blue-500 hover:text-blue-700"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>'
                        : '<span class="text-gray-300">-</span>'
                    ) +
                '</td>' +
            '</tr>';

            if (hasDetail) {
                row += '<tr id="' + detailId + '" class="hidden">' +
                    '<td colspan="6" class="px-4 py-3 bg-gray-50">' +
                        '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">' +
                            (log.old_value
                                ? '<div><div class="font-semibold text-gray-600 mb-1">변경 전</div><pre class="bg-white border border-gray-200 rounded p-3 text-xs overflow-x-auto whitespace-pre-wrap break-all">' + formatJsonDisplay(log.old_value) + '</pre></div>'
                                : ''
                            ) +
                            (log.new_value
                                ? '<div><div class="font-semibold text-gray-600 mb-1">변경 후</div><pre class="bg-white border border-gray-200 rounded p-3 text-xs overflow-x-auto whitespace-pre-wrap break-all">' + formatJsonDisplay(log.new_value) + '</pre></div>'
                                : ''
                            ) +
                        '</div>' +
                    '</td>' +
                '</tr>';
            }

            return row;
        }).join('');
    }

    /**
     * Toggle log detail row visibility.
     */
    function toggleLogDetail(id) {
        var row = document.getElementById(id);
        if (row) {
            row.classList.toggle('hidden');
        }
    }

    /**
     * Format JSON string for display.
     */
    function formatJsonDisplay(str) {
        try {
            var obj = JSON.parse(str);
            return escapeHtml(JSON.stringify(obj, null, 2));
        } catch (e) {
            return escapeHtml(str);
        }
    }

    /**
     * Get styled badge for action type.
     */
    function getActionBadge(action) {
        var colors = {
            'LOGIN': 'badge-primary',
            'LOGOUT': 'badge-secondary',
            'USER_CREATE': 'badge-success',
            'USER_UPDATE': 'badge-warning',
            'USER_DELETE': 'badge-danger',
            'COMPANY_CREATE': 'badge-success',
            'COMPANY_UPDATE': 'badge-warning',
            'COMPANY_CARRYOVER': 'badge-primary',
            'SHOPPING_UPLOAD': 'badge-primary',
            'SHOPPING_STATUS': 'badge-warning',
            'SHOPPING_ASSIGN': 'badge-success',
            'SHOPPING_REVOKE': 'badge-danger',
            'PLACE_CREATE': 'badge-success',
            'PLACE_STATUS': 'badge-warning',
            'PLACE_ASSIGN': 'badge-success',
            'PLACE_REVOKE': 'badge-danger',
            'MEMO_CREATE': 'badge-primary',
            'EXCEL_DOWNLOAD': 'badge-secondary',
        };
        var cls = colors[action] || 'badge-secondary';
        return '<span class="badge ' + cls + '">' + escapeHtml(action) + '</span>';
    }

    /**
     * Render log pagination.
     */
    function renderLogPagination(pagination) {
        var container = document.getElementById('log-pagination');
        if (!container) return;

        var page = pagination.page;
        var totalPages = pagination.total_pages;
        var total = pagination.total;

        if (totalPages <= 1) {
            container.innerHTML = '<div class="text-sm text-gray-500 text-center">전체 ' + total + '건</div>';
            return;
        }

        var html = '<div class="flex flex-col sm:flex-row items-center justify-between gap-3">';

        // Page size selector
        html += '<div class="flex items-center space-x-2 text-sm text-gray-600">';
        html += '<label>표시개수:</label>';
        html += '<select class="form-select rounded-md border-gray-300 text-sm py-1.5 pl-3 pr-8" onchange="changeLogPageSize(this.value)">';
        [50, 100, 300, 500, 1000].forEach(function (size) {
            html += '<option value="' + size + '"' + (size === logPageSize ? ' selected' : '') + '>' + size + '개</option>';
        });
        html += '</select></div>';

        // Pagination links
        html += '<nav class="flex items-center space-x-1">';

        // Previous
        if (page > 1) {
            html += '<button onclick="loadLogs(' + (page - 1) + ')" class="pagination-btn"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>';
        } else {
            html += '<span class="pagination-btn pagination-btn-disabled"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></span>';
        }

        // Page numbers
        var range = 2;
        var startPage = Math.max(1, page - range);
        var endPage = Math.min(totalPages, page + range);

        if (startPage > 1) {
            html += '<button onclick="loadLogs(1)" class="pagination-btn">1</button>';
            if (startPage > 2) html += '<span class="pagination-ellipsis">...</span>';
        }

        for (var i = startPage; i <= endPage; i++) {
            html += '<button onclick="loadLogs(' + i + ')" class="pagination-btn' + (i === page ? ' pagination-btn-active' : '') + '">' + i + '</button>';
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += '<span class="pagination-ellipsis">...</span>';
            html += '<button onclick="loadLogs(' + totalPages + ')" class="pagination-btn">' + totalPages + '</button>';
        }

        // Next
        if (page < totalPages) {
            html += '<button onclick="loadLogs(' + (page + 1) + ')" class="pagination-btn"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>';
        } else {
            html += '<span class="pagination-btn pagination-btn-disabled"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>';
        }

        html += '</nav>';

        // Page info
        html += '<div class="text-sm text-gray-500">' + page + ' / ' + totalPages + ' 페이지 (전체 ' + total + '건)</div>';

        html += '</div>';
        container.innerHTML = html;
    }

    /**
     * Change log page size.
     */
    function changeLogPageSize(size) {
        logPageSize = parseInt(size);
        loadLogs(1);
    }

    /**
     * Reset log filters.
     */
    function resetLogFilters() {
        var from = document.getElementById('filter-from');
        var to = document.getElementById('filter-to');
        var userId = document.getElementById('filter-user');
        var action = document.getElementById('filter-action');

        if (from) from.value = '';
        if (to) to.value = '';
        if (userId) userId.value = '';
        if (action) action.value = '';

        loadLogs(1);
    }

    /* ======================================================================
       Export to Global Scope
       ====================================================================== */

    window.initEmployeePage = initEmployeePage;
    window.initLogPage = initLogPage;
    window.loadEmployees = loadEmployees;
    window.openCreateModal = openCreateModal;
    window.handleCreate = handleCreate;
    window.openEditModal = openEditModal;
    window.handleUpdate = handleUpdate;
    window.deactivateEmployee = deactivateEmployee;
    window.reactivateEmployee = reactivateEmployee;
    window.loadLogs = loadLogs;
    window.resetLogFilters = resetLogFilters;
    window.toggleLogDetail = toggleLogDetail;
    window.changeLogPageSize = changeLogPageSize;

})();

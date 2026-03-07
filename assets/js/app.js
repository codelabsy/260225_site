/**
 * ERP+CRM - Common JavaScript Utilities
 */

(function () {
    'use strict';

    /* ======================================================================
       CSRF Token Management
       ====================================================================== */

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /* ======================================================================
       API Request Wrapper
       ====================================================================== */

    /**
     * Fetch wrapper with JSON handling and automatic CSRF token.
     * @param {string} url    - Request URL
     * @param {string} method - HTTP method (GET, POST, PUT, DELETE)
     * @param {object|null} data - Request body data (for POST/PUT)
     * @returns {Promise<object>} Parsed JSON response
     */
    async function apiRequest(url, method = 'GET', data = null) {
        const options = {
            method: method.toUpperCase(),
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        };

        const csrfToken = getCsrfToken();
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken;
        }

        if (data && method.toUpperCase() !== 'GET') {
            if (csrfToken) {
                data._csrf_token = csrfToken;
            }
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);

        if (response.status === 401) {
            showToast('세션이 만료되었습니다. 다시 로그인하세요.', 'error');
            setTimeout(() => {
                window.location.href = '/login.php';
            }, 1500);
            throw new Error('Unauthorized');
        }

        if (response.status === 403) {
            showToast('접근 권한이 없습니다.', 'error');
            throw new Error('Forbidden');
        }

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || errorData.error || 'Request failed');
        }

        return response.json();
    }

    /* ======================================================================
       Toast Notifications
       ====================================================================== */

    /**
     * Show a toast notification.
     * @param {string} message - Notification message
     * @param {string} type    - 'success', 'error', 'warning', 'info'
     * @param {number} duration - Auto-dismiss duration in ms (default 3000)
     */
    function showToast(message, type = 'info', duration = 3000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const icons = {
            success: '<svg class="toast-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
            error: '<svg class="toast-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
            warning: '<svg class="toast-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>',
            info: '<svg class="toast-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        };

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = (icons[type] || icons.info) + '<span>' + escapeHtml(message) + '</span>';
        container.appendChild(toast);

        // Auto-dismiss
        setTimeout(() => {
            toast.style.animation = 'toast-out 0.3s ease-in forwards';
            toast.addEventListener('animationend', () => {
                toast.remove();
            });
        }, duration);

        // Click to dismiss
        toast.addEventListener('click', () => {
            toast.style.animation = 'toast-out 0.3s ease-in forwards';
            toast.addEventListener('animationend', () => {
                toast.remove();
            });
        });
    }

    /* ======================================================================
       Number & Date Formatting
       ====================================================================== */

    /**
     * Format number with thousands separator.
     * @param {number|string} num
     * @returns {string}
     */
    function formatNumber(num) {
        if (num === null || num === undefined || num === '') return '0';
        const n = typeof num === 'string' ? parseFloat(num.replace(/,/g, '')) : num;
        if (isNaN(n)) return '0';
        return n.toLocaleString('ko-KR');
    }

    /**
     * Format date string to localized format.
     * @param {string} dateStr - Date string (YYYY-MM-DD or ISO)
     * @returns {string}
     */
    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    /* ======================================================================
       Modal Control
       ====================================================================== */

    /**
     * Open a modal by its ID.
     * @param {string} id - Modal element ID
     */
    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close a modal by its ID.
     * @param {string} id - Modal element ID
     */
    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    /* ======================================================================
       Utility Functions
       ====================================================================== */

    /**
     * Debounce function execution.
     * @param {Function} fn    - Function to debounce
     * @param {number}   delay - Delay in ms (default 300)
     * @returns {Function}
     */
    function debounce(fn, delay = 300) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    /**
     * Show confirmation dialog.
     * @param {string} message - Confirmation message
     * @returns {Promise<boolean>}
     */
    function confirmAction(message) {
        return new Promise((resolve) => {
            resolve(window.confirm(message || '이 작업을 진행하시겠습니까?'));
        });
    }

    /**
     * Escape HTML entities for safe insertion.
     * @param {string} text
     * @returns {string}
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /* ======================================================================
       Table Checkbox Management
       ====================================================================== */

    let _lastCheckedIndex = null;

    function initTableCheckboxes() {
        // Check-all toggle
        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('table-check-all')) {
                const tableId = e.target.dataset.table;
                const checkboxes = document.querySelectorAll(
                    '.table-check-row[data-table="' + tableId + '"]'
                );
                checkboxes.forEach(function (cb) {
                    cb.checked = e.target.checked;
                });
            }
        });
    }

    /**
     * Handle checkbox cell click: toggle checkbox on cell click & shift+click range select.
     * Called from inline onclick on checkbox <td> elements.
     */
    function handleCheckboxCell(e, td) {
        const checkbox = td.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        // If the click was not directly on the checkbox, toggle it
        if (e.target !== checkbox) {
            checkbox.checked = !checkbox.checked;
        }

        // Shift+click range select
        const tbody = td.closest('tbody');
        if (!tbody) return;
        const allCheckboxes = Array.from(tbody.querySelectorAll('input[type="checkbox"].row-check, input[type="checkbox"].table-check-row'));
        const currentIndex = allCheckboxes.indexOf(checkbox);

        if (e.shiftKey && _lastCheckedIndex !== null && _lastCheckedIndex !== currentIndex) {
            const start = Math.min(_lastCheckedIndex, currentIndex);
            const end = Math.max(_lastCheckedIndex, currentIndex);
            const checked = checkbox.checked;
            for (let i = start; i <= end; i++) {
                allCheckboxes[i].checked = checked;
            }
        }

        _lastCheckedIndex = currentIndex;
    }

    /**
     * Get selected row IDs from a table.
     * @param {string} tableId
     * @returns {string[]}
     */
    function getSelectedRows(tableId) {
        const checkboxes = document.querySelectorAll(
            '.table-check-row[data-table="' + tableId + '"]:checked'
        );
        return Array.from(checkboxes).map(function (cb) {
            return cb.value;
        });
    }

    /* ======================================================================
       Logout Handler
       ====================================================================== */

    function initLogout() {
        const btn = document.getElementById('btn-logout');
        if (!btn) return;

        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const confirmed = await confirmAction('로그아웃 하시겠습니까?');
            if (!confirmed) return;

            try {
                await apiRequest('/api/auth/logout.php', 'POST');
            } catch (err) {
                // Ignore errors — redirect anyway
            }
            window.location.href = '/login.php';
        });
    }

    /* ======================================================================
       Mobile Menu Toggle
       ====================================================================== */

    function initMobileMenu() {
        const btn = document.getElementById('btn-mobile-menu');
        const menu = document.getElementById('mobile-menu');
        if (!btn || !menu) return;

        btn.addEventListener('click', function () {
            menu.classList.toggle('hidden');
        });
    }

    /* ======================================================================
       Global Error Handler
       ====================================================================== */

    window.addEventListener('unhandledrejection', function (event) {
        console.error('Unhandled promise rejection:', event.reason);
    });

    window.addEventListener('error', function (event) {
        console.error('Global error:', event.error);
    });

    /* ======================================================================
       Keyboard Shortcuts
       ====================================================================== */

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            // Close modals
            const openModals = document.querySelectorAll('.modal-overlay.active');
            openModals.forEach(function (modal) {
                if (modal.dataset.static !== 'true') {
                    closeModal(modal.id);
                }
            });

            // Close detail panels (place, shopping, erp)
            if (typeof PlaceModule !== 'undefined' && PlaceModule.closeDetail) {
                PlaceModule.closeDetail();
            }
            if (typeof closeDetailPanel === 'function') {
                closeDetailPanel();
            }
            if (typeof ERP !== 'undefined' && ERP.closeDetail) {
                ERP.closeDetail();
            }
        }
    });

    /* ======================================================================
       DOM Ready Initialization
       ====================================================================== */

    document.addEventListener('DOMContentLoaded', function () {
        initLogout();
        initMobileMenu();
        initTableCheckboxes();
    });

    /* ======================================================================
       Export to Global Scope
       ====================================================================== */

    window.apiRequest = apiRequest;
    window.showToast = showToast;
    window.formatNumber = formatNumber;
    window.formatDate = formatDate;
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.debounce = debounce;
    window.confirmAction = confirmAction;
    window.escapeHtml = escapeHtml;
    window.getSelectedRows = getSelectedRows;
    window._handleCheckboxCell = handleCheckboxCell;

})();

/**
 * Dashboard JavaScript
 * Loads summary widgets, charts, and recent activity.
 */

(function () {
    'use strict';

    const isAdmin = document.getElementById('chart-employee-comparison') !== null;
    let chartMonthlySales = null;
    let chartEmployeeComparison = null;
    let chartStatusShopping = null;
    let chartStatusPlace = null;

    /* ======================================================================
       Number Animation
       ====================================================================== */

    function animateNumber(element, target, duration) {
        if (!element) return;
        duration = duration || 800;
        const start = 0;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out cubic
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(start + (target - start) * eased);
            element.textContent = formatNumber(current) + '원';
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }

    function animateCount(element, target, suffix, duration) {
        if (!element) return;
        suffix = suffix || '건';
        duration = duration || 600;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(target * eased);
            element.textContent = formatNumber(current) + suffix;
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }

        requestAnimationFrame(update);
    }

    /* ======================================================================
       Widget Card Update
       ====================================================================== */

    function updateWidgetCard(containerId, title, value, icon, change, options) {
        var container = document.getElementById(containerId);
        if (!container) return;

        var colorMap = {
            blue: { bg: 'bg-blue-50', text: 'text-blue-600', icon: 'bg-blue-100 text-blue-600' },
            green: { bg: 'bg-green-50', text: 'text-green-600', icon: 'bg-green-100 text-green-600' },
            red: { bg: 'bg-red-50', text: 'text-red-600', icon: 'bg-red-100 text-red-600' },
            yellow: { bg: 'bg-amber-50', text: 'text-amber-600', icon: 'bg-amber-100 text-amber-600' },
            purple: { bg: 'bg-purple-50', text: 'text-purple-600', icon: 'bg-purple-100 text-purple-600' },
        };
        var color = (options && options.color) || 'blue';
        var subtitle = (options && options.subtitle) || '';
        var colors = colorMap[color] || colorMap.blue;

        var changeHtml = '';
        if (change) {
            var changeColor = 'text-gray-500';
            var changeIcon = '';
            if (change.indexOf('+') === 0) {
                changeColor = 'text-green-600';
                changeIcon = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>';
            } else if (change.indexOf('-') === 0) {
                changeColor = 'text-red-600';
                changeIcon = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>';
            }
            changeHtml = '<div class="mt-2 flex items-center gap-1 ' + changeColor + '">' + changeIcon + '<span class="text-xs font-medium">' + escapeHtml(change) + '</span></div>';
        }

        var subtitleHtml = subtitle ? '<p class="mt-1 text-xs text-gray-400">' + escapeHtml(subtitle) + '</p>' : '';

        var iconHtml = icon ? '<div class="flex-shrink-0 ml-4"><div class="w-10 h-10 rounded-lg ' + colors.icon + ' flex items-center justify-center">' + icon + '</div></div>' : '';

        container.innerHTML =
            '<div class="widget-card bg-white rounded-lg border border-gray-200 shadow-sm p-5">' +
            '  <div class="flex items-start justify-between">' +
            '    <div class="flex-1 min-w-0">' +
            '      <p class="text-sm font-medium text-gray-500 truncate">' + escapeHtml(title) + '</p>' +
            '      <p class="mt-2 text-2xl font-bold text-gray-900">' + escapeHtml(value) + '</p>' +
            subtitleHtml +
            changeHtml +
            '    </div>' +
            iconHtml +
            '  </div>' +
            '</div>';
    }

    /* ======================================================================
       Load Summary Data
       ====================================================================== */

    async function loadSummary() {
        try {
            var response = await apiRequest('/api/dashboard/summary.php');
            if (!response.success) return;
            var d = response.data;

            var salesIcon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            var marginIcon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>';
            var invoiceIcon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>';
            var dbIcon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>';
            var contractIcon = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';

            updateWidgetCard('widget-today-sales', '금일 매출', formatNumber(d.today_sales) + '원', salesIcon, '', {
                color: 'blue',
                subtitle: '계산서: ' + formatNumber(d.today_invoice) + '원'
            });
            updateWidgetCard('widget-month-sales', '금월 매출', formatNumber(d.month_sales) + '원', salesIcon, '', {
                color: 'green',
                subtitle: '계산서: ' + formatNumber(d.month_invoice) + '원'
            });
            updateWidgetCard('widget-today-margin', '금일 순마진', formatNumber(d.today_margin) + '원', marginIcon, '', {
                color: 'purple',
                subtitle: '실행비: ' + formatNumber(d.today_execution_cost) + '원'
            });
            updateWidgetCard('widget-month-margin', '금월 순마진', formatNumber(d.month_margin) + '원', marginIcon, '', {
                color: 'yellow',
                subtitle: '실행비: ' + formatNumber(d.month_execution_cost) + '원'
            });

            if (isAdmin) {
                updateWidgetCard('widget-invoice', '계산서 발행금액 (월)', formatNumber(d.month_invoice) + '원', invoiceIcon, '', { color: 'blue' });
                updateWidgetCard('widget-new-db', '금일 신규 DB', formatNumber(d.today_new_db) + '건', dbIcon, '', { color: 'green' });
                updateWidgetCard('widget-contracts', '금일 계약완료', formatNumber(d.today_contracts) + '건', contractIcon, '', { color: 'red' });
            } else {
                // Employee: update target & incentive
                updateTargetProgress(d);
                updateIncentive(d);
            }
        } catch (err) {
            console.error('Failed to load summary:', err);
        }
    }

    /* ======================================================================
       Employee Target & Incentive
       ====================================================================== */

    function updateTargetProgress(d) {
        var current = d.month_sales || 0;
        var target = d.target_amount || 0;
        var rate = target > 0 ? Math.min(Math.round(current / target * 100 * 10) / 10, 999) : 0;
        var remaining = Math.max(target - current, 0);

        var elCurrent = document.getElementById('target-current');
        var elAmount = document.getElementById('target-amount');
        var elBar = document.getElementById('target-bar');
        var elRate = document.getElementById('target-rate');
        var elRemaining = document.getElementById('target-remaining');

        if (elCurrent) elCurrent.textContent = formatNumber(current) + '원';
        if (elAmount) elAmount.textContent = '목표: ' + formatNumber(target) + '원';
        if (elBar) elBar.style.width = Math.min(rate, 100) + '%';
        if (elRate) elRate.textContent = rate + '%';
        if (elRemaining) elRemaining.textContent = '남은 금액: ' + formatNumber(remaining) + '원';

        // Color the bar
        if (elBar) {
            if (rate >= 100) {
                elBar.className = 'h-full bg-green-500 rounded-full transition-all duration-1000 ease-out';
            } else if (rate >= 70) {
                elBar.className = 'h-full bg-blue-600 rounded-full transition-all duration-1000 ease-out';
            } else if (rate >= 40) {
                elBar.className = 'h-full bg-amber-500 rounded-full transition-all duration-1000 ease-out';
            } else {
                elBar.className = 'h-full bg-red-500 rounded-full transition-all duration-1000 ease-out';
            }
        }
    }

    function updateIncentive(d) {
        var margin = d.month_margin || 0;
        var rate = d.incentive_rate || 0;
        var amount = Math.round(margin * rate / 100);

        var elMargin = document.getElementById('incentive-margin');
        var elRate = document.getElementById('incentive-rate');
        var elAmount = document.getElementById('incentive-amount');

        if (elMargin) elMargin.textContent = formatNumber(margin) + '원';
        if (elRate) elRate.textContent = rate + '%';
        if (elAmount) elAmount.textContent = formatNumber(amount) + '원';
    }

    /* ======================================================================
       Chart Helpers
       ====================================================================== */

    var chartColors = {
        blue: { bg: 'rgba(59, 130, 246, 0.1)', border: 'rgba(59, 130, 246, 1)' },
        green: { bg: 'rgba(34, 197, 94, 0.1)', border: 'rgba(34, 197, 94, 1)' },
        purple: { bg: 'rgba(168, 85, 247, 0.1)', border: 'rgba(168, 85, 247, 1)' },
        red: { bg: 'rgba(239, 68, 68, 0.1)', border: 'rgba(239, 68, 68, 1)' },
        amber: { bg: 'rgba(245, 158, 11, 0.1)', border: 'rgba(245, 158, 11, 1)' },
        cyan: { bg: 'rgba(6, 182, 212, 0.1)', border: 'rgba(6, 182, 212, 1)' },
    };

    var statusColors = [
        'rgba(59, 130, 246, 0.8)',   // blue
        'rgba(34, 197, 94, 0.8)',    // green
        'rgba(245, 158, 11, 0.8)',   // amber
        'rgba(239, 68, 68, 0.8)',    // red
        'rgba(168, 85, 247, 0.8)',   // purple
        'rgba(6, 182, 212, 0.8)',    // cyan
        'rgba(107, 114, 128, 0.8)',  // gray
    ];

    function currencyTooltip(context) {
        return context.dataset.label + ': ' + formatNumber(context.parsed.y) + '원';
    }

    /* ======================================================================
       Monthly Sales Chart
       ====================================================================== */

    async function loadMonthlySalesChart() {
        var year = document.getElementById('dashboard-year').value;
        try {
            var response = await apiRequest('/api/dashboard/chart.php?type=monthly_sales&year=' + year);
            if (!response.success) return;

            var labels = response.data.map(function (d) { return d.label; });
            var sales = response.data.map(function (d) { return d.sales; });
            var margins = response.data.map(function (d) { return d.margin; });

            var ctx = document.getElementById('chart-monthly-sales');
            if (!ctx) return;

            if (chartMonthlySales) chartMonthlySales.destroy();

            chartMonthlySales = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: '매출액',
                            data: sales,
                            borderColor: chartColors.blue.border,
                            backgroundColor: chartColors.blue.bg,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        },
                        {
                            label: '순마진',
                            data: margins,
                            borderColor: chartColors.green.border,
                            backgroundColor: chartColors.green.bg,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        tooltip: {
                            callbacks: { label: currencyTooltip },
                        },
                        legend: { position: 'top' },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (v) { return formatNumber(v) + '원'; },
                            },
                        },
                    },
                },
            });
        } catch (err) {
            console.error('Failed to load monthly sales chart:', err);
        }
    }

    /* ======================================================================
       Employee Comparison Chart (Admin)
       ====================================================================== */

    async function loadEmployeeChart() {
        if (!isAdmin) return;
        var year = document.getElementById('dashboard-year').value;
        try {
            var response = await apiRequest('/api/dashboard/chart.php?type=employee_comparison&year=' + year);
            if (!response.success) return;

            var labels = response.data.map(function (d) { return d.user_name; });
            var sales = response.data.map(function (d) { return d.sales; });
            var margins = response.data.map(function (d) { return d.margin; });

            var ctx = document.getElementById('chart-employee-comparison');
            if (!ctx) return;

            if (chartEmployeeComparison) chartEmployeeComparison.destroy();

            chartEmployeeComparison = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: '매출액',
                            data: sales,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1,
                        },
                        {
                            label: '순마진',
                            data: margins,
                            backgroundColor: 'rgba(34, 197, 94, 0.7)',
                            borderColor: 'rgba(34, 197, 94, 1)',
                            borderWidth: 1,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: { label: currencyTooltip },
                        },
                        legend: { position: 'top' },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (v) { return formatNumber(v) + '원'; },
                            },
                        },
                    },
                },
            });
        } catch (err) {
            console.error('Failed to load employee chart:', err);
        }
    }

    /* ======================================================================
       Status Distribution Charts (Admin)
       ====================================================================== */

    async function loadStatusCharts() {
        if (!isAdmin) return;
        try {
            var response = await apiRequest('/api/dashboard/chart.php?type=status_distribution');
            if (!response.success) return;

            // Shopping
            renderDoughnut('chart-status-shopping', response.data.shopping);
            // Place
            renderDoughnut('chart-status-place', response.data.place);
        } catch (err) {
            console.error('Failed to load status charts:', err);
        }
    }

    function renderDoughnut(canvasId, items) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return;

        // Destroy existing chart to prevent memory leak
        if (canvasId === 'chart-status-shopping' && chartStatusShopping) {
            chartStatusShopping.destroy();
        } else if (canvasId === 'chart-status-place' && chartStatusPlace) {
            chartStatusPlace.destroy();
        }

        var labels = items.map(function (d) { return d.status; });
        var data = items.map(function (d) { return d.count; });
        var colors = items.map(function (_, i) { return statusColors[i % statusColors.length]; });

        var chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 12, usePointStyle: true, pointStyle: 'circle' },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var total = context.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct = total > 0 ? Math.round(context.parsed / total * 100) : 0;
                                return context.label + ': ' + formatNumber(context.parsed) + '건 (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });

        // Store chart reference for cleanup
        if (canvasId === 'chart-status-shopping') chartStatusShopping = chart;
        else if (canvasId === 'chart-status-place') chartStatusPlace = chart;
    }

    /* ======================================================================
       Recent Activity Log (Admin)
       ====================================================================== */

    async function loadRecentActivity() {
        if (!isAdmin) return;
        var container = document.getElementById('recent-activity');
        if (!container) return;

        try {
            var response = await apiRequest('/api/dashboard/activity.php?limit=20');
            if (!response.success || !response.data || response.data.length === 0) {
                container.innerHTML = '<div class="p-8 text-center text-gray-400 text-sm">최근 활동이 없습니다</div>';
                return;
            }

            var html = '';
            response.data.forEach(function (log) {
                var actionBadge = getActionBadge(log.action);
                html += '<div class="px-4 py-3 hover:bg-gray-50 transition-colors">' +
                    '<div class="flex items-center justify-between">' +
                    '  <div class="flex items-center gap-2 min-w-0">' +
                    '    <span class="badge ' + actionBadge.class + ' text-[11px]">' + actionBadge.label + '</span>' +
                    '    <span class="text-sm text-gray-700 truncate">' + escapeHtml(log.user_name || '시스템') + '</span>' +
                    '  </div>' +
                    '  <span class="text-xs text-gray-400 flex-shrink-0 ml-2">' + escapeHtml(log.created_at || '') + '</span>' +
                    '</div>' +
                    (log.description ? '<p class="text-xs text-gray-500 mt-1 truncate">' + escapeHtml(log.description) + '</p>' : '') +
                    '</div>';
            });
            container.innerHTML = html;
        } catch (err) {
            console.error('Failed to load activity:', err);
            container.innerHTML = '<div class="p-8 text-center text-gray-400 text-sm">활동 로그를 불러올 수 없습니다</div>';
        }
    }

    function getActionBadge(action) {
        var badges = {
            LOGIN: { label: '로그인', class: 'badge-primary' },
            LOGOUT: { label: '로그아웃', class: 'badge-secondary' },
            COMPANY_CREATE: { label: '업체등록', class: 'badge-success' },
            COMPANY_UPDATE: { label: '업체수정', class: 'badge-warning' },
            SHOPPING_STATUS: { label: '쇼핑상태', class: 'badge-primary' },
            PLACE_STATUS: { label: '플레이스상태', class: 'badge-primary' },
            SHOPPING_UPLOAD: { label: '쇼핑업로드', class: 'badge-success' },
            EXCEL_DOWNLOAD: { label: '엑셀다운', class: 'badge-secondary' },
            MEMO_CREATE: { label: '메모', class: 'badge-warning' },
        };
        return badges[action] || { label: action, class: 'badge-secondary' };
    }

    /* ======================================================================
       Initialization
       ====================================================================== */

    function loadAllData() {
        loadSummary();
        loadMonthlySalesChart();
        if (isAdmin) {
            loadEmployeeChart();
            loadStatusCharts();
            loadRecentActivity();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadAllData();

        // Year filter change
        var yearSelect = document.getElementById('dashboard-year');
        if (yearSelect) {
            yearSelect.addEventListener('change', function () {
                loadMonthlySalesChart();
                if (isAdmin) {
                    loadEmployeeChart();
                }
            });
        }
    });

})();

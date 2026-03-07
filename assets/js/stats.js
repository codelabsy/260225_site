/**
 * Statistics Page JavaScript
 * Tab switching, charts, data tables, excel download.
 */

(function () {
    'use strict';

    var isAdmin = window.__isAdmin || false;
    var chartSalesTrend = null;
    var chartStatusShopping = null;
    var chartStatusPlace = null;
    var chartStatusCombined = null;
    var chartEmployee = null;

    var statusColors = [
        'rgba(59, 130, 246, 0.8)',
        'rgba(34, 197, 94, 0.8)',
        'rgba(245, 158, 11, 0.8)',
        'rgba(239, 68, 68, 0.8)',
        'rgba(168, 85, 247, 0.8)',
        'rgba(6, 182, 212, 0.8)',
        'rgba(107, 114, 128, 0.8)',
    ];

    /* ======================================================================
       Tab Switching
       ====================================================================== */

    function initTabs() {
        var tabs = document.querySelectorAll('.stats-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var targetTab = this.dataset.tab;

                // Update tab styles
                tabs.forEach(function (t) {
                    t.classList.remove('active', 'border-blue-600', 'text-blue-600');
                    t.classList.add('border-transparent', 'text-gray-500');
                });
                this.classList.add('active', 'border-blue-600', 'text-blue-600');
                this.classList.remove('border-transparent', 'text-gray-500');

                // Show/hide content
                document.querySelectorAll('.stats-tab-content').forEach(function (content) {
                    content.classList.add('hidden');
                });
                var targetContent = document.getElementById('tab-' + targetTab);
                if (targetContent) {
                    targetContent.classList.remove('hidden');
                }

                // Load data for newly visible tab
                if (targetTab === 'status') {
                    loadStatusData();
                } else if (targetTab === 'employee') {
                    loadEmployeeData();
                }
            });
        });
    }

    /* ======================================================================
       Sales Statistics
       ====================================================================== */

    async function loadSalesData() {
        var type = document.getElementById('sales-type').value;
        var start = document.getElementById('sales-start').value;
        var end = document.getElementById('sales-end').value;
        var userSelect = document.getElementById('sales-user');
        var userId = userSelect ? userSelect.value : '';

        var url = '/api/stats/sales.php?type=' + type +
            '&period_start=' + encodeURIComponent(start) +
            '&period_end=' + encodeURIComponent(end);
        if (userId) url += '&user_id=' + userId;

        try {
            var response = await apiRequest(url);
            if (!response.success) return;

            renderSalesChart(response.data, type);
            renderSalesTable(response.data, response.totals);
        } catch (err) {
            console.error('Failed to load sales data:', err);
            showToast('매출 데이터를 불러올 수 없습니다.', 'error');
        }
    }

    function renderSalesChart(data, type) {
        var ctx = document.getElementById('chart-sales-trend');
        if (!ctx) return;

        var labels = data.map(function (d) { return d.label || d.period; });
        var sales = data.map(function (d) { return d.sales; });
        var margins = data.map(function (d) { return d.margin; });
        var execCosts = data.map(function (d) { return d.execution_cost; });

        if (chartSalesTrend) chartSalesTrend.destroy();

        var chartType = type === 'daily' && data.length > 60 ? 'line' : (type === 'yearly' ? 'bar' : 'line');

        chartSalesTrend = new Chart(ctx, {
            type: chartType,
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '매출액',
                        data: sales,
                        borderColor: 'rgba(59, 130, 246, 1)',
                        backgroundColor: chartType === 'bar' ? 'rgba(59, 130, 246, 0.7)' : 'rgba(59, 130, 246, 0.1)',
                        fill: chartType === 'line',
                        tension: 0.3,
                        pointRadius: chartType === 'line' ? 3 : 0,
                    },
                    {
                        label: '순마진',
                        data: margins,
                        borderColor: 'rgba(34, 197, 94, 1)',
                        backgroundColor: chartType === 'bar' ? 'rgba(34, 197, 94, 0.7)' : 'rgba(34, 197, 94, 0.1)',
                        fill: chartType === 'line',
                        tension: 0.3,
                        pointRadius: chartType === 'line' ? 3 : 0,
                    },
                    {
                        label: '실행비',
                        data: execCosts,
                        borderColor: 'rgba(239, 68, 68, 1)',
                        backgroundColor: chartType === 'bar' ? 'rgba(239, 68, 68, 0.7)' : 'rgba(239, 68, 68, 0.1)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: chartType === 'line' ? 2 : 0,
                        borderDash: [5, 5],
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label + ': ' + formatNumber(ctx.parsed.y) + '원';
                            },
                        },
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
    }

    function renderSalesTable(data, totals) {
        var tbody = document.getElementById('sales-table-body');
        if (!tbody) return;

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">데이터가 없습니다</td></tr>';
            return;
        }

        var html = '';
        data.forEach(function (row) {
            html += '<tr class="border-b hover:bg-gray-50">' +
                '<td class="px-4 py-3 text-gray-700">' + escapeHtml(row.label || row.period) + '</td>' +
                '<td class="px-4 py-3 text-right font-medium">' + formatNumber(row.sales) + '</td>' +
                '<td class="px-4 py-3 text-right text-red-600">' + formatNumber(row.execution_cost) + '</td>' +
                '<td class="px-4 py-3 text-right text-gray-500">' + formatNumber(row.vat) + '</td>' +
                '<td class="px-4 py-3 text-right text-green-600 font-medium">' + formatNumber(row.margin) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(row.invoice) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(row.count) + '</td>' +
                '</tr>';
        });

        // Totals row
        if (totals) {
            html += '<tr class="bg-gray-50 font-semibold border-t-2">' +
                '<td class="px-4 py-3 text-gray-700">합계</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(totals.sales) + '</td>' +
                '<td class="px-4 py-3 text-right text-red-600">' + formatNumber(totals.execution_cost) + '</td>' +
                '<td class="px-4 py-3 text-right text-gray-500">' + formatNumber(totals.vat) + '</td>' +
                '<td class="px-4 py-3 text-right text-green-600">' + formatNumber(totals.margin) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(totals.invoice) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(totals.count) + '</td>' +
                '</tr>';
        }

        tbody.innerHTML = html;

        // Update totals display
        var totalsEl = document.getElementById('sales-totals');
        if (totalsEl && totals) {
            totalsEl.textContent = '총 매출: ' + formatNumber(totals.sales) + '원 | 순마진: ' + formatNumber(totals.margin) + '원 | ' + formatNumber(totals.count) + '건';
        }
    }

    /* ======================================================================
       Status Statistics (Admin)
       ====================================================================== */

    async function loadStatusData() {
        if (!isAdmin) return;

        try {
            var response = await apiRequest('/api/stats/status.php');
            if (!response.success) return;

            renderStatusChart('chart-status-shopping-stats', 'status-shopping-legend', response.data.shopping.items);
            renderStatusChart('chart-status-place-stats', 'status-place-legend', response.data.place.items);
            renderStatusChart('chart-status-combined', 'status-combined-legend', response.data.combined.items);
            renderStatusTable(response.data);
        } catch (err) {
            console.error('Failed to load status data:', err);
        }
    }

    function renderStatusChart(canvasId, legendId, items) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return;

        var labels = items.map(function (d) { return d.status; });
        var data = items.map(function (d) { return d.count; });
        var colors = items.map(function (_, i) { return statusColors[i % statusColors.length]; });

        // Destroy existing chart
        var existingChart = Chart.getChart(ctx);
        if (existingChart) existingChart.destroy();

        new Chart(ctx, {
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
                    legend: { display: false },
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

        // Custom legend
        var legendEl = document.getElementById(legendId);
        if (legendEl) {
            var total = data.reduce(function (a, b) { return a + b; }, 0);
            var html = '';
            items.forEach(function (item, i) {
                var pct = total > 0 ? Math.round(item.count / total * 100) : 0;
                html += '<div class="flex items-center justify-between text-sm">' +
                    '<div class="flex items-center gap-2">' +
                    '<span class="w-3 h-3 rounded-full inline-block" style="background-color:' + colors[i] + '"></span>' +
                    '<span class="text-gray-600">' + escapeHtml(item.status) + '</span>' +
                    '</div>' +
                    '<span class="font-medium">' + formatNumber(item.count) + '건 <span class="text-gray-400">(' + pct + '%)</span></span>' +
                    '</div>';
            });
            legendEl.innerHTML = html;
        }
    }

    function renderStatusTable(data) {
        var tbody = document.getElementById('status-table-body');
        if (!tbody) return;

        // Collect all unique statuses
        var allStatuses = {};
        data.shopping.items.forEach(function (item) {
            allStatuses[item.status] = { shopping: item.count, place: 0 };
        });
        data.place.items.forEach(function (item) {
            if (!allStatuses[item.status]) {
                allStatuses[item.status] = { shopping: 0, place: 0 };
            }
            allStatuses[item.status].place = item.count;
        });

        var html = '';
        var totalShopping = 0, totalPlace = 0;
        Object.keys(allStatuses).forEach(function (status) {
            var row = allStatuses[status];
            var total = row.shopping + row.place;
            totalShopping += row.shopping;
            totalPlace += row.place;
            html += '<tr class="border-b hover:bg-gray-50">' +
                '<td class="px-4 py-3 text-gray-700 font-medium">' + escapeHtml(status) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(row.shopping) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(row.place) + '</td>' +
                '<td class="px-4 py-3 text-right font-semibold">' + formatNumber(total) + '</td>' +
                '</tr>';
        });

        html += '<tr class="bg-gray-50 font-semibold border-t-2">' +
            '<td class="px-4 py-3">합계</td>' +
            '<td class="px-4 py-3 text-right">' + formatNumber(totalShopping) + '</td>' +
            '<td class="px-4 py-3 text-right">' + formatNumber(totalPlace) + '</td>' +
            '<td class="px-4 py-3 text-right">' + formatNumber(totalShopping + totalPlace) + '</td>' +
            '</tr>';

        tbody.innerHTML = html;
    }

    /* ======================================================================
       Employee Statistics (Admin)
       ====================================================================== */

    async function loadEmployeeData() {
        if (!isAdmin) return;

        var year = document.getElementById('emp-year').value;
        var month = document.getElementById('emp-month').value;

        var url = '/api/stats/employee.php?year=' + year;
        if (month) url += '&month=' + month;

        try {
            var response = await apiRequest(url);
            if (!response.success) return;

            renderEmployeeChart(response.data);
            renderEmployeeTable(response.data);
        } catch (err) {
            console.error('Failed to load employee data:', err);
        }
    }

    function renderEmployeeChart(data) {
        var ctx = document.getElementById('chart-employee-stats');
        if (!ctx) return;

        var labels = data.map(function (d) { return d.user_name; });
        var sales = data.map(function (d) { return d.sales; });
        var margins = data.map(function (d) { return d.margin; });

        if (chartEmployee) chartEmployee.destroy();

        chartEmployee = new Chart(ctx, {
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
                        callbacks: {
                            label: function (ctx) {
                                return ctx.dataset.label + ': ' + formatNumber(ctx.parsed.y) + '원';
                            },
                        },
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
    }

    function renderEmployeeTable(data) {
        var tbody = document.getElementById('employee-table-body');
        if (!tbody) return;

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">데이터가 없습니다</td></tr>';
            return;
        }

        var html = '';
        data.forEach(function (row) {
            var achieveClass = row.achievement_rate >= 100 ? 'text-green-600' :
                (row.achievement_rate >= 70 ? 'text-blue-600' :
                    (row.achievement_rate >= 40 ? 'text-amber-600' : 'text-red-600'));

            html += '<tr class="border-b hover:bg-gray-50">' +
                '<td class="px-4 py-3 font-medium text-gray-700">' + escapeHtml(row.user_name) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(row.sales) + '</td>' +
                '<td class="px-4 py-3 text-right text-green-600">' + formatNumber(row.margin) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(row.contract_count) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(row.call_count) + '</td>' +
                '<td class="px-4 py-3 text-right">' + row.incentive_rate + '%</td>' +
                '<td class="px-4 py-3 text-right text-purple-600">' + formatNumber(row.incentive_amount) + '</td>' +
                '<td class="px-4 py-3 text-right">' + formatNumber(row.target_amount) + '</td>' +
                '<td class="px-4 py-3 text-right font-semibold ' + achieveClass + '">' + row.achievement_rate + '%</td>' +
                '</tr>';
        });

        tbody.innerHTML = html;
    }

    /* ======================================================================
       User List (for Sales filter)
       ====================================================================== */

    async function loadUserList() {
        if (!isAdmin) return;
        var select = document.getElementById('sales-user');
        if (!select) return;

        try {
            var response = await apiRequest('/api/stats/employee.php?year=' + new Date().getFullYear());
            if (!response.success) return;

            response.data.forEach(function (emp) {
                var option = document.createElement('option');
                option.value = emp.user_id;
                option.textContent = emp.user_name;
                select.appendChild(option);
            });
        } catch (err) {
            console.error('Failed to load user list:', err);
        }
    }

    /* ======================================================================
       Excel Download
       ====================================================================== */

    function handleExcelDownload() {
        var btn = document.getElementById('btn-excel-download');
        if (!btn) return;

        btn.addEventListener('click', function () {
            // Determine active tab
            var activeTab = document.querySelector('.stats-tab.active');
            var tab = activeTab ? activeTab.dataset.tab : 'sales';

            var params = [];

            if (tab === 'sales') {
                params.push('type=stats');
                params.push('stats_type=' + (document.getElementById('sales-type').value || 'monthly'));
                params.push('period_start=' + (document.getElementById('sales-start').value || ''));
                params.push('period_end=' + (document.getElementById('sales-end').value || ''));
                var userSelect = document.getElementById('sales-user');
                if (userSelect && userSelect.value) {
                    params.push('user_id=' + userSelect.value);
                }
            } else if (tab === 'status') {
                // Export shopping and place data
                params.push('type=shopping');
            } else if (tab === 'employee') {
                params.push('type=stats');
                params.push('stats_type=monthly');
                var year = document.getElementById('emp-year').value;
                params.push('period_start=' + year + '-01-01');
                params.push('period_end=' + year + '-12-31');
            }

            window.location.href = '/api/export/excel.php?' + params.join('&');
        });
    }

    /* ======================================================================
       Initialization
       ====================================================================== */

    document.addEventListener('DOMContentLoaded', function () {
        initTabs();
        handleExcelDownload();

        if (isAdmin) {
            loadUserList();
        }

        // Sales search button
        var btnSalesSearch = document.getElementById('btn-sales-search');
        if (btnSalesSearch) {
            btnSalesSearch.addEventListener('click', loadSalesData);
        }

        // Employee search button
        var btnEmpSearch = document.getElementById('btn-emp-search');
        if (btnEmpSearch) {
            btnEmpSearch.addEventListener('click', loadEmployeeData);
        }

        // Initial load for sales tab
        loadSalesData();
    });

})();

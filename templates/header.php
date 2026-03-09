<?php
/**
 * Common header template.
 * Includes HTML5 doctype, CSS/JS CDNs, navigation bar.
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Permission.php';

$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$csrfToken = Permission::generateCsrfToken();

// Determine current page for active nav highlight
$currentPage = $currentPage ?? basename($_SERVER['SCRIPT_NAME'], '.php');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - 스마트미디어 ERP+CRM' : '스마트미디어 ERP+CRM' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- Top Navigation Bar -->
    <nav class="bg-slate-900 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-screen-2xl mx-auto px-4">
            <div class="flex items-center justify-between h-14">
                <!-- Logo -->
                <div class="flex-shrink-0">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="text-lg font-bold tracking-tight text-white hover:text-blue-300 transition-colors">
                        스마트미디어 ERP+CRM
                    </a>
                </div>

                <!-- Center Navigation -->
                <div class="hidden md:flex items-center space-x-1">
                    <a href="<?= BASE_URL ?>/dashboard.php"
                       class="nav-link <?= $currentPage === 'dashboard' ? 'nav-link-active' : '' ?>">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                        대시보드
                    </a>
                    <a href="<?= BASE_URL ?>/erp.php"
                       class="nav-link <?= $currentPage === 'erp' ? 'nav-link-active' : '' ?>">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        ERP매출
                    </a>
                    <a href="<?= BASE_URL ?>/shopping.php"
                       class="nav-link <?= $currentPage === 'shopping' ? 'nav-link-active' : '' ?>">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        쇼핑DB
                    </a>
                    <a href="<?= BASE_URL ?>/place.php"
                       class="nav-link <?= $currentPage === 'place' ? 'nav-link-active' : '' ?>">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        플레이스DB
                    </a>
                    <a href="<?= BASE_URL ?>/stats.php"
                       class="nav-link <?= $currentPage === 'stats' ? 'nav-link-active' : '' ?>">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        통계
                    </a>
                    <?php if ($isAdmin): ?>
                    <div class="w-px h-6 bg-slate-700 mx-1"></div>
                    <a href="<?= BASE_URL ?>/employees.php"
                       class="nav-link <?= $currentPage === 'employees' ? 'nav-link-active' : '' ?>">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                        직원관리
                    </a>
                    <a href="<?= BASE_URL ?>/activity-log.php"
                       class="nav-link <?= $currentPage === 'activity-log' ? 'nav-link-active' : '' ?>">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        활동로그
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Right: User Info + Logout -->
                <div class="flex items-center space-x-3">
                    <?php if ($currentUser): ?>
                    <div class="hidden sm:flex items-center space-x-2">
                        <div class="w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center text-xs font-medium">
                            <?= htmlspecialchars(mb_substr($currentUser['name'], 0, 1)) ?>
                        </div>
                        <span class="text-sm text-slate-300">
                            <?= htmlspecialchars($currentUser['name']) ?>
                            <?php if ($isAdmin): ?>
                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-medium bg-blue-600 rounded">관리자</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <button id="btn-logout" class="text-sm text-slate-400 hover:text-white transition-colors px-2 py-1 rounded hover:bg-slate-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                    <?php endif; ?>

                    <!-- Mobile menu button -->
                    <button id="btn-mobile-menu" class="md:hidden text-slate-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-800">
            <div class="px-4 py-3 space-y-1">
                <a href="<?= BASE_URL ?>/dashboard.php" class="mobile-nav-link <?= $currentPage === 'dashboard' ? 'mobile-nav-link-active' : '' ?>">대시보드</a>
                <a href="<?= BASE_URL ?>/erp.php" class="mobile-nav-link <?= $currentPage === 'erp' ? 'mobile-nav-link-active' : '' ?>">ERP매출</a>
                <a href="<?= BASE_URL ?>/shopping.php" class="mobile-nav-link <?= $currentPage === 'shopping' ? 'mobile-nav-link-active' : '' ?>">쇼핑DB</a>
                <a href="<?= BASE_URL ?>/place.php" class="mobile-nav-link <?= $currentPage === 'place' ? 'mobile-nav-link-active' : '' ?>">플레이스DB</a>
                <a href="<?= BASE_URL ?>/stats.php" class="mobile-nav-link <?= $currentPage === 'stats' ? 'mobile-nav-link-active' : '' ?>">통계</a>
                <?php if ($isAdmin): ?>
                <div class="border-t border-slate-800 my-2"></div>
                <a href="<?= BASE_URL ?>/employees.php" class="mobile-nav-link <?= $currentPage === 'employees' ? 'mobile-nav-link-active' : '' ?>">직원관리</a>
                <a href="<?= BASE_URL ?>/activity-log.php" class="mobile-nav-link <?= $currentPage === 'activity-log' ? 'mobile-nav-link-active' : '' ?>">활동로그</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 max-w-screen-2xl w-full mx-auto px-4 py-6">

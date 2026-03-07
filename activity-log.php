<?php
/**
 * Activity log entry point.
 * Admin only.
 */
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Permission.php';

Permission::requireAdmin();

$pageTitle = '활동로그';
$currentPage = 'activity-log';
require_once __DIR__ . '/pages/admin/logs.php';

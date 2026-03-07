<?php
/**
 * Admin employees entry point.
 * Redirects to /employees.php
 */
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Permission.php';

Permission::requireAdmin();

$pageTitle = '직원관리';
$currentPage = 'employees';
require_once __DIR__ . '/../pages/admin/employees.php';

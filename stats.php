<?php
/**
 * Statistics page entry point.
 */

require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Permission.php';

Permission::requireLogin();

$pageTitle = '통계';
$currentPage = 'stats';

require_once __DIR__ . '/pages/stats/index.php';

<?php
/**
 * Coupang DB entry point.
 */
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Permission.php';

Permission::requireLogin();

$pageTitle = '쿠팡DB';
require_once __DIR__ . '/pages/coupang/list.php';

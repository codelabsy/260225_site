<?php
/**
 * Shopping DB entry point - redirects to list page.
 */
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Permission.php';

Permission::requireLogin();

$pageTitle = '쇼핑DB';
require_once __DIR__ . '/pages/shopping/list.php';

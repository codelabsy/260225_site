<?php
/**
 * Place DB management page.
 * Entry point that loads the place list view.
 */

$pageTitle = '플레이스DB';

require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Permission.php';

Permission::requireLogin();

require_once __DIR__ . '/pages/place/list.php';

<?php
/**
 * ERP Sales Management entry point.
 * Redirects to the ERP list page.
 */

require_once __DIR__ . '/core/Auth.php';

if (!Auth::check()) {
    header('Location: /login.php');
    exit;
}

// Include the actual ERP list page
require __DIR__ . '/pages/erp/list.php';

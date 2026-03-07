<?php
/**
 * Entry point.
 * Redirects to dashboard if logged in, otherwise to login page.
 */

require_once __DIR__ . '/core/Auth.php';

if (Auth::check()) {
    header('Location: /dashboard.php');
} else {
    header('Location: /login.php');
}
exit;

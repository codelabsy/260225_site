<?php
/**
 * Session configuration and timeout management.
 */

require_once __DIR__ . '/constants.php';

ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the current session has expired.
 * Returns true if session is still valid, false if expired.
 */
function checkSessionTimeout(): bool
{
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }

    if ((time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

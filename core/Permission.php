<?php
/**
 * Permission and CSRF protection utilities.
 */

require_once __DIR__ . '/Auth.php';

class Permission
{
    /**
     * Redirect to login page if not authenticated.
     */
    public static function requireLogin(): void
    {
        if (!Auth::check()) {
            $base = defined('BASE_URL') ? BASE_URL : '';
            header('Location: ' . $base . '/login.php');
            exit;
        }
    }

    /**
     * Return 403 if not admin.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();

        if (!Auth::isAdmin()) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }

    /**
     * Check if current user can access data belonging to $targetUserId.
     * Admins can access all; employees can access only their own.
     */
    public static function canAccessData(int $targetUserId): bool
    {
        if (!Auth::check()) {
            return false;
        }

        if (Auth::isAdmin()) {
            return true;
        }

        return (int)$_SESSION['user_id'] === $targetUserId;
    }

    /**
     * Generate a CSRF token and store it in the session.
     */
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify a submitted CSRF token against the session token.
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

<?php
/**
 * Authentication handler.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/constants.php';

class Auth
{
    /**
     * Attempt login with username and password.
     * Returns user array on success, null on failure.
     */
    public static function login(string $username, string $password): ?array
    {
        $db = Database::getInstance();
        $user = $db->fetch(
            'SELECT * FROM users WHERE username = ? AND is_active = 1',
            [$username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();

        unset($user['password']);
        return $user;
    }

    /**
     * Destroy the current session.
     */
    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Check if user is currently logged in.
     */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']) && checkSessionTimeout();
    }

    /**
     * Get current user info from session.
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'name' => $_SESSION['name'],
            'role' => $_SESSION['role'],
        ];
    }

    /**
     * Check if current user is admin.
     */
    public static function isAdmin(): bool
    {
        return self::check() && ($_SESSION['role'] ?? '') === ROLE_ADMIN;
    }
}

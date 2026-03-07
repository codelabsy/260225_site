<?php
/**
 * User model.
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../config/constants.php';

class User
{
    /**
     * Find a user by ID.
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /**
     * Find a user by username.
     */
    public static function findByUsername(string $username): ?array
    {
        $db = Database::getInstance();
        return $db->fetch('SELECT * FROM users WHERE username = ?', [$username]);
    }

    /**
     * Get all users.
     */
    public static function all(bool $onlyActive = true): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT * FROM users';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';
        return $db->fetchAll($sql);
    }

    /**
     * Create a new user.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO users (username, password, name, role, position, phone, email, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['username'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                $data['name'],
                $data['role'] ?? ROLE_EMPLOYEE,
                $data['position'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['is_active'] ?? 1,
            ]
        );
        return (int) $db->lastInsertId();
    }

    /**
     * Update an existing user.
     */
    public static function update(int $id, array $data): int
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [];

        $allowed = ['name', 'role', 'position', 'phone', 'email', 'is_active'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        // Handle password update separately
        if (!empty($data['password'])) {
            $fields[] = 'password = ?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (empty($fields)) {
            return 0;
        }

        $fields[] = "updated_at = datetime('now', 'localtime')";
        $params[] = $id;

        return $db->execute(
            'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?',
            $params
        );
    }

    /**
     * Deactivate a user (soft delete).
     */
    public static function deactivate(int $id): int
    {
        $db = Database::getInstance();
        return $db->execute(
            "UPDATE users SET is_active = 0, updated_at = datetime('now', 'localtime') WHERE id = ?",
            [$id]
        );
    }

    /**
     * Get employees only (role = EMPLOYEE).
     */
    public static function getEmployees(bool $onlyActive = true): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT * FROM users WHERE role = ?';
        $params = [ROLE_EMPLOYEE];

        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        return $db->fetchAll($sql, $params);
    }
}

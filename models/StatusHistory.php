<?php
/**
 * Status history model.
 */

require_once __DIR__ . '/../core/Database.php';

class StatusHistory
{
    /**
     * Create a new status history record.
     */
    public static function create(string $targetType, int $targetId, int $userId, ?string $oldStatus, string $newStatus): int
    {
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO status_histories (target_type, target_id, user_id, old_status, new_status)
             VALUES (?, ?, ?, ?, ?)',
            [$targetType, $targetId, $userId, $oldStatus, $newStatus]
        );
        return (int) $db->lastInsertId();
    }

    /**
     * Get status history by target (newest first).
     */
    public static function getByTarget(string $targetType, int $targetId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT sh.*, u.name AS user_name
             FROM status_histories sh
             LEFT JOIN users u ON u.id = sh.user_id
             WHERE sh.target_type = ? AND sh.target_id = ?
             ORDER BY sh.created_at DESC',
            [$targetType, $targetId]
        );
    }
}

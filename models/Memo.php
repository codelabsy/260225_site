<?php
/**
 * Memo model (INSERT ONLY - no update, no delete).
 */

require_once __DIR__ . '/../core/Database.php';

class Memo
{
    /**
     * Create a new memo.
     */
    public static function create(string $targetType, int $targetId, int $userId, string $content): int
    {
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO memos (target_type, target_id, user_id, content)
             VALUES (?, ?, ?, ?)',
            [$targetType, $targetId, $userId, $content]
        );
        return (int) $db->lastInsertId();
    }

    /**
     * Get memos by target (newest first).
     */
    public static function getByTarget(string $targetType, int $targetId): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            'SELECT m.*, u.name AS user_name
             FROM memos m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.target_type = ? AND m.target_id = ?
             ORDER BY m.created_at DESC',
            [$targetType, $targetId]
        );
    }
}

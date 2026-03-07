<?php
/**
 * Activity log recorder.
 */

require_once __DIR__ . '/Database.php';

class Logger
{
    /**
     * Record an activity log entry.
     */
    public static function log(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $description = null,
        ?string $oldValue = null,
        ?string $newValue = null
    ): void {
        $userId = $_SESSION['user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO activity_logs (user_id, action, target_type, target_id, description, old_value, new_value, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\', \'localtime\'))',
            [$userId, $action, $targetType, $targetId, $description, $oldValue, $newValue, $ipAddress]
        );
    }
}

<?php
/**
 * Activity log model (read-only for admin log viewing).
 */

require_once __DIR__ . '/../core/Database.php';

class ActivityLog
{
    /**
     * Get all activity logs with filters.
     */
    public static function all(array $filters = []): array
    {
        $db = Database::getInstance();
        $where = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'al.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['period'])) {
            $period = $filters['period'];
            if (!empty($period['from'])) {
                $where[] = 'al.created_at >= ?';
                $params[] = $period['from'];
            }
            if (!empty($period['to'])) {
                $where[] = 'al.created_at <= ?';
                $params[] = $period['to'] . ' 23:59:59';
            }
        }

        $whereClause = '';
        if (!empty($where)) {
            $whereClause = 'WHERE ' . implode(' AND ', $where);
        }

        // Pagination
        $limit = '';
        if (isset($filters['limit'])) {
            $limit = 'LIMIT ' . (int) $filters['limit'];
            if (isset($filters['offset'])) {
                $limit .= ' OFFSET ' . (int) $filters['offset'];
            }
        }

        return $db->fetchAll(
            "SELECT al.*, u.name AS user_name, u.username AS user_username
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             $whereClause
             ORDER BY al.created_at DESC
             $limit",
            $params
        );
    }
}

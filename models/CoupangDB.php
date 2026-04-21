<?php
/**
 * Coupang DB model.
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../config/constants.php';

class CoupangDB
{
    /**
     * Find a record by ID.
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetch(
            'SELECT s.*, u.name AS user_name
             FROM coupang_db s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.id = ?',
            [$id]
        );
    }

    /**
     * Get all records with filters.
     */
    public static function all(array $filters = []): array
    {
        return self::buildQuery($filters);
    }

    /** All allowed insert/update fields */
    private static array $allFields = [
        'user_id', 'company_name', 'representative', 'phone', 'mobile_phone',
        'status', 'upload_history_id',
        'keyword', 'product_name', 'product_id',
        'email', 'business_number', 'address', 'address_detail',
        'rating_count', 'recommend_count', 'not_recommend_count', 'recommend_ratio',
        'collected_at', 'notes',
    ];

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $fields = [];
        $placeholders = [];
        $values = [];

        foreach (self::$allFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $data[$field];
            }
        }

        if (!in_array('phone', $fields)) {
            $fields[] = 'phone';
            $placeholders[] = '?';
            $values[] = $data['phone'] ?? '';
        }

        if (!in_array('status', $fields)) {
            $fields[] = 'status';
            $placeholders[] = '?';
            $values[] = $data['status'] ?? COUPANG_DEFAULT_STATUS;
        }

        $db->execute(
            'INSERT INTO coupang_db (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')',
            $values
        );
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [];

        $allowed = array_diff(self::$allFields, ['user_id', 'status', 'upload_history_id']);
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return 0;
        }

        $fields[] = "updated_at = datetime('now', 'localtime')";
        $params[] = $id;

        return $db->execute(
            'UPDATE coupang_db SET ' . implode(', ', $fields) . ' WHERE id = ?',
            $params
        );
    }

    public static function updateStatus(int $id, string $status, int $userId): int
    {
        if (!in_array($status, COUPANG_STATUSES)) {
            throw new InvalidArgumentException('Invalid status: ' . $status);
        }

        $db = Database::getInstance();
        $current = self::find($id);
        if (!$current) {
            throw new Exception('Record not found');
        }

        $oldStatus = $current['status'];

        $db->beginTransaction();
        try {
            $affected = $db->execute(
                "UPDATE coupang_db SET status = ?, updated_at = datetime('now', 'localtime') WHERE id = ?",
                [$status, $id]
            );

            $db->execute(
                "INSERT INTO status_histories (target_type, target_id, user_id, old_status, new_status)
                 VALUES (?, ?, ?, ?, ?)",
                [TARGET_COUPANG, $id, $userId, $oldStatus, $status]
            );

            $db->commit();
            return $affected;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function assign(array $ids, int $userId, int $adminId): int
    {
        if (empty($ids)) {
            return 0;
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $count = 0;
            foreach ($ids as $id) {
                $id = (int) $id;
                $current = self::find($id);
                $fromUserId = $current ? $current['user_id'] : null;

                $db->execute(
                    "UPDATE coupang_db SET user_id = ?, updated_at = datetime('now', 'localtime') WHERE id = ?",
                    [$userId, $id]
                );

                $db->execute(
                    'INSERT INTO db_assignments (db_type, db_id, from_user_id, to_user_id, action, admin_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [TARGET_COUPANG, $id, $fromUserId, $userId, 'assign', $adminId]
                );

                $count++;
            }

            $db->commit();
            return $count;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function revoke(array $ids, int $adminId): int
    {
        if (empty($ids)) {
            return 0;
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $count = 0;
            foreach ($ids as $id) {
                $id = (int) $id;
                $current = self::find($id);
                $fromUserId = $current ? $current['user_id'] : null;

                $db->execute(
                    "UPDATE coupang_db SET user_id = ?, updated_at = datetime('now', 'localtime') WHERE id = ?",
                    [null, $id]
                );

                $db->execute(
                    'INSERT INTO db_assignments (db_type, db_id, from_user_id, to_user_id, action, admin_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [TARGET_COUPANG, $id, $fromUserId, null, 'revoke', $adminId]
                );

                $count++;
            }

            $db->commit();
            return $count;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function delete(int $id): int
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM coupang_db WHERE id = ?', [$id]);
    }

    public static function checkDuplicate(string $phone): bool
    {
        $db = Database::getInstance();
        $result = $db->fetch(
            'SELECT id FROM coupang_db WHERE phone = ?',
            [$phone]
        );
        return $result !== null;
    }

    private static function applyFilters(array &$where, array &$params, array $filters): void
    {
        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $where[] = 's.user_id IS NULL';
        } elseif (!empty($filters['user_id'])) {
            $where[] = 's.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(s.company_name LIKE ? OR s.representative LIKE ? OR s.phone LIKE ? OR s.product_name LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['period'])) {
            $period = $filters['period'];
            if (!empty($period['from'])) {
                $where[] = 's.created_at >= ?';
                $params[] = $period['from'];
            }
            if (!empty($period['to'])) {
                $where[] = 's.created_at <= ?';
                $params[] = $period['to'] . ' 23:59:59';
            }
        }
    }

    private static function buildQuery(array $filters = []): array
    {
        $db = Database::getInstance();
        $where = [];
        $params = [];

        self::applyFilters($where, $params, $filters);

        $whereClause = '';
        if (!empty($where)) {
            $whereClause = 'WHERE ' . implode(' AND ', $where);
        }

        $sortColumn = $filters['sort'] ?? 's.created_at';
        $allowedSorts = ['s.created_at', 's.company_name', 's.representative', 's.phone', 's.status', 's.updated_at'];
        if (!in_array($sortColumn, $allowedSorts)) {
            $sortColumn = 's.created_at';
        }
        $order = (isset($filters['order']) && strtoupper($filters['order']) === 'ASC') ? 'ASC' : 'DESC';

        $limit = '';
        if (isset($filters['limit'])) {
            $limit = 'LIMIT ' . (int) $filters['limit'];
            if (isset($filters['offset'])) {
                $limit .= ' OFFSET ' . (int) $filters['offset'];
            }
        }

        return $db->fetchAll(
            "SELECT s.*, u.name AS user_name
             FROM coupang_db s
             LEFT JOIN users u ON u.id = s.user_id
             $whereClause
             ORDER BY $sortColumn $order
             $limit",
            $params
        );
    }
}

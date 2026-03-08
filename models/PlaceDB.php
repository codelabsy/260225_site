<?php
/**
 * Place DB model.
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../config/constants.php';

class PlaceDB
{
    /**
     * Find a record by ID.
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetch(
            'SELECT p.*, u.name AS user_name
             FROM place_db p
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.id = ?',
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

    /**
     * Create a new record.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO place_db (user_id, company_name, phone, region, register_date, source, status, initial_memo, request_content, contract_date, payment_amount, old_system_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'] ?? null,
                $data['company_name'],
                $data['phone'],
                $data['region'] ?? null,
                $data['register_date'] ?? date('Y-m-d'),
                $data['source'] ?? null,
                $data['status'] ?? PLACE_DEFAULT_STATUS,
                $data['initial_memo'] ?? null,
                $data['request_content'] ?? null,
                $data['contract_date'] ?? null,
                $data['payment_amount'] ?? 0,
                $data['old_system_id'] ?? null,
            ]
        );
        return (int) $db->lastInsertId();
    }

    /**
     * Update a record.
     */
    public static function update(int $id, array $data): int
    {
        $db = Database::getInstance();
        $fields = [];
        $params = [];

        $allowed = ['company_name', 'phone', 'region', 'register_date', 'source', 'initial_memo', 'request_content', 'contract_date', 'payment_amount'];
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
            'UPDATE place_db SET ' . implode(', ', $fields) . ' WHERE id = ?',
            $params
        );
    }

    /**
     * Update status and record history.
     */
    public static function updateStatus(int $id, string $status, int $userId): int
    {
        if (!in_array($status, PLACE_STATUSES)) {
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
                "UPDATE place_db SET status = ?, updated_at = datetime('now', 'localtime') WHERE id = ?",
                [$status, $id]
            );

            $db->execute(
                'INSERT INTO status_histories (target_type, target_id, user_id, old_status, new_status)
                 VALUES (?, ?, ?, ?, ?)',
                [TARGET_PLACE, $id, $userId, $oldStatus, $status]
            );

            $db->commit();
            return $affected;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Assign records to a user.
     */
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
                    "UPDATE place_db SET user_id = ?, updated_at = datetime('now', 'localtime') WHERE id = ?",
                    [$userId, $id]
                );

                $db->execute(
                    'INSERT INTO db_assignments (db_type, db_id, from_user_id, to_user_id, action, admin_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [TARGET_PLACE, $id, $fromUserId, $userId, 'assign', $adminId]
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

    /**
     * Revoke records (unassign from user).
     */
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
                    "UPDATE place_db SET user_id = ?, updated_at = datetime('now', 'localtime') WHERE id = ?",
                    [null, $id]
                );

                $db->execute(
                    'INSERT INTO db_assignments (db_type, db_id, from_user_id, to_user_id, action, admin_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [TARGET_PLACE, $id, $fromUserId, null, 'revoke', $adminId]
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

    /**
     * Get records by user ID.
     */
    public static function getByUser(int $userId, array $filters = []): array
    {
        $filters['user_id'] = $userId;
        return self::buildQuery($filters);
    }

    /**
     * Apply common filters.
     */
    private static function applyFilters(array &$where, array &$params, array $filters): void
    {
        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $where[] = 'p.user_id IS NULL';
        } elseif (!empty($filters['user_id'])) {
            $where[] = 'p.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'p.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['region'])) {
            $where[] = 'p.region = ?';
            $params[] = $filters['region'];
        }

        if (!empty($filters['source'])) {
            $where[] = 'p.source = ?';
            $params[] = $filters['source'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(p.company_name LIKE ? OR p.phone LIKE ? OR p.region LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['period'])) {
            $period = $filters['period'];
            if (!empty($period['from'])) {
                $where[] = 'p.register_date >= ?';
                $params[] = $period['from'];
            }
            if (!empty($period['to'])) {
                $where[] = 'p.register_date <= ?';
                $params[] = $period['to'];
            }
        }
    }

    /**
     * Build and execute query with filters.
     */
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

        // Sorting
        $sortColumn = $filters['sort'] ?? 'p.register_date';
        $allowedSorts = ['p.register_date', 'p.company_name', 'p.phone', 'p.status', 'p.region', 'p.created_at', 'p.updated_at'];
        if (!in_array($sortColumn, $allowedSorts)) {
            $sortColumn = 'p.register_date';
        }
        $order = (isset($filters['order']) && strtoupper($filters['order']) === 'ASC') ? 'ASC' : 'DESC';

        // Pagination
        $limit = '';
        if (isset($filters['limit'])) {
            $limit = 'LIMIT ' . (int) $filters['limit'];
            if (isset($filters['offset'])) {
                $limit .= ' OFFSET ' . (int) $filters['offset'];
            }
        }

        return $db->fetchAll(
            "SELECT p.*, u.name AS user_name
             FROM place_db p
             LEFT JOIN users u ON u.id = p.user_id
             $whereClause
             ORDER BY $sortColumn $order
             $limit",
            $params
        );
    }
}

<?php
/**
 * Shopping DB model.
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../config/constants.php';

class ShoppingDB
{
    /**
     * Find a record by ID.
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetch(
            'SELECT s.*, u.name AS user_name
             FROM shopping_db s
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

    /**
     * Create a new record.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO shopping_db (user_id, company_name, contact_name, phone, status, upload_history_id, extra_field_1, extra_field_2, extra_field_3)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'] ?? null,
                $data['company_name'] ?? null,
                $data['contact_name'] ?? null,
                $data['phone'],
                $data['status'] ?? SHOPPING_DEFAULT_STATUS,
                $data['upload_history_id'] ?? null,
                $data['extra_field_1'] ?? null,
                $data['extra_field_2'] ?? null,
                $data['extra_field_3'] ?? null,
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

        $allowed = ['company_name', 'contact_name', 'phone', 'extra_field_1', 'extra_field_2', 'extra_field_3'];
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
            'UPDATE shopping_db SET ' . implode(', ', $fields) . ' WHERE id = ?',
            $params
        );
    }

    /**
     * Update status and record history.
     */
    public static function updateStatus(int $id, string $status, int $userId): int
    {
        if (!in_array($status, SHOPPING_STATUSES)) {
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
                "UPDATE shopping_db SET status = ?, updated_at = datetime('now', 'localtime') WHERE id = ?",
                [$status, $id]
            );

            // Record status history
            $db->execute(
                "INSERT INTO status_histories (target_type, target_id, user_id, old_status, new_status)
                 VALUES (?, ?, ?, ?, ?)",
                [TARGET_SHOPPING, $id, $userId, $oldStatus, $status]
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
                    "UPDATE shopping_db SET user_id = ?, updated_at = datetime('now', 'localtime') WHERE id = ?",
                    [$userId, $id]
                );

                $db->execute(
                    'INSERT INTO db_assignments (db_type, db_id, from_user_id, to_user_id, action, admin_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [TARGET_SHOPPING, $id, $fromUserId, $userId, 'assign', $adminId]
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
                    "UPDATE shopping_db SET user_id = ?, updated_at = datetime('now', 'localtime') WHERE id = ?",
                    [null, $id]
                );

                $db->execute(
                    'INSERT INTO db_assignments (db_type, db_id, from_user_id, to_user_id, action, admin_user_id)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [TARGET_SHOPPING, $id, $fromUserId, null, 'revoke', $adminId]
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
     * Bulk create from Excel upload.
     */
    public static function bulkCreate(array $rows, int $userId, int $uploadHistoryId): array
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $successCount = 0;
            $duplicateCount = 0;

            foreach ($rows as $row) {
                $phone = $row['phone'] ?? '';
                if (empty($phone)) {
                    continue;
                }

                // Check duplicate
                if (self::checkDuplicate($phone)) {
                    $duplicateCount++;
                    continue;
                }

                $db->execute(
                    'INSERT INTO shopping_db (user_id, company_name, contact_name, phone, status, upload_history_id, extra_field_1, extra_field_2, extra_field_3)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $row['user_id'] ?? $userId,
                        $row['company_name'] ?? null,
                        $row['contact_name'] ?? null,
                        $phone,
                        SHOPPING_DEFAULT_STATUS,
                        $uploadHistoryId,
                        $row['extra_field_1'] ?? null,
                        $row['extra_field_2'] ?? null,
                        $row['extra_field_3'] ?? null,
                    ]
                );
                $successCount++;
            }

            // Update upload history
            $db->execute(
                'UPDATE upload_histories SET total_count = ?, duplicate_count = ?, success_count = ? WHERE id = ?',
                [count($rows), $duplicateCount, $successCount, $uploadHistoryId]
            );

            $db->commit();
            return [
                'total' => count($rows),
                'success' => $successCount,
                'duplicate' => $duplicateCount,
            ];
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Check if phone number already exists.
     */
    public static function checkDuplicate(string $phone): bool
    {
        $db = Database::getInstance();
        $result = $db->fetch(
            'SELECT id FROM shopping_db WHERE phone = ?',
            [$phone]
        );
        return $result !== null;
    }

    /**
     * Apply common filters.
     */
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
            $where[] = '(s.company_name LIKE ? OR s.contact_name LIKE ? OR s.phone LIKE ?)';
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
        $sortColumn = $filters['sort'] ?? 's.created_at';
        $allowedSorts = ['s.created_at', 's.company_name', 's.contact_name', 's.phone', 's.status', 's.updated_at'];
        if (!in_array($sortColumn, $allowedSorts)) {
            $sortColumn = 's.created_at';
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
            "SELECT s.*, u.name AS user_name
             FROM shopping_db s
             LEFT JOIN users u ON u.id = s.user_id
             $whereClause
             ORDER BY $sortColumn $order
             $limit",
            $params
        );
    }
}

<?php
/**
 * Company model.
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../config/constants.php';

class Company
{
    /**
     * Find a company by ID (with details and user name).
     */
    public static function find(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetch(
            'SELECT c.*, cd.*, c.id AS id, c.created_at AS created_at, c.updated_at AS updated_at,
                    u.name AS user_name
             FROM companies c
             LEFT JOIN company_details cd ON cd.company_id = c.id
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.id = ?',
            [$id]
        );
    }

    /**
     * Get all companies with filters.
     */
    public static function all(array $filters = []): array
    {
        return self::buildQuery($filters);
    }

    /**
     * Create a new company with details.
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->execute(
                'INSERT INTO companies (user_id, register_date, product_name, company_name, payment_amount, invoice_amount, execution_cost, vat_included, registrant_position)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $data['user_id'] ?? null,
                    $data['register_date'],
                    $data['product_name'],
                    $data['company_name'],
                    $data['payment_amount'] ?? 0,
                    $data['invoice_amount'] ?? 0,
                    $data['execution_cost'] ?? 0,
                    isset($data['vat_included']) ? (int)$data['vat_included'] : 1,
                    $data['registrant_position'] ?? null,
                ]
            );
            $companyId = (int) $db->lastInsertId();

            // Insert company details
            $detailFields = [
                'company_id', 'sales_register_date', 'work_start_date', 'work_end_date',
                'contract_start', 'contract_end', 'contract_term', 'business_name', 'ceo_name',
                'phone', 'payment_type', 'business_number', 'work_keywords', 'work_content',
                'email', 'naver_account', 'detail_execution_cost', 'outsourcing_cost', 'keyword_cost',
                'pg_name', 'tax_type', 'bank', 'card_number', 'card_owner', 'card_owner_birth',
                'card_validity', 'card_installment', 'making_type', 'making_bigo', 'vpn_id',
                'blog_id', 'blog_pass', 'naver_ad_id', 'naver_ad_pass', 'daum_ad_id', 'daum_ad_pass',
                'target_url', 'making_url', 'created_level', 'updated_by', 'updated_level', 'old_system_id',
            ];
            $detailValues = [$companyId];
            foreach (array_slice($detailFields, 1) as $f) {
                $detailValues[] = $data[$f] ?? null;
            }
            // Set numeric defaults
            foreach (['detail_execution_cost', 'outsourcing_cost', 'keyword_cost'] as $numField) {
                $idx = array_search($numField, $detailFields);
                if ($idx !== false && $detailValues[$idx] === null) {
                    $detailValues[$idx] = 0;
                }
            }
            $placeholders = implode(', ', array_fill(0, count($detailFields), '?'));
            $fieldList = implode(', ', $detailFields);
            $db->execute(
                "INSERT INTO company_details ($fieldList) VALUES ($placeholders)",
                $detailValues
            );

            $db->commit();
            return $companyId;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Update a company and its details.
     */
    public static function update(int $id, array $data): int
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            // Update companies table
            $companyFields = [];
            $companyParams = [];
            $allowedCompany = ['user_id', 'register_date', 'product_name', 'company_name', 'payment_amount', 'invoice_amount', 'execution_cost', 'vat_included', 'registrant_position'];

            foreach ($allowedCompany as $field) {
                if (array_key_exists($field, $data)) {
                    $companyFields[] = "$field = ?";
                    $companyParams[] = $data[$field];
                }
            }

            $affected = 0;
            if (!empty($companyFields)) {
                // 금액 관련 필드가 변경되면 vat/invoice_amount/net_margin 재계산
                $needsRecalc = false;
                foreach (['payment_amount', 'execution_cost', 'vat_included'] as $f) {
                    if (array_key_exists($f, $data)) {
                        $needsRecalc = true;
                        break;
                    }
                }

                if ($needsRecalc) {
                    // 현재 DB 값을 기준으로 변경될 값 병합
                    $current = $db->fetch('SELECT payment_amount, execution_cost, vat_included FROM companies WHERE id = ?', [$id]);
                    $payment = array_key_exists('payment_amount', $data) ? (float)$data['payment_amount'] : (float)$current['payment_amount'];
                    $execution = array_key_exists('execution_cost', $data) ? (float)$data['execution_cost'] : (float)$current['execution_cost'];
                    $vatIncluded = array_key_exists('vat_included', $data) ? (int)$data['vat_included'] : (int)$current['vat_included'];

                    $vat = $vatIncluded ? round($execution / 11) : 0;
                    $invoiceAmt = $vatIncluded ? $payment - round($payment / 11) : $payment;
                    $netMargin = $payment - $execution - $vat;

                    $companyFields[] = "vat = ?";
                    $companyParams[] = $vat;
                    $companyFields[] = "invoice_amount = ?";
                    $companyParams[] = $invoiceAmt;
                    $companyFields[] = "net_margin = ?";
                    $companyParams[] = $netMargin;
                }

                $companyFields[] = "updated_at = datetime('now', 'localtime')";
                $companyParams[] = $id;
                $affected = $db->execute(
                    'UPDATE companies SET ' . implode(', ', $companyFields) . ' WHERE id = ?',
                    $companyParams
                );
            }

            // Update company_details table
            $detailFields = [];
            $detailParams = [];
            $allowedDetail = [
                'sales_register_date', 'work_start_date', 'work_end_date', 'contract_start', 'contract_end',
                'contract_term', 'business_name', 'ceo_name', 'phone', 'payment_type', 'business_number',
                'work_keywords', 'work_content', 'email', 'naver_account', 'detail_execution_cost',
                'outsourcing_cost', 'keyword_cost', 'pg_name', 'tax_type', 'bank', 'card_number',
                'card_owner', 'card_owner_birth', 'card_validity', 'card_installment', 'making_type',
                'making_bigo', 'vpn_id', 'blog_id', 'blog_pass', 'naver_ad_id', 'naver_ad_pass',
                'daum_ad_id', 'daum_ad_pass', 'target_url', 'making_url',
            ];

            foreach ($allowedDetail as $field) {
                if (array_key_exists($field, $data)) {
                    $detailFields[] = "$field = ?";
                    $detailParams[] = $data[$field];
                }
            }

            if (!empty($detailFields)) {
                $detailFields[] = "updated_at = datetime('now', 'localtime')";
                $detailParams[] = $id;
                $db->execute(
                    'UPDATE company_details SET ' . implode(', ', $detailFields) . ' WHERE company_id = ?',
                    $detailParams
                );
            }

            $db->commit();
            return $affected;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Soft-delete a company (set is_active = 0).
     */
    public static function delete(int $id): int
    {
        $db = Database::getInstance();
        return $db->execute(
            "UPDATE companies SET is_active = 0, updated_at = datetime('now', 'localtime') WHERE id = ?",
            [$id]
        );
    }

    /**
     * Carry over a company: copy and reset contract period.
     */
    public static function carryOver(int $id): int
    {
        $db = Database::getInstance();
        $original = self::find($id);
        if (!$original) {
            throw new Exception('Company not found');
        }

        $db->beginTransaction();
        try {
            $db->execute(
                'INSERT INTO companies (user_id, register_date, product_name, company_name, payment_amount, invoice_amount, execution_cost, registrant_position, carried_from_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $original['user_id'],
                    date('Y-m-d'),
                    $original['product_name'],
                    $original['company_name'],
                    $original['payment_amount'],
                    $original['invoice_amount'] ?? 0,
                    $original['execution_cost'],
                    $original['registrant_position'],
                    $id,
                ]
            );
            $newId = (int) $db->lastInsertId();

            // Copy details with reset contract period
            $carryFields = [
                'sales_register_date', 'work_start_date', 'work_end_date', 'contract_term',
                'business_name', 'ceo_name', 'phone', 'payment_type', 'business_number',
                'work_keywords', 'work_content', 'email', 'naver_account', 'detail_execution_cost',
                'outsourcing_cost', 'keyword_cost', 'pg_name', 'tax_type', 'bank',
                'card_number', 'card_owner', 'card_owner_birth', 'card_validity', 'card_installment',
                'making_type', 'making_bigo', 'vpn_id', 'blog_id', 'blog_pass',
                'naver_ad_id', 'naver_ad_pass', 'daum_ad_id', 'daum_ad_pass', 'target_url', 'making_url',
            ];
            $carryValues = [$newId, null, null]; // company_id, contract_start=NULL, contract_end=NULL
            foreach ($carryFields as $f) {
                $carryValues[] = $original[$f] ?? null;
            }
            $carryFieldList = 'company_id, contract_start, contract_end, ' . implode(', ', $carryFields);
            $carryPlaceholders = implode(', ', array_fill(0, count($carryValues), '?'));
            $db->execute(
                "INSERT INTO company_details ($carryFieldList) VALUES ($carryPlaceholders)",
                $carryValues
            );

            // Copy memos (FR-ERP-005: 메모도 이월 대상)
            $memos = $db->fetchAll(
                'SELECT user_id, content FROM memos WHERE target_type = ? AND target_id = ? ORDER BY id ASC',
                [TARGET_COMPANY, $id]
            );
            foreach ($memos as $memo) {
                $db->execute(
                    'INSERT INTO memos (target_type, target_id, user_id, content) VALUES (?, ?, ?, ?)',
                    [TARGET_COMPANY, $newId, $memo['user_id'], $memo['content']]
                );
            }

            $db->commit();
            return $newId;
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Get companies by user ID.
     */
    public static function getByUser(int $userId, array $filters = []): array
    {
        $filters['user_id'] = $userId;
        return self::buildQuery($filters);
    }

    /**
     * Get summary totals with filters.
     */
    public static function getSummary(array $filters = []): array
    {
        $db = Database::getInstance();
        $where = [];
        $params = [];

        self::applyFilters($where, $params, $filters);

        $whereClause = '';
        if (!empty($where)) {
            $whereClause = 'WHERE ' . implode(' AND ', $where);
        }

        $row = $db->fetch(
            "SELECT
                COALESCE(SUM(c.payment_amount), 0) AS total_payment,
                COALESCE(SUM(c.invoice_amount), 0) AS total_invoice,
                COALESCE(SUM(c.execution_cost), 0) AS total_execution_cost,
                COALESCE(SUM(c.vat), 0) AS total_vat,
                COALESCE(SUM(c.net_margin), 0) AS total_net_margin,
                COUNT(*) AS total_count
             FROM companies c
             LEFT JOIN company_details cd ON cd.company_id = c.id
             $whereClause",
            $params
        );

        return $row ?: [
            'total_payment' => 0,
            'total_invoice' => 0,
            'total_execution_cost' => 0,
            'total_vat' => 0,
            'total_net_margin' => 0,
            'total_count' => 0,
        ];
    }

    /**
     * Apply common filters for WHERE clause.
     */
    private static function applyFilters(array &$where, array &$params, array $filters): void
    {
        $where[] = 'c.is_active = 1';

        if (!empty($filters['user_id'])) {
            $where[] = 'c.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(c.company_name LIKE ? OR c.product_name LIKE ? OR cd.ceo_name LIKE ? OR cd.business_name LIKE ? OR cd.phone LIKE ? OR cd.business_number LIKE ?)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['payment_type'])) {
            $where[] = 'cd.payment_type = ?';
            $params[] = $filters['payment_type'];
        }

        // Period filter (register_date based)
        if (!empty($filters['period'])) {
            $period = $filters['period'];
            if (!empty($period['year'])) {
                $where[] = "strftime('%Y', c.register_date) = ?";
                $params[] = (string) $period['year'];
            }
            if (!empty($period['month'])) {
                $where[] = "strftime('%m', c.register_date) = ?";
                $params[] = str_pad((string) $period['month'], 2, '0', STR_PAD_LEFT);
            }
            if (!empty($period['day'])) {
                $where[] = "strftime('%d', c.register_date) = ?";
                $params[] = str_pad((string) $period['day'], 2, '0', STR_PAD_LEFT);
            }
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'c.register_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'c.register_date <= ?';
            $params[] = $filters['date_to'];
        }
    }

    /**
     * Build and execute the main query with filters.
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
        $sortColumn = $filters['sort'] ?? 'c.register_date';
        $allowedSorts = ['c.register_date', 'c.company_name', 'c.payment_amount', 'c.execution_cost', 'c.net_margin', 'c.created_at'];
        if (!in_array($sortColumn, $allowedSorts)) {
            $sortColumn = 'c.register_date';
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
            "SELECT c.*, cd.*, c.id AS id, c.created_at AS created_at, c.updated_at AS updated_at,
                    u.name AS user_name
             FROM companies c
             LEFT JOIN company_details cd ON cd.company_id = c.id
             LEFT JOIN users u ON u.id = c.user_id
             $whereClause
             ORDER BY $sortColumn $order
             $limit",
            $params
        );
    }
}

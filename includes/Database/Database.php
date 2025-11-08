<?php
/**
 * Database Handler
 *
 * @package TPWC\HebrewPdf\Database
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\Database;

use TPWC\HebrewPdf\Logger;

/**
 * Manages database operations.
 */
class Database
{
    /**
     * Table name.
     */
    private const TABLE_NAME = 'tpwc_pdfs';

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get full table name with prefix.
     *
     * @return string
     */
    public function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create database tables.
     *
     * @return void
     */
    public function createTables(): void
    {
        global $wpdb;

        $tableName = $this->getTableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type ENUM('order', 'transaction') NOT NULL,
            ref_id BIGINT(20) UNSIGNED NOT NULL,
            customer_name VARCHAR(190) NOT NULL DEFAULT '',
            amount DECIMAL(20,6) NULL,
            created_at DATETIME NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_hash CHAR(64) NOT NULL,
            bytes INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('cached', 'stale') NOT NULL DEFAULT 'cached',
            meta JSON NULL,
            PRIMARY KEY (id),
            INDEX type_created (type, created_at),
            INDEX ref_id (ref_id),
            INDEX file_hash (file_hash),
            INDEX status (status)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $this->logger->info('Database tables created');
    }

    /**
     * Insert a PDF record.
     *
     * @param array $data Record data.
     * @return int|false Record ID on success, false on failure.
     */
    public function insert(array $data)
    {
        global $wpdb;

        $defaults = [
            'type' => 'order',
            'ref_id' => 0,
            'customer_name' => '',
            'amount' => null,
            'created_at' => current_time('mysql'),
            'file_path' => '',
            'file_hash' => '',
            'bytes' => 0,
            'status' => 'cached',
            'meta' => null,
        ];

        $data = wp_parse_args($data, $defaults);

        // Encode meta as JSON if it's an array.
        if (is_array($data['meta'])) {
            $data['meta'] = wp_json_encode($data['meta']);
        }

        $result = $wpdb->insert(
            $this->getTableName(),
            $data,
            [
                '%s', // type
                '%d', // ref_id
                '%s', // customer_name
                '%f', // amount
                '%s', // created_at
                '%s', // file_path
                '%s', // file_hash
                '%d', // bytes
                '%s', // status
                '%s', // meta
            ]
        );

        if ($result === false) {
            $this->logger->error('Failed to insert PDF record: ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update a PDF record.
     *
     * @param int   $id   Record ID.
     * @param array $data Data to update.
     * @return bool True on success, false on failure.
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        // Encode meta as JSON if it's an array.
        if (isset($data['meta']) && is_array($data['meta'])) {
            $data['meta'] = wp_json_encode($data['meta']);
        }

        $result = $wpdb->update(
            $this->getTableName(),
            $data,
            ['id' => $id],
            null,
            ['%d']
        );

        if ($result === false) {
            $this->logger->error("Failed to update PDF record {$id}: " . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Get a PDF record by ID.
     *
     * @param int $id Record ID.
     * @return object|null Record object or null if not found.
     */
    public function get(int $id): ?object
    {
        global $wpdb;

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->getTableName()} WHERE id = %d",
                $id
            )
        );

        if ($record && $record->meta) {
            $record->meta = json_decode($record->meta, true);
        }

        return $record ?: null;
    }

    /**
     * Get a PDF record by file hash.
     *
     * @param string $hash File hash.
     * @return object|null Record object or null if not found.
     */
    public function getByHash(string $hash): ?object
    {
        global $wpdb;

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->getTableName()} WHERE file_hash = %s AND status = 'cached' LIMIT 1",
                $hash
            )
        );

        if ($record && $record->meta) {
            $record->meta = json_decode($record->meta, true);
        }

        return $record ?: null;
    }

    /**
     * Get PDF records by type and ref_id.
     *
     * @param string $type   Record type.
     * @param int    $refId  Reference ID.
     * @return array Array of record objects.
     */
    public function getByRef(string $type, int $refId): array
    {
        global $wpdb;

        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->getTableName()} WHERE type = %s AND ref_id = %d ORDER BY created_at DESC",
                $type,
                $refId
            )
        );

        foreach ($records as $record) {
            if ($record->meta) {
                $record->meta = json_decode($record->meta, true);
            }
        }

        return $records;
    }

    /**
     * Delete a PDF record.
     *
     * @param int $id Record ID.
     * @return bool True on success, false on failure.
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->getTableName(),
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get paginated records.
     *
     * @param array $args Query arguments.
     * @return array Array with 'items' and 'total'.
     */
    public function getPaginated(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'type' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'status' => '',
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $wherePlaceholders = [];

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $wherePlaceholders[] = $args['type'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $wherePlaceholders[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $where[] = '(customer_name LIKE %s OR ref_id LIKE %s)';
            $searchTerm = '%' . $wpdb->esc_like($args['search']) . '%';
            $wherePlaceholders[] = $searchTerm;
            $wherePlaceholders[] = $searchTerm;
        }

        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $wherePlaceholders[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $wherePlaceholders[] = $args['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        // Get total count.
        $countQuery = "SELECT COUNT(*) FROM {$this->getTableName()} WHERE {$whereClause}";
        if (!empty($wherePlaceholders)) {
            $countQuery = $wpdb->prepare($countQuery, $wherePlaceholders);
        }
        $total = (int) $wpdb->get_var($countQuery);

        // Get paginated items.
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);

        // Fallback to safe default if sanitization fails
        if ($orderby === false) {
            $orderby = 'created_at DESC';
        }

        $query = "SELECT * FROM {$this->getTableName()} WHERE {$whereClause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $queryPlaceholders = array_merge($wherePlaceholders, [$args['per_page'], $offset]);

        $items = $wpdb->get_results($wpdb->prepare($query, $queryPlaceholders));

        foreach ($items as $item) {
            if ($item->meta) {
                $item->meta = json_decode($item->meta, true);
            }
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Mark records as stale.
     *
     * @param array $ids Array of record IDs.
     * @return bool True on success, false on failure.
     */
    public function markAsStale(array $ids): bool
    {
        global $wpdb;

        if (empty($ids)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->getTableName()} SET status = 'stale' WHERE id IN ({$placeholders})",
                $ids
            )
        );

        return $result !== false;
    }

    /**
     * Get cache statistics.
     *
     * @return array Statistics array.
     */
    public function getStats(): array
    {
        global $wpdb;

        $tableName = $this->getTableName();

        // Use single optimized query with conditional counts
        $result = $wpdb->get_row("
            SELECT
                COUNT(*) as total,
                COALESCE(SUM(bytes), 0) as total_bytes,
                SUM(CASE WHEN status = 'cached' THEN 1 ELSE 0 END) as cached_files,
                SUM(CASE WHEN status = 'stale' THEN 1 ELSE 0 END) as stale_files,
                SUM(CASE WHEN type = 'order' THEN 1 ELSE 0 END) as order_pdfs,
                SUM(CASE WHEN type = 'transaction' THEN 1 ELSE 0 END) as transaction_pdfs
            FROM {$tableName}
        ");

        return [
            'total_files' => (int) ($result->total ?? 0),
            'total_bytes' => (int) ($result->total_bytes ?? 0),
            'cached_files' => (int) ($result->cached_files ?? 0),
            'stale_files' => (int) ($result->stale_files ?? 0),
            'order_pdfs' => (int) ($result->order_pdfs ?? 0),
            'transaction_pdfs' => (int) ($result->transaction_pdfs ?? 0),
        ];
    }

    /**
     * Delete old records.
     *
     * @param int $daysOld Number of days old.
     * @return int Number of deleted records.
     */
    public function deleteOldRecords(int $daysOld): int
    {
        global $wpdb;

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->getTableName()} WHERE created_at < %s",
                $cutoffDate
            )
        );

        return $result !== false ? (int) $result : 0;
    }
}

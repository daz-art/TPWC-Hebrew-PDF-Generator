<?php
/**
 * Cache Manager
 *
 * @package TPWC\HebrewPdf\Cache
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\Cache;

use TPWC\HebrewPdf\Database\Database;
use TPWC\HebrewPdf\Admin\Settings;
use TPWC\HebrewPdf\Logger;
use Normalizer;

/**
 * Manages deterministic caching with SHA-256 hashing.
 */
class CacheManager
{
    /**
     * Database instance.
     *
     * @var Database
     */
    private Database $database;

    /**
     * Settings instance.
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Cache hit counter.
     *
     * @var int
     */
    private int $cacheHits = 0;

    /**
     * Cache miss counter.
     *
     * @var int
     */
    private int $cacheMisses = 0;

    /**
     * Constructor.
     *
     * @param Database $database Database instance.
     * @param Settings $settings Settings instance.
     * @param Logger   $logger   Logger instance.
     */
    public function __construct(Database $database, Settings $settings, Logger $logger)
    {
        $this->database = $database;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Compute canonical hash for an order.
     *
     * @param int $orderId Order ID.
     * @return string SHA-256 hash.
     */
    public function computeOrderHash(int $orderId): string
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            throw new \InvalidArgumentException("Order {$orderId} not found");
        }

        // Get order items sorted by line_id.
        $items = $order->get_items();
        $canonicalItems = [];

        foreach ($items as $itemId => $item) {
            $canonicalItems[] = [
                'line_id' => $itemId,
                'name' => $this->canonicalize($item->get_name()),
                'sku' => $this->canonicalize($item->get_product() ? $item->get_product()->get_sku() : ''),
                'qty' => (int) $item->get_quantity(),
                'unit_price' => $this->canonicalizePrice($item->get_subtotal() / max(1, $item->get_quantity())),
                'line_total' => $this->canonicalizePrice($item->get_total()),
            ];
        }

        // Sort items by line_id.
        usort($canonicalItems, fn($a, $b) => $a['line_id'] <=> $b['line_id']);

        // Build canonical payload.
        $payload = [
            'order_id' => $orderId,
            'items' => $canonicalItems,
            'totals' => [
                'subtotal' => $this->canonicalizePrice($order->get_subtotal()),
                'shipping' => $this->canonicalizePrice($order->get_shipping_total()),
                'tax' => $this->canonicalizePrice($order->get_total_tax()),
                'total' => $this->canonicalizePrice($order->get_total()),
            ],
            'customer' => [
                'first_name' => $this->canonicalize($order->get_billing_first_name()),
                'last_name' => $this->canonicalize($order->get_billing_last_name()),
                'company' => $this->canonicalize($order->get_billing_company()),
                'address_1' => $this->canonicalize($order->get_billing_address_1()),
                'address_2' => $this->canonicalize($order->get_billing_address_2()),
                'city' => $this->canonicalize($order->get_billing_city()),
                'postcode' => $this->canonicalize($order->get_billing_postcode()),
                'country' => $this->canonicalize($order->get_billing_country()),
                'email' => $this->canonicalize($order->get_billing_email()),
                'phone' => $this->canonicalize($order->get_billing_phone()),
            ],
            'business_profile_version' => (int) $this->settings->get('business_profile_version', 1),
            'template_version' => (int) $this->settings->get('template_version', 1),
            'logo_hash' => $this->settings->get('logo_hash', ''),
        ];

        // Sort keys before JSON encoding.
        $payload = $this->sortKeysRecursive($payload);

        // Compute hash.
        return hash('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Compute canonical hash for a transaction.
     *
     * @param int   $transactionId Transaction ID.
     * @param array $data          Transaction data.
     * @return string SHA-256 hash.
     */
    public function computeTransactionHash(int $transactionId, array $data): string
    {
        // Build canonical payload.
        $payload = [
            'transaction_id' => $transactionId,
            'type' => $this->canonicalize($data['type'] ?? ''),
            'amount' => $this->canonicalizePrice($data['amount'] ?? 0),
            'method' => $this->canonicalize($data['method'] ?? ''),
            'reference' => $this->canonicalize($data['reference'] ?? ''),
            'note' => $this->canonicalize($data['note'] ?? ''),
            'related_order_id' => isset($data['related_order_id']) ? (int) $data['related_order_id'] : null,
            'business_profile_version' => (int) $this->settings->get('business_profile_version', 1),
            'template_version' => (int) $this->settings->get('template_version', 1),
            'logo_hash' => $this->settings->get('logo_hash', ''),
        ];

        // Sort keys before JSON encoding.
        $payload = $this->sortKeysRecursive($payload);

        // Compute hash.
        return hash('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Canonicalize a string value.
     *
     * @param mixed $value Value to canonicalize.
     * @return string Canonical string.
     */
    private function canonicalize($value): string
    {
        // Convert null to empty string.
        if ($value === null) {
            return '';
        }

        // Convert to string.
        $str = (string) $value;

        // Trim whitespace.
        $str = trim($str);

        // Normalize to UTF-8 NFC.
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($str, Normalizer::FORM_C);
            if ($normalized !== false) {
                $str = $normalized;
            }
        }

        return $str;
    }

    /**
     * Canonicalize a price value.
     *
     * @param mixed $value Price value.
     * @return float Canonical price.
     */
    private function canonicalizePrice($value): float
    {
        $decimals = wc_get_price_decimals();
        return round((float) $value, $decimals);
    }

    /**
     * Sort array keys recursively.
     *
     * @param array $array Array to sort.
     * @return array Sorted array.
     */
    private function sortKeysRecursive(array $array): array
    {
        ksort($array);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortKeysRecursive($value);
            }
        }

        return $array;
    }

    /**
     * Check if a cached file exists for a given hash.
     *
     * @param string $hash File hash.
     * @return object|null Cached record or null.
     */
    public function get(string $hash): ?object
    {
        $record = $this->database->getByHash($hash);

        if ($record && $record->status === 'cached' && file_exists($record->file_path)) {
            $this->cacheHits++;
            $this->logger->debug("Cache hit for hash {$hash}");
            return $record;
        }

        $this->cacheMisses++;
        $this->logger->debug("Cache miss for hash {$hash}");

        return null;
    }

    /**
     * Store a PDF file in cache.
     *
     * @param string $hash       File hash.
     * @param string $filePath   File path.
     * @param string $type       Record type.
     * @param int    $refId      Reference ID.
     * @param array  $metadata   Additional metadata.
     * @return int|false Record ID on success, false on failure.
     */
    public function store(string $hash, string $filePath, string $type, int $refId, array $metadata = [])
    {
        if (!file_exists($filePath)) {
            $this->logger->error("Cannot store non-existent file: {$filePath}");
            return false;
        }

        $data = [
            'type' => $type,
            'ref_id' => $refId,
            'customer_name' => $metadata['customer_name'] ?? '',
            'amount' => $metadata['amount'] ?? null,
            'file_path' => $filePath,
            'file_hash' => $hash,
            'bytes' => filesize($filePath),
            'status' => 'cached',
            'meta' => $metadata,
        ];

        $recordId = $this->database->insert($data);

        if ($recordId) {
            $this->logger->info("Stored PDF in cache: {$filePath} (hash: {$hash})");
        }

        return $recordId;
    }

    /**
     * Mark all PDFs as stale (for regeneration).
     *
     * @return void
     */
    public function invalidateAll(): void
    {
        global $wpdb;

        $tableName = $this->database->getTableName();
        $wpdb->query("UPDATE {$tableName} SET status = 'stale'");

        $this->logger->info('Invalidated all cached PDFs');
    }

    /**
     * Get cache statistics.
     *
     * @return array Statistics.
     */
    public function getStats(): array
    {
        $dbStats = $this->database->getStats();

        return array_merge($dbStats, [
            'cache_hits' => $this->cacheHits,
            'cache_misses' => $this->cacheMisses,
            'hit_rate' => $this->cacheMisses > 0
                ? round(($this->cacheHits / ($this->cacheHits + $this->cacheMisses)) * 100, 2)
                : 0,
        ]);
    }

    /**
     * Clean up old PDF files.
     *
     * @return void
     */
    public function cleanupOldFiles(): void
    {
        $ttl = (int) $this->settings->get('cleanup_ttl', 90);

        if ($ttl <= 0) {
            return;
        }

        // Get old records.
        global $wpdb;
        $tableName = $this->database->getTableName();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$ttl} days"));

        $oldRecords = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, file_path FROM {$tableName} WHERE created_at < %s",
                $cutoffDate
            )
        );

        $deletedCount = 0;

        foreach ($oldRecords as $record) {
            // Delete file.
            if (file_exists($record->file_path)) {
                unlink($record->file_path);
            }

            // Delete database record.
            $this->database->delete($record->id);
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            $this->logger->info("Cleaned up {$deletedCount} old PDF files");
        }
    }

    /**
     * Get storage directory path.
     *
     * @return string Storage directory path.
     */
    public function getStorageDir(): string
    {
        $uploadDir = wp_upload_dir();
        return $uploadDir['basedir'] . '/tpwc-pdfs';
    }

    /**
     * Generate a unique file path for a PDF.
     *
     * @param string $type  Record type.
     * @param int    $refId Reference ID.
     * @return string File path.
     */
    public function generateFilePath(string $type, int $refId): string
    {
        $storageDir = $this->getStorageDir();
        $filename = sprintf('%s-%d-%s.pdf', $type, $refId, wp_generate_password(12, false));

        return $storageDir . '/' . $filename;
    }
}

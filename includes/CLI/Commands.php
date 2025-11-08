<?php
/**
 * WP-CLI Commands
 *
 * @package TPWC\HebrewPdf\CLI
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\CLI;

use TPWC\HebrewPdf\Plugin;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI commands for TPWC Hebrew PDF Generator.
 */
class Commands extends WP_CLI_Command
{
    /**
     * Regenerate PDFs.
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Type of PDFs to regenerate (order or transaction).
     *
     * [--since=<date>]
     * : Only regenerate PDFs created since this date (YYYY-MM-DD format).
     *
     * [--limit=<number>]
     * : Maximum number of PDFs to regenerate.
     * ---
     * default: 0
     * ---
     *
     * ## EXAMPLES
     *
     *     wp tpwc regen --type=order
     *     wp tpwc regen --since=2024-01-01
     *     wp tpwc regen --type=transaction --limit=100
     *
     * @param array $args       Positional arguments.
     * @param array $assocArgs Associative arguments.
     * @return void
     */
    public function regen($args, $assocArgs): void
    {
        $plugin = Plugin::instance();

        $type = $assocArgs['type'] ?? '';
        $since = $assocArgs['since'] ?? '';
        $limit = (int) ($assocArgs['limit'] ?? 0);

        WP_CLI::log('Starting PDF regeneration...');

        // Build query args.
        $queryArgs = [
            'per_page' => $limit > 0 ? $limit : 999999,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'type' => $type,
            'date_from' => $since,
        ];

        $result = $plugin->database->getPaginated($queryArgs);
        $records = $result['items'];

        if (empty($records)) {
            WP_CLI::warning('No PDFs found matching the criteria.');
            return;
        }

        $total = count($records);
        WP_CLI::log("Found {$total} PDFs to regenerate.");

        $progress = \WP_CLI\Utils\make_progress_bar('Regenerating PDFs', $total);

        $success = 0;
        $failed = 0;

        foreach ($records as $record) {
            try {
                $newRecordId = $plugin->generator->regeneratePdf($record->id);

                if ($newRecordId !== false) {
                    $success++;
                } else {
                    $failed++;
                    WP_CLI::warning("Failed to regenerate PDF #{$record->id}");
                }
            } catch (\Exception $e) {
                $failed++;
                WP_CLI::warning("Error regenerating PDF #{$record->id}: " . $e->getMessage());
            }

            $progress->tick();
        }

        $progress->finish();

        WP_CLI::success("Regeneration complete. Success: {$success}, Failed: {$failed}");
    }

    /**
     * Purge old cached PDFs.
     *
     * ## OPTIONS
     *
     * [--older-than=<days>]
     * : Delete PDFs older than this many days.
     * ---
     * default: 90
     * ---
     *
     * [--dry-run]
     * : Show what would be deleted without actually deleting.
     *
     * ## EXAMPLES
     *
     *     wp tpwc purge-cache --older-than=90
     *     wp tpwc purge-cache --older-than=30 --dry-run
     *
     * @param array $args       Positional arguments.
     * @param array $assocArgs Associative arguments.
     * @return void
     */
    public function purgeCache($args, $assocArgs): void
    {
        $plugin = Plugin::instance();

        $daysOld = (int) ($assocArgs['older-than'] ?? 90);
        $dryRun = isset($assocArgs['dry-run']);

        if ($daysOld <= 0) {
            WP_CLI::error('Invalid --older-than value. Must be greater than 0.');
            return;
        }

        WP_CLI::log("Purging PDFs older than {$daysOld} days...");

        // Get old records.
        global $wpdb;
        $tableName = $plugin->database->getTableName();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        $oldRecords = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, file_path, bytes FROM {$tableName} WHERE created_at < %s",
                $cutoffDate
            )
        );

        if (empty($oldRecords)) {
            WP_CLI::success('No old PDFs found.');
            return;
        }

        $count = count($oldRecords);
        $totalBytes = array_sum(array_column($oldRecords, 'bytes'));

        WP_CLI::log("Found {$count} PDFs to delete (" . size_format($totalBytes) . ")");

        if ($dryRun) {
            WP_CLI::warning('DRY RUN - No files will be deleted.');

            foreach ($oldRecords as $record) {
                WP_CLI::log("Would delete: #{$record->id} ({$record->file_path})");
            }

            return;
        }

        $progress = \WP_CLI\Utils\make_progress_bar('Deleting PDFs', $count);

        $deleted = 0;

        foreach ($oldRecords as $record) {
            // Delete file.
            if (file_exists($record->file_path)) {
                unlink($record->file_path);
            }

            // Delete database record.
            if ($plugin->database->delete($record->id)) {
                $deleted++;
            }

            $progress->tick();
        }

        $progress->finish();

        WP_CLI::success("Deleted {$deleted} PDFs (" . size_format($totalBytes) . ")");
    }

    /**
     * Show cache statistics.
     *
     * ## EXAMPLES
     *
     *     wp tpwc stats
     *
     * @return void
     */
    public function stats(): void
    {
        $plugin = Plugin::instance();
        $stats = $plugin->database->getStats();

        WP_CLI::log('');
        WP_CLI::log('Cache Statistics:');
        WP_CLI::log('================');
        WP_CLI::log('Total Files:       ' . number_format_i18n($stats['total_files']));
        WP_CLI::log('Total Size:        ' . size_format($stats['total_bytes']));
        WP_CLI::log('Cached Files:      ' . number_format_i18n($stats['cached_files']));
        WP_CLI::log('Stale Files:       ' . number_format_i18n($stats['stale_files']));
        WP_CLI::log('Order PDFs:        ' . number_format_i18n($stats['order_pdfs']));
        WP_CLI::log('Transaction PDFs:  ' . number_format_i18n($stats['transaction_pdfs']));
        WP_CLI::log('');
    }

    /**
     * Generate PDF for an order.
     *
     * ## OPTIONS
     *
     * <order_id>
     * : Order ID.
     *
     * [--force]
     * : Force regeneration (skip cache).
     *
     * ## EXAMPLES
     *
     *     wp tpwc generate-order 123
     *     wp tpwc generate-order 123 --force
     *
     * @param array $args       Positional arguments.
     * @param array $assocArgs Associative arguments.
     * @return void
     */
    public function generateOrder($args, $assocArgs): void
    {
        $plugin = Plugin::instance();

        $orderId = (int) $args[0];
        $force = isset($assocArgs['force']);

        $order = wc_get_order($orderId);

        if (!$order) {
            WP_CLI::error("Order #{$orderId} not found.");
            return;
        }

        WP_CLI::log("Generating PDF for order #{$orderId}...");

        try {
            $recordId = $plugin->generator->generateOrderPdf($orderId, $force);

            if ($recordId === false) {
                WP_CLI::error('Failed to generate PDF.');
                return;
            }

            $record = $plugin->database->get($recordId);

            if (!$record) {
                WP_CLI::error('Failed to retrieve PDF record.');
                return;
            }

            $downloadUrl = $plugin->fileController->generateSignedUrl($recordId, 3600);

            WP_CLI::success("PDF generated successfully.");
            WP_CLI::log("Record ID: {$recordId}");
            WP_CLI::log("File: {$record->file_path}");
            WP_CLI::log("Size: " . size_format($record->bytes));
            WP_CLI::log("Download URL: {$downloadUrl}");
        } catch (\Exception $e) {
            WP_CLI::error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Invalidate all cached PDFs.
     *
     * ## EXAMPLES
     *
     *     wp tpwc invalidate-all
     *
     * @return void
     */
    public function invalidateAll(): void
    {
        $plugin = Plugin::instance();

        WP_CLI::log('Invalidating all cached PDFs...');

        $plugin->cache->invalidateAll();

        WP_CLI::success('All cached PDFs have been marked as stale.');
    }
}

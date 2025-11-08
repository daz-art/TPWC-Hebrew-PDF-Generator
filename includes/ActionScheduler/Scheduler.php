<?php
/**
 * Action Scheduler Integration
 *
 * @package TPWC\HebrewPdf\ActionScheduler
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\ActionScheduler;

use TPWC\HebrewPdf\Plugin;
use TPWC\HebrewPdf\Logger;

/**
 * Action Scheduler integration for async PDF generation.
 */
class Scheduler
{
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
        $this->initHooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function initHooks(): void
    {
        // Register action hooks.
        add_action('tpwc_async_generate_order_pdf', [$this, 'processOrderPdf'], 10, 2);
        add_action('tpwc_async_generate_transaction_pdf', [$this, 'processTransactionPdf'], 10, 3);
        add_action('tpwc_async_bulk_regenerate', [$this, 'processBulkRegenerate'], 10, 1);
    }

    /**
     * Schedule order PDF generation.
     *
     * @param int  $orderId Order ID.
     * @param bool $force   Force regeneration.
     * @return int|false Action ID on success, false on failure.
     */
    public function scheduleOrderPdf(int $orderId, bool $force = false)
    {
        if (!function_exists('as_enqueue_async_action')) {
            $this->logger->warning('Action Scheduler not available');
            return false;
        }

        return as_enqueue_async_action(
            'tpwc_async_generate_order_pdf',
            [$orderId, $force],
            'tpwc_pdf_generation'
        );
    }

    /**
     * Schedule transaction PDF generation.
     *
     * @param int   $transactionId Transaction ID.
     * @param array $data          Transaction data.
     * @param bool  $force         Force regeneration.
     * @return int|false Action ID on success, false on failure.
     */
    public function scheduleTransactionPdf(int $transactionId, array $data, bool $force = false)
    {
        if (!function_exists('as_enqueue_async_action')) {
            $this->logger->warning('Action Scheduler not available');
            return false;
        }

        return as_enqueue_async_action(
            'tpwc_async_generate_transaction_pdf',
            [$transactionId, $data, $force],
            'tpwc_pdf_generation'
        );
    }

    /**
     * Schedule bulk PDF regeneration.
     *
     * @param array $recordIds Array of record IDs.
     * @return int Number of scheduled actions.
     */
    public function scheduleBulkRegenerate(array $recordIds): int
    {
        if (!function_exists('as_enqueue_async_action')) {
            $this->logger->warning('Action Scheduler not available');
            return 0;
        }

        $scheduled = 0;

        foreach ($recordIds as $recordId) {
            $actionId = as_enqueue_async_action(
                'tpwc_async_bulk_regenerate',
                [$recordId],
                'tpwc_bulk_operations'
            );

            if ($actionId !== false) {
                $scheduled++;
            }
        }

        return $scheduled;
    }

    /**
     * Process order PDF generation.
     *
     * @param int  $orderId Order ID.
     * @param bool $force   Force regeneration.
     * @return void
     */
    public function processOrderPdf(int $orderId, bool $force): void
    {
        // Validate types from Action Scheduler queue.
        if (!is_int($orderId) || $orderId <= 0) {
            $this->logger->error("Async: Invalid order ID type or value: " . var_export($orderId, true));
            return;
        }

        if (!is_bool($force)) {
            $this->logger->error("Async: Invalid force parameter type: " . var_export($force, true));
            return;
        }

        try {
            $plugin = Plugin::instance();
            $recordId = $plugin->generator->generateOrderPdf($orderId, $force);

            if ($recordId === false) {
                $this->logger->error("Async: Failed to generate order PDF for order {$orderId}");
            } else {
                $this->logger->info("Async: Generated order PDF for order {$orderId} (record {$recordId})");
            }
        } catch (\Exception $e) {
            $this->logger->error("Async: Error generating order PDF for order {$orderId}: " . $e->getMessage());
        }
    }

    /**
     * Process transaction PDF generation.
     *
     * @param int   $transactionId Transaction ID.
     * @param array $data          Transaction data.
     * @param bool  $force         Force regeneration.
     * @return void
     */
    public function processTransactionPdf(int $transactionId, array $data, bool $force): void
    {
        // Validate types from Action Scheduler queue.
        if (!is_int($transactionId) || $transactionId <= 0) {
            $this->logger->error("Async: Invalid transaction ID type or value: " . var_export($transactionId, true));
            return;
        }

        if (!is_array($data)) {
            $this->logger->error("Async: Invalid data parameter type: " . var_export($data, true));
            return;
        }

        if (!is_bool($force)) {
            $this->logger->error("Async: Invalid force parameter type: " . var_export($force, true));
            return;
        }

        try {
            $plugin = Plugin::instance();
            $recordId = $plugin->generator->generateTransactionPdf($transactionId, $data, $force);

            if ($recordId === false) {
                $this->logger->error("Async: Failed to generate transaction PDF for transaction {$transactionId}");
            } else {
                $this->logger->info("Async: Generated transaction PDF for transaction {$transactionId} (record {$recordId})");
            }
        } catch (\Exception $e) {
            $this->logger->error("Async: Error generating transaction PDF for transaction {$transactionId}: " . $e->getMessage());
        }
    }

    /**
     * Process bulk regeneration.
     *
     * @param int $recordId Record ID.
     * @return void
     */
    public function processBulkRegenerate(int $recordId): void
    {
        // Validate type from Action Scheduler queue.
        if (!is_int($recordId) || $recordId <= 0) {
            $this->logger->error("Async: Invalid record ID type or value: " . var_export($recordId, true));
            return;
        }

        try {
            $plugin = Plugin::instance();
            $newRecordId = $plugin->generator->regeneratePdf($recordId);

            if ($newRecordId === false) {
                $this->logger->error("Async: Failed to regenerate PDF record {$recordId}");
            } else {
                $this->logger->info("Async: Regenerated PDF record {$recordId} -> {$newRecordId}");
            }
        } catch (\Exception $e) {
            $this->logger->error("Async: Error regenerating PDF record {$recordId}: " . $e->getMessage());
        }
    }

    /**
     * Get action status.
     *
     * @param int $actionId Action ID.
     * @return string|null Status or null if not found.
     */
    public function getActionStatus(int $actionId): ?string
    {
        if (!function_exists('as_get_scheduled_actions')) {
            return null;
        }

        try {
            if (!class_exists('\ActionScheduler') || !method_exists('\ActionScheduler', 'store')) {
                return null;
            }

            $action = \ActionScheduler::store()->fetch_action($actionId);

            if (!$action) {
                return null;
            }

            return $action->get_status();
        } catch (\Exception $e) {
            $this->logger->warning("Failed to fetch action status: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cancel scheduled action.
     *
     * @param int $actionId Action ID.
     * @return void
     */
    public function cancelAction(int $actionId): void
    {
        if (!function_exists('as_unschedule_action')) {
            return;
        }

        try {
            if (class_exists('\ActionScheduler') && method_exists('\ActionScheduler', 'store')) {
                \ActionScheduler::store()->cancel_action($actionId);
            }
        } catch (\Exception $e) {
            $this->logger->warning("Failed to cancel action: " . $e->getMessage());
        }
    }
}

<?php
/**
 * Email Attachments Handler
 *
 * @package TPWC\HebrewPdf\Email
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\Email;

use TPWC\HebrewPdf\Plugin;
use TPWC\HebrewPdf\Logger;

/**
 * Handles attaching PDFs to WooCommerce emails.
 */
class Attachments
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
        add_filter('woocommerce_email_attachments', [$this, 'attachPdfToEmail'], 10, 4);
    }

    /**
     * Attach PDF to WooCommerce email.
     *
     * @param array     $attachments Email attachments.
     * @param string    $emailId     Email ID.
     * @param object    $order       Order object.
     * @param \WC_Email $email       Email object.
     * @return array Modified attachments.
     */
    public function attachPdfToEmail(array $attachments, string $emailId, $order, $email): array
    {
        // Only attach to order emails.
        if (!$order instanceof \WC_Order) {
            return $attachments;
        }

        // Get enabled email types from settings.
        $enabledEmails = get_option('tpwc_email_attachments', []);

        if (empty($enabledEmails) || !in_array($emailId, $enabledEmails, true)) {
            return $attachments;
        }

        try {
            $plugin = Plugin::instance();
            $orderId = $order->get_id();

            // Generate or get cached PDF.
            $recordId = $plugin->generator->generateOrderPdf($orderId, false);

            if ($recordId === false) {
                $this->logger->error("Failed to attach PDF to email {$emailId} for order {$orderId}");
                return $attachments;
            }

            $record = $plugin->database->get($recordId);

            if ($record && file_exists($record->file_path)) {
                $attachments[] = $record->file_path;
                $this->logger->info("Attached PDF to email {$emailId} for order {$orderId}");
            }
        } catch (\Exception $e) {
            $this->logger->error("Error attaching PDF to email {$emailId}: " . $e->getMessage());
        }

        return $attachments;
    }

    /**
     * Get available WooCommerce email types.
     *
     * @return array Email types.
     */
    public static function getAvailableEmailTypes(): array
    {
        return [
            'customer_completed_order' => __('Completed Order', 'tpwc-hebrew-pdf'),
            'customer_invoice' => __('Customer Invoice', 'tpwc-hebrew-pdf'),
            'customer_processing_order' => __('Processing Order', 'tpwc-hebrew-pdf'),
            'customer_on_hold_order' => __('On Hold Order', 'tpwc-hebrew-pdf'),
            'customer_refunded_order' => __('Refunded Order', 'tpwc-hebrew-pdf'),
        ];
    }
}

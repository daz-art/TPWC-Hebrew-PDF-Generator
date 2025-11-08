<?php
/**
 * File Controller
 *
 * @package TPWC\HebrewPdf\PDF
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\PDF;

use TPWC\HebrewPdf\Database\Database;
use TPWC\HebrewPdf\Cache\CacheManager;
use TPWC\HebrewPdf\Logger;

/**
 * Handles secure file downloads with HMAC-signed URLs.
 */
class FileController
{
    /**
     * Database instance.
     *
     * @var Database
     */
    private Database $database;

    /**
     * Cache manager instance.
     *
     * @var CacheManager
     */
    private CacheManager $cache;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param Database     $database Database instance.
     * @param CacheManager $cache    Cache manager instance.
     * @param Logger       $logger   Logger instance.
     */
    public function __construct(Database $database, CacheManager $cache, Logger $logger)
    {
        $this->database = $database;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Get database instance.
     *
     * @return Database Database instance.
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Generate a signed download URL.
     *
     * @param int  $recordId Record ID.
     * @param int  $ttl      Time-to-live in seconds.
     * @param bool $preview  Whether this is a preview (inline) or download.
     * @return string Signed URL.
     */
    public function generateSignedUrl(int $recordId, int $ttl = 900, bool $preview = false): string
    {
        $expires = time() + $ttl;
        $action = $preview ? 'preview' : 'download';

        $payload = sprintf('%d:%d:%s', $recordId, $expires, $action);
        $signature = $this->sign($payload);

        return add_query_arg(
            [
                'tpwc_pdf' => $recordId,
                'expires' => $expires,
                'action' => $action,
                'signature' => $signature,
            ],
            home_url('/')
        );
    }

    /**
     * Sign a payload with HMAC-SHA256.
     *
     * @param string $payload Payload to sign.
     * @return string HMAC signature.
     */
    private function sign(string $payload): string
    {
        $key = $this->getSigningKey();
        return hash_hmac('sha256', $payload, $key);
    }

    /**
     * Verify a signed payload.
     *
     * @param string $payload   Payload.
     * @param string $signature Signature to verify.
     * @return bool True if valid, false otherwise.
     */
    private function verify(string $payload, string $signature): bool
    {
        $expected = $this->sign($payload);
        return hash_equals($expected, $signature);
    }

    /**
     * Get signing key.
     *
     * @return string Signing key.
     */
    private function getSigningKey(): string
    {
        $key = get_option('tpwc_signing_key');

        if (!$key) {
            $key = wp_generate_password(64, true, true);
            add_option('tpwc_signing_key', $key, '', false);
        }

        return $key;
    }

    /**
     * Handle download request.
     *
     * @return void
     */
    public function handleDownloadRequest(): void
    {
        // Check if this is a download request.
        if (!isset($_GET['tpwc_pdf'], $_GET['expires'], $_GET['action'], $_GET['signature'])) {
            return;
        }

        $recordId = (int) $_GET['tpwc_pdf'];
        $expires = (int) $_GET['expires'];
        $action = in_array($_GET['action'], ['preview', 'download'], true)
            ? $_GET['action']
            : 'download';
        $signature = sanitize_text_field($_GET['signature']);

        // Verify signature.
        $payload = sprintf('%d:%d:%s', $recordId, $expires, $action);

        if (!$this->verify($payload, $signature)) {
            $this->logger->warning("Invalid signature for PDF download (record {$recordId})");
            wp_die(esc_html__('Invalid download link.', 'tpwc-hebrew-pdf'), 403);
        }

        // Check expiration.
        if (time() > $expires) {
            $this->logger->warning("Expired download link for PDF (record {$recordId})");
            wp_die(esc_html__('Download link has expired.', 'tpwc-hebrew-pdf'), 403);
        }

        // Get record.
        $record = $this->database->get($recordId);

        if (!$record) {
            $this->logger->error("PDF record {$recordId} not found");
            wp_die(esc_html__('PDF not found.', 'tpwc-hebrew-pdf'), 404);
        }

        // Check if file exists.
        if (!file_exists($record->file_path)) {
            $this->logger->error("PDF file not found: {$record->file_path}");
            wp_die(esc_html__('PDF file not found.', 'tpwc-hebrew-pdf'), 404);
        }

        // Check capabilities.
        if (!$this->canAccessPdf($record)) {
            $this->logger->warning("Unauthorized access attempt to PDF (record {$recordId})");
            wp_die(esc_html__('You do not have permission to access this PDF.', 'tpwc-hebrew-pdf'), 403);
        }

        // Stream the file.
        $this->streamFile($record, $action === 'preview');

        exit;
    }

    /**
     * Check if current user can access a PDF.
     *
     * @param object $record PDF record.
     * @return bool True if user can access, false otherwise.
     */
    private function canAccessPdf(object $record): bool
    {
        // Admins and shop managers can access all PDFs.
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        // For orders, check if the user owns the order.
        if ($record->type === 'order') {
            $order = wc_get_order($record->ref_id);
            $customerId = get_current_user_id();

            // Ensure user is logged in (ID > 0) and matches order customer
            if ($customerId > 0 && $order && $order->get_customer_id() === $customerId) {
                return true;
            }
        }

        // For transactions, check if user has view_order capability.
        if ($record->type === 'transaction') {
            // Transactions can be viewed by users with manage_woocommerce capability.
            return current_user_can('manage_woocommerce');
        }

        return false;
    }

    /**
     * Stream a PDF file.
     *
     * @param object $record  PDF record.
     * @param bool   $preview Whether to display inline (preview) or force download.
     * @return void
     */
    private function streamFile(object $record, bool $preview = false): void
    {
        // Clear output buffers.
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers.
        header('Content-Type: application/pdf');
        header('Content-Length: ' . $record->bytes);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Generate filename.
        $filename = sprintf(
            '%s-%d-%s.pdf',
            $record->type,
            $record->ref_id,
            gmdate('Y-m-d', strtotime($record->created_at))
        );

        if ($preview) {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }

        // Stream the file.
        readfile($record->file_path);

        $this->logger->info("Streamed PDF: {$record->file_path} ({$record->type} #{$record->ref_id})");
    }

    /**
     * Delete a PDF file.
     *
     * @param int $recordId Record ID.
     * @return bool True on success, false on failure.
     */
    public function deletePdf(int $recordId): bool
    {
        $record = $this->database->get($recordId);

        if (!$record) {
            return false;
        }

        // Delete file if it exists.
        if (file_exists($record->file_path)) {
            if (!@unlink($record->file_path)) {
                $this->logger->warning("Failed to delete file: {$record->file_path}");
            }
        }

        // Delete database record.
        return $this->database->delete($recordId);
    }
}

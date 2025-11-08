<?php
/**
 * Plugin Deactivator
 *
 * @package TPWC\HebrewPdf
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf;

/**
 * Handles plugin deactivation.
 */
class Deactivator
{
    /**
     * Deactivate the plugin.
     *
     * @return void
     */
    public static function deactivate(): void
    {
        // Clear scheduled cron jobs.
        $timestamp = wp_next_scheduled('tpwc_cleanup_old_pdfs');
        if ($timestamp) {
            $result = wp_unschedule_event($timestamp, 'tpwc_cleanup_old_pdfs');
            if ($result === false) {
                error_log('[TPWC] Failed to unschedule cleanup cron job');
            }
        }

        // Flush rewrite rules.
        flush_rewrite_rules();

        // Log deactivation if WooCommerce is still active.
        if (function_exists('wc_get_logger')) {
            $logger = new Logger();
            $logger->info('TPWC Hebrew PDF Generator deactivated');
        }
    }
}

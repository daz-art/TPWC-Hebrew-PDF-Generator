<?php
/**
 * Logger Class
 *
 * @package TPWC\HebrewPdf
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf;

/**
 * Logger using WC_Logger.
 */
class Logger
{
    /**
     * Log channel name.
     */
    private const CHANNEL = 'tpwc';

    /**
     * WooCommerce logger instance.
     *
     * @var \WC_Logger|null
     */
    private ?\WC_Logger $logger = null;

    /**
     * Get logger instance.
     *
     * @return \WC_Logger
     */
    private function getLogger(): \WC_Logger
    {
        if ($this->logger === null && function_exists('wc_get_logger')) {
            $this->logger = wc_get_logger();
        }

        return $this->logger;
    }

    /**
     * Log error message.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context.
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log warning message.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context.
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log info message.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context.
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log debug message.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context.
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log('debug', $message, $context);
        }
    }

    /**
     * Log message.
     *
     * @param string $level   Log level.
     * @param string $message Message to log.
     * @param array  $context Additional context.
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void
    {
        try {
            $logger = $this->getLogger();
            if ($logger) {
                $logger->log($level, $message, array_merge(['source' => self::CHANNEL], $context));
            }
        } catch (\Exception $e) {
            // Fallback to error_log if WC logger fails.
            error_log(sprintf('[TPWC] [%s] %s', strtoupper($level), $message));
        }
    }
}

<?php
/**
 * Main Plugin Class
 *
 * @package TPWC\HebrewPdf
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf;

use TPWC\HebrewPdf\Admin\AdminMenu;
use TPWC\HebrewPdf\Admin\Settings;
use TPWC\HebrewPdf\Cache\CacheManager;
use TPWC\HebrewPdf\Database\Database;
use TPWC\HebrewPdf\PDF\Generator;
use TPWC\HebrewPdf\PDF\FileController;
use TPWC\HebrewPdf\REST\API;
use TPWC\HebrewPdf\CLI\Commands;
use TPWC\HebrewPdf\ActionScheduler\Scheduler;
use TPWC\HebrewPdf\Email\Attachments;

/**
 * Main plugin singleton class.
 */
final class Plugin
{
    /**
     * Plugin instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Database handler.
     *
     * @var Database
     */
    public Database $database;

    /**
     * Cache manager.
     *
     * @var CacheManager
     */
    public CacheManager $cache;

    /**
     * PDF generator.
     *
     * @var Generator
     */
    public Generator $generator;

    /**
     * File controller.
     *
     * @var FileController
     */
    public FileController $fileController;

    /**
     * Settings handler.
     *
     * @var Settings
     */
    public Settings $settings;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    public Logger $logger;

    /**
     * Action Scheduler instance.
     *
     * @var Scheduler
     */
    public Scheduler $scheduler;

    /**
     * Email Attachments handler.
     *
     * @var Attachments
     */
    public Attachments $emailAttachments;

    /**
     * Admin menu handler.
     *
     * @var AdminMenu|null
     */
    private ?AdminMenu $adminMenu = null;

    /**
     * REST API handler.
     *
     * @var API|null
     */
    private ?API $api = null;

    /**
     * Private constructor to enforce singleton.
     */
    private function __construct()
    {
        $this->logger = new Logger();
        $this->database = new Database($this->logger);
        $this->settings = new Settings($this->logger);
        $this->cache = new CacheManager($this->database, $this->settings, $this->logger);
        $this->fileController = new FileController($this->database, $this->cache, $this->logger);
        $this->generator = new Generator($this->cache, $this->settings, $this->fileController, $this->logger);
        $this->scheduler = new Scheduler($this->logger);
        $this->emailAttachments = new Attachments($this->logger);

        $this->initHooks();
    }

    /**
     * Get plugin instance.
     *
     * @return Plugin
     */
    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    private function initHooks(): void
    {
        // Admin interface.
        if (is_admin()) {
            $this->adminMenu = new AdminMenu($this->database, $this->generator, $this->settings, $this->logger);
        }

        // REST API.
        add_action('rest_api_init', function () {
            $this->api = new API($this->generator, $this->database, $this->fileController, $this->logger);
        });

        // WP-CLI.
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('tpwc', Commands::class);
        }

        // WooCommerce hooks.
        add_action('woocommerce_order_status_changed', [$this, 'onOrderStatusChanged'], 10, 4);

        // File download controller.
        add_action('init', [$this->fileController, 'handleDownloadRequest']);

        // Cron cleanup.
        add_action('tpwc_cleanup_old_pdfs', [$this->cache, 'cleanupOldFiles']);

        // Schedule cleanup with transient lock to prevent race conditions
        if (!wp_next_scheduled('tpwc_cleanup_old_pdfs')) {
            // Use transient lock to prevent concurrent scheduling
            if (false === get_transient('tpwc_scheduling_cleanup')) {
                set_transient('tpwc_scheduling_cleanup', 1, 60); // 60 second lock
                if (!wp_next_scheduled('tpwc_cleanup_old_pdfs')) {
                    wp_schedule_event(time(), 'daily', 'tpwc_cleanup_old_pdfs');
                }
                delete_transient('tpwc_scheduling_cleanup');
            }
        }

        // Load text domain.
        add_action('init', [$this, 'loadTextDomain']);

        // Declare HPOS compatibility.
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                    'custom_order_tables',
                    TPWC_HEBREW_PDF_FILE,
                    true
                );
            }
        });
    }

    /**
     * Handle order status changed.
     *
     * @param int       $orderId     Order ID.
     * @param string    $oldStatus   Old status.
     * @param string    $newStatus   New status.
     * @param \WC_Order $order       Order object.
     * @return void
     */
    public function onOrderStatusChanged(int $orderId, string $oldStatus, string $newStatus, \WC_Order $order): void
    {
        $autoGenerateStatuses = $this->settings->get('auto_generate_statuses', []);

        if (!is_array($autoGenerateStatuses)) {
            $autoGenerateStatuses = [];
        }

        if (in_array($newStatus, $autoGenerateStatuses, true)) {
            try {
                $this->generator->generateOrderPdf($orderId);
            } catch (\Exception $e) {
                $this->logger->error("Failed to auto-generate PDF for order {$orderId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Load plugin text domain.
     *
     * @return void
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'tpwc-hebrew-pdf',
            false,
            dirname(TPWC_HEBREW_PDF_BASENAME) . '/languages'
        );
    }

    /**
     * Prevent cloning.
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization.
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}

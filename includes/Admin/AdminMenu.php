<?php
/**
 * Admin Menu Handler
 *
 * @package TPWC\HebrewPdf\Admin
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\Admin;

use TPWC\HebrewPdf\Database\Database;
use TPWC\HebrewPdf\PDF\Generator;
use TPWC\HebrewPdf\Logger;

/**
 * Manages admin menu and pages.
 */
class AdminMenu
{
    /**
     * Database instance.
     *
     * @var Database
     */
    private Database $database;

    /**
     * PDF generator instance.
     *
     * @var Generator
     */
    private Generator $generator;

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
     * Constructor.
     *
     * @param Database  $database  Database instance.
     * @param Generator $generator PDF generator instance.
     * @param Settings  $settings  Settings instance.
     * @param Logger    $logger    Logger instance.
     */
    public function __construct(Database $database, Generator $generator, Settings $settings, Logger $logger)
    {
        $this->database = $database;
        $this->generator = $generator;
        $this->settings = $settings;
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
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('woocommerce_screen_ids', [$this, 'addScreenIds']);

        // Order actions.
        add_filter('woocommerce_admin_order_actions', [$this, 'addOrderActions'], 10, 2);
        add_action('admin_action_tpwc_generate_order_pdf', [$this, 'handleGenerateOrderAction']);
    }

    /**
     * Add menu pages.
     *
     * @return void
     */
    public function addMenuPages(): void
    {
        // Add submenu under WooCommerce.
        add_submenu_page(
            'woocommerce',
            __('PDF Documents', 'tpwc-hebrew-pdf'),
            __('PDF Docs', 'tpwc-hebrew-pdf'),
            'manage_woocommerce',
            'tpwc-pdf-docs',
            [$this, 'renderListPage']
        );

        add_submenu_page(
            'woocommerce',
            __('PDF Settings', 'tpwc-hebrew-pdf'),
            __('PDF Settings', 'tpwc-hebrew-pdf'),
            'manage_options',
            'tpwc-pdf-settings',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'tpwc-pdf') === false) {
            return;
        }

        wp_enqueue_style(
            'tpwc-admin',
            TPWC_HEBREW_PDF_URL . 'assets/css/admin.css',
            [],
            TPWC_HEBREW_PDF_VERSION
        );

        wp_enqueue_script(
            'tpwc-admin',
            TPWC_HEBREW_PDF_URL . 'assets/js/admin.js',
            ['jquery', 'wp-util'],
            TPWC_HEBREW_PDF_VERSION,
            true
        );

        wp_localize_script('tpwc-admin', 'tpwcAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpwc_admin'),
        ]);

        // Enqueue media uploader.
        wp_enqueue_media();
    }

    /**
     * Add WooCommerce screen IDs.
     *
     * @param array $ids Screen IDs.
     * @return array Modified screen IDs.
     */
    public function addScreenIds(array $ids): array
    {
        $ids[] = 'woocommerce_page_tpwc-pdf-docs';
        $ids[] = 'woocommerce_page_tpwc-pdf-settings';
        return $ids;
    }

    /**
     * Render PDF documents list page.
     *
     * @return void
     */
    public function renderListPage(): void
    {
        require_once TPWC_HEBREW_PDF_PATH . 'includes/Admin/ListTable.php';

        $listTable = new ListTable($this->database, $this->generator, $this->logger);
        $listTable->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('PDF Documents', 'tpwc-hebrew-pdf') . '</h1>';

        // Display cache stats.
        $this->displayCacheStats();

        // List table.
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="tpwc-pdf-docs" />';
        $listTable->search_box(__('Search', 'tpwc-hebrew-pdf'), 'tpwc-search');
        $listTable->display();
        echo '</form>';

        echo '</div>';
    }

    /**
     * Display cache statistics.
     *
     * @return void
     */
    private function displayCacheStats(): void
    {
        $stats = $this->database->getStats();

        echo '<div class="tpwc-stats" style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin: 20px 0;">';
        echo '<h3 style="margin-top: 0;">' . esc_html__('Cache Statistics', 'tpwc-hebrew-pdf') . '</h3>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';

        $this->renderStatBox(__('Total Files', 'tpwc-hebrew-pdf'), number_format_i18n($stats['total_files']));
        $this->renderStatBox(__('Total Size', 'tpwc-hebrew-pdf'), size_format($stats['total_bytes']));
        $this->renderStatBox(__('Cached', 'tpwc-hebrew-pdf'), number_format_i18n($stats['cached_files']));
        $this->renderStatBox(__('Stale', 'tpwc-hebrew-pdf'), number_format_i18n($stats['stale_files']));
        $this->renderStatBox(__('Order PDFs', 'tpwc-hebrew-pdf'), number_format_i18n($stats['order_pdfs']));
        $this->renderStatBox(__('Transaction PDFs', 'tpwc-hebrew-pdf'), number_format_i18n($stats['transaction_pdfs']));

        echo '</div>';
        echo '</div>';
    }

    /**
     * Render a stat box.
     *
     * @param string $label Label.
     * @param string $value Value.
     * @return void
     */
    private function renderStatBox(string $label, string $value): void
    {
        echo '<div style="text-align: center; padding: 10px; background: #f0f0f1; border-radius: 3px;">';
        echo '<div style="font-size: 24px; font-weight: 600; color: #2271b1;">' . esc_html($value) . '</div>';
        echo '<div style="font-size: 12px; color: #646970; margin-top: 5px;">' . esc_html($label) . '</div>';
        echo '</div>';
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function renderSettingsPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('PDF Settings', 'tpwc-hebrew-pdf') . '</h1>';

        if (isset($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'tpwc-hebrew-pdf') . '</p></div>';

            // Update logo hash if logo changed.
            $this->settings->updateLogoHash();
        }

        echo '<form method="post" action="options.php">';
        settings_fields('tpwc_settings');
        do_settings_sections('tpwc_settings');
        submit_button();
        echo '</form>';

        // Regenerate all button.
        echo '<hr />';
        echo '<h2>' . esc_html__('Advanced Actions', 'tpwc-hebrew-pdf') . '</h2>';
        echo '<p>' . esc_html__('Warning: This will invalidate all cached PDFs and they will be regenerated on next access.', 'tpwc-hebrew-pdf') . '</p>';
        echo '<form method="post" action="">';
        wp_nonce_field('tpwc_regenerate_all', 'tpwc_nonce');
        submit_button(__('Invalidate All Cached PDFs', 'tpwc-hebrew-pdf'), 'delete', 'tpwc_regenerate_all');
        echo '</form>';

        if (isset($_POST['tpwc_regenerate_all']) && check_admin_referer('tpwc_regenerate_all', 'tpwc_nonce')) {
            $this->settings->incrementVersion('template_version');
            echo '<div class="notice notice-success"><p>' . esc_html__('All cached PDFs have been invalidated.', 'tpwc-hebrew-pdf') . '</p></div>';
        }

        echo '</div>';
    }

    /**
     * Add order actions.
     *
     * @param array     $actions Order actions.
     * @param \WC_Order $order   Order object.
     * @return array Modified actions.
     */
    public function addOrderActions(array $actions, \WC_Order $order): array
    {
        $actions['tpwc_generate_pdf'] = [
            'url' => wp_nonce_url(
                admin_url('admin.php?action=tpwc_generate_order_pdf&order_id=' . $order->get_id()),
                'tpwc_generate_order_pdf'
            ),
            'name' => __('Generate PDF', 'tpwc-hebrew-pdf'),
            'action' => 'tpwc_generate_pdf',
        ];

        return $actions;
    }

    /**
     * Handle generate order PDF action.
     *
     * @return void
     */
    public function handleGenerateOrderAction(): void
    {
        check_admin_referer('tpwc_generate_order_pdf');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'tpwc-hebrew-pdf'));
        }

        $orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

        if (!$orderId) {
            wp_die(esc_html__('Invalid order ID.', 'tpwc-hebrew-pdf'));
        }

        $recordId = $this->generator->generateOrderPdf($orderId, true);

        if ($recordId) {
            wp_redirect(add_query_arg([
                'page' => 'tpwc-pdf-docs',
                'generated' => '1',
            ], admin_url('admin.php')));
        } else {
            wp_die(esc_html__('Failed to generate PDF.', 'tpwc-hebrew-pdf'));
        }

        exit;
    }
}

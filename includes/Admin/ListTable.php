<?php
/**
 * PDF Documents List Table
 *
 * @package TPWC\HebrewPdf\Admin
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\Admin;

use TPWC\HebrewPdf\Database\Database;
use TPWC\HebrewPdf\PDF\Generator;
use TPWC\HebrewPdf\Logger;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * PDF documents list table.
 */
class ListTable extends \WP_List_Table
{
    /**
     * Database instance.
     *
     * @var Database
     */
    private Database $database;

    /**
     * Generator instance.
     *
     * @var Generator
     */
    private Generator $generator;

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
     * @param Generator $generator Generator instance.
     * @param Logger    $logger    Logger instance.
     */
    public function __construct(Database $database, Generator $generator, Logger $logger)
    {
        $this->database = $database;
        $this->generator = $generator;
        $this->logger = $logger;

        parent::__construct([
            'singular' => 'pdf',
            'plural' => 'pdfs',
            'ajax' => false,
        ]);

        $this->processBulkActions();
        $this->handleExport();
    }

    /**
     * Get columns.
     *
     * @return array Columns.
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'type' => __('Type', 'tpwc-hebrew-pdf'),
            'ref' => __('Reference', 'tpwc-hebrew-pdf'),
            'customer' => __('Customer', 'tpwc-hebrew-pdf'),
            'amount' => __('Amount', 'tpwc-hebrew-pdf'),
            'created' => __('Created', 'tpwc-hebrew-pdf'),
            'status' => __('Status', 'tpwc-hebrew-pdf'),
            'size' => __('Size', 'tpwc-hebrew-pdf'),
            'actions' => __('Actions', 'tpwc-hebrew-pdf'),
        ];
    }

    /**
     * Get sortable columns.
     *
     * @return array Sortable columns.
     */
    protected function get_sortable_columns(): array
    {
        return [
            'type' => ['type', false],
            'ref' => ['ref_id', false],
            'created' => ['created_at', true],
            'size' => ['bytes', false],
        ];
    }

    /**
     * Get bulk actions.
     *
     * @return array Bulk actions.
     */
    protected function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete', 'tpwc-hebrew-pdf'),
            'regenerate' => __('Regenerate', 'tpwc-hebrew-pdf'),
            'export_csv' => __('Export CSV', 'tpwc-hebrew-pdf'),
        ];
    }

    /**
     * Get filter options.
     *
     * @return void
     */
    protected function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }

        echo '<div class="alignleft actions">';

        // Type filter.
        $currentType = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';
        echo '<select name="filter_type">';
        echo '<option value="">' . esc_html__('All Types', 'tpwc-hebrew-pdf') . '</option>';
        echo '<option value="order"' . selected($currentType, 'order', false) . '>' . esc_html__('Order', 'tpwc-hebrew-pdf') . '</option>';
        echo '<option value="transaction"' . selected($currentType, 'transaction', false) . '>' . esc_html__('Transaction', 'tpwc-hebrew-pdf') . '</option>';
        echo '</select>';

        // Status filter.
        $currentStatus = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        echo '<select name="filter_status">';
        echo '<option value="">' . esc_html__('All Statuses', 'tpwc-hebrew-pdf') . '</option>';
        echo '<option value="cached"' . selected($currentStatus, 'cached', false) . '>' . esc_html__('Cached', 'tpwc-hebrew-pdf') . '</option>';
        echo '<option value="stale"' . selected($currentStatus, 'stale', false) . '>' . esc_html__('Stale', 'tpwc-hebrew-pdf') . '</option>';
        echo '</select>';

        // Date filters.
        $dateFrom = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $dateTo = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

        echo '<input type="date" name="date_from" value="' . esc_attr($dateFrom) . '" placeholder="' . esc_attr__('From', 'tpwc-hebrew-pdf') . '" />';
        echo '<input type="date" name="date_to" value="' . esc_attr($dateTo) . '" placeholder="' . esc_attr__('To', 'tpwc-hebrew-pdf') . '" />';

        submit_button(__('Filter', 'tpwc-hebrew-pdf'), '', 'filter_action', false);

        echo '</div>';
    }

    /**
     * Prepare items.
     *
     * @return void
     */
    public function prepare_items(): void
    {
        $perPage = 20;
        $currentPage = $this->get_pagenum();

        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

        $args = [
            'per_page' => $perPage,
            'page' => $currentPage,
            'orderby' => $orderby,
            'order' => $order,
            'type' => isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '',
            'status' => isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '',
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
        ];

        $result = $this->database->getPaginated($args);

        $this->items = $result['items'];

        $this->set_pagination_args([
            'total_items' => $result['total'],
            'per_page' => $perPage,
            'total_pages' => ceil($result['total'] / $perPage),
        ]);
    }

    /**
     * Render checkbox column.
     *
     * @param object $item Item.
     * @return string Checkbox HTML.
     */
    protected function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="ids[]" value="%d" />', $item->id);
    }

    /**
     * Render type column.
     *
     * @param object $item Item.
     * @return string Column HTML.
     */
    protected function column_type($item): string
    {
        $badges = [
            'order' => '<span class="tpwc-badge tpwc-badge-order">' . __('Order', 'tpwc-hebrew-pdf') . '</span>',
            'transaction' => '<span class="tpwc-badge tpwc-badge-transaction">' . __('Transaction', 'tpwc-hebrew-pdf') . '</span>',
        ];

        return $badges[$item->type] ?? $item->type;
    }

    /**
     * Render reference column.
     *
     * @param object $item Item.
     * @return string Column HTML.
     */
    protected function column_ref($item): string
    {
        if ($item->type === 'order') {
            $order = wc_get_order($item->ref_id);
            if ($order) {
                return sprintf(
                    '<a href="%s">#%s</a>',
                    esc_url(get_edit_post_link($item->ref_id)),
                    esc_html($order->get_order_number())
                );
            }
        }

        return esc_html((string) $item->ref_id);
    }

    /**
     * Render customer column.
     *
     * @param object $item Item.
     * @return string Column HTML.
     */
    protected function column_customer($item): string
    {
        return esc_html($item->customer_name ?: '-');
    }

    /**
     * Render amount column.
     *
     * @param object $item Item.
     * @return string Column HTML.
     */
    protected function column_amount($item): string
    {
        if ($item->amount === null) {
            return '-';
        }

        return wc_price($item->amount);
    }

    /**
     * Render created column.
     *
     * @param object $item Item.
     * @return string Column HTML.
     */
    protected function column_created($item): string
    {
        return sprintf(
            '<abbr title="%s">%s</abbr>',
            esc_attr($item->created_at),
            esc_html(human_time_diff(strtotime($item->created_at), current_time('timestamp')) . ' ago')
        );
    }

    /**
     * Render status column.
     *
     * @param object $item Item.
     * @return string Column HTML.
     */
    protected function column_status($item): string
    {
        $badges = [
            'cached' => '<span class="tpwc-status tpwc-status-cached">' . __('Cached', 'tpwc-hebrew-pdf') . '</span>',
            'stale' => '<span class="tpwc-status tpwc-status-stale">' . __('Stale', 'tpwc-hebrew-pdf') . '</span>',
        ];

        return $badges[$item->status] ?? $item->status;
    }

    /**
     * Render size column.
     *
     * @param object $item Item.
     * @return string Column HTML.
     */
    protected function column_size($item): string
    {
        return size_format($item->bytes);
    }

    /**
     * Render actions column.
     *
     * @param object $item Item.
     * @return string Column HTML.
     */
    protected function column_actions($item): string
    {
        $fileController = \TPWC\HebrewPdf\Plugin::instance()->fileController;

        $previewUrl = $fileController->generateSignedUrl((int) $item->id, 900, true);
        $downloadUrl = $fileController->generateSignedUrl((int) $item->id, 900, false);

        $actions = [];

        $actions[] = sprintf(
            '<a href="%s" target="_blank" class="button button-small">%s</a>',
            esc_url($previewUrl),
            esc_html__('Preview', 'tpwc-hebrew-pdf')
        );

        $actions[] = sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            esc_url($downloadUrl),
            esc_html__('Download', 'tpwc-hebrew-pdf')
        );

        $actions[] = sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            esc_url(wp_nonce_url(
                add_query_arg(['action' => 'regenerate', 'id' => $item->id]),
                'tpwc_regenerate_' . $item->id
            )),
            esc_html__('Regenerate', 'tpwc-hebrew-pdf')
        );

        $actions[] = sprintf(
            '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\')">%s</a>',
            esc_url(wp_nonce_url(
                add_query_arg(['action' => 'delete', 'id' => $item->id]),
                'tpwc_delete_' . $item->id
            )),
            esc_attr__('Are you sure?', 'tpwc-hebrew-pdf'),
            esc_html__('Delete', 'tpwc-hebrew-pdf')
        );

        return implode(' ', $actions);
    }

    /**
     * Process bulk actions.
     *
     * @return void
     */
    protected function processBulkActions(): void
    {
        // Single actions.
        if (isset($_GET['action'], $_GET['id'])) {
            $action = sanitize_text_field($_GET['action']);
            $id = (int) $_GET['id'];

            if ($action === 'delete' && check_admin_referer('tpwc_delete_' . $id)) {
                $fileController = \TPWC\HebrewPdf\Plugin::instance()->fileController;
                $fileController->deletePdf($id);
                wp_redirect(remove_query_arg(['action', 'id', '_wpnonce']));
                exit;
            }

            if ($action === 'regenerate' && check_admin_referer('tpwc_regenerate_' . $id)) {
                $this->generator->regeneratePdf($id);
                wp_redirect(remove_query_arg(['action', 'id', '_wpnonce']));
                exit;
            }
        }

        // Bulk actions.
        $action = $this->current_action();

        if (!$action || empty($_POST['ids'])) {
            return;
        }

        check_admin_referer('bulk-' . $this->_args['plural']);

        $ids = array_map('intval', $_POST['ids']);

        switch ($action) {
            case 'delete':
                $fileController = \TPWC\HebrewPdf\Plugin::instance()->fileController;
                foreach ($ids as $id) {
                    $fileController->deletePdf($id);
                }
                break;

            case 'regenerate':
                foreach ($ids as $id) {
                    $this->generator->regeneratePdf($id);
                }
                break;

            case 'export_csv':
                // Handled by handleExport().
                break;
        }

        wp_redirect(remove_query_arg(['action', 'action2', 'ids', '_wpnonce', '_wp_http_referer']));
        exit;
    }

    /**
     * Handle CSV export.
     *
     * @return void
     */
    protected function handleExport(): void
    {
        if ($this->current_action() !== 'export_csv' || empty($_POST['ids'])) {
            return;
        }

        check_admin_referer('bulk-' . $this->_args['plural']);

        $ids = array_map('intval', $_POST['ids']);

        // Get records.
        $records = [];
        foreach ($ids as $id) {
            $record = $this->database->get($id);
            if ($record) {
                $records[] = $record;
            }
        }

        if (empty($records)) {
            return;
        }

        // Generate CSV.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=tpwc-pdfs-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        // BOM for UTF-8.
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Headers.
        fputcsv($output, ['ID', 'Type', 'Reference', 'Customer', 'Amount', 'Created', 'Status', 'Size']);

        // Data.
        foreach ($records as $record) {
            fputcsv($output, [
                $record->id,
                $record->type,
                $record->ref_id,
                $record->customer_name,
                $record->amount,
                $record->created_at,
                $record->status,
                $record->bytes,
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Display no items message.
     *
     * @return void
     */
    public function no_items(): void
    {
        esc_html_e('No PDF documents found.', 'tpwc-hebrew-pdf');
    }
}

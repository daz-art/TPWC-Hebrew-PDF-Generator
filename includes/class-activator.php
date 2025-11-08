<?php
/**
 * Plugin Activator
 *
 * @package TPWC\HebrewPdf
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf;

use TPWC\HebrewPdf\Database\Database;

/**
 * Handles plugin activation.
 */
class Activator
{
    /**
     * Activate the plugin.
     *
     * @return void
     */
    public static function activate(): void
    {
        // Create database tables.
        $logger = new Logger();
        $database = new Database($logger);
        $database->createTables();

        // Create storage directory.
        self::createStorageDirectory();

        // Set default options.
        self::setDefaultOptions();

        // Flush rewrite rules.
        flush_rewrite_rules();

        // Log activation.
        $logger->info('TPWC Hebrew PDF Generator activated');
    }

    /**
     * Create storage directory.
     *
     * @return void
     */
    private static function createStorageDirectory(): void
    {
        $uploadDir = wp_upload_dir();
        $storageDir = $uploadDir['basedir'] . '/tpwc-pdfs';

        if (!file_exists($storageDir)) {
            wp_mkdir_p($storageDir);
        }

        // Create .htaccess to deny direct access.
        $htaccessFile = $storageDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            $htaccessContent = <<<'HTACCESS'
# Deny all direct access
<Files *>
    Order Allow,Deny
    Deny from all
</Files>
HTACCESS;
            file_put_contents($htaccessFile, $htaccessContent);
        }

        // Create index.php to prevent directory listing.
        $indexFile = $storageDir . '/index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, '<?php // Silence is golden');
        }

        // Create nginx.conf for nginx users.
        $nginxFile = $storageDir . '/nginx.conf';
        if (!file_exists($nginxFile)) {
            $nginxContent = <<<'NGINX'
# Deny all direct access to PDF files
location ~ ^/wp-content/uploads/tpwc-pdfs/ {
    deny all;
    return 403;
}
NGINX;
            file_put_contents($nginxFile, $nginxContent);
        }
    }

    /**
     * Set default plugin options.
     *
     * @return void
     */
    private static function setDefaultOptions(): void
    {
        $defaults = [
            'tpwc_business_profile_version' => 1,
            'tpwc_template_version' => 1,
            'tpwc_logo_hash' => '',
            'tpwc_signed_url_ttl' => 900, // 15 minutes
            'tpwc_auto_generate_statuses' => ['completed'],
            'tpwc_cleanup_ttl' => 90, // 90 days
            'tpwc_business_name_he' => '',
            'tpwc_business_name_en' => '',
            'tpwc_business_registration_id' => '',
            'tpwc_business_tax_id' => '',
            'tpwc_business_address' => '',
            'tpwc_business_phone' => '',
            'tpwc_business_email' => '',
            'tpwc_business_website' => '',
            'tpwc_accent_color' => '#2271b1',
            'tpwc_footer_text' => '',
            'tpwc_template_margins' => [
                'top' => 15,
                'right' => 15,
                'bottom' => 15,
                'left' => 15,
            ],
            'tpwc_show_header' => true,
            'tpwc_show_footer' => true,
            'tpwc_show_page_numbers' => true,
            'tpwc_order_show_shipping' => true,
            'tpwc_order_columns' => ['item', 'sku', 'qty', 'unit_price', 'line_total'],
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
}

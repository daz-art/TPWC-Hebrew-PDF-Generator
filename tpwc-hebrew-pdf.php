<?php
/**
 * Plugin Name: TPWC Hebrew PDF Generator
 * Plugin URI: https://github.com/daz-art/TPWC-Hebrew-PDF-Generator
 * Description: Generate Hebrew RTL PDF documents (Order Summary and Transaction Records) for WooCommerce with deterministic caching and Rubik font.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: TPWC
 * Author URI: https://github.com/daz-art
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: tpwc-hebrew-pdf
 * Domain Path: /languages
 * WC requires at least: 9.0
 * WC tested up to: 9.5
 *
 * @package TPWC\HebrewPdf
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
if (!defined('TPWC_HEBREW_PDF_VERSION')) {
    define('TPWC_HEBREW_PDF_VERSION', '1.0.0');
}
if (!defined('TPWC_HEBREW_PDF_FILE')) {
    define('TPWC_HEBREW_PDF_FILE', __FILE__);
}
if (!defined('TPWC_HEBREW_PDF_PATH')) {
    define('TPWC_HEBREW_PDF_PATH', plugin_dir_path(__FILE__));
}
if (!defined('TPWC_HEBREW_PDF_URL')) {
    define('TPWC_HEBREW_PDF_URL', plugin_dir_url(__FILE__));
}
if (!defined('TPWC_HEBREW_PDF_BASENAME')) {
    define('TPWC_HEBREW_PDF_BASENAME', plugin_basename(__FILE__));
}

// Check PHP version.
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>';
        echo esc_html__('TPWC Hebrew PDF Generator requires PHP 8.1 or higher.', 'tpwc-hebrew-pdf');
        echo '</p></div>';
    });
    return;
}

// Check required PHP extensions.
$required_extensions = ['intl', 'mbstring', 'json'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

// Check for GD or Imagick.
if (!extension_loaded('gd') && !extension_loaded('imagick')) {
    $missing_extensions[] = 'gd or imagick';
}

if (!empty($missing_extensions)) {
    add_action('admin_notices', function () use ($missing_extensions) {
        echo '<div class="error"><p>';
        printf(
            esc_html__('TPWC Hebrew PDF Generator requires the following PHP extensions: %s', 'tpwc-hebrew-pdf'),
            esc_html(implode(', ', $missing_extensions))
        );
        echo '</p></div>';
    });
    return;
}

// Require Composer autoloader.
$autoloader = TPWC_HEBREW_PDF_PATH . 'vendor/autoload.php';
if (!file_exists($autoloader)) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>';
        echo esc_html__('TPWC Hebrew PDF Generator: Please run "composer install" to install dependencies.', 'tpwc-hebrew-pdf');
        echo '</p></div>';
    });
    return;
}

require_once $autoloader;

// Initialize the plugin.
add_action('plugins_loaded', function () {
    // Check if WooCommerce is active.
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>';
            echo esc_html__('TPWC Hebrew PDF Generator requires WooCommerce to be installed and active.', 'tpwc-hebrew-pdf');
            echo '</p></div>';
        });
        return;
    }

    // Check WooCommerce version.
    $wc = WC();
    if (!$wc || !isset($wc->version)) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>';
            echo esc_html__('TPWC Hebrew PDF Generator: WooCommerce not properly initialized.', 'tpwc-hebrew-pdf');
            echo '</p></div>';
        });
        return;
    }

    if (version_compare($wc->version, '9.0', '<')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>';
            echo esc_html__('TPWC Hebrew PDF Generator requires WooCommerce 9.0 or higher.', 'tpwc-hebrew-pdf');
            echo '</p></div>';
        });
        return;
    }

    // Initialize the plugin.
    Plugin::instance();
}, 20);

// Activation hook.
register_activation_hook(__FILE__, function () {
    require_once TPWC_HEBREW_PDF_PATH . 'includes/class-activator.php';
    Activator::activate();
});

// Deactivation hook.
register_deactivation_hook(__FILE__, function () {
    require_once TPWC_HEBREW_PDF_PATH . 'includes/class-deactivator.php';
    Deactivator::deactivate();
});

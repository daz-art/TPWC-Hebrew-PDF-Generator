<?php
/**
 * Settings Handler
 *
 * @package TPWC\HebrewPdf\Admin
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\Admin;

use TPWC\HebrewPdf\Logger;

/**
 * Manages plugin settings.
 */
class Settings
{
    /**
     * Option prefix.
     */
    private const OPTION_PREFIX = 'tpwc_';

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
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public function registerSettings(): void
    {
        // Business Profile section.
        add_settings_section(
            'tpwc_business_profile',
            __('Business Profile', 'tpwc-hebrew-pdf'),
            null,
            'tpwc_settings'
        );

        $this->registerField('business_name_he', __('Business Name (Hebrew)', 'tpwc-hebrew-pdf'), 'text');
        $this->registerField('business_name_en', __('Business Name (English)', 'tpwc-hebrew-pdf'), 'text');
        $this->registerField('business_registration_id', __('Registration ID', 'tpwc-hebrew-pdf'), 'text');
        $this->registerField('business_tax_id', __('Tax ID (VAT)', 'tpwc-hebrew-pdf'), 'text');
        $this->registerField('business_address', __('Address', 'tpwc-hebrew-pdf'), 'textarea');
        $this->registerField('business_phone', __('Phone', 'tpwc-hebrew-pdf'), 'text');
        $this->registerField('business_email', __('Email', 'tpwc-hebrew-pdf'), 'email');
        $this->registerField('business_website', __('Website', 'tpwc-hebrew-pdf'), 'url');
        $this->registerField('business_logo', __('Logo (SVG or PNG)', 'tpwc-hebrew-pdf'), 'media');
        $this->registerField('accent_color', __('Accent Color', 'tpwc-hebrew-pdf'), 'color');
        $this->registerField('footer_text', __('Footer/Legal Text', 'tpwc-hebrew-pdf'), 'textarea');

        // Template Options section.
        add_settings_section(
            'tpwc_template_options',
            __('Template Options', 'tpwc-hebrew-pdf'),
            null,
            'tpwc_settings'
        );

        $this->registerField('template_margins', __('Margins (mm)', 'tpwc-hebrew-pdf'), 'margins');
        $this->registerField('show_header', __('Show Header', 'tpwc-hebrew-pdf'), 'checkbox');
        $this->registerField('show_footer', __('Show Footer', 'tpwc-hebrew-pdf'), 'checkbox');
        $this->registerField('show_page_numbers', __('Show Page Numbers', 'tpwc-hebrew-pdf'), 'checkbox');
        $this->registerField('order_show_shipping', __('Show Shipping on Orders', 'tpwc-hebrew-pdf'), 'checkbox');
        $this->registerField('order_columns', __('Order Columns', 'tpwc-hebrew-pdf'), 'checkboxes', [
            'options' => [
                'item' => __('Item', 'tpwc-hebrew-pdf'),
                'sku' => __('SKU', 'tpwc-hebrew-pdf'),
                'qty' => __('Quantity', 'tpwc-hebrew-pdf'),
                'unit_price' => __('Unit Price', 'tpwc-hebrew-pdf'),
                'line_total' => __('Line Total', 'tpwc-hebrew-pdf'),
            ],
        ]);

        // General Settings section.
        add_settings_section(
            'tpwc_general_settings',
            __('General Settings', 'tpwc-hebrew-pdf'),
            null,
            'tpwc_settings'
        );

        $this->registerField('auto_generate_statuses', __('Auto-generate on Order Statuses', 'tpwc-hebrew-pdf'), 'checkboxes', [
            'options' => $this->getOrderStatuses(),
        ]);
        $this->registerField('signed_url_ttl', __('Signed URL TTL (seconds)', 'tpwc-hebrew-pdf'), 'number');
        $this->registerField('cleanup_ttl', __('Cleanup TTL (days)', 'tpwc-hebrew-pdf'), 'number');
    }

    /**
     * Register a settings field.
     *
     * @param string $name  Field name.
     * @param string $label Field label.
     * @param string $type  Field type.
     * @param array  $args  Additional arguments.
     * @return void
     */
    private function registerField(string $name, string $label, string $type, array $args = []): void
    {
        $optionName = self::OPTION_PREFIX . $name;

        register_setting('tpwc_settings', $optionName, [
            'sanitize_callback' => [$this, 'sanitizeField'],
        ]);

        add_settings_field(
            $optionName,
            $label,
            [$this, 'renderField'],
            'tpwc_settings',
            $this->getSection($name),
            array_merge($args, [
                'name' => $optionName,
                'type' => $type,
                'label' => $label,
            ])
        );
    }

    /**
     * Get section for a field.
     *
     * @param string $fieldName Field name.
     * @return string Section ID.
     */
    private function getSection(string $fieldName): string
    {
        if (strpos($fieldName, 'business_') === 0) {
            return 'tpwc_business_profile';
        }

        if (in_array($fieldName, ['template_margins', 'show_header', 'show_footer', 'show_page_numbers', 'order_show_shipping', 'order_columns'], true)) {
            return 'tpwc_template_options';
        }

        return 'tpwc_general_settings';
    }

    /**
     * Render a settings field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function renderField(array $args): void
    {
        $name = $args['name'];
        $type = $args['type'];
        $value = get_option($name);

        // Ensure value is a string for text-based fields
        if (in_array($type, ['text', 'email', 'url', 'number', 'textarea'], true)) {
            $value = is_string($value) ? $value : '';
        }

        switch ($type) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
                printf(
                    '<input type="%s" name="%s" id="%s" value="%s" class="regular-text" />',
                    esc_attr($type),
                    esc_attr($name),
                    esc_attr($name),
                    esc_attr($value)
                );
                break;

            case 'color':
                printf(
                    '<input type="color" name="%s" id="%s" value="%s" />',
                    esc_attr($name),
                    esc_attr($name),
                    esc_attr($value ?: '#2271b1')
                );
                break;

            case 'textarea':
                printf(
                    '<textarea name="%s" id="%s" rows="5" class="large-text">%s</textarea>',
                    esc_attr($name),
                    esc_attr($name),
                    esc_textarea($value)
                );
                break;

            case 'checkbox':
                printf(
                    '<label><input type="checkbox" name="%s" id="%s" value="1" %s /> %s</label>',
                    esc_attr($name),
                    esc_attr($name),
                    checked($value, true, false),
                    esc_html__('Enable', 'tpwc-hebrew-pdf')
                );
                break;

            case 'checkboxes':
                $options = $args['options'] ?? [];
                $selectedValues = is_array($value) ? $value : [];

                foreach ($options as $optionValue => $optionLabel) {
                    printf(
                        '<label style="display: block; margin-bottom: 5px;"><input type="checkbox" name="%s[]" value="%s" %s /> %s</label>',
                        esc_attr($name),
                        esc_attr($optionValue),
                        checked(in_array($optionValue, $selectedValues, true), true, false),
                        esc_html($optionLabel)
                    );
                }
                break;

            case 'media':
                $attachmentId = (int) $value;
                $imageUrl = $attachmentId ? wp_get_attachment_url($attachmentId) : '';

                echo '<div class="tpwc-media-upload">';
                printf(
                    '<input type="hidden" name="%s" id="%s" value="%s" />',
                    esc_attr($name),
                    esc_attr($name),
                    esc_attr($attachmentId)
                );

                if ($imageUrl) {
                    printf(
                        '<img src="%s" style="max-width: 200px; display: block; margin-bottom: 10px;" />',
                        esc_url($imageUrl)
                    );
                }

                printf(
                    '<button type="button" class="button tpwc-upload-media" data-target="%s">%s</button>',
                    esc_attr($name),
                    esc_html__('Select Logo', 'tpwc-hebrew-pdf')
                );

                if ($attachmentId) {
                    printf(
                        ' <button type="button" class="button tpwc-remove-media" data-target="%s">%s</button>',
                        esc_attr($name),
                        esc_html__('Remove', 'tpwc-hebrew-pdf')
                    );
                }

                echo '</div>';
                break;

            case 'margins':
                $margins = is_array($value) ? $value : [
                    'top' => 15,
                    'right' => 15,
                    'bottom' => 15,
                    'left' => 15,
                ];

                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    printf(
                        '<label style="display: inline-block; margin-right: 15px;">%s: <input type="number" name="%s[%s]" value="%s" min="0" max="50" style="width: 60px;" /></label>',
                        esc_html(ucfirst($side)),
                        esc_attr($name),
                        esc_attr($side),
                        esc_attr($margins[$side] ?? 15)
                    );
                }
                break;
        }
    }

    /**
     * Sanitize field value.
     *
     * @param mixed $value Field value.
     * @return mixed Sanitized value.
     */
    public function sanitizeField($value)
    {
        // Handle arrays (checkboxes, margins).
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }

        // Handle scalar values.
        return sanitize_text_field($value);
    }

    /**
     * Get a setting value.
     *
     * @param string $name    Setting name.
     * @param mixed  $default Default value.
     * @return mixed Setting value.
     */
    public function get(string $name, $default = null)
    {
        $optionName = self::OPTION_PREFIX . $name;
        $value = get_option($optionName, $default);

        return $value !== false ? $value : $default;
    }

    /**
     * Set a setting value.
     *
     * @param string $name  Setting name.
     * @param mixed  $value Setting value.
     * @return bool True on success, false on failure.
     */
    public function set(string $name, $value): bool
    {
        $optionName = self::OPTION_PREFIX . $name;
        return update_option($optionName, $value);
    }

    /**
     * Increment a version setting.
     *
     * @param string $name Version setting name.
     * @return int New version number.
     */
    public function incrementVersion(string $name): int
    {
        $optionName = self::OPTION_PREFIX . $name;
        $version = (int) get_option($optionName, 0);
        $newVersion = $version + 1;
        update_option($optionName, $newVersion);

        $this->logger->info("Incremented {$name} to {$newVersion}");

        return $newVersion;
    }

    /**
     * Get WooCommerce order statuses.
     *
     * @return array Order statuses.
     */
    private function getOrderStatuses(): array
    {
        if (!function_exists('wc_get_order_statuses')) {
            return [];
        }

        return wc_get_order_statuses();
    }

    /**
     * Get business profile data.
     *
     * @return array Business profile data.
     */
    public function getBusinessProfile(): array
    {
        return [
            'name_he' => $this->get('business_name_he', ''),
            'name_en' => $this->get('business_name_en', ''),
            'registration_id' => $this->get('business_registration_id', ''),
            'tax_id' => $this->get('business_tax_id', ''),
            'address' => $this->get('business_address', ''),
            'phone' => $this->get('business_phone', ''),
            'email' => $this->get('business_email', ''),
            'website' => $this->get('business_website', ''),
            'logo' => $this->get('business_logo', 0),
            'accent_color' => $this->get('accent_color', '#2271b1'),
            'footer_text' => $this->get('footer_text', ''),
        ];
    }

    /**
     * Get logo file path.
     *
     * @return string|null Logo file path or null.
     */
    public function getLogoPath(): ?string
    {
        $logoId = (int) $this->get('business_logo', 0);

        if (!$logoId) {
            return null;
        }

        $filePath = get_attached_file($logoId);

        return $filePath && file_exists($filePath) ? $filePath : null;
    }

    /**
     * Get logo hash for cache invalidation.
     *
     * @return string Logo hash.
     */
    public function getLogoHash(): string
    {
        $logoPath = $this->getLogoPath();

        if (!$logoPath) {
            return '';
        }

        return hash_file('sha256', $logoPath);
    }

    /**
     * Update logo hash.
     *
     * @return void
     */
    public function updateLogoHash(): void
    {
        $hash = $this->getLogoHash();
        $this->set('logo_hash', $hash);
    }
}

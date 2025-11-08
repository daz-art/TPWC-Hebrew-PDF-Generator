<?php
/**
 * Order Summary PDF Template
 *
 * @package TPWC\HebrewPdf\Templates
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\Templates;

use TPWC\HebrewPdf\Admin\Settings;
use TPWC\HebrewPdf\Logger;
use NumberFormatter;
use IntlDateFormatter;

/**
 * Order Summary template renderer.
 */
class OrderTemplate
{
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
     * Number formatter.
     *
     * @var NumberFormatter
     */
    private NumberFormatter $numberFormatter;

    /**
     * Date formatter.
     *
     * @var IntlDateFormatter
     */
    private IntlDateFormatter $dateFormatter;

    /**
     * Constructor.
     *
     * @param Settings $settings Settings instance.
     * @param Logger   $logger   Logger instance.
     */
    public function __construct(Settings $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;

        // Initialize formatters for he_IL locale.
        $this->numberFormatter = new NumberFormatter('he_IL', NumberFormatter::CURRENCY);
        $this->dateFormatter = new IntlDateFormatter(
            'he_IL',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE
        );
    }

    /**
     * Render the order PDF template.
     *
     * @param \WC_Order $order Order object.
     * @return string HTML content.
     */
    public function render(\WC_Order $order): string
    {
        $businessProfile = $this->settings->getBusinessProfile();
        $accentColor = $businessProfile['accent_color'];

        $html = '';

        // Header.
        if ($this->settings->get('show_header', true)) {
            $html .= $this->renderHeader($businessProfile, $accentColor);
        }

        // Document title.
        $html .= '<h1 style="text-align: center; color: ' . esc_attr($accentColor) . '; margin: 20px 0;">סיכום הזמנה</h1>';

        // Business details.
        $html .= $this->renderBusinessDetails($businessProfile);

        // Customer details.
        $html .= $this->renderCustomerDetails($order);

        // Order metadata.
        $html .= $this->renderOrderMetadata($order);

        // Products table.
        $html .= $this->renderProductsTable($order, $accentColor);

        // Totals.
        $html .= $this->renderTotals($order);

        // Footer.
        if ($this->settings->get('show_footer', true)) {
            $html .= $this->renderFooter($businessProfile);
        }

        return $html;
    }

    /**
     * Render header with logo.
     *
     * @param array  $profile     Business profile.
     * @param string $accentColor Accent color.
     * @return string HTML.
     */
    private function renderHeader(array $profile, string $accentColor): string
    {
        $html = '<div style="border-bottom: 3px solid ' . esc_attr($accentColor) . '; padding-bottom: 15px; margin-bottom: 20px;">';

        $logoPath = $this->settings->getLogoPath();
        if ($logoPath) {
            $logoData = $this->getImageData($logoPath);
            if ($logoData) {
                $html .= '<div style="text-align: center;">';
                $html .= '<img src="' . $logoData . '" style="max-width: 200px; max-height: 80px;" />';
                $html .= '</div>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render business details.
     *
     * @param array $profile Business profile.
     * @return string HTML.
     */
    private function renderBusinessDetails(array $profile): string
    {
        $html = '<div style="margin-bottom: 20px; padding: 10px; background-color: #f8f9fa;">';
        $html .= '<h3 style="margin: 0 0 10px 0;">פרטי העסק</h3>';

        if (!empty($profile['name_he'])) {
            $html .= '<p style="margin: 3px 0;"><strong>' . esc_html($profile['name_he']) . '</strong></p>';
        }

        if (!empty($profile['name_en'])) {
            $html .= '<p style="margin: 3px 0;"><bdi dir="ltr">' . esc_html($profile['name_en']) . '</bdi></p>';
        }

        if (!empty($profile['registration_id'])) {
            $html .= '<p style="margin: 3px 0;">ח.פ: ' . esc_html($profile['registration_id']) . '</p>';
        }

        if (!empty($profile['tax_id'])) {
            $html .= '<p style="margin: 3px 0;">מס׳ עוסק מורשה: ' . esc_html($profile['tax_id']) . '</p>';
        }

        if (!empty($profile['address'])) {
            $html .= '<p style="margin: 3px 0;">' . nl2br(esc_html($profile['address'])) . '</p>';
        }

        if (!empty($profile['phone'])) {
            $html .= '<p style="margin: 3px 0;">טל: <bdi dir="ltr">' . esc_html($profile['phone']) . '</bdi></p>';
        }

        if (!empty($profile['email'])) {
            $html .= '<p style="margin: 3px 0;">דוא״ל: <bdi dir="ltr">' . esc_html($profile['email']) . '</bdi></p>';
        }

        if (!empty($profile['website'])) {
            $html .= '<p style="margin: 3px 0;">אתר: <bdi dir="ltr">' . esc_html($profile['website']) . '</bdi></p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render customer details.
     *
     * @param \WC_Order $order Order object.
     * @return string HTML.
     */
    private function renderCustomerDetails(\WC_Order $order): string
    {
        $html = '<div style="margin-bottom: 20px; padding: 10px; background-color: #f8f9fa;">';
        $html .= '<h3 style="margin: 0 0 10px 0;">פרטי הלקוח</h3>';

        $name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if ($name) {
            $html .= '<p style="margin: 3px 0;"><strong>' . esc_html($name) . '</strong></p>';
        }

        if ($order->get_billing_company()) {
            $html .= '<p style="margin: 3px 0;">' . esc_html($order->get_billing_company()) . '</p>';
        }

        $address = trim(
            $order->get_billing_address_1() . ' ' .
            $order->get_billing_address_2() . ', ' .
            $order->get_billing_city() . ' ' .
            $order->get_billing_postcode()
        );
        if ($address !== ', ') {
            $html .= '<p style="margin: 3px 0;">' . esc_html($address) . '</p>';
        }

        if ($order->get_billing_email()) {
            $html .= '<p style="margin: 3px 0;">דוא״ל: <bdi dir="ltr">' . esc_html($order->get_billing_email()) . '</bdi></p>';
        }

        if ($order->get_billing_phone()) {
            $html .= '<p style="margin: 3px 0;">טל: <bdi dir="ltr">' . esc_html($order->get_billing_phone()) . '</bdi></p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render order metadata.
     *
     * @param \WC_Order $order Order object.
     * @return string HTML.
     */
    private function renderOrderMetadata(\WC_Order $order): string
    {
        $html = '<div style="margin-bottom: 20px;">';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr>';
        $html .= '<td style="width: 50%; padding: 5px;"><strong>מספר הזמנה:</strong> ' . esc_html($order->get_order_number()) . '</td>';
        $html .= '<td style="width: 50%; padding: 5px;"><strong>תאריך:</strong> ' . esc_html($this->formatDate($order->get_date_created())) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="padding: 5px;"><strong>סטטוס:</strong> ' . esc_html(wc_get_order_status_name($order->get_status())) . '</td>';
        $html .= '<td style="padding: 5px;"><strong>אמצעי תשלום:</strong> ' . esc_html($order->get_payment_method_title()) . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render products table.
     *
     * @param \WC_Order $order       Order object.
     * @param string    $accentColor Accent color.
     * @return string HTML.
     */
    private function renderProductsTable(\WC_Order $order, string $accentColor): string
    {
        $columns = $this->settings->get('order_columns', ['item', 'sku', 'qty', 'unit_price', 'line_total']);

        $html = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;" autosize="1">';

        // Table header.
        $html .= '<thead>';
        $html .= '<tr style="background-color: ' . esc_attr($accentColor) . '; color: white;">';

        foreach ($columns as $column) {
            $html .= '<th style="padding: 8px; text-align: right; border: 1px solid #ddd;">';
            $html .= $this->getColumnLabel($column);
            $html .= '</th>';
        }

        $html .= '</tr>';
        $html .= '</thead>';

        // Table body.
        $html .= '<tbody>';

        $rowIndex = 0;
        foreach ($order->get_items() as $item) {
            $bgColor = $rowIndex % 2 === 0 ? '#ffffff' : '#f8f9fa';
            $html .= '<tr style="background-color: ' . $bgColor . ';">';

            foreach ($columns as $column) {
                $html .= '<td style="padding: 8px; border: 1px solid #ddd;">';
                $html .= $this->getColumnValue($column, $item, $order);
                $html .= '</td>';
            }

            $html .= '</tr>';
            $rowIndex++;
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Get column label.
     *
     * @param string $column Column name.
     * @return string Label.
     */
    private function getColumnLabel(string $column): string
    {
        $labels = [
            'item' => 'פריט',
            'sku' => 'מק״ט',
            'qty' => 'כמות',
            'unit_price' => 'מחיר יחידה',
            'line_total' => 'סה״כ',
        ];

        return $labels[$column] ?? $column;
    }

    /**
     * Get column value.
     *
     * @param string         $column Column name.
     * @param \WC_Order_Item $item   Order item.
     * @param \WC_Order      $order  Order object.
     * @return string Value.
     */
    private function getColumnValue(string $column, \WC_Order_Item $item, \WC_Order $order): string
    {
        switch ($column) {
            case 'item':
                return esc_html($item->get_name());

            case 'sku':
                $product = $item->get_product();
                $sku = $product ? $product->get_sku() : '';
                return $sku ? '<bdi dir="ltr">' . esc_html($sku) . '</bdi>' : '-';

            case 'qty':
                return esc_html((string) $item->get_quantity());

            case 'unit_price':
                $unitPrice = $item->get_subtotal() / max(1, $item->get_quantity());
                return $this->formatPrice($unitPrice, $order->get_currency());

            case 'line_total':
                return $this->formatPrice($item->get_total(), $order->get_currency());

            default:
                return '';
        }
    }

    /**
     * Render totals section.
     *
     * @param \WC_Order $order Order object.
     * @return string HTML.
     */
    private function renderTotals(\WC_Order $order): string
    {
        $html = '<div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa;">';
        $html .= '<table style="width: 100%; max-width: 400px; margin-right: auto;">';

        // Subtotal.
        $html .= '<tr>';
        $html .= '<td style="padding: 5px;"><strong>סכום ביניים:</strong></td>';
        $html .= '<td style="padding: 5px; text-align: left;">' . $this->formatPrice($order->get_subtotal(), $order->get_currency()) . '</td>';
        $html .= '</tr>';

        // Shipping.
        if ($this->settings->get('order_show_shipping', true) && $order->get_shipping_total() > 0) {
            $html .= '<tr>';
            $html .= '<td style="padding: 5px;"><strong>משלוח:</strong></td>';
            $html .= '<td style="padding: 5px; text-align: left;">' . $this->formatPrice($order->get_shipping_total(), $order->get_currency()) . '</td>';
            $html .= '</tr>';
        }

        // Tax.
        if ($order->get_total_tax() > 0) {
            $html .= '<tr>';
            $html .= '<td style="padding: 5px;"><strong>מע״מ:</strong></td>';
            $html .= '<td style="padding: 5px; text-align: left;">' . $this->formatPrice($order->get_total_tax(), $order->get_currency()) . '</td>';
            $html .= '</tr>';
        }

        // Grand total.
        $html .= '<tr style="border-top: 2px solid #333;">';
        $html .= '<td style="padding: 10px 5px 5px 5px;"><strong style="font-size: 14pt;">סה״כ לתשלום:</strong></td>';
        $html .= '<td style="padding: 10px 5px 5px 5px; text-align: left;"><strong style="font-size: 14pt;">' . $this->formatPrice($order->get_total(), $order->get_currency()) . '</strong></td>';
        $html .= '</tr>';

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render footer.
     *
     * @param array $profile Business profile.
     * @return string HTML.
     */
    private function renderFooter(array $profile): string
    {
        $html = '<div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 9pt; color: #666; text-align: center;">';

        if ($this->settings->get('show_page_numbers', true)) {
            $html .= '<p style="margin: 5px 0;">עמוד {PAGENO} מתוך {nbpg}</p>';
        }

        if (!empty($profile['footer_text'])) {
            $html .= '<p style="margin: 5px 0;">' . nl2br(esc_html($profile['footer_text'])) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Format price using Intl NumberFormatter.
     *
     * @param float  $amount   Amount.
     * @param string $currency Currency code.
     * @return string Formatted price.
     */
    private function formatPrice(float $amount, string $currency): string
    {
        $this->numberFormatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);
        return $this->numberFormatter->formatCurrency($amount, $currency);
    }

    /**
     * Format date using Intl DateFormatter.
     *
     * @param \WC_DateTime $date Date object.
     * @return string Formatted date.
     */
    private function formatDate(\WC_DateTime $date): string
    {
        return $this->dateFormatter->format($date->getTimestamp());
    }

    /**
     * Get image data as base64 for embedding.
     *
     * @param string $path Image path.
     * @return string|null Data URI or null.
     */
    private function getImageData(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        $imageData = file_get_contents($path);
        if ($imageData === false) {
            return null;
        }

        $mimeType = mime_content_type($path);
        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }
}

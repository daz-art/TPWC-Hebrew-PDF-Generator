<?php
/**
 * Transaction Record PDF Template
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
 * Transaction Record template renderer.
 */
class TransactionTemplate
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
            IntlDateFormatter::MEDIUM
        );
    }

    /**
     * Render the transaction PDF template.
     *
     * @param int   $transactionId Transaction ID.
     * @param array $data          Transaction data.
     * @return string HTML content.
     */
    public function render(int $transactionId, array $data): string
    {
        $businessProfile = $this->settings->getBusinessProfile();
        $accentColor = $businessProfile['accent_color'];

        $html = '';

        // Header.
        if ($this->settings->get('show_header', true)) {
            $html .= $this->renderHeader($businessProfile, $accentColor);
        }

        // Document title.
        $html .= '<h1 style="text-align: center; color: ' . esc_attr($accentColor) . '; margin: 20px 0;">תיעוד עסקה</h1>';

        // Business details.
        $html .= $this->renderBusinessDetails($businessProfile);

        // Counterparty details.
        $html .= $this->renderCounterpartyDetails($data);

        // Transaction details.
        $html .= $this->renderTransactionDetails($transactionId, $data, $accentColor);

        // Related order (if any).
        if (!empty($data['related_order_id'])) {
            $html .= $this->renderRelatedOrder($data['related_order_id']);
        }

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
     * Render counterparty details.
     *
     * @param array $data Transaction data.
     * @return string HTML.
     */
    private function renderCounterpartyDetails(array $data): string
    {
        $html = '<div style="margin-bottom: 20px; padding: 10px; background-color: #f8f9fa;">';
        $html .= '<h3 style="margin: 0 0 10px 0;">פרטי הצד השני</h3>';

        if (!empty($data['counterparty_name'])) {
            $html .= '<p style="margin: 3px 0;"><strong>' . esc_html($data['counterparty_name']) . '</strong></p>';
        } else {
            $html .= '<p style="margin: 3px 0;">לא צוין</p>';
        }

        if (!empty($data['counterparty_email'])) {
            $html .= '<p style="margin: 3px 0;">דוא״ל: <bdi dir="ltr">' . esc_html($data['counterparty_email']) . '</bdi></p>';
        }

        if (!empty($data['counterparty_phone'])) {
            $html .= '<p style="margin: 3px 0;">טל: <bdi dir="ltr">' . esc_html($data['counterparty_phone']) . '</bdi></p>';
        }

        if (!empty($data['counterparty_address'])) {
            $html .= '<p style="margin: 3px 0;">' . nl2br(esc_html($data['counterparty_address'])) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render transaction details.
     *
     * @param int    $transactionId Transaction ID.
     * @param array  $data          Transaction data.
     * @param string $accentColor   Accent color.
     * @return string HTML.
     */
    private function renderTransactionDetails(int $transactionId, array $data, string $accentColor): string
    {
        $html = '<div style="margin-bottom: 20px; padding: 15px; border: 2px solid ' . esc_attr($accentColor) . ';">';
        $html .= '<h3 style="margin: 0 0 15px 0; color: ' . esc_attr($accentColor) . ';">פרטי העסקה</h3>';

        $html .= '<table style="width: 100%; border-collapse: collapse;">';

        // Transaction ID.
        $html .= '<tr>';
        $html .= '<td style="padding: 8px; width: 40%; background-color: #f8f9fa; border: 1px solid #ddd;"><strong>מספר עסקה:</strong></td>';
        $html .= '<td style="padding: 8px; border: 1px solid #ddd;"><bdi dir="ltr">' . esc_html((string) $transactionId) . '</bdi></td>';
        $html .= '</tr>';

        // Transaction type.
        $html .= '<tr>';
        $html .= '<td style="padding: 8px; background-color: #f8f9fa; border: 1px solid #ddd;"><strong>סוג עסקה:</strong></td>';
        $html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($this->getTransactionTypeLabel($data['type'] ?? '')) . '</td>';
        $html .= '</tr>';

        // Amount.
        if (isset($data['amount'])) {
            $html .= '<tr>';
            $html .= '<td style="padding: 8px; background-color: #f8f9fa; border: 1px solid #ddd;"><strong>סכום:</strong></td>';
            $html .= '<td style="padding: 8px; border: 1px solid #ddd;"><strong style="font-size: 12pt;">' . $this->formatPrice((float) $data['amount'], $data['currency'] ?? 'ILS') . '</strong></td>';
            $html .= '</tr>';
        }

        // Payment method.
        if (!empty($data['method'])) {
            $html .= '<tr>';
            $html .= '<td style="padding: 8px; background-color: #f8f9fa; border: 1px solid #ddd;"><strong>אמצעי:</strong></td>';
            $html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($data['method']) . '</td>';
            $html .= '</tr>';
        }

        // Reference.
        if (!empty($data['reference'])) {
            $html .= '<tr>';
            $html .= '<td style="padding: 8px; background-color: #f8f9fa; border: 1px solid #ddd;"><strong>אסמכתא:</strong></td>';
            $html .= '<td style="padding: 8px; border: 1px solid #ddd;"><bdi dir="ltr">' . esc_html($data['reference']) . '</bdi></td>';
            $html .= '</tr>';
        }

        // Authorization code.
        if (!empty($data['auth_code'])) {
            $html .= '<tr>';
            $html .= '<td style="padding: 8px; background-color: #f8f9fa; border: 1px solid #ddd;"><strong>קוד אישור:</strong></td>';
            $html .= '<td style="padding: 8px; border: 1px solid #ddd;"><bdi dir="ltr">' . esc_html($data['auth_code']) . '</bdi></td>';
            $html .= '</tr>';
        }

        // Timestamp.
        $timestamp = $data['timestamp'] ?? time();
        $html .= '<tr>';
        $html .= '<td style="padding: 8px; background-color: #f8f9fa; border: 1px solid #ddd;"><strong>תאריך ושעה:</strong></td>';
        $html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($this->formatDateTime($timestamp)) . '</td>';
        $html .= '</tr>';

        // Note.
        if (!empty($data['note'])) {
            $html .= '<tr>';
            $html .= '<td style="padding: 8px; background-color: #f8f9fa; border: 1px solid #ddd; vertical-align: top;"><strong>הערות:</strong></td>';
            $html .= '<td style="padding: 8px; border: 1px solid #ddd;">' . nl2br(esc_html($data['note'])) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render related order information.
     *
     * @param int $orderId Order ID.
     * @return string HTML.
     */
    private function renderRelatedOrder(int $orderId): string
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            return '';
        }

        $html = '<div style="margin-bottom: 20px; padding: 10px; background-color: #fffbf0; border-right: 4px solid #f0ad4e;">';
        $html .= '<h4 style="margin: 0 0 10px 0;">הזמנה קשורה</h4>';

        $html .= '<p style="margin: 3px 0;"><strong>מספר הזמנה:</strong> ' . esc_html($order->get_order_number()) . '</p>';
        $html .= '<p style="margin: 3px 0;"><strong>תאריך הזמנה:</strong> ' . esc_html($this->formatDate($order->get_date_created())) . '</p>';
        $html .= '<p style="margin: 3px 0;"><strong>סכום הזמנה:</strong> ' . $this->formatPrice($order->get_total(), $order->get_currency()) . '</p>';
        $html .= '<p style="margin: 3px 0;"><strong>סטטוס:</strong> ' . esc_html(wc_get_order_status_name($order->get_status())) . '</p>';

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
     * Get transaction type label in Hebrew.
     *
     * @param string $type Transaction type.
     * @return string Label.
     */
    private function getTransactionTypeLabel(string $type): string
    {
        $labels = [
            'payment' => 'תשלום',
            'refund' => 'החזר כספי',
            'credit' => 'זיכוי',
            'debit' => 'חיוב',
            'transfer' => 'העברה',
            'adjustment' => 'התאמה',
            'fee' => 'עמלה',
            'other' => 'אחר',
        ];

        return $labels[$type] ?? $type;
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
        $formatter = new IntlDateFormatter(
            'he_IL',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE
        );
        return $formatter->format($date->getTimestamp());
    }

    /**
     * Format datetime using Intl DateFormatter.
     *
     * @param int $timestamp Unix timestamp.
     * @return string Formatted datetime.
     */
    private function formatDateTime(int $timestamp): string
    {
        return $this->dateFormatter->format($timestamp);
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

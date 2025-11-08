<?php
/**
 * PDF Generator
 *
 * @package TPWC\HebrewPdf\PDF
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\PDF;

use TPWC\HebrewPdf\Cache\CacheManager;
use TPWC\HebrewPdf\Admin\Settings;
use TPWC\HebrewPdf\Logger;
use TPWC\HebrewPdf\Templates\OrderTemplate;
use TPWC\HebrewPdf\Templates\TransactionTemplate;
use Mpdf\Mpdf;

/**
 * Generates PDF documents using mPDF.
 */
class Generator
{
    /**
     * Cache manager instance.
     *
     * @var CacheManager
     */
    private CacheManager $cache;

    /**
     * Settings instance.
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * File controller instance.
     *
     * @var FileController
     */
    private FileController $fileController;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param CacheManager   $cache          Cache manager instance.
     * @param Settings       $settings       Settings instance.
     * @param FileController $fileController File controller instance.
     * @param Logger         $logger         Logger instance.
     */
    public function __construct(
        CacheManager $cache,
        Settings $settings,
        FileController $fileController,
        Logger $logger
    ) {
        $this->cache = $cache;
        $this->settings = $settings;
        $this->fileController = $fileController;
        $this->logger = $logger;
    }

    /**
     * Generate an order PDF.
     *
     * @param int  $orderId Order ID.
     * @param bool $force   Force regeneration (skip cache).
     * @return int|false Record ID on success, false on failure.
     */
    public function generateOrderPdf(int $orderId, bool $force = false)
    {
        try {
            // Compute hash.
            $hash = $this->cache->computeOrderHash($orderId);

            // Check cache.
            if (!$force) {
                $cached = $this->cache->get($hash);
                if ($cached) {
                    $this->logger->info("Using cached PDF for order {$orderId}");
                    return (int) $cached->id;
                }
            }

            // Get order.
            $order = wc_get_order($orderId);
            if (!$order) {
                throw new \InvalidArgumentException("Order {$orderId} not found");
            }

            // Generate PDF.
            $template = new OrderTemplate($this->settings, $this->logger);
            $html = $template->render($order);

            $filePath = $this->cache->generateFilePath('order', $orderId);
            $this->renderPdf($html, $filePath);

            // Store in cache.
            $metadata = [
                'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'amount' => $order->get_total(),
            ];

            $recordId = $this->cache->store($hash, $filePath, 'order', $orderId, $metadata);

            $this->logger->info("Generated order PDF for order {$orderId}");

            return $recordId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to generate order PDF for order {$orderId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a transaction PDF.
     *
     * @param int   $transactionId Transaction ID.
     * @param array $data          Transaction data.
     * @param bool  $force         Force regeneration (skip cache).
     * @return int|false Record ID on success, false on failure.
     */
    public function generateTransactionPdf(int $transactionId, array $data, bool $force = false)
    {
        try {
            // Compute hash.
            $hash = $this->cache->computeTransactionHash($transactionId, $data);

            // Check cache.
            if (!$force) {
                $cached = $this->cache->get($hash);
                if ($cached) {
                    $this->logger->info("Using cached PDF for transaction {$transactionId}");
                    return (int) $cached->id;
                }
            }

            // Generate PDF.
            $template = new TransactionTemplate($this->settings, $this->logger);
            $html = $template->render($transactionId, $data);

            $filePath = $this->cache->generateFilePath('transaction', $transactionId);
            $this->renderPdf($html, $filePath);

            // Store in cache.
            $metadata = [
                'customer_name' => $data['counterparty_name'] ?? '',
                'amount' => $data['amount'] ?? null,
            ];

            $recordId = $this->cache->store($hash, $filePath, 'transaction', $transactionId, $metadata);

            $this->logger->info("Generated transaction PDF for transaction {$transactionId}");

            return $recordId;
        } catch (\Exception $e) {
            $this->logger->error("Failed to generate transaction PDF for transaction {$transactionId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Render HTML to PDF using mPDF.
     *
     * @param string $html     HTML content.
     * @param string $filePath Output file path.
     * @return void
     * @throws \Exception If rendering fails.
     */
    private function renderPdf(string $html, string $filePath): void
    {
        try {
            // Verify Rubik fonts are available.
            $this->verifyFonts();

            // Get template settings.
            $margins = $this->settings->get('template_margins', [
                'top' => 15,
                'right' => 15,
                'bottom' => 15,
                'left' => 15,
            ]);

            // Configure mPDF.
            $config = [
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => $margins['left'],
                'margin_right' => $margins['right'],
                'margin_top' => $margins['top'],
                'margin_bottom' => $margins['bottom'],
                'margin_header' => 5,
                'margin_footer' => 5,
                'default_font_size' => 10,
                'default_font' => 'rubik',
                'tempDir' => sys_get_temp_dir(),
                'fontDir' => [TPWC_HEBREW_PDF_PATH . 'includes/Fonts'],
                'fontdata' => $this->getFontData(),
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'autoVietnamese' => false,
                'autoArabic' => false,
            ];

            $mpdf = new Mpdf($config);

            // Set RTL direction.
            $mpdf->SetDirectionality('rtl');

            // Set default styles.
            $mpdf->WriteHTML($this->getDefaultStyles(), 1);

            // Write HTML content.
            $mpdf->WriteHTML($html, 2);

            // Output to file.
            $mpdf->Output($filePath, 'F');

            $this->logger->debug("Rendered PDF: {$filePath}");
        } catch (\Mpdf\MpdfException $e) {
            // mPDF-specific errors (font loading, rendering, memory issues)
            $this->logger->error("mPDF rendering error: " . $e->getMessage());
            throw new \Exception("PDF rendering failed: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            // File write errors or other issues
            $this->logger->error("Failed to create PDF file: " . $e->getMessage());
            throw new \Exception("PDF creation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify that Rubik fonts are available.
     *
     * @return void
     * @throws \Exception If fonts are missing.
     */
    private function verifyFonts(): void
    {
        $fontDir = TPWC_HEBREW_PDF_PATH . 'includes/Fonts';
        $requiredFonts = [
            'Rubik-Regular.ttf',
            'Rubik-Medium.ttf',
            'Rubik-Bold.ttf',
        ];

        $missingFonts = [];

        foreach ($requiredFonts as $font) {
            if (!file_exists($fontDir . '/' . $font)) {
                $missingFonts[] = $font;
            }
        }

        if (!empty($missingFonts)) {
            throw new \Exception(
                sprintf(
                    'Required Rubik font files are missing: %s. Please see includes/Fonts/README.md for installation instructions.',
                    implode(', ', $missingFonts)
                )
            );
        }
    }

    /**
     * Get font data configuration for mPDF.
     *
     * @return array Font data configuration.
     */
    private function getFontData(): array
    {
        return [
            'rubik' => [
                'R' => 'Rubik-Regular.ttf',
                'B' => 'Rubik-Bold.ttf',
                'I' => 'Rubik-Medium.ttf',
                'BI' => 'Rubik-Bold.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ],
        ];
    }

    /**
     * Get default CSS styles for PDFs.
     *
     * @return string CSS styles.
     */
    private function getDefaultStyles(): string
    {
        return <<<'CSS'
body {
    font-family: rubik, sans-serif;
    direction: rtl;
    unicode-bidi: bidi-override;
}

* {
    font-family: rubik, sans-serif;
}

.ltr {
    direction: ltr;
    unicode-bidi: embed;
}

bdi {
    unicode-bidi: isolate;
}
CSS;
    }

    /**
     * Regenerate a PDF by record ID.
     *
     * @param int $recordId Record ID.
     * @return int|false New record ID on success, false on failure.
     */
    public function regeneratePdf(int $recordId)
    {
        // Access database through FileController which has public access
        $record = $this->fileController->database->get($recordId);

        if (!$record) {
            $this->logger->error("Cannot regenerate: record {$recordId} not found");
            return false;
        }

        if ($record->type === 'order') {
            return $this->generateOrderPdf($record->ref_id, true);
        }

        if ($record->type === 'transaction') {
            // For transactions, we need the original data.
            // We'll retrieve it from the meta field if available.
            $data = $record->meta ?? [];
            return $this->generateTransactionPdf($record->ref_id, $data, true);
        }

        return false;
    }
}

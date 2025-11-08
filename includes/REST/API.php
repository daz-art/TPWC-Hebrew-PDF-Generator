<?php
/**
 * REST API Handler
 *
 * @package TPWC\HebrewPdf\REST
 */

declare(strict_types=1);

namespace TPWC\HebrewPdf\REST;

use TPWC\HebrewPdf\PDF\Generator;
use TPWC\HebrewPdf\PDF\FileController;
use TPWC\HebrewPdf\Database\Database;
use TPWC\HebrewPdf\Logger;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API endpoints.
 */
class API extends WP_REST_Controller
{
    /**
     * Namespace.
     */
    private const NAMESPACE = 'tpwc/v1';

    /**
     * Generator instance.
     *
     * @var Generator
     */
    private Generator $generator;

    /**
     * Database instance.
     *
     * @var Database
     */
    private Database $database;

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
     * @param Generator      $generator      Generator instance.
     * @param Database       $database       Database instance.
     * @param FileController $fileController File controller instance.
     * @param Logger         $logger         Logger instance.
     */
    public function __construct(
        Generator $generator,
        Database $database,
        FileController $fileController,
        Logger $logger
    ) {
        $this->generator = $generator;
        $this->database = $database;
        $this->fileController = $fileController;
        $this->logger = $logger;

        $this->registerRoutes();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        // Generate order PDF.
        register_rest_route(self::NAMESPACE, '/generate/order/(?P<order_id>\d+)', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'generateOrderPdf'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => [
                'order_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => [$this, 'validateOrderId'],
                ],
                'force' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // Generate transaction PDF.
        register_rest_route(self::NAMESPACE, '/generate/transaction/(?P<transaction_id>\d+)', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'generateTransactionPdf'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => [
                'transaction_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'data' => [
                    'required' => true,
                    'type' => 'object',
                ],
                'force' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // Regenerate PDF.
        register_rest_route(self::NAMESPACE, '/regenerate/(?P<record_id>\d+)', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'regeneratePdf'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => [
                'record_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => [$this, 'validateRecordId'],
                ],
            ],
        ]);

        // Get PDF file.
        register_rest_route(self::NAMESPACE, '/file/(?P<record_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getFile'],
            'permission_callback' => [$this, 'checkFilePermissions'],
            'args' => [
                'record_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => [$this, 'validateRecordId'],
                ],
                'token' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
    }

    /**
     * Generate order PDF.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function generateOrderPdf(WP_REST_Request $request)
    {
        $orderId = (int) $request['order_id'];
        $force = (bool) $request->get_param('force');

        try {
            $recordId = $this->generator->generateOrderPdf($orderId, $force);

            if ($recordId === false) {
                return new WP_Error(
                    'generation_failed',
                    __('Failed to generate order PDF.', 'tpwc-hebrew-pdf'),
                    ['status' => 500]
                );
            }

            $record = $this->database->get($recordId);
            $downloadUrl = $this->fileController->generateSignedUrl($recordId);

            return new WP_REST_Response([
                'success' => true,
                'record_id' => $recordId,
                'download_url' => $downloadUrl,
                'cached' => $record->status === 'cached',
            ], 201);
        } catch (\Exception $e) {
            $this->logger->error('REST API error: ' . $e->getMessage());

            return new WP_Error(
                'generation_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Generate transaction PDF.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function generateTransactionPdf(WP_REST_Request $request)
    {
        $transactionId = (int) $request['transaction_id'];
        $data = $request->get_param('data');
        $force = (bool) $request->get_param('force');

        try {
            $recordId = $this->generator->generateTransactionPdf($transactionId, $data, $force);

            if ($recordId === false) {
                return new WP_Error(
                    'generation_failed',
                    __('Failed to generate transaction PDF.', 'tpwc-hebrew-pdf'),
                    ['status' => 500]
                );
            }

            $record = $this->database->get($recordId);
            $downloadUrl = $this->fileController->generateSignedUrl($recordId);

            return new WP_REST_Response([
                'success' => true,
                'record_id' => $recordId,
                'download_url' => $downloadUrl,
                'cached' => $record->status === 'cached',
            ], 201);
        } catch (\Exception $e) {
            $this->logger->error('REST API error: ' . $e->getMessage());

            return new WP_Error(
                'generation_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Regenerate PDF.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function regeneratePdf(WP_REST_Request $request)
    {
        $recordId = (int) $request['record_id'];

        try {
            $newRecordId = $this->generator->regeneratePdf($recordId);

            if ($newRecordId === false) {
                return new WP_Error(
                    'regeneration_failed',
                    __('Failed to regenerate PDF.', 'tpwc-hebrew-pdf'),
                    ['status' => 500]
                );
            }

            $downloadUrl = $this->fileController->generateSignedUrl($newRecordId);

            return new WP_REST_Response([
                'success' => true,
                'record_id' => $newRecordId,
                'download_url' => $downloadUrl,
            ], 200);
        } catch (\Exception $e) {
            $this->logger->error('REST API error: ' . $e->getMessage());

            return new WP_Error(
                'regeneration_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get file.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function getFile(WP_REST_Request $request)
    {
        $recordId = (int) $request['record_id'];
        $downloadUrl = $this->fileController->generateSignedUrl($recordId);

        return new WP_REST_Response([
            'success' => true,
            'download_url' => $downloadUrl,
        ], 200);
    }

    /**
     * Check permissions.
     *
     * @return bool|WP_Error True if user has permission, error otherwise.
     */
    public function checkPermissions()
    {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this resource.', 'tpwc-hebrew-pdf'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check file access permissions.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if user has permission, error otherwise.
     */
    public function checkFilePermissions(WP_REST_Request $request)
    {
        // Token-based access is handled via signed URLs.
        // For REST API, we still require manage_woocommerce capability.
        return $this->checkPermissions();
    }

    /**
     * Validate order ID.
     *
     * @param int             $orderId Order ID.
     * @param WP_REST_Request $request Request object.
     * @param string          $key     Parameter key.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    public function validateOrderId($orderId, $request, $key)
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            return new WP_Error(
                'invalid_order',
                __('Order not found.', 'tpwc-hebrew-pdf'),
                ['status' => 404]
            );
        }

        return true;
    }

    /**
     * Validate record ID.
     *
     * @param int             $recordId Record ID.
     * @param WP_REST_Request $request  Request object.
     * @param string          $key      Parameter key.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    public function validateRecordId($recordId, $request, $key)
    {
        $record = $this->database->get($recordId);

        if (!$record) {
            return new WP_Error(
                'invalid_record',
                __('PDF record not found.', 'tpwc-hebrew-pdf'),
                ['status' => 404]
            );
        }

        return true;
    }
}

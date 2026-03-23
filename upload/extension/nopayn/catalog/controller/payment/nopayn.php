<?php
namespace Opencart\Catalog\Controller\Extension\Nopayn\Payment;

class Nopayn extends \Opencart\System\Engine\Controller {

    private const API_BASE_URL = 'https://api.nopayn.co.uk';

    private const METHOD_MAP = [
        'nopayn_creditcard' => 'credit-card',
        'nopayn_applepay'   => 'apple-pay',
        'nopayn_googlepay'  => 'google-pay',
        'nopayn_mobilepay'  => 'vipps-mobilepay',
    ];

    private const LOCALE_MAP = [
        'en-gb' => 'en-GB',
        'de-de' => 'de-DE',
        'nl-nl' => 'nl-NL',
        'fr-fr' => 'fr-FR',
        'sv-se' => 'sv-SE',
        'no-no' => 'no-NO',
        'da-dk' => 'da-DK',
    ];

    public function index(): string {
        $this->load->language('extension/nopayn/payment/nopayn');

        $data['language'] = $this->config->get('config_language');
        $data['payment_method'] = $this->session->data['payment_method']['code'] ?? '';

        return $this->load->view('extension/nopayn/payment/nopayn', $data);
    }

    /**
     * Called when customer clicks "Confirm Order".
     * Creates a NoPayn order via API and returns a redirect URL to the HPP.
     */
    public function confirm(): void {
        $this->load->language('extension/nopayn/payment/nopayn');

        $json = [];

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
            $this->respondJson($json);
            return;
        }

        $paymentCode = $this->session->data['payment_method']['code'] ?? '';

        $codeParts = explode('.', $paymentCode);
        $methodKey = $codeParts[1] ?? '';

        if (!isset(self::METHOD_MAP[$methodKey])) {
            $json['error'] = $this->language->get('error_payment_method');
            $this->respondJson($json);
            return;
        }

        $apiKey = $this->config->get('payment_nopayn_api_key');

        if (!$apiKey) {
            $json['error'] = $this->language->get('error_api_key');
            $this->respondJson($json);
            return;
        }

        $orderId = (int)$this->session->data['order_id'];
        $nopaynMethod = self::METHOD_MAP[$methodKey];

        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($orderId);

        if (!$order) {
            $json['error'] = $this->language->get('error_order');
            $this->respondJson($json);
            return;
        }

        $currency = $order['currency_code'];
        $currencyValue = (float)$order['currency_value'];
        $amountInCurrency = (float)$order['total'] * ($currencyValue ?: 1.0);
        $amountCents = (int)round($amountInCurrency * 100);

        $pendingStatusId = (int)$this->config->get('payment_nopayn_pending_status_id');
        if ($pendingStatusId) {
            $this->model_checkout_order->addHistory($orderId, $pendingStatusId, 'NoPayn: Redirecting to payment page', false);
        }

        $shopUrl = $this->config->get('config_url');
        $lang = $this->config->get('config_language');

        $token = bin2hex(random_bytes(32));

        $this->session->data['nopayn_token'] = $token;
        $this->session->data['nopayn_order_id_shop'] = $orderId;

        $returnUrl = $shopUrl . 'index.php?route=extension/nopayn/payment/nopayn.callback'
            . '&language=' . $lang
            . '&token=' . $token
            . '&oid=' . $orderId;

        $failureUrl = $shopUrl . 'index.php?route=extension/nopayn/payment/nopayn.callback'
            . '&language=' . $lang
            . '&token=' . $token
            . '&oid=' . $orderId
            . '&status=failure';

        $webhookUrl = $shopUrl . 'index.php?route=extension/nopayn/payment/nopayn.webhook&language=' . $lang;

        // Build transaction entry with optional manual capture for credit card
        $transactionEntry = ['payment_method' => $nopaynMethod];

        $captureMode = 'auto';
        if ($methodKey === 'nopayn_creditcard' && $this->config->get('payment_nopayn_creditcard_manual_capture')) {
            $transactionEntry['capture_mode'] = 'manual';
            $captureMode = 'manual';
        }

        $params = [
            'currency'          => $currency,
            'amount'            => $amountCents,
            'description'       => 'Order #' . $orderId,
            'merchant_order_id' => (string)$orderId,
            'return_url'        => $returnUrl,
            'failure_url'       => $failureUrl,
            'webhook_url'       => $webhookUrl,
            'transactions'      => [$transactionEntry],
        ];

        // Build itemized order lines
        $orderLines = $this->buildOrderLines($orderId, $currency, $currencyValue);
        if ($orderLines) {
            $params['order_lines'] = $orderLines;
        }

        $locale = self::LOCALE_MAP[strtolower($lang)] ?? '';
        if ($locale) {
            $params['locale'] = $locale;
        }

        $this->log('confirm: Creating order for shop order #' . $orderId . ' method=' . $nopaynMethod . ' amount=' . $amountCents . ' currency=' . $currency . ' capture_mode=' . $captureMode);

        $response = $this->apiRequest('POST', '/v1/orders/', $apiKey, $params);

        if (isset($response['error'])) {
            $json['error'] = $this->language->get('error_gateway') . ' ' . ($response['error'] ?? '');
            $this->respondJson($json);
            return;
        }

        $nopaynOrderId = $response['id'] ?? '';

        $paymentUrl = '';
        $nopaynTransactionId = '';
        if (!empty($response['transactions'][0]['payment_url'])) {
            $paymentUrl = $response['transactions'][0]['payment_url'];
            $nopaynTransactionId = $response['transactions'][0]['id'] ?? '';
        } elseif (!empty($response['order_url'])) {
            $paymentUrl = $response['order_url'];
        }

        if (!$nopaynOrderId || !$paymentUrl) {
            $json['error'] = $this->language->get('error_gateway');
            $this->respondJson($json);
            return;
        }

        $this->load->model('extension/nopayn/payment/nopayn');
        $this->model_extension_nopayn_payment_nopayn->addTransaction($orderId, $nopaynOrderId, $nopaynMethod, $amountCents, $currency, $captureMode, $nopaynTransactionId);

        $this->log('confirm: Order created nopayn_order_id=' . $nopaynOrderId . ' transaction_id=' . $nopaynTransactionId . ' payment_url=' . $paymentUrl);

        $this->session->data['nopayn_order_id'] = $nopaynOrderId;

        $json['redirect'] = $paymentUrl;

        $this->respondJson($json);
    }

    /**
     * Customer returns here from the NoPayn HPP.
     * Verifies payment status via API and redirects to success or checkout.
     */
    public function callback(): void {
        $this->load->language('extension/nopayn/payment/nopayn');

        $token = $this->request->get['token'] ?? '';
        $orderId = (int)($this->request->get['oid'] ?? 0);
        $failureFlag = ($this->request->get['status'] ?? '') === 'failure';

        $sessionToken = $this->session->data['nopayn_token'] ?? '';
        $nopaynOrderId = $this->session->data['nopayn_order_id'] ?? '';

        if (!$token || !$orderId || !$sessionToken || !hash_equals($sessionToken, $token)) {
            $this->load->model('extension/nopayn/payment/nopayn');
            $transaction = $this->model_extension_nopayn_payment_nopayn->getTransactionByOrderId($orderId);
            $nopaynOrderId = $transaction['nopayn_order_id'] ?? '';
        }

        if ($failureFlag || !$nopaynOrderId) {
            $this->handleFailure($orderId, 'Payment was cancelled or failed.');
            return;
        }

        $apiKey = $this->config->get('payment_nopayn_api_key');
        $response = $this->apiRequest('GET', '/v1/orders/' . urlencode($nopaynOrderId) . '/', $apiKey);
        $status = $response['status'] ?? '';

        $this->log('callback: order_id=' . $orderId . ' nopayn_order_id=' . $nopaynOrderId . ' api_status=' . $status);

        $this->load->model('extension/nopayn/payment/nopayn');
        $this->load->model('checkout/order');

        // Store transaction ID from API response if available
        if (!empty($response['transactions'][0]['id'])) {
            $this->model_extension_nopayn_payment_nopayn->updateTransactionNopaynTransactionId($nopaynOrderId, $response['transactions'][0]['id']);
        }

        if ($status === 'completed') {
            $this->model_extension_nopayn_payment_nopayn->updateTransactionStatus($nopaynOrderId, 'completed');

            $completedStatusId = (int)$this->config->get('payment_nopayn_order_status_id');
            $this->model_checkout_order->addHistory($orderId, $completedStatusId, 'Payment completed (NoPayn)', false);

            $this->cleanupSession();
            $this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
        } elseif ($status === 'processing' || $status === 'new') {
            $pendingStatusId = (int)$this->config->get('payment_nopayn_pending_status_id');
            if ($pendingStatusId) {
                $this->model_checkout_order->addHistory($orderId, $pendingStatusId, 'Payment processing (NoPayn) — awaiting webhook confirmation', false);
            }

            $this->cleanupSession();
            $this->response->redirect($this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true));
        } else {
            $this->model_extension_nopayn_payment_nopayn->updateTransactionStatus($nopaynOrderId, $status);
            $this->handleFailure($orderId, 'Payment was not completed (status: ' . $status . ').');
        }
    }

    /**
     * Receives webhook POST from NoPayn servers.
     * Verifies status via API and updates order accordingly.
     */
    public function webhook(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);

        $this->log('webhook: Received payload: ' . $rawBody);

        if (!is_array($payload) || empty($payload['order_id'])) {
            $this->log('webhook: Invalid payload');
            http_response_code(200);
            echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
            exit;
        }

        $nopaynOrderId = $payload['order_id'];
        $apiKey = $this->config->get('payment_nopayn_api_key');

        if (!$apiKey) {
            $this->log('webhook: No API key configured');
            http_response_code(200);
            echo json_encode(['status' => 'error', 'message' => 'No API key']);
            exit;
        }

        $this->load->model('extension/nopayn/payment/nopayn');
        $transaction = $this->model_extension_nopayn_payment_nopayn->getTransactionByNopaynOrderId($nopaynOrderId);

        if (!$transaction) {
            $this->log('webhook: Unknown order ' . $nopaynOrderId);
            http_response_code(200);
            echo json_encode(['status' => 'error', 'message' => 'Unknown order']);
            exit;
        }

        $currentStatus = $transaction['status'];

        if (in_array($currentStatus, ['completed', 'cancelled', 'expired'], true)) {
            $this->log('webhook: Order ' . $nopaynOrderId . ' already in terminal status ' . $currentStatus . ', skipping');
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'updated' => false]);
            exit;
        }

        $response = $this->apiRequest('GET', '/v1/orders/' . urlencode($nopaynOrderId) . '/', $apiKey);
        $apiStatus = $response['status'] ?? '';

        $this->log('webhook: nopayn_order_id=' . $nopaynOrderId . ' api_status=' . $apiStatus . ' capture_mode=' . ($transaction['capture_mode'] ?? 'auto'));

        // Store transaction ID from API response if available
        if (!empty($response['transactions'][0]['id'])) {
            $this->model_extension_nopayn_payment_nopayn->updateTransactionNopaynTransactionId($nopaynOrderId, $response['transactions'][0]['id']);
            $transaction['nopayn_transaction_id'] = $response['transactions'][0]['id'];
        }

        $this->load->model('checkout/order');
        $shopOrderId = (int)$transaction['order_id'];
        $updated = false;

        switch ($apiStatus) {
            case 'completed':
                // If manual capture was used, attempt capture before marking completed
                if (($transaction['capture_mode'] ?? 'auto') === 'manual' && !empty($transaction['nopayn_transaction_id'])) {
                    $this->log('webhook: Manual capture mode — capturing transaction ' . $transaction['nopayn_transaction_id']);
                    $captureResult = $this->captureTransaction($nopaynOrderId, $transaction['nopayn_transaction_id']);
                    if (isset($captureResult['error'])) {
                        $this->log('webhook: Capture failed: ' . ($captureResult['error'] ?? 'unknown'));
                    } else {
                        $this->log('webhook: Capture successful');
                    }
                }

                $statusId = (int)$this->config->get('payment_nopayn_order_status_id');
                $this->model_checkout_order->addHistory($shopOrderId, $statusId, 'Payment completed (NoPayn webhook)', false);
                $this->model_extension_nopayn_payment_nopayn->updateTransactionStatus($nopaynOrderId, 'completed');
                $updated = true;
                break;

            case 'cancelled':
            case 'expired':
            case 'error':
                // If manual capture and authorized but not yet captured, void the transaction
                if (($transaction['capture_mode'] ?? 'auto') === 'manual' && !empty($transaction['nopayn_transaction_id'])) {
                    $this->log('webhook: Manual capture mode — voiding transaction ' . $transaction['nopayn_transaction_id']);
                    $voidResult = $this->voidTransaction($nopaynOrderId, $transaction['nopayn_transaction_id'], (int)$transaction['amount'], 'Order ' . $apiStatus . ' via webhook');
                    if (isset($voidResult['error'])) {
                        $this->log('webhook: Void failed: ' . ($voidResult['error'] ?? 'unknown'));
                    } else {
                        $this->log('webhook: Void successful');
                    }
                }

                $statusId = (int)$this->config->get('payment_nopayn_cancelled_status_id');
                if ($statusId) {
                    $this->model_checkout_order->addHistory($shopOrderId, $statusId, 'Payment ' . $apiStatus . ' (NoPayn webhook)', false);
                }
                $this->model_extension_nopayn_payment_nopayn->updateTransactionStatus($nopaynOrderId, $apiStatus);
                $updated = true;
                break;
        }

        $this->log('webhook: Processing complete for ' . $nopaynOrderId . ' updated=' . ($updated ? 'true' : 'false'));

        http_response_code(200);
        echo json_encode(['status' => 'ok', 'updated' => $updated]);
        exit;
    }

    // -------------------------------------------------------------------------
    //  Order line builder
    // -------------------------------------------------------------------------

    /**
     * Builds itemized order lines from order products and shipping.
     */
    private function buildOrderLines(int $orderId, string $currencyCode, float $currencyValue): array {
        $this->load->model('checkout/order');
        $orderProducts = $this->model_checkout_order->getProducts($orderId);
        $orderTotals = $this->model_checkout_order->getTotals($orderId);

        $orderLines = [];

        foreach ($orderProducts as $product) {
            $price = (float)$product['price'] * ($currencyValue ?: 1.0);
            $priceInCents = (int)round($price * 100);

            $vatPercentage = 0;
            if ($price > 0 && isset($product['tax']) && (float)$product['tax'] > 0) {
                $vatPercentage = (int)round((float)$product['tax'] / $price * 10000);
            }

            $orderLines[] = [
                'type'                    => 'physical',
                'name'                    => $product['name'],
                'quantity'                => (int)$product['quantity'],
                'amount'                  => $priceInCents,
                'currency'                => $currencyCode,
                'vat_percentage'          => $vatPercentage,
                'merchant_order_line_id'  => (string)$product['product_id'],
            ];
        }

        // Add shipping line from order totals
        foreach ($orderTotals as $total) {
            if ($total['code'] === 'shipping' && (float)$total['value'] > 0) {
                $shippingValue = (float)$total['value'] * ($currencyValue ?: 1.0);
                $shippingInCents = (int)round($shippingValue * 100);

                $orderLines[] = [
                    'type'                    => 'shipping_fee',
                    'name'                    => $total['title'],
                    'quantity'                => 1,
                    'amount'                  => $shippingInCents,
                    'currency'                => $currencyCode,
                    'vat_percentage'          => 0,
                    'merchant_order_line_id'  => 'shipping',
                ];
                break;
            }
        }

        return $orderLines;
    }

    // -------------------------------------------------------------------------
    //  Inline API methods
    // -------------------------------------------------------------------------

    private function apiRequest(string $method, string $endpoint, string $apiKey, ?array $body = null): array {
        $url = self::API_BASE_URL . $endpoint;

        $this->log('apiRequest: ' . $method . ' ' . $url . ($body !== null ? ' body=' . json_encode($body) : ''));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERPWD        => $apiKey . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->log('apiRequest: cURL error: ' . $curlError);
            return ['error' => 'cURL error: ' . $curlError];
        }

        $this->log('apiRequest: HTTP ' . $httpCode . ' response=' . $response);

        $decoded = json_decode($response, true);

        if ($decoded === null && $response !== '') {
            return ['error' => 'Invalid JSON response'];
        }

        if ($httpCode >= 400) {
            $msg = $decoded['error']['value'] ?? $decoded['error']['message'] ?? 'HTTP ' . $httpCode;
            return ['error' => $msg];
        }

        return $decoded ?? [];
    }

    /**
     * Capture an authorized transaction.
     * POST /v1/orders/{orderId}/transactions/{transactionId}/captures/
     */
    private function captureTransaction(string $orderId, string $transactionId): array|false {
        $apiKey = $this->config->get('payment_nopayn_api_key');

        if (!$apiKey) {
            return false;
        }

        $endpoint = '/v1/orders/' . urlencode($orderId) . '/transactions/' . urlencode($transactionId) . '/captures/';

        $this->log('captureTransaction: order_id=' . $orderId . ' transaction_id=' . $transactionId);

        return $this->apiRequest('POST', $endpoint, $apiKey);
    }

    /**
     * Void (release) an authorized transaction amount.
     * POST /v1/orders/{orderId}/transactions/{transactionId}/voids/amount/
     */
    private function voidTransaction(string $orderId, string $transactionId, int $amountInCents, string $description = ''): array|false {
        $apiKey = $this->config->get('payment_nopayn_api_key');

        if (!$apiKey) {
            return false;
        }

        $endpoint = '/v1/orders/' . urlencode($orderId) . '/transactions/' . urlencode($transactionId) . '/voids/amount/';

        $body = ['amount' => $amountInCents];
        if ($description !== '') {
            $body['description'] = $description;
        }

        $this->log('voidTransaction: order_id=' . $orderId . ' transaction_id=' . $transactionId . ' amount=' . $amountInCents);

        return $this->apiRequest('POST', $endpoint, $apiKey, $body);
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    private function handleFailure(int $orderId, string $message): void {
        $cancelledStatusId = (int)$this->config->get('payment_nopayn_cancelled_status_id');
        if ($cancelledStatusId && $orderId) {
            $this->load->model('checkout/order');
            $this->model_checkout_order->addHistory($orderId, $cancelledStatusId, $message, false);
        }

        $this->cleanupSession();
        $this->response->redirect($this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true));
    }

    private function cleanupSession(): void {
        unset(
            $this->session->data['nopayn_token'],
            $this->session->data['nopayn_order_id'],
            $this->session->data['nopayn_order_id_shop']
        );
    }

    private function respondJson(array $json): void {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Write a log entry to the NoPayn log file if debug logging is enabled.
     */
    private function log(string $message): void {
        if (!$this->config->get('payment_nopayn_debug_logging')) {
            return;
        }

        $logFile = DIR_LOGS . 'nopayn.log';
        $timestamp = date('Y-m-d H:i:s');
        $entry = '[' . $timestamp . '] NoPayn_ ' . $message . PHP_EOL;

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}

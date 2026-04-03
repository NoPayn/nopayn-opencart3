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

        $params = [
            'currency'          => $currency,
            'amount'            => $amountCents,
            'description'       => 'Order #' . $orderId,
            'merchant_order_id' => (string)$orderId,
            'return_url'        => $returnUrl,
            'failure_url'       => $failureUrl,
            'webhook_url'       => $webhookUrl,
            'transactions'      => [
                ['payment_method' => $nopaynMethod],
            ],
        ];

        $locale = self::LOCALE_MAP[strtolower($lang)] ?? '';
        if ($locale) {
            $params['locale'] = $locale;
        }

        $response = $this->apiRequest('POST', '/v1/orders/', $apiKey, $params);

        if (isset($response['error'])) {
            $json['error'] = $this->language->get('error_gateway') . ' ' . ($response['error'] ?? '');
            $this->respondJson($json);
            return;
        }

        $nopaynOrderId = $response['id'] ?? '';

        $paymentUrl = '';
        if (!empty($response['transactions'][0]['payment_url'])) {
            $paymentUrl = $response['transactions'][0]['payment_url'];
        } elseif (!empty($response['order_url'])) {
            $paymentUrl = $response['order_url'];
        }

        if (!$nopaynOrderId || !$paymentUrl) {
            $json['error'] = $this->language->get('error_gateway');
            $this->respondJson($json);
            return;
        }

        $this->load->model('extension/nopayn/payment/nopayn');
        $this->model_extension_nopayn_payment_nopayn->addTransaction($orderId, $nopaynOrderId, $nopaynMethod, $amountCents, $currency);

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

        $this->load->model('extension/nopayn/payment/nopayn');
        $this->load->model('checkout/order');

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

        if (!is_array($payload) || empty($payload['order_id'])) {
            http_response_code(200);
            echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
            exit;
        }

        $nopaynOrderId = $payload['order_id'];
        $apiKey = $this->config->get('payment_nopayn_api_key');

        if (!$apiKey) {
            http_response_code(200);
            echo json_encode(['status' => 'error', 'message' => 'No API key']);
            exit;
        }

        $this->load->model('extension/nopayn/payment/nopayn');
        $transaction = $this->model_extension_nopayn_payment_nopayn->getTransactionByNopaynOrderId($nopaynOrderId);

        if (!$transaction) {
            http_response_code(200);
            echo json_encode(['status' => 'error', 'message' => 'Unknown order']);
            exit;
        }

        $currentStatus = $transaction['status'];

        if (in_array($currentStatus, ['completed', 'cancelled', 'expired'], true)) {
            http_response_code(200);
            echo json_encode(['status' => 'ok', 'updated' => false]);
            exit;
        }

        $response = $this->apiRequest('GET', '/v1/orders/' . urlencode($nopaynOrderId) . '/', $apiKey);
        $apiStatus = $response['status'] ?? '';

        $this->load->model('checkout/order');
        $shopOrderId = (int)$transaction['order_id'];
        $updated = false;

        switch ($apiStatus) {
            case 'completed':
                $statusId = (int)$this->config->get('payment_nopayn_order_status_id');
                $this->model_checkout_order->addHistory($shopOrderId, $statusId, 'Payment completed (NoPayn webhook)', false);
                $this->model_extension_nopayn_payment_nopayn->updateTransactionStatus($nopaynOrderId, 'completed');
                $updated = true;
                break;

            case 'cancelled':
            case 'expired':
            case 'error':
                $statusId = (int)$this->config->get('payment_nopayn_cancelled_status_id');
                if ($statusId) {
                    $this->model_checkout_order->addHistory($shopOrderId, $statusId, 'Payment ' . $apiStatus . ' (NoPayn webhook)', false);
                }
                $this->model_extension_nopayn_payment_nopayn->updateTransactionStatus($nopaynOrderId, $apiStatus);
                $updated = true;
                break;
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok', 'updated' => $updated]);
        exit;
    }

    // -------------------------------------------------------------------------

    private function apiRequest(string $method, string $endpoint, string $apiKey, ?array $body = null): array {
        $url = self::API_BASE_URL . $endpoint;

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
            return ['error' => 'cURL error: ' . $curlError];
        }

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
}

<?php
namespace Opencart\Admin\Controller\Extension\Nopayn\Payment;

class Nopayn extends \Opencart\System\Engine\Controller {

    private const API_BASE_URL = 'https://api.nopayn.co.uk';

    public function index(): void {
        $this->load->language('extension/nopayn/payment/nopayn');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment'),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/nopayn/payment/nopayn', 'user_token=' . $this->session->data['user_token']),
        ];

        $data['save'] = $this->url->link('extension/nopayn/payment/nopayn.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        $data['payment_nopayn_api_key'] = $this->config->get('payment_nopayn_api_key');
        $data['payment_nopayn_order_status_id'] = $this->config->get('payment_nopayn_order_status_id');
        $data['payment_nopayn_pending_status_id'] = $this->config->get('payment_nopayn_pending_status_id');
        $data['payment_nopayn_cancelled_status_id'] = $this->config->get('payment_nopayn_cancelled_status_id');
        $data['payment_nopayn_geo_zone_id'] = $this->config->get('payment_nopayn_geo_zone_id');
        $data['payment_nopayn_status'] = $this->config->get('payment_nopayn_status');
        $data['payment_nopayn_sort_order'] = $this->config->get('payment_nopayn_sort_order');

        $data['payment_nopayn_creditcard'] = $this->config->get('payment_nopayn_creditcard');
        $data['payment_nopayn_applepay'] = $this->config->get('payment_nopayn_applepay');
        $data['payment_nopayn_googlepay'] = $this->config->get('payment_nopayn_googlepay');
        $data['payment_nopayn_mobilepay'] = $this->config->get('payment_nopayn_mobilepay');

        $data['payment_nopayn_creditcard_manual_capture'] = $this->config->get('payment_nopayn_creditcard_manual_capture');
        $data['payment_nopayn_debug_logging'] = $this->config->get('payment_nopayn_debug_logging');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/nopayn/payment/nopayn', $data));
    }

    public function save(): void {
        $this->load->language('extension/nopayn/payment/nopayn');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/nopayn/payment/nopayn')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_nopayn', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void {
        $this->load->model('extension/nopayn/payment/nopayn');
        $this->model_extension_nopayn_payment_nopayn->install();
    }

    public function uninstall(): void {
        $this->load->model('extension/nopayn/payment/nopayn');
        $this->model_extension_nopayn_payment_nopayn->uninstall();
    }

    // -------------------------------------------------------------------------
    //  Inline API methods (for admin-side capture/void/refund operations)
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

        return $this->apiRequest('POST', $endpoint, $apiKey, $body);
    }
}

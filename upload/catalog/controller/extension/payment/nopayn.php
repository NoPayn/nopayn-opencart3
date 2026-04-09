<?php
class ControllerExtensionPaymentNopayn extends Controller {
	private const API_BASE_URL = 'https://api.nopayn.co.uk';
	private const DEFAULT_EXPIRATION_MINUTES = 5;
	private const ALLOWED_MODULES = array(
		'nopayn_card',
		'nopayn_applepay',
		'nopayn_googlepay',
		'nopayn_vippsmobilepay',
		'nopayn_swishpay'
	);

	private const LOCALE_MAP = array(
		'en-gb' => 'en-GB',
		'de-de' => 'de-DE',
		'nl-nl' => 'nl-NL',
		'fr-fr' => 'fr-FR',
		'sv-se' => 'sv-SE',
		'no-no' => 'no-NO',
		'da-dk' => 'da-DK'
	);

	public function index() {
		return '';
	}

	public function confirm() {
		$json = array();
		$module_code = $this->resolveModuleCode();

		$this->loadModuleLanguage($module_code);

		if (!isset($this->session->data['order_id'])) {
			$json['error'] = $this->language->get('error_order');
			$this->respondJson($json);
			return;
		}

		$this->load->model('extension/payment/nopayn');

		$requested_payment_methods = $this->model_extension_payment_nopayn->getRequestedPaymentMethods($module_code);
		$transaction_entries = $this->model_extension_payment_nopayn->getTransactionEntries($module_code);
		$requested_payment_method = isset($requested_payment_methods[0]) ? $requested_payment_methods[0] : '';

		if (!$module_code || !$requested_payment_method || !$transaction_entries) {
			$json['error'] = $this->language->get('error_payment_method');
			$this->respondJson($json);
			return;
		}

		$api_key = $this->config->get('payment_nopayn_api_key');

		if (!$api_key) {
			$json['error'] = $this->language->get('error_api_key');
			$this->respondJson($json);
			return;
		}

		$order_id = (int)$this->session->data['order_id'];

		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			$json['error'] = $this->language->get('error_order');
			$this->respondJson($json);
			return;
		}

		$currency = $order_info['currency_code'];
		$currency_value = (float)$order_info['currency_value'];
		$amount_in_currency = (float)$order_info['total'] * ($currency_value ? $currency_value : 1.0);
		$amount_cents = (int)round($amount_in_currency * 100);

		$pending_status_id = (int)$this->config->get('payment_nopayn_pending_status_id');

		if ($pending_status_id) {
			$this->model_checkout_order->addOrderHistory($order_id, $pending_status_id, 'NoPayn: Redirecting to payment page', false);
		}

		$token = $this->generateToken();

		$this->session->data['nopayn_token'] = $token;
		$this->session->data['nopayn_order_id_shop'] = $order_id;
		$this->session->data['nopayn_module_code'] = $module_code;

		$return_url = str_replace('&amp;', '&', $this->url->link('extension/payment/nopayn/callback', 'module=' . $module_code . '&token=' . $token . '&oid=' . $order_id, true));
		$failure_url = str_replace('&amp;', '&', $this->url->link('extension/payment/nopayn/callback', 'module=' . $module_code . '&token=' . $token . '&oid=' . $order_id . '&status=failure', true));
		$webhook_url = str_replace('&amp;', '&', $this->url->link('extension/payment/nopayn/webhook', '', true));

		$capture_mode = 'auto';

		if ($this->model_extension_payment_nopayn->isManualCapture($module_code)) {
			$capture_mode = 'manual';
		}

		$params = array(
			'currency' => $currency,
			'amount' => $amount_cents,
			'description' => 'Order #' . $order_id,
			'merchant_order_id' => (string)$order_id,
			'return_url' => $return_url,
			'failure_url' => $failure_url,
			'webhook_url' => $webhook_url,
			'transactions' => $transaction_entries
		);

		$expiration_minutes = $this->getExpirationMinutes();

		if ($expiration_minutes > 0) {
			$params['expiration_period'] = 'PT' . $expiration_minutes . 'M';
		}

		$order_lines = $this->buildOrderLines($order_id, $currency, $currency_value);

		if ($order_lines) {
			$params['order_lines'] = $order_lines;
		}

		$language_code = strtolower($this->config->get('config_language'));

		if (isset(self::LOCALE_MAP[$language_code])) {
			$params['locale'] = self::LOCALE_MAP[$language_code];
		}

		$this->log('confirm: Creating order for shop order #' . $order_id . ' module=' . $module_code . ' method=' . $requested_payment_method . ' amount=' . $amount_cents . ' currency=' . $currency . ' capture_mode=' . $capture_mode . ' expiration_minutes=' . $expiration_minutes);

		$response = $this->apiRequest('POST', '/v1/orders/', $api_key, $params);

		if (isset($response['error'])) {
			$json['error'] = $this->language->get('error_gateway') . ' ' . $response['error'];
			$this->respondJson($json);
			return;
		}

		$nopayn_order_id = isset($response['id']) ? $response['id'] : '';
		$payment_url = $this->resolvePaymentUrl($response);
		$nopayn_transaction_id = $this->extractTransactionValue($response, 'id');

		if (!$nopayn_order_id || !$payment_url) {
			$json['error'] = $this->language->get('error_gateway');
			$this->respondJson($json);
			return;
		}

		$this->model_extension_payment_nopayn->addTransaction(
			$order_id,
			$nopayn_order_id,
			$requested_payment_method,
			$amount_cents,
			$currency,
			$capture_mode,
			$nopayn_transaction_id,
			$module_code
		);

		$this->session->data['nopayn_order_id'] = $nopayn_order_id;

		$this->log('confirm: Order created nopayn_order_id=' . $nopayn_order_id . ' transaction_id=' . $nopayn_transaction_id . ' payment_url=' . $payment_url);

		$json['redirect'] = $payment_url;

		$this->respondJson($json);
	}

	public function callback() {
		$this->load->model('extension/payment/nopayn');
		$this->load->model('checkout/order');

		$module_code = $this->resolveModuleCode();
		$this->loadModuleLanguage($module_code);

		$token = isset($this->request->get['token']) ? $this->request->get['token'] : '';
		$order_id = isset($this->request->get['oid']) ? (int)$this->request->get['oid'] : 0;
		$failure_flag = isset($this->request->get['status']) && $this->request->get['status'] === 'failure';

		$session_token = isset($this->session->data['nopayn_token']) ? $this->session->data['nopayn_token'] : '';
		$nopayn_order_id = isset($this->session->data['nopayn_order_id']) ? $this->session->data['nopayn_order_id'] : '';

		if (!$token || !$order_id || !$session_token || !hash_equals($session_token, $token)) {
			$transaction = $this->model_extension_payment_nopayn->getTransactionByOrderId($order_id);
			$nopayn_order_id = !empty($transaction['nopayn_order_id']) ? $transaction['nopayn_order_id'] : '';
			$module_code = !empty($transaction['extension_code']) ? $transaction['extension_code'] : $module_code;
			$this->loadModuleLanguage($module_code);
		}

		if ($failure_flag || !$nopayn_order_id) {
			$this->handleFailure($order_id, 'Payment was cancelled or failed.');
			return;
		}

		$api_key = $this->config->get('payment_nopayn_api_key');

		if (!$api_key) {
			$this->handleFailure($order_id, 'NoPayn API key is not configured.');
			return;
		}

		$response = $this->apiRequest('GET', '/v1/orders/' . urlencode($nopayn_order_id) . '/', $api_key);

		if (isset($response['error'])) {
			$this->handleFailure($order_id, 'NoPayn status lookup failed: ' . $response['error']);
			return;
		}

		$status = isset($response['status']) ? $response['status'] : '';

		$this->log('callback: order_id=' . $order_id . ' nopayn_order_id=' . $nopayn_order_id . ' api_status=' . $status);

		$transaction_id = $this->extractTransactionValue($response, 'id');
		$payment_method = $this->extractTransactionValue($response, 'payment_method');

		if ($transaction_id) {
			$this->model_extension_payment_nopayn->updateTransactionNopaynTransactionId($nopayn_order_id, $transaction_id);
		}

		if ($payment_method) {
			$this->model_extension_payment_nopayn->updateTransactionPaymentMethod($nopayn_order_id, $payment_method);
		}

		if ($status === 'completed') {
			$this->model_extension_payment_nopayn->updateTransactionStatus($nopayn_order_id, 'completed');

			$completed_status_id = (int)$this->config->get('payment_nopayn_order_status_id');
			if ($completed_status_id) {
				$this->model_checkout_order->addOrderHistory($order_id, $completed_status_id, 'Payment completed (NoPayn)', false);
			}

			$this->cleanupSession();
			$this->response->redirect($this->url->link('checkout/success', '', true));
		} elseif ($status === 'processing' || $status === 'new') {
			$pending_status_id = (int)$this->config->get('payment_nopayn_pending_status_id');
			if ($pending_status_id) {
				$this->model_checkout_order->addOrderHistory($order_id, $pending_status_id, 'Payment processing (NoPayn) - awaiting webhook confirmation', false);
			}

			$this->cleanupSession();
			$this->response->redirect($this->url->link('checkout/success', '', true));
		} else {
			$this->model_extension_payment_nopayn->updateTransactionStatus($nopayn_order_id, $status);
			$this->handleFailure($order_id, 'Payment was not completed (status: ' . $status . ').');
		}
	}

	public function webhook() {
		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$this->respondJson(array('status' => 'error', 'message' => 'Method Not Allowed'), 405);
			return;
		}

		$raw_body = file_get_contents('php://input');
		$payload = json_decode($raw_body, true);

		$this->log('webhook: Received payload: ' . $raw_body);

		if (!is_array($payload) || empty($payload['order_id'])) {
			$this->log('webhook: Invalid payload');
			$this->respondJson(array('status' => 'error', 'message' => 'Invalid payload'));
			return;
		}

		$api_key = $this->config->get('payment_nopayn_api_key');

		if (!$api_key) {
			$this->log('webhook: No API key configured');
			$this->respondJson(array('status' => 'error', 'message' => 'No API key'));
			return;
		}

		$nopayn_order_id = $payload['order_id'];

		$this->load->model('extension/payment/nopayn');
		$transaction = $this->model_extension_payment_nopayn->getTransactionByNopaynOrderId($nopayn_order_id);

		if (!$transaction) {
			$this->log('webhook: Unknown order ' . $nopayn_order_id);
			$this->respondJson(array('status' => 'error', 'message' => 'Unknown order'));
			return;
		}

		$current_status = $transaction['status'];

		if (in_array($current_status, array('completed', 'cancelled', 'expired'), true)) {
			$this->log('webhook: Order ' . $nopayn_order_id . ' already in terminal status ' . $current_status . ', skipping');
			$this->respondJson(array('status' => 'ok', 'updated' => false));
			return;
		}

		$response = $this->apiRequest('GET', '/v1/orders/' . urlencode($nopayn_order_id) . '/', $api_key);

		if (isset($response['error'])) {
			$this->log('webhook: Status lookup failed: ' . $response['error']);
			$this->respondJson(array('status' => 'error', 'message' => $response['error']));
			return;
		}

		$api_status = isset($response['status']) ? $response['status'] : '';

		$this->log('webhook: nopayn_order_id=' . $nopayn_order_id . ' api_status=' . $api_status . ' capture_mode=' . (isset($transaction['capture_mode']) ? $transaction['capture_mode'] : 'auto'));

		$transaction_id = $this->extractTransactionValue($response, 'id');
		$payment_method = $this->extractTransactionValue($response, 'payment_method');

		if ($transaction_id) {
			$this->model_extension_payment_nopayn->updateTransactionNopaynTransactionId($nopayn_order_id, $transaction_id);
			$transaction['nopayn_transaction_id'] = $transaction_id;
		}

		if ($payment_method) {
			$this->model_extension_payment_nopayn->updateTransactionPaymentMethod($nopayn_order_id, $payment_method);
			$transaction['payment_method'] = $payment_method;
		}

		$this->load->model('checkout/order');
		$shop_order_id = (int)$transaction['order_id'];
		$updated = false;

		switch ($api_status) {
			case 'completed':
				if ((isset($transaction['capture_mode']) ? $transaction['capture_mode'] : 'auto') === 'manual' && !empty($transaction['nopayn_transaction_id'])) {
					$this->log('webhook: Manual capture mode - capturing transaction ' . $transaction['nopayn_transaction_id']);
					$capture_result = $this->captureTransaction($nopayn_order_id, $transaction['nopayn_transaction_id']);

					if (isset($capture_result['error'])) {
						$this->log('webhook: Capture failed: ' . $capture_result['error']);
					} else {
						$this->log('webhook: Capture successful');
					}
				}

				$status_id = (int)$this->config->get('payment_nopayn_order_status_id');
				if ($status_id) {
					$this->model_checkout_order->addOrderHistory($shop_order_id, $status_id, 'Payment completed (NoPayn webhook)', false);
				}
				$this->model_extension_payment_nopayn->updateTransactionStatus($nopayn_order_id, 'completed');
				$updated = true;
				break;

			case 'cancelled':
			case 'expired':
			case 'error':
				if ((isset($transaction['capture_mode']) ? $transaction['capture_mode'] : 'auto') === 'manual' && !empty($transaction['nopayn_transaction_id'])) {
					$this->log('webhook: Manual capture mode - voiding transaction ' . $transaction['nopayn_transaction_id']);
					$void_result = $this->voidTransaction($nopayn_order_id, $transaction['nopayn_transaction_id'], (int)$transaction['amount'], 'Order ' . $api_status . ' via webhook');

					if (isset($void_result['error'])) {
						$this->log('webhook: Void failed: ' . $void_result['error']);
					} else {
						$this->log('webhook: Void successful');
					}
				}

				$status_id = (int)$this->config->get('payment_nopayn_cancelled_status_id');
				if ($status_id) {
					$this->model_checkout_order->addOrderHistory($shop_order_id, $status_id, 'Payment ' . $api_status . ' (NoPayn webhook)', false);
				}
				$this->model_extension_payment_nopayn->updateTransactionStatus($nopayn_order_id, $api_status);
				$updated = true;
				break;
		}

		$this->log('webhook: Processing complete for ' . $nopayn_order_id . ' updated=' . ($updated ? 'true' : 'false'));

		$this->respondJson(array('status' => 'ok', 'updated' => $updated));
	}

	private function buildOrderLines($order_id, $currency_code, $currency_value) {
		$this->load->model('checkout/order');

		$order_products = $this->model_checkout_order->getOrderProducts($order_id);
		$order_totals = $this->model_checkout_order->getOrderTotals($order_id);
		$order_lines = array();

		foreach ($order_products as $product) {
			$price = (float)$product['price'] * ($currency_value ? $currency_value : 1.0);
			$price_in_cents = (int)round($price * 100);

			$vat_percentage = 0;
			if ($price > 0 && isset($product['tax']) && (float)$product['tax'] > 0) {
				$vat_percentage = (int)round((float)$product['tax'] / $price * 10000);
			}

			$order_lines[] = array(
				'type' => 'physical',
				'name' => $product['name'],
				'quantity' => (int)$product['quantity'],
				'amount' => $price_in_cents,
				'currency' => $currency_code,
				'vat_percentage' => $vat_percentage,
				'merchant_order_line_id' => (string)$product['product_id']
			);
		}

		foreach ($order_totals as $total) {
			if ($total['code'] === 'shipping' && (float)$total['value'] > 0) {
				$shipping_value = (float)$total['value'] * ($currency_value ? $currency_value : 1.0);
				$shipping_in_cents = (int)round($shipping_value * 100);

				$order_lines[] = array(
					'type' => 'shipping_fee',
					'name' => $total['title'],
					'quantity' => 1,
					'amount' => $shipping_in_cents,
					'currency' => $currency_code,
					'vat_percentage' => 0,
					'merchant_order_line_id' => 'shipping'
				);

				break;
			}
		}

		return $order_lines;
	}

	private function apiRequest($method, $endpoint, $api_key, $body = null) {
		$url = self::API_BASE_URL . $endpoint;

		$this->log('apiRequest: ' . $method . ' ' . $url . ($body !== null ? ' body=' . json_encode($body) : ''));

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_USERPWD => $api_key . ':',
			CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Accept: application/json'),
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2
		));

		if ($method === 'POST') {
			curl_setopt($curl, CURLOPT_POST, true);

			if ($body !== null) {
				curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
			}
		}

		$response = curl_exec($curl);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($curl);

		curl_close($curl);

		if ($response === false) {
			$this->log('apiRequest: cURL error: ' . $curl_error);
			return array('error' => 'cURL error: ' . $curl_error);
		}

		$this->log('apiRequest: HTTP ' . $http_code . ' response=' . $response);

		$decoded = json_decode($response, true);

		if ($decoded === null && $response !== '') {
			return array('error' => 'Invalid JSON response');
		}

		if ($http_code >= 400) {
			$message = 'HTTP ' . $http_code;

			if (!empty($decoded['error']['value'])) {
				$message = $decoded['error']['value'];
			} elseif (!empty($decoded['error']['message'])) {
				$message = $decoded['error']['message'];
			}

			return array('error' => $message);
		}

		return $decoded ? $decoded : array();
	}

	private function captureTransaction($order_id, $transaction_id) {
		$api_key = $this->config->get('payment_nopayn_api_key');

		if (!$api_key) {
			return false;
		}

		$endpoint = '/v1/orders/' . urlencode($order_id) . '/transactions/' . urlencode($transaction_id) . '/captures/';

		$this->log('captureTransaction: order_id=' . $order_id . ' transaction_id=' . $transaction_id);

		return $this->apiRequest('POST', $endpoint, $api_key);
	}

	private function voidTransaction($order_id, $transaction_id, $amount_in_cents, $description = '') {
		$api_key = $this->config->get('payment_nopayn_api_key');

		if (!$api_key) {
			return false;
		}

		$endpoint = '/v1/orders/' . urlencode($order_id) . '/transactions/' . urlencode($transaction_id) . '/voids/amount/';
		$body = array('amount' => $amount_in_cents);

		if ($description !== '') {
			$body['description'] = $description;
		}

		$this->log('voidTransaction: order_id=' . $order_id . ' transaction_id=' . $transaction_id . ' amount=' . $amount_in_cents);

		return $this->apiRequest('POST', $endpoint, $api_key, $body);
	}

	private function handleFailure($order_id, $message) {
		$cancelled_status_id = (int)$this->config->get('payment_nopayn_cancelled_status_id');

		if ($cancelled_status_id && $order_id) {
			$this->load->model('checkout/order');
			$this->model_checkout_order->addOrderHistory($order_id, $cancelled_status_id, $message, false);
		}

		$this->cleanupSession();
		$this->response->redirect($this->url->link('checkout/failure', '', true));
	}

	private function cleanupSession() {
		unset(
			$this->session->data['nopayn_token'],
			$this->session->data['nopayn_order_id'],
			$this->session->data['nopayn_order_id_shop'],
			$this->session->data['nopayn_module_code']
		);
	}

	private function respondJson($json, $status_code = 200) {
		if ($status_code === 405) {
			$this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function log($message) {
		if (!$this->config->get('payment_nopayn_debug_logging')) {
			return;
		}

		$log = new Log('nopayn.log');
		$log->write($message);
	}

	private function generateToken() {
		if (function_exists('random_bytes')) {
			return bin2hex(random_bytes(32));
		}

		if (function_exists('openssl_random_pseudo_bytes')) {
			return bin2hex(openssl_random_pseudo_bytes(32));
		}

		return token(64);
	}

	private function languageValue($key, $fallback) {
		$value = $this->language->get($key);

		return $value === $key ? $fallback : $value;
	}

	private function resolveModuleCode() {
		$module_code = '';

		if (!empty($this->request->get['module'])) {
			$module_code = $this->request->get['module'];
		} elseif (!empty($this->request->post['nopayn_module_code'])) {
			$module_code = $this->request->post['nopayn_module_code'];
		} elseif (!empty($this->session->data['payment_method']['code'])) {
			$module_code = $this->session->data['payment_method']['code'];
		} elseif (!empty($this->session->data['nopayn_module_code'])) {
			$module_code = $this->session->data['nopayn_module_code'];
		}

		return in_array($module_code, self::ALLOWED_MODULES, true) ? $module_code : '';
	}

	private function loadModuleLanguage($module_code) {
		$this->load->language('extension/payment/nopayn');

		if ($module_code && in_array($module_code, self::ALLOWED_MODULES, true)) {
			$this->load->language('extension/payment/' . $module_code);
		}
	}

	private function getExpirationMinutes() {
		$value = (int)$this->config->get('payment_nopayn_expiration_minutes');

		return $value > 0 ? $value : self::DEFAULT_EXPIRATION_MINUTES;
	}

	private function resolvePaymentUrl($response) {
		if (!empty($response['transactions']) && is_array($response['transactions'])) {
			foreach ($response['transactions'] as $transaction) {
				if (!empty($transaction['payment_url'])) {
					return $transaction['payment_url'];
				}
			}
		}

		if (!empty($response['order_url'])) {
			return $response['order_url'];
		}

		return '';
	}

	private function extractTransactionValue($response, $key) {
		if (empty($response['transactions']) || !is_array($response['transactions'])) {
			return '';
		}

		foreach ($response['transactions'] as $transaction) {
			if (!empty($transaction[$key])) {
				return $transaction[$key];
			}
		}

		return '';
	}
}

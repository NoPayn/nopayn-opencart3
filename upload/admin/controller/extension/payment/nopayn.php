<?php
class ControllerExtensionPaymentNopayn extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/nopayn');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post_data = $this->request->post;
			$post_data['payment_nopayn_status'] = 1;
			$post_data['payment_nopayn_expiration_minutes'] = $this->normaliseExpirationMinutes(isset($post_data['payment_nopayn_expiration_minutes']) ? $post_data['payment_nopayn_expiration_minutes'] : 5);
			$this->model_setting_setting->editSetting('payment_nopayn', $post_data);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/nopayn', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/nopayn', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		$config_keys = array(
			'payment_nopayn_api_key',
			'payment_nopayn_expiration_minutes',
			'payment_nopayn_order_status_id',
			'payment_nopayn_pending_status_id',
			'payment_nopayn_cancelled_status_id',
			'payment_nopayn_creditcard',
			'payment_nopayn_applepay',
			'payment_nopayn_googlepay',
			'payment_nopayn_mobilepay',
			'payment_nopayn_swish',
			'payment_nopayn_creditcard_manual_capture',
			'payment_nopayn_debug_logging'
		);

		foreach ($config_keys as $config_key) {
			if (isset($this->request->post[$config_key])) {
				$data[$config_key] = $this->request->post[$config_key];
			} else {
				$data[$config_key] = $this->config->get($config_key);
			}
		}

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/nopayn', $data));
	}

	public function install() {
		$this->load->model('extension/payment/nopayn');
		$this->model_extension_payment_nopayn->install();

		$this->load->model('setting/setting');
		$settings = $this->model_setting_setting->getSetting('payment_nopayn');
		$settings['payment_nopayn_status'] = 1;
		$settings['payment_nopayn_expiration_minutes'] = isset($settings['payment_nopayn_expiration_minutes']) ? $this->normaliseExpirationMinutes($settings['payment_nopayn_expiration_minutes']) : 5;
		$this->model_setting_setting->editSetting('payment_nopayn', $settings);
	}

	public function uninstall() {
		$this->load->model('extension/payment/nopayn');
		$this->model_extension_payment_nopayn->uninstall();
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/nopayn')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	private function normaliseExpirationMinutes($value) {
		$value = (int)$value;

		return $value > 0 ? $value : 5;
	}
}

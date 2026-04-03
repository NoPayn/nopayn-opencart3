<?php
class ControllerExtensionPaymentNopaynVippsmobilepay extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/nopayn');
		$this->load->language('extension/payment/nopayn_vippsmobilepay');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_nopayn_vippsmobilepay', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['breadcrumbs'] = $this->getBreadcrumbs();
		$data['action'] = $this->url->link('extension/payment/nopayn_vippsmobilepay', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
		$data['global_settings_href'] = $this->url->link('extension/payment/nopayn', 'user_token=' . $this->session->data['user_token'], true);
		$data['module_prefix'] = 'payment_nopayn_vippsmobilepay';
		$data['module_geo_zone_id'] = isset($this->request->post['payment_nopayn_vippsmobilepay_geo_zone_id']) ? $this->request->post['payment_nopayn_vippsmobilepay_geo_zone_id'] : $this->config->get('payment_nopayn_vippsmobilepay_geo_zone_id');
		$data['module_status'] = isset($this->request->post['payment_nopayn_vippsmobilepay_status']) ? $this->request->post['payment_nopayn_vippsmobilepay_status'] : $this->config->get('payment_nopayn_vippsmobilepay_status');
		$data['module_sort_order'] = isset($this->request->post['payment_nopayn_vippsmobilepay_sort_order']) ? $this->request->post['payment_nopayn_vippsmobilepay_sort_order'] : $this->config->get('payment_nopayn_vippsmobilepay_sort_order');

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/nopayn_method', $data));
	}

	public function install() {
		$this->load->model('extension/payment/nopayn');
		$this->model_extension_payment_nopayn->install();
	}

	public function uninstall() {
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/nopayn_vippsmobilepay')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	private function getBreadcrumbs() {
		return array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			),
			array(
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/payment/nopayn_vippsmobilepay', 'user_token=' . $this->session->data['user_token'], true)
			)
		);
	}
}

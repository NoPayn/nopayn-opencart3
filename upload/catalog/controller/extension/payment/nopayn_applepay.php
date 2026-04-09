<?php
class ControllerExtensionPaymentNopaynApplepay extends Controller {
	public function index() {
		$this->load->language('extension/payment/nopayn_applepay');

		$data['module_code'] = 'nopayn_applepay';
		$data['text_redirect'] = $this->language->get('text_redirect');
		$data['button_confirm'] = 'Confirm Order';
		$data['text_loading'] = 'Loading...';
		$data['confirm_url'] = str_replace('&amp;', '&', $this->url->link('extension/payment/nopayn/confirm', 'module=nopayn_applepay', true));

		return $this->load->view('extension/payment/nopayn_method', $data);
	}
}

<?php
class ControllerExtensionPaymentNopaynSwishpay extends Controller {
	public function index() {
		$this->load->language('extension/payment/nopayn_swishpay');

		$data['module_code'] = 'nopayn_swishpay';
		$data['text_redirect'] = $this->language->get('text_redirect');
		$data['button_confirm'] = 'Confirm Order';
		$data['text_loading'] = 'Loading...';
		$data['confirm_url'] = str_replace('&amp;', '&', $this->url->link('extension/payment/nopayn/confirm', 'module=nopayn_swishpay', true));

		return $this->load->view('extension/payment/nopayn_method', $data);
	}
}

<?php
class ModelExtensionPaymentNopaynApplepay extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/nopayn_applepay');
		$this->load->model('extension/payment/nopayn');

		return $this->model_extension_payment_nopayn->getMethodData($address, $total, 'nopayn_applepay');
	}
}

<?php
class ModelExtensionPaymentNopaynCard extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/nopayn_card');
		$this->load->model('extension/payment/nopayn');

		return $this->model_extension_payment_nopayn->getMethodData($address, $total, 'nopayn_card');
	}
}

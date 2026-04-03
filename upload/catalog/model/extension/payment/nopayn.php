<?php
class ModelExtensionPaymentNopayn extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/nopayn');

		if ($this->config->get('payment_nopayn_status') != 1) {
			return array();
		}

		if (!$this->config->get('payment_nopayn_api_key')) {
			return array();
		}

		if (
			!$this->config->get('payment_nopayn_creditcard') &&
			!$this->config->get('payment_nopayn_applepay') &&
			!$this->config->get('payment_nopayn_googlepay') &&
			!$this->config->get('payment_nopayn_mobilepay')
		) {
			return array();
		}

		if ($this->cart->hasRecurringProducts()) {
			return array();
		}

		if (!$this->config->get('payment_nopayn_geo_zone_id')) {
			$status = true;
		} else {
			$query = $this->db->query(
				"SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` "
				. "WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_nopayn_geo_zone_id') . "' "
				. "AND `country_id` = '" . (int)$address['country_id'] . "' "
				. "AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')"
			);

			$status = (bool)$query->num_rows;
		}

		if (!$status) {
			return array();
		}

		return array(
			'code' => 'nopayn',
			'title' => $this->language->get('text_title'),
			'terms' => '',
			'sort_order' => $this->config->get('payment_nopayn_sort_order')
		);
	}

	public function addTransaction($order_id, $nopayn_order_id, $payment_method, $amount, $currency, $capture_mode = 'auto', $nopayn_transaction_id = '') {
		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "nopayn_transactions` SET "
			. "`order_id` = '" . (int)$order_id . "', "
			. "`nopayn_order_id` = '" . $this->db->escape($nopayn_order_id) . "', "
			. "`nopayn_transaction_id` = '" . $this->db->escape($nopayn_transaction_id) . "', "
			. "`payment_method` = '" . $this->db->escape($payment_method) . "', "
			. "`amount` = '" . (int)$amount . "', "
			. "`currency` = '" . $this->db->escape($currency) . "', "
			. "`status` = 'new', "
			. "`capture_mode` = '" . $this->db->escape($capture_mode) . "', "
			. "`created_at` = NOW(), "
			. "`updated_at` = NOW()"
		);
	}

	public function updateTransactionNopaynTransactionId($nopayn_order_id, $nopayn_transaction_id) {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "nopayn_transactions` SET "
			. "`nopayn_transaction_id` = '" . $this->db->escape($nopayn_transaction_id) . "', "
			. "`updated_at` = NOW() "
			. "WHERE `nopayn_order_id` = '" . $this->db->escape($nopayn_order_id) . "'"
		);
	}

	public function getTransactionByNopaynOrderId($nopayn_order_id) {
		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "nopayn_transactions` "
			. "WHERE `nopayn_order_id` = '" . $this->db->escape($nopayn_order_id) . "' LIMIT 1"
		);

		return $query->row ? $query->row : array();
	}

	public function getTransactionByOrderId($order_id) {
		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "nopayn_transactions` "
			. "WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `id` DESC LIMIT 1"
		);

		return $query->row ? $query->row : array();
	}

	public function updateTransactionStatus($nopayn_order_id, $status) {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "nopayn_transactions` SET "
			. "`status` = '" . $this->db->escape($status) . "', "
			. "`updated_at` = NOW() "
			. "WHERE `nopayn_order_id` = '" . $this->db->escape($nopayn_order_id) . "'"
		);
	}
}

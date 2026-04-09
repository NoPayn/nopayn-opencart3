<?php
class ModelExtensionPaymentNopayn extends Model {
	public function getMethod($address, $total) {
		return array();
	}

	public function getMethodData($address, $total, $module_code) {
		if ($this->config->get('payment_' . $module_code . '_status') != 1) {
			return array();
		}

		if (!$this->config->get('payment_nopayn_api_key')) {
			return array();
		}

		if (!$this->getRequestedPaymentMethods($module_code)) {
			return array();
		}

		if ($this->cart->hasRecurringProducts()) {
			return array();
		}

		$geo_zone_id = (int)$this->config->get('payment_' . $module_code . '_geo_zone_id');

		if (!$this->isAddressInGeoZone($geo_zone_id, $address)) {
			return array();
		}

		return array(
			'code' => $module_code,
			'title' => $this->getCheckoutTitle($module_code),
			'terms' => '',
			'sort_order' => (int)$this->config->get('payment_' . $module_code . '_sort_order')
		);
	}

	public function getRequestedPaymentMethods($module_code) {
		switch ($module_code) {
			case 'nopayn_card':
				return $this->config->get('payment_nopayn_creditcard') ? array('credit-card') : array();

			case 'nopayn_applepay':
				return $this->config->get('payment_nopayn_applepay') ? array('apple-pay') : array();

			case 'nopayn_googlepay':
				return $this->config->get('payment_nopayn_googlepay') ? array('google-pay') : array();

			case 'nopayn_vippsmobilepay':
				return $this->config->get('payment_nopayn_mobilepay') ? array('vipps-mobilepay') : array();

			case 'nopayn_swishpay':
				return $this->config->get('payment_nopayn_swish') ? array('swish') : array();

			default:
				return array();
		}
	}

	public function getTransactionEntries($module_code) {
		$entries = array();

		foreach ($this->getRequestedPaymentMethods($module_code) as $payment_method) {
			$entry = array(
				'payment_method' => $payment_method
			);

			if ($module_code === 'nopayn_card' && $this->config->get('payment_nopayn_creditcard_manual_capture')) {
				$entry['capture_mode'] = 'manual';
			}

			$entries[] = $entry;
		}

		return $entries;
	}

	public function isManualCapture($module_code) {
		return $module_code === 'nopayn_card' && $this->config->get('payment_nopayn_creditcard_manual_capture');
	}

	public function addTransaction($order_id, $nopayn_order_id, $payment_method, $amount, $currency, $capture_mode = 'auto', $nopayn_transaction_id = '', $extension_code = '') {
		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "nopayn_transactions` SET "
			. "`order_id` = '" . (int)$order_id . "', "
			. "`extension_code` = '" . $this->db->escape($extension_code) . "', "
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

	public function updateTransactionPaymentMethod($nopayn_order_id, $payment_method) {
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "nopayn_transactions` SET "
			. "`payment_method` = '" . $this->db->escape($payment_method) . "', "
			. "`updated_at` = NOW() "
			. "WHERE `nopayn_order_id` = '" . $this->db->escape($nopayn_order_id) . "'"
		);
	}

	private function getCheckoutTitle($module_code) {
		$value = $this->language->get('text_title');

		return $value !== 'text_title' ? $value : 'Payment';
	}

	private function isAddressInGeoZone($geo_zone_id, $address) {
		if (!$geo_zone_id) {
			return true;
		}

		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` "
			. "WHERE `geo_zone_id` = '" . (int)$geo_zone_id . "' "
			. "AND `country_id` = '" . (int)$address['country_id'] . "' "
			. "AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')"
		);

		return (bool)$query->num_rows;
	}
}

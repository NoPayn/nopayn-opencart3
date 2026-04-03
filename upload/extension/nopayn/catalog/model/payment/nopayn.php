<?php
namespace Opencart\Catalog\Model\Extension\Nopayn\Payment;

class Nopayn extends \Opencart\System\Engine\Model {

    public function getMethods(array $address = []): array {
        $this->load->language('extension/nopayn/payment/nopayn');

        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->config->get('payment_nopayn_status')) {
            $status = false;
        } elseif (!$this->config->get('payment_nopayn_api_key')) {
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_nopayn_geo_zone_id')) {
            $status = true;
        } else {
            $query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` "
                . "WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_nopayn_geo_zone_id') . "' "
                . "AND `country_id` = '" . (int)$address['country_id'] . "' "
                . "AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')"
            );
            $status = $query->num_rows > 0;
        }

        $method_data = [];

        if ($status) {
            $option_data = [];

            if ($this->config->get('payment_nopayn_creditcard')) {
                $option_data['nopayn_creditcard'] = [
                    'code' => 'nopayn.nopayn_creditcard',
                    'name' => $this->language->get('text_creditcard'),
                ];
            }

            if ($this->config->get('payment_nopayn_applepay')) {
                $option_data['nopayn_applepay'] = [
                    'code' => 'nopayn.nopayn_applepay',
                    'name' => $this->language->get('text_applepay'),
                ];
            }

            if ($this->config->get('payment_nopayn_googlepay')) {
                $option_data['nopayn_googlepay'] = [
                    'code' => 'nopayn.nopayn_googlepay',
                    'name' => $this->language->get('text_googlepay'),
                ];
            }

            if ($this->config->get('payment_nopayn_mobilepay')) {
                $option_data['nopayn_mobilepay'] = [
                    'code' => 'nopayn.nopayn_mobilepay',
                    'name' => $this->language->get('text_mobilepay'),
                ];
            }

            if ($option_data) {
                $method_data = [
                    'code'       => 'nopayn',
                    'name'       => $this->language->get('heading_title'),
                    'option'     => $option_data,
                    'sort_order' => $this->config->get('payment_nopayn_sort_order'),
                ];
            }
        }

        return $method_data;
    }

    public function addTransaction(int $orderId, string $nopaynOrderId, string $paymentMethod, int $amount, string $currency): void {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "nopayn_transactions` SET "
            . "`order_id` = '" . (int)$orderId . "', "
            . "`nopayn_order_id` = '" . $this->db->escape($nopaynOrderId) . "', "
            . "`payment_method` = '" . $this->db->escape($paymentMethod) . "', "
            . "`amount` = '" . (int)$amount . "', "
            . "`currency` = '" . $this->db->escape($currency) . "', "
            . "`status` = 'new', "
            . "`created_at` = NOW(), "
            . "`updated_at` = NOW()"
        );
    }

    public function getTransactionByNopaynOrderId(string $nopaynOrderId): array {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "nopayn_transactions` "
            . "WHERE `nopayn_order_id` = '" . $this->db->escape($nopaynOrderId) . "' LIMIT 1"
        );
        return $query->row ?: [];
    }

    public function getTransactionByOrderId(int $orderId): array {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "nopayn_transactions` "
            . "WHERE `order_id` = '" . (int)$orderId . "' ORDER BY `id` DESC LIMIT 1"
        );
        return $query->row ?: [];
    }

    public function updateTransactionStatus(string $nopaynOrderId, string $status): void {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "nopayn_transactions` SET "
            . "`status` = '" . $this->db->escape($status) . "', "
            . "`updated_at` = NOW() "
            . "WHERE `nopayn_order_id` = '" . $this->db->escape($nopaynOrderId) . "'"
        );
    }
}

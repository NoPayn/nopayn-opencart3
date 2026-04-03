<?php
namespace Opencart\Admin\Model\Extension\Nopayn\Payment;

class Nopayn extends \Opencart\System\Engine\Model {

    public function install(): void {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "nopayn_transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `nopayn_order_id` VARCHAR(255) NOT NULL,
            `payment_method` VARCHAR(64) NOT NULL DEFAULT '',
            `amount` INT NOT NULL,
            `currency` VARCHAR(3) NOT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'new',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_order_id` (`order_id`),
            INDEX `idx_nopayn_order_id` (`nopayn_order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "nopayn_refunds` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `nopayn_order_id` VARCHAR(255) NOT NULL,
            `nopayn_refund_id` VARCHAR(255) DEFAULT '',
            `amount` INT NOT NULL,
            `currency` VARCHAR(3) NOT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
            `description` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_order_id` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function uninstall(): void {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "nopayn_transactions`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "nopayn_refunds`");
    }
}

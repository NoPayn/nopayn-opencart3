<?php
class ModelExtensionPaymentNopayn extends Model {
	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "nopayn_transactions` (
			`id` INT(11) NOT NULL AUTO_INCREMENT,
			`order_id` INT(11) NOT NULL,
			`nopayn_order_id` VARCHAR(255) NOT NULL,
			`nopayn_transaction_id` VARCHAR(255) NOT NULL DEFAULT '',
			`payment_method` VARCHAR(64) NOT NULL DEFAULT '',
			`amount` INT(11) NOT NULL,
			`currency` CHAR(3) NOT NULL,
			`status` VARCHAR(32) NOT NULL DEFAULT 'new',
			`capture_mode` VARCHAR(16) NOT NULL DEFAULT 'auto',
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `idx_order_id` (`order_id`),
			KEY `idx_nopayn_order_id` (`nopayn_order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "nopayn_refunds` (
			`id` INT(11) NOT NULL AUTO_INCREMENT,
			`order_id` INT(11) NOT NULL,
			`nopayn_order_id` VARCHAR(255) NOT NULL,
			`nopayn_refund_id` VARCHAR(255) NOT NULL DEFAULT '',
			`amount` INT(11) NOT NULL,
			`currency` CHAR(3) NOT NULL,
			`status` VARCHAR(32) NOT NULL DEFAULT 'pending',
			`description` TEXT,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			KEY `idx_order_id` (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "nopayn_transactions`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "nopayn_refunds`");
	}
}

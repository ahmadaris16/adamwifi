<?php
require_once __DIR__ . '/admin_config.php';

function column_exists(PDO $pdo, $table, $column) {
  $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
  $stmt->execute([$column]);
  return (bool)$stmt->fetch();
}
function table_exists(PDO $pdo, $table) {
  try { $pdo->query("SELECT 1 FROM `$table` LIMIT 1"); return true; }
  catch (Throwable $e) { return false; }
}

header('Content-Type: text/plain; charset=UTF-8');

// users
$pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('Owner','Admin','Technician') NOT NULL DEFAULT 'Owner',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "OK: users\n";

// customers (tambah kolom bila belum ada)
if (table_exists($pdo, 'customers')) {
  if (!column_exists($pdo, 'customers', 'whatsapp_number')) {
    $pdo->exec("ALTER TABLE `customers` ADD COLUMN `whatsapp_number` VARCHAR(32) NULL AFTER `name`");
    echo "ADD: customers.whatsapp_number\n";
  }
  if (!column_exists($pdo, 'customers', 'pppoe_username')) {
    $pdo->exec("ALTER TABLE `customers` ADD COLUMN `pppoe_username` VARCHAR(100) NULL AFTER `whatsapp_number`");
    echo "ADD: customers.pppoe_username\n";
  }
  if (!column_exists($pdo, 'customers', 'billable')) {
    $pdo->exec("ALTER TABLE `customers` ADD COLUMN `billable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `pppoe_username`");
    echo "ADD: customers.billable (1=ditagih,0=gratis)\n";
  }
} else {
  echo "INFO: tabel customers tidak ditemukan, lewati.\n";
}

// audit_logs
$pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity` VARCHAR(100) NOT NULL,
  `entity_id` VARCHAR(100) NOT NULL,
  `before_json` JSON NULL,
  `after_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`), INDEX(`entity`), INDEX(`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "OK: audit_logs\n";

echo "SELESAI.\n";

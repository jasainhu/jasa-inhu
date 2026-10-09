<?php
require_once __DIR__ . '/../config/database.php';
$db = get_db();

$createSql = $db->query("SHOW CREATE TABLE service_providers")->fetch(PDO::FETCH_NUM)[1];
preg_match('/DEFAULT CHARSET=([a-zA-Z0-9_]+)/', $createSql, $m);
$charset = $m[1] ?? 'utf8';
echo "[INFO] Using charset: $charset\n";

$sql = "
CREATE TABLE IF NOT EXISTS `provider_portfolios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `image_before` VARCHAR(255) NULL,
    `image_after` VARCHAR(255) NOT NULL,
    `service_date` DATE NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_portfolios_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=$charset;
";

$db->exec($sql);
echo "[SUCCESS] Tabel provider_portfolios berhasil dibuat atau sudah ada!\n";


// Pastikan folder uploads/portfolios ada
$uploadDir = __DIR__ . '/../uploads/portfolios';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    echo "[SUCCESS] Direktori uploads/portfolios dibuat!\n";
} else {
    echo "[INFO] Direktori uploads/portfolios sudah ada.\n";
}

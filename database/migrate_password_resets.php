<?php
// Load .env jika ada
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v, " \t\n\r\0\x0B\"'"));
        }
    }
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    echo "Terhubung ke database server: " . DB_HOST . ":" . DB_PORT . " (" . DB_NAME . ")\n";
    echo "Membuat tabel password_resets jika belum ada...\n";

    $sql = "
    CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `email` VARCHAR(150) NOT NULL,
        `token` VARCHAR(64) NOT NULL UNIQUE,
        `expires_at` DATETIME NOT NULL,
        `used_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_pwd_token` (`token`),
        KEY `idx_pwd_email` (`email`),
        KEY `idx_pwd_user` (`user_id`),
        CONSTRAINT `fk_pwd_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $db->exec($sql);
    echo "[SUCCESS] Tabel password_resets berhasil dibuat atau sudah siap!\n";

    $cols = $db->query("DESCRIBE password_resets")->fetchAll(PDO::FETCH_ASSOC);
    echo "\n=== Struktur Tabel password_resets ===\n";
    foreach ($cols as $c) {
        echo "- {$c['Field']} ({$c['Type']})\n";
    }
    echo "\nSelesai!\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

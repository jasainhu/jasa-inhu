<?php
/**
 * Migration: Tabel user_verifications
 * Menyimpan data OTP dan token verifikasi akun via WhatsApp dan Gmail
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            channel ENUM('whatsapp', 'email') NOT NULL DEFAULT 'whatsapp',
            target VARCHAR(150) NOT NULL,
            code VARCHAR(10) NOT NULL,
            token VARCHAR(64) NULL,
            expires_at DATETIME NOT NULL,
            is_verified TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id),
            KEY idx_code (code),
            KEY idx_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");

    echo "Tabel user_verifications berhasil dibuat!\n";
} catch (Exception $e) {
    echo "Gagal membuat tabel user_verifications: " . $e->getMessage() . "\n";
    exit(1);
}

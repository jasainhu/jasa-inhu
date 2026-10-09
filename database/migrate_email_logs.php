<?php
/**
 * Migration: Tabel email_logs & App Settings untuk SMTP Gmail
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();

    // 1. Buat tabel email_logs
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(150) NOT NULL,
            recipient_name VARCHAR(150) NULL,
            subject VARCHAR(255) NOT NULL,
            body_text TEXT NOT NULL,
            status ENUM('sent', 'failed', 'simulated') DEFAULT 'sent',
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_recipient (recipient_email),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");

    // 2. Tambahkan pengaturan default untuk SMTP di app_settings
    $default_smtp = [
        ['smtp_host', 'smtp.gmail.com', 'email', 'Host server SMTP Gmail'],
        ['smtp_port', '587', 'email', 'Port server SMTP (587 untuk TLS atau 465 untuk SSL)'],
        ['smtp_user', '', 'email', 'Alamat akun Gmail pengirim resmi (misal: jasainhu.official@gmail.com)'],
        ['smtp_pass', '', 'email', 'Google App Password 16 karakter akun Gmail'],
        ['smtp_from_name', 'JASA INHU Resmi', 'email', 'Nama pengirim email yang tampil di inbox pengguna'],
        ['smtp_secure', 'tls', 'email', 'Tipe enkripsi SMTP (tls atau ssl)']
    ];

    $stmt = $db->prepare("
        INSERT INTO app_settings (setting_key, setting_value, setting_group, description)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE description = VALUES(description)
    ");

    foreach ($default_smtp as $s) {
        $stmt->execute($s);
    }

    echo "Tabel email_logs dan konfigurasi SMTP app_settings berhasil dibuat!\n";
} catch (Exception $e) {
    echo "Gagal migrasi email_logs: " . $e->getMessage() . "\n";
    exit(1);
}

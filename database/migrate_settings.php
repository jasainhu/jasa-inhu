<?php
/**
 * Migration: Tabel app_settings
 * Menyimpan konfigurasi dinamis (Nomor WhatsApp Admin, Rekening Bank, Lead Fee, dll)
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();

    $db->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            setting_group VARCHAR(50) DEFAULT 'general',
            description VARCHAR(255) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ");

    $default_settings = [
        ['admin_wa', '6285126241679', 'contact', 'Nomor WhatsApp Resmi Layanan & Admin JASA INHU'],
        ['admin_email', 'jasainhu@gmail.com', 'contact', 'Alamat Email Resmi Layanan & Admin JASA INHU'],
        ['bank_name_1', 'Bank Riau Kepri Syariah', 'finance', 'Nama Bank Rekening Utama 1'],
        ['bank_acc_1', '102-20-12345', 'finance', 'Nomor Rekening Bank Utama 1'],
        ['bank_owner_1', 'Jasa Inhu Official', 'finance', 'Atas Nama Pemilik Rekening 1'],
        ['bank_name_2', 'Bank Mandiri / BRI / BCA', 'finance', 'Nama Bank Rekening Alternatif 2'],
        ['bank_acc_2', '108-00-9876543', 'finance', 'Nomor Rekening Bank Alternatif 2'],
        ['bank_owner_2', 'Admin Jasa Inhu', 'finance', 'Atas Nama Pemilik Rekening 2'],
        ['ewallet_name', 'DANA / OVO / GoPay', 'finance', 'Nama Penyedia E-Wallet / Dompet Digital'],
        ['ewallet_acc', '0812-7599-0011', 'finance', 'Nomor Akun / HP E-Wallet'],
        ['ewallet_owner', 'Admin JASA INHU', 'finance', 'Atas Nama Pemilik Akun E-Wallet'],
        ['qris_info', 'Scan QRIS Langsung via WhatsApp Admin', 'finance', 'Instruksi Pembayaran QRIS'],
        ['lead_fee_amount', '3000', 'lead_fee', 'Biaya kontak per pesanan yang disepakati (Rp)'],
        ['welcome_bonus_amount', '45000', 'lead_fee', 'Bonus saldo awal gratis untuk mitra baru (Rp)']
    ];

    $stmtInsert = $db->prepare("
        INSERT INTO app_settings (setting_key, setting_value, setting_group, description)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE description = VALUES(description)
    ");

    foreach ($default_settings as $setting) {
        $stmtInsert->execute($setting);
    }

    echo "Tabel app_settings berhasil dibuat dan dikonfigurasi!\n";
} catch (Exception $e) {
    echo "Gagal migrasi app_settings: " . $e->getMessage() . "\n";
    exit(1);
}

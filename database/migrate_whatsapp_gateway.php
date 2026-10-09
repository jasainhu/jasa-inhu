<?php
/**
 * Migration: Tabel Riwayat & Integrasi Notifikasi WhatsApp (WhatsApp Gateway)
 * JASA INHU - Tahap 4
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "=== MEMULAI MIGRASI WHATSAPP GATEWAY ===\n";

try {
    $db = get_db();

    $sql = "
    CREATE TABLE IF NOT EXISTS whatsapp_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NULL,
        recipient_phone VARCHAR(30) NOT NULL,
        recipient_name VARCHAR(100) NULL,
        event_type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('queued', 'sent', 'simulated', 'failed') DEFAULT 'simulated',
        direct_url TEXT NULL,
        response_payload TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_wa_request (request_id),
        KEY idx_wa_phone (recipient_phone),
        KEY idx_wa_event (event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $db->exec($sql);
    echo "[OK] Tabel `whatsapp_logs` berhasil dibuat atau sudah ada.\n";

    echo "=== MIGRASI WHATSAPP SELESAI DENGAN SUKSES ===\n";
} catch (Exception $e) {
    echo "[ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    exit(1);
}

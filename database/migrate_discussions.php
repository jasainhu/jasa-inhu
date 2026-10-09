<?php
/**
 * Migration: Tabel provider_discussions (Tanya Jawab & Diskusi Layanan)
 * JASA INHU
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "=== MEMULAI MIGRASI TABEL PROVIDER_DISCUSSIONS ===\n";

try {
    $db = get_db();

    $sql = "
    CREATE TABLE IF NOT EXISTS provider_discussions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_id INT NOT NULL,
        user_id INT NOT NULL,
        parent_id INT NULL,
        message TEXT NOT NULL,
        is_provider_reply TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_disc_provider (provider_id),
        KEY idx_disc_user (user_id),
        KEY idx_disc_parent (parent_id),
        CONSTRAINT fk_disc_provider FOREIGN KEY (provider_id) REFERENCES service_providers (id) ON DELETE CASCADE,
        CONSTRAINT fk_disc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_disc_parent FOREIGN KEY (parent_id) REFERENCES provider_discussions (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";

    $db->exec($sql);
    echo "[OK] Tabel `provider_discussions` berhasil dibuat atau sudah ada.\n";

} catch (Exception $e) {
    echo "[ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
/**
 * Migration: Sistem Dompet Deposit Mitra, Biaya Kontak (Lead Fee) & Tagihan Akhir
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "=== MEMULAI MIGRASI FITUR DOMPET DEPOSIT & BIAYA KONTAK (LEAD FEE) ===\n";

try {
    $db = get_db();

    // 1. Tambah kolom `wallet_balance` ke tabel `service_providers` jika belum ada
    $checkCol = $db->query("SHOW COLUMNS FROM service_providers LIKE 'wallet_balance'")->fetch();
    if (!$checkCol) {
        $db->exec("ALTER TABLE service_providers ADD COLUMN wallet_balance DECIMAL(12,2) DEFAULT 0.00 AFTER completed_jobs");
        echo "[OK] Kolom `wallet_balance` berhasil ditambahkan ke `service_providers`.\n";
    } else {
        echo "[INFO] Kolom `wallet_balance` sudah ada di `service_providers`.\n";
    }

    // 2. Tambah kolom `final_price` dan `cost_breakdown` ke `service_requests` jika belum ada
    $checkFinal = $db->query("SHOW COLUMNS FROM service_requests LIKE 'final_price'")->fetch();
    if (!$checkFinal) {
        $db->exec("ALTER TABLE service_requests ADD COLUMN final_price DECIMAL(12,2) NULL AFTER budget");
        $db->exec("ALTER TABLE service_requests ADD COLUMN cost_breakdown TEXT NULL AFTER final_price");
        echo "[OK] Kolom `final_price` & `cost_breakdown` berhasil ditambahkan ke `service_requests`.\n";
    } else {
        echo "[INFO] Kolom `final_price` sudah ada di `service_requests`.\n";
    }

    // 3. Tabel Mutasi Dompet Mitra (provider_wallet_transactions)
    $sqlTx = "
    CREATE TABLE IF NOT EXISTS provider_wallet_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_id INT NOT NULL,
        type ENUM('topup', 'lead_fee', 'bonus', 'refund', 'adjustment') NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        balance_after DECIMAL(12,2) NOT NULL,
        description VARCHAR(255) NOT NULL,
        reference_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pwt_provider (provider_id),
        KEY idx_pwt_type (type),
        KEY idx_pwt_created (created_at),
        CONSTRAINT fk_pwt_provider FOREIGN KEY (provider_id) REFERENCES service_providers (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $db->exec($sqlTx);
    echo "[OK] Tabel `provider_wallet_transactions` berhasil dibuat atau sudah ada.\n";

    // 4. Tabel Permintaan Top-up Saldo (wallet_topup_requests)
    $sqlTopup = "
    CREATE TABLE IF NOT EXISTS wallet_topup_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        payment_method VARCHAR(100) DEFAULT 'Transfer Bank / QRIS',
        proof_image VARCHAR(255) NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approved_at DATETIME NULL,
        KEY idx_wtr_provider (provider_id),
        KEY idx_wtr_status (status),
        CONSTRAINT fk_wtr_provider FOREIGN KEY (provider_id) REFERENCES service_providers (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $db->exec($sqlTopup);
    echo "[OK] Tabel `wallet_topup_requests` berhasil dibuat atau sudah ada.\n";

    // 5. Berikan Saldo Awal / Starter Bonus (Rp 30.000 = kuota 10 order) ke semua mitra aktif jika saldo masih 0
    $stmtProv = $db->query("SELECT id, business_name, wallet_balance FROM service_providers WHERE wallet_balance = 0 OR wallet_balance IS NULL");
    $providers = $stmtProv->fetchAll();
    
    $bonusAmount = 30000.00;
    foreach ($providers as $prov) {
        $provId = (int)$prov['id'];
        
        // Update saldo
        $upd = $db->prepare("UPDATE service_providers SET wallet_balance = ? WHERE id = ?");
        $upd->execute([$bonusAmount, $provId]);

        // Catat transaksi bonus
        $stmtInsTx = $db->prepare("
            INSERT INTO provider_wallet_transactions 
            (provider_id, type, amount, balance_after, description, created_at)
            VALUES (?, 'bonus', ?, ?, 'Bonus Saldo Awal Mitra Baru JASA INHU (10 Kuota Pesanan)', NOW())
        ");
        $stmtInsTx->execute([$provId, $bonusAmount, $bonusAmount]);
        echo "  - Bonus Rp 30.000 diberikan ke Mitra ID $provId (" . $prov['business_name'] . ").\n";
    }

    echo "\n=== MIGRASI SELESAI DENGAN SUKSES! ===\n";

} catch (Exception $e) {
    echo "[ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    exit(1);
}

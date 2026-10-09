<?php
/**
 * Migration: Tabel Riwayat Garis Waktu Pesanan (Order Tracker Timeline)
 * JASA INHU - Pelacak Status Pesanan Interaktif
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "=== MEMULAI MIGRASI ORDER TRACKER TIMELINE ===\n";

try {
    $db = get_db();

    // 1. Tambah kolom `progress_step` ke tabel `service_requests` jika belum ada
    $checkStep = $db->query("SHOW COLUMNS FROM service_requests LIKE 'progress_step'")->fetch();
    if (!$checkStep) {
        $db->exec("ALTER TABLE service_requests ADD COLUMN progress_step VARCHAR(30) DEFAULT 'created' AFTER status");
        echo "[OK] Kolom `progress_step` berhasil ditambahkan ke `service_requests`.\n";
    } else {
        echo "[INFO] Kolom `progress_step` sudah ada di `service_requests`.\n";
    }

    // 2. Buat tabel `service_request_timeline`
    $sqlTimeline = "
    CREATE TABLE IF NOT EXISTS service_request_timeline (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        status_key VARCHAR(50) NOT NULL,
        title VARCHAR(150) NOT NULL,
        note TEXT NULL,
        actor_role VARCHAR(50) DEFAULT 'system',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_timeline_request (request_id),
        KEY idx_timeline_created (created_at),
        CONSTRAINT fk_timeline_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $db->exec($sqlTimeline);
    echo "[OK] Tabel `service_request_timeline` berhasil dibuat atau sudah ada.\n";

    // 3. Backfill data awal untuk pesanan-pesanan yang sudah ada di database
    $existingRequests = $db->query("SELECT id, status, created_at, updated_at FROM service_requests ORDER BY id ASC")->fetchAll();
    $backfillCount = 0;

    foreach ($existingRequests as $req) {
        $reqId = (int)$req['id'];
        $chk = $db->query("SELECT COUNT(*) FROM service_request_timeline WHERE request_id = {$reqId}")->fetchColumn();
        if ((int)$chk === 0) {
            // Log 1: Pesanan Dibuat
            $stmt = $db->prepare("INSERT INTO service_request_timeline (request_id, status_key, title, note, actor_role, created_at) VALUES (?, 'created', 'Pesanan Jasa Dibuat', 'Pelanggan berhasil mengirimkan pesanan jasa.', 'customer', ?)");
            $stmt->execute([$reqId, $req['created_at']]);

            // Jika status in_progress atau completed
            if ($req['status'] === 'in_progress' || $req['status'] === 'completed') {
                $stmt = $db->prepare("INSERT INTO service_request_timeline (request_id, status_key, title, note, actor_role, created_at) VALUES (?, 'accepted', 'Mitra Menyetujui Pesanan', 'Mitra penyedia jasa menyepakati pekerjaan dan mulai bersiap.', 'provider', DATE_ADD(?, INTERVAL 5 MINUTE))");
                $stmt->execute([$reqId, $req['created_at']]);

                $stmt = $db->prepare("INSERT INTO service_request_timeline (request_id, status_key, title, note, actor_role, created_at) VALUES (?, 'working', 'Pengerjaan Jasa Berlangsung', 'Mitra telah berada di lokasi dan melakukan pengerjaan jasa.', 'provider', DATE_ADD(?, INTERVAL 30 MINUTE))");
                $stmt->execute([$reqId, $req['created_at']]);
            }

            // Jika status completed
            if ($req['status'] === 'completed') {
                $compTime = $req['updated_at'] ?: date('Y-m-d H:i:s', strtotime($req['created_at'] . ' +2 hours'));
                $stmt = $db->prepare("INSERT INTO service_request_timeline (request_id, status_key, title, note, actor_role, created_at) VALUES (?, 'completed', 'Pekerjaan Selesai & Tagihan Diterbitkan', 'Pengerjaan jasa telah tuntas dilaksanakan.', 'provider', ?)");
                $stmt->execute([$reqId, $compTime]);
            }
            $backfillCount++;
        }
    }
    echo "[OK] Backfill timeline berhasil untuk {$backfillCount} pesanan eksisting.\n";

    echo "=== MIGRASI SELESAI DENGAN SUKSES ===\n";
} catch (Exception $e) {
    echo "[ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
/**
 * Migration: Peningkatan Sistem Ulasan & Rating (Review Enhancement)
 * Menambahkan foto bukti kerja, tag ulasan kepuasan, dan respon/tanggapan mitra
 * JASA INHU - Tahap 2
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "=== MEMULAI MIGRASI REVIEW & RATING ENHANCEMENT ===\n";

try {
    $db = get_db();

    // 1. Tambah kolom `photo_url` ke tabel `reviews`
    $checkPhoto = $db->query("SHOW COLUMNS FROM reviews LIKE 'photo_url'")->fetch();
    if (!$checkPhoto) {
        $db->exec("ALTER TABLE reviews ADD COLUMN photo_url VARCHAR(255) NULL AFTER comment");
        echo "[OK] Kolom `photo_url` berhasil ditambahkan ke `reviews`.\n";
    } else {
        echo "[INFO] Kolom `photo_url` sudah ada di `reviews`.\n";
    }

    // 2. Tambah kolom `tags` ke tabel `reviews`
    $checkTags = $db->query("SHOW COLUMNS FROM reviews LIKE 'tags'")->fetch();
    if (!$checkTags) {
        $db->exec("ALTER TABLE reviews ADD COLUMN tags TEXT NULL AFTER photo_url");
        echo "[OK] Kolom `tags` berhasil ditambahkan ke `reviews`.\n";
    } else {
        echo "[INFO] Kolom `tags` sudah ada di `reviews`.\n";
    }

    // 3. Tambah kolom `reply_text` ke tabel `reviews`
    $checkReply = $db->query("SHOW COLUMNS FROM reviews LIKE 'reply_text'")->fetch();
    if (!$checkReply) {
        $db->exec("ALTER TABLE reviews ADD COLUMN reply_text TEXT NULL AFTER tags");
        echo "[OK] Kolom `reply_text` berhasil ditambahkan ke `reviews`.\n";
    } else {
        echo "[INFO] Kolom `reply_text` sudah ada di `reviews`.\n";
    }

    // 4. Tambah kolom `replied_at` ke tabel `reviews`
    $checkRepliedAt = $db->query("SHOW COLUMNS FROM reviews LIKE 'replied_at'")->fetch();
    if (!$checkRepliedAt) {
        $db->exec("ALTER TABLE reviews ADD COLUMN replied_at TIMESTAMP NULL AFTER reply_text");
        echo "[OK] Kolom `replied_at` berhasil ditambahkan ke `reviews`.\n";
    } else {
        echo "[INFO] Kolom `replied_at` sudah ada di `reviews`.\n";
    }

    // 5. Pastikan folder direktori uploads/reviews/ ada
    $uploadDir = __DIR__ . '/../uploads/reviews';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
        echo "[OK] Folder direktori `uploads/reviews` berhasil dibuat.\n";
    } else {
        echo "[INFO] Folder direktori `uploads/reviews` sudah ada.\n";
    }

    echo "=== MIGRASI SELESAI DENGAN SUKSES ===\n";
} catch (Exception $e) {
    echo "[ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    exit(1);
}

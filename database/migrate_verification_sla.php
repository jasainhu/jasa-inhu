<?php
/**
 * Migrasi Kolom SLA Verifikasi 1 x 24 Jam Dokumen KTP & Sertifikat Mitra
 */
require_once __DIR__ . '/../config/app.php';

try {
    $db = get_db();
    echo "Menghubungkan ke database: " . DB_NAME . "\n";

    // 1. Cek kolom di service_providers
    $cols = $db->query("DESCRIBE service_providers")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('verification_submitted_at', $cols)) {
        $db->exec("ALTER TABLE service_providers ADD COLUMN verification_submitted_at TIMESTAMP NULL DEFAULT NULL AFTER is_verified");
        echo "[SUCCESS] Kolom `verification_submitted_at` berhasil ditambahkan.\n";
    } else {
        echo "[INFO] Kolom `verification_submitted_at` sudah ada.\n";
    }

    if (!in_array('verification_notes', $cols)) {
        $db->exec("ALTER TABLE service_providers ADD COLUMN verification_notes TEXT NULL DEFAULT NULL AFTER verification_submitted_at");
        echo "[SUCCESS] Kolom `verification_notes` berhasil ditambahkan.\n";
    } else {
        echo "[INFO] Kolom `verification_notes` sudah ada.\n";
    }

    // Set default verification_submitted_at untuk yang sudah upload tapi belum diverifikasi
    $stmtInit = $db->exec("
        UPDATE service_providers 
        SET verification_submitted_at = updated_at 
        WHERE (is_verified = 0 OR is_verified IS NULL) 
          AND ((id_card_image IS NOT NULL AND LENGTH(id_card_image) > 50) 
            OR (certificate_url IS NOT NULL AND LENGTH(certificate_url) > 50))
          AND verification_submitted_at IS NULL
    ");
    echo "[INFO] Inisialisasi tanggal pengajuan untuk mitra pending: {$stmtInit} baris diperbarui.\n";

    echo "\n=== MIGRASI SELESAI DENGAN SUKSES ===\n";
} catch (Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

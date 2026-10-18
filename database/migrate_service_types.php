<?php
/**
 * Migrasi Kolom Tipe Layanan Mitra (Panggilan ke Rumah & Toko Fisik)
 * JASA INHU - Kompatibel TiDB Cloud & MySQL
 */

require_once __DIR__ . '/../config/database.php';

$db = get_db();

echo "Memulai migrasi tipe layanan mitra...\n";

// 1. Tambah kolom serves_home_visit jika belum ada
try {
    $db->exec("ALTER TABLE service_providers ADD COLUMN serves_home_visit TINYINT(1) NOT NULL DEFAULT 1");
    echo "Kolom 'serves_home_visit' berhasil ditambahkan.\n";
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'already exists')) {
        echo "Kolom 'serves_home_visit' sudah ada.\n";
    } else {
        echo "Info serves_home_visit: " . $e->getMessage() . "\n";
    }
}

// 2. Tambah kolom has_physical_store jika belum ada
try {
    $db->exec("ALTER TABLE service_providers ADD COLUMN has_physical_store TINYINT(1) NOT NULL DEFAULT 0");
    echo "Kolom 'has_physical_store' berhasil ditambahkan.\n";
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'already exists')) {
        echo "Kolom 'has_physical_store' sudah ada.\n";
    } else {
        echo "Info has_physical_store: " . $e->getMessage() . "\n";
    }
}

// 3. Set default untuk mitra yang sudah ada agar semuanya default serves_home_visit = 1
$db->exec("UPDATE service_providers SET serves_home_visit = 1 WHERE serves_home_visit IS NULL");

// Khusus toko yang jelas punya toko fisik (seperti RANIA SABLON, ZILA KOMPUTER, Bengkel las, dll) kita set has_physical_store = 1
$storeKeywords = ['SABLON', 'KOMPUTER', 'BENGKEL', 'STORE', 'ELEKTRONIK', 'PENJAHIT', 'CAKE', 'SPA'];
foreach ($storeKeywords as $kw) {
    $db->exec("UPDATE service_providers SET has_physical_store = 1 WHERE UPPER(business_name) LIKE '%{$kw}%'");
}

echo "Migrasi tipe layanan selesai sukses!\n";

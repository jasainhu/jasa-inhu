<?php
require_once __DIR__ . '/../config/database.php';

$db = get_db();

// 1. Cek apakah kolom image_url sudah ada di tabel service_providers
$cols = $db->query('DESCRIBE service_providers')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('image_url', $cols)) {
    $db->exec('ALTER TABLE service_providers ADD COLUMN image_url VARCHAR(255) NULL AFTER business_name');
    echo "[MIGRATION] Kolom 'image_url' berhasil ditambahkan ke tabel service_providers.\n";
} else {
    echo "[MIGRATION] Kolom 'image_url' sudah ada di service_providers.\n";
}

// 2. Buat folder uploads/providers jika belum ada
$uploadDir = __DIR__ . '/../uploads/providers';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    echo "[FOLDER] Folder 'uploads/providers' berhasil dibuat.\n";
} else {
    echo "[FOLDER] Folder 'uploads/providers' sudah ada.\n";
}

<?php
require_once __DIR__ . '/../config/database.php';

$db = get_db();

// 1. Cek kolom id_card_image pada tabel service_providers
$cols = $db->query('DESCRIBE service_providers')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('id_card_image', $cols)) {
    $db->exec('ALTER TABLE service_providers ADD COLUMN id_card_image VARCHAR(255) NULL AFTER id_card_number');
    echo "[MIGRATION] Kolom 'id_card_image' berhasil ditambahkan ke tabel service_providers.\n";
} else {
    echo "[MIGRATION] Kolom 'id_card_image' sudah ada di service_providers.\n";
}

// 2. Buat folder uploads/ktp jika belum ada
$ktpDir = __DIR__ . '/../uploads/ktp';
if (!is_dir($ktpDir)) {
    mkdir($ktpDir, 0777, true);
    echo "[FOLDER] Folder 'uploads/ktp' berhasil dibuat.\n";
} else {
    echo "[FOLDER] Folder 'uploads/ktp' sudah ada.\n";
}

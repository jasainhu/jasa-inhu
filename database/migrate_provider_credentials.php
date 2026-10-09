<?php
require_once __DIR__ . '/../config/database.php';

$db = get_db();

// 1. Cek apakah kolom credential_title dan certificate_url sudah ada di service_providers
$cols = $db->query('DESCRIBE service_providers')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('credential_title', $cols)) {
    $db->exec('ALTER TABLE service_providers ADD COLUMN credential_title VARCHAR(150) NULL AFTER headline');
    echo "[MIGRATION] Kolom 'credential_title' berhasil ditambahkan.\n";
} else {
    echo "[MIGRATION] Kolom 'credential_title' sudah ada.\n";
}

if (!in_array('certificate_url', $cols)) {
    $db->exec('ALTER TABLE service_providers ADD COLUMN certificate_url VARCHAR(255) NULL AFTER credential_title');
    echo "[MIGRATION] Kolom 'certificate_url' berhasil ditambahkan.\n";
} else {
    echo "[MIGRATION] Kolom 'certificate_url' sudah ada.\n";
}

// 2. Buat direktori uploads/certificates jika belum ada
$certDir = __DIR__ . '/../uploads/certificates';
if (!is_dir($certDir)) {
    mkdir($certDir, 0777, true);
    echo "[FOLDER] Folder 'uploads/certificates' berhasil dibuat.\n";
} else {
    echo "[FOLDER] Folder 'uploads/certificates' sudah ada.\n";
}

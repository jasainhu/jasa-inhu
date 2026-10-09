<?php
/**
 * Migration: Tambah kolom 'position' pada tabel banners
 */
require_once __DIR__ . '/../config/database.php';

$db = get_db();

$cols = $db->query("DESCRIBE banners")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('position', $cols)) {
    $db->exec("ALTER TABLE banners ADD COLUMN position VARCHAR(30) NOT NULL DEFAULT 'carousel' AFTER show_overlay");
    echo "[MIGRATION] Kolom 'position' berhasil ditambahkan ke tabel banners.\n";
} else {
    echo "[MIGRATION] Kolom 'position' sudah ada.\n";
}

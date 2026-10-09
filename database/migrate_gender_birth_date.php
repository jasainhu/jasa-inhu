<?php
require_once __DIR__ . '/../config/database.php';

$db = get_db();

$cols = $db->query('DESCRIBE profiles')->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('gender', $cols)) {
    $db->exec("ALTER TABLE profiles ADD COLUMN gender VARCHAR(20) NULL AFTER bio");
    echo "[MIGRATION] Kolom 'gender' berhasil ditambahkan ke tabel profiles.\n";
} else {
    echo "[MIGRATION] Kolom 'gender' sudah ada di tabel profiles.\n";
}

if (!in_array('birth_date', $cols)) {
    $db->exec("ALTER TABLE profiles ADD COLUMN birth_date DATE NULL AFTER gender");
    echo "[MIGRATION] Kolom 'birth_date' berhasil ditambahkan ke tabel profiles.\n";
} else {
    echo "[MIGRATION] Kolom 'birth_date' sudah ada di tabel profiles.\n";
}

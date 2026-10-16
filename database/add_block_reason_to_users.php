<?php
require_once __DIR__ . '/../config/database.php';

$db = get_db();

echo "Memeriksa struktur tabel users...\n";

$stmt = $db->query("SHOW COLUMNS FROM users LIKE 'block_reason'");
$col_exists = $stmt->fetch();

if (!$col_exists) {
    echo "Menambahkan kolom block_reason dan blocked_at ke tabel users...\n";
    $db->exec("ALTER TABLE users ADD COLUMN block_reason VARCHAR(255) NULL AFTER is_active");
    $db->exec("ALTER TABLE users ADD COLUMN blocked_at DATETIME NULL AFTER block_reason");
    echo "Kolom block_reason dan blocked_at berhasil ditambahkan!\n";
} else {
    echo "Kolom block_reason sudah tersedia di tabel users.\n";
}

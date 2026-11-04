<?php
/**
 * Migration Endpoint: Update reviews.photo_url column to MEDIUMTEXT
 * Memastikan penyimpanan foto ulasan berbasis Base64 / File berjalan lancar di TiDB Cloud
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    
    // 1. Cek apakah kolom photo_url sudah ada
    $checkCol = $db->query("SHOW COLUMNS FROM reviews LIKE 'photo_url'")->fetch(PDO::FETCH_ASSOC);
    
    if (!$checkCol) {
        $db->exec("ALTER TABLE reviews ADD COLUMN photo_url MEDIUMTEXT NULL AFTER comment");
        $status = 'added_mediumtext';
    } else {
        $db->exec("ALTER TABLE reviews MODIFY COLUMN photo_url MEDIUMTEXT NULL");
        $status = 'modified_to_mediumtext';
    }

    // 2. Pastikan kolom tags, reply_text, replied_at juga ada
    $checkTags = $db->query("SHOW COLUMNS FROM reviews LIKE 'tags'")->fetch(PDO::FETCH_ASSOC);
    if (!$checkTags) {
        $db->exec("ALTER TABLE reviews ADD COLUMN tags TEXT NULL AFTER photo_url");
    }

    $checkReply = $db->query("SHOW COLUMNS FROM reviews LIKE 'reply_text'")->fetch(PDO::FETCH_ASSOC);
    if (!$checkReply) {
        $db->exec("ALTER TABLE reviews ADD COLUMN reply_text TEXT NULL AFTER tags");
    }

    $checkRepliedAt = $db->query("SHOW COLUMNS FROM reviews LIKE 'replied_at'")->fetch(PDO::FETCH_ASSOC);
    if (!$checkRepliedAt) {
        $db->exec("ALTER TABLE reviews ADD COLUMN replied_at TIMESTAMP NULL AFTER reply_text");
    }

    // Verifikasi kolom sekarang
    $verify = $db->query("SHOW COLUMNS FROM reviews LIKE 'photo_url'")->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'status' => $status,
        'column_info' => $verify,
        'message' => 'Kolom reviews.photo_url berhasil diperbarui ke MEDIUMTEXT!'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memperbarui kolom database: ' . $e->getMessage()
    ]);
}

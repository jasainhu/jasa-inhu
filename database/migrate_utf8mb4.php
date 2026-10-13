<?php
/**
 * Migrasi Charset Database ke utf8mb4
 * Memastikan tabel notifications, chat_messages, users, dll dapat menyimpan karakter emoji & simbol
 */

require_once __DIR__ . '/../config/database.php';

$db = get_db();

$columns = [
    ['notifications', 'title', 'VARCHAR(255)'],
    ['notifications', 'message', 'TEXT'],
    ['chat_messages', 'message', 'TEXT'],
    ['service_requests', 'title', 'VARCHAR(200)'],
    ['service_requests', 'description', 'TEXT'],
    ['reviews', 'comment', 'TEXT']
];

foreach ($columns as [$tbl, $col, $type]) {
    try {
        $db->exec("ALTER TABLE {$tbl} MODIFY {$col} {$type} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Column {$tbl}.{$col} set to utf8mb4.\n";
    } catch (Exception $e) {
        echo "Column {$tbl}.{$col} note: " . $e->getMessage() . "\n";
    }
}

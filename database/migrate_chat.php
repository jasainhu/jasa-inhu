<?php
/**
 * Migration: Tabel conversations & chat_messages (Fitur Obrolan / Live Chat In-App)
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

echo "=== MEMULAI MIGRASI FITUR OBROLAN / LIVE CHAT ===\n";

try {
    $db = get_db();

    // 1. Tabel Percakapan (Conversations)
    $sql1 = "
    CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_one_id INT NOT NULL,
        user_two_id INT NOT NULL,
        provider_id INT NULL,
        last_message TEXT NULL,
        last_message_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_conv_user_one (user_one_id),
        KEY idx_conv_user_two (user_two_id),
        KEY idx_conv_provider (provider_id),
        KEY idx_conv_last_time (last_message_at),
        UNIQUE KEY uq_conversation_users (user_one_id, user_two_id),
        CONSTRAINT fk_conv_user_one FOREIGN KEY (user_one_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_conv_user_two FOREIGN KEY (user_two_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_conv_provider FOREIGN KEY (provider_id) REFERENCES service_providers (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $db->exec($sql1);
    echo "[OK] Tabel `conversations` berhasil dibuat atau sudah ada.\n";

    // 2. Tabel Pesan Chat (Chat Messages)
    $sql2 = "
    CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_msg_conv (conversation_id),
        KEY idx_msg_sender (sender_id),
        KEY idx_msg_receiver (receiver_id),
        KEY idx_msg_read (is_read),
        KEY idx_msg_time (created_at),
        CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
        CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $db->exec($sql2);
    echo "[OK] Tabel `chat_messages` berhasil dibuat atau sudah ada.\n";

} catch (Exception $e) {
    echo "[ERROR] Migrasi chat gagal: " . $e->getMessage() . "\n";
    exit(1);
}

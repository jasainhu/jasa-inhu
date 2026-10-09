<?php
/**
 * API: Hitung Jumlah Pesan Belum Dibaca (Navbar Badge Poller)
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'unread' => 0]);
    exit;
}

$user = current_user();
$db = get_db();

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $unread = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'unread' => $unread
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'unread' => 0]);
}

<?php
/**
 * API: Tandai Notifikasi Sudah Dibaca
 * JASA INHU
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk terlebih dahulu.']);
    exit;
}

$user = current_user();
$db = get_db();

try {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Semua notifikasi telah ditandai dibaca.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memperbarui status notifikasi: ' . $e->getMessage()
    ]);
}

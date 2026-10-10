<?php
/**
 * API: Cek Pesan Masuk & Notifikasi Pesanan Baru Real-time untuk Mitra
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = current_user();
$db = get_db();

try {
    // 1. Ambil ID provider
    $stmtP = $db->prepare("SELECT id, business_name FROM service_providers WHERE user_id = ? LIMIT 1");
    $stmtP->execute([$user['id']]);
    $provider = $stmtP->fetch();

    if (!$provider) {
        echo json_encode(['success' => false, 'is_provider' => false]);
        exit;
    }

    $provider_id = (int)$provider['id'];

    // 2. Cek pesanan langsung baru (status: open)
    $stmtCheck = $db->prepare("
        SELECT sr.id, sr.title, sr.budget, sr.created_at, u.name as customer_name
        FROM service_requests sr
        JOIN users u ON sr.user_id = u.id
        WHERE sr.provider_id = ? AND sr.status = 'open'
        ORDER BY sr.id DESC
        LIMIT 1
    ");
    $stmtCheck->execute([$provider_id]);
    $latestOrder = $stmtCheck->fetch();

    // Hitung total order baru yang belum direspon
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM service_requests WHERE provider_id = ? AND status = 'open'");
    $stmtCount->execute([$provider_id]);
    $totalNew = (int)$stmtCount->fetchColumn();

    // Hitung pesan chat belum dibaca
    $stmtUnreadChat = $db->prepare("
        SELECT COUNT(*) 
        FROM chat_messages cm
        JOIN conversations c ON cm.conversation_id = c.id
        WHERE cm.receiver_id = ? AND cm.is_read = 0
    ");
    $stmtUnreadChat->execute([$user['id']]);
    $totalUnreadChat = (int)$stmtUnreadChat->fetchColumn();

    echo json_encode([
        'success'           => true,
        'is_provider'       => true,
        'provider_id'       => $provider_id,
        'new_orders_count'  => $totalNew,
        'unread_chat_count' => $totalUnreadChat,
        'latest_order'      => $latestOrder ? [
            'id'            => (int)$latestOrder['id'],
            'title'         => $latestOrder['title'],
            'customer_name' => $latestOrder['customer_name'],
            'budget'        => (float)$latestOrder['budget'],
            'created_at'    => $latestOrder['created_at']
        ] : null
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

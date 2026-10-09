<?php
/**
 * API: Ambil Pesan Obrolan & Tandai Telah Dibaca (Chat Polling)
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = current_user();
$db = get_db();

$conversation_id = (int)($_GET['conversation_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0);

if ($conversation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Percakapan tidak valid.']);
    exit;
}

try {
    // 1. Validasi Akses Percakapan
    $stmtConv = $db->prepare("
        SELECT c.*, 
               u1.name as user_one_name, u2.name as user_two_name,
               sp.id as sp_id, sp.business_name as sp_business_name, sp.rating_avg,
               cat.name as category_name
        FROM conversations c
        JOIN users u1 ON c.user_one_id = u1.id
        JOIN users u2 ON c.user_two_id = u2.id
        LEFT JOIN service_providers sp ON c.provider_id = sp.id
        LEFT JOIN service_categories cat ON sp.primary_category_id = cat.id
        WHERE c.id = ? AND (c.user_one_id = ? OR c.user_two_id = ?)
        LIMIT 1
    ");
    $stmtConv->execute([$conversation_id, $user['id'], $user['id']]);
    $conversation = $stmtConv->fetch();

    if (!$conversation) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses percakapan ditolak.']);
        exit;
    }

    // 2. Tandai pesan yang diterima sebagai sudah dibaca
    $stmtRead = $db->prepare("
        UPDATE chat_messages 
        SET is_read = 1 
        WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmtRead->execute([$conversation_id, $user['id']]);

    // 3. Ambil pesan (semua atau incremental > last_id)
    if ($last_id > 0) {
        $stmtMsgs = $db->prepare("
            SELECT cm.*, u.name as sender_name
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.conversation_id = ? AND cm.id > ?
            ORDER BY cm.created_at ASC
        ");
        $stmtMsgs->execute([$conversation_id, $last_id]);
    } else {
        $stmtMsgs = $db->prepare("
            SELECT cm.*, u.name as sender_name
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.conversation_id = ?
            ORDER BY cm.created_at ASC
            LIMIT 100
        ");
        $stmtMsgs->execute([$conversation_id]);
    }
    $rawMessages = $stmtMsgs->fetchAll();

    $messages = [];
    foreach ($rawMessages as $m) {
        $ts = strtotime($m['created_at']);
        $messages[] = [
            'id' => (int)$m['id'],
            'sender_id' => (int)$m['sender_id'],
            'receiver_id' => (int)$m['receiver_id'],
            'sender_name' => $m['sender_name'],
            'message' => $m['message'],
            'is_read' => (int)$m['is_read'],
            'time' => date('H:i', $ts),
            'date' => date('d M Y', $ts),
            'is_me' => ((int)$m['sender_id'] === (int)$user['id'])
        ];
    }

    // Tentukan info partner bicara
    $partner_id = ((int)$conversation['user_one_id'] === (int)$user['id']) ? (int)$conversation['user_two_id'] : (int)$conversation['user_one_id'];
    $partner_name = ((int)$conversation['user_one_id'] === (int)$user['id']) ? $conversation['user_two_name'] : $conversation['user_one_name'];
    $is_partner_provider = false;
    $partner_business_name = null;

    if (!empty($conversation['sp_id'])) {
        $stmtPartnerSP = $db->prepare("SELECT id, business_name, user_id FROM service_providers WHERE user_id = ? LIMIT 1");
        $stmtPartnerSP->execute([$partner_id]);
        $psp = $stmtPartnerSP->fetch();
        if ($psp) {
            $is_partner_provider = true;
            $partner_business_name = $psp['business_name'];
        }
    }

    echo json_encode([
        'success' => true,
        'conversation_id' => $conversation_id,
        'partner' => [
            'id' => $partner_id,
            'name' => $partner_name,
            'is_provider' => $is_partner_provider,
            'business_name' => $partner_business_name,
            'provider_id' => $conversation['sp_id'] ?? null
        ],
        'messages' => $messages
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}

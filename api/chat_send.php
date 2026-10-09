<?php
/**
 * API: Kirim Pesan Obrolan Langsung (Chat In-App)
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'need_login' => true,
        'message' => 'Silakan masuk ke akun Anda terlebih dahulu untuk menggunakan fitur obrolan.'
    ]);
    exit;
}

$user = current_user();
$db = get_db();

if (!validate_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Sesi keamanan berakhir. Silakan muat ulang halaman.']);
    exit;
}

$conversation_id = !empty($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
$receiver_id = !empty($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$provider_id = !empty($_POST['provider_id']) ? (int)$_POST['provider_id'] : null;
$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Pesan tidak boleh kosong.']);
    exit;
}

try {
    $db->beginTransaction();

    // 1. Tentukan atau buat Percakapan (Conversation)
    if ($conversation_id > 0) {
        $stmtConv = $db->prepare("SELECT * FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1");
        $stmtConv->execute([$conversation_id, $user['id'], $user['id']]);
        $conversation = $stmtConv->fetch();

        if (!$conversation) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Percakapan tidak ditemukan atau Anda tidak memiliki akses.']);
            exit;
        }

        $receiver_id = ((int)$conversation['user_one_id'] === (int)$user['id']) ? (int)$conversation['user_two_id'] : (int)$conversation['user_one_id'];
    } else {
        // Jika conversation_id belum ada, harus ada receiver_id atau provider_id
        if ($provider_id && $receiver_id <= 0) {
            $stmtP = $db->prepare("SELECT user_id FROM service_providers WHERE id = ? LIMIT 1");
            $stmtP->execute([$provider_id]);
            $receiver_id = (int)$stmtP->fetchColumn();
        }

        if ($receiver_id <= 0) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Tujuan penerima pesan tidak valid.']);
            exit;
        }

        if ($receiver_id === (int)$user['id']) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Anda tidak dapat mengirim pesan ke akun sendiri.']);
            exit;
        }

        $u1 = min((int)$user['id'], $receiver_id);
        $u2 = max((int)$user['id'], $receiver_id);

        $stmtFind = $db->prepare("SELECT * FROM conversations WHERE user_one_id = ? AND user_two_id = ? LIMIT 1");
        $stmtFind->execute([$u1, $u2]);
        $conversation = $stmtFind->fetch();

        if ($conversation) {
            $conversation_id = (int)$conversation['id'];
        } else {
            $stmtNew = $db->prepare("
                INSERT INTO conversations (user_one_id, user_two_id, provider_id, last_message, last_message_at, created_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmtNew->execute([$u1, $u2, $provider_id, $message]);
            $conversation_id = (int)$db->lastInsertId();
        }
    }

    // 2. Simpan Pesan Baru ke `chat_messages`
    $stmtMsg = $db->prepare("
        INSERT INTO chat_messages (conversation_id, sender_id, receiver_id, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    $stmtMsg->execute([$conversation_id, $user['id'], $receiver_id, $message]);
    $message_id = (int)$db->lastInsertId();

    // 3. Perbarui `conversations.last_message` dan `last_message_at`
    $stmtUpd = $db->prepare("UPDATE conversations SET last_message = ?, last_message_at = NOW() WHERE id = ?");
    $stmtUpd->execute([$message, $conversation_id]);

    // 4. Kirim Notifikasi Lonceng ke Penerima
    $preview = mb_substr($message, 0, 75) . (mb_strlen($message) > 75 ? '...' : '');
    $senderName = $user['name'];
    
    // Cek apakah pengirim adalah mitra penyedia jasa
    $stmtSP = $db->prepare("SELECT business_name FROM service_providers WHERE user_id = ? LIMIT 1");
    $stmtSP->execute([$user['id']]);
    $provName = $stmtSP->fetchColumn();
    if ($provName) {
        $senderName = $provName;
    }

    $notifTitle = "Pesan Obrolan Baru dari {$senderName}";
    $notifMsg = "\"{$preview}\"";
    $notifLink = "/chat.php?c={$conversation_id}";

    $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmtNotif->execute([$receiver_id, $notifTitle, $notifMsg, $notifLink]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pesan berhasil dikirim.',
        'data' => [
            'id' => $message_id,
            'conversation_id' => $conversation_id,
            'sender_id' => (int)$user['id'],
            'receiver_id' => $receiver_id,
            'message' => $message,
            'time' => date('H:i'),
            'date' => date('d M Y'),
            'is_me' => true
        ]
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal mengirim pesan: ' . $e->getMessage()]);
}

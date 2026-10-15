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

    // 5. Kirim Notifikasi WhatsApp Otomatis ke Penerima (Real-Time Alert)
    try {
        $stmtRec = $db->prepare("
            SELECT u.id, u.name, u.phone, sp.business_name 
            FROM users u 
            LEFT JOIN service_providers sp ON sp.user_id = u.id 
            WHERE u.id = ? 
            LIMIT 1
        ");
        $stmtRec->execute([$receiver_id]);
        $recUser = $stmtRec->fetch(PDO::FETCH_ASSOC);

        if ($recUser && !empty($recUser['phone'])) {
            $recPhone = trim($recUser['phone']);
            $recName = !empty($recUser['business_name']) ? $recUser['business_name'] : $recUser['name'];
            $isReceiverProvider = !empty($recUser['business_name']);

            // Cek cooldown: Apakah sudah ada notifikasi WA chat ke nomor ini dalam 10 menit terakhir?
            $cleanRecPhone = preg_replace('/[^0-9]/', '', $recPhone);
            $phoneSuffix = substr($cleanRecPhone, -8);

            $stmtCheckCooldown = $db->prepare("
                SELECT id FROM whatsapp_logs 
                WHERE recipient_phone LIKE ? 
                  AND event_type = 'chat_message' 
                  AND created_at >= (NOW() - INTERVAL 10 MINUTE)
                LIMIT 1
            ");
            $stmtCheckCooldown->execute(['%' . $phoneSuffix]);
            $isCooldown = (bool)$stmtCheckCooldown->fetchColumn();

            if (!$isCooldown) {
                $appUrl = rtrim(defined('APP_URL') ? APP_URL : 'https://jasainhu.gafahrandi.my.id', '/');
                $chatUrl = "{$appUrl}/chat.php?c={$conversation_id}";

                $senderPhone = $user['phone'] ?? '';
                $cleanSenderPhone = preg_replace('/[^0-9]/', '', $senderPhone);
                if (str_starts_with($cleanSenderPhone, '0')) {
                    $cleanSenderPhone = '62' . substr($cleanSenderPhone, 1);
                } elseif (str_starts_with($cleanSenderPhone, '8')) {
                    $cleanSenderPhone = '62' . $cleanSenderPhone;
                }

                // Potong pesan jika terlalu panjang untuk WA preview
                $waMsgContent = mb_strlen($message) > 250 ? mb_substr($message, 0, 247) . '...' : $message;

                if ($isReceiverProvider) {
                    $waMsg = "🔔 *Pesan Masuk di JASA INHU*\n\n"
                           . "Halo *{$recName}*,\n"
                           . "Ada calon pelanggan yang mengirim pesan obrolan untuk Anda di aplikasi JASA INHU:\n\n"
                           . "💬 _\"{$waMsgContent}\"_\n\n"
                           . "👉 *Buka & Balas di Aplikasi:*\n"
                           . "{$chatUrl}\n\n"
                           . "🛡️ *Peringatan:* Harap sepakati pesanan melalui sistem resmi JASA INHU untuk jaminan keamanan dan garansi Anda.\n\n"
                           . "— *Sistem Resmi JASA INHU*";
                } else {
                    $waMsg = "🔔 *Pesan Masuk di JASA INHU*\n\n"
                           . "Halo *{$recName}*,\n"
                           . "Mitra *{$senderName}* telah membalas pesan obrolan Anda di JASA INHU:\n\n"
                           . "💬 _\"{$waMsgContent}\"_\n\n"
                           . "👉 *Buka Obrolan Sekarang:*\n"
                           . "{$chatUrl}\n\n"
                           . "— *Sistem Resmi JASA INHU*";
                }

                send_wa_notification($recPhone, $waMsg, 'chat_message', null, $recName);
            }
        }
    } catch (Throwable $waEx) {
        error_log("WA Chat Notification Error: " . $waEx->getMessage());
    }

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

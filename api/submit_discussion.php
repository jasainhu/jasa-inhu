<?php
/**
 * API: Pengiriman Pertanyaan & Tanggapan Diskusi Layanan (Q&A)
 * JASA INHU
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
        'message' => 'Silakan masuk ke akun Anda terlebih dahulu untuk bertanya atau membalas diskusi.'
    ]);
    exit;
}

$user = current_user();
$db = get_db();

if (!validate_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Sesi keamanan berakhir. Silakan muat ulang halaman.']);
    exit;
}

$provider_id = (int)($_POST['provider_id'] ?? 0);
$parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$message = trim($_POST['message'] ?? '');

if ($provider_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Penyedia jasa tidak valid.']);
    exit;
}

if (empty($message) || mb_strlen($message) < 5) {
    echo json_encode(['success' => false, 'message' => 'Pesan pertanyaan atau tanggapan minimal 5 karakter.']);
    exit;
}

try {
    // 1. Ambil data provider
    $stmtProv = $db->prepare("
        SELECT sp.*, u.id as owner_user_id, u.name as owner_name 
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.id = ?
        LIMIT 1
    ");
    $stmtProv->execute([$provider_id]);
    $provider = $stmtProv->fetch();

    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Profil penyedia jasa tidak ditemukan.']);
        exit;
    }

    $is_provider_reply = ((int)$provider['owner_user_id'] === (int)$user['id']) ? 1 : 0;

    // 2. Jika ini adalah balasan (parent_id tidak null), validasi pertanyaan induk
    $parent_discussion = null;
    if ($parent_id !== null) {
        $stmtParent = $db->prepare("SELECT * FROM provider_discussions WHERE id = ? AND provider_id = ? LIMIT 1");
        $stmtParent->execute([$parent_id, $provider_id]);
        $parent_discussion = $stmtParent->fetch();

        if (!$parent_discussion) {
            echo json_encode(['success' => false, 'message' => 'Pertanyaan induk tidak ditemukan.']);
            exit;
        }
    }

    $db->beginTransaction();

    // 3. Simpan diskusi
    $stmtIns = $db->prepare("
        INSERT INTO provider_discussions (provider_id, user_id, parent_id, message, is_provider_reply, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmtIns->execute([$provider_id, $user['id'], $parent_id, $message, $is_provider_reply]);
    $discussion_id = (int)$db->lastInsertId();

    // 4. Notifikasi
    if ($parent_id !== null && $parent_discussion) {
        // Balasan: Beritahu penanya awal jika yang membalas adalah orang lain
        if ((int)$parent_discussion['user_id'] !== (int)$user['id']) {
            $senderLabel = $is_provider_reply ? $provider['business_name'] : $user['name'];
            $notifTitle = "Tanggapan Baru untuk Pertanyaan Anda";
            $preview = mb_substr($message, 0, 80) . (mb_strlen($message) > 80 ? '...' : '');
            $notifMsg = "{$senderLabel} membalas: \"{$preview}\"";
            $link = "/provider_detail.php?id={$provider_id}#disc-{$parent_id}";

            $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmtNotif->execute([$parent_discussion['user_id'], $notifTitle, $notifMsg, $link]);
        }
    } else {
        // Pertanyaan baru: Beritahu pemilik usaha (mitra) jika penanya bukan pemilik sendiri
        if ((int)$provider['owner_user_id'] !== (int)$user['id']) {
            $notifTitle = "Pertanyaan Baru di Profil Usaha Anda";
            $preview = mb_substr($message, 0, 80) . (mb_strlen($message) > 80 ? '...' : '');
            $notifMsg = "{$user['name']} bertanya: \"{$preview}\"";
            $link = "/provider_detail.php?id={$provider_id}#disc-{$discussion_id}";

            $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmtNotif->execute([$provider['owner_user_id'], $notifTitle, $notifMsg, $link]);
        }
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => $parent_id ? 'Tanggapan Anda berhasil dikirim!' : 'Pertanyaan Anda berhasil dikirim ke mitra jasa!',
        'discussion' => [
            'id' => $discussion_id,
            'parent_id' => $parent_id,
            'user_name' => $user['name'],
            'is_provider_reply' => $is_provider_reply,
            'message' => $message,
            'created_at' => date('d M Y, H:i')
        ]
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal mengirim diskusi: ' . $e->getMessage()]);
}

<?php
/**
 * API: Pemesanan Jasa Cepat Langsung (1-Click Instant Order)
 * JASA INHU
 */
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

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
    echo json_encode([
        'success'    => false,
        'need_login' => true,
        'message'    => 'Silakan masuk ke akun Anda terlebih dahulu untuk memesan jasa.'
    ]);
    exit;
}

$user = current_user();
$db = get_db();

if (!validate_csrf()) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi keamanan berakhir. Silakan muat ulang halaman.'
    ]);
    exit;
}

$provider_id = (int)($_POST['provider_id'] ?? 0);
$problem = trim($_POST['problem'] ?? '');
$address = trim($_POST['address'] ?? '');
$preferred_time = trim($_POST['preferred_time'] ?? 'Hari ini / Segera');
$order_for = trim($_POST['order_for'] ?? 'self');
$recipient_name = trim($_POST['recipient_name'] ?? '');
$recipient_phone = trim($_POST['recipient_phone'] ?? '');
$maps_url = trim($_POST['maps_url'] ?? '');
$budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;

if ($provider_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Penyedia jasa tidak valid.']);
    exit;
}

if (empty($problem)) {
    echo json_encode(['success' => false, 'message' => 'Mohon jelaskan apa yang perlu dikerjakan atau diservis.']);
    exit;
}

try {
    // Ambil data penyedia jasa
    $stmtProv = $db->prepare("
        SELECT sp.*, u.phone as provider_phone, u.name as provider_owner_name, sc.name as category_name
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        JOIN service_categories sc ON sp.primary_category_id = sc.id
        WHERE sp.id = ? AND u.is_active = 1
        LIMIT 1
    ");
    $stmtProv->execute([$provider_id]);
    $provider = $stmtProv->fetch();

    if (!$provider) {
        echo json_encode(['success' => false, 'message' => 'Mitra penyedia jasa tidak ditemukan atau sedang tidak aktif.']);
        exit;
    }

    if ((int)$provider['user_id'] === (int)$user['id']) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak dapat memesan jasa ke usaha Anda sendiri.']);
        exit;
    }

    $db->beginTransaction();

    $title = mb_substr($problem, 0, 100);
    $full_desc = "Kebutuhan / Keluhan:\n" . $problem;

    if ($order_for === 'other' && !empty($recipient_name)) {
        $full_desc .= "\n\n[Pemesanan untuk Kerabat / Orang Lain]";
        $full_desc .= "\nPenerima di Lokasi: " . $recipient_name . (!empty($recipient_phone) ? " (" . $recipient_phone . ")" : "");
        $full_desc .= "\nAkun Pemesan: " . $user['name'] . " (" . $user['phone'] . ")";
    }

    if (!empty($address)) {
        $full_desc .= "\n\nAlamat & Patokan:\n" . $address;
    }
    if (!empty($maps_url)) {
        $full_desc .= "\nTitik Navigasi GPS: " . $maps_url;
    }
    if (!empty($preferred_time)) {
        $full_desc .= "\n\nWaktu yang Diinginkan:\n" . $preferred_time;
    }

    $district_id = (int)($provider['district_id'] ?: ($user['district_id'] ?? 1));

    // Simpan ke service_requests
    $stmtIns = $db->prepare("
        INSERT INTO service_requests 
        (user_id, category_id, provider_id, district_id, title, description, address_detail, budget, urgency, status, progress_step, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'normal', 'open', 'created', NOW())
    ");
    $stmtIns->execute([
        $user['id'],
        $provider['primary_category_id'],
        $provider['id'],
        $district_id,
        $title,
        $full_desc,
        $address,
        $budget
    ]);
    $request_id = (int)$db->lastInsertId();

    // Catat ke Timeline Garis Waktu Pesanan
    add_order_timeline_event(
        $request_id,
        'created',
        'Pesanan Dibuat & Menunggu Respon Mitra',
        "Pelanggan {$user['name']} memesan jasa: '{$title}'. Menunggu konfirmasi ketersediaan dari mitra.",
        'customer'
    );

    // Masukkan kartu pesan resmi ke percakapan chat jika percakapan aktif
    $stmtFindConv = $db->prepare("
        SELECT id FROM conversations 
        WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?) 
        LIMIT 1
    ");
    $u1 = min((int)$user['id'], (int)$provider['user_id']);
    $u2 = max((int)$user['id'], (int)$provider['user_id']);
    $stmtFindConv->execute([$u1, $u2, $u1, $u2]);
    $convId = (int)$stmtFindConv->fetchColumn();

    if ($convId > 0) {
        $cardMsg = "📋 *[PESANAN RESMI DIBUAT]*\n"
                 . "No. Pesanan: #{$request_id}\n"
                 . "Layanan: {$title}\n"
                 . ($budget ? "Biaya Disepakati: " . format_rupiah($budget) . "\n" : "")
                 . "Lokasi: " . ($address ?: 'Sesuai kesepakatan') . "\n"
                 . "Waktu: {$preferred_time}\n"
                 . "Status: Menunggu Konfirmasi Mitra";

        $stmtMsg = $db->prepare("
            INSERT INTO chat_messages (conversation_id, sender_id, receiver_id, message, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $stmtMsg->execute([$convId, $user['id'], $provider['user_id'], $cardMsg]);

        $stmtUpdC = $db->prepare("UPDATE conversations SET last_message = ?, last_message_at = NOW() WHERE id = ?");
        $stmtUpdC->execute([$cardMsg, $convId]);
    }

    // Notifikasi ke Mitra
    $notifTitle = "Pesanan Jasa Baru Langsung Masuk!";
    $notifMsg = "Warga " . $user['name'] . " memesan jasa: '" . $title . "'.";
    $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmtNotif->execute([$provider['user_id'], $notifTitle, $notifMsg, '/provider/index.php']);

    // Kirim notifikasi WhatsApp otomatis ke HP Mitra jika nomor tersedia & gateway aktif
    if (!empty($provider['provider_phone'])) {
        $autoWaMsg = "🔔 *PESANAN JASA BARU DARI WARGA!* (No: #{$request_id})\n\n" .
                     "Halo *{$provider['business_name']}*, ada pesanan baru dari *{$user['name']}*:\n" .
                     "📌 *Layanan*: {$title}\n" .
                     ($budget ? "💰 *Biaya Disepakati*: " . format_rupiah($budget) . "\n" : "") .
                     "📍 *Lokasi*: " . ($address ?: 'Sesuai kesepakatan') . "\n" .
                     "⏰ *Waktu*: {$preferred_time}\n\n" .
                     "👉 *Buka & Konfirmasi Pesanan*:\n" .
                     BASE_URL . "/provider/index.php\n\n" .
                     "— *Sistem Resmi JASA INHU*";
        send_wa_notification($provider['provider_phone'], $autoWaMsg, 'new_order', $request_id, $provider['business_name']);
    }

    $db->commit();

    // Buat format pesan WhatsApp resmi ke nomor mitra
    $cleanPhone = preg_replace('/[^0-9]/', '', $provider['provider_phone']);
    if (str_starts_with($cleanPhone, '0')) {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    } elseif (!str_starts_with($cleanPhone, '62')) {
        $cleanPhone = '62' . $cleanPhone;
    }

    $targetName = ($order_for === 'other' && !empty($recipient_name)) ? $recipient_name . " (Dipesankan oleh {$user['name']})" : $user['name'];
    $targetPhone = ($order_for === 'other' && !empty($recipient_phone)) ? $recipient_phone : $user['phone'];

    $waMessage = 
        "Halo *{$provider['business_name']}*, saya memesan jasa Anda via platform *JASA INHU* (No. Pesanan: #{$request_id}):\n\n" .
        "👤 *Penerima Jasa*: {$targetName}\n" .
        "📞 *No. Kontak WhatsApp*: {$targetPhone}\n" .
        "🔧 *Kebutuhan Jasa*: {$problem}\n" .
        "📍 *Alamat/Lokasi*: " . ($address ?: 'Sesuai kesepakatan') . "\n";
    
    if (!empty($maps_url)) {
        $waMessage .= "🗺️ *Titik GPS Navigasi*: {$maps_url}\n";
    }

    $waMessage .= "⏰ *Waktu*: {$preferred_time}\n\n" .
        "Mohon konfirmasi ketersediaan Anda. Terima kasih!";

    $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($waMessage);

    echo json_encode([
        'success'       => true,
        'message'       => "Pesanan berhasil dikirim ke {$provider['business_name']}!",
        'request_id'    => $request_id,
        'provider_id'   => $provider_id,
        'provider_name' => $provider['business_name'],
        'chat_url'      => BASE_URL . "/chat.php?provider_id={$provider_id}",
        'track_url'     => BASE_URL . "/user/requests.php?id={$request_id}",
        'wa_url'        => $waUrl
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memproses pesanan: ' . $e->getMessage()
    ]);
}

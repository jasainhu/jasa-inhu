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
    if (!empty($address)) {
        $full_desc .= "\n\nAlamat & Patokan:\n" . $address;
    }
    if (!empty($preferred_time)) {
        $full_desc .= "\n\nWaktu yang Diinginkan:\n" . $preferred_time;
    }

    $district_id = (int)($provider['district_id'] ?: ($user['district_id'] ?? 1));

    // Simpan ke service_requests
    $stmtIns = $db->prepare("
        INSERT INTO service_requests 
        (user_id, category_id, provider_id, district_id, title, description, address_detail, urgency, status, progress_step, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'normal', 'open', 'created', NOW())
    ");
    $stmtIns->execute([
        $user['id'],
        $provider['primary_category_id'],
        $provider['id'],
        $district_id,
        $title,
        $full_desc,
        $address
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

    // Notifikasi ke Mitra
    $notifTitle = "Pesanan Jasa Baru Langsung Masuk!";
    $notifMsg = "Warga " . $user['name'] . " memesan jasa: '" . $title . "'.";
    $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmtNotif->execute([$provider['user_id'], $notifTitle, $notifMsg, '/provider/index.php']);

    $db->commit();

    // Buat format pesan WhatsApp resmi ke nomor mitra
    $cleanPhone = preg_replace('/[^0-9]/', '', $provider['provider_phone']);
    if (str_starts_with($cleanPhone, '0')) {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    } elseif (!str_starts_with($cleanPhone, '62')) {
        $cleanPhone = '62' . $cleanPhone;
    }

    $waMessage = 
        "Halo *{$provider['business_name']}*, saya memesan jasa Anda via platform *JASA INHU* (No. Pesanan: #{$request_id}):\n\n" .
        "👤 *Pemesan*: {$user['name']}\n" .
        "📞 *No. Kontak*: {$user['phone']}\n" .
        "🔧 *Kebutuhan Jasa*: {$problem}\n" .
        "📍 *Alamat/Patokan*: " . ($address ?: 'Sesuai kesepakatan') . "\n" .
        "⏰ *Waktu*: {$preferred_time}\n\n" .
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

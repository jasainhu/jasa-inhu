<?php
/**
 * Otomatisasi Broadcast WhatsApp Pagi untuk Seluruh Mitra Aktif JASA INHU
 * Menjalankan sapaan pagi, edukasi share toko, dan portofolio ke mitra.
 * 
 * Penggunaan:
 *   php scripts/broadcast_mitra_morning.php --test          (Kirim preview ke nomor Admin saja)
 *   php scripts/broadcast_mitra_morning.php --now           (Kirim langsung sekarang ke semua mitra)
 *   php scripts/broadcast_mitra_morning.php --at=08:00      (Tunggu otomatis sampai jam 08:00 WIB, lalu kirim)
 */

if (php_sapi_name() !== 'cli') {
    die("Script ini hanya dapat dijalankan melalui CLI / Terminal.\n");
}

date_default_timezone_set('Asia/Jakarta');
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$options = getopt('', ['test', 'now', 'at::']);
$is_test = isset($options['test']);
$is_now = isset($options['now']);
$target_time_str = $options['at'] ?? '08:00';

$db = get_db();

echo "====================================================\n";
echo "   JASA INHU - OTOMATISASI BROADCAST MITRA PAGI     \n";
echo "====================================================\n";
echo "Waktu sekarang: " . date('Y-m-d H:i:s') . " WIB\n";

// 1. Ambil daftar seluruh mitra aktif
$stmt = $db->query("
    SELECT sp.id, sp.business_name, u.name as user_name, u.phone, sp.is_verified, sp.primary_category_id, sc.name as category_name
    FROM service_providers sp 
    JOIN users u ON u.id = sp.user_id 
    LEFT JOIN service_categories sc ON sc.id = sp.primary_category_id
    WHERE u.is_active = 1 
      AND sp.business_name NOT LIKE '[NONAKTIF%'
      AND u.phone IS NOT NULL 
      AND u.phone != ''
    ORDER BY sp.id ASC
");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Mitra Aktif Terdeteksi: " . count($providers) . " mitra.\n";

if ($is_test) {
    echo "\n[MODE TEST]: Pesan hanya akan dikirimkan ke nomor Admin sebagai simulasi.\n";
    $adminPhone = '085126241679'; // Nomor Admin Angga Fahrandi
    $sampleProv = $providers[0] ?? [
        'id' => 1,
        'business_name' => 'Toko Contoh Jasa Inhu',
        'user_name' => 'Mitra Binaan',
        'phone' => $adminPhone
    ];
    $sampleProv['phone'] = $adminPhone;
    $providers = [$sampleProv];
} elseif (!$is_now) {
    // Mode Menunggu Jam Target (misal 08:00 WIB)
    list($tHour, $tMin) = explode(':', $target_time_str);
    $targetTimestamp = mktime((int)$tHour, (int)$tMin, 0, (int)date('m'), (int)date('d'), (int)date('Y'));
    
    // Jika waktu target hari ini sudah lewat, jadwalkan besok
    if ($targetTimestamp < time()) {
        $targetTimestamp += 86400;
    }

    $waitSecs = $targetTimestamp - time();
    $waitMins = round($waitSecs / 60);

    echo "\nMenunggu hingga jam {$target_time_str} WIB (sekitar {$waitMins} menit lagi)...\n";
    echo "PENTING: Jangan tutup layar laptop / pastikan laptop tetap menyala.\n\n";

    while (time() < $targetTimestamp) {
        $remaining = $targetTimestamp - time();
        $rHours = floor($remaining / 3600);
        $rMins = floor(($remaining % 3600) / 60);
        $rSecs = $remaining % 60;
        printf("\r[%s WIB] Menunggu jam %s WIB... Sisa waktu: %02d jam %02d menit %02d detik   ", 
            date('H:i:s'), $target_time_str, $rHours, $rMins, $rSecs
        );
        sleep(5);
    }
    echo "\n\n>>> Waktu telah tiba! Memulai pengiriman otomatis ke seluruh mitra... <<<\n\n";
}

$appUrl = rtrim(defined('APP_URL') ? APP_URL : 'https://jasainhu.gafahrandi.my.id', '/');
$successCount = 0;
$failCount = 0;
$total = count($providers);

foreach ($providers as $idx => $p) {
    $num = $idx + 1;
    $provName = trim($p['business_name']);
    $ownerName = trim($p['user_name']);
    $phone = trim($p['phone']);
    $storeUrl = "{$appUrl}/provider_detail.php?id={$p['id']}";

    $igUrl = "https://www.instagram.com/reel/DemkadQywIY/";

    $message = "Selamat siang Kak/Pak/Bu *{$ownerName}* (*{$provName}*), salam hormat dari saya selaku pengelola JASA INHU 🙏✨\n\n"
             . "Pertama-tama, saya ingin menyampaikan rasa terima kasih dan apresiasi yang sebesar-besarnya atas kepercayaannya bergabung bersama kami di platform JASA INHU.\n\n"
             . "Alhamdulillah, saat ini sudah ada *50 mitra penyedia jasa hebat* se-Indragiri Hulu yang resmi terdaftar. Ini adalah amanah yang sangat berharga bagi saya pribadi.\n\n"
             . "Saya ingin menyampaikan bahwa *kami terus berjuang setiap hari* untuk mengenalkan wadah ini ke warga se-Inhu agar keahlian dan usaha Bapak/Ibu semakin dikenal luas dan mudah dipanggil pelanggan.\n\n"
             . "Berikut adalah link profil toko resmi usaha Bapak/Ibu:\n"
             . "👉 {$storeUrl}\n\n"
             . "💡 *Saran untuk Bapak/Ibu:* Silakan pasang link toko resmi ini di *Status WhatsApp* masing-masing agar pelanggan setia maupun kenalan baru bisa melihat portofolio dan nomor kontak usaha Bapak/Ibu secara profesional.\n\n"
             . "🎬 *Tonton Video Pengenalan JASA INHU:*\n"
             . "Bagi Bapak/Ibu yang aktif di Instagram, yuk tonton dan bantu Like/Share video Reels terbaru kita di sini ya:\n"
             . "👉 {$igUrl}\n\n"
             . "Mohon simpan nomor WhatsApp Admin ini ya Pak/Bu. Selamat istirahat siang, semoga Allah melancarkan rezeki dan memberikan kesehatan untuk Bapak/Ibu sekeluarga hari ini 💪😊\n\n"
             . "Hormat saya,\n"
             . "— *Admin / Founder JASA INHU*";

    echo "[{$num}/{$total}] Mengirim ke {$provName} ({$phone})... ";

    try {
        $res = send_wa_notification($phone, $message, 'morning_broadcast', null, $provName);
        if ($res) {
            echo "BERHASIL!\n";
            $successCount++;
        } else {
            echo "GAGAL (Cek log)\n";
            $failCount++;
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $failCount++;
    }

    // Jeda 8-10 detik antar nomor demi keamanan reputasi nomor WhatsApp Admin di Fonnte
    if ($num < $total) {
        echo "   Menunggu 10 detik agar aman dari proteksi spam WA...\n";
        sleep(10);
    }
}

echo "\n====================================================\n";
echo "BROADCAST SELESAI!\n";
echo "Sukses: {$successCount} | Gagal: {$failCount} | Total: {$total}\n";
echo "Waktu selesai: " . date('Y-m-d H:i:s') . " WIB\n";
echo "====================================================\n";

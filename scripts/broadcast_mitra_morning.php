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

    $message = "Selamat pagi Kak/Pak/Bu *{$ownerName}* (*{$provName}*), salam hormat dari saya selaku pengelola JASA-INHU 🙏✨\n\n"
             . "Pertama-tama, dari lubuk hati yang paling dalam, saya ingin menyampaikan *permohonan maaf* jika di awal perjalanan ini orderan belum merata atau belum sebanyak yang Bapak/Ibu harapkan.\n\n"
             . "Alhamdulillah, saat ini sudah ada *45+ mitra penyedia jasa hebat* se-Indragiri Hulu yang bergabung bersama kita di JASA-INHU. Ini adalah amanah dan tanggung jawab yang sangat besar bagi saya pribadi.\n\n"
             . "Saya ingin menyampaikan satu hal: *Saya di sini tidak tinggal diam dan terus berjuang.* Siang dan malam saya terus berusaha mengenalkan wadah ini ke warga Inhu dan menghubungi berbagai pihak agar jasa Bapak/Ibu semakin dikenal luas.\n\n"
             . "Perjalanan ini memang tidak mudah dan kita memulainya dari bawah, tapi saya percaya niat baik kita untuk memajukan rezeki keluarga dan UMKM lokal Inhu pasti ada jalannya.\n\n"
             . "Mohon bantuan doa dan dukungannya ya Bapak/Ibu... Jika ada waktu luang pagi ini, yuk bantu pasang link profil toko Bapak/Ibu di Status WhatsApp masing-masing:\n"
             . "👉 {$storeUrl}\n\n"
             . "Satu langkah kecil dari kita semua untuk saling bantu share, insyaAllah akan membuka pintu rezeki bagi kita bersama.\n\n"
             . "Terima kasih banyak atas kesabaran dan kepercayaannya. Selamat beraktivitas di hari Minggu ini, semoga Allah melancarkan rezeki dan memberikan kesehatan untuk Bapak/Ibu sekeluarga hari ini 💪😊\n\n"
             . "Hormat saya,\n"
             . "— *Founder JASA-INHU*";

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

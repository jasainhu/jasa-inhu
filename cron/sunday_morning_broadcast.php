<?php
/**
 * Script Otomatis: Broadcast Semangat Pagi Minggu untuk Mitra JASA INHU
 * Dijalankan via CLI / Scheduler pada jam prima (07:35 WIB)
 * Sesuai rencana awal: pesan teks resmi Fonnte ke 53 Mitra aktif
 */

if (php_sapi_name() !== 'cli' && empty($_GET['secret'])) {
    die("Akses khusus CLI / Internal Admin");
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$startTime = microtime(true);
echo "====================================================\n";
echo " JASA INHU - AUTO BROADCAST PAGI MINGGU (TEKS MITRA)\n";
echo " Waktu Mulai: " . date('Y-m-d H:i:s') . " WIB\n";
echo "====================================================\n\n";

$db = get_db();

// Ambil semua mitra aktif yang memiliki nomor WhatsApp valid
$query = "
    SELECT sp.id as sp_id, u.id, u.name, u.phone, sp.business_name, d.name as district_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN districts d ON sp.district_id = d.id
    WHERE u.is_active = 1 
      AND u.phone IS NOT NULL 
      AND LENGTH(u.phone) >= 9
    ORDER BY sp.id ASC
";
$stmt = $db->query($query);
$rawList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$uniqueRecipients = [];
$seenPhones = [];

foreach ($rawList as $row) {
    $rawPhone = $row['phone'];
    $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
    if (str_starts_with($cleanPhone, '0')) {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    } elseif (str_starts_with($cleanPhone, '8')) {
        $cleanPhone = '62' . $cleanPhone;
    }
    
    if (!isset($seenPhones[$cleanPhone]) && strlen($cleanPhone) >= 10) {
        $seenPhones[$cleanPhone] = true;
        $row['clean_phone'] = $cleanPhone;
        $uniqueRecipients[] = $row;
    }
}

$total = count($uniqueRecipients);
echo "Ditemukan {$total} mitra aktif dengan nomor WA terdaftar.\n\n";

if ($total === 0) {
    echo "Tidak ada mitra yang dapat dikirim pesan.\n";
    exit(0);
}

$sentCount = 0;
$failCount = 0;

foreach ($uniqueRecipients as $idx => $rec) {
    $num = $idx + 1;
    $cleanPhone = $rec['clean_phone'];
    $bizName = !empty($rec['business_name']) ? trim($rec['business_name']) : trim($rec['name']);
    $district = !empty($rec['district_name']) ? 'Kec. ' . $rec['district_name'] : 'Indragiri Hulu';

    $message = "Halo Mitra *" . $bizName . "* di " . $district . ",\n\n"
        . "Semangat Pagi Minggu! 💪⚡\n\n"
        . "Hari Minggu biasanya permintaan jasa rumahan (servis AC, montir panggilan, ledeng/listrik, pertukangan, babat rumput & kebersihan) sedang ramai-ramainya dari warga se-Inhu.\n\n"
        . "💡 *Tips Panen Order Hari Ini:*\n"
        . "1. Pastikan nomor WhatsApp aktif & nada dering HP bersuara nyaring.\n"
        . "2. Balas chat dan panggilan warga dengan cepat & ramah.\n"
        . "3. Berikan pelayanan terbaik agar toko Anda mendapat rating bintang 5!\n\n"
        . "Buka profil & pantau orderan Anda di:\n"
        . "👉 https://jasainhu.gafahrandi.my.id/provider/index.php\n\n"
        . "_Semoga hari ini orderan lancar, berkah dan rezeki melimpah untuk usaha Anda!_ 🤲✨\n\n"
        . "- *Admin JASA INHU*";

    echo "[{$num}/{$total}] Mengirim teks semangat ke {$bizName} ({$cleanPhone})... ";

    $res = send_wa_notification($cleanPhone, $message, 'auto_broadcast_minggu', null, $bizName);
    
    if (($res['status'] ?? '') === 'sent') {
        echo "BERHASIL ✅\n";
        $sentCount++;
    } else {
        $err = is_string($res['response'] ?? '') ? $res['response'] : ($res['message'] ?? 'Gagal');
        echo "GAGAL ❌ ({$err})\n";
        $failCount++;
    }

    // Delay 1.5 detik per pesan agar aman dari rate limiting & anti-banned
    usleep(1500000);
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "\n====================================================\n";
echo " SELESAI DALAM {$elapsed} DETIK\n";
echo " Berhasil Terkirim : {$sentCount}\n";
echo " Gagal             : {$failCount}\n";
echo "====================================================\n";

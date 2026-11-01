<?php
/**
 * Script Otomatis: Broadcast Semangat Pagi Minggu untuk Mitra JASA INHU
 * Dijalankan via CLI / Scheduler pada jam prima (07:35 WIB)
 */

if (php_sapi_name() !== 'cli' && empty($_GET['secret'])) {
    die("Akses khusus CLI / Internal Admin");
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$startTime = microtime(true);
echo "====================================================\n";
echo " JASA INHU - AUTO BROADCAST PAGI MINGGU MITRA\n";
echo " Waktu Mulai: " . date('Y-m-d H:i:s') . " WIB\n";
echo "====================================================\n\n";

$db = get_db();

// Ambil semua mitra aktif yang memiliki nomor WhatsApp valid
$query = "
    SELECT u.id, u.name, u.phone, sp.business_name, d.name as district_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN districts d ON sp.district_id = d.id
    WHERE u.is_active = 1 
      AND u.phone IS NOT NULL 
      AND LENGTH(u.phone) >= 9
    ORDER BY sp.id ASC
";
$stmt = $db->query($query);
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($partners);
echo "Ditemukan {$total} mitra aktif dengan nomor HP terdaftar.\n\n";

if ($total === 0) {
    echo "Tidak ada mitra yang dapat dikirim pesan.\n";
    exit(0);
}

$sentCount = 0;
$failCount = 0;

foreach ($partners as $idx => $p) {
    $num = $idx + 1;
    $rawPhone = $p['phone'];
    $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
    
    // Normalisasi awalan 08 / 62
    if (str_starts_with($cleanPhone, '0')) {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    } elseif (str_starts_with($cleanPhone, '8')) {
        $cleanPhone = '62' . $cleanPhone;
    }

    $bizName = !empty($p['business_name']) ? trim($p['business_name']) : trim($p['name']);
    $district = !empty($p['district_name']) ? 'Kec. ' . $p['district_name'] : 'Indragiri Hulu';

    $message = "Halo Mitra *" . $bizName . "* di " . $district . ",\n\n"
        . "Semangat Pagi Minggu! 💪⚡\n\n"
        . "Hari Minggu biasanya permintaan jasa rumahan (AC, montir, ledeng, pertukangan, babat rumput & kebersihan) sedang ramai dari warga se-Inhu.\n\n"
        . "💡 *Tips Siaga Hari Ini:*\n"
        . "1. Pastikan nomor WhatsApp aktif & notifikasi HP bersuara nyaring.\n"
        . "2. Balas chat dan panggilan warga dengan cepat & ramah.\n"
        . "3. Berikan pelayanan terbaik agar toko Anda mendapat rating bintang 5!\n\n"
        . "Buka profil & pantau orderan Anda di:\n"
        . "👉 https://jasainhu.gafahrandi.my.id/provider/index.php\n\n"
        . "_Semoga hari ini orderan lancar, berkah dan rezeki melimpah untuk usaha Anda!_ 🤲✨\n\n"
        . "- *Admin JASA INHU*";

    echo "[{$num}/{$total}] Mengirim ke {$bizName} ({$cleanPhone})... ";

    $imageUrl = 'https://jasainhu.gafahrandi.my.id/assets/images/story_minggu_santai.jpg';
    $res = send_wa_notification($cleanPhone, $message, 'auto_broadcast_minggu', null, $bizName, $imageUrl);
    
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


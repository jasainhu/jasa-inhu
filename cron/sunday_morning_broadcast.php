<?php
/**
 * Script Otomatis: Broadcast Semangat Pagi Minggu untuk Mitra & Pengguna JASA INHU
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
echo " JASA INHU - AUTO BROADCAST PAGI MINGGU (GAMBAR + PESAN)\n";
echo " Waktu Mulai: " . date('Y-m-d H:i:s') . " WIB\n";
echo "====================================================\n\n";

$db = get_db();

// Ambil semua mitra dan pengguna aktif yang memiliki nomor WhatsApp valid
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
echo "Ditemukan {$total} penerima unik aktif dengan nomor WA terdaftar.\n\n";

if ($total === 0) {
    echo "Tidak ada penerima yang dapat dikirim pesan.\n";
    exit(0);
}

$sentCount = 0;
$failCount = 0;
$imagePath = __DIR__ . '/../assets/images/story_minggu_santai.jpg';
if (!file_exists($imagePath)) {
    $imagePath = 'https://jasainhu.gafahrandi.my.id/assets/images/story_minggu_santai.jpg';
}

foreach ($uniqueRecipients as $idx => $rec) {
    $num = $idx + 1;
    $cleanPhone = $rec['clean_phone'];
    $bizName = !empty($rec['business_name']) ? trim($rec['business_name']) : trim($rec['name']);
    $district = !empty($rec['district_name']) ? 'Kec. ' . $rec['district_name'] : 'Indragiri Hulu';

    $message = "Halo *" . $bizName . "* di " . $district . ",\n\n"
        . "Selamat Pagi Minggu & Selamat Berakhir Pekan! ☕🌿\n\n"
        . "Hari Minggu biasanya permintaan jasa rumahan (servis AC, montir panggilan, pertukangan, ledeng/listrik, kebersihan & babat rumput) sedang ramai-ramainya dari warga se-Inhu.\n\n"
        . "💡 *Tips Panen Order Hari Ini:*\n"
        . "1. Pastikan nomor WhatsApp aktif & nada dering HP bersuara nyaring.\n"
        . "2. Download poster promosi resmi & pasang di *Status / Story WhatsApp* Anda agar tetangga sekitar tahu Anda siap melayani hari ini:\n"
        . "👉 https://jasainhu.gafahrandi.my.id/assets/images/story_minggu_santai.jpg\n"
        . "3. Balas panggilan order warga dengan cepat & ramah untuk dapat rating bintang 5.\n\n"
        . "Buka profil & pantau orderan Anda di:\n"
        . "👉 https://jasainhu.gafahrandi.my.id/provider/index.php\n\n"
        . "Butuh panggil jasa lain untuk urusan rumah sendiri? Cek di:\n"
        . "👉 https://jasainhu.gafahrandi.my.id\n\n"
        . "_Semoga hari ini orderan lancar, berkah dan rezeki melimpah untuk Anda dan keluarga!_ 🤲✨\n\n"
        . "- *Admin JASA INHU*";

    echo "[{$num}/{$total}] Mengirim poster + caption ke {$bizName} ({$cleanPhone})... ";

    $res = send_wa_notification($cleanPhone, $message, 'auto_broadcast_minggu', null, $bizName, $imagePath);
    
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

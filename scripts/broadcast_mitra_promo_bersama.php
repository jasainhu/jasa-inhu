<?php
/**
 * Broadcast Ajakan Promosi Bersama ke Seluruh Mitra JASA INHU
 * Mengajak mitra saling dukung & forward link ke grup keluarga.
 *
 * Penggunaan:
 *   php scripts/broadcast_mitra_promo_bersama.php --test   (Kirim preview ke nomor Admin dulu)
 *   php scripts/broadcast_mitra_promo_bersama.php --now    (Kirim langsung ke seluruh mitra)
 */

if (php_sapi_name() !== 'cli') {
    die("Script ini hanya dapat dijalankan melalui CLI / Terminal.\n");
}

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$options = getopt('', ['test', 'now']);
$is_test = isset($options['test']);
$is_now = isset($options['now']);

$db = get_db();

echo "========================================================\n";
echo "   JASA INHU - BROADCAST AJAKAN PROMOSI BERSAMA MITRA   \n";
echo "========================================================\n";
echo "Waktu: " . date('Y-m-d H:i:s') . " WIB\n";

// 1. Ambil seluruh mitra aktif yang memiliki nomor HP
$stmt = $db->query("
    SELECT sp.id, sp.business_name, u.name as user_name, u.phone
    FROM service_providers sp 
    JOIN users u ON u.id = sp.user_id 
    WHERE u.is_active = 1 
      AND sp.business_name NOT LIKE '[NONAKTIF%'
      AND u.phone IS NOT NULL 
      AND u.phone != ''
    ORDER BY sp.id ASC
");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Mitra Aktif Terdeteksi: " . count($providers) . " nomor.\n";

if ($is_test) {
    echo "\n[MODE TEST]: Pesan hanya dikirim ke nomor Admin untuk pengujian.\n";
    $adminPhone = '085126241679';
    $sampleProv = [
        'id' => 999999,
        'business_name' => 'Admin Jasa Inhu',
        'user_name' => 'Angga Fahrandi',
        'phone' => $adminPhone
    ];
    $providers = [$sampleProv];
} elseif (!$is_now) {
    echo "\nPERINGATAN: Tambahkan flag --now untuk mengirim ke semua mitra, atau --test untuk uji coba ke admin.\n";
    echo "Contoh:\n";
    echo "  php scripts/broadcast_mitra_promo_bersama.php --test\n";
    echo "  php scripts/broadcast_mitra_promo_bersama.php --now\n";
    exit(0);
}

$successCount = 0;
$failCount = 0;
$total = count($providers);

foreach ($providers as $idx => $p) {
    $num = $idx + 1;
    $provName = trim($p['business_name']);
    $ownerName = trim($p['user_name']);
    $phone = trim($p['phone']);

    $message = "Selamat Pagi Rekan Mitra *{$provName}*! ☀️☕\n\n"
             . "Semoga hari Minggu ini membawa berkah dan kelancaran rezeki untuk usaha Bapak/Ibu sekalian.\n\n"
             . "Platform JASA INHU ini kita bangun bersama untuk memajukan tenaga kerja dan usaha lokal di Indragiri Hulu. Kami di tim pengelola terus berjuang agar platform ini makin dikenal luas oleh masyarakat.\n\n"
             . "Tapi kami tidak bisa berjuang sendirian. Kami butuh dukungan dan gotong-royong dari rekan-rekan mitra sekalian. 🤝\n\n"
             . "Makin banyak warga Inhu yang tahu platform ini, tentu makin banyak pintu rezeki dan orderan yang masuk untuk usaha Bapak/Ibu.\n\n"
             . "Bolehkah kami minta tolong sedikit bantuannya untuk meneruskan pesan singkat di bawah ini ke grup keluarga, tetangga, atau kawan-kawan Bapak/Ibu?\n\n"
             . "Satu kali share dari rekan-rekan sangat berarti bagi perjuangan kita bersama. Terima kasih banyak ya! 🙏✨\n\n"
             . "---------------------------------------------\n"
             . "(Bisa langsung diteruskan / forward ke grup) 👇\n\n"
             . "Info penting untuk keluarga & sahabat di Inhu 🙏\n\n"
             . "Sekarang kalau di rumah butuh tukang bangunan, servis AC, pompa air, montir panggilan, las, sampai urut, tak perlu bingung lagi.\n\n"
             . "Sudah ada portal resminya:\n"
             . "👉 https://jasainhu.gafahrandi.my.id\n\n"
             . "Tinggal pilih tukang terdekat di kecamatan kita, langsung terhubung ke WhatsApp. Tolong disimpan link-nya ya, sewaktu-waktu pasti butuh di rumah 😊👍";

    echo "[{$num}/{$total}] Mengirim ke {$provName} ({$phone})... ";

    try {
        $res = send_wa_notification($phone, $message, 'broadcast_promo_bersama', null, $provName);
        if ($res && ($res['status'] ?? '') !== 'failed') {
            echo "BERHASIL!\n";
            $successCount++;
        } else {
            echo "GAGAL (Cek kuota/koneksi gateway)\n";
            $failCount++;
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $failCount++;
    }

    // Jeda 8-10 detik antar pengiriman agar aman dari blokir WhatsApp
    if ($num < $total) {
        echo "   Menunggu jeda 8 detik agar aman dari spam filter...\n";
        sleep(8);
    }
}

echo "\n========================================================\n";
echo "PENGIRIMAN SELESAI!\n";
echo "Sukses: {$successCount} | Gagal: {$failCount} | Total: {$total}\n";
echo "Waktu selesai: " . date('Y-m-d H:i:s') . " WIB\n";
echo "========================================================\n";

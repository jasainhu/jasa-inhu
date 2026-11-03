<?php
/**
 * Broadcast Pesan Edukasi & Pengenalan 50 Mitra ke Pelanggan / Masyarakat Terdaftar di JASA INHU
 */
if (php_sapi_name() !== 'cli') {
    die("Script ini hanya dapat dijalankan melalui CLI / Terminal.\n");
}

date_default_timezone_set('Asia/Jakarta');
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = get_db();

echo "====================================================\n";
echo "   JASA INHU - BROADCAST EDUKASI KE PELANGGAN       \n";
echo "====================================================\n";
echo "Waktu sekarang: " . date('Y-m-d H:i:s') . " WIB\n";

// Ambil seluruh akun dengan role pengguna (pelanggan/masyarakat) yang aktif & punya nomor telepon
$stmt = $db->query("
    SELECT u.id, u.name, u.phone, u.email 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE r.name = 'pengguna' 
      AND u.is_active = 1 
      AND u.phone IS NOT NULL 
      AND u.phone != ''
    ORDER BY u.id ASC
");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($customers);
echo "Total Pelanggan Terdaftar Terdeteksi: {$total} pelanggan.\n\n";

if ($total === 0) {
    echo "Tidak ada pelanggan yang memenuhi kriteria.\n";
    exit(0);
}

$appUrl = rtrim(defined('APP_URL') ? APP_URL : 'https://jasainhu.gafahrandi.my.id', '/');
$successCount = 0;
$failCount = 0;

foreach ($customers as $idx => $c) {
    $num = $idx + 1;
    $custName = trim($c['name']) ?: 'Kak';
    $phone = trim($c['phone']);

    $profileUrl = "{$appUrl}/user/profile.php";

    $message = "Halo Kak *{$custName}*, selamat pagi! Salam hangat dari Tim JASA INHU 🙏✨\n\n"
             . "Sekadar mengabarkan, alhamdulillah saat ini sudah ada *54 mitra penyedia jasa terpercaya* se-Indragiri Hulu yang siap melayani kebutuhan Kakak dan keluarga di rumah (mulai dari Servis AC, Montir, Tukang Bangunan, Babat Rumput, hingga Servis Elektronik).\n\n"
             . "💡 *PENTING: LENGKAPI PROFIL & ALAMAT RUMAH KAKAK*\n"
             . "Agar saat Kakak sewaktu-waktu butuh bantuan jasa mendadak teknisi bisa *langsung meluncur cepat ke lokasi tanpa tersesat*, yuk pastikan data alamat dan patokan rumah Kakak sudah lengkap:\n\n"
             . "👉 *Lengkapi Profil di Sini:*\n"
             . "{$profileUrl}\n\n"
             . "Kakak juga bisa langsung menjelajahi katalog jasa dan hasil kerja nyata mitra di:\n"
             . "🌐 {$appUrl}\n\n"
             . "Simpan nomor WhatsApp Admin ini ya Kak, jika ada kendala atau butuh rekomendasi tukang terdekat di Inhu, kami siap membantu 24 jam. Semoga hari Kakak menyenangkan! 😊🙏\n\n"
             . "— *Tim Layanan JASA INHU*";

    echo "[{$num}/{$total}] Mengirim ke {$custName} ({$phone})... ";

    try {
        $res = send_wa_notification($phone, $message, 'customer_broadcast', null, $custName);
        if ($res && !empty($res['success'])) {
            echo "BERHASIL!\n";
            $successCount++;
        } else {
            echo "GAGAL: " . ($res['message'] ?? 'Unknown error') . "\n";
            $failCount++;
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $failCount++;
    }

    // Jeda 8-10 detik antar nomor demi keamanan reputasi WhatsApp
    if ($num < $total) {
        echo "   Menunggu 10 detik...\n";
        sleep(10);
    }
}

echo "\n====================================================\n";
echo "BROADCAST PELANGGAN SELESAI!\n";
echo "Sukses: {$successCount} | Gagal: {$failCount} | Total: {$total}\n";
echo "Waktu selesai: " . date('Y-m-d H:i:s') . " WIB\n";
echo "====================================================\n";


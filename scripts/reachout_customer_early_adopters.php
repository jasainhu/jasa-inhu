<?php
/**
 * Script Reachout Personal ke Early Adopter Customer (Masyarakat Terdaftar)
 * Mengirimkan pesan ramah & personal dari Tim Resmi JASA INHU via WhatsApp Gateway.
 * 
 * Penggunaan:
 *   php scripts/reachout_customer_early_adopters.php --test    (Simulasi kirim ke nomor Admin)
 *   php scripts/reachout_customer_early_adopters.php --now     (Kirim langsung ke seluruh customer)
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

if (!$is_test && !$is_now) {
    echo "Pilih opsi: --test (simulasi ke admin) atau --now (kirim langsung sekarang)\n";
    exit;
}

$db = get_db();

echo "====================================================\n";
echo "   JASA INHU - REACHOUT CUSTOMER / MASYARAKAT       \n";
echo "====================================================\n";
echo "Waktu sekarang: " . date('Y-m-d H:i:s') . " WIB\n";

// 1. Ambil data pelanggan (non-mitra)
$stmt = $db->query("
    SELECT u.id, u.name, u.phone, u.created_at 
    FROM users u 
    LEFT JOIN service_providers sp ON sp.user_id = u.id 
    WHERE sp.id IS NULL 
      AND u.id != 1 
      AND u.phone IS NOT NULL 
      AND u.phone != ''
    ORDER BY u.id ASC
");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Customer Terdeteksi: " . count($customers) . " orang.\n";

$adminPhone = '085126241679';

if ($is_test) {
    echo "\n[MODE TEST]: Pesan hanya akan dikirimkan ke nomor Admin ({$adminPhone}) sebagai simulasi.\n";
    $sample = $customers[0] ?? [
        'id' => 999,
        'name' => 'Medi Apriandika',
        'phone' => $adminPhone
    ];
    $sample['phone'] = $adminPhone;
    $customers = [$sample];
}

$successCount = 0;
$failCount = 0;
$total = count($customers);

foreach ($customers as $idx => $c) {
    $num = $idx + 1;
    $fullName = trim($c['name']);
    $phone = trim($c['phone']);

    // Ambil nama panggilan (kata pertama), huruf kapital rapi
    $nameParts = explode(' ', $fullName);
    $firstName = ucfirst(strtolower($nameParts[0]));
    if (empty($firstName)) {
        $firstName = 'Kak';
        $greetingName = 'Kak';
    } else {
        $greetingName = "Kak {$firstName}";
    }

    $message = "Halo {$greetingName}, salam hangat dari Tim Layanan *JASA-INHU* 🙏✨\n\n"
             . "Terima kasih banyak ya Kak sudah mampir dan mendaftar di portal JASA-INHU beberapa waktu lalu.\n\n"
             . "Kami ingin menyapa santai nih Kak... Waktu membuka website kemarin, apakah Kakak sedang mencari tukang atau jasa harian tertentu di wilayah Inhu?\n\n"
             . "Siapa tahu kemarin sempat ada kendala atau belum menemukan penyedia jasa yang dicari. Jika saat ini Kakak ada kebutuhan perbaikan, servis, renovasi, atau jasa lainnya, Tim JASA INHU siap bantu rekomendasikan dan hubungkan langsung dengan mitra terpercaya yang paling dekat dengan lokasi Kakak (gratis tanpa biaya perantara).\n\n"
             . "Kira-kira kemarin lagi berencana butuh jasa apa ya Kak kalau boleh tahu? 😊\n\n"
             . "— *Tim Layanan JASA-INHU*\n"
             . "🌐 https://jasainhu.gafahrandi.my.id";

    echo "[{$num}/{$total}] Mengirim ke {$greetingName} ({$fullName} - {$phone})... ";

    try {
        $res = send_wa_notification($phone, $message, 'customer_reachout', null, $fullName);
        if ($res && isset($res['status']) && in_array($res['status'], ['sent', 'simulated', 'success'])) {
            echo "BERHASIL!\n";
            $successCount++;
        } else {
            echo "GAGAL (Cek respon gateway)\n";
            $failCount++;
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $failCount++;
    }

    // Jeda 8 detik antar nomor agar aman dan natural
    if ($num < $total) {
        echo "   Menunggu 8 detik...\n";
        sleep(8);
    }
}

echo "\n====================================================\n";
echo "PENGIRIMAN SELESAI!\n";
echo "Sukses: {$successCount} | Gagal: {$failCount} | Total: {$total}\n";
echo "Waktu selesai: " . date('Y-m-d H:i:s') . " WIB\n";
echo "====================================================\n";

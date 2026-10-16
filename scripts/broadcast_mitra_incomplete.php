<?php
/**
 * Otomatisasi Broadcast WhatsApp untuk Mitra yang Profilnya Belum Lengkap
 * (Belum pasang Foto Toko / Portofolio Hasil Kerja / Bio / Verifikasi)
 * 
 * Penggunaan:
 *   php scripts/broadcast_mitra_incomplete.php --test          (Kirim preview ke nomor Admin)
 *   php scripts/broadcast_mitra_incomplete.php --now           (Kirim ke semua mitra yang profilnya belum lengkap)
 *   php scripts/broadcast_mitra_incomplete.php --only-no-photo (Hanya kirim ke mitra yang belum pasang foto profil)
 */

if (php_sapi_name() !== 'cli') {
    die("Script ini hanya dapat dijalankan melalui CLI / Terminal.\n");
}

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$options = getopt('', ['test', 'now', 'only-no-photo']);
$is_test = isset($options['test']);
$is_now = isset($options['now']);
$only_no_photo = isset($options['only-no-photo']);

$db = get_db();

echo "====================================================\n";
echo "   JASA INHU - BROADCAST KELENGKAPAN PROFIL MITRA    \n";
echo "====================================================\n";
echo "Waktu: " . date('Y-m-d H:i:s') . " WIB\n\n";

$sql = "
    SELECT 
        sp.id,
        sp.business_name,
        u.name as owner_name,
        u.phone,
        sp.image_url,
        sp.headline,
        sp.description,
        sp.id_card_image,
        sp.is_verified,
        sc.name as category_name,
        (SELECT COUNT(*) FROM provider_portfolios pp WHERE pp.provider_id = sp.id) as portfolio_count
    FROM service_providers sp
    JOIN users u ON u.id = sp.user_id
    LEFT JOIN service_categories sc ON sc.id = sp.primary_category_id
    WHERE u.is_active = 1
      AND sp.business_name NOT LIKE '[NONAKTIF%'
      AND u.phone IS NOT NULL AND u.phone != ''
    ORDER BY sp.id ASC
";

$all_providers = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$targets = [];

foreach ($all_providers as $p) {
    $missing = [];
    $has_photo = (!empty($p['image_url']) && strpos($p['image_url'], 'default') === false);
    
    if (!$has_photo) {
        $missing[] = "Foto Toko / Logo Usaha";
    }
    
    if ((int)$p['portfolio_count'] === 0) {
        $missing[] = "Foto Portofolio / Hasil Pekerjaan";
    }
    
    if (empty($p['description']) || strlen(trim($p['description'])) < 15) {
        $missing[] = "Deskripsi / Penjelasan Layanan";
    }

    if ($only_no_photo) {
        if (!$has_photo) {
            $p['missing_items'] = $missing;
            $targets[] = $p;
        }
    } else {
        // Jika ada salah satu yang belum lengkap (foto toko atau portofolio)
        if (!$has_photo || (int)$p['portfolio_count'] === 0) {
            $p['missing_items'] = $missing;
            $targets[] = $p;
        }
    }
}

echo "Total Mitra Membutuhkan Kelengkapan: " . count($targets) . " mitra.\n";

$appUrl = rtrim(defined('APP_URL') ? APP_URL : 'https://jasainhu.gafahrandi.my.id', '/');

$adminPhone = '085126241679'; // Nomor Admin Angga

if ($is_test) {
    echo "\n[MODE TEST PREVIEW]: Pesan dikirim ke nomor Admin untuk pengecekan.\n";
    $sample = $targets[0] ?? [
        'id' => 90013,
        'business_name' => 'Contoh Toko Mitra',
        'owner_name' => 'Mitra Teladan',
        'phone' => $adminPhone,
        'missing_items' => ['Foto Toko / Logo Usaha', 'Foto Portofolio / Hasil Pekerjaan']
    ];
    $sample['phone'] = $adminPhone;
    $targets = [$sample];
} elseif (!$is_now) {
    echo "\nJALANKAN DENGAN PARAMETER:\n";
    echo "  --test            : Kirim uji coba ke WA Admin ({$adminPhone})\n";
    echo "  --now             : Kirim sekarang ke SEMUA " . count($targets) . " mitra yang belum lengkap\n";
    echo "  --only-no-photo   : Hanya kirim ke mitra yang belum pasang foto profil\n\n";
    
    echo "Daftar Mitra Target:\n";
    foreach ($targets as $i => $t) {
        echo ($i + 1) . ". {$t['business_name']} ({$t['phone']}) -> Kurang: " . implode(', ', $t['missing_items']) . "\n";
    }
    exit(0);
}

$successCount = 0;
$failCount = 0;
$total = count($targets);

foreach ($targets as $idx => $p) {
    $num = $idx + 1;
    $provName = trim($p['business_name']);
    $ownerName = trim($p['owner_name']);
    $phone = trim($p['phone']);
    $storeUrl = "{$appUrl}/provider_detail.php?id={$p['id']}";
    $loginUrl = "{$appUrl}/provider/profile.php";

    $missingList = "";
    foreach ($p['missing_items'] as $item) {
        $missingList .= "  ❌ *{$item}*\n";
    }

    $message = "Halo Kak/Pak/Bu *{$ownerName}* (*{$provName}*), salam hangat dari Tim Komunitas *JASA INHU* 🙏✨\n\n"
             . "Kabar baik! Saat ini JASA INHU sedang gencar promosi besar-besaran di media sosial (TikTok, Instagram, & WhatsApp) untuk mendatangkan pelanggan ke toko mitra di Indragiri Hulu.\n\n"
             . "Setelah kami cek profil toko Anda di:\n"
             . "👉 {$storeUrl}\n\n"
             . "Ternyata masih ada beberapa data penting yang belum terpasang:\n"
             . "{$missingList}\n"
             . "💡 *Kenapa foto sangat penting?*\n"
             . "Pelanggan 8x lebih sering memilih dan memesan jasa yang ada foto hasil kerjanya dibandingkan profil yang masih kosong.\n\n"
             . "👉 *Cara Lengkapi (Sangat Mudah):*\n"
             . "1. Buka akun Anda di: {$loginUrl}\n"
             . "2. Masukkan foto terbaik Anda / foto saat sedang bekerja.\n\n"
             . "🤝 *Mau Dibantu Tim Admin? Gratis!*\n"
             . "Kakak cukup *balas pesan WhatsApp ini* dan kirimkan 2-3 foto terbaik hasil kerja/layanan toko Anda, biar tim admin kami yang langsung pasangkan ke akun toko Kakak!\n\n"
             . "Yuk lengkapi sekarang agar toko Kakak kebagian orderan saat promosi berjalan. Semangat dan lancar terus usahanya ya Kak! 💪🔥\n\n"
             . "— *Tim Pengembang JASA INHU*";

    echo "[{$num}/{$total}] Mengirim ke {$provName} ({$phone})... ";

    try {
        $res = send_wa_notification($phone, $message, 'profile_reminder', null, $provName);
        if ($res && isset($res['status']) && $res['status'] === 'success') {
            echo "BERHASIL!\n";
            $successCount++;
        } else {
            echo "STATUS: " . ($res['status'] ?? 'unknown') . "\n";
            $successCount++;
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $failCount++;
    }

    // Jeda 8-10 detik jika mengirim banyak nomor agar reputasi WhatsApp aman
    if ($num < $total) {
        echo "   Menunggu jeda 8 detik...\n";
        sleep(8);
    }
}

echo "\n====================================================\n";
echo "PENGIRIMAN SELESAI!\n";
echo "Total: {$total} | Berhasil: {$successCount} | Gagal: {$failCount}\n";
echo "====================================================\n";

<?php
require_once __DIR__ . '/../config/database.php';
$db = get_db();

// Create realistic graphic images with GD
function create_sample_img($path, $text, $subtext, $bgColor, $textColor) {
    $w = 640;
    $h = 420;
    $im = imagecreatetruecolor($w, $h);
    
    // Background
    list($r, $g, $b) = sscanf($bgColor, "#%02x%02x%02x");
    $bg = imagecolorallocate($im, $r, $g, $b);
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    
    // Grid pattern / subtle texture
    $gridColor = imagecolorallocatealpha($im, 255, 255, 255, 115);
    for ($x = 0; $x < $w; $x += 30) {
        imageline($im, $x, 0, $x, $h, $gridColor);
    }
    for ($y = 0; $y < $h; $y += 30) {
        imageline($im, 0, $y, $w, $y, $gridColor);
    }

    // Border
    $borderColor = imagecolorallocatealpha($im, 255, 255, 255, 80);
    imagerectangle($im, 10, 10, $w - 11, $h - 11, $borderColor);

    // Text color
    list($tr, $tg, $tb) = sscanf($textColor, "#%02x%02x%02x");
    $fg = imagecolorallocate($im, $tr, $tg, $tb);
    $subfg = imagecolorallocatealpha($im, $tr, $tg, $tb, 40);

    // Render text with built-in fonts
    imagestring($im, 5, 30, $h / 2 - 30, $text, $fg);
    imagestring($im, 4, 30, $h / 2 + 10, $subtext, $subfg);
    imagestring($im, 3, 30, $h - 35, "JASA INHU - BUKTI PENGERJAAN NYATA", $fg);

    imagejpeg($im, $path, 90);
    imagedestroy($im);
}

$dir = __DIR__ . '/../uploads/portfolios';
if (!is_dir($dir)) mkdir($dir, 0777, true);

create_sample_img("$dir/sample_before_kanopi.jpg", "[FOTO SEBELUM]", "Kondisi Teras Belum Terpasang Kanopi", "#475569", "#ffffff");
create_sample_img("$dir/sample_after_kanopi.jpg", "[FOTO SESUDAH]", "Kanopi Baja Ringan & Atap Spandek Rapi", "#0f766e", "#ffffff");

create_sample_img("$dir/sample_before_truk.jpg", "[FOTO SEBELUM]", "Kondisi Bak Truk Sawit Retak & Aus", "#b91c1c", "#ffffff");
create_sample_img("$dir/sample_after_truk.jpg", "[FOTO SESUDAH]", "Hasil Las Sambung Baja Tebal & Finishing Cat", "#0369a1", "#ffffff");

create_sample_img("$dir/sample_after_teralis.jpg", "[FOTO SESUDAH]", "Pemasangan Teralis Besi Minimalis Modern", "#15803d", "#ffffff");

// Cari provider id 1
$prov = $db->query("SELECT id FROM service_providers ORDER BY id ASC LIMIT 1")->fetch();
if ($prov) {
    $provId = (int)$prov['id'];

    // Hapus portfolio lama sample jika ada
    $db->exec("DELETE FROM provider_portfolios WHERE provider_id = $provId");

    $stmt = $db->prepare("
        INSERT INTO provider_portfolios (provider_id, title, description, image_before, image_after, service_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $provId,
        'Pemasangan Kanopi Baja Ringan Teras Rumah',
        'Pengerjaan rangka baja ringan kokoh dengan atap spandek peredam panas. Selesai tepat waktu 1 hari kerja di Belilas.',
        'uploads/portfolios/sample_before_kanopi.jpg',
        'uploads/portfolios/sample_after_kanopi.jpg',
        '2026-09-28'
    ]);

    $stmt->execute([
        $provId,
        'Las & Modifikasi Rangka Bak Truk Sawit',
        'Penguatan engsel dan lantai bak truk angkut sawit agar kuat menahan beban muatan berat. Menggunakan kawat las baja khusus.',
        'uploads/portfolios/sample_before_truk.jpg',
        'uploads/portfolios/sample_after_truk.jpg',
        '2026-09-20'
    ]);

    $stmt->execute([
        $provId,
        'Pembuatan Teralis Jendela Besi Hollow Minimalis',
        'Teralis jendela 8 unit dengan finishing cat semprot anti-karat hitam doff, rapi dan presisi.',
        null,
        'uploads/portfolios/sample_after_teralis.jpg',
        '2026-09-12'
    ]);

    echo "[SUCCESS] Sample portfolios seeded for provider #$provId!\n";
} else {
    echo "[WARNING] No provider found.\n";
}

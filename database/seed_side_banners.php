<?php
require_once __DIR__ . '/../config/database.php';

$db = get_db();

// Cek apakah side_top sudah ada
$stmt = $db->query("SELECT id FROM banners WHERE position = 'side_top' LIMIT 1");
if (!$stmt->fetch()) {
    $db->prepare("
        INSERT INTO banners (title, subtitle, badge_text, badge_color, link_url, button_text, show_overlay, position, sort_order, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ")->execute([
        'Butuh Tukang Cepat & Urgent? (Siaga 24 Jam)',
        'Kunci mogok, derek, tambal ban & listrik mati.',
        'SIAGA 24 JAM',
        '#ef4444',
        '/index.php?q=siaga#penyedia',
        'Panggil Teknisi Sekarang',
        1,
        'side_top',
        1
    ]);
    echo "Seed side_top berhasil.\n";
} else {
    echo "side_top sudah ada.\n";
}

// Cek apakah side_bottom sudah ada
$stmt = $db->query("SELECT id FROM banners WHERE position = 'side_bottom' LIMIT 1");
if (!$stmt->fetch()) {
    $db->prepare("
        INSERT INTO banners (title, subtitle, badge_text, badge_color, link_url, button_text, show_overlay, position, sort_order, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ")->execute([
        'Punya Keahlian di Inhu? Buka Usaha Jasa',
        'Dapatkan orderan jasa dari warga setiap hari tanpa biaya pendaftaran.',
        'BUKA USAHA JASA',
        '#0d9488',
        '/register.php?role=penyedia',
        'Daftar Jadi Mitra Inhu',
        1,
        'side_bottom',
        2
    ]);
    echo "Seed side_bottom berhasil.\n";
} else {
    echo "side_bottom sudah ada.\n";
}

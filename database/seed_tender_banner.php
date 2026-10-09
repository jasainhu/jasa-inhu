<?php
require_once __DIR__ . '/../config/database.php';

$db = get_db();

$stmt = $db->query("SELECT id FROM banners WHERE position = 'tender' LIMIT 1");
if (!$stmt->fetch()) {
    $db->prepare("
        INSERT INTO banners (title, subtitle, badge_text, badge_color, link_url, button_text, show_overlay, position, sort_order, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ")->execute([
        'Pasang Kebutuhan Jasa Terbuka',
        'Bingung memilih teknisi atau ingin membandingkan harga? Siarkan keluhan atau proyek pekerjaan Anda secara gratis. Teknisi & bengkel terverifikasi di sekitarmu akan mengajukan penawaran harga terbaik!',
        'Tender Kilat & Siaran Warga Inhu',
        '#f59e0b',
        '/tender.php',
        'Pasang Kebutuhan Sekarang',
        1,
        'tender',
        1
    ]);
    echo "Seed tender banner berhasil.\n";
} else {
    echo "tender banner sudah ada.\n";
}

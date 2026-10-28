<?php
require_once __DIR__ . '/../config/app.php';

$db = get_db();

// Cek apakah banner promo sudah ada
$stmtC = $db->query("SELECT id FROM banners WHERE position = 'carousel' AND title LIKE '%Promosi%'");
$existing = $stmtC->fetch();

if (!$existing) {
    $stmt = $db->prepare("
        INSERT INTO banners (title, subtitle, badge_text, badge_color, button_text, link_url, position, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 'carousel', 4, 1)
    ");
    $stmt->execute([
        'Pasang Iklan & Promosi Toko di Sini',
        'Ingin usaha Anda tampil di urutan teratas pencarian warga se-Inhu? Hubungi Admin.',
        'PROMOSI USAHA',
        '#f59e0b',
        'Tanya Info Promosi',
        'https://wa.me/6285126241679?text=Halo%20Admin%20JASA%20INHU%2C%20saya%20tertarik%20tanya%20info%20pemasangan%20banner%20%2F%20promosi%20usaha%20di%20website%20Jasa%20Inhu.'
    ]);
    echo "SUCCESS: Banner carousel promo berhasil ditambahkan dengan ID: " . $db->lastInsertId() . "\n";
} else {
    echo "INFO: Banner sudah ada dengan ID: " . $existing['id'] . "\n";
}


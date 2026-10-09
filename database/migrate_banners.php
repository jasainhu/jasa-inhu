<?php
/**
 * Migration Script: Add banners table
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    
    $sql = "CREATE TABLE IF NOT EXISTS banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        subtitle VARCHAR(255) NULL,
        badge_text VARCHAR(50) NULL DEFAULT 'PENGUMUMAN',
        badge_color VARCHAR(30) NULL DEFAULT '#0d9488',
        image_url VARCHAR(255) NULL,
        link_url VARCHAR(255) NULL,
        button_text VARCHAR(50) NULL DEFAULT 'Lihat Selengkapnya',
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        KEY idx_banners_active (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
    
    $db->exec($sql);
    echo "Tabel 'banners' berhasil dibuat atau sudah ada.\n";

    $count = (int)$db->query("SELECT COUNT(*) FROM banners")->fetchColumn();
    if ($count === 0) {
        $stmt = $db->prepare("INSERT INTO banners (title, subtitle, badge_text, badge_color, image_url, link_url, button_text, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            'Gabung Jadi Mitra JASA INHU Gratis!',
            'Punya keahlian bengkel, tukang, teknisi AC, atau jasa kebun di Indragiri Hulu? Dapatkan pelanggan lokal setiap hari.',
            'PELUANG MITRA',
            '#0d9488',
            '',
            BASE_URL . '/register.php',
            'Daftar Sekarang',
            1,
            1
        ]);
        
        $stmt->execute([
            'Tips Aman & Nyaman Bertransaksi Jasa',
            'Pastikan pengerjaan dicek bersama sebelum pelunasan biaya. Gunakan chat WhatsApp terverifikasi untuk konfirmasi.',
            'TIPS AMAN',
            '#2563eb',
            '',
            BASE_URL . '/#kategori',
            'Pelajari Layanan',
            2,
            1
        ]);
        
        $stmt->execute([
            'Layanan Siaga Montir & Servis Panggilan se-Inhu',
            'Motor mogok atau AC rusak di Rengat, Belilas, Air Molek, dan Peranap? Temukan teknisi terdekat dalam hitungan menit.',
            'LAYANAN CEPAT',
            '#d97706',
            '',
            BASE_URL . '/#kategori',
            'Cari Teknisi',
            3,
            1
        ]);
        
        echo "3 Banner default berhasil ditambahkan.\n";
    } else {
        echo "Tabel banners sudah berisi {$count} data.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

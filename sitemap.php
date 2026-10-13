<?php
/**
 * XML Sitemap Generator - JASA INHU
 * Menghasilkan sitemap dinamis sesuai standar Google Search Console
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$prodDomain = 'https://jasainhu.gafahrandi.my.id';
$baseUrl = (!empty(BASE_URL) && !str_contains(BASE_URL, 'localhost')) ? rtrim(BASE_URL, '/') : $prodDomain;
$db = get_db();

// 1. Kategori Jasa Aktif
$categories = [];
try {
    $categories = $db->query("SELECT id, name FROM service_categories ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 2. Kecamatan di Kab. Inhu
$districts = [];
try {
    $districts = $db->query("SELECT id, name FROM districts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 3. Mitra Penyedia Jasa Aktif
$providers = [];
try {
    $providers = $db->query("
        SELECT sp.id, sp.updated_at 
        FROM service_providers sp 
        JOIN users u ON sp.user_id = u.id 
        WHERE u.is_active = 1 
        ORDER BY sp.updated_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Halaman Utama & Fitur Unggulan -->
    <url>
        <loc><?= $baseUrl ?>/</loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/tender.php</loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/search.php</loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/register.php</loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/terms.php</loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc><?= $baseUrl ?>/privacy.php</loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Halaman Tiap Kategori Jasa -->
    <?php foreach ($categories as $cat): ?>
    <url>
        <loc><?= $baseUrl ?>/search.php?kategori=<?= $cat['id'] ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>

    <!-- Halaman Tiap Kecamatan di Kab. Indragiri Hulu -->
    <?php foreach ($districts as $dst): ?>
    <url>
        <loc><?= $baseUrl ?>/search.php?kecamatan=<?= $dst['id'] ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>

    <!-- Halaman Profil Tiap Mitra Aktif -->
    <?php foreach ($providers as $prov): ?>
    <url>
        <loc><?= $baseUrl ?>/provider_detail.php?id=<?= $prov['id'] ?></loc>
        <lastmod><?= !empty($prov['updated_at']) ? date('Y-m-d', strtotime($prov['updated_at'])) : $today ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>
</urlset>

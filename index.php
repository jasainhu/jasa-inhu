<?php
/**
 * Prototype Tampilan Beranda JASA INHU Ala Shopee Indonesia
 * Hero Grid (Carousel + 2 Mini Banner) + 10-Icon Action Hub + Live Job Board + Modal Kategori
 */

$page_title = 'Temukan Jasa yang Kamu Butuhkan di Indragiri Hulu';
$active_nav = 'home';

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Filter Parameter dari URL
$filter_q = trim($_GET['q'] ?? '');
$filter_cat = (int)($_GET['kategori'] ?? 0);
$filter_dist = (int)($_GET['kecamatan'] ?? 0);

$categories = [];
$districts = [];
$featured_providers = [];
$recent_requests = [];
$banners = [];

try {
    $db = get_db();

    // 1. Kategori Jasa Aktif
    $stmtCat = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    $categories = $stmtCat->fetchAll();

    // 2. Kecamatan di Inhu
    $stmtDist = $db->query("SELECT id, name FROM districts ORDER BY name ASC");
    $districts = $stmtDist->fetchAll();

    // 3. Penyedia Jasa dengan Filter Dinamis
    $prov_where = ["u.is_active = 1"];
    $prov_params = [];

    if (!empty($filter_q)) {
        $prov_where[] = "(sp.business_name LIKE ? OR sc.name LIKE ? OR sp.description LIKE ?)";
        $prov_params[] = "%{$filter_q}%";
        $prov_params[] = "%{$filter_q}%";
        $prov_params[] = "%{$filter_q}%";
    }

    if ($filter_cat > 0) {
        $prov_where[] = "sp.primary_category_id = ?";
        $prov_params[] = $filter_cat;
    }

    if ($filter_dist > 0) {
        $prov_where[] = "(sp.district_id = ? OR EXISTS (SELECT 1 FROM service_areas sa WHERE sa.provider_id = sp.id AND sa.district_id = ?))";
        $prov_params[] = $filter_dist;
        $prov_params[] = $filter_dist;
    }

    $sqlProv = "
        SELECT sp.*, u.name as owner_name, u.phone, sc.name as category_name, sc.icon as category_icon,
               d.name as district_name, v.name as village_name,
               (SELECT COUNT(*) FROM provider_portfolios pp WHERE pp.provider_id = sp.id) as portfolio_count
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        JOIN service_categories sc ON sp.primary_category_id = sc.id
        LEFT JOIN districts d ON sp.district_id = d.id
        LEFT JOIN villages v ON sp.village_id = v.id
        WHERE " . implode(' AND ', $prov_where) . "
        ORDER BY sp.is_verified DESC, sp.rating_avg DESC, sp.completed_jobs DESC
        LIMIT 12
    ";
    $stmtProv = $db->prepare($sqlProv);
    $stmtProv->execute($prov_params);
    $featured_providers = $stmtProv->fetchAll();

    // Mapping Foto Portofolio Mitra untuk Modal Galeri Instan
    $all_portfolios = [];
    if (!empty($featured_providers)) {
        $prov_ids = array_column($featured_providers, 'id');
        $in_clause = implode(',', array_map('intval', $prov_ids));
        $stmtPortAll = $db->query("SELECT * FROM provider_portfolios WHERE provider_id IN ($in_clause) ORDER BY id DESC");
        $raw_ports = $stmtPortAll->fetchAll();
        foreach ($raw_ports as $rp) {
            $all_portfolios[$rp['provider_id']][] = $rp;
        }
    }

    // 4. Permintaan Jasa Terbaru (Open)
    $stmtReq = $db->query("
        SELECT sr.*, sc.name as category_name, sc.icon as category_icon, d.name as district_name,
               (SELECT COUNT(*) FROM service_request_responses srr WHERE srr.request_id = sr.id) as total_offers
        FROM service_requests sr
        JOIN service_categories sc ON sr.category_id = sc.id
        JOIN districts d ON sr.district_id = d.id
        WHERE sr.status = 'open'
        ORDER BY sr.created_at DESC
        LIMIT 4
    ");
    $recent_requests = $stmtReq->fetchAll();

    // 5. Banner Promosi (Carousel & Side Banners)
    try {
        $stmtBanner = $db->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
        $all_banners = $stmtBanner->fetchAll();
        $banners = array_values(array_filter($all_banners, fn($b) => ($b['position'] ?? 'carousel') === 'carousel'));
        $side_top_banner = current(array_filter($all_banners, fn($b) => ($b['position'] ?? '') === 'side_top')) ?: null;
        $side_bottom_banner = current(array_filter($all_banners, fn($b) => ($b['position'] ?? '') === 'side_bottom')) ?: null;
    } catch (Exception $e) {
        $banners = [];
        $side_top_banner = null;
        $side_bottom_banner = null;
    }

    // 6. Galeri Bukti Hasil Kerja Nyata (Before - After Showcase)
    $showcase_portfolios = [];
    try {
        $stmtShowcase = $db->query("
            SELECT pp.*, sp.id as provider_id, sp.business_name, sp.rating_avg, sc.name as category_name, d.name as district_name
            FROM provider_portfolios pp
            JOIN service_providers sp ON pp.provider_id = sp.id
            JOIN service_categories sc ON sp.primary_category_id = sc.id
            LEFT JOIN districts d ON sp.district_id = d.id
            WHERE pp.image_after IS NOT NULL AND pp.image_after != ''
            ORDER BY pp.id DESC
            LIMIT 6
        ");
        $showcase_portfolios = $stmtShowcase->fetchAll();
    } catch (Exception $e) {
        $showcase_portfolios = [];
    }

    // 7. Testimoni Nyata & Rating Bintang Warga Inhu
    $showcase_reviews = [];
    try {
        $stmtRevShow = $db->query("
            SELECT r.*, u.name as customer_name, sp.id as provider_id, sp.business_name, sc.name as category_name, d.name as district_name
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN service_providers sp ON r.provider_id = sp.id
            JOIN service_categories sc ON sp.primary_category_id = sc.id
            LEFT JOIN districts d ON sp.district_id = d.id
            WHERE r.rating >= 4
            ORDER BY r.created_at DESC
            LIMIT 6
        ");
        $showcase_reviews = $stmtRevShow->fetchAll();
    } catch (Exception $e) {
        $showcase_reviews = [];
    }

} catch (Exception $e) {
    error_log("Homepage query error: " . $e->getMessage());
}

// 10 Ikon Menu & Program Unggulan Ala Shopee Hub
$shopee_hub = [
    [
        'title' => "Jasa Siaga\n24 Jam",
        'icon' => 'fa-shield-halved',
        'color' => '#ef4444',
        'bg' => '#fee2e2',
        'link' => BASE_URL . '/search.php?filter=siaga'
    ],
    [
        'title' => "Tukang\nBangunan",
        'icon' => 'fa-trowel-bricks',
        'color' => '#0d9488',
        'bg' => '#ccfbf1',
        'link' => BASE_URL . '/search.php?q=bangunan'
    ],
    [
        'title' => "Mitra\nTerverifikasi",
        'icon' => 'fa-certificate',
        'color' => '#00AA5B',
        'bg' => '#dcfce7',
        'link' => BASE_URL . '/search.php?filter=verified'
    ],
    [
        'title' => "Tarif\nHemat",
        'icon' => 'fa-tags',
        'color' => '#f59e0b',
        'bg' => '#fef3c7',
        'link' => BASE_URL . '/search.php?sort=cheap'
    ],
    [
        'title' => "Spesialis\nSawit",
        'icon' => 'fa-tree',
        'color' => '#16a34a',
        'bg' => '#dcfce7',
        'link' => BASE_URL . '/search.php?q=sawit'
    ],
    [
        'title' => "Sewa\nPickup",
        'icon' => 'fa-truck-pickup',
        'color' => '#475569',
        'bg' => '#f1f5f9',
        'link' => BASE_URL . '/search.php?q=pickup'
    ],
    [
        'title' => "Servis\nAC",
        'icon' => 'fa-snowflake',
        'color' => '#0284c7',
        'bg' => '#e0f2fe',
        'link' => BASE_URL . '/search.php?q=ac'
    ],
    [
        'title' => "Bengkel\nMotor",
        'icon' => 'fa-motorcycle',
        'color' => '#ea580c',
        'bg' => '#ffedd5',
        'link' => BASE_URL . '/search.php?q=motor'
    ],
    [
        'title' => "Gabung\nMitra",
        'icon' => 'fa-user-plus',
        'color' => '#6366f1',
        'bg' => '#e0e7ff',
        'link' => BASE_URL . '/register.php?role=penyedia'
    ],
    [
        'title' => "Semua\nKategori",
        'icon' => 'fa-table-cells-large',
        'color' => '#ffffff',
        'bg' => '#0d9488',
        'link' => BASE_URL . '/search.php'
    ]
];

// Kecamatan Populer untuk Pill Filter
$popular_districts = [
    ['id' => 0, 'name' => '📍 Semua Wilayah'],
    ['id' => 1, 'name' => 'Rengat'],
    ['id' => 2, 'name' => 'Rengat Barat (Pematang Reba)'],
    ['id' => 3, 'name' => 'Pasir Penyu (Air Molek)'],
    ['id' => 4, 'name' => 'Seberida (Belilas)'],
    ['id' => 9, 'name' => 'Peranap'],
    ['id' => 13, 'name' => 'Lirik'],
    ['id' => 11, 'name' => 'Kuala Cenaku'],
    ['id' => 5, 'name' => 'Batang Cenaku']
];
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Hero Banner Grid Ala Shopee (Carousel Kiri + 2 Banner Mini Kanan) -->
<section class="shopee-hero-section">
    <div class="container">
        <div class="row g-1.5 g-md-2 g-lg-3 align-items-stretch">
            <!-- Kolom Kiri: Banner Carousel Utama (Selalu Berdampingan di Kiri) -->
            <div class="col-8 pe-1">
                <?php if (!empty($banners)): ?>
                    <div id="heroBannerCarousel" class="carousel slide carousel-dark h-100" data-bs-ride="carousel" data-bs-interval="5000">
                        <?php if (count($banners) > 1): ?>
                            <div class="carousel-indicators mb-1 mb-md-2">
                                <?php foreach ($banners as $idx => $b): ?>
                                    <button type="button" data-bs-target="#heroBannerCarousel" data-bs-slide-to="<?= $idx ?>" class="<?= $idx === 0 ? 'active' : '' ?>" aria-label="Slide <?= $idx + 1 ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="carousel-inner rounded-3 rounded-md-4 shadow-sm overflow-hidden hero-carousel-inner h-100">
                            <?php foreach ($banners as $idx => $b): ?>
                                <div class="carousel-item h-100 <?= $idx === 0 ? 'active' : '' ?>">
                                    <?php if (!empty($b['image_url']) && file_exists(__DIR__ . '/' . $b['image_url'])): ?>
                                        <div class="banner-slide-wrapper position-relative h-100 w-100 overflow-hidden">
                                            <img src="<?= BASE_URL ?>/<?= e($b['image_url']) ?>" class="d-block w-100 h-100 banner-img-responsive" alt="Banner Promo">
                                            <?php if (!empty($b['show_overlay'])): ?>
                                                <div class="banner-slide-overlay d-flex flex-column justify-content-center p-2 p-md-4 text-white">
                                                    <span class="badge py-1 px-2 fw-bold mb-1 align-self-start" style="background-color: <?= e($b['badge_color'] ?: '#0d9488') ?>; font-size: 0.65rem;">
                                                        <i class="fa-solid fa-bullhorn me-1"></i> <?= e($b['badge_text'] ?: 'INFO INHU') ?>
                                                    </span>
                                                    <h4 class="fw-bold mb-1 text-white text-shadow fs-6 fs-md-4"><?= e($b['title']) ?></h4>
                                                    <p class="text-light opacity-90 small mb-2 text-shadow text-truncate-2 d-none d-sm-block" style="max-width: 480px; font-size: 0.75rem;"><?= e($b['subtitle']) ?></p>
                                                    <?php if (!empty($b['link_url'])): ?>
                                                        <a href="<?= e($b['link_url']) ?>" class="btn btn-light btn-sm fw-bold px-2.5 py-1 rounded-pill align-self-start shadow-sm" style="color: #0f172a; font-size: 0.72rem;">
                                                            <?= e($b['button_text'] ?: 'Detail') ?> &rarr;
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="banner-slide-card p-2 p-md-4 d-flex flex-column justify-content-center h-100" style="background: linear-gradient(135deg, <?= e($b['badge_color'] ?: '#0d9488') ?> 0%, #0f172a 100%);">
                                            <span class="badge py-1 px-2 fw-bold mb-1 align-self-start" style="background: rgba(255,255,255,0.25); font-size: 0.65rem;">
                                                <i class="fa-solid fa-bullhorn me-1"></i> <?= e($b['badge_text'] ?: 'INFO RESMI') ?>
                                            </span>
                                            <h4 class="fw-bold mb-1 text-white fs-6 fs-md-4"><?= e($b['title']) ?></h4>
                                            <p class="text-light opacity-80 small mb-2 text-truncate-2 d-none d-sm-block" style="max-width: 480px; font-size: 0.75rem;"><?= e($b['subtitle']) ?></p>
                                            <?php if (!empty($b['link_url'])): ?>
                                                <a href="<?= e($b['link_url']) ?>" class="btn btn-light btn-sm fw-bold px-2.5 py-1 rounded-pill align-self-start shadow-sm" style="color: #0f172a; font-size: 0.72rem;">
                                                    <?= e($b['button_text'] ?: 'Detail') ?> &rarr;
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($banners) > 1): ?>
                            <button class="carousel-control-prev d-none d-md-flex" type="button" data-bs-target="#heroBannerCarousel" data-bs-slide="prev" aria-label="Sebelumnya">
                                <span class="carousel-nav-btn"><i class="fa-solid fa-chevron-left"></i></span>
                            </button>
                            <button class="carousel-control-next d-none d-md-flex" type="button" data-bs-target="#heroBannerCarousel" data-bs-slide="next" aria-label="Selanjutnya">
                                <span class="carousel-nav-btn"><i class="fa-solid fa-chevron-right"></i></span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kolom Kanan: 2 Mini Side Banner Iklan (Selalu di Sebelah Kanan Carousel: Desktop & HP) -->
            <div class="col-4 ps-1">
                <div class="shopee-side-banners-col">
                    <!-- Mini Banner 1 (Kanan Atas) -->
                    <?php if (!empty($side_top_banner)): ?>
                        <?php 
                        $st_link = !empty($side_top_banner['link_url']) ? e($side_top_banner['link_url']) : '#';
                        $st_has_img = !empty($side_top_banner['image_url']) && file_exists(__DIR__ . '/' . $side_top_banner['image_url']);
                        ?>
                        <a href="<?= $st_link ?>" class="shopee-side-banner shadow-sm <?= !$st_has_img ? 'shopee-side-banner-emergency' : '' ?>" style="<?= (!$st_has_img && !empty($side_top_banner['badge_color'])) ? 'background: linear-gradient(135deg, #0f172a 0%, ' . e($side_top_banner['badge_color']) . ' 100%);' : '' ?>">
                            <?php if ($st_has_img): ?>
                                <img src="<?= BASE_URL ?>/<?= e($side_top_banner['image_url']) ?>" alt="<?= e($side_top_banner['title']) ?>" class="shopee-side-banner-img">
                                <?php if (!empty($side_top_banner['show_overlay'])): ?>
                                    <div class="position-relative z-1 d-flex flex-column justify-content-between h-100 p-1.5 p-md-3" style="background: linear-gradient(to right, rgba(15,23,42,0.88) 0%, rgba(15,23,42,0.3) 100%);">
                                        <div>
                                            <span class="badge fw-bold" style="font-size: 0.6rem; background-color: <?= e($side_top_banner['badge_color'] ?: '#ef4444') ?>;">
                                                <?= e($side_top_banner['badge_text'] ?: 'PROMO') ?>
                                            </span>
                                            <h6 class="fw-bold mb-0 text-white text-shadow text-truncate" style="font-size: 0.75rem;"><?= e($side_top_banner['title']) ?></h6>
                                        </div>
                                        <div class="pt-0.5">
                                            <span class="fw-semibold text-warning" style="font-size: 0.68rem;"><?= e($side_top_banner['button_text'] ?: 'Lihat') ?> &rarr;</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div>
                                    <span class="badge fw-bold" style="font-size: 0.6rem; background-color: <?= e($side_top_banner['badge_color'] ?: '#ef4444') ?>;">
                                        <i class="fa-solid fa-bolt me-1"></i> <?= e($side_top_banner['badge_text'] ?: 'SIAGA') ?>
                                    </span>
                                    <h6 class="fw-bold mb-0 text-white text-truncate mt-0.5" style="font-size: 0.75rem;"><?= e($side_top_banner['title']) ?></h6>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-0.5 border-top border-secondary border-opacity-25">
                                    <span class="fw-semibold text-warning" style="font-size: 0.68rem;"><?= e($side_top_banner['button_text'] ?: 'Panggil') ?> &rarr;</span>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/search.php?filter=siaga" class="shopee-side-banner shopee-side-banner-emergency shadow-sm">
                            <div>
                                <span class="badge bg-danger fw-bold" style="font-size: 0.6rem;">
                                    <i class="fa-solid fa-bolt me-1"></i> SIAGA 24 JAM
                                </span>
                                <h6 class="fw-bold mb-0 text-white text-truncate mt-0.5" style="font-size: 0.75rem;">Butuh Tukang Cepat?</h6>
                            </div>
                            <div class="d-flex align-items-center justify-content-between pt-0.5 border-top border-secondary border-opacity-25">
                                <span class="fw-semibold text-warning" style="font-size: 0.68rem;">Panggil &rarr;</span>
                            </div>
                        </a>
                    <?php endif; ?>

                    <!-- Mini Banner 2 (Kanan Bawah) -->
                    <?php if (!empty($side_bottom_banner)): ?>
                        <?php 
                        $sb_link = !empty($side_bottom_banner['link_url']) ? e($side_bottom_banner['link_url']) : '#';
                        $sb_has_img = !empty($side_bottom_banner['image_url']) && file_exists(__DIR__ . '/' . $side_bottom_banner['image_url']);
                        ?>
                        <a href="<?= $sb_link ?>" class="shopee-side-banner shadow-sm <?= !$sb_has_img ? 'shopee-side-banner-partner' : '' ?>" style="<?= (!$sb_has_img && !empty($side_bottom_banner['badge_color'])) ? 'background: linear-gradient(135deg, #042f2e 0%, ' . e($side_bottom_banner['badge_color']) . ' 100%);' : '' ?>">
                            <?php if ($sb_has_img): ?>
                                <img src="<?= BASE_URL ?>/<?= e($side_bottom_banner['image_url']) ?>" alt="<?= e($side_bottom_banner['title']) ?>" class="shopee-side-banner-img">
                                <?php if (!empty($side_bottom_banner['show_overlay'])): ?>
                                    <div class="position-relative z-1 d-flex flex-column justify-content-between h-100 p-1.5 p-md-3" style="background: linear-gradient(to right, rgba(4,47,46,0.88) 0%, rgba(4,47,46,0.3) 100%);">
                                        <div>
                                            <span class="badge fw-bold" style="font-size: 0.6rem; background-color: <?= e($side_bottom_banner['badge_color'] ?: '#0d9488') ?>;">
                                                <?= e($side_bottom_banner['badge_text'] ?: 'MITRA') ?>
                                            </span>
                                            <h6 class="fw-bold mb-0 text-white text-shadow text-truncate" style="font-size: 0.75rem;"><?= e($side_bottom_banner['title']) ?></h6>
                                        </div>
                                        <div class="pt-0.5">
                                            <span class="fw-semibold text-white" style="font-size: 0.68rem;"><?= e($side_bottom_banner['button_text'] ?: 'Daftar') ?> &rarr;</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div>
                                    <span class="badge bg-white text-teal fw-bold" style="font-size: 0.6rem;">
                                        <i class="fa-solid fa-handshake me-1"></i> <?= e($side_bottom_banner['badge_text'] ?: 'BUKA USAHA') ?>
                                    </span>
                                    <h6 class="fw-bold mb-0 text-white text-truncate mt-0.5" style="font-size: 0.75rem;"><?= e($side_bottom_banner['title']) ?></h6>
                                </div>
                                <div class="d-flex align-items-center justify-content-between pt-0.5 border-top border-white border-opacity-20">
                                    <span class="fw-semibold text-white" style="font-size: 0.68rem;"><?= e($side_bottom_banner['button_text'] ?: 'Daftar Mitra') ?> &rarr;</span>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/register.php?role=penyedia" class="shopee-side-banner shopee-side-banner-partner shadow-sm">
                            <div>
                                <span class="badge bg-white text-teal fw-bold" style="font-size: 0.6rem;">
                                    <i class="fa-solid fa-handshake me-1"></i> BUKA USAHA
                                </span>
                                <h6 class="fw-bold mb-0 text-white text-truncate mt-0.5" style="font-size: 0.75rem;">Punya Keahlian?</h6>
                            </div>
                            <div class="d-flex align-items-center justify-content-between pt-0.5 border-top border-white border-opacity-20">
                                <span class="fw-semibold text-white" style="font-size: 0.68rem;">Daftar &rarr;</span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2. Deretan 10 Ikon Menu & Program Unggulan Ala Shopee Quick Hub -->
        <div class="shopee-hub-card">
            <div class="shopee-hub-grid" id="shopeeHubGrid">
                <?php foreach ($shopee_hub as $sh): ?>
                    <?php if (!empty($sh['modal_trigger'])): ?>
                        <a href="javascript:void(0)" class="shopee-hub-item" data-bs-toggle="modal" data-bs-target="#allCategoriesModal">
                            <div class="shopee-hub-icon" style="background-color: <?= $sh['bg'] ?>; color: <?= $sh['color'] ?>;">
                                <i class="fa-solid <?= $sh['icon'] ?>"></i>
                            </div>
                            <span class="shopee-hub-label"><?= e($sh['title']) ?></span>
                        </a>
                    <?php else: ?>
                        <a href="<?= $sh['link'] ?>" class="shopee-hub-item">
                            <div class="shopee-hub-icon" style="background-color: <?= $sh['bg'] ?>; color: <?= $sh['color'] ?>;">
                                <i class="fa-solid <?= $sh['icon'] ?>"></i>
                            </div>
                            <span class="shopee-hub-label"><?= e($sh['title']) ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Mini Scroll Indicator Bar Ala Shopee/Tokopedia -->
            <div class="hub-scroll-track-wrapper d-lg-none">
                <div class="hub-scroll-track" title="Geser ke samping untuk menu lainnya">
                    <div class="hub-scroll-thumb" id="hubScrollThumb"></div>
                </div>
            </div>
        </div>



    </div>
</section>

<!-- 4. Penyedia Jasa Terdekat & Terpercaya (With District Pill Bar) -->
<section id="penyedia" class="py-4 bg-white">
    <div class="container">
        <!-- Header Section (Ramping 1-Baris di HP, Lengkap di Desktop) -->
        <div class="d-flex align-items-center justify-content-between mb-2 mb-md-3">
            <div>
                <span class="text-uppercase fw-bold small text-teal d-none d-md-block" style="letter-spacing: 0.5px;">Mitra Terpilih & Terdekat</span>
                <h3 class="fw-bold mb-0 text-dark fs-6 fs-md-4 d-flex align-items-center gap-1.5">
                    <span>Penyedia Jasa di Indragiri Hulu</span>
                    <span class="badge bg-light text-teal border rounded-pill d-md-none" style="font-size: 0.65rem; font-weight: 600;">
                        <?= count($featured_providers) ?> Mitra
                    </span>
                </h3>
                <p class="text-muted small mb-0 d-none d-md-block">Mitra beridentitas jelas, nomor terverifikasi, dan siap melayani panggilan Anda</p>
            </div>
            <div class="d-flex align-items-center gap-1.5 gap-md-2">
                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2.5 rounded-pill d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#allCategoriesModal" style="font-size: 0.75rem;">
                    <i class="fa-solid fa-layer-group text-teal"></i>
                    <span>Kategori (<?= count($categories) ?>)</span>
                </button>
                <a href="<?= BASE_URL ?>/register.php?role=penyedia" class="btn btn-sm btn-outline-custom py-1 px-3 rounded-pill d-none d-md-inline-flex align-items-center gap-1" style="font-size: 0.75rem;">
                    <i class="fa-solid fa-user-plus me-1"></i> Gabung Mitra
                </a>
            </div>
        </div>

        <!-- Filter Pil Kecamatan Cepat (One-Tap District Bar) -->
        <div class="mb-2 mb-md-4">
            <div class="district-pill-bar">
                <?php foreach ($popular_districts as $pd): ?>
                    <?php 
                        $is_active = ($filter_dist === $pd['id']) || ($pd['id'] === 0 && $filter_dist === 0);
                        $pill_url = ($pd['id'] === 0) 
                            ? BASE_URL . '/index.php#penyedia' 
                            : BASE_URL . '/index.php?kecamatan=' . $pd['id'] . (!empty($filter_q) ? '&q=' . urlencode($filter_q) : '') . '#penyedia';
                    ?>
                    <a href="<?= $pill_url ?>" class="district-pill <?= $is_active ? 'active' : '' ?>">
                        <?= e($pd['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Indikator Filter Aktif -->
        <?php if (!empty($filter_q) || $filter_dist > 0 || $filter_cat > 0): ?>
            <div class="alert alert-info py-2 px-3 small d-flex align-items-center justify-content-between mb-4 rounded-3 border-0 bg-light">
                <div>
                    <i class="fa-solid fa-filter text-teal me-2"></i>
                    Menampilkan hasil untuk: 
                    <?php if (!empty($filter_q)): ?><strong>"<?= e($filter_q) ?>"</strong><?php endif; ?>
                    <?php if ($filter_dist > 0): ?>
                        &bull; Kecamatan: <strong><?= e($districts[array_search($filter_dist, array_column($districts, 'id'))]['name'] ?? 'Inhu') ?></strong>
                    <?php endif; ?>
                    (Ditemukan <strong><?= count($featured_providers) ?></strong> mitra)
                </div>
                <a href="<?= BASE_URL ?>/index.php#penyedia" class="text-danger fw-semibold text-decoration-none">
                    <i class="fa-solid fa-xmark me-1"></i> Reset Filter
                </a>
            </div>
        <?php endif; ?>

        <!-- Grid Kartu Penyedia Jasa (Shopee 2-Kolom di HP & 3-Kolom di Desktop) -->
        <div class="row g-2 g-md-3 g-lg-4">
            <?php if (!empty($featured_providers)): ?>
                <?php foreach ($featured_providers as $prov): ?>
                    <div class="col-6 col-md-6 col-lg-4">
                        <div class="provider-card-modern h-100 rounded-3 rounded-md-4 border bg-white shadow-sm overflow-hidden d-flex flex-column">
                            <!-- Cover Foto Usaha / Workshop (Dengan Fallback Cantik) -->
                            <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $prov['id'] ?>" class="provider-cover-wrap position-relative d-block text-decoration-none">
                                <?php if (!empty($prov['image_url']) && file_exists(__DIR__ . '/' . $prov['image_url'])): ?>
                                    <img src="<?= BASE_URL ?>/<?= e($prov['image_url']) ?>" alt="<?= e($prov['business_name']) ?>" class="provider-cover-img">
                                <?php else: ?>
                                    <div class="provider-cover-fallback d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #0d9488 0%, #0f172a 100%);">
                                        <div class="text-center text-white p-2 p-md-3">
                                            <i class="fa-solid <?= e($prov['category_icon'] ?: 'fa-wrench') ?> fs-2 fs-md-1 opacity-75 mb-1"></i>
                                            <div class="small fw-semibold opacity-90 d-none d-sm-block"><?= e($prov['category_name']) ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Floating Badges on Photo -->
                                <div class="position-absolute top-0 start-0 m-1.5 m-md-3 d-flex flex-wrap gap-1">
                                    <?php if ($prov['is_verified']): ?>
                                        <span class="badge bg-white shadow-xs border fw-bold py-1 px-1.5 text-success" title="Identitas KTP & Lokasi Usaha Terverifikasi" style="font-size: 0.65rem;">
                                            <i class="fa-solid fa-circle-check text-success"></i> <span class="d-none d-sm-inline">Terverifikasi</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="position-absolute top-0 end-0 m-1.5 m-md-3">
                                    <span class="badge bg-white text-success shadow-xs border fw-semibold py-1 px-1.5" style="font-size: 0.65rem;">
                                        <i class="fa-solid fa-circle text-success" style="font-size: 0.4rem;"></i> <span class="d-none d-sm-inline">Siaga</span>
                                    </span>
                                </div>

                                <!-- Starting Rate Tag Overlay on Cover Photo -->
                                <div class="position-absolute bottom-0 start-0 m-1.5 m-md-2.5">
                                    <span class="badge bg-dark bg-opacity-75 text-white backdrop-blur py-0.5 px-1.5 py-md-1 px-md-2.5 rounded-pill shadow-xs" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-tag text-warning me-0.5"></i> <?= (!empty($prov['hourly_rate_min']) && $prov['hourly_rate_min'] > 0) ? 'Mulai Rp ' . number_format((float)$prov['hourly_rate_min'], 0, ',', '.') : 'Tarif Fleksibel' ?>
                                    </span>
                                </div>
                            </a>

                            <!-- Card Body -->
                            <div class="p-2 p-md-3 d-flex flex-column flex-grow-1">
                                <!-- Header Usaha & Kategori -->
                                <div class="mb-1 mb-md-2">
                                    <div class="d-flex align-items-center justify-content-between gap-1 mb-0.5">
                                        <span class="badge bg-light text-secondary border small text-nowrap flex-shrink-0" style="font-size: 0.62rem; padding: 2px 5px;">
                                            <?= e($prov['category_name']) ?>
                                        </span>
                                    </div>
                                    <h5 class="fw-bold mb-0.5 text-dark lh-sm text-truncate-2" style="font-size: 0.85rem;">
                                        <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $prov['id'] ?>" class="text-decoration-none text-dark hover-teal">
                                            <?= e($prov['business_name']) ?>
                                        </a>
                                    </h5>
                                    <div class="d-flex align-items-center gap-1 text-muted small text-truncate" style="font-size: 0.72rem;" title="<?= e((!empty($prov['village_name']) ? $prov['village_name'] . ', ' : '') . 'Kec. ' . ($prov['district_name'] ?: 'Inhu')) ?>">
                                        <i class="fa-solid fa-location-dot text-danger flex-shrink-0" style="font-size: 0.68rem;"></i>
                                        <span class="text-truncate">
                                            <?= !empty($prov['village_name']) ? '<span class="fw-medium text-dark">' . e($prov['village_name']) . '</span>, ' : '' ?>Kec. <?= e($prov['district_name'] ?: 'Inhu') ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Headline / Slogan (Tampil di Desktop & Tablet) -->
                                <p class="small text-muted mb-2 flex-grow-1 d-none d-md-block" style="line-height: 1.45; font-size: 0.84rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= e($prov['headline'] ?: $prov['description']) ?>
                                </p>

                                <!-- Tombol Portofolio (Tampil di Desktop) -->
                                <?php if (!empty($prov['portfolio_count']) && $prov['portfolio_count'] > 0): ?>
                                    <div class="mb-2.5 d-none d-md-block">
                                        <button type="button" class="btn btn-sm btn-outline-teal w-100 py-1 rounded-3 d-flex align-items-center justify-content-center gap-1.5 fw-semibold" onclick="openPortfolioModal(<?= $prov['id'] ?>)">
                                            <i class="fa-solid fa-images"></i>
                                            <span>Lihat Bukti Hasil Kerja (<?= (int)$prov['portfolio_count'] ?> Portofolio)</span>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Pricing Tag & Rating Row -->
                                <div class="pt-1.5 pb-1.5 pt-md-2 pb-md-2 border-top border-bottom d-flex align-items-center justify-content-between mb-2 mb-md-3 bg-light px-2 px-md-2.5 rounded-2 rounded-md-3 mt-auto">
                                    <div>
                                        <div class="text-muted d-none d-md-block" style="font-size: 0.65rem; text-transform: uppercase; font-weight: 600;">Estimasi Biaya:</div>
                                        <div class="fw-bold text-teal" style="font-size: 0.78rem;">
                                            <?php if (!empty($prov['hourly_rate_min']) && $prov['hourly_rate_min'] > 0): ?>
                                                Rp <?= number_format((float)$prov['hourly_rate_min'], 0, ',', '.') ?>
                                            <?php else: ?>
                                                Hubungi Mitra
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-dark d-flex align-items-center justify-content-end gap-1" style="font-size: 0.76rem;">
                                            <i class="fa-solid fa-star text-warning"></i> <?= number_format((float)$prov['rating_avg'], 1) ?>
                                            <span class="text-muted fw-normal d-none d-sm-inline" style="font-size: 0.68rem;">(<?= (int)($prov['reviews_count'] ?? 0) ?>)</span>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.65rem;">
                                            <?= $prov['completed_jobs'] ?: 0 ?> selesai
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons: Chat, Pesan & Lihat Profil -->
                                <div class="d-flex flex-column gap-1.5 mt-auto">
                                    <div class="row g-1 g-md-2">
                                        <div class="col-6">
                                            <a href="<?= BASE_URL ?>/chat.php?provider_id=<?= $prov['id'] ?>" class="btn btn-outline-teal w-100 py-1.5 py-md-2 fw-semibold shadow-xs d-flex align-items-center justify-content-center gap-1" style="font-size: 0.75rem;">
                                                <i class="fa-solid fa-comments"></i> <span>Chat</span>
                                            </a>
                                        </div>
                                        <div class="col-6">
                                            <button type="button" class="btn btn-teal text-white w-100 py-1.5 py-md-2 fw-semibold shadow-xs d-flex align-items-center justify-content-center gap-1" style="font-size: 0.75rem;" onclick="openDirectQuickOrderModal(<?= htmlspecialchars(json_encode([
                                                'id' => (int)$prov['id'],
                                                'name' => $prov['business_name'],
                                                'phone' => $prov['phone'],
                                                'category' => $prov['category_name'],
                                                'district' => $prov['district_name'] ?: 'Kab. Indragiri Hulu',
                                                'rate' => (!empty($prov['hourly_rate_min']) && $prov['hourly_rate_min'] > 0) ? 'Mulai Rp ' . number_format((float)$prov['hourly_rate_min'], 0, ',', '.') : 'Sesuai Kesepakatan'
                                            ]), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fa-solid fa-bolt text-warning"></i> <span>Pesan</span>
                                            </button>
                                        </div>
                                    </div>
                                    <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $prov['id'] ?>" class="btn btn-light btn-sm w-100 py-1 border text-dark fw-semibold d-none d-md-block" style="font-size: 0.78rem;">
                                        <i class="fa-solid fa-store me-1 text-teal"></i> Lihat Profil Lengkap & Testimoni &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Smart & Engaging Empty State -->
                <div class="col-12">
                    <div class="empty-state-modern p-4 p-md-5 rounded-4 border bg-white shadow-sm text-center">
                        <div class="empty-state-icon-circle mx-auto mb-3">
                            <i class="fa-solid fa-map-location-dot text-teal fs-2"></i>
                        </div>
                        <?php
                            $active_dist_name = 'Kriteria Ini';
                            if ($filter_dist > 0) {
                                foreach ($districts as $d) {
                                    if ((int)$d['id'] === $filter_dist) {
                                        $active_dist_name = 'Kecamatan ' . $d['name'];
                                        break;
                                    }
                                }
                            }
                        ?>
                        <h4 class="fw-bold text-dark mb-2">
                            Belum Ada Penyedia Jasa di <?= e($active_dist_name) ?>
                        </h4>
                        <p class="text-muted small mx-auto mb-4" style="max-width: 520px; line-height: 1.6;">
                            Saat ini belum ada mitra terdaftar untuk kriteria yang Anda pilih. Anda tetap bisa memasang permintaan agar teknisi terdekat menghubungi Anda, atau bergabung menjadi mitra pertama!
                        </p>

                        <!-- Call to Action Buttons -->
                        <div class="d-flex flex-column flex-sm-row justify-content-center align-items-center gap-3 mb-4">
                            <a href="<?= BASE_URL ?>/user/requests.php" class="btn btn-primary-custom px-4 py-2.5 fw-bold rounded-pill shadow-sm">
                                <i class="fa-solid fa-bullhorn me-1"></i> Pasang Permintaan Jasa (Gratis)
                            </a>
                            <a href="<?= BASE_URL ?>/register.php?role=penyedia" class="btn btn-outline-custom px-4 py-2.5 fw-bold rounded-pill">
                                <i class="fa-solid fa-briefcase me-1"></i> Daftar Jadi Mitra Pertama
                            </a>
                        </div>

                        <div class="pt-3 border-top d-inline-block">
                            <a href="<?= BASE_URL ?>/index.php#penyedia" class="small text-decoration-none text-muted">
                                <i class="fa-solid fa-arrow-rotate-left me-1 text-teal"></i> Reset filter & lihat seluruh mitra se-Kabupaten Inhu
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!empty($showcase_reviews)): ?>
<!-- FITUR BESAR 4: TESTIMONI & ULASAN ASLI WARGA INHU (Social Proof) -->
<section class="py-4 bg-light border-top border-bottom mb-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <span class="badge bg-warning text-dark fw-bold px-2.5 py-1 rounded-pill small mb-1">
                    <i class="fa-solid fa-star me-1"></i> KATA WARGA INHU
                </span>
                <h4 class="fw-bold text-dark mb-0 fs-5">Pengalaman Nyata Warga Menggunakan Jasa</h4>
                <p class="text-muted small mb-0">Ulasan kepuasan jujur dari masyarakat di berbagai kecamatan se-Inhu</p>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($showcase_reviews as $sRev): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-3.5 h-100 bg-white d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center fw-bold small" style="width: 36px; height: 36px;">
                                    <?= strtoupper(substr($sRev['customer_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small"><?= e($sRev['customer_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;"><i class="fa-solid fa-location-dot text-danger me-1"></i> Kec. <?= e($sRev['district_name'] ?: 'Inhu') ?></div>
                                </div>
                            </div>
                            <div class="text-warning small">
                                <?php for ($i = 0; $i < (int)$sRev['rating']; $i++): ?>
                                    <i class="fa-solid fa-star"></i>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <p class="small text-secondary mb-3 flex-grow-1 fst-italic" style="line-height: 1.5; font-size: 0.82rem;">
                            "<?= e($sRev['comment']) ?>"
                        </p>

                        <div class="p-2 rounded-3 bg-light border-top d-flex align-items-center justify-content-between" style="font-size: 0.75rem;">
                            <span class="text-muted">Untuk Mitra:</span>
                            <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $sRev['provider_id'] ?>" class="fw-bold text-teal text-decoration-none">
                                <?= e($sRev['business_name']) ?> &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- FITUR BESAR 5: JAMINAN AMAN PLATFORM JASA INHU -->
<div class="container mb-5">
    <div class="card border-0 shadow-sm rounded-4 p-4 text-white" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
        <div class="row g-4 align-items-center">
            <div class="col-lg-4 text-center text-lg-start">
                <span class="badge bg-warning text-dark fw-bold px-3 py-1.5 rounded-pill mb-2">
                    <i class="fa-solid fa-shield-halved me-1"></i> STANDAR KEAMANAN
                </span>
                <h4 class="fw-bold text-white mb-2">Jaminan Transaksi Aman & Nyaman</h4>
                <p class="text-white-50 small mb-0">Platform digital lokal yang mengutamakan kejujuran dan kepercayaan warga Kabupaten Indragiri Hulu.</p>
            </div>
            <div class="col-lg-8">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 h-100">
                            <i class="fa-solid fa-id-card text-warning fs-3 mb-2"></i>
                            <h6 class="fw-bold text-white mb-1">Identitas KTP Terdata</h6>
                            <p class="text-white-50 small mb-0" style="font-size: 0.75rem;">Seluruh mitra terdaftar memiliki identitas dan domisili jelas di Inhu.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 h-100">
                            <i class="fa-solid fa-hand-holding-dollar text-warning fs-3 mb-2"></i>
                            <h6 class="fw-bold text-white mb-1">Bayar Usai Selesai</h6>
                            <p class="text-white-50 small mb-0" style="font-size: 0.75rem;">Cek dan pastikan pengerjaan tuntas sebelum melakukan pelunasan biaya.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3 bg-white bg-opacity-10 h-100">
                            <i class="fa-solid fa-comments text-warning fs-3 mb-2"></i>
                            <h6 class="fw-bold text-white mb-1">Mediasi Tim Admin</h6>
                            <p class="text-white-50 small mb-0" style="font-size: 0.75rem;">Layanan WhatsApp CS Admin siap membantu mediasi jika ada aduan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5. MODAL POP-UP SELURUH KATEGORI JASA (SHOPEE STYLE DRAWER/MODAL) -->
<div class="modal fade" id="allCategoriesModal" tabindex="-1" aria-labelledby="allCategoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="allCategoriesModalLabel">
                        <i class="fa-solid fa-table-cells-large text-teal me-2"></i> Seluruh Kategori Jasa di Indragiri Hulu
                    </h5>
                    <p class="text-muted small mb-0">Pilih jenis layanan yang Anda butuhkan untuk mencari mitra terdekat</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Search Box Kategori Instan -->
                <div class="mb-3 position-relative">
                    <input type="text" id="categoryFilterInput" class="form-control form-control-lg rounded-pill ps-4 pe-5 border" placeholder="Ketik nama jasa... (misal: Las, AC, Motor, Sawit)" onkeyup="filterCategoriesInModal(this.value)">
                    <i class="fa-solid fa-magnifying-glass position-absolute top-50 end-0 translate-middle-y me-4 text-muted"></i>
                </div>

                <!-- Grid Kategori Lengkap -->
                <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-2.5" id="categoryModalGrid">
                    <?php foreach ($categories as $cat): ?>
                        <div class="col category-modal-item" data-cat-name="<?= strtolower(e($cat['name'])) ?>">
                            <a href="<?= BASE_URL ?>/search.php?kategori=<?= $cat['id'] ?>" class="p-3 rounded-3 border bg-white d-flex align-items-center gap-2.5 text-decoration-none text-dark h-100 transition-hover shadow-xs">
                                <div class="rounded-3 d-flex align-items-center justify-content-center text-teal flex-shrink-0" style="width: 40px; height: 40px; background: #ccfbf1; font-size: 1.15rem;">
                                    <i class="fa-solid <?= e($cat['icon'] ?: 'fa-wrench') ?>"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fw-bold small text-truncate"><?= e($cat['name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;">Pilih Layanan &rarr;</div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Empty State jika pencarian tidak ditemukan -->
                <div id="categoryModalEmpty" class="text-center py-4 d-none">
                    <i class="fa-solid fa-magnifying-glass fs-2 text-muted opacity-50 mb-2"></i>
                    <p class="text-muted small mb-0">Kategori jasa tidak ditemukan. Coba kata kunci lain.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5. Modal Galeri Portofolio Hasil Kerja Nyata -->
<div class="modal fade" id="portfolioGalleryModal" tabindex="-1" aria-labelledby="portfolioGalleryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-0.5">
                        <span class="badge bg-teal-subtle text-teal fw-bold" style="font-size: 0.7rem;">BUKTI KERJA NYATA</span>
                        <span class="text-muted small" id="portModalCategory"></span>
                    </div>
                    <h5 class="modal-title fw-bold text-dark mb-0" id="portModalTitle">Portofolio Hasil Kerja</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4" style="max-height: 75vh; overflow-y: auto;">
                <div id="portfolioGalleryContainer">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                <div class="d-flex gap-2">
                    <a href="#" id="portModalChatBtn" class="btn btn-outline-teal fw-semibold px-3 d-flex align-items-center gap-1.5">
                        <i class="fa-solid fa-comments"></i> <span>Chat Mitra di Aplikasi</span>
                    </a>
                    <a href="#" id="portModalOrderBtn" class="btn btn-primary-custom fw-bold px-3 d-flex align-items-center gap-1.5">
                        <i class="fa-solid fa-calendar-check"></i> <span>Pesan Jasa Ini</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 7. Modal Pesan Jasa Langsung (1-Click Instant Order Modal) -->
<div class="modal fade" id="directQuickOrderModal" tabindex="-1" aria-labelledby="directQuickOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Header Modal -->
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-white text-teal d-flex align-items-center justify-content-center shadow-xs" style="width: 42px; height: 42px; font-size: 1.25rem;">
                        <i class="fa-solid fa-bolt text-warning"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="directQuickOrderModalLabel">Pesan Jasa Langsung</h5>
                        <span class="text-white-50 small" style="font-size: 0.78rem;">Proses kilat tanpa reload &bull; Langsung ke teknisi</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Body Modal: Form Input State -->
            <div class="modal-body p-4" id="quickOrderFormBody">
                <!-- Info Mitra yang Dipilih -->
                <div class="p-3 rounded-3 bg-light border mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-teal-subtle text-teal fw-bold px-2 py-0.5" style="font-size: 0.7rem;">
                            <i class="fa-solid fa-circle-check me-1"></i> MITRA TERDAFTAR INHU
                        </span>
                        <span class="badge bg-white border text-teal fw-semibold" id="quickOrderModalRate" style="font-size: 0.72rem;"></span>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 fs-6" id="quickOrderModalProviderName">Nama Mitra</h6>
                    <div class="text-muted small" style="font-size: 0.78rem;">
                        <span id="quickOrderModalCategory">Kategori</span> &bull; <i class="fa-solid fa-location-dot text-danger ms-1"></i> <span id="quickOrderModalDistrict">Kecamatan</span>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <a href="#" id="quickOrderViewProfileBtn" target="_blank" class="small text-teal text-decoration-none fw-semibold">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Lihat Deskripsi & Testimoni Pelanggan ↗
                        </a>
                    </div>
                </div>

                <!-- Alert Error Asinkron -->
                <div id="quickOrderAlert" class="alert alert-danger d-none py-2 px-3 small align-items-center mb-3 rounded-3" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <span id="quickOrderAlertText"></span>
                </div>

                <?php if (!is_logged_in()): ?>
                    <!-- State: Belum Login -->
                    <div class="alert alert-warning border-0 p-3 rounded-3 mb-3">
                        <div class="d-flex gap-2">
                            <i class="fa-solid fa-lock text-warning fs-5 mt-1"></i>
                            <div>
                                <div class="fw-bold text-dark mb-1" style="font-size: 0.9rem;">Masuk Akun untuk Memesan</div>
                                <p class="text-muted small mb-2" style="line-height: 1.45;">
                                    Agar pesanan Anda tercatat dan teknisi dapat menghubungi nomor Anda, silakan masuk terlebih dahulu (tersedia akun demo 1-klik).
                                </p>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-primary-custom fw-bold px-3 py-1.5" onclick="switchToLoginFromQuickOrder()">
                                        <i class="fa-solid fa-right-to-bracket me-1"></i> Masuk Sekarang (1 Klik)
                                    </button>
                                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-outline-secondary fw-semibold px-2.5 py-1.5">
                                        <i class="fa-solid fa-user-plus me-1"></i> Daftar Akun Baru
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Info Pemesan Terverifikasi -->
                    <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 bg-light border mb-3">
                        <div class="small">
                            <span class="text-muted">Pemesan:</span> <strong><?= e($current_user['name']) ?></strong> <span class="text-muted">(<?= e($current_user['phone']) ?>)</span>
                        </div>
                        <span class="badge bg-success-subtle text-success small fw-semibold">
                            <i class="fa-solid fa-user-check me-1"></i> Aktif
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Form Ringkas (Hanya 2 Pertanyaan Inti) -->
                <form id="directQuickOrderForm" onsubmit="event.preventDefault(); submitDirectQuickOrder();">
                    <input type="hidden" id="quickOrderProviderId" value="">

                    <!-- Pertanyaan 1: Masalah / Kebutuhan -->
                    <div class="mb-3">
                        <label for="quickOrderProblem" class="form-label small fw-bold text-dark mb-1">
                            1. Apa yang perlu diperbaiki / dikerjakan? <span class="text-danger">*</span>
                        </label>
                        <textarea id="quickOrderProblem" rows="3" class="form-control" placeholder="Contoh: AC kamar bocor menetes dan tidak dingin, tolong dicuci dan cek freon..." required <?= !is_logged_in() ? 'disabled' : '' ?>></textarea>
                        
                        <!-- Quick Suggestions / Chips -->
                        <div class="d-flex flex-wrap gap-1 mt-1.5">
                            <span class="text-muted small me-1" style="font-size: 0.72rem;">Saran cepat:</span>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill" style="font-size: 0.72rem;" onclick="appendQuickProblem('Servis & Cuci Berkala')">Cuci/Servis</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill" style="font-size: 0.72rem;" onclick="appendQuickProblem('Perbaikan Kerusakan')">Perbaikan Rusak</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill" style="font-size: 0.72rem;" onclick="appendQuickProblem('Bongkar & Pasang')">Bongkar Pasang</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill" style="font-size: 0.72rem;" onclick="appendQuickProblem('Panggilan Darurat / Cepat')">Darurat</button>
                        </div>
                    </div>

                    <!-- Pertanyaan 2: Patokan Alamat & Waktu -->
                    <div class="mb-3">
                        <label for="quickOrderAddress" class="form-label small fw-bold text-dark mb-1">
                            2. Patokan Alamat & Jadwal Pengerjaan <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="quickOrderAddress" class="form-control form-control-sm mb-2" placeholder="Contoh: Belilas KM 4, Jl. Utama samping Masjid Baiturrahman" required <?= !is_logged_in() ? 'disabled' : '' ?>>
                        
                        <div class="row g-2">
                            <div class="col-12 col-sm-7">
                                <select id="quickOrderTime" class="form-select form-select-sm" <?= !is_logged_in() ? 'disabled' : '' ?>>
                                    <option value="Hari ini (Segera / Urgent)">⚡ Hari ini (Segera / Urgent)</option>
                                    <option value="Besok Pagi (08:00 - 12:00)" selected>🌅 Besok Pagi (08:00 - 12:00)</option>
                                    <option value="Besok Siang / Sore (13:00 - 17:00)">☀️ Besok Siang / Sore (13:00 - 17:00)</option>
                                    <option value="Akhir Pekan (Sabtu / Minggu)">📅 Akhir Pekan (Sabtu / Minggu)</option>
                                    <option value="Waktu Fleksibel (Sesuai Kesepakatan)">🤝 Waktu Fleksibel</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-5 d-flex align-items-center">
                                <span class="text-muted small" style="font-size: 0.72rem;">
                                    <i class="fa-solid fa-hand-holding-dollar text-teal me-1"></i> Bayar di tempat usai kerja
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi Submit -->
                    <div class="pt-2">
                        <?php if (is_logged_in()): ?>
                            <button type="button" id="btnSubmitQuickOrder" class="btn btn-primary-custom w-100 py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" onclick="submitDirectQuickOrder()">
                                <i class="fa-solid fa-paper-plane" id="btnQuickOrderIcon"></i>
                                <span id="btnQuickOrderText">Kirim Pesanan</span>
                                <span id="btnQuickOrderSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-primary-custom w-100 py-2.5 fw-bold shadow-sm" onclick="switchToLoginFromQuickOrder()">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Masuk untuk Kirim Pesanan
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Body Modal: Success Confirmation State -->
            <div class="modal-body p-4 d-none" id="quickOrderSuccessBody">
                <div class="text-center py-2">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 68px; height: 68px; font-size: 2.2rem;">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">Pesanan Jasa Diterima!</h4>
                    <p class="text-muted small mb-3" id="quickOrderSuccessMsg">Pesanan Anda telah otomatis dicatat di sistem dan diteruskan ke teknisi.</p>
                    
                    <div class="p-3 bg-light rounded-3 border mb-3 text-start small">
                        <div class="d-flex justify-content-between mb-1.5">
                            <span class="text-muted">Nomor Pesanan:</span>
                            <strong id="quickOrderSuccessId" class="text-teal font-monospace fs-6">#---</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1.5">
                            <span class="text-muted">Mitra Tujuan:</span>
                            <strong id="quickOrderSuccessProv">---</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Status:</span>
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> Menunggu Konfirmasi Mitra</span>
                        </div>
                    </div>

                    <div class="alert alert-teal-subtle text-teal border-0 p-2.5 small rounded-3 mb-3 text-start" style="background-color: #f0fdfa; color: #0d9488;">
                        <i class="fa-solid fa-comments me-1.5"></i>
                        <strong>Pesanan Terkirim!</strong> Anda dapat langsung koordinasi detail perbaikan melalui obrolan di aplikasi:
                    </div>

                    <a href="#" id="quickOrderSuccessChatBtn" class="btn btn-teal text-white w-100 py-2.5 fw-bold shadow-sm mb-2 d-flex align-items-center justify-content-center gap-2">
                        <i class="fa-solid fa-comments fs-5"></i>
                        <span>Buka Obrolan di Aplikasi</span>
                    </a>

                    <div class="d-flex gap-2 mt-2">
                        <a href="<?= BASE_URL ?>/user/requests.php" class="btn btn-outline-secondary w-50 py-2 fw-semibold small">
                            <i class="fa-solid fa-clipboard-list me-1"></i> Pesanan Saya
                        </a>
                        <button type="button" class="btn btn-light border w-50 py-2 fw-semibold small" data-bs-dismiss="modal">
                            Selesai & Tutup
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Data Portofolio Lengkap dari PHP
window.ALL_PORTFOLIOS = <?= json_encode($all_portfolios, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.FEATURED_PROVIDERS = <?= json_encode(array_column($featured_providers, null, 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.BASE_URL = "<?= BASE_URL ?>";
window.CSRF_TOKEN = "<?= csrf_token() ?>";
window.IS_LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;

let currentDirectOrderProvider = null;

function openPortfolioModal(providerId) {
    const prov = window.FEATURED_PROVIDERS[providerId];
    if (!prov) return;

    const portfolios = window.ALL_PORTFOLIOS[providerId] || [];
    document.getElementById('portModalTitle').textContent = "Portofolio Kerja: " + prov.business_name;
    document.getElementById('portModalCategory').textContent = prov.category_name + " • Kec. " + (prov.district_name || 'Inhu');
    
    // Action buttons in modal
    document.getElementById('portModalOrderBtn').onclick = function(e) {
        e.preventDefault();
        const portModal = bootstrap.Modal.getInstance(document.getElementById('portfolioGalleryModal'));
        if (portModal) portModal.hide();
        openDirectQuickOrderModal({
            id: prov.id,
            name: prov.business_name,
            phone: prov.phone,
            category: prov.category_name,
            district: prov.district_name || 'Indragiri Hulu',
            rate: (prov.hourly_rate_min > 0) ? ('Mulai Rp ' + new Intl.NumberFormat('id-ID').format(prov.hourly_rate_min)) : 'Sesuai Kesepakatan'
        });
    };

    const chatBtn = document.getElementById('portModalChatBtn');
    if (chatBtn) {
        chatBtn.href = window.BASE_URL + '/chat.php?provider_id=' + prov.id;
    }

    const container = document.getElementById('portfolioGalleryContainer');
    container.innerHTML = '';

    if (portfolios.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-camera fs-1 mb-2 opacity-50"></i>
                <p>Belum ada foto portofolio untuk mitra ini.</p>
            </div>
        `;
    } else {
        let html = '<div class="row g-4">';
        portfolios.forEach(item => {
            let beforeAfterHtml = '';
            if (item.image_before) {
                beforeAfterHtml = `
                    <div class="row g-0 border-bottom" style="min-height: 220px;">
                        <div class="col-6 position-relative border-end overflow-hidden" style="background: #0f172a;">
                            <img src="${window.BASE_URL}/${item.image_before}" alt="Sebelum" class="w-100 h-100 object-fit-cover" style="max-height: 260px;">
                            <span class="badge bg-danger position-absolute top-0 start-0 m-2 fw-bold" style="font-size: 0.65rem;">SEBELUM</span>
                        </div>
                        <div class="col-6 position-relative overflow-hidden" style="background: #0f172a;">
                            <img src="${window.BASE_URL}/${item.image_after}" alt="Sesudah" class="w-100 h-100 object-fit-cover" style="max-height: 260px;">
                            <span class="badge bg-success position-absolute top-0 end-0 m-2 fw-bold" style="font-size: 0.65rem;">SESUDAH</span>
                        </div>
                    </div>
                `;
            } else {
                beforeAfterHtml = `
                    <div class="position-relative overflow-hidden border-bottom text-center" style="background: #0f172a; max-height: 320px;">
                        <img src="${window.BASE_URL}/${item.image_after}" alt="${item.title}" class="img-fluid object-fit-cover w-100" style="max-height: 320px;">
                        <span class="badge bg-success position-absolute top-0 end-0 m-2 fw-bold" style="font-size: 0.65rem;">HASIL SELESAI</span>
                    </div>
                `;
            }

            html += `
                <div class="col-12 col-md-6">
                    <div class="card h-100 border shadow-xs rounded-4 overflow-hidden bg-white">
                        ${beforeAfterHtml}
                        <div class="card-body p-3">
                            <h6 class="fw-bold mb-1 text-dark">${item.title}</h6>
                            ${item.description ? `<p class="text-muted small mb-2" style="font-size: 0.8rem; line-height: 1.4;">${item.description}</p>` : ''}
                            <div class="text-muted small" style="font-size: 0.72rem;">
                                <i class="fa-regular fa-calendar me-1"></i> Selesai: ${item.service_date || item.created_at.substring(0, 10)}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    const modal = new bootstrap.Modal(document.getElementById('portfolioGalleryModal'));
    modal.show();
}

// 1-Click Instant Order Modal Functions
function openDirectQuickOrderModal(prov) {
    currentDirectOrderProvider = prov;
    
    // Reset state modal
    document.getElementById('quickOrderFormBody').classList.remove('d-none');
    document.getElementById('quickOrderSuccessBody').classList.add('d-none');
    document.getElementById('quickOrderAlert').classList.add('d-none');
    
    // Set Provider info
    document.getElementById('quickOrderProviderId').value = prov.id;
    document.getElementById('quickOrderModalProviderName').textContent = prov.name;
    document.getElementById('quickOrderModalCategory').textContent = prov.category;
    document.getElementById('quickOrderModalDistrict').textContent = prov.district;
    document.getElementById('quickOrderModalRate').textContent = prov.rate;

    const profileLink = document.getElementById('quickOrderViewProfileBtn');
    if (profileLink) {
        profileLink.href = window.BASE_URL + '/provider_detail.php?id=' + prov.id;
    }

    const modalEl = document.getElementById('directQuickOrderModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function appendQuickProblem(text) {
    const area = document.getElementById('quickOrderProblem');
    if (!area) return;
    if (area.value.trim() === '') {
        area.value = text + ': ';
    } else {
        area.value = area.value.trim() + ', ' + text;
    }
    area.focus();
}

function switchToLoginFromQuickOrder() {
    const orderModal = bootstrap.Modal.getInstance(document.getElementById('directQuickOrderModal'));
    if (orderModal) orderModal.hide();
    
    const loginModalEl = document.getElementById('loginModal');
    if (loginModalEl) {
        const loginRedirect = document.getElementById('modal_login_redirect');
        if (loginRedirect) {
            loginRedirect.value = window.location.pathname + window.location.search + '#penyedia';
        }
        const loginModal = bootstrap.Modal.getOrCreateInstance(loginModalEl);
        loginModal.show();
    }
}

function switchToWaFromQuickOrder() {
    const orderModal = bootstrap.Modal.getInstance(document.getElementById('directQuickOrderModal'));
    if (orderModal) orderModal.hide();
    if (currentDirectOrderProvider) {
        openQuickWaModal(currentDirectOrderProvider);
    }
}

function submitDirectQuickOrder() {
    if (!window.IS_LOGGED_IN) {
        switchToLoginFromQuickOrder();
        return;
    }

    const provId = document.getElementById('quickOrderProviderId').value;
    const problem = document.getElementById('quickOrderProblem').value.trim();
    const address = document.getElementById('quickOrderAddress').value.trim();
    const preferredTime = document.getElementById('quickOrderTime').value;

    const alertEl = document.getElementById('quickOrderAlert');
    const alertText = document.getElementById('quickOrderAlertText');

    if (!problem) {
        alertText.textContent = "Mohon jelaskan apa yang perlu diperbaiki atau diservis.";
        alertEl.classList.remove('d-none');
        document.getElementById('quickOrderProblem').focus();
        return;
    }

    if (!address) {
        alertText.textContent = "Mohon cantumkan patokan alamat pengerjaan jasa di Inhu.";
        alertEl.classList.remove('d-none');
        document.getElementById('quickOrderAddress').focus();
        return;
    }

    alertEl.classList.add('d-none');

    // Loading state
    const btnSubmit = document.getElementById('btnSubmitQuickOrder');
    const btnText = document.getElementById('btnQuickOrderText');
    const btnSpinner = document.getElementById('btnQuickOrderSpinner');
    const btnIcon = document.getElementById('btnQuickOrderIcon');

    if (btnSubmit) btnSubmit.disabled = true;
    if (btnText) btnText.textContent = "Mengirim Pesanan...";
    if (btnSpinner) btnSpinner.classList.remove('d-none');
    if (btnIcon) btnIcon.classList.add('d-none');

    const formData = new FormData();
    formData.append('csrf_token', window.CSRF_TOKEN);
    formData.append('provider_id', provId);
    formData.append('problem', problem);
    formData.append('address', address);
    formData.append('preferred_time', preferredTime);

    fetch(window.BASE_URL + '/api/direct_order.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (btnSubmit) btnSubmit.disabled = false;
        if (btnText) btnText.textContent = "Kirim Pesanan";
        if (btnSpinner) btnSpinner.classList.add('d-none');
        if (btnIcon) btnIcon.classList.remove('d-none');

        if (data.success) {
            // Show Success State
            document.getElementById('quickOrderFormBody').classList.add('d-none');
            document.getElementById('quickOrderSuccessBody').classList.remove('d-none');
            document.getElementById('quickOrderSuccessId').textContent = '#' + data.request_id;
            document.getElementById('quickOrderSuccessProv').textContent = data.provider_name;
            document.getElementById('quickOrderSuccessMsg').textContent = data.message;
            document.getElementById('quickOrderSuccessChatBtn').href = data.chat_url || (window.BASE_URL + '/chat.php?provider_id=' + provId);
            
            // Clear inputs for next time
            document.getElementById('quickOrderProblem').value = '';
            document.getElementById('quickOrderAddress').value = '';
        } else {
            if (data.need_login) {
                switchToLoginFromQuickOrder();
            } else {
                alertText.textContent = data.message || "Gagal membuat pesanan.";
                alertEl.classList.remove('d-none');
            }
        }
    })
    .catch(err => {
        if (btnSubmit) btnSubmit.disabled = false;
        if (btnText) btnText.textContent = "Kirim Pesanan";
        if (btnSpinner) btnSpinner.classList.add('d-none');
        if (btnIcon) btnIcon.classList.remove('d-none');

        alertText.textContent = "Terjadi gangguan jaringan. Silakan periksa koneksi Anda dan coba lagi.";
        alertEl.classList.remove('d-none');
    });
}

function filterCategoriesInModal(query) {
    const q = query.toLowerCase().trim();
    const items = document.querySelectorAll('.category-modal-item');
    let visibleCount = 0;
    
    items.forEach(item => {
        const name = item.getAttribute('data-cat-name');
        if (name.includes(q)) {
            item.classList.remove('d-none');
            visibleCount++;
        } else {
            item.classList.add('d-none');
        }
    });

    const emptyBox = document.getElementById('categoryModalEmpty');
    if (visibleCount === 0) {
        emptyBox.classList.remove('d-none');
    } else {
        emptyBox.classList.add('d-none');
    }
}

// Sync Shopee/Tokopedia Mini Scroll Bar Indicator
document.addEventListener('DOMContentLoaded', function() {
    const hubGrid = document.getElementById('shopeeHubGrid');
    const hubThumb = document.getElementById('hubScrollThumb');
    if (hubGrid && hubThumb) {
        const updateHubScroll = () => {
            const maxScroll = hubGrid.scrollWidth - hubGrid.clientWidth;
            if (maxScroll <= 0) return;
            const progress = Math.min(Math.max(hubGrid.scrollLeft / maxScroll, 0), 1);
            const trackWidth = 44;
            const thumbWidth = 16;
            const maxTranslate = trackWidth - thumbWidth;
            hubThumb.style.transform = `translateX(${progress * maxTranslate}px)`;
        };
        hubGrid.addEventListener('scroll', updateHubScroll, { passive: true });
        window.addEventListener('resize', updateHubScroll);
        updateHubScroll();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


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
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 16;
$total_providers = 0;
$total_pages = 1;
$offset = 0;

if (!function_exists('get_home_page_url')) {
    function get_home_page_url(int $p, int $filter_dist = 0, string $filter_q = '', int $filter_cat = 0): string {
        $params = [];
        if ($filter_dist > 0) $params['kecamatan'] = $filter_dist;
        if (!empty($filter_q)) $params['q'] = $filter_q;
        if ($filter_cat > 0) $params['kategori'] = $filter_cat;
        if ($p > 1) $params['page'] = $p;
        $qs = http_build_query($params);
        return BASE_URL . '/' . ($qs ? '?' . $qs : '') . '#penyedia';
    }
}

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

    // 3. Penyedia Jasa dengan Filter Dinamis (Hanya akun terverifikasi)
    $prov_where = ["u.is_active = 1", "u.email_verified_at IS NOT NULL"];
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

    // Hitung Total Mitra Sesuai Filter
    $countSql = "
        SELECT COUNT(*)
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        JOIN service_categories sc ON sp.primary_category_id = sc.id
        LEFT JOIN districts d ON sp.district_id = d.id
        WHERE " . implode(' AND ', $prov_where);
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute($prov_params);
    $total_providers = (int)$stmtCount->fetchColumn();
    $total_pages = max(1, (int)ceil($total_providers / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $per_page;

    $sqlProv = "
        SELECT sp.id, sp.user_id, sp.primary_category_id, sp.business_name, sp.image_url, sp.headline,
               sp.description, sp.experience_years, sp.hourly_rate_min, sp.hourly_rate_max,
               sp.is_verified, sp.id_card_type, sp.school_name, sp.credential_title, sp.certificate_url,
               sp.rating_avg, sp.reviews_count, sp.completed_jobs, sp.district_id, sp.village_id,
               sp.serves_home_visit, sp.has_physical_store,
               u.name as owner_name, u.phone, p.avatar as owner_avatar, sc.name as category_name, sc.icon as category_icon,
               d.name as district_name, v.name as village_name,
               (SELECT COUNT(*) FROM provider_portfolios pp WHERE pp.provider_id = sp.id) as portfolio_count
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN profiles p ON p.user_id = u.id
        JOIN service_categories sc ON sp.primary_category_id = sc.id
        LEFT JOIN districts d ON sp.district_id = d.id
        LEFT JOIN villages v ON sp.village_id = v.id
        WHERE " . implode(' AND ', $prov_where) . "
        ORDER BY sp.is_verified DESC, sp.rating_avg DESC, sp.completed_jobs DESC, sp.id DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";
    $stmtProv = $db->prepare($sqlProv);
    $stmtProv->execute($prov_params);
    $featured_providers = $stmtProv->fetchAll();

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
        'title' => "Bersih Rumah\n& ART",
        'icon' => 'fa-broom',
        'color' => '#0d9488',
        'bg' => '#ccfbf1',
        'link' => BASE_URL . '/search.php?kategori=16'
    ],
    [
        'title' => "Spesialis\nSawit",
        'icon' => 'fa-tree',
        'color' => '#16a34a',
        'bg' => '#dcfce7',
        'link' => BASE_URL . '/search.php?q=sawit'
    ],
    [
        'title' => "Kuliner &\nKatering",
        'icon' => 'fa-utensils',
        'color' => '#ea580c',
        'bg' => '#ffedd5',
        'link' => BASE_URL . '/search.php?kategori=120019'
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

// Kecamatan Populer untuk Pill Filter (Disesuaikan dengan sebaran mitra aktif)
$popular_districts = [
    ['id' => 0, 'name' => '📍 Semua Wilayah'],
    ['id' => 3, 'name' => 'Pasir Penyu (Air Molek)'],
    ['id' => 1, 'name' => 'Rengat'],
    ['id' => 4, 'name' => 'Seberida (Belilas)'],
    ['id' => 13, 'name' => 'Lirik'],
    ['id' => 2, 'name' => 'Rengat Barat (Pematang Reba)'],
    ['id' => 7, 'name' => 'Kelayang'],
    ['id' => 9, 'name' => 'Peranap'],
    ['id' => 11, 'name' => 'Kuala Cenaku']
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
                                    <?php if (has_media($b['image_url'] ?? '')): ?>
                                        <div class="banner-slide-wrapper position-relative h-100 w-100 overflow-hidden">
                                            <img src="<?= e(get_media_url($b['image_url'])) ?>" class="d-block w-100 h-100 banner-img-responsive" alt="Banner Promo">
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
                        $st_has_img = has_media($side_top_banner['image_url'] ?? '');
                        ?>
                        <a href="<?= $st_link ?>" class="shopee-side-banner shadow-sm <?= !$st_has_img ? 'shopee-side-banner-emergency' : '' ?>" style="<?= (!$st_has_img && !empty($side_top_banner['badge_color'])) ? 'background: linear-gradient(135deg, #0f172a 0%, ' . e($side_top_banner['badge_color']) . ' 100%);' : '' ?>">
                            <?php if ($st_has_img): ?>
                                <img src="<?= e(get_media_url($side_top_banner['image_url'])) ?>" alt="<?= e($side_top_banner['title']) ?>" class="shopee-side-banner-img">
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
                        $sb_has_img = has_media($side_bottom_banner['image_url'] ?? '');
                        ?>
                        <a href="<?= $sb_link ?>" class="shopee-side-banner shadow-sm <?= !$sb_has_img ? 'shopee-side-banner-partner' : '' ?>" style="<?= (!$sb_has_img && !empty($side_bottom_banner['badge_color'])) ? 'background: linear-gradient(135deg, #042f2e 0%, ' . e($side_bottom_banner['badge_color']) . ' 100%);' : '' ?>">
                            <?php if ($sb_has_img): ?>
                                <img src="<?= e(get_media_url($side_bottom_banner['image_url'])) ?>" alt="<?= e($side_bottom_banner['title']) ?>" class="shopee-side-banner-img">
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
                <h3 class="fw-bold mb-0 text-dark fs-6 fs-md-4 d-flex align-items-center gap-1.5 flex-wrap">
                    <span>Penyedia Jasa di Indragiri Hulu</span>
                    <span class="badge bg-light text-teal border rounded-pill" style="font-size: 0.68rem; font-weight: 600;">
                        <?= $total_providers ?> Mitra
                    </span>
                    <?php if ($total_pages > 1): ?>
                        <span class="badge bg-teal-subtle text-teal border border-teal-subtle rounded-pill" style="font-size: 0.65rem; font-weight: 600;">
                            Hal. <?= $page ?>/<?= $total_pages ?>
                        </span>
                    <?php endif; ?>
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
                    (Ditemukan <strong><?= $total_providers ?></strong> mitra)
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
                            <?php $prov_cover = get_provider_cover_url($prov); ?>
                            <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $prov['id'] ?>" class="provider-cover-wrap position-relative d-block text-decoration-none">
                                <?php if (!empty($prov_cover)): ?>
                                    <img src="<?= e($prov_cover) ?>" alt="<?= e($prov['business_name']) ?>" class="provider-cover-img" loading="lazy">
                                <?php else: ?>
                                    <div class="provider-cover-fallback d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #0d9488 0%, #0f172a 100%);">
                                        <div class="text-center text-white p-2 p-md-3">
                                            <i class="fa-solid <?= e($prov['category_icon'] ?: 'fa-wrench') ?> fs-2 fs-md-1 opacity-75 mb-1"></i>
                                            <div class="small fw-semibold opacity-90 d-none d-sm-block"><?= e($prov['category_name']) ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Floating Badges on Photo (Modern Clean & Responsive) -->
                                <div class="position-absolute top-0 start-0 m-1.5 m-md-2">
                                    <span class="badge bg-dark bg-opacity-75 text-white backdrop-blur py-0.5 px-1.5 rounded-pill shadow-xs d-inline-flex align-items-center gap-1" style="font-size: 0.65rem;">
                                        <i class="fa-solid fa-star text-warning"></i>
                                        <span class="fw-bold"><?= number_format((float)$prov['rating_avg'], 1) ?></span>
                                    </span>
                                </div>
                                
                                <div class="position-absolute top-0 end-0 m-1.5 m-md-2">
                                    <?= render_provider_service_type_badge($prov, 'xs') ?>
                                </div>

                                <!-- Starting Rate Tag Overlay on Cover Photo -->
                                <div class="position-absolute bottom-0 start-0 m-1.5 m-md-2">
                                    <span class="badge bg-dark bg-opacity-75 text-white backdrop-blur py-0.5 px-2 rounded-pill shadow-xs" style="font-size: 0.66rem;">
                                        <i class="fa-solid fa-tag text-warning me-0.5"></i> <?= (!empty($prov['hourly_rate_min']) && $prov['hourly_rate_min'] > 0) ? 'Mulai Rp ' . number_format((float)$prov['hourly_rate_min'], 0, ',', '.') : 'Tarif Fleksibel' ?>
                                    </span>
                                </div>
                            </a>

                            <!-- Card Body -->
                            <div class="p-2 p-md-3 d-flex flex-column flex-grow-1">
                                <!-- Kategori Jasa (Baris Sendiri - Tidak Kepotong) -->
                                <div class="mb-1">
                                    <span class="badge bg-light text-secondary border small text-truncate" style="font-size: 0.63rem; padding: 2px 6px; max-width: 100%;">
                                        <?= e($prov['category_name']) ?>
                                    </span>
                                </div>

                                <!-- Nama Toko / Usaha + Centang Verifikasi KTP -->
                                <h5 class="fw-bold mb-1 text-dark lh-sm text-truncate-2" style="font-size: 0.86rem; min-height: 2.1rem;">
                                    <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $prov['id'] ?>" class="text-decoration-none text-dark hover-teal">
                                        <?= e($prov['business_name']) ?>
                                    </a>
                                    <?php if ($prov['is_verified']): ?>
                                        <i class="fa-solid fa-circle-check text-success ms-0.5" style="font-size: 0.75rem;" title="Terverifikasi KTP Resmi"></i>
                                    <?php endif; ?>
                                </h5>

                                <div class="d-flex align-items-center gap-1 text-muted small text-truncate mb-2" style="font-size: 0.70rem;" title="<?= e((!empty($prov['village_name']) ? $prov['village_name'] . ', ' : '') . 'Kec. ' . ($prov['district_name'] ?: 'Inhu')) ?>">
                                    <i class="fa-solid fa-location-dot text-danger flex-shrink-0" style="font-size: 0.65rem;"></i>
                                    <span class="text-truncate">
                                        <?= !empty($prov['village_name']) ? '<span class="fw-medium text-dark">' . e($prov['village_name']) . '</span>, ' : '' ?>Kec. <?= e($prov['district_name'] ?: 'Inhu') ?>
                                    </span>
                                </div>

                                <!-- Headline / Slogan (Tampil di Desktop & Tablet) -->
                                <p class="small text-muted mb-2 flex-grow-1 d-none d-md-block" style="line-height: 1.45; font-size: 0.84rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= e($prov['headline'] ?: $prov['description']) ?>
                                </p>

                                <!-- Tombol Portofolio (Tampil di Desktop jika ada) -->
                                <?php if (!empty($prov['portfolio_count']) && $prov['portfolio_count'] > 0): ?>
                                    <div class="mb-2 d-none d-md-block">
                                        <button type="button" class="btn btn-sm btn-outline-teal w-100 py-1 rounded-3 d-flex align-items-center justify-content-center gap-1.5 fw-semibold" onclick="openPortfolioModal(<?= $prov['id'] ?>)">
                                            <i class="fa-solid fa-images"></i>
                                            <span>Lihat Bukti Hasil Kerja (<?= (int)$prov['portfolio_count'] ?> Portofolio)</span>
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Info Ringkas: Estimasi & Pekerjaan Selesai -->
                                <div class="d-flex align-items-center justify-content-between pt-1.5 pb-1.5 border-top border-light-subtle mt-auto mb-2">
                                    <div>
                                        <div class="text-muted" style="font-size: 0.60rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.2px;">Estimasi</div>
                                        <div class="fw-bold text-teal" style="font-size: 0.80rem;">
                                            <?php if (!empty($prov['hourly_rate_min']) && $prov['hourly_rate_min'] > 0): ?>
                                                Rp <?= number_format((float)$prov['hourly_rate_min'], 0, ',', '.') ?>
                                            <?php else: ?>
                                                Tarif Fleksibel
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-light text-secondary border fw-medium px-1.5 py-0.5" style="font-size: 0.64rem;">
                                            <i class="fa-solid fa-check text-success me-0.5"></i> <?= (int)$prov['completed_jobs'] ?> Selesai
                                        </span>
                                    </div>
                                </div>

                                <!-- Action Buttons: 2 Tombol Sejajar (Chat & Pesan - Tanpa Teks Terpotong/Numpuk) -->
                                <div class="row g-1 g-md-2 mt-auto">
                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/chat.php?provider_id=<?= $prov['id'] ?>" class="btn btn-outline-teal w-100 py-1.5 py-md-2 px-1 fw-semibold shadow-xs d-flex align-items-center justify-content-center gap-1 rounded-3 text-nowrap" style="font-size: 0.74rem;">
                                            <i class="fa-solid fa-comments"></i> <span>Chat</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-teal text-white w-100 py-1.5 py-md-2 px-1 fw-semibold shadow-xs d-flex align-items-center justify-content-center gap-1 rounded-3 text-nowrap" style="font-size: 0.74rem;" onclick="openDirectQuickOrderModal(<?= htmlspecialchars(json_encode([
                                            'id' => (int)$prov['id'],
                                            'name' => $prov['business_name'],
                                            'phone' => $prov['phone'],
                                            'category' => $prov['category_name'],
                                            'district' => $prov['district_name'] ?: 'Kab. Indragiri Hulu',
                                            'rate' => (!empty($prov['hourly_rate_min']) && $prov['hourly_rate_min'] > 0) ? 'Mulai Rp ' . number_format((float)$prov['hourly_rate_min'], 0, ',', '.') : 'Sesuai Kesepakatan'
                                        ]), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="fa-solid fa-bolt text-warning"></i> <span>Pesan<span class="d-none d-sm-inline"> Jasa</span></span>
                                        </button>
                                    </div>
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

        <!-- Pagination Navigasi Halaman Modern -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-4 pt-3 border-top d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3">
                <div class="small text-muted text-center text-sm-start">
                    Menampilkan <strong><?= $offset + 1 ?>-<?= min($offset + count($featured_providers), $total_providers) ?></strong> dari <strong><?= $total_providers ?></strong> Mitra Jasa
                </div>
                <nav aria-label="Navigasi Halaman Mitra">
                    <ul class="pagination pagination-sm m-0 gap-1 justify-content-center">
                        <!-- Tombol Sebelumnya -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link rounded-3 px-2.5 py-1.5 <?= ($page <= 1) ? 'text-muted bg-light border-0' : 'text-teal fw-semibold border' ?>" 
                               href="<?= ($page > 1) ? get_home_page_url($page - 1, $filter_dist, $filter_q, $filter_cat) : '#' ?>" 
                               tabindex="<?= ($page <= 1) ? '-1' : '0' ?>" aria-label="Halaman Sebelumnya">
                                <i class="fa-solid fa-chevron-left me-1"></i> Prev
                            </a>
                        </li>

                        <!-- Nomor Halaman -->
                        <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            if ($start_page > 1): 
                        ?>
                            <li class="page-item">
                                <a class="page-link rounded-3 px-2.5 py-1.5 fw-semibold text-dark border" href="<?= get_home_page_url(1, $filter_dist, $filter_q, $filter_cat) ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link rounded-3 px-2 py-1.5 border-0 bg-transparent text-muted">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                            <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                                <a class="page-link rounded-3 px-3 py-1.5 fw-bold <?= ($p === $page) ? 'bg-teal border-teal text-white shadow-xs' : 'text-dark border' ?>" 
                                   href="<?= get_home_page_url($p, $filter_dist, $filter_q, $filter_cat) ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link rounded-3 px-2 py-1.5 border-0 bg-transparent text-muted">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link rounded-3 px-2.5 py-1.5 fw-semibold text-dark border" href="<?= get_home_page_url($total_pages, $filter_dist, $filter_q, $filter_cat) ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <!-- Tombol Selanjutnya -->
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link rounded-3 px-2.5 py-1.5 <?= ($page >= $total_pages) ? 'text-muted bg-light border-0' : 'text-teal fw-semibold border' ?>" 
                               href="<?= ($page < $total_pages) ? get_home_page_url($page + 1, $filter_dist, $filter_q, $filter_cat) : '#' ?>" 
                               tabindex="<?= ($page >= $total_pages) ? '-1' : '0' ?>" aria-label="Halaman Selanjutnya">
                                Next <i class="fa-solid fa-chevron-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</section>



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

<!-- 7. Modal Pesan Jasa Langsung Modern (1-Click Instant Order Modal) -->
<?php
$user_saved_address = '';
if (!empty($current_user)) {
    $addr_parts = [];
    if (!empty($current_user['address'])) $addr_parts[] = $current_user['address'];
    if (!empty($current_user['village_name'])) $addr_parts[] = 'Desa ' . $current_user['village_name'];
    if (!empty($current_user['district_name'])) $addr_parts[] = 'Kec. ' . $current_user['district_name'];
    $user_saved_address = implode(', ', $addr_parts);
}
?>
<div class="modal fade" id="directQuickOrderModal" tabindex="-1" aria-labelledby="directQuickOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 530px;">
        <div class="modal-content border-0 shadow-2xl rounded-4 overflow-hidden">
            <!-- Header Modal Modern dengan Gradien & Mini Provider Card -->
            <div class="modal-header border-0 text-white p-3 p-md-3.5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #0d9488 0%, #064e3b 100%);">
                <div class="position-absolute end-0 top-0 bottom-0 opacity-10 d-none d-sm-block pe-3 pt-2" style="pointer-events: none;">
                    <i class="fa-solid fa-bolt" style="font-size: 8rem; transform: rotate(-15deg);"></i>
                </div>
                <div class="position-relative z-1 w-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-white bg-opacity-20 text-white backdrop-blur rounded-pill px-2.5 py-1 small fw-bold">
                            <i class="fa-solid fa-bolt text-warning me-1"></i> Pesan Jasa Kilat
                        </span>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Mini Provider Card di Header -->
                    <div class="d-flex align-items-center gap-2.5 p-2.5 rounded-3 bg-white bg-opacity-10 backdrop-blur border border-white border-opacity-20">
                        <div class="rounded-3 overflow-hidden bg-white shadow-xs flex-shrink-0 d-flex align-items-center justify-content-center text-teal" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-store fs-4" id="quickOrderModalProvIcon"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="fw-bold text-white mb-0 text-truncate fs-6" id="quickOrderModalProviderName">Nama Mitra</h6>
                            <div class="text-white-50 small d-flex align-items-center gap-1.5 flex-wrap" style="font-size: 0.74rem;">
                                <span><i class="fa-solid fa-tag text-warning me-0.5"></i> <span id="quickOrderModalCategory">Kategori</span></span>
                                <span>&bull;</span>
                                <span><i class="fa-solid fa-location-dot text-danger me-0.5"></i> Kec. <span id="quickOrderModalDistrict">Kecamatan</span></span>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <span class="badge bg-white text-teal fw-bold rounded-pill px-2 py-1 shadow-xs" style="font-size: 0.72rem;" id="quickOrderModalRate">Tarif Fleksibel</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body Modal: Form Input State -->
            <div class="modal-body p-3 p-md-3.5" id="quickOrderFormBody" style="background: #f8fafc;">
                
                <!-- Alert Error Asinkron -->
                <div id="quickOrderAlert" class="alert alert-danger d-none py-2.5 px-3 small align-items-center mb-3 rounded-3 shadow-xs border-0" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
                    <span id="quickOrderAlertText"></span>
                </div>

                <?php if (!is_logged_in()): ?>
                    <!-- State: Belum Login -->
                    <div class="card border-0 shadow-xs rounded-4 p-3 mb-3 bg-white">
                        <div class="d-flex gap-3 align-items-center">
                            <div class="rounded-circle bg-warning bg-opacity-15 text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                <i class="fa-solid fa-user-lock fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-dark small">Masuk untuk Menyelesaikan Pesanan</div>
                                <div class="text-muted" style="font-size: 0.74rem;">Mitra memerlukan kontak Anda untuk konfirmasi jadwal pengerjaan di Inhu.</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3 pt-2 border-top">
                            <button type="button" class="btn btn-sm btn-teal flex-fill fw-bold py-1.5 rounded-pill shadow-xs" onclick="switchToLoginFromQuickOrder()">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Masuk Sekarang
                            </button>
                            <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-outline-secondary flex-fill fw-semibold py-1.5 rounded-pill">
                                Daftar Baru
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- 1. SEGMENTED SWITCH: PESAN UNTUK SAYA SENDIRI vs ORANG LAIN -->
                    <div class="mb-3">
                        <div class="d-flex p-1 rounded-pill border shadow-2xs" style="background: #e2e8f0;">
                            <button type="button" class="btn btn-sm flex-fill rounded-pill py-1.5 fw-bold bg-white text-teal shadow-xs transition-all" id="modeSelfBtnIndex" onclick="setOrderRecipientModeIndex('self')">
                                <i class="fa-solid fa-house-user me-1"></i> Untuk Saya Sendiri
                            </button>
                            <button type="button" class="btn btn-sm flex-fill rounded-pill py-1.5 fw-semibold text-secondary transition-all" id="modeOtherBtnIndex" onclick="setOrderRecipientModeIndex('other')">
                                <i class="fa-solid fa-user-group me-1"></i> Pesan untuk Orang Lain
                            </button>
                        </div>
                    </div>

                    <!-- Detail Pemesan: Untuk Saya Sendiri -->
                    <div id="orderSelfRecipientBoxIndex" class="p-2.5 px-3 rounded-3 bg-white border shadow-2xs mb-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-teal bg-opacity-10 text-teal d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-size: 0.9rem;">
                                <i class="fa-solid fa-user-check"></i>
                            </div>
                            <div class="small">
                                <span class="text-muted" style="font-size: 0.72rem;">Kontak Pemesan:</span>
                                <div class="fw-bold text-dark lh-1"><?= e($current_user['name']) ?> <span class="fw-normal text-muted">(<?= e($current_user['phone']) ?>)</span></div>
                            </div>
                        </div>
                        <span class="badge bg-success-subtle text-success rounded-pill fw-semibold" style="font-size: 0.68rem;">
                            <i class="fa-solid fa-circle-check me-0.5"></i> Akun Anda
                        </span>
                    </div>

                    <!-- Detail Pemesan: Untuk Orang Lain / Kerabat (Hidden by default) -->
                    <div id="orderOtherRecipientBoxIndex" class="card border border-teal-subtle shadow-xs rounded-3 p-3 mb-3 bg-white d-none">
                        <div class="fw-bold text-teal small mb-2 d-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-user-pen"></i>
                            <span>Data Kerabat / Orang yang Dituju di Inhu:</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted mb-1" style="font-size: 0.72rem;">Nama Penerima Jasa <span class="text-danger">*</span></label>
                                <input type="text" id="orderOtherNameIndex" class="form-control form-control-sm rounded-3 py-1.5" placeholder="Contoh: Ibu Rini / Teman">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted mb-1" style="font-size: 0.72rem;">No. WhatsApp Penerima <span class="text-danger">*</span></label>
                                <input type="tel" id="orderOtherPhoneIndex" class="form-control form-control-sm rounded-3 py-1.5" placeholder="08xxxxxxxxxx">
                            </div>
                        </div>
                        <div class="text-muted mt-1.5" style="font-size: 0.68rem;">
                            <i class="fa-solid fa-info-circle me-0.5 text-teal"></i> Teknisi akan menghubungi nomor ini saat hendak tiba di lokasi.
                        </div>
                    </div>
                <?php endif; ?>

                <form id="directQuickOrderForm" onsubmit="event.preventDefault(); submitDirectQuickOrder();">
                    <input type="hidden" id="quickOrderProviderId" value="">
                    <input type="hidden" id="quickOrderMapsUrlIndex" value="">

                    <!-- KEBUTUHAN JASA DENGAN QUICK CHIPS -->
                    <div class="card border-0 shadow-2xs rounded-3 p-3 mb-3 bg-white">
                        <label class="form-label fw-bold text-dark small mb-1.5 d-flex align-items-center justify-content-between" for="quickOrderProblem">
                            <span><i class="fa-solid fa-clipboard-list text-teal me-1.5"></i> Apa yang perlu dikerjakan? <span class="text-danger">*</span></span>
                            <span class="text-muted fw-normal" style="font-size: 0.68rem;">Pilih saran / ketik</span>
                        </label>

                        <!-- Quick Suggestion Chips -->
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button type="button" class="btn btn-xs rounded-pill border bg-light text-secondary fw-semibold py-0.5 px-2 hover-chip" style="font-size: 0.68rem;" onclick="appendQuickProblemIndex('Servis & Cuci Berkala')">+ Cuci / Servis Berkala</button>
                            <button type="button" class="btn btn-xs rounded-pill border bg-light text-secondary fw-semibold py-0.5 px-2 hover-chip" style="font-size: 0.68rem;" onclick="appendQuickProblemIndex('Perbaikan Kerusakan')">+ Perbaikan Kerusakan</button>
                            <button type="button" class="btn btn-xs rounded-pill border bg-light text-secondary fw-semibold py-0.5 px-2 hover-chip" style="font-size: 0.68rem;" onclick="appendQuickProblemIndex('Pemeriksaan / Cek Dahulu')">+ Cek Dahulu</button>
                            <button type="button" class="btn btn-xs rounded-pill border bg-light text-secondary fw-semibold py-0.5 px-2 hover-chip" style="font-size: 0.68rem;" onclick="appendQuickProblemIndex('Panggilan Darurat / Cepat')">+ Darurat / Cepat</button>
                        </div>

                        <textarea class="form-control border-light-subtle rounded-3" id="quickOrderProblem" rows="2" placeholder="Jelaskan kendala, kebutuhan, atau perbaikan yang dicari..." style="font-size: 0.82rem;" required <?= !is_logged_in() ? 'disabled' : '' ?>></textarea>
                    </div>

                    <!-- PATOKAN ALAMAT & GPS SMART DETECTION -->
                    <div class="card border-0 shadow-2xs rounded-3 p-3 mb-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-1.5 flex-wrap gap-1">
                            <label class="form-label fw-bold text-dark small mb-0" for="quickOrderAddress">
                                <i class="fa-solid fa-map-location-dot text-danger me-1"></i> Lokasi & Patokan Alamat <span class="text-danger">*</span>
                            </label>
                            
                            <!-- SMART GPS BUTTON -->
                            <button type="button" class="btn btn-xs btn-outline-teal rounded-pill px-2.5 py-1 fw-bold shadow-2xs d-flex align-items-center gap-1.5" id="btnDetectGpsIndex" onclick="detectUserGPSLocationIndex()" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-crosshairs text-teal"></i>
                                <span>Deteksi GPS Saya (Otomatis)</span>
                            </button>
                        </div>

                        <div class="position-relative">
                            <input type="text" class="form-control border-light-subtle rounded-3 py-2" id="quickOrderAddress" placeholder="Ketik patokan jalan/rumah, atau klik tombol GPS di atas..." value="<?= e($user_saved_address) ?>" style="font-size: 0.82rem;" required <?= !is_logged_in() ? 'disabled' : '' ?>>
                        </div>

                        <!-- Status Notifikasi GPS Real-time -->
                        <div id="gpsDetectionStatusIndex" class="<?= !empty($user_saved_address) ? '' : 'd-none' ?> mt-1.5 p-2 rounded-3 small border" style="font-size: 0.72rem; background: #f8fafc;">
                            <?php if (!empty($user_saved_address)): ?>
                                <span class="text-muted"><i class="fa-solid fa-house-chimney text-teal me-1"></i> Alamat otomatis terisi dari profil Anda. Boleh tambahkan nomor rumah/patokan jika perlu.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- PILIHAN WAKTU / JADWAL PENGERJAAN (MODERN PILLS) -->
                    <div class="card border-0 shadow-2xs rounded-3 p-3 mb-3 bg-white">
                        <label class="form-label fw-bold text-dark small mb-2 d-flex align-items-center justify-content-between">
                            <span><i class="fa-regular fa-calendar-check text-primary me-1.5"></i> Waktu Pengerjaan yang Diinginkan</span>
                            <span class="text-teal small" style="font-size: 0.7rem;"><i class="fa-solid fa-handshake me-1"></i> Bayar di tempat usai kerja</span>
                        </label>

                        <div class="d-flex flex-wrap gap-1.5" id="quickOrderTimePickerIndex">
                            <label class="time-picker-pill active flex-fill text-center p-2 rounded-3 border">
                                <input type="radio" name="order_time_radio_index" value="Hari ini secepatnya (Darurat / Mendesak)" class="d-none" checked onchange="selectOrderTimePillIndex(this)">
                                <div class="fw-bold small text-dark" style="font-size: 0.78rem;"><i class="fa-solid fa-bolt text-warning me-1"></i> Secepatnya</div>
                                <div class="text-muted" style="font-size: 0.65rem;">Mendesak / Hari Ini</div>
                            </label>
                            <label class="time-picker-pill flex-fill text-center p-2 rounded-3 border">
                                <input type="radio" name="order_time_radio_index" value="Besok Pagi (08:00 - 12:00)" class="d-none" onchange="selectOrderTimePillIndex(this)">
                                <div class="fw-bold small text-dark" style="font-size: 0.78rem;">🌅 Besok Pagi</div>
                                <div class="text-muted" style="font-size: 0.65rem;">08:00 - 12:00</div>
                            </label>
                            <label class="time-picker-pill flex-fill text-center p-2 rounded-3 border">
                                <input type="radio" name="order_time_radio_index" value="Besok Siang / Sore (13:00 - 17:00)" class="d-none" onchange="selectOrderTimePillIndex(this)">
                                <div class="fw-bold small text-dark" style="font-size: 0.78rem;">🌇 Besok Siang</div>
                                <div class="text-muted" style="font-size: 0.65rem;">13:00 - 17:00</div>
                            </label>
                            <label class="time-picker-pill flex-fill text-center p-2 rounded-3 border">
                                <input type="radio" name="order_time_radio_index" value="Jadwal Fleksibel (Bisa diobrolkan di Chat)" class="d-none" onchange="selectOrderTimePillIndex(this)">
                                <div class="fw-bold small text-dark" style="font-size: 0.78rem;">💬 Fleksibel</div>
                                <div class="text-muted" style="font-size: 0.65rem;">Diskusi di Chat</div>
                            </label>
                        </div>
                        <input type="hidden" id="quickOrderTime" value="Hari ini secepatnya (Darurat / Mendesak)">
                    </div>

                    <!-- TOMBOL KIRIM PESANAN -->
                    <?php if (is_logged_in()): ?>
                        <button type="button" id="btnSubmitQuickOrder" class="btn btn-teal w-100 py-2.5 fw-bold shadow-md rounded-pill d-flex align-items-center justify-content-center gap-2" style="font-size: 0.92rem; background: linear-gradient(135deg, #0d9488 0%, #065f46 100%);" onclick="submitDirectQuickOrder()">
                            <span id="btnQuickOrderSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i id="btnQuickOrderIcon" class="fa-solid fa-paper-plane"></i>
                            <span id="btnQuickOrderText">Kirim Pesanan Sekarang 🚀</span>
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-teal w-100 py-2.5 fw-bold shadow-md rounded-pill d-flex align-items-center justify-content-center gap-2" style="font-size: 0.92rem; background: linear-gradient(135deg, #0d9488 0%, #065f46 100%);" onclick="switchToLoginFromQuickOrder()">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Masuk Akun untuk Kirim Pesanan
                        </button>
                    <?php endif; ?>

                    <div class="text-center text-muted small mt-2" style="font-size: 0.7rem;">
                        <i class="fa-solid fa-shield-halved text-success me-1"></i> Gratis tanpa biaya admin • Hubungkan langsung ke WhatsApp mitra
                    </div>
                </form>
            </div>

            <!-- Body Modal: State Sukses -->
            <div class="modal-body p-4 text-center d-none" id="quickOrderSuccessBody">
                <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 72px; height: 72px;">
                    <i class="fa-solid fa-circle-check fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Pesanan Berhasil Terkirim!</h5>
                <p class="text-muted small mb-3" id="quickOrderSuccessMsg">
                    Pesanan Anda telah diteruskan langsung ke mitra.
                </p>

                <div class="p-3 bg-light rounded-3 border text-start mb-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Nomor Pesanan:</span>
                        <strong class="text-teal" id="quickOrderSuccessId">#</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Penyedia Jasa:</span>
                        <strong class="text-dark" id="quickOrderSuccessProv"></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Status:</span>
                        <span class="badge text-bg-warning">Menunggu Konfirmasi Mitra</span>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <a href="#" id="quickOrderSuccessWaBtn" target="_blank" class="btn btn-success text-white w-100 py-2.5 fw-semibold shadow-xs d-flex align-items-center justify-content-center gap-2 rounded-pill">
                        <i class="fa-brands fa-whatsapp fs-5"></i> <span>Kirim Detail ke WhatsApp Mitra ↗</span>
                    </a>
                    <a href="#" id="quickOrderSuccessChatBtn" class="btn btn-teal text-white w-100 py-2.5 fw-semibold shadow-xs d-flex align-items-center justify-content-center gap-1.5 rounded-pill">
                        <i class="fa-solid fa-comments fs-5"></i> <span>Buka Obrolan di Aplikasi</span>
                    </a>
                    <a href="<?= BASE_URL ?>/user/requests.php" class="btn btn-outline-secondary w-100 btn-sm py-2 fw-semibold rounded-pill">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Lacak Status di Menu Pesanan Saya
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.time-picker-pill {
    cursor: pointer;
    background: #fff;
    transition: all 0.2s ease;
    border-color: #e2e8f0 !important;
}
.time-picker-pill:hover {
    border-color: #0d9488 !important;
    background: #f0fdfa;
}
.time-picker-pill.active {
    border-color: #0d9488 !important;
    background: #f0fdfa;
    box-shadow: 0 0 0 1.5px #0d9488;
}
.hover-chip {
    transition: all 0.15s ease;
}
.hover-chip:hover {
    background-color: #0d9488 !important;
    color: #fff !important;
    border-color: #0d9488 !important;
}
</style>

<script>
<?php
$featured_providers_map = [];
foreach ($featured_providers as $fp) {
    $featured_providers_map[$fp['id']] = [
        'id' => $fp['id'],
        'business_name' => $fp['business_name'],
        'category_name' => $fp['category_name'],
        'district_name' => $fp['district_name'],
        'phone' => $fp['phone'],
        'hourly_rate_min' => $fp['hourly_rate_min'],
    ];
}
?>
window.FEATURED_PROVIDERS = <?= json_encode($featured_providers_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.BASE_URL = "<?= BASE_URL ?>";
window.CSRF_TOKEN = "<?= csrf_token() ?>";
window.IS_LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;

let currentDirectOrderProvider = null;

function formatMediaUrl(path) {
    if (!path) return '';
    if (path.startsWith('data:') || path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }
    return (window.BASE_URL || '') + '/' + path.replace(/^\/+/, '');
}

async function openPortfolioModal(providerId) {
    const prov = window.FEATURED_PROVIDERS[providerId];
    if (!prov) return;

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
    container.innerHTML = `
        <div class="text-center py-5 text-muted">
            <div class="spinner-border text-teal mb-3" role="status">
                <span class="visually-hidden">Memuat...</span>
            </div>
            <div class="fw-semibold">Memuat bukti hasil kerja...</div>
        </div>
    `;

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('portfolioGalleryModal'));
    modal.show();

    try {
        const response = await fetch(`${window.BASE_URL}/api/provider_portfolio.php?provider_id=${providerId}`);
        const result = await response.json();
        const portfolios = (result && result.data) ? result.data : [];

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
                                <img src="${formatMediaUrl(item.image_before)}" alt="Sebelum" class="w-100 h-100 object-fit-cover" style="max-height: 260px;">
                                <span class="badge bg-danger position-absolute top-0 start-0 m-2 fw-bold" style="font-size: 0.65rem;">SEBELUM</span>
                            </div>
                            <div class="col-6 position-relative overflow-hidden" style="background: #0f172a;">
                                <img src="${formatMediaUrl(item.image_after)}" alt="Sesudah" class="w-100 h-100 object-fit-cover" style="max-height: 260px;">
                                <span class="badge bg-success position-absolute top-0 end-0 m-2 fw-bold" style="font-size: 0.65rem;">SESUDAH</span>
                            </div>
                        </div>
                    `;
                } else {
                    beforeAfterHtml = `
                        <div class="position-relative overflow-hidden border-bottom text-center" style="background: #0f172a; max-height: 320px;">
                            <img src="${formatMediaUrl(item.image_after)}" alt="${item.title}" class="img-fluid object-fit-cover w-100" style="max-height: 320px;">
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
                                    <i class="fa-regular fa-calendar me-1"></i> Selesai: ${item.service_date || (item.created_at ? item.created_at.substring(0, 10) : 'Terbaru')}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }
    } catch (err) {
        container.innerHTML = `
            <div class="text-center py-4 text-danger">
                <i class="fa-solid fa-triangle-exclamation fs-2 mb-2"></i>
                <div class="fw-semibold">Gagal memuat portofolio</div>
                <div class="small text-muted">Silakan periksa koneksi internet Anda atau coba lagi.</div>
            </div>
        `;
    }
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

    const iconEl = document.getElementById('quickOrderModalProvIcon');
    if (iconEl && prov.icon) {
        iconEl.className = 'fa-solid ' + prov.icon + ' fs-4';
    }

    setOrderRecipientModeIndex('self');

    const modalEl = document.getElementById('directQuickOrderModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

let orderRecipientModeIndex = 'self';

function setOrderRecipientModeIndex(mode) {
    orderRecipientModeIndex = mode;
    const selfBtn = document.getElementById('modeSelfBtnIndex');
    const otherBtn = document.getElementById('modeOtherBtnIndex');
    const otherForm = document.getElementById('orderOtherRecipientBoxIndex');
    const selfInfo = document.getElementById('orderSelfRecipientBoxIndex');

    if (mode === 'other') {
        if (selfBtn) {
            selfBtn.classList.remove('bg-white', 'text-teal', 'shadow-xs', 'fw-bold');
            selfBtn.classList.add('text-secondary', 'fw-semibold');
        }
        if (otherBtn) {
            otherBtn.classList.remove('text-secondary', 'fw-semibold');
            otherBtn.classList.add('bg-white', 'text-teal', 'shadow-xs', 'fw-bold');
        }
        if (otherForm) otherForm.classList.remove('d-none');
        if (selfInfo) selfInfo.classList.add('d-none');
    } else {
        if (otherBtn) {
            otherBtn.classList.remove('bg-white', 'text-teal', 'shadow-xs', 'fw-bold');
            otherBtn.classList.add('text-secondary', 'fw-semibold');
        }
        if (selfBtn) {
            selfBtn.classList.remove('text-secondary', 'fw-semibold');
            selfBtn.classList.add('bg-white', 'text-teal', 'shadow-xs', 'fw-bold');
        }
        if (otherForm) otherForm.classList.add('d-none');
        if (selfInfo) selfInfo.classList.remove('d-none');
    }
}

function appendQuickProblemIndex(text) {
    const area = document.getElementById('quickOrderProblem');
    if (!area) return;
    if (area.value.trim() === '') {
        area.value = text;
    } else if (!area.value.includes(text)) {
        area.value = area.value.trim() + ', ' + text;
    }
    area.focus();
}

function selectOrderTimePillIndex(radioInput) {
    document.querySelectorAll('#quickOrderTimePickerIndex .time-picker-pill').forEach(pill => {
        pill.classList.remove('active');
    });
    radioInput.closest('.time-picker-pill').classList.add('active');
    document.getElementById('quickOrderTime').value = radioInput.value;
}

function detectUserGPSLocationIndex() {
    const btn = document.getElementById('btnDetectGpsIndex');
    const statusBox = document.getElementById('gpsDetectionStatusIndex');
    const addressInput = document.getElementById('quickOrderAddress');
    const hiddenMapsUrl = document.getElementById('quickOrderMapsUrlIndex');

    if (!navigator.geolocation) {
        alert("Perangkat atau browser Anda tidak mendukung akses GPS.");
        return;
    }

    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mencari GPS...';

    statusBox.classList.remove('d-none');
    statusBox.className = 'mt-1.5 p-2 rounded-3 small bg-light border text-muted d-flex align-items-center gap-2';
    statusBox.innerHTML = '<span class="spinner-border spinner-border-sm text-teal"></span> <span>Sedang menghubungkan ke satelit GPS HP Anda...</span>';

    navigator.geolocation.getCurrentPosition(
        async function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy || 15);
            const mapsUrl = `https://maps.google.com/?q=${lat},${lng}`;
            hiddenMapsUrl.value = mapsUrl;

            let placeName = '';
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data && data.display_name) {
                        const parts = data.display_name.split(',');
                        placeName = parts.slice(0, 4).join(',').trim();
                    }
                }
            } catch(e) {}

            const gpsLabel = placeName ? `${placeName}` : `Lokasi Saya (GPS: ${lat.toFixed(5)}, ${lng.toFixed(5)})`;
            addressInput.value = gpsLabel;

            statusBox.className = 'mt-1.5 p-2 rounded-3 small bg-success-subtle border border-success-subtle text-success d-flex align-items-center justify-content-between flex-wrap gap-1';
            statusBox.innerHTML = `
                <div>
                    <i class="fa-solid fa-circle-check me-1"></i>
                    <strong>GPS Terkunci (±${acc}m):</strong> Lokasi Anda berhasil diambil otomatis.
                </div>
                <a href="${mapsUrl}" target="_blank" class="fw-semibold text-teal text-decoration-none">
                    <i class="fa-solid fa-map-pin me-0.5"></i> Cek di Maps ↗
                </a>
            `;

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check text-success me-1"></i> GPS Terkunci';
            setTimeout(() => { btn.innerHTML = origHtml; }, 3500);
        },
        function(err) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            statusBox.className = 'mt-1.5 p-2 rounded-3 small bg-warning-subtle border border-warning-subtle text-dark';
            let errMsg = "Izin lokasi tidak diberikan. Silakan ketik patokan alamat secara manual.";
            if (err.code === 1) errMsg = "Akses lokasi ditolak. Silakan izinkan lokasi di browser atau ketik patokan alamat.";
            else if (err.code === 2) errMsg = "Sinyal GPS tidak ditemukan. Silakan ketik patokan alamat.";
            statusBox.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> ${errMsg}`;
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
    );
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
    const mapsUrl = document.getElementById('quickOrderMapsUrlIndex') ? document.getElementById('quickOrderMapsUrlIndex').value : '';

    const alertEl = document.getElementById('quickOrderAlert');
    const alertText = document.getElementById('quickOrderAlertText');

    if (!problem) {
        alertText.textContent = "Mohon jelaskan apa yang perlu diperbaiki atau diservis.";
        alertEl.classList.remove('d-none');
        document.getElementById('quickOrderProblem').focus();
        return;
    }

    if (!address) {
        alertText.textContent = "Mohon cantumkan patokan alamat atau klik tombol Deteksi GPS.";
        alertEl.classList.remove('d-none');
        document.getElementById('quickOrderAddress').focus();
        return;
    }

    let otherName = '';
    let otherPhone = '';
    if (orderRecipientModeIndex === 'other') {
        const nameEl = document.getElementById('orderOtherNameIndex');
        const phoneEl = document.getElementById('orderOtherPhoneIndex');
        otherName = nameEl ? nameEl.value.trim() : '';
        otherPhone = phoneEl ? phoneEl.value.trim() : '';
        if (!otherName) {
            alertText.textContent = "Mohon cantumkan nama kerabat/orang yang memesan.";
            alertEl.classList.remove('d-none');
            if (nameEl) nameEl.focus();
            return;
        }
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
    formData.append('order_for', orderRecipientModeIndex);
    if (orderRecipientModeIndex === 'other') {
        formData.append('recipient_name', otherName);
        formData.append('recipient_phone', otherPhone);
    }
    if (mapsUrl) {
        formData.append('maps_url', mapsUrl);
    }

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
        if (btnText) btnText.textContent = "Kirim Pesanan Sekarang 🚀";
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
            
            const waBtn = document.getElementById('quickOrderSuccessWaBtn');
            if (waBtn && data.whatsapp_url) {
                waBtn.href = data.whatsapp_url;
            }

            // Clear inputs for next time
            document.getElementById('quickOrderProblem').value = '';
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
        if (btnText) btnText.textContent = "Kirim Pesanan Sekarang 🚀";
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


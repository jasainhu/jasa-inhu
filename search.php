<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$q = trim($_GET['q'] ?? '');
$kategori_id = (int)($_GET['kategori'] ?? 0);
$kecamatan_id = (int)($_GET['kecamatan'] ?? 0);
$desa_id = (int)($_GET['desa'] ?? 0);
$filter_mode = trim($_GET['filter'] ?? '');
$sort_mode = trim($_GET['sort'] ?? '');
$min_rating = (float)($_GET['min_rating'] ?? 0);
$service_type = trim($_GET['service_type'] ?? '');

$providers = [];
$categories = [];
$districts = [];
$selected_category = null;
$selected_district = null;

try {
    $db = get_db();

    // 1. Kategori Aktif
    $stmtCat = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    $categories = $stmtCat->fetchAll();

    // 2. Kecamatan di Inhu
    $stmtDist = $db->query("SELECT id, name FROM districts ORDER BY name ASC");
    $districts = $stmtDist->fetchAll();

    if ($kategori_id > 0) {
        foreach ($categories as $c) {
            if ((int)$c['id'] === $kategori_id) {
                $selected_category = $c;
                break;
            }
        }
    }

    if ($kecamatan_id > 0) {
        foreach ($districts as $d) {
            if ((int)$d['id'] === $kecamatan_id) {
                $selected_district = $d;
                break;
            }
        }
    }

    // 3. Query Filter Dinamis (Hanya akun terverifikasi)
    $where = ["u.is_active = 1", "u.email_verified_at IS NOT NULL"];
    $params = [];

    if ($q !== '') {
        $where[] = "(sp.business_name LIKE ? OR sc.name LIKE ? OR sp.description LIKE ?)";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
    }

    if ($kategori_id > 0) {
        $where[] = "sp.primary_category_id = ?";
        $params[] = $kategori_id;
    }

    if ($kecamatan_id > 0) {
        $where[] = "(sp.district_id = ? OR EXISTS (SELECT 1 FROM service_areas sa WHERE sa.provider_id = sp.id AND sa.district_id = ?))";
        $params[] = $kecamatan_id;
        $params[] = $kecamatan_id;
    }

    if ($desa_id > 0) {
        $where[] = "sp.village_id = ?";
        $params[] = $desa_id;
    }

    if ($min_rating > 0) {
        $where[] = "sp.rating_avg >= ?";
        $params[] = $min_rating;
    }

    if ($service_type === 'home_visit') {
        $where[] = "sp.serves_home_visit = 1";
    } elseif ($service_type === 'store') {
        $where[] = "sp.has_physical_store = 1";
    } elseif ($service_type === 'both') {
        $where[] = "sp.serves_home_visit = 1 AND sp.has_physical_store = 1";
    }

    if ($filter_mode === 'verified') {
        $where[] = "sp.is_verified = 1";
    }

    if ($filter_mode === 'siaga') {
        $where[] = "(sp.business_name LIKE '%siaga%' OR sp.description LIKE '%siaga%' OR sp.description LIKE '%24 jam%' OR sp.headline LIKE '%siaga%')";
    }

    if ($filter_mode === 'hemat' || $sort_mode === 'cheap') {
        $where[] = "sp.hourly_rate_min > 0";
    }

    // Sorting
    $order_clause = "sp.is_verified DESC, sp.rating_avg DESC, sp.completed_jobs DESC";
    if ($sort_mode === 'cheap' || $filter_mode === 'hemat') {
        $order_clause = "sp.hourly_rate_min ASC, sp.rating_avg DESC";
    } elseif ($sort_mode === 'rating') {
        $order_clause = "sp.rating_avg DESC, sp.completed_jobs DESC";
    } elseif ($sort_mode === 'jobs') {
        $order_clause = "sp.completed_jobs DESC, sp.rating_avg DESC";
    } elseif ($sort_mode === 'latest') {
        $order_clause = "sp.id DESC";
    }

    $sql = "
        SELECT sp.id, sp.user_id, sp.primary_category_id, sp.business_name, sp.image_url, sp.headline,
               sp.description, sp.experience_years, sp.hourly_rate_min, sp.hourly_rate_max,
               sp.is_verified, sp.id_card_type, sp.school_name, sp.credential_title, sp.certificate_url,
               sp.rating_avg, sp.reviews_count, sp.completed_jobs, sp.district_id, sp.village_id,
               sp.serves_home_visit, sp.has_physical_store,
               sp.address, u.name as owner_name, u.phone, p.avatar as owner_avatar, sc.name as category_name, sc.icon as category_icon,
               d.name as district_name, v.name as village_name,
               (SELECT COUNT(*) FROM provider_portfolios pp WHERE pp.provider_id = sp.id) as portfolio_count
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN profiles p ON p.user_id = u.id
        JOIN service_categories sc ON sp.primary_category_id = sc.id
        LEFT JOIN districts d ON sp.district_id = d.id
        LEFT JOIN villages v ON sp.village_id = v.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$order_clause}
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $providers = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Search page query error: " . $e->getMessage());
}

// Judul Halaman Dinamis
$title_heading = "Semua Mitra Jasa";
$title_badge = "Indragiri Hulu";
if ($filter_mode === 'hemat' || $sort_mode === 'cheap') {
    $title_heading = "Mitra Tarif Hemat & Terjangkau";
    $title_badge = "Tarif Ekonomis";
} elseif ($filter_mode === 'siaga') {
    $title_heading = "Layanan Darurat & Siaga 24 Jam";
    $title_badge = "Siaga 24 Jam";
} elseif ($filter_mode === 'verified') {
    $title_heading = "Mitra Terverifikasi KTP & Usaha";
    $title_badge = "Terverifikasi";
} elseif ($selected_category) {
    $title_heading = "Jasa " . $selected_category['name'];
    $title_badge = "Kategori";
} elseif ($q !== '') {
    $title_heading = 'Pencarian: "' . $q . '"';
    $title_badge = "Hasil Cari";
}

function build_search_url($new_params = []) {
    $current = [
        'q' => $_GET['q'] ?? '',
        'kategori' => $_GET['kategori'] ?? '',
        'kecamatan' => $_GET['kecamatan'] ?? '',
        'filter' => $_GET['filter'] ?? '',
        'sort' => $_GET['sort'] ?? ''
    ];
    $merged = array_merge($current, $new_params);
    $clean = [];
    foreach ($merged as $k => $v) {
        if ($v !== '' && $v !== null && $v !== 0 && $v !== '0') {
            $clean[$k] = $v;
        }
    }
    return BASE_URL . '/search.php' . (!empty($clean) ? '?' . http_build_query($clean) : '');
}

$page_title = $title_heading . " di Inhu | " . APP_NAME;

require_once __DIR__ . '/includes/header.php';
?>

<?php
$active_filter_count = 0;
if ($kategori_id > 0) $active_filter_count++;
if ($kecamatan_id > 0) $active_filter_count++;
if (!empty($filter_mode)) $active_filter_count++;
if (!empty($sort_mode)) $active_filter_count++;

$sort_labels = [
    '' => 'Rekomendasi',
    'cheap' => 'Termurah',
    'rating' => 'Rating Tertinggi',
    'jobs' => 'Terbanyak Selesai',
    'latest' => 'Mitra Terbaru'
];
$current_sort_name = $sort_labels[$sort_mode] ?? 'Urutkan';
?>

<!-- Ultra-Compact Action Bar (Shopee / Airbnb Mobile-First Style) -->
<div class="bg-white border-bottom shadow-2xs">
    <div class="container py-2 d-flex align-items-center justify-content-between gap-2">
        <!-- Kiri: Jumlah Mitra & Query / Kategori -->
        <div class="d-flex align-items-center gap-1.5 min-w-0">
            <span class="fw-bold text-dark fs-6 text-nowrap">
                <?= count($providers) ?>
                <span class="text-muted fw-normal small">Mitra</span>
            </span>
            <?php if ($q !== ''): ?>
                <span class="text-muted small">•</span>
                <span class="badge bg-light text-dark border text-truncate fw-normal px-2 py-1" style="max-width: 160px; font-size: 0.75rem;">
                    "<?= e($q) ?>"
                </span>
            <?php elseif ($selected_category): ?>
                <span class="text-muted small">•</span>
                <span class="text-teal small fw-semibold text-truncate" style="max-width: 140px;">
                    <?= e($selected_category['name']) ?>
                </span>
            <?php elseif ($selected_district): ?>
                <span class="text-muted small">•</span>
                <span class="text-muted small text-truncate" style="max-width: 140px;">
                    Kec. <?= e($selected_district['name']) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Kanan: Urutkan & Filter Drawer Button -->
        <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
            <!-- Dropdown Urutkan Cepat -->
            <div class="dropdown">
                <button class="btn btn-sm <?= !empty($sort_mode) ? 'btn-teal text-white fw-bold' : 'btn-light bg-light border text-secondary' ?> rounded-pill px-2.5 py-1 d-flex align-items-center gap-1 dropdown-toggle" style="font-size: 0.78rem;" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-arrow-down-short-wide <?= !empty($sort_mode) ? 'text-white' : 'text-teal' ?>"></i>
                    <span class="d-none d-sm-inline"><?= e($current_sort_name) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-1 py-1" style="font-size: 0.8rem;">
                    <?php foreach ($sort_labels as $s_key => $s_lbl): ?>
                        <li><a class="dropdown-item py-1.5 <?= $sort_mode === $s_key ? 'active fw-bold' : '' ?>" href="<?= build_search_url(['sort' => $s_key]) ?>"><?= e($s_lbl) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Tombol Buka Bottom Sheet Filter Modal -->
            <button class="btn btn-sm <?= $active_filter_count > 0 ? 'btn-teal text-white fw-bold shadow-xs' : 'btn-white bg-white border text-dark' ?> rounded-pill px-3 py-1 d-flex align-items-center gap-1.5" style="font-size: 0.78rem;" type="button" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fa-solid fa-sliders <?= $active_filter_count > 0 ? 'text-white' : 'text-teal' ?>"></i>
                <span>Filter</span>
                <?php if ($active_filter_count > 0): ?>
                    <span class="badge bg-warning text-dark rounded-pill px-1.5 py-0.5 fw-bold" style="font-size: 0.65rem;"><?= $active_filter_count ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <!-- Active Filter Tags (Muncul Ramping hanya bila ada filter aktif) -->
    <?php if ($active_filter_count > 0 || $q !== ''): ?>
        <div class="bg-light border-top py-1.5 px-3">
            <div class="container d-flex align-items-center gap-1.5 overflow-x-auto" style="scrollbar-width: none; -webkit-overflow-scrolling: touch; white-space: nowrap;">
                <span class="text-muted small me-1 flex-shrink-0" style="font-size: 0.72rem;">Filter aktif:</span>
                
                <?php if ($q !== ''): ?>
                    <a href="<?= build_search_url(['q' => '']) ?>" class="active-filter-badge flex-shrink-0" title="Hapus pencarian">
                        <span>"<?= e($q) ?>"</span>
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>

                <?php if ($selected_district): ?>
                    <a href="<?= build_search_url(['kecamatan' => '']) ?>" class="active-filter-badge flex-shrink-0" title="Hapus filter wilayah">
                        <i class="fa-solid fa-location-dot text-danger" style="font-size: 0.65rem;"></i>
                        <span>Kec. <?= e($selected_district['name']) ?></span>
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>

                <?php if ($selected_category): ?>
                    <a href="<?= build_search_url(['kategori' => '']) ?>" class="active-filter-badge flex-shrink-0" title="Hapus filter kategori">
                        <span><?= e($selected_category['name']) ?></span>
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>

                <?php if ($filter_mode === 'siaga'): ?>
                    <a href="<?= build_search_url(['filter' => '']) ?>" class="active-filter-badge flex-shrink-0" title="Hapus filter siaga">
                        <i class="fa-solid fa-bolt text-warning" style="font-size: 0.65rem;"></i>
                        <span>Siaga 24 Jam</span>
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>

                <?php if ($filter_mode === 'verified'): ?>
                    <a href="<?= build_search_url(['filter' => '']) ?>" class="active-filter-badge flex-shrink-0" title="Hapus filter terverifikasi">
                        <i class="fa-solid fa-circle-check text-success" style="font-size: 0.65rem;"></i>
                        <span>Terverifikasi KTP</span>
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>

                <?php if ($filter_mode === 'hemat'): ?>
                    <a href="<?= build_search_url(['filter' => '']) ?>" class="active-filter-badge flex-shrink-0" title="Hapus filter tarif hemat">
                        <i class="fa-solid fa-tags text-warning" style="font-size: 0.65rem;"></i>
                        <span>Tarif Hemat</span>
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>

                <?php if (!empty($sort_mode)): ?>
                    <a href="<?= build_search_url(['sort' => '']) ?>" class="active-filter-badge flex-shrink-0" title="Reset urutan">
                        <span>Urutan: <?= e($current_sort_name) ?></span>
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/search.php" class="text-danger text-decoration-none fw-semibold ms-2 flex-shrink-0 small" style="font-size: 0.72rem;">
                    Reset Semua
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Grid Kartu Penyedia Jasa (Shopee 2-Kolom di HP & 3-Kolom di Desktop) -->
<div class="container py-2.5 py-md-3">
    <div class="row g-2 g-md-3 g-lg-4">
        <?php if (!empty($providers)): ?>
            <?php foreach ($providers as $prov): ?>
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

                            <!-- Floating Badges on Photo -->
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

                                <!-- Trust & Credibility Badges (KTP & Sertifikat/STR - Sesuai UI Mockup) -->
                                <?php 
                                    $has_verified_ktp = !empty($prov['is_verified']);
                                    $has_certificate = (!empty($prov['certificate_url']) && strlen($prov['certificate_url']) > 5) || (!empty($prov['credential_title']) && strlen($prov['credential_title']) > 2);
                                ?>
                                <?php if ($has_verified_ktp || $has_certificate): ?>
                                    <div class="provider-trust-row">
                                        <?php if ($has_verified_ktp): ?>
                                            <span class="badge-trust-ktp" title="Identitas KTP Resmi Terverifikasi Admin JASA INHU">
                                                <i class="fa-solid <?= (($prov['id_card_type'] ?? '') === 'pelajar') ? 'fa-id-badge' : 'fa-id-card' ?>"></i>
                                                <span><?= (($prov['id_card_type'] ?? '') === 'pelajar') ? 'Kartu Pelajar' : 'KTP Terverifikasi' ?></span>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($has_certificate): ?>
                                            <span class="badge-trust-cert" title="Memiliki Sertifikat Keahlian / STR Resmi">
                                                <i class="fa-solid fa-graduation-cap"></i>
                                                <span class="text-truncate" style="max-width: 130px;"><?= !empty($prov['credential_title']) ? e($prov['credential_title']) : 'Bersertifikat' ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

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

                                <!-- Action Buttons: 2 Tombol Sejajar (Chat & Pesan - Sesuai Mockup) -->
                                <div class="row g-1 g-md-2 mt-auto">
                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/chat.php?provider_id=<?= $prov['id'] ?>" class="btn btn-outline-teal w-100 py-1.5 py-md-2 px-1 fw-bold shadow-xs d-flex align-items-center justify-content-center gap-1 rounded-pill text-nowrap" style="font-size: 0.74rem;">
                                            <i class="fa-regular fa-comment-dots"></i> <span>Obrolan</span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-teal text-white w-100 py-1.5 py-md-2 px-1 fw-bold shadow-xs d-flex align-items-center justify-content-center gap-1 rounded-pill text-nowrap" style="font-size: 0.74rem;" onclick="openDirectQuickOrderModal(<?= htmlspecialchars(json_encode([
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
            <!-- Empty State -->
            <div class="col-12">
                <div class="empty-state-modern p-4 p-md-5 rounded-4 border bg-white shadow-sm text-center">
                    <div class="empty-state-icon-circle mx-auto mb-3" style="width: 70px; height: 70px; background: #f0fdfa; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-magnifying-glass text-teal fs-2"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Mitra Jasa Tidak Ditemukan</h5>
                    <p class="text-secondary small mb-4 mx-auto" style="max-width: 450px;">
                        Belum ada penyedia jasa yang sesuai dengan filter atau kata kunci pencarian Anda saat ini di Indragiri Hulu.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="<?= BASE_URL ?>/search.php" class="btn btn-primary-custom px-3 py-2 fw-semibold shadow-xs">
                            <i class="fa-solid fa-rotate-left me-1"></i> Tampilkan Semua Mitra
                        </a>
                        <a href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>?text=Halo%20Admin%20Jasa%20Inhu,%20saya%20mencari%20jasa%20di%20wilayah%20saya%20tetapi%20belum%20menemukan%20mitra%20yang%20sesuai.%20Bisa%20bantu%20rekomendasikan?" target="_blank" class="btn btn-success px-3 py-2 fw-semibold shadow-xs">
                            <i class="fa-brands fa-whatsapp me-1"></i> Bantuan Admin via WA
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Banner Promosi Usaha Lokal (Desain Grafis Penuh - Responsif Mobile & Desktop) -->
    <div class="mt-4 pt-1 mb-2">
        <a href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>?text=<?= urlencode("Halo Admin JASA INHU 🙏 Saya tertarik tanya info pemasangan banner / promosi toko di website Jasa Inhu. Boleh minta info penempatannya?") ?>" 
           target="_blank" 
           class="d-block text-decoration-none rounded-3 rounded-md-4 overflow-hidden shadow-sm transition-hover position-relative" 
           style="background: linear-gradient(135deg, #042f2e 0%, #0d9488 100%);" 
           title="Pasang Iklan & Promosi Toko di JASA INHU">
            <picture>
                <source media="(max-width: 767.98px)" srcset="<?= BASE_URL ?>/uploads/banners/banner_promo_mobile_v3.jpg">
                <source media="(min-width: 768px)" srcset="<?= BASE_URL ?>/uploads/banners/banner_promo_desktop_panoramic.jpg">
                <img src="<?= BASE_URL ?>/uploads/banners/banner_promo_desktop_panoramic.jpg" 
                     alt="Pasang Iklan & Promosi Toko di JASA INHU" 
                     class="w-100 d-block shadow-xs" 
                     style="border-radius: inherit; width: 100%; height: auto; display: block;" 
                     loading="lazy">
            </picture>
        </a>
    </div>
</div>

<!-- Modal Bottom Sheet: Filter Lengkap (Mobile Drawer ala Shopee / Airbnb & Modal di Desktop) -->
<div class="modal fade modal-bottom-sheet" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Drag Handle Bar untuk Mobile -->
            <div class="filter-sheet-drag-handle d-md-none"></div>

            <div class="modal-header border-bottom py-2.5 px-3 px-md-4 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-teal-subtle text-teal d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="filterModalLabel">Filter Pencarian</h6>
                        <span class="text-muted small" style="font-size: 0.75rem;">Sesuaikan kriteria mitra jasa di Kab. Inhu</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
                <form action="<?= BASE_URL ?>/search.php" method="GET" id="searchFilterForm">
                    <input type="hidden" name="q" value="<?= e($q) ?>">

                    <!-- 1. Filter Wilayah / Kecamatan (14 Kecamatan di Kab. Inhu) -->
                    <div class="mb-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="fw-bold text-dark small mb-0 d-flex align-items-center gap-1.5">
                                <i class="fa-solid fa-location-dot text-danger"></i> Wilayah / Kecamatan
                            </label>
                            <span class="text-muted small" style="font-size: 0.7rem;">Kab. Indragiri Hulu</span>
                        </div>
                        <div class="d-flex flex-wrap gap-1.5">
                            <input type="radio" class="btn-check" name="kecamatan" id="f_kec_0" value="" autocomplete="off" <?= $kecamatan_id === 0 ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_kec_0">Semua Inhu</label>

                            <?php foreach ($districts as $d): ?>
                                <input type="radio" class="btn-check" name="kecamatan" id="f_kec_<?= $d['id'] ?>" value="<?= $d['id'] ?>" autocomplete="off" <?= $kecamatan_id === (int)$d['id'] ? 'checked' : '' ?>>
                                <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_kec_<?= $d['id'] ?>">Kec. <?= e($d['name']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <hr class="my-3 opacity-10">

                    <!-- 2. Filter Kategori Jasa -->
                    <div class="mb-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="fw-bold text-dark small mb-0 d-flex align-items-center gap-1.5">
                                <i class="fa-solid fa-shapes text-teal"></i> Kategori Layanan
                            </label>
                            <span class="text-muted small" style="font-size: 0.7rem;"><?= count($categories) ?> Bidang</span>
                        </div>
                        <div class="d-flex flex-wrap gap-1.5">
                            <input type="radio" class="btn-check" name="kategori" id="f_cat_0" value="" autocomplete="off" <?= $kategori_id === 0 ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_cat_0">Semua Kategori</label>

                            <?php foreach ($categories as $cat): ?>
                                <input type="radio" class="btn-check" name="kategori" id="f_cat_<?= $cat['id'] ?>" value="<?= $cat['id'] ?>" autocomplete="off" <?= $kategori_id === (int)$cat['id'] ? 'checked' : '' ?>>
                                <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_cat_<?= $cat['id'] ?>"><?= e($cat['name']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <hr class="my-3 opacity-10">

                    <!-- 3. Layanan Khusus & Jaminan -->
                    <div class="mb-3.5">
                        <label class="fw-bold text-dark small mb-2 d-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-certificate text-warning"></i> Layanan Khusus & Keunggulan
                        </label>
                        <div class="d-flex flex-wrap gap-1.5">
                            <input type="radio" class="btn-check" name="filter" id="f_spec_0" value="" autocomplete="off" <?= empty($filter_mode) ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_spec_0">Semua</label>

                            <input type="radio" class="btn-check" name="filter" id="f_spec_siaga" value="siaga" autocomplete="off" <?= $filter_mode === 'siaga' ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_spec_siaga">
                                <i class="fa-solid fa-bolt text-warning me-1"></i> Siaga 24 Jam
                            </label>

                            <input type="radio" class="btn-check" name="filter" id="f_spec_verified" value="verified" autocomplete="off" <?= $filter_mode === 'verified' ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_spec_verified">
                                <i class="fa-solid fa-circle-check text-success me-1"></i> Terverifikasi KTP
                            </label>

                            <input type="radio" class="btn-check" name="filter" id="f_spec_hemat" value="hemat" autocomplete="off" <?= $filter_mode === 'hemat' ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_spec_hemat">
                                <i class="fa-solid fa-tags text-warning me-1"></i> Tarif Hemat
                            </label>
                        </div>
                    </div>

                    <hr class="my-3 opacity-10">

                    <!-- 4. Urutan Hasil (Sorting) -->
                    <div class="mb-2">
                        <label class="fw-bold text-dark small mb-2 d-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-arrow-down-short-wide text-secondary"></i> Urutkan Hasil
                        </label>
                        <div class="d-flex flex-wrap gap-1.5">
                            <input type="radio" class="btn-check" name="sort" id="f_sort_default" value="" autocomplete="off" <?= empty($sort_mode) ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_sort_default">Rekomendasi</label>

                            <input type="radio" class="btn-check" name="sort" id="f_sort_cheap" value="cheap" autocomplete="off" <?= $sort_mode === 'cheap' ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_sort_cheap">Tarif Termurah</label>

                            <input type="radio" class="btn-check" name="sort" id="f_sort_rating" value="rating" autocomplete="off" <?= $sort_mode === 'rating' ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_sort_rating">Rating Tertinggi</label>

                            <input type="radio" class="btn-check" name="sort" id="f_sort_jobs" value="jobs" autocomplete="off" <?= $sort_mode === 'jobs' ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_sort_jobs">Terbanyak Selesai</label>

                            <input type="radio" class="btn-check" name="sort" id="f_sort_latest" value="latest" autocomplete="off" <?= $sort_mode === 'latest' ? 'checked' : '' ?>>
                            <label class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5" for="f_sort_latest">Mitra Terbaru</label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Sticky Modal Footer -->
            <div class="modal-footer bg-light border-top py-2.5 px-3 px-md-4 d-flex justify-content-between align-items-center">
                <a href="<?= BASE_URL ?>/search.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>" class="btn btn-white bg-white border btn-sm rounded-pill px-3.5 text-muted fw-semibold">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset
                </a>
                <button type="submit" form="searchFilterForm" class="btn btn-teal text-white btn-sm rounded-pill px-4 py-2 fw-bold shadow-xs">
                    Terapkan Filter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Portofolio Dinamis -->
<div class="modal fade" id="portfolioShowcaseModal" tabindex="-1" aria-labelledby="portfolioShowcaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <div>
                    <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="portfolioShowcaseModalLabel">
                        <i class="fa-solid fa-images text-teal"></i>
                        <span id="modalPortProviderName">Portofolio Hasil Kerja Mitra</span>
                    </h6>
                    <span class="text-muted small" id="modalPortCategory">Bukti pengerjaan nyata di Indragiri Hulu</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-3 p-md-4" id="portfolioShowcaseModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-teal spinner-border-sm" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pemesanan Langsung Modern (Direct Quick Order) -->
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
                            <h6 class="fw-bold text-white mb-0 text-truncate fs-6" id="quickOrderModalProvName">Nama Mitra</h6>
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
                                <div class="text-muted" style="font-size: 0.74rem;">Mitra memerlukan nomor kontak Anda untuk konfirmasi jadwal pengerjaan di Inhu.</div>
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
                            <button type="button" class="btn btn-sm flex-fill rounded-pill py-1.5 fw-bold bg-white text-teal shadow-xs transition-all" id="modeSelfBtnSearch" onclick="setOrderRecipientModeSearch('self')">
                                <i class="fa-solid fa-house-user me-1"></i> Untuk Saya Sendiri
                            </button>
                            <button type="button" class="btn btn-sm flex-fill rounded-pill py-1.5 fw-semibold text-secondary transition-all" id="modeOtherBtnSearch" onclick="setOrderRecipientModeSearch('other')">
                                <i class="fa-solid fa-user-group me-1"></i> Pesan untuk Orang Lain
                            </button>
                        </div>
                    </div>

                    <!-- Detail Pemesan: Untuk Saya Sendiri -->
                    <div id="orderSelfRecipientBoxSearch" class="p-2.5 px-3 rounded-3 bg-white border shadow-2xs mb-3 d-flex align-items-center justify-content-between">
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
                    <div id="orderOtherRecipientBoxSearch" class="card border border-teal-subtle shadow-xs rounded-3 p-3 mb-3 bg-white d-none">
                        <div class="fw-bold text-teal small mb-2 d-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-user-pen"></i>
                            <span>Data Kerabat / Orang yang Dituju di Inhu:</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted mb-1" style="font-size: 0.72rem;">Nama Penerima Jasa <span class="text-danger">*</span></label>
                                <input type="text" id="orderOtherNameSearch" class="form-control form-control-sm rounded-3 py-1.5" placeholder="Contoh: Ibu Rini / Teman">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-muted mb-1" style="font-size: 0.72rem;">No. WhatsApp Penerima <span class="text-danger">*</span></label>
                                <input type="tel" id="orderOtherPhoneSearch" class="form-control form-control-sm rounded-3 py-1.5" placeholder="08xxxxxxxxxx">
                            </div>
                        </div>
                        <div class="text-muted mt-1.5" style="font-size: 0.68rem;">
                            <i class="fa-solid fa-info-circle me-0.5 text-teal"></i> Teknisi akan menghubungi nomor ini saat hendak tiba di lokasi.
                        </div>
                    </div>
                <?php endif; ?>

                <form id="quickDirectOrderForm" onsubmit="event.preventDefault(); submitDirectQuickOrder();">
                    <input type="hidden" id="quickOrderProviderId" value="">
                    <input type="hidden" id="quickOrderMapsUrlSearch" value="">
                    
                    <!-- KEBUTUHAN JASA DENGAN QUICK CHIPS -->
                    <div class="card border-0 shadow-2xs rounded-3 p-3 mb-3 bg-white">
                        <label class="form-label fw-bold text-dark small mb-1.5 d-flex align-items-center justify-content-between" for="quickOrderProblem">
                            <span><i class="fa-solid fa-clipboard-list text-teal me-1.5"></i> Apa yang perlu dikerjakan? <span class="text-danger">*</span></span>
                            <span class="text-muted fw-normal" style="font-size: 0.68rem;">Pilih saran / ketik</span>
                        </label>

                        <!-- Quick Suggestion Chips -->
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button type="button" class="btn btn-xs rounded-pill border bg-light text-secondary fw-semibold py-0.5 px-2 hover-chip" style="font-size: 0.68rem;" onclick="appendQuickProblemSearch('Servis & Cuci Berkala')">+ Cuci / Servis Berkala</button>
                            <button type="button" class="btn btn-xs rounded-pill border bg-light text-secondary fw-semibold py-0.5 px-2 hover-chip" style="font-size: 0.68rem;" onclick="appendQuickProblemSearch('Perbaikan Kerusakan')">+ Perbaikan Kerusakan</button>
                            <button type="button" class="btn btn-xs rounded-pill border bg-light text-secondary fw-semibold py-0.5 px-2 hover-chip" style="font-size: 0.68rem;" onclick="appendQuickProblemSearch('Pemeriksaan / Cek Dahulu')">+ Cek Dahulu</button>
                            <button type="button" class="btn btn-xs rounded-pill border bg-light text-secondary fw-semibold py-0.5 px-2 hover-chip" style="font-size: 0.68rem;" onclick="appendQuickProblemSearch('Panggilan Darurat / Cepat')">+ Darurat / Cepat</button>
                        </div>

                        <textarea id="quickOrderProblem" class="form-control border-light-subtle rounded-3" rows="2" placeholder="Jelaskan kebutuhan, kendala, atau perbaikan..." style="font-size: 0.82rem;" required <?= !is_logged_in() ? 'disabled' : '' ?>></textarea>
                    </div>

                    <!-- PATOKAN ALAMAT & GPS SMART DETECTION -->
                    <div class="card border-0 shadow-2xs rounded-3 p-3 mb-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-1.5 flex-wrap gap-1">
                            <label class="form-label fw-bold text-dark small mb-0" for="quickOrderAddress">
                                <i class="fa-solid fa-map-location-dot text-danger me-1"></i> Lokasi & Patokan Alamat <span class="text-danger">*</span>
                            </label>
                            
                            <!-- SMART GPS BUTTON -->
                            <button type="button" class="btn btn-xs btn-outline-teal rounded-pill px-2.5 py-1 fw-bold shadow-2xs d-flex align-items-center gap-1.5" id="btnDetectGpsSearch" onclick="detectUserGPSLocationSearch()" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-crosshairs text-teal"></i>
                                <span>Deteksi GPS Saya (Otomatis)</span>
                            </button>
                        </div>

                        <div class="position-relative">
                            <input type="text" id="quickOrderAddress" class="form-control border-light-subtle rounded-3 py-2" placeholder="Ketik patokan jalan/rumah, atau klik tombol GPS di atas..." value="<?= e($user_saved_address) ?>" style="font-size: 0.82rem;" required <?= !is_logged_in() ? 'disabled' : '' ?>>
                        </div>

                        <!-- Status Notifikasi GPS Real-time -->
                        <div id="gpsDetectionStatusSearch" class="<?= !empty($user_saved_address) ? '' : 'd-none' ?> mt-1.5 p-2 rounded-3 small border" style="font-size: 0.72rem; background: #f8fafc;">
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

                        <div class="d-flex flex-wrap gap-1.5" id="quickOrderTimePickerSearch">
                            <label class="time-picker-pill active flex-fill text-center p-2 rounded-3 border">
                                <input type="radio" name="order_time_radio_search" value="Hari ini secepatnya (Darurat / Mendesak)" class="d-none" checked onchange="selectOrderTimePillSearch(this)">
                                <div class="fw-bold small text-dark" style="font-size: 0.78rem;"><i class="fa-solid fa-bolt text-warning me-1"></i> Secepatnya</div>
                                <div class="text-muted" style="font-size: 0.65rem;">Mendesak / Hari Ini</div>
                            </label>
                            <label class="time-picker-pill flex-fill text-center p-2 rounded-3 border">
                                <input type="radio" name="order_time_radio_search" value="Besok Pagi (08:00 - 12:00)" class="d-none" onchange="selectOrderTimePillSearch(this)">
                                <div class="fw-bold small text-dark" style="font-size: 0.78rem;">🌅 Besok Pagi</div>
                                <div class="text-muted" style="font-size: 0.65rem;">08:00 - 12:00</div>
                            </label>
                            <label class="time-picker-pill flex-fill text-center p-2 rounded-3 border">
                                <input type="radio" name="order_time_radio_search" value="Besok Siang / Sore (13:00 - 17:00)" class="d-none" onchange="selectOrderTimePillSearch(this)">
                                <div class="fw-bold small text-dark" style="font-size: 0.78rem;">🌇 Besok Siang</div>
                                <div class="text-muted" style="font-size: 0.65rem;">13:00 - 17:00</div>
                            </label>
                            <label class="time-picker-pill flex-fill text-center p-2 rounded-3 border">
                                <input type="radio" name="order_time_radio_search" value="Jadwal Fleksibel (Bisa diobrolkan di Chat)" class="d-none" onchange="selectOrderTimePillSearch(this)">
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

            <!-- Body State Sukses -->
            <div class="modal-body p-4 text-center d-none" id="quickOrderSuccessBody">
                <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 72px; height: 72px;">
                    <i class="fa-solid fa-circle-check fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Pesanan Berhasil Terkirim!</h5>
                <p class="text-muted small mb-3" id="quickOrderSuccessMsg">
                    Pesanan Anda telah diteruskan langsung ke mitra.
                </p>

                <div class="p-3 rounded-3 bg-light border text-start mb-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Nomor Pesanan:</span>
                        <strong class="text-teal" id="quickOrderSuccessId">#</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Mitra Terpilih:</span>
                        <strong class="text-dark" id="quickOrderSuccessProv">-</strong>
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
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Lihat Status di Menu Pesanan
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
window.CSRF_TOKEN = '<?= csrf_token() ?>';
window.IS_LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;
window.BASE_URL = '<?= BASE_URL ?>';
function formatMediaUrl(path) {
    if (!path) return '';
    if (path.startsWith('data:') || path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }
    return (window.BASE_URL || '') + '/' + path.replace(/^\/+/, '');
}

async function openPortfolioModal(providerId) {
    const modalEl = document.getElementById('portfolioShowcaseModal');
    const modalBody = document.getElementById('portfolioShowcaseModalBody');
    const modalTitle = document.getElementById('modalPortProviderName');
    const modalCategory = document.getElementById('modalPortCategory');
    
    modalTitle.textContent = 'Memuat Portofolio...';
    modalCategory.textContent = 'Layanan Inhu';
    modalBody.innerHTML = `
        <div class="text-center py-5 text-muted">
            <div class="spinner-border text-teal mb-3" role="status">
                <span class="visually-hidden">Memuat...</span>
            </div>
            <div class="fw-semibold">Mengambil bukti pekerjaan mitra...</div>
        </div>
    `;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    try {
        const response = await fetch(`${window.BASE_URL}/api/provider_portfolio.php?provider_id=${providerId}`);
        const result = await response.json();
        const portfolios = (result && result.data) ? result.data : [];
        const provider = (result && result.provider) ? result.provider : null;

        if (provider) {
            modalTitle.textContent = provider.business_name || 'Portofolio Hasil Kerja';
            modalCategory.textContent = 'Kategori: ' + (provider.category_name || 'Layanan Inhu');
        }

        if (portfolios.length === 0) {
            modalBody.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fa-regular fa-image fs-1 mb-2 opacity-40"></i>
                    <div class="fw-semibold">Belum Ada Portofolio Foto</div>
                    <div class="small">Mitra belum mengunggah foto hasil pekerjaan.</div>
                </div>
            `;
        } else {
            let html = '<div class="row g-3">';
            portfolios.forEach(item => {
                const hasBefore = item.image_before && item.image_before.trim() !== '';
                const hasAfter = item.image_after && item.image_after.trim() !== '';
                
                html += `
                    <div class="col-md-6">
                        <div class="card border rounded-3 overflow-hidden shadow-xs h-100">
                            <div class="position-relative" style="height: 190px; background: #f1f5f9;">
                                ${hasAfter ? `
                                    <img src="${formatMediaUrl(item.image_after)}" alt="${item.title}" class="w-100 h-100 object-fit-cover">
                                    <span class="badge bg-success position-absolute top-0 end-0 m-2 shadow-xs">Hasil Selesai</span>
                                ` : (hasBefore ? `
                                    <img src="${formatMediaUrl(item.image_before)}" alt="${item.title}" class="w-100 h-100 object-fit-cover">
                                    <span class="badge bg-secondary position-absolute top-0 end-0 m-2 shadow-xs">Sebelum Pengerjaan</span>
                                ` : `
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                        <i class="fa-solid fa-camera fs-2 opacity-50"></i>
                                    </div>
                                `)}
                            </div>
                            <div class="p-3">
                                <h6 class="fw-bold text-dark mb-1">${item.title || 'Dokumentasi Pekerjaan'}</h6>
                                <p class="text-muted small mb-0" style="font-size: 0.78rem; line-height: 1.4;">
                                    ${item.description || 'Pengerjaan jasa profesional untuk pelanggan di Kabupaten Indragiri Hulu.'}
                                </p>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            modalBody.innerHTML = html;
        }
    } catch (err) {
        modalBody.innerHTML = `
            <div class="text-center py-4 text-danger">
                <i class="fa-solid fa-triangle-exclamation fs-2 mb-2"></i>
                <div class="fw-semibold">Gagal memuat portofolio</div>
                <div class="small text-muted">Silakan periksa koneksi internet Anda atau coba lagi.</div>
            </div>
        `;
    }
}

let orderRecipientModeSearch = 'self';

function setOrderRecipientModeSearch(mode) {
    orderRecipientModeSearch = mode;
    const selfBtn = document.getElementById('modeSelfBtnSearch');
    const otherBtn = document.getElementById('modeOtherBtnSearch');
    const otherForm = document.getElementById('orderOtherRecipientBoxSearch');
    const selfInfo = document.getElementById('orderSelfRecipientBoxSearch');

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

function appendQuickProblemSearch(text) {
    const area = document.getElementById('quickOrderProblem');
    if (!area) return;
    if (area.value.trim() === '') {
        area.value = text;
    } else if (!area.value.includes(text)) {
        area.value = area.value.trim() + ', ' + text;
    }
    area.focus();
}

function selectOrderTimePillSearch(radioInput) {
    document.querySelectorAll('#quickOrderTimePickerSearch .time-picker-pill').forEach(pill => {
        pill.classList.remove('active');
    });
    radioInput.closest('.time-picker-pill').classList.add('active');
    document.getElementById('quickOrderTime').value = radioInput.value;
}

function detectUserGPSLocationSearch() {
    const btn = document.getElementById('btnDetectGpsSearch');
    const statusBox = document.getElementById('gpsDetectionStatusSearch');
    const addressInput = document.getElementById('quickOrderAddress');
    const hiddenMapsUrl = document.getElementById('quickOrderMapsUrlSearch');

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

function openDirectQuickOrderModal(prov) {
    document.getElementById('quickOrderProviderId').value = prov.id;
    document.getElementById('quickOrderModalProvName').textContent = prov.name;
    document.getElementById('quickOrderModalCategory').textContent = prov.category;
    document.getElementById('quickOrderModalRate').textContent = prov.rate;
    document.getElementById('quickOrderModalDistrict').textContent = prov.district;

    const iconEl = document.getElementById('quickOrderModalProvIcon');
    if (iconEl && prov.icon) {
        iconEl.className = 'fa-solid ' + prov.icon + ' fs-4';
    }

    setOrderRecipientModeSearch('self');

    document.getElementById('quickOrderAlert').classList.add('d-none');
    document.getElementById('quickOrderFormBody').classList.remove('d-none');
    document.getElementById('quickOrderSuccessBody').classList.add('d-none');

    const modalEl = document.getElementById('directQuickOrderModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function switchToLoginFromQuickOrder() {
    const redirectUrl = encodeURIComponent(window.location.pathname + window.location.search);
    window.location.href = window.BASE_URL + '/login.php?redirect=' + redirectUrl;
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
    const mapsUrl = document.getElementById('quickOrderMapsUrlSearch') ? document.getElementById('quickOrderMapsUrlSearch').value : '';

    const alertEl = document.getElementById('quickOrderAlert');
    const alertText = document.getElementById('quickOrderAlertText');

    if (!problem) {
        alertText.textContent = "Mohon jelaskan masalah atau kebutuhan jasa Anda.";
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
    if (orderRecipientModeSearch === 'other') {
        const nameEl = document.getElementById('orderOtherNameSearch');
        const phoneEl = document.getElementById('orderOtherPhoneSearch');
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
    formData.append('order_for', orderRecipientModeSearch);
    if (orderRecipientModeSearch === 'other') {
        formData.append('recipient_name', otherName);
        formData.append('recipient_phone', otherPhone);
    }
    if (mapsUrl) {
        formData.append('maps_url', mapsUrl);
    }

    fetch(window.BASE_URL + '/api/direct_order.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (btnSubmit) btnSubmit.disabled = false;
        if (btnText) btnText.textContent = "Kirim Pesanan Sekarang 🚀";
        if (btnSpinner) btnSpinner.classList.add('d-none');
        if (btnIcon) btnIcon.classList.remove('d-none');

        if (data.success) {
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

            document.getElementById('quickOrderProblem').value = '';
        } else {
            alertText.textContent = data.message || "Gagal membuat pesanan.";
            alertEl.classList.remove('d-none');
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

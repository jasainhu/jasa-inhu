<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

$q = trim($_GET['q'] ?? '');
$kategori_id = (int)($_GET['kategori'] ?? 0);
$kecamatan_id = (int)($_GET['kecamatan'] ?? 0);
$filter_mode = trim($_GET['filter'] ?? '');
$sort_mode = trim($_GET['sort'] ?? '');

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

    // 3. Query Filter Dinamis
    $where = ["u.is_active = 1"];
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
        SELECT sp.*, u.name as owner_name, u.phone, sc.name as category_name, sc.icon as category_icon,
               d.name as district_name, v.name as village_name,
               (SELECT COUNT(*) FROM provider_portfolios pp WHERE pp.provider_id = sp.id) as portfolio_count
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        JOIN service_categories sc ON sp.primary_category_id = sc.id
        LEFT JOIN districts d ON sp.district_id = d.id
        LEFT JOIN villages v ON sp.village_id = v.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$order_clause}
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $providers = $stmt->fetchAll();

    // Portofolio
    $all_portfolios = [];
    if (!empty($providers)) {
        $p_ids = array_column($providers, 'id');
        $in_clause = implode(',', array_map('intval', $p_ids));
        $stmtPort = $db->query("SELECT * FROM provider_portfolios WHERE provider_id IN ($in_clause) ORDER BY id DESC");
        $raw_ports = $stmtPort->fetchAll();
        foreach ($raw_ports as $rp) {
            $all_portfolios[$rp['provider_id']][] = $rp;
        }
    }
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

                            <!-- Action Buttons: Chat & Pesan Jasa -->
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

<!-- Modal Pemesanan Langsung (Direct Quick Order) -->
<div class="modal fade" id="directQuickOrderModal" tabindex="-1" aria-labelledby="directQuickOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa-solid fa-bolt text-warning"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="directQuickOrderModalLabel">Pesan Jasa Langsung</h6>
                        <span class="text-muted small" style="font-size: 0.75rem;">Terhubung langsung ke mitra pilihan Anda</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            
            <div class="modal-body p-4" id="quickOrderFormBody">
                <!-- Ringkasan Info Mitra yang Dipilih -->
                <div class="p-3 rounded-3 bg-light border mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Mitra Tujuan:</div>
                            <h6 class="fw-bold text-dark mb-0" id="quickOrderModalProvName">-</h6>
                            <span class="badge bg-teal-subtle text-teal border border-teal-subtle mt-1" id="quickOrderModalCategory" style="font-size: 0.68rem;">-</span>
                        </div>
                        <div class="text-end">
                            <div class="text-muted" style="font-size: 0.7rem;">Estimasi:</div>
                            <div class="fw-bold text-teal" style="font-size: 0.85rem;" id="quickOrderModalRate">-</div>
                            <div class="text-muted small" style="font-size: 0.68rem;" id="quickOrderModalDistrict">-</div>
                        </div>
                    </div>
                </div>

                <div id="quickOrderAlert" class="alert alert-danger p-2.5 small d-none mb-3">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> <span id="quickOrderAlertText"></span>
                </div>

                <form id="quickDirectOrderForm" onsubmit="event.preventDefault(); submitDirectQuickOrder();">
                    <input type="hidden" id="quickOrderProviderId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Deskripsi Kebutuhan / Kerusakan <span class="text-danger">*</span>
                        </label>
                        <textarea id="quickOrderProblem" class="form-control form-control-sm rounded-3" rows="3" placeholder="Contoh: AC kamar tidak dingin dan airnya menetes, butuh dicek dan dicuci besok siang..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Alamat / Patokan Lokasi di Inhu <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="quickOrderAddress" class="form-control form-control-sm rounded-3" placeholder="Contoh: Jl. Lintas Timur Belilas, depan Pasar Rakyat / dekat Mesjid..." required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Waktu Pengerjaan yang Diinginkan
                        </label>
                        <select id="quickOrderTime" class="form-select form-select-sm rounded-3">
                            <option value="secepatnya">Secepatnya / Darurat (Siaga)</option>
                            <option value="hari_ini">Hari Ini</option>
                            <option value="besok">Besok</option>
                            <option value="kesepakatan">Fleksibel / Diskusi Nanti di Chat</option>
                        </select>
                    </div>

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

            <!-- Body State Sukses -->
            <div class="modal-body p-4 text-center d-none" id="quickOrderSuccessBody">
                <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 70px; height: 70px;">
                    <i class="fa-solid fa-circle-check fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Pesanan Berhasil Dikirim!</h5>
                <p class="text-muted small mb-3" id="quickOrderSuccessMsg">
                    Pesanan Anda telah diteruskan langsung ke mitra.
                </p>

                <div class="p-3 rounded-3 bg-light border text-start mb-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Nomor Pesanan:</span>
                        <strong class="text-dark" id="quickOrderSuccessId">#</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Mitra Terpilih:</span>
                        <strong class="text-dark" id="quickOrderSuccessProv">-</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Status:</span>
                        <span class="badge bg-warning text-dark">Menunggu Respon</span>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <a href="#" id="quickOrderSuccessChatBtn" class="btn btn-teal text-white w-100 py-2.5 fw-semibold shadow-xs d-flex align-items-center justify-content-center gap-1.5">
                        <i class="fa-solid fa-comments fs-5"></i> <span>Buka Obrolan di Aplikasi</span>
                    </a>
                    <a href="<?= BASE_URL ?>/user/requests.php" class="btn btn-outline-secondary w-100 btn-sm py-2 fw-semibold">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Lihat Status di Menu Pesanan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.CSRF_TOKEN = '<?= csrf_token() ?>';
window.IS_LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;
window.BASE_URL = '<?= BASE_URL ?>';
window.PROVIDER_PORTFOLIOS = <?= json_encode($all_portfolios) ?>;

function openPortfolioModal(providerId) {
    const modalEl = document.getElementById('portfolioShowcaseModal');
    const modalBody = document.getElementById('portfolioShowcaseModalBody');
    const modalTitle = document.getElementById('modalPortProviderName');
    const modalCategory = document.getElementById('modalPortCategory');
    
    const portfolios = window.PROVIDER_PORTFOLIOS[providerId] || [];
    
    if (portfolios.length === 0) {
        modalBody.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="fa-regular fa-image fs-1 mb-2 opacity-40"></i>
                <div class="fw-semibold">Belum Ada Portofolio Foto</div>
                <div class="small">Mitra belum mengunggah foto hasil pekerjaan.</div>
            </div>
        `;
    } else {
        modalTitle.textContent = portfolios[0].business_name || 'Portofolio Hasil Kerja';
        modalCategory.textContent = 'Kategori: ' + (portfolios[0].category_name || 'Layanan Inhu');

        let html = '<div class="row g-3">';
        portfolios.forEach(item => {
            const hasBefore = item.image_before && item.image_before.trim() !== '';
            const hasAfter = item.image_after && item.image_after.trim() !== '';
            
            html += `
                <div class="col-md-6">
                    <div class="card border rounded-3 overflow-hidden shadow-xs h-100">
                        <div class="position-relative" style="height: 190px; background: #f1f5f9;">
                            ${hasAfter ? `
                                <img src="${window.BASE_URL}/${item.image_after}" alt="${item.title}" class="w-100 h-100 object-fit-cover">
                                <span class="badge bg-success position-absolute top-0 end-0 m-2 shadow-xs">Hasil Selesai</span>
                            ` : (hasBefore ? `
                                <img src="${window.BASE_URL}/${item.image_before}" alt="${item.title}" class="w-100 h-100 object-fit-cover">
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

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function openDirectQuickOrderModal(prov) {
    document.getElementById('quickOrderProviderId').value = prov.id;
    document.getElementById('quickOrderModalProvName').textContent = prov.name;
    document.getElementById('quickOrderModalCategory').textContent = prov.category;
    document.getElementById('quickOrderModalRate').textContent = prov.rate;
    document.getElementById('quickOrderModalDistrict').textContent = prov.district;

    document.getElementById('quickOrderAlert').classList.add('d-none');
    document.getElementById('quickOrderFormBody').classList.remove('d-none');
    document.getElementById('quickOrderSuccessBody').classList.add('d-none');

    const modalEl = document.getElementById('directQuickOrderModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function switchToLoginFromQuickOrder() {
    const provId = document.getElementById('quickOrderProviderId').value;
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

    const alertEl = document.getElementById('quickOrderAlert');
    const alertText = document.getElementById('quickOrderAlertText');

    if (!problem) {
        alertText.textContent = "Mohon jelaskan masalah atau kebutuhan jasa Anda.";
        alertEl.classList.remove('d-none');
        document.getElementById('quickOrderProblem').focus();
        return;
    }

    if (!address) {
        alertText.textContent = "Mohon cantumkan alamat atau patokan lokasi Anda di Inhu.";
        alertEl.classList.remove('d-none');
        document.getElementById('quickOrderAddress').focus();
        return;
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

    fetch(window.BASE_URL + '/api/direct_order.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (btnSubmit) btnSubmit.disabled = false;
        if (btnText) btnText.textContent = "Kirim Pesanan";
        if (btnSpinner) btnSpinner.classList.add('d-none');
        if (btnIcon) btnIcon.classList.remove('d-none');

        if (data.success) {
            document.getElementById('quickOrderFormBody').classList.add('d-none');
            document.getElementById('quickOrderSuccessBody').classList.remove('d-none');
            document.getElementById('quickOrderSuccessId').textContent = '#' + data.request_id;
            document.getElementById('quickOrderSuccessProv').textContent = data.provider_name;
            document.getElementById('quickOrderSuccessMsg').textContent = data.message;
            document.getElementById('quickOrderSuccessChatBtn').href = data.chat_url || (window.BASE_URL + '/chat.php?provider_id=' + provId);
            
            document.getElementById('quickOrderProblem').value = '';
            document.getElementById('quickOrderAddress').value = '';
        } else {
            alertText.textContent = data.message || "Gagal membuat pesanan.";
            alertEl.classList.remove('d-none');
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

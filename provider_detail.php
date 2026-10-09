<?php
/**
 * Halaman Detail Profil & Testimoni Mitra Penyedia Jasa
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$provider_id = (int)($_GET['id'] ?? 0);
if ($provider_id <= 0) {
    set_flash('danger', 'Mitra penyedia jasa tidak ditemukan.');
    redirect('/');
}

$db = get_db();
$current_user = current_user();

// 1. Ambil Data Detail Mitra
$stmtProv = $db->prepare("
    SELECT sp.*, u.name as owner_name, u.phone, u.email,
           sc.name as category_name, sc.icon as category_icon,
           d.name as district_name, v.name as village_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    LEFT JOIN villages v ON sp.village_id = v.id
    WHERE sp.id = ? AND u.is_active = 1
    LIMIT 1
");
$stmtProv->execute([$provider_id]);
$provider = $stmtProv->fetch();

if (!$provider) {
    set_flash('danger', 'Mitra penyedia jasa tidak ditemukan atau sedang tidak aktif.');
    redirect('/');
}

$page_title = e($provider['business_name']) . ' - Profil & Testimoni Mitra JASA INHU';

// 2. Ambil Wilayah Layanan yang Dicakup di Kab. Inhu
$stmtAreas = $db->prepare("
    SELECT d.name as district_name
    FROM service_areas sa
    JOIN districts d ON sa.district_id = d.id
    WHERE sa.provider_id = ?
    ORDER BY d.name ASC
");
$stmtAreas->execute([$provider_id]);
$service_areas = $stmtAreas->fetchAll();

// 3. Ambil Portofolio Kerja Mitra
$stmtPort = $db->prepare("
    SELECT * FROM provider_portfolios 
    WHERE provider_id = ? 
    ORDER BY id DESC
");
$stmtPort->execute([$provider_id]);
$portfolios = $stmtPort->fetchAll();

// 4. Ambil Semua Ulasan & Testimoni Pelanggan
$stmtReviews = $db->prepare("
    SELECT r.*, u.name as customer_name, sr.title as request_title
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN service_requests sr ON r.request_id = sr.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
");
$stmtReviews->execute([$provider_id]);
$reviews = $stmtReviews->fetchAll();

// 5. Hitung Distribusi Bintang (5, 4, 3, 2, 1)
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$total_reviews = count($reviews);
foreach ($reviews as $rev) {
    $r = (int)$rev['rating'];
    if (isset($rating_counts[$r])) {
        $rating_counts[$r]++;
    }
}

// 6. Mitra Lainnya di Kategori yang Sama (Rekomendasi)
$stmtRelated = $db->prepare("
    SELECT sp.*, u.name as owner_name, u.phone, sc.name as category_name, sc.icon as category_icon, d.name as district_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    WHERE sp.primary_category_id = ? AND sp.id != ? AND u.is_active = 1
    ORDER BY sp.rating_avg DESC, sp.completed_jobs DESC
    LIMIT 3
");
$stmtRelated->execute([$provider['primary_category_id'], $provider_id]);
$related_providers = $stmtRelated->fetchAll();

// 7. Ambil Tanya Jawab & Diskusi Layanan (Q&A Tokopedia Style)
$stmtDisc = $db->prepare("
    SELECT pd.*, u.name as user_name
    FROM provider_discussions pd
    JOIN users u ON pd.user_id = u.id
    WHERE pd.provider_id = ?
    ORDER BY pd.created_at ASC
");
$stmtDisc->execute([$provider_id]);
$all_discussions = $stmtDisc->fetchAll();

$discussions = [];
$replies = [];
foreach ($all_discussions as $d) {
    if (empty($d['parent_id'])) {
        $discussions[$d['id']] = $d;
        $discussions[$d['id']]['replies'] = [];
    } else {
        $replies[] = $d;
    }
}
foreach ($replies as $r) {
    if (isset($discussions[$r['parent_id']])) {
        $discussions[$r['parent_id']]['replies'][] = $r;
    }
}
$discussions = array_reverse($discussions, true);
$total_questions = count($discussions);

// Persiapan link WA
$clean_phone = preg_replace('/[^0-9]/', '', $provider['phone']);
if (str_starts_with($clean_phone, '0')) {
    $intl_phone = '62' . substr($clean_phone, 1);
} elseif (!str_starts_with($clean_phone, '62')) {
    $intl_phone = '62' . $clean_phone;
} else {
    $intl_phone = $clean_phone;
}

$wa_message = "Halo " . $provider['business_name'] . ", saya melihat profil usaha Anda di portal JASA INHU dan ingin berkonsultasi mengenai jasa: " . $provider['category_name'] . ". Apakah saat ini tersedia?";
$wa_url = "https://wa.me/" . $intl_phone . "?text=" . urlencode($wa_message);

require_once __DIR__ . '/includes/header.php';
?>

<div class="bg-light py-2.5 border-bottom mb-4">
    <div class="container d-flex align-items-center justify-content-between">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i> Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/?kategori=<?= $provider['primary_category_id'] ?>" class="text-decoration-none text-muted"><?= e($provider['category_name']) ?></a></li>
                <li class="breadcrumb-item active fw-bold text-dark text-truncate" aria-current="page" style="max-width: 260px;"><?= e($provider['business_name']) ?></li>
            </ol>
        </nav>
        <button type="button" class="btn btn-sm btn-white bg-white border rounded-pill px-3 py-1 text-dark shadow-xs d-flex align-items-center gap-1.5 fw-semibold hover-lift" onclick="shareProviderProfile()" title="Bagikan profil usaha ini">
            <i class="fa-solid fa-share-nodes text-teal"></i>
            <span>Bagikan</span>
        </button>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4">
        <!-- Kolom Kiri / Utama: Detail Usaha, Portofolio, dan Testimoni -->
        <div class="col-lg-8">
            <!-- 1. Header Banner Toko / Usaha -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <!-- Cover Photo Banner -->
                <div class="position-relative" style="height: 220px; background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                    <?php if (!empty($provider['image_url']) && file_exists(__DIR__ . '/' . $provider['image_url'])): ?>
                        <img src="<?= BASE_URL ?>/<?= e($provider['image_url']) ?>" alt="<?= e($provider['business_name']) ?>" class="w-100 h-100 object-fit-cover" style="opacity: 0.85;">
                    <?php else: ?>
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center text-white opacity-40">
                            <i class="fa-solid <?= e($provider['category_icon'] ?: 'fa-wrench') ?>" style="font-size: 5rem;"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="position-absolute top-0 end-0 m-3 d-flex gap-2 align-items-center">
                        <button type="button" class="btn btn-sm btn-light bg-white bg-opacity-90 border-0 rounded-pill px-3 py-1 shadow-xs fw-semibold d-flex align-items-center gap-1.5 hover-lift" onclick="shareProviderProfile()" title="Bagikan profil usaha ini">
                            <i class="fa-solid fa-share-nodes text-dark"></i>
                            <span class="text-dark small">Bagikan</span>
                        </button>
                        <span class="badge bg-dark bg-opacity-75 text-white backdrop-blur py-1.5 px-3 rounded-pill">
                            <i class="fa-solid fa-circle text-success me-1" style="font-size: 0.5rem;"></i> Mitra Siaga
                        </span>
                    </div>
                </div>

                <!-- Info Header Usaha -->
                <div class="card-body p-4 position-relative">
                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-3">
                        <div>
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-1.5">
                                <h2 class="fw-bold text-dark mb-0 fs-3"><?= e($provider['business_name']) ?></h2>
                                <?php if ($provider['is_verified']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2.5 rounded-pill" title="KTP & Lokasi Usaha Terverifikasi Tim JASA INHU">
                                        <i class="fa-solid fa-circle-check me-1"></i> Terverifikasi KTP
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($provider['credential_title'])): ?>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle py-1 px-2.5 rounded-pill" title="Kualifikasi / Sertifikasi Terdaftar">
                                        <i class="fa-solid fa-award me-1 text-teal"></i> <?= e($provider['credential_title']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2 text-muted small">
                                <span class="badge text-bg-light border text-primary">
                                    <i class="fa-solid <?= e($provider['category_icon'] ?: 'fa-wrench') ?> me-1"></i> <?= e($provider['category_name']) ?>
                                </span>
                                <span>&bull;</span>
                                <span><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= !empty($provider['village_name']) ? '<strong class="text-dark">' . e($provider['village_name']) . '</strong>, ' : '' ?>Kec. <?= e($provider['district_name'] ?: 'Kab. Indragiri Hulu') ?></span>
                                <span>&bull;</span>
                                <span><i class="fa-solid fa-briefcase text-muted me-1"></i> <?= (int)$provider['completed_jobs'] ?> Pesanan Sukses</span>
                            </div>
                        </div>

                        <!-- Rating Badge Header -->
                        <div class="d-flex align-items-center gap-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 px-3 py-2 rounded-3">
                            <i class="fa-solid fa-star text-warning fs-3"></i>
                            <div>
                                <div class="fw-bold text-dark fs-5 lh-1"><?= number_format((float)($provider['rating_avg'] ?? 5.0), 1) ?> <span class="text-muted fs-6 fw-normal">/ 5.0</span></div>
                                <div class="text-muted small" style="font-size: 0.72rem;"><?= (int)($provider['reviews_count'] ?? 0) ?> ulasan kepuasan</div>
                            </div>
                        </div>
                    </div>

                    <!-- Highlight Badges -->
                    <div class="row g-2 pt-3 border-top text-center text-md-start">
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded-3 bg-light">
                                <div class="text-muted" style="font-size: 0.72rem;">Pengalaman Kerja</div>
                                <div class="fw-bold text-dark"><?= (int)($provider['experience_years'] ?: 2) ?>+ Tahun</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded-3 bg-light">
                                <div class="text-muted" style="font-size: 0.72rem;">Estimasi Biaya</div>
                                <div class="fw-bold text-teal">
                                    <?= (!empty($provider['hourly_rate_min']) && $provider['hourly_rate_min'] > 0) ? 'Mulai ' . format_rupiah($provider['hourly_rate_min']) : 'Fleksibel' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded-3 bg-light">
                                <div class="text-muted" style="font-size: 0.72rem;">Portofolio Kerja</div>
                                <div class="fw-bold text-dark"><?= count($portfolios) ?> Hasil Kerja</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded-3 bg-light">
                                <div class="text-muted" style="font-size: 0.72rem;">Waktu Tanggap</div>
                                <div class="fw-bold text-success">&plusmn; 15 - 30 Menit</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Deskripsi & Profil Usaha -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-info text-teal"></i>
                    <span>Tentang Usaha & Layanan</span>
                </h5>
                <?php if (!empty($provider['headline'])): ?>
                    <p class="lead fs-6 text-dark fw-semibold mb-3 fst-italic">
                        "<?= e($provider['headline']) ?>"
                    </p>
                <?php endif; ?>
                <div class="text-secondary small" style="line-height: 1.7; white-space: pre-line;">
                    <?= e($provider['description'] ?: 'Penyedia jasa berpengalaman di bidang ' . $provider['category_name'] . ' yang siap melayani kebutuhan perbaikan, pemeliharaan, dan pengerjaan jasa untuk masyarakat di Kabupaten Indragiri Hulu.') ?>
                </div>

                <!-- Lencana Kualifikasi Profesi / STR / Sertifikat (Jika Ada) -->
                <?php if (!empty($provider['credential_title'])): ?>
                    <div class="p-3 my-3 rounded-3 border d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: #f0fdfa; border-color: #99f6e4 !important;">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="rounded-circle bg-white text-teal d-flex align-items-center justify-content-center shadow-xs" style="width: 38px; height: 38px; font-size: 1.15rem; flex-shrink: 0;">
                                <i class="fa-solid fa-award text-teal"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark small">Kualifikasi Profesi & Sertifikasi:</div>
                                <div class="text-teal small fw-semibold"><?= e($provider['credential_title']) ?></div>
                            </div>
                        </div>
                        <?php if (!empty($provider['certificate_url']) && file_exists(__DIR__ . '/' . $provider['certificate_url'])): ?>
                            <a href="<?= BASE_URL ?>/<?= e($provider['certificate_url']) ?>" target="_blank" class="btn btn-sm btn-outline-teal fw-semibold rounded-pill px-3 py-1">
                                <i class="fa-solid fa-file-shield me-1"></i> Lihat Dokumen ↗
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Wilayah yang Dijangkau -->
                <div class="mt-4 pt-3 border-top">
                    <h6 class="fw-bold text-dark small mb-2">
                        <i class="fa-solid fa-map-location-dot text-danger me-1"></i> Wilayah Operasional di Indragiri Hulu:
                    </h6>
                    <div class="d-flex flex-wrap gap-1.5">
                        <span class="badge text-bg-light border text-dark fw-normal py-1 px-2">
                            <i class="fa-solid fa-check text-success me-1"></i> Kec. <?= e($provider['district_name'] ?: 'Kab. Indragiri Hulu') ?> (Basis)
                        </span>
                        <?php if (!empty($service_areas)): ?>
                            <?php foreach ($service_areas as $area): ?>
                                <?php if ($area['district_name'] !== $provider['district_name']): ?>
                                    <span class="badge text-bg-light border text-muted fw-normal py-1 px-2">
                                        <i class="fa-solid fa-check text-teal me-1"></i> Kec. <?= e($area['district_name']) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="badge text-bg-light border text-muted fw-normal py-1 px-2">Melayani Seluruh Kecamatan Inhu via Panggilan</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alamat Workshop & Navigasi Maps (Format Ramping & Hemat Tempat) -->
                <?php if (!empty($provider['address']) || !empty($provider['village_name']) || !empty($provider['district_name'])): ?>
                    <?php
                    $mapsQueryParts = [];
                    if (!empty($provider['address'])) {
                        $mapsQueryParts[] = $provider['address'];
                    }
                    if (!empty($provider['village_name'])) {
                        $mapsQueryParts[] = 'Desa ' . $provider['village_name'];
                    }
                    if (!empty($provider['district_name'])) {
                        $mapsQueryParts[] = 'Kecamatan ' . $provider['district_name'];
                    }
                    $mapsQueryParts[] = 'Indragiri Hulu, Riau';
                    $mapsQuery = implode(', ', $mapsQueryParts);
                    $mapsUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($mapsQuery);
                    ?>
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2.5 rounded-3 bg-light border">
                            <div class="d-flex align-items-start gap-2 small text-dark" style="max-width: 78%;">
                                <i class="fa-solid fa-location-dot text-danger mt-1 flex-shrink-0"></i>
                                <div>
                                    <span class="text-muted" style="font-size: 0.72rem; display: block;">Alamat Workshop / Basis Operasional:</span>
                                    <span class="fw-semibold"><?= e($provider['address'] ?: 'Kecamatan ' . ($provider['district_name'] ?: 'Indragiri Hulu')) ?></span>
                                    <?php if (!empty($provider['village_name'])): ?>
                                        <span class="text-secondary">(Desa/Kel. <?= e($provider['village_name']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="<?= $mapsUrl ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-danger py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1.5 shadow-xs fw-semibold flex-shrink-0" style="font-size: 0.75rem;" title="Buka Titik di Google Maps">
                                <i class="fa-solid fa-diamond-turn-right"></i>
                                <span>Google Maps ↗</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. Portofolio Hasil Kerja (Jika Ada) -->
            <?php if (!empty($portfolios)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" id="section-portofolio">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-images text-teal"></i>
                            <span>Bukti Hasil Kerja (<?= count($portfolios) ?>)</span>
                        </h5>
                        <span class="text-muted small">Dokumentasi pekerjaan sebelumnya</span>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($portfolios as $port): ?>
                            <div class="col-md-6">
                                <div class="card border rounded-3 overflow-hidden shadow-xs h-100">
                                    <div class="position-relative" style="height: 180px; background: #f1f5f9;">
                                        <?php if (!empty($port['after_image_url']) && file_exists(__DIR__ . '/' . $port['after_image_url'])): ?>
                                            <img src="<?= BASE_URL ?>/<?= e($port['after_image_url']) ?>" alt="<?= e($port['title']) ?>" class="w-100 h-100 object-fit-cover">
                                            <span class="badge bg-success position-absolute top-0 end-0 m-2 shadow-xs">Hasil Selesai</span>
                                        <?php elseif (!empty($port['before_image_url']) && file_exists(__DIR__ . '/' . $port['before_image_url'])): ?>
                                            <img src="<?= BASE_URL ?>/<?= e($port['before_image_url']) ?>" alt="<?= e($port['title']) ?>" class="w-100 h-100 object-fit-cover">
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                                <i class="fa-solid fa-camera fs-2 opacity-50"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold text-dark mb-1 fs-6"><?= e($port['title']) ?></h6>
                                        <?php if (!empty($port['description'])): ?>
                                            <p class="text-muted small mb-2" style="font-size: 0.8rem; line-height: 1.45;"><?= e($port['description']) ?></p>
                                        <?php endif; ?>
                                        <div class="text-muted small" style="font-size: 0.72rem;">
                                            <i class="fa-regular fa-calendar me-1"></i> Selesai: <?= format_date($port['service_date'] ?: $port['created_at']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 4. Ulasan & Testimoni Pelanggan (Bagian Utama yang Ditanyakan User) -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" id="section-testimoni">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-star text-warning"></i>
                            <span>Ulasan & Testimoni Pelanggan</span>
                        </h5>
                        <p class="text-muted small mb-0">Ulasan asli dari masyarakat Indragiri Hulu yang telah menggunakan jasa ini</p>
                    </div>
                </div>

                <!-- Ringkasan Rating Breakdown Ala Tokopedia -->
                <div class="p-3.5 rounded-3 bg-light border mb-4">
                    <div class="row align-items-center g-3">
                        <div class="col-md-4 text-center border-end-md">
                            <div class="display-4 fw-bold text-dark lh-1"><?= number_format((float)($provider['rating_avg'] ?? 5.0), 1) ?></div>
                            <div class="text-warning fs-5 my-1">
                                <?php
                                $fullStars = floor((float)($provider['rating_avg'] ?? 5.0));
                                for ($s = 1; $s <= 5; $s++) {
                                    if ($s <= $fullStars) echo '<i class="fa-solid fa-star"></i>';
                                    else echo '<i class="fa-regular fa-star text-muted"></i>';
                                }
                                ?>
                            </div>
                            <div class="small text-muted">Berdasarkan <strong><?= $total_reviews ?> ulasan</strong></div>
                        </div>

                        <!-- Progress Bar Rating 5 - 1 Bintang -->
                        <div class="col-md-8">
                            <div class="d-flex flex-column gap-1.5 small">
                                <?php for ($star = 5; $star >= 1; $star--): ?>
                                    <?php
                                    $count = $rating_counts[$star] ?? 0;
                                    $pct = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
                                    ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted text-nowrap" style="min-width: 55px;">
                                            <?= $star ?> <i class="fa-solid fa-star text-warning small"></i>
                                        </span>
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span class="text-muted small text-end" style="min-width: 35px;"><?= $count ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daftar Testimoni Pelanggan -->
                <?php if (!empty($reviews)): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="p-3.5 rounded-3 border bg-white shadow-xs">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center fw-bold shadow-xs" style="width: 40px; height: 40px;">
                                            <?= strtoupper(substr($rev['customer_name'] ?? 'W', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark d-flex align-items-center gap-1.5">
                                                <span><?= e($rev['customer_name']) ?></span>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-2" style="font-size: 0.65rem;">
                                                    <i class="fa-solid fa-check me-1"></i> Pelanggan Terverifikasi
                                                </span>
                                            </div>
                                            <div class="text-muted small" style="font-size: 0.72rem;">
                                                <i class="fa-regular fa-clock me-1"></i> <?= format_date($rev['created_at'], true) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-warning small fs-6">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="fa-<?= $s <= (int)$rev['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                        <?php endfor; ?>
                                        <span class="text-dark fw-bold ms-1" style="font-size: 0.85rem;"><?= (int)$rev['rating'] ?>.0</span>
                                    </div>
                                </div>

                                <?php if (!empty($rev['request_title'])): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">
                                            <i class="fa-solid fa-tag text-teal me-1"></i> Jasa: <?= e($rev['request_title']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($rev['comment'])): ?>
                                    <p class="text-dark mb-0 small" style="line-height: 1.55; white-space: pre-line;">
                                        <?= e($rev['comment']) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted small fst-italic mb-0">Pelanggan memberikan rating bintang tanpa ulasan tertulis.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 bg-light rounded-3 border">
                        <div class="rounded-circle bg-white text-muted d-inline-flex align-items-center justify-content-center p-3 mb-2 shadow-xs" style="width: 55px; height: 55px;">
                            <i class="fa-regular fa-comment-dots fs-3"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Belum Ada Ulasan Masuk</h6>
                        <p class="text-muted small mb-3">Jadilah pelanggan pertama yang memesan jasa dari <?= e($provider['business_name']) ?> dan berikan testimoni Anda!</p>
                        <button type="button" class="btn btn-primary-custom btn-sm fw-bold px-3 py-2" onclick="openDirectOrderFromDetail()">
                            <i class="fa-solid fa-bolt me-1"></i> Pesan Jasa Ini Sekarang
                        </button>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Kolom Kanan: Sticky Booking Card & Rekomendasi -->
        <div class="col-lg-4">
            <div class="sticky-top" style="top: 85px; z-index: 10;">
                <!-- Booking Box Card -->
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white border-top border-4 border-teal">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Estimasi Tarif Layanan:</span>
                            <div class="fw-bold text-teal fs-4">
                                <?php if (!empty($provider['hourly_rate_min']) && $provider['hourly_rate_min'] > 0): ?>
                                    Mulai Rp <?= number_format((float)$provider['hourly_rate_min'], 0, ',', '.') ?>
                                <?php else: ?>
                                    Sesuai Kesepakatan
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge bg-success-subtle text-success py-1.5 px-2.5 rounded-pill small">
                            <i class="fa-solid fa-shield-halved me-1"></i> Jaminan Aman
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-1.5 text-muted small mb-3 p-2 rounded-2 bg-light border">
                        <i class="fa-solid fa-location-dot text-danger flex-shrink-0"></i>
                        <span class="text-truncate">
                            <?= !empty($provider['village_name']) ? '<strong class="text-dark">' . e($provider['village_name']) . '</strong>, ' : '' ?>Kec. <?= e($provider['district_name'] ?: 'Indragiri Hulu') ?>
                        </span>
                    </div>

                    <div class="bg-light p-3 rounded-3 mb-3 small">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fa-solid fa-circle-check text-teal"></i>
                            <span>Respon langsung dari pemilik usaha</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fa-solid fa-circle-check text-teal"></i>
                            <span>Biaya transparan & sepakat di awal</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-check text-teal"></i>
                            <span>Dapat dibayar tunai usai pekerjaan selesai</span>
                        </div>
                    </div>

                    <!-- Tombol Aksi Utama -->
                    <div class="d-flex flex-column gap-2 mb-3">
                        <?php if ($current_user && (int)$provider['user_id'] === (int)$current_user['id']): ?>
                            <div class="alert alert-info border-0 p-2.5 rounded-3 mb-1 small text-center">
                                <i class="fa-solid fa-circle-info me-1"></i> Ini adalah tampilan etalase profil Anda yang dilihat publik.
                            </div>
                            <a href="<?= BASE_URL ?>/provider/profile.php" class="btn btn-warning w-100 py-2.5 fw-bold shadow-xs rounded-3 d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>Edit Informasi & Tarif Usaha</span>
                            </a>
                            <a href="<?= BASE_URL ?>/provider/portfolio.php" class="btn btn-outline-teal w-100 py-2 fw-semibold shadow-xs rounded-3 d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-solid fa-camera"></i>
                                <span>Kelola Portofolio Hasil Kerja</span>
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-primary-custom w-100 py-2.5 fw-bold shadow-xs rounded-3 d-flex align-items-center justify-content-center gap-2 mb-1" onclick="openDirectOrderFromDetail()">
                                <i class="fa-solid fa-bolt text-warning"></i>
                                <span>Pesan Jasa</span>
                            </button>
                            <a href="<?= BASE_URL ?>/chat.php?provider_id=<?= $provider['id'] ?>" class="btn btn-outline-teal w-100 py-2.5 fw-semibold shadow-xs rounded-3 d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-solid fa-comments"></i>
                                <span>Chat</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="text-center">
                        <span class="text-muted small" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-lock text-muted me-1"></i> Data Anda aman dan terlindungi
                        </span>
                    </div>
                </div>

                <!-- Mitra Serupa di Kategori Sama -->
                <?php if (!empty($related_providers)): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-3.5 bg-white">
                        <h6 class="fw-bold text-dark small mb-3">
                            <i class="fa-solid fa-wrench text-teal me-1"></i> Rekomendasi Mitra <?= e($provider['category_name']) ?> Lainnya:
                        </h6>
                        <div class="d-flex flex-column gap-2.5">
                            <?php foreach ($related_providers as $rel): ?>
                                <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $rel['id'] ?>" class="text-decoration-none text-dark p-2 rounded-3 border bg-light d-flex align-items-center justify-content-between hover-shadow">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-white text-teal d-flex align-items-center justify-content-center border" style="width: 36px; height: 36px;">
                                            <i class="fa-solid <?= e($rel['category_icon'] ?: 'fa-wrench') ?> small"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold small text-truncate" style="max-width: 170px;"><?= e($rel['business_name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem;">Kec. <?= e($rel['district_name'] ?: 'Inhu') ?></div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-dark small"><i class="fa-solid fa-star text-warning"></i> <?= number_format((float)$rel['rating_avg'], 1) ?></div>
                                        <div class="text-muted" style="font-size: 0.68rem;"><?= $rel['completed_jobs'] ?> order</div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pesan Jasa Langsung (Embedded untuk Kemudahan 1-Klik dari Halaman Ini) -->
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
                        <span class="badge bg-white border text-teal fw-semibold" id="quickOrderModalRate" style="font-size: 0.72rem;">
                            <?= (!empty($provider['hourly_rate_min']) && $provider['hourly_rate_min'] > 0) ? 'Mulai Rp ' . number_format((float)$provider['hourly_rate_min'], 0, ',', '.') : 'Sesuai Kesepakatan' ?>
                        </span>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 fs-6"><?= e($provider['business_name']) ?></h6>
                    <div class="text-muted small" style="font-size: 0.78rem;">
                        <span><?= e($provider['category_name']) ?></span> &bull; <i class="fa-solid fa-location-dot text-danger ms-1"></i> <span><?= !empty($provider['village_name']) ? e($provider['village_name']) . ', ' : '' ?>Kec. <?= e($provider['district_name'] ?: 'Inhu') ?></span>
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
                                    <a href="<?= BASE_URL ?>/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-sm btn-primary-custom fw-bold px-3 py-1.5">
                                        <i class="fa-solid fa-right-to-bracket me-1"></i> Masuk Sekarang (1-Klik)
                                    </a>
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
                <form id="directQuickOrderForm" onsubmit="event.preventDefault(); submitDirectQuickOrderFromDetail();">
                    <input type="hidden" id="quickOrderProviderId" value="<?= (int)$provider['id'] ?>">

                    <!-- Pertanyaan 1: Masalah / Kebutuhan -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1" for="quickOrderProblem">
                            1. Apa yang perlu diperbaiki / dikerjakan? <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="quickOrderProblem" rows="3" placeholder="Contoh: AC kamar bocor menetes dan tidak dingin, tolong dicuci dan cek freon..." required></textarea>
                    </div>

                    <!-- Pertanyaan 2: Alamat & Jadwal -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1" for="quickOrderAddress">
                            2. Patokan Alamat & Jadwal Pengerjaan <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control mb-2" id="quickOrderAddress" placeholder="Contoh: Belilas KM 4, Jl. Utama samping Masjid Baiturrahman" required>
                        <div class="row g-2">
                            <div class="col-sm-7">
                                <select class="form-select form-select-sm" id="quickOrderTime">
                                    <option value="Hari ini secepatnya (Darurat / Mendesak)">⚡ Hari ini secepatnya (Mendesak)</option>
                                    <option value="Hari ini (Siang / Sore)">🕒 Hari ini (Siang / Sore)</option>
                                    <option value="Besok Pagi (08:00 - 12:00)" selected>🌅 Besok Pagi (08:00 - 12:00)</option>
                                    <option value="Besok Siang / Sore (13:00 - 17:00)">🌇 Besok Siang / Sore (13:00 - 17:00)</option>
                                    <option value="Jadwal Fleksibel (Bisa diobrolkan di Chat)">💬 Fleksibel / Obrolkan di Chat</option>
                                </select>
                            </div>
                            <div class="col-sm-5 d-flex align-items-center">
                                <span class="text-muted small" style="font-size: 0.72rem;">
                                    <i class="fa-solid fa-handshake text-teal me-1"></i> Bayar di tempat usai kerja
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Submit -->
                    <button type="submit" id="btnSubmitQuickOrder" class="btn btn-primary-custom w-100 py-2.5 fw-bold shadow-sm rounded-3 mt-2">
                        <span id="btnQuickOrderSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <i id="btnQuickOrderIcon" class="fa-solid fa-paper-plane me-1"></i>
                        <span id="btnQuickOrderText">Kirim Pesanan</span>
                    </button>
                </form>
            </div>

            <!-- Body Modal: State Sukses -->
            <div class="modal-body p-4 text-center d-none" id="quickOrderSuccessBody">
                <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 70px; height: 70px;">
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
                    <a href="#" id="quickOrderSuccessChatBtn" class="btn btn-teal text-white w-100 py-2.5 fw-semibold shadow-xs d-flex align-items-center justify-content-center gap-1.5">
                        <i class="fa-solid fa-comments fs-5"></i> <span>Buka Obrolan di Aplikasi</span>
                    </a>
                    <a href="<?= BASE_URL ?>/user/requests.php" class="btn btn-outline-secondary w-100 btn-sm py-2 fw-semibold">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Lacak Status di Menu Pesanan Saya
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sticky Bottom Action Bar (Tokopedia / Shopee Style for Service Booking) -->
<div class="provider-mobile-bottom-bar d-lg-none" aria-label="Aksi Cepat Mobile">
    <div class="container d-flex align-items-center justify-content-between gap-2 px-2 py-1.5">
        <?php if ($current_user && (int)$provider['user_id'] === (int)$current_user['id']): ?>
            <a href="<?= BASE_URL ?>/provider/profile.php" class="btn btn-warning flex-grow-1 py-2 fw-bold rounded-3 shadow-xs small">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Profil Usaha
            </a>
            <a href="<?= BASE_URL ?>/provider/portfolio.php" class="btn btn-outline-teal flex-grow-1 py-2 fw-semibold rounded-3 shadow-xs small">
                <i class="fa-solid fa-camera me-1"></i> Portofolio
            </a>
        <?php else: ?>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <a href="<?= BASE_URL ?>/chat.php?provider_id=<?= $provider['id'] ?>" class="btn-mobile-action-icon" title="Chat Mitra">
                    <i class="fa-solid fa-comments text-teal"></i>
                    <span>Chat</span>
                </a>
                <button type="button" class="btn-mobile-action-icon border-0 bg-transparent" onclick="shareProviderProfile()" title="Bagikan Usaha">
                    <i class="fa-solid fa-share-nodes text-secondary"></i>
                    <span>Bagikan</span>
                </button>
            </div>
            <button type="button" class="btn btn-teal flex-grow-1 py-2 px-2.5 fw-bold rounded-3 shadow-xs d-flex align-items-center justify-content-center gap-2" onclick="openDirectOrderFromDetail()">
                <i class="fa-solid fa-bolt text-warning"></i>
                <div class="text-start lh-1">
                    <div class="fw-bold" style="font-size: 0.85rem;">Pesan Jasa</div>
                    <div style="font-size: 0.68rem; opacity: 0.9;"><?= (!empty($provider['hourly_rate_min']) && $provider['hourly_rate_min'] > 0) ? 'Mulai Rp ' . number_format((float)$provider['hourly_rate_min'], 0, ',', '.') : 'Langsung' ?></div>
                </div>
            </button>
        <?php endif; ?>
    </div>
</div>

<script>
window.CSRF_TOKEN = '<?= csrf_token() ?>';
window.IS_LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;
window.BASE_URL = '<?= BASE_URL ?>';

function openDirectOrderFromDetail() {
    const modalEl = document.getElementById('directQuickOrderModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function submitDirectQuickOrderFromDetail() {
    if (!window.IS_LOGGED_IN) {
        window.location.href = window.BASE_URL + '/login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
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

        alertText.textContent = "Terjadi gangguan jaringan. Silakan coba lagi.";
        alertEl.classList.remove('d-none');
    });
}

// Fitur Bagikan Universal (Multi-Aplikasi & Salin Tautan)
function shareProviderProfile() {
    const shareTitle = '<?= addslashes(e($provider['business_name'])) ?> - JASA INHU';
    const shareText = 'Cek profil usaha <?= addslashes(e($provider['business_name'])) ?> (Jasa <?= addslashes(e($provider['category_name'])) ?>) di JASA INHU!';
    const shareUrl = window.location.href;

    if (navigator.share) {
        navigator.share({
            title: shareTitle,
            text: shareText,
            url: shareUrl
        }).catch(err => {
            if (err.name !== 'AbortError') {
                copyProfileLink();
            }
        });
    } else {
        copyProfileLink();
    }
}

function copyProfileLink() {
    const shareUrl = window.location.href;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(shareUrl).then(() => {
            showShareToast('Tautan profil usaha berhasil disalin!');
        }).catch(() => {
            promptFallbackShare(shareUrl);
        });
    } else {
        promptFallbackShare(shareUrl);
    }
}

function promptFallbackShare(text) {
    const dummy = document.createElement('input');
    document.body.appendChild(dummy);
    dummy.value = text;
    dummy.select();
    try {
        document.execCommand('copy');
        showShareToast('Tautan profil usaha berhasil disalin!');
    } catch (e) {
        showShareToast('Gagal menyalin tautan.');
    }
    document.body.removeChild(dummy);
}

function showShareToast(message) {
    let toast = document.getElementById('shareToastNotification');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'shareToastNotification';
        toast.style.cssText = 'position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%) translateY(15px); z-index: 1060; background: #0f172a; color: #fff; padding: 10px 22px; border-radius: 999px; font-size: 0.85rem; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.25); display: flex; align-items: center; gap: 8px; transition: opacity 0.25s ease, transform 0.25s ease; pointer-events: none; opacity: 0;';
        toast.innerHTML = '<i class="fa-solid fa-circle-check text-success"></i> <span id="shareToastText"></span>';
        document.body.appendChild(toast);
    }
    document.getElementById('shareToastText').textContent = message;
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0)';
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(15px)';
    }, 2800);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

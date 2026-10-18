<?php
/**
 * Admin Dashboard JASA INHU
 */

$page_title = 'Dashboard Pengelola';
$admin_active = 'dashboard';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();

// Handle quick refresh gateway action
if (isset($_GET['refresh_gateway']) && $_GET['refresh_gateway'] === '1') {
    get_fonnte_account_info(true);
    set_flash('success', 'Status WhatsApp Gateway Fonnte berhasil diperiksa ulang & diperbarui secara langsung!');
    redirect('/admin/index.php');
}

// Handle save domain expiry reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_domain_expiry') {
    if (validate_csrf()) {
        $domain_exp = trim($_POST['domain_expiry_date'] ?? '');
        $domain_reg = trim($_POST['domain_registrar'] ?? '');
        if (!empty($domain_exp)) {
            update_setting('domain_expiry_date', $domain_exp);
        }
        if (!empty($domain_reg)) {
            update_setting('domain_registrar', $domain_reg);
        }
        set_flash('success', 'Pengingat tanggal jatuh tempo domain berhasil disimpan!');
        redirect('/admin/index.php');
    }
}

// Handle quick verify provider action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_verify') {
    if (validate_csrf()) {
        $provider_id = (int)$_POST['provider_id'];
        $new_status = (int)$_POST['new_status'];
        $stmtVer = $db->prepare("UPDATE service_providers SET is_verified = ? WHERE id = ?");
        $stmtVer->execute([$new_status, $provider_id]);
        set_flash('success', 'Status verifikasi mitra berhasil diperbarui.');
        redirect('/admin/index.php');
    }
}

// Ambil Status Langganan Gateway WhatsApp & Domain
$fonnte_info = get_fonnte_account_info(false);
$domain_info = get_domain_subscription_info();


// 1. Ambil statistik (Hanya akun yang telah menyelesaikan verifikasi OTP)
$total_users = (int)$db->query("SELECT COUNT(*) FROM users WHERE role_id = 2 AND email_verified_at IS NOT NULL")->fetchColumn();
$total_providers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role_id = 3 AND email_verified_at IS NOT NULL")->fetchColumn();
$total_categories = (int)$db->query("SELECT COUNT(*) FROM service_categories")->fetchColumn();
$total_requests = (int)$db->query("SELECT COUNT(*) FROM service_requests")->fetchColumn();
$total_unverified = (int)$db->query("SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL")->fetchColumn();

// 2. Daftar mitra penyedia jasa terbaru (yang sudah terverifikasi OTP)
$stmtProviders = $db->query("
    SELECT sp.id, sp.user_id, sp.business_name, sp.is_verified, sp.rating_avg, sp.completed_jobs, sp.created_at,
           u.name as user_name, u.email, u.phone, sc.name as category_name, d.name as district_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    WHERE u.email_verified_at IS NOT NULL
    ORDER BY sp.created_at DESC
    LIMIT 6
");
$recent_providers = $stmtProviders->fetchAll();

// 3. Daftar permintaan jasa terbaru
$stmtRequests = $db->query("
    SELECT sr.*, u.name as customer_name, sc.name as category_name, d.name as district_name
    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id
    JOIN service_categories sc ON sr.category_id = sc.id
    JOIN districts d ON sr.district_id = d.id
    ORDER BY sr.created_at DESC
    LIMIT 6
");
$recent_requests = $stmtRequests->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">Dashboard Pengelola Platform</h1>
        <p class="text-muted small mb-0">Pemantauan aktivitas marketplace jasa Kabupaten Indragiri Hulu</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/admin/export_providers.php" target="_blank" class="btn btn-danger btn-sm fw-semibold shadow-xs">
            <i class="fa-solid fa-file-pdf me-1"></i> Cetak PDF Mitra
        </a>
        <a href="<?= BASE_URL ?>/admin/banners.php" class="btn btn-primary-custom btn-sm fw-semibold">
            <i class="fa-solid fa-rectangle-ad me-1"></i> Kelola Banner & Iklan
        </a>
        <span class="badge text-bg-light border p-2">
            <i class="fa-solid fa-clock me-1 text-primary"></i> <?= date('d M Y, H:i') ?> WIB
        </span>
    </div>
</div>

<?php if ($total_unverified > 0): ?>
    <div class="alert alert-warning border-0 shadow-xs mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-clock text-warning fs-5"></i>
            <div>
                <strong class="text-dark">Ada <?= $total_unverified ?> akun pendaftar yang belum menyelesaikan verifikasi OTP.</strong>
                <div class="small text-muted">Anda dapat memeriksa atau langsung memverifikasinya secara manual.</div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/admin/users.php?role=unverified" class="btn btn-sm btn-warning text-dark fw-bold px-3 py-1.5 rounded-pill shadow-xs">
            Lihat & Verifikasi Manual &rarr;
        </a>
    </div>
<?php endif; ?>


<!-- PUSAT PENGINGAT LANGGANAN & MASA AKTIF LAYANAN (WHATSAPP, DOMAIN, SERVER) -->
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: #ffffff; border: 1px solid rgba(226, 232, 240, 0.8) !important;">
    <div class="card-header border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff;">
        <div class="d-flex align-items-center gap-2.5">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(45, 212, 191, 0.2); color: #2dd4bf;">
                <i class="fa-solid fa-bell-concierge fs-6"></i>
            </span>
            <div>
                <h6 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                    Pusat Pengingat Langganan & Masa Aktif Layanan
                    <span class="badge rounded-pill bg-teal text-dark fw-bold px-2 py-0.5" style="font-size: 0.68rem; background-color: #2dd4bf !important;">Billing Monitor</span>
                </h6>
                <small class="text-white-50" style="font-size: 0.76rem;">Pantau jatuh tempo & estimasi biaya sewa Gateway WhatsApp, Domain, dan Server JASA INHU</small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="small text-white-50 d-none d-sm-inline" style="font-size: 0.74rem;">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Terakhir Cek: <?= !empty($fonnte_info['last_checked']) ? date('H:i', strtotime($fonnte_info['last_checked'])) : 'Baru saja' ?> WIB
            </span>
            <a href="<?= BASE_URL ?>/admin/index.php?refresh_gateway=1" class="btn btn-xs btn-outline-light rounded-pill px-3 py-1 fw-semibold shadow-xs" title="Periksa status API Fonnte terbaru sekarang">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Perbarui Status Fonnte
            </a>
        </div>
    </div>
    <div class="card-body p-3.5 bg-white">
        <div class="row g-3">
            <!-- 1. WhatsApp Gateway (Fonnte API) -->
            <div class="col-lg-4 col-md-6">
                <div class="p-3.5 rounded-3 border h-100 position-relative <?= $fonnte_info['days_remaining'] <= 7 ? 'border-warning bg-warning bg-opacity-10' : 'bg-light bg-opacity-50' ?>" style="border-left: 5px solid #25d366 !important;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white shadow-xs" style="width: 34px; height: 34px; background: #25d366;">
                                <i class="fa-brands fa-whatsapp fs-5"></i>
                            </span>
                            <div>
                                <strong class="d-block text-dark small fw-bold">WhatsApp Gateway</strong>
                                <span class="text-muted" style="font-size: 0.72rem;">Fonnte Cloud API</span>
                            </div>
                        </div>
                        <?php if ($fonnte_info['is_connected']): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-bold" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-circle text-success me-1" style="font-size: 0.45rem;"></i> Terhubung
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-bold" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-circle text-danger me-1" style="font-size: 0.45rem;"></i> Terputus
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="my-2.5 p-2.5 rounded-3 bg-white border shadow-2xs">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small" style="font-size: 0.75rem;">Jatuh Tempo:</span>
                            <span class="fw-bold small text-dark"><?= e($fonnte_info['expired_raw']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small" style="font-size: 0.75rem;">Hitung Mundur:</span>
                            <span class="badge <?= $fonnte_info['days_remaining'] <= 7 ? 'bg-danger text-white' : ($fonnte_info['days_remaining'] <= 14 ? 'bg-warning text-dark' : 'bg-primary text-white') ?> fw-bold px-2.5 py-1 shadow-xs" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-hourglass-half me-1"></i> <?= $fonnte_info['days_remaining'] ?> Hari Tersisa
                            </span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between text-muted small mb-3 px-1" style="font-size: 0.74rem;">
                        <span>Sisa Kuota: <strong class="text-dark"><?= number_format($fonnte_info['quota']) ?> pesan</strong></span>
                        <span>Paket: <strong class="badge bg-light text-dark border"><?= e($fonnte_info['package']) ?></strong></span>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="https://fonnte.com" target="_blank" class="btn btn-sm btn-success fw-bold flex-grow-1 shadow-xs" style="font-size: 0.76rem;">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Perpanjang / Top Up
                        </a>
                        <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-sm btn-outline-secondary" title="Pengaturan Token Gateway" style="font-size: 0.76rem;">
                            <i class="fa-solid fa-gear"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 2. Domain Website (.my.id) -->
            <div class="col-lg-4 col-md-6">
                <div class="p-3.5 rounded-3 border h-100 position-relative <?= $domain_info['days_remaining'] <= 30 ? 'border-warning bg-warning bg-opacity-10' : 'bg-light bg-opacity-50' ?>" style="border-left: 5px solid #0284c7 !important;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white shadow-xs" style="width: 34px; height: 34px; background: #0284c7;">
                                <i class="fa-solid fa-globe fs-6"></i>
                            </span>
                            <div>
                                <strong class="d-block text-dark small fw-bold">Domain Website</strong>
                                <span class="text-muted font-monospace" style="font-size: 0.72rem;"><?= e($domain_info['domain_name']) ?></span>
                            </div>
                        </div>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2.5 py-1 small fw-bold" style="font-size: 0.7rem;">
                            <i class="fa-solid fa-check me-1"></i> Aktif
                        </span>
                    </div>

                    <div class="my-2.5 p-2.5 rounded-3 bg-white border shadow-2xs">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small" style="font-size: 0.75rem;">Jatuh Tempo:</span>
                            <span class="fw-bold small text-dark"><?= e($domain_info['expiry_formatted']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small" style="font-size: 0.75rem;">Hitung Mundur:</span>
                            <span class="badge <?= $domain_info['days_remaining'] <= 30 ? 'bg-warning text-dark' : 'bg-success text-white' ?> fw-bold px-2.5 py-1 shadow-xs" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-calendar-day me-1"></i> <?= $domain_info['days_remaining'] ?> Hari Tersisa
                            </span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between text-muted small mb-3 px-1" style="font-size: 0.74rem;">
                        <span>Biaya: <strong class="text-success fw-bold">~Rp 15rb - 25rb / thn</strong></span>
                        <span class="text-truncate" style="max-width: 130px;" title="<?= e($domain_info['registrar']) ?>"><?= e($domain_info['registrar']) ?></span>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold w-100 shadow-xs" data-bs-toggle="modal" data-bs-target="#modalDomainExpiry" style="font-size: 0.76rem;">
                        <i class="fa-solid fa-calendar-check me-1"></i> Atur Tanggal Jatuh Tempo
                    </button>
                </div>
            </div>

            <!-- 3. Server Hosting & Database (Gratis Selamanya) -->
            <div class="col-lg-4 col-md-12">
                <div class="p-3.5 rounded-3 border h-100 position-relative bg-light bg-opacity-50" style="border-left: 5px solid #10b981 !important;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white shadow-xs" style="width: 34px; height: 34px; background: #10b981;">
                                <i class="fa-solid fa-server fs-6"></i>
                            </span>
                            <div>
                                <strong class="d-block text-dark small fw-bold">Server & Database</strong>
                                <span class="text-muted" style="font-size: 0.72rem;">Vercel + TiDB Cloud</span>
                            </div>
                        </div>
                        <span class="badge bg-success text-white rounded-pill px-2.5 py-1 small fw-bold" style="font-size: 0.7rem;">
                            100% BEBAS BIAYA
                        </span>
                    </div>

                    <div class="my-2.5 p-2.5 rounded-3 bg-white border shadow-2xs">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small" style="font-size: 0.75rem;">Hosting Web:</span>
                            <span class="badge bg-light text-dark border">Vercel (Rp 0 / Selamanya)</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small" style="font-size: 0.75rem;">Database:</span>
                            <span class="badge bg-light text-dark border">TiDB Serverless (25 GB Free)</span>
                        </div>
                    </div>

                    <p class="text-muted mb-2.5 small px-1" style="font-size: 0.73rem; line-height: 1.4;">
                        <i class="fa-solid fa-circle-check text-success me-1"></i> <strong>Bebas Tagihan Bulanan:</strong> Tidak ada biaya sewa server bulanan. Sistem berjalan otomatis di cloud global.
                    </p>

                    <a href="<?= BASE_URL ?>/admin/backup.php" class="btn btn-sm btn-outline-success fw-bold w-100 shadow-xs" style="font-size: 0.76rem;">
                        <i class="fa-solid fa-cloud-arrow-down me-1"></i> Cadangkan Database (.sql)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Masyarakat / Pengguna</span>
                <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div class="h3 fw-bold mb-1"><?= $total_users ?></div>
            <div class="small text-muted">Warga terdaftar di Inhu</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Mitra Penyedia Jasa</span>
                <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
                    <i class="fa-solid fa-briefcase"></i>
                </div>
            </div>
            <div class="h3 fw-bold mb-1"><?= $total_providers ?></div>
            <div class="small text-muted">Tukang & teknisi lokal</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Kategori Jasa</span>
                <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                    <i class="fa-solid fa-shapes"></i>
                </div>
            </div>
            <div class="h3 fw-bold mb-1"><?= $total_categories ?></div>
            <div class="small text-muted">Bidang layanan aktif</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= BASE_URL ?>/admin/orders.php" class="text-decoration-none text-dark">
            <div class="stat-card hover-lift transition">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold text-muted">Pesanan Masuk</span>
                    <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-1"><?= $total_requests ?></div>
                <div class="small text-muted d-flex align-items-center justify-content-between">
                    <span>Total pesanan warga</span>
                    <span class="text-primary fw-semibold" style="font-size: 0.72rem;">Buka &rarr;</span>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Banner Arsip & Rekapitulasi Data Mitra Resmi -->
<div class="card border-0 rounded-3 shadow-xs mb-4 text-white overflow-hidden position-relative" style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #0369a1 100%);">
    <div class="card-body p-3 p-md-4 position-relative z-1 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 50px; height: 50px; font-size: 1.4rem;">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-1 text-white">Laporan & Rekapitulasi Data Mitra Resmi</h5>
                <p class="mb-0 opacity-90 small" style="font-size: 0.82rem;">
                    Unduh atau cetak Buku Daftar <?= $total_providers ?> Mitra berizin/terdaftar lengkap dengan kop surat resmi untuk arsip, audiensi Pemkab/Dinas Koperasi & UMKM, atau presentasi kemitraan.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <a href="<?= BASE_URL ?>/admin/export_providers.php" target="_blank" class="btn btn-light text-teal fw-bold shadow-sm d-flex align-items-center gap-1.5 px-3 py-2 rounded-3">
                <i class="fa-solid fa-file-pdf text-danger"></i>
                <span>Cetak / Unduh PDF</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/export_providers.php?format=excel" class="btn btn-outline-light fw-semibold d-flex align-items-center gap-1.5 px-3 py-2 rounded-3">
                <i class="fa-solid fa-file-excel text-warning"></i>
                <span>Excel (CSV)</span>
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Tabel Mitra Penyedia Jasa -->
    <div class="col-lg-7">
        <div class="card border shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users-gear me-2 text-primary"></i> Mitra Penyedia Jasa Terdaftar</h6>
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= BASE_URL ?>/admin/export_providers.php" target="_blank" class="badge bg-danger-subtle text-danger border border-danger-subtle text-decoration-none py-1.5 px-2">
                        <i class="fa-solid fa-file-pdf me-0.5"></i> PDF
                    </a>
                    <a href="<?= BASE_URL ?>/admin/users.php?role=penyedia" class="small text-decoration-none">Semua Mitra &rarr;</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Usaha / Pemilik</th>
                            <th>Kategori</th>
                            <th>Kecamatan</th>
                            <th>Status Verifikasi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_providers)): ?>
                            <?php foreach ($recent_providers as $p): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/admin/provider_detail.php?id=<?= $p['id'] ?>" class="fw-bold text-dark text-decoration-none hover-teal" title="Buka Lembar Berkas Arsip Mitra">
                                            <?= e($p['business_name']) ?> <i class="fa-solid fa-arrow-up-right-from-square text-teal ms-0.5" style="font-size: 0.65rem;"></i>
                                        </a>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= e($p['user_name']) ?> &bull; <?= e($p['phone']) ?></div>
                                    </td>
                                    <td><span class="badge text-bg-light border"><?= e($p['category_name']) ?></span></td>
                                    <td><?= e($p['district_name'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($p['is_verified']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="fa-solid fa-check"></i> Terverifikasi
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                Menunggu
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="<?= BASE_URL ?>/admin/provider_detail.php?id=<?= $p['id'] ?>" class="btn btn-outline-teal btn-sm py-1 px-2" style="font-size: 0.72rem;" title="Lembar Arsip Mitra">
                                                <i class="fa-solid fa-file-invoice"></i>
                                            </a>
                                            <form method="POST" action="<?= BASE_URL ?>/admin/index.php" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_verify">
                                                <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $p['is_verified'] ? 0 : 1 ?>">
                                                <button type="submit" class="btn btn-xs <?= $p['is_verified'] ? 'btn-outline-danger' : 'btn-success' ?> btn-sm py-1 px-2" style="font-size: 0.72rem;">
                                                    <?= $p['is_verified'] ? 'Batalkan' : 'Verifikasi' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">Belum ada penyedia jasa.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabel Permintaan Jasa Terbaru -->
    <div class="col-lg-5">
        <div class="card border shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-bullhorn me-2 text-warning"></i> Permintaan Jasa Masuk</h6>
                <a href="<?= BASE_URL ?>/admin/orders.php" class="small text-decoration-none">Kelola Semua Pesanan &rarr;</a>
            </div>
            <div class="list-group list-group-flush small">
                <?php if (!empty($recent_requests)): ?>
                    <?php foreach ($recent_requests as $req): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge text-bg-light border text-primary"><?= e($req['category_name']) ?></span>
                                <span class="badge <?= $req['status'] === 'open' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= strtoupper(e($req['status'])) ?></span>
                            </div>
                            <div class="fw-bold text-dark mb-1"><?= e($req['title']) ?></div>
                            <div class="text-muted" style="font-size: 0.78rem;">
                                Pemohon: <strong><?= e($req['customer_name']) ?></strong> &bull; Kec. <?= e($req['district_name']) ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <span class="fw-semibold text-teal"><?= format_rupiah($req['budget']) ?></span>
                                <span class="text-muted" style="font-size: 0.72rem;"><?= format_date($req['created_at']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">Belum ada permintaan pekerjaan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Atur Tanggal Jatuh Tempo Domain -->
<div class="modal fade" id="modalDomainExpiry" tabindex="-1" aria-labelledby="modalDomainExpiryLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff;">
                <h6 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="modalDomainExpiryLabel">
                    <i class="fa-solid fa-calendar-check text-info"></i> Pengingat Perpanjangan Domain
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/admin/index.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_domain_expiry">
                <div class="modal-body p-4">
                    <div class="alert alert-info-subtle border border-info-subtle rounded-3 small mb-3">
                        <i class="fa-solid fa-circle-info text-primary me-1"></i>
                        Domain <strong><?= e($domain_info['domain_name']) ?></strong> biasanya disewa per 1 tahun di registrar tempat Anda membelinya (seperti DomaiNesia, Niagahoster, Rumahweb, dll). Masukkan tanggal jatuh tempo sesuai bukti order/email Anda agar sistem selalu menampilkan hitung mundur sebelum masa aktif habis.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nama Domain Utama:</label>
                        <input type="text" class="form-control bg-light font-monospace" value="<?= e($domain_info['domain_name']) ?>" readonly>
                        <small class="text-muted" style="font-size: 0.72rem;">Subdomain aktif: <code><?= e($domain_info['subdomain']) ?></code></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Tanggal Jatuh Tempo / Habis Masa Sewa:</label>
                        <input type="date" name="domain_expiry_date" class="form-control fw-bold" value="<?= e($domain_info['expiry_date']) ?>" required>
                        <small class="text-muted" style="font-size: 0.72rem;">Sistem akan menghitung mundur sisa hari secara otomatis setiap hari.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Penyedia Registrar Domain:</label>
                        <input type="text" name="domain_registrar" class="form-control" value="<?= e($domain_info['registrar']) ?>" placeholder="Contoh: DomaiNesia / Niagahoster / Rumahweb">
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2 px-4">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary-custom btn-sm fw-bold shadow-xs">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Pengingat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


<?php
/**
 * Berkas Lembar Arsip Satuan per Mitra (Provider Master Dossier)
 * JASA INHU - Portal Layanan Terpadu Kabupaten Indragiri Hulu
 */

$page_title = 'Lembar Arsip Master Mitra';
$admin_active = 'users';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();
$error = '';
$success = '';

// Ambil ID Provider atau User ID
$provider_id = (int)($_GET['id'] ?? 0);
$user_id = (int)($_GET['user_id'] ?? 0);

if ($provider_id <= 0 && $user_id > 0) {
    $stmtP = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
    $stmtP->execute([$user_id]);
    $provRow = $stmtP->fetch();
    if ($provRow) {
        $provider_id = (int)$provRow['id'];
    }
}

if ($provider_id <= 0) {
    set_flash('danger', 'ID Penyedia Jasa tidak valid atau tidak ditemukan.');
    redirect('/admin/users.php');
}

// Handle Admin Actions on this partner
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Sesi keamanan berakhir, silakan coba lagi.');
        redirect("/admin/provider_detail.php?id={$provider_id}");
    }

    $action = trim($_POST['action'] ?? '');

    // 1. Toggle Verifikasi KTP / Pelajar
    if ($action === 'toggle_verif') {
        $new_status = (int)($_POST['new_status'] ?? 0);
        $stmt = $db->prepare("UPDATE service_providers SET is_verified = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $provider_id]);

        $msg = ($new_status === 1) ? 'Status berkas mitra berhasil disetujui & diverifikasi resmi.' : 'Status verifikasi berkas mitra berhasil dibatalkan.';
        set_flash('success', $msg);
        redirect("/admin/provider_detail.php?id={$provider_id}");
    }

    // 2. Blokir / Buka Blokir Akun Pemilik
    if ($action === 'toggle_user_active') {
        $target_uid = (int)($_POST['user_id'] ?? 0);
        $new_active = (int)($_POST['is_active'] ?? 1);
        $reason = trim($_POST['block_reason'] ?? '');

        if ($new_active === 0) {
            $reason = !empty($reason) ? $reason : 'Dinonaktifkan oleh administrator platform';
            $db->prepare("UPDATE users SET is_active = 0, block_reason = ?, blocked_at = NOW(), updated_at = NOW() WHERE id = ?")
               ->execute([$reason, $target_uid]);
            set_flash('warning', "Akun mitra berhasil diblokir. Alasan: \"{$reason}\"");
        } else {
            $db->prepare("UPDATE users SET is_active = 1, block_reason = NULL, blocked_at = NULL, updated_at = NOW() WHERE id = ?")
               ->execute([$target_uid]);
            set_flash('success', "Blokir akun berhasil dicabut. Akun mitra aktif kembali.");
        }
        redirect("/admin/provider_detail.php?id={$provider_id}");
    }
}

// Ambil Data Master Mitra Lengkap
$stmt = $db->prepare("
    SELECT sp.*, 
           sc.name as category_name, sc.icon as category_icon,
           d.name as district_name, v.name as village_name,
           u.id as user_id, u.name as owner_name, u.email as owner_email, u.phone as owner_phone,
           u.is_active as user_active, u.block_reason, u.blocked_at, u.email_verified_at, u.created_at as registered_at,
           p.avatar as owner_avatar, p.gender, p.birth_date, p.address as profile_address
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    LEFT JOIN villages v ON sp.village_id = v.id
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE sp.id = ?
    LIMIT 1
");
$stmt->execute([$provider_id]);
$p = $stmt->fetch();

if (!$p) {
    set_flash('danger', 'Data berkas mitra tidak ditemukan di database.');
    redirect('/admin/users.php');
}

// Ambil Wilayah Operasional (Service Areas)
$stmtAreas = $db->prepare("
    SELECT d.name as district_name
    FROM service_areas sa
    JOIN districts d ON sa.district_id = d.id
    WHERE sa.provider_id = ?
    ORDER BY d.name ASC
");
$stmtAreas->execute([$provider_id]);
$service_areas = $stmtAreas->fetchAll();

// Ambil Portofolio Pekerjaan
$stmtPort = $db->prepare("
    SELECT * FROM provider_portfolios 
    WHERE provider_id = ? 
    ORDER BY created_at DESC
");
$stmtPort->execute([$provider_id]);
$portfolios = $stmtPort->fetchAll();

// Ambil Riwayat Pesanan Masuk Terkait Mitra Ini
$stmtOrders = $db->prepare("
    SELECT sr.*, sc.name as category_name, cust.name as customer_name, cust.phone as customer_phone,
           d.name as district_name, v.name as village_name
    FROM service_requests sr
    JOIN users cust ON sr.user_id = cust.id
    JOIN service_categories sc ON sr.category_id = sc.id
    LEFT JOIN districts d ON sr.district_id = d.id
    LEFT JOIN villages v ON sr.village_id = v.id
    WHERE sr.provider_id = ?
    ORDER BY sr.created_at DESC
");
$stmtOrders->execute([$provider_id]);
$orders = $stmtOrders->fetchAll();

// Ambil Ulasan & Rating Pelanggan
$stmtReviews = $db->prepare("
    SELECT r.*, cust.name as customer_name, cust.phone as customer_phone, sr.title as request_title
    FROM reviews r
    JOIN users cust ON r.user_id = cust.id
    LEFT JOIN service_requests sr ON r.request_id = sr.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
");
$stmtReviews->execute([$provider_id]);
$reviews = $stmtReviews->fetchAll();

// Format nomor WhatsApp
$wa_number = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $p['owner_phone']));
$is_student = (($p['id_card_type'] ?? '') === 'pelajar');
$doc_label = $is_student ? 'Kartu Tanda Pelajar (KTP Pelajar)' : 'Kartu Tanda Penduduk (KTP)';

// Cek ketersediaan berkas identitas & sertifikat
$has_ktp = !empty($p['id_card_image']) && strlen($p['id_card_image']) > 30;
$has_cert = !empty($p['certificate_url']) && strlen($p['certificate_url']) > 30;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Print Specific Styling for A4 Dossier -->
<style>
@media print {
    /* Hide dashboard chrome */
    .dashboard-sidebar, 
    .navbar, 
    .admin-header, 
    .btn-toolbar, 
    .no-print, 
    .modal, 
    footer,
    #sidebarMenu {
        display: none !important;
    }
    
    body {
        background: #fff !important;
        font-family: 'Times New Roman', Times, serif, 'Inter', sans-serif !important;
        color: #111 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    main {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .print-only {
        display: block !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        margin-bottom: 12px !important;
        page-break-inside: avoid;
    }
    
    .card-header {
        background-color: #f1f1f1 !important;
        border-bottom: 1px solid #000 !important;
        color: #000 !important;
        font-weight: bold !important;
    }
    
    .badge {
        border: 1px solid #333 !important;
        color: #000 !important;
        background: transparent !important;
    }
    
    .table {
        border-color: #000 !important;
    }
    
    .table th, .table td {
        border: 1px solid #333 !important;
        padding: 4px 8px !important;
    }

    .doc-preview-print {
        max-height: 220px !important;
        object-fit: contain !important;
        border: 1px solid #666 !important;
    }

    a {
        text-decoration: none !important;
        color: #000 !important;
    }
}

.print-only {
    display: none;
}

.dossier-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 2px;
}

.dossier-value {
    font-size: 0.92rem;
    font-weight: 600;
    color: #1e293b;
}

.portfolio-thumb-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
}
.portfolio-thumb-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
}
</style>

<!-- Top Breadcrumb & Action Toolbar (Screen Only) -->
<div class="no-print">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/users.php" class="text-decoration-none">Kelola Pengguna</a></li>
                    <li class="breadcrumb-item active text-truncate" style="max-width: 250px;">Arsip: <?= e($p['business_name']) ?></li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-0 d-flex align-items-center gap-2 flex-wrap">
                <span>Berkas Lembar Arsip Satuan Mitra</span>
                <span class="badge bg-light text-teal border fs-6 py-1 px-2.5">
                    <?= format_user_id($p['user_id']) ?> / #<?= $p['id'] ?>
                </span>
            </h1>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" onclick="window.print()" class="btn btn-danger fw-bold shadow-sm d-inline-flex align-items-center gap-1.5">
                <i class="fa-solid fa-file-pdf"></i> Cetak / Simpan PDF Lembar Arsip
            </button>
            <a href="https://wa.me/<?= $wa_number ?>?text=Halo%20<?= urlencode($p['business_name']) ?>%2C%20saya%20dari%20Administrator%20Jasa%20Inhu..." target="_blank" class="btn btn-success fw-bold shadow-sm d-inline-flex align-items-center gap-1.5">
                <i class="fa-brands fa-whatsapp"></i> WhatsApp Pemilik
            </a>
            <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<!-- KOP RESMI LEMBAR ARSIP (TAMPIL HANYA SAAT CETAK / PRINT PDF) -->
<div class="print-only mb-4 border-bottom pb-3">
    <div class="d-flex align-items-center gap-3">
        <div style="width: 70px; text-align: center;">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" style="max-height: 65px; width: auto;" onerror="this.src='<?= BASE_URL ?>/assets/images/logojasa.png'">
        </div>
        <div class="flex-grow-1 text-center">
            <h5 class="fw-bold mb-0 text-uppercase" style="letter-spacing: 0.5px; font-size: 1.15rem;">PEMERINTAH KABUPATEN INDRAGIRI HULU</h5>
            <h6 class="fw-bold mb-0" style="font-size: 0.95rem;">DINAS KOMUNIKASI DAN INFORMATIKA &bull; PORTAL TERPADU JASA INHU</h6>
            <p class="small mb-0 text-muted" style="font-size: 0.75rem;">Jalan Lintas Rengat - Pematang Reba, Rengat Barat, Riau 29351 | Website Resmi: https://jasainhu.my.id</p>
            <div class="fw-bold text-uppercase mt-2 py-1 px-3 border border-dark d-inline-block" style="font-size: 0.88rem; background: #f8f9fa;">
                LEMBAR ARSIP MASTER DATA MITRA PENYEDIA JASA
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between small text-muted mt-2" style="font-size: 0.72rem;">
        <span>Tanggal Cetak: <?= date('d F Y, H:i:s') ?> WIB</span>
        <span>Kode Arsip: JSA-MITRA-<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?>-INHU</span>
    </div>
</div>

<!-- Header Card Mitra (Profil Utama) -->
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden border">
    <div class="bg-teal text-white p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="position-relative">
                    <?php 
                    $prov_avatar_url = get_provider_cover_url($p);
                    if (!empty($prov_avatar_url)): 
                    ?>
                        <img src="<?= $prov_avatar_url ?>" alt="Avatar" class="rounded-circle border border-3 border-white shadow-sm object-fit-cover" style="width: 75px; height: 75px;">
                    <?php else: ?>
                        <div class="rounded-circle bg-white text-teal d-flex align-items-center justify-content-center fw-bold fs-3 shadow-sm" style="width: 75px; height: 75px;">
                            <?= strtoupper(mb_substr($p['business_name'] ?: $p['owner_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($p['is_verified'])): ?>
                        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-warning text-dark border border-white" title="Terverifikasi Resmi">
                            <i class="fa-solid fa-check"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h2 class="h4 fw-bold mb-0 text-white"><?= e($p['business_name']) ?></h2>
                        <?php if (!empty($p['category_name'])): ?>
                            <span class="badge bg-white text-teal fw-semibold">
                                <i class="<?= e($p['category_icon'] ?: 'fa-solid fa-tag') ?> me-1"></i> <?= e($p['category_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-white-50 small d-flex align-items-center gap-3 flex-wrap">
                        <span><i class="fa-solid fa-user me-1"></i> Penanggung Jawab: <strong class="text-white"><?= e($p['owner_name']) ?></strong></span>
                        <span><i class="fa-solid fa-map-pin me-1"></i> Kec. <?= e($p['district_name'] ?: 'Kab. Inhu') ?></span>
                        <span><i class="fa-regular fa-calendar-check me-1"></i> Bergabung: <?= format_date($p['registered_at']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Action Status (Screen Only) -->
            <div class="d-flex align-items-center gap-2 flex-wrap no-print">
                <?php if (empty($p['is_verified'])): ?>
                    <form method="POST" action="<?= BASE_URL ?>/admin/provider_detail.php?id=<?= $p['id'] ?>" class="d-inline" onsubmit="return confirm('Apakah Anda yakin menyetujui verifikasi berkas mitra ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_verif">
                        <input type="hidden" name="new_status" value="1">
                        <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm shadow-sm">
                            <i class="fa-solid fa-circle-check me-1"></i> Setujui Verifikasi KTP
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>/admin/provider_detail.php?id=<?= $p['id'] ?>" class="d-inline" onsubmit="return confirm('Batalkan status verifikasi mitra ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_verif">
                        <input type="hidden" name="new_status" value="0">
                        <button type="submit" class="btn btn-outline-light btn-sm text-white border-white-50">
                            <i class="fa-solid fa-xmark me-1"></i> Cabut Verifikasi
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($p['user_active']): ?>
                    <button type="button" class="btn btn-danger btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#blockUserModal">
                        <i class="fa-solid fa-ban me-1"></i> Blokir Akun
                    </button>
                <?php else: ?>
                    <form method="POST" action="<?= BASE_URL ?>/admin/provider_detail.php?id=<?= $p['id'] ?>" class="d-inline" onsubmit="return confirm('Buka blokir akun ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_user_active">
                        <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" class="btn btn-success btn-sm shadow-sm">
                            <i class="fa-solid fa-circle-check me-1"></i> Buka Blokir
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sub-Bar Info Ringkas -->
    <div class="card-body bg-light py-3 border-top">
        <div class="row g-3 text-center text-md-start">
            <div class="col-6 col-md-3 border-end">
                <div class="dossier-label">Status Akun</div>
                <div>
                    <?php if ($p['user_active']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                            <i class="fa-solid fa-circle-check me-1"></i> Aktif Beroperasi
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger text-white px-2 py-1">
                            <i class="fa-solid fa-ban me-1"></i> Diblokir: <?= e($p['block_reason'] ?: 'Pelanggaran') ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-md-3 border-end">
                <div class="dossier-label">Status Dokumen & Verifikasi</div>
                <div>
                    <?php if (!empty($p['is_verified'])): ?>
                        <span class="badge bg-teal-subtle text-teal border border-teal-subtle px-2 py-1">
                            <i class="fa-solid fa-certificate me-1"></i> <?= $is_student ? 'Pelajar Terverifikasi' : 'KTP Terverifikasi' ?>
                        </span>
                    <?php elseif ($has_ktp): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                            <i class="fa-solid fa-clock me-1"></i> Menunggu Verifikasi Admin
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary px-2 py-1">
                            <i class="fa-solid fa-file-circle-question me-1"></i> Belum Mengunggah KTP
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-md-3 border-end">
                <div class="dossier-label">Performa & Pesanan</div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark fs-6"><i class="fa-solid fa-star text-warning me-1"></i> <?= number_format((float)($p['rating_avg'] ?? 0), 1) ?></span>
                    <span class="text-muted small">(<?= (int)($p['reviews_count'] ?? 0) ?> ulasan)</span>
                    <span class="badge bg-light text-dark border ms-auto"><?= (int)($p['completed_jobs'] ?? 0) ?> Selesai</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="dossier-label">Saldo Dompet Mitra</div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-teal fs-6"><?= format_rupiah($p['wallet_balance'] ?? 0) ?></span>
                    <a href="<?= BASE_URL ?>/admin/wallet.php" class="badge bg-light text-teal border text-decoration-none no-print" title="Kelola Saldo di Panel Dompet">
                        <i class="fa-solid fa-wallet me-0.5"></i> Kelola
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grid Master Informasi Mitra -->
<div class="row g-4">
    <!-- Kolom Kiri: Identitas Usaha & Identitas Pemilik -->
    <div class="col-lg-7">
        <!-- 1. IDENTITAS USAHA & SPESIFIKASI LAYANAN -->
        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-store me-2 text-teal"></i> 1. Identitas Usaha & Spesifikasi Jasa
                </h6>
                <span class="badge text-bg-light border text-muted">Profil Mitra</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="dossier-label">Nama Usaha / Merk Dagang</div>
                        <div class="dossier-value"><?= e($p['business_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Kategori Utama</div>
                        <div class="dossier-value text-teal">
                            <i class="<?= e($p['category_icon'] ?: 'fa-solid fa-tag') ?> me-1"></i> <?= e($p['category_name'] ?: 'Umum / Serbaguna') ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="dossier-label">Slogan / Headline Layanan</div>
                        <div class="dossier-value text-muted fw-normal fst-italic">
                            <?= !empty($p['headline']) ? '"' . e($p['headline']) . '"' : '<span class="text-secondary">- Tidak ada slogan -</span>' ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Pengalaman Kerja</div>
                        <div class="dossier-value">
                            <i class="fa-solid fa-briefcase me-1 text-secondary"></i> <?= !empty($p['experience_years']) ? (int)$p['experience_years'] . ' Tahun Pengalaman' : 'Pemula / Kurang dari 1 tahun' ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Estimasi Tarif Dasar Jasa</div>
                        <div class="dossier-value text-teal">
                            <?php if (!empty($p['hourly_rate_min']) || !empty($p['hourly_rate_max'])): ?>
                                <?= format_rupiah($p['hourly_rate_min'] ?? 0) ?> s/d <?= format_rupiah($p['hourly_rate_max'] ?? 0) ?>
                                <small class="text-muted fw-normal">(<?= e($p['price_type'] ?: 'per jam/proyek') ?>)</small>
                            <?php else: ?>
                                <span class="text-muted fw-normal">Menyesuaikan Kesepakatan</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="dossier-label">Deskripsi Lengkap Layanan & Keahlian</div>
                        <div class="p-3 bg-light rounded-3 border small" style="line-height: 1.6; white-space: pre-line;">
                            <?= !empty($p['description']) ? e($p['description']) : '<span class="text-muted fst-italic">Mitra belum menambahkan deskripsi detail keahlian.</span>' ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Kecamatan Domisili</div>
                        <div class="dossier-value"><?= e($p['district_name'] ?: 'Kabupaten Indragiri Hulu') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Desa / Kelurahan</div>
                        <div class="dossier-value"><?= e($p['village_name'] ?: '-') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="dossier-label">Alamat Lengkap Workshop / Lokasi</div>
                        <div class="dossier-value fw-normal"><?= e($p['address'] ?: ($p['profile_address'] ?: 'Alamat belum diatur secara detail')) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="dossier-label">Cakupan Wilayah Layanan yang Siap Didatangi</div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <?php if (!empty($service_areas)): ?>
                                <?php foreach ($service_areas as $area): ?>
                                    <span class="badge bg-light text-dark border py-1 px-2">
                                        <i class="fa-solid fa-location-dot text-teal me-1"></i> Kec. <?= e($area['district_name']) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border py-1 px-2">
                                    <i class="fa-solid fa-globe text-teal me-1"></i> Melayani Seluruh Wilayah Kec. <?= e($p['district_name'] ?: 'Indragiri Hulu') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. IDENTITAS PEMILIK / PENANGGUNG JAWAB HUKUM -->
        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-address-card me-2 text-teal"></i> 2. Identitas Penanggung Jawab Akun
                </h6>
                <span class="badge text-bg-light border text-muted">Data Akun Pengguna</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="dossier-label">Nama Lengkap Pemilik Sesuai KTP</div>
                        <div class="dossier-value"><?= e($p['owner_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Nomor WhatsApp / HP</div>
                        <div class="dossier-value">
                            <a href="https://wa.me/<?= $wa_number ?>" target="_blank" class="text-teal text-decoration-none fw-bold">
                                <i class="fa-brands fa-whatsapp text-success me-1"></i> <?= e($p['owner_phone']) ?>
                            </a>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Alamat Email Terdaftar</div>
                        <div class="dossier-value text-break"><?= e($p['owner_email']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Status Verifikasi OTP</div>
                        <div class="dossier-value">
                            <?php if (!empty($p['email_verified_at'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="fa-solid fa-check-double me-1"></i> Terverifikasi (<?= format_date($p['email_verified_at']) ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                    <i class="fa-solid fa-clock me-1"></i> Belum Verif OTP
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Jenis Kelamin</div>
                        <div class="dossier-value">
                            <?= ($p['gender'] === 'male') ? 'Laki-laki' : (($p['gender'] === 'female') ? 'Perempuan' : 'Belum diisi') ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dossier-label">Tanggal Lahir</div>
                        <div class="dossier-value">
                            <?= !empty($p['birth_date']) ? date('d F Y', strtotime($p['birth_date'])) : 'Belum diisi' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Berkas Dokumen & Kredensial Resmi -->
    <div class="col-lg-5">
        <!-- 3. BERKAS IDENTITAS RESMI (KTP / KARTU PELAJAR) -->
        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-id-card me-2 text-teal"></i> 3. Berkas Identitas Resmi
                </h6>
                <span class="badge <?= !empty($p['is_verified']) ? 'bg-success text-white' : 'bg-warning text-dark' ?>">
                    <?= !empty($p['is_verified']) ? 'Terverifikasi' : 'Belum Diverifikasi' ?>
                </span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="dossier-label">Jenis Tanda Pengenal</div>
                    <div class="dossier-value">
                        <i class="fa-solid <?= $is_student ? 'fa-graduation-cap text-purple' : 'fa-id-card text-teal' ?> me-1"></i>
                        <?= $is_student ? 'Kartu Pelajar Siswa / Mahasiswa' : 'KTP (Kartu Tanda Penduduk)' ?>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="dossier-label"><?= $is_student ? 'Nomor Induk Siswa Nasional (NISN)' : 'Nomor Induk Kependudukan (NIK)' ?></div>
                    <div class="dossier-value fs-5 font-monospace text-dark">
                        <?= !empty($p['id_card_number']) ? e($p['id_card_number']) : '<span class="text-muted fs-6 fw-normal">- Nomor belum diisi -</span>' ?>
                    </div>
                </div>
                <?php if ($is_student && !empty($p['school_name'])): ?>
                    <div class="mb-3">
                        <div class="dossier-label">Nama Sekolah / Lembaga Pendidikan</div>
                        <div class="dossier-value text-purple fw-bold">
                            <i class="fa-solid fa-school me-1"></i> <?= e($p['school_name']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="dossier-label mb-1">Pratinjau Fisik Berkas Identitas</div>
                <?php if ($has_ktp): ?>
                    <div class="p-2 border rounded-3 bg-light text-center position-relative">
                        <!-- Direct Secure Stream URL -->
                        <img src="<?= BASE_URL ?>/admin/view_doc.php?user_id=<?= $p['user_id'] ?>&type=ktp" 
                             alt="Berkas KTP" 
                             class="img-fluid rounded-2 border shadow-xs doc-preview-print" 
                             style="max-height: 230px; width: 100%; object-fit: contain; background: #fff;"
                             onerror="this.src='<?= BASE_URL ?>/assets/images/placeholder.jpg'">
                        
                        <div class="mt-2.5 d-flex align-items-center justify-content-center gap-2 no-print">
                            <a href="<?= BASE_URL ?>/admin/view_doc.php?user_id=<?= $p['user_id'] ?>&type=ktp" target="_blank" class="btn btn-sm btn-outline-teal py-1 px-2.5 shadow-xs">
                                <i class="fa-solid fa-up-right-from-square me-1"></i> Buka Ukuran Asli
                            </a>
                            <a href="<?= BASE_URL ?>/admin/view_doc.php?user_id=<?= $p['user_id'] ?>&type=ktp&download=1" class="btn btn-sm btn-outline-secondary py-1 px-2.5 shadow-xs">
                                <i class="fa-solid fa-download me-1"></i> Unduh File
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-4 border border-dashed rounded-3 text-center bg-light text-muted">
                        <i class="fa-solid fa-id-card-clip fs-1 text-secondary opacity-50 mb-2"></i>
                        <div class="fw-semibold small">Foto KTP Belum Diunggah</div>
                        <div class="text-secondary" style="font-size: 0.72rem;">Mitra belum melengkapi foto KTP / Kartu Pelajar saat mendaftar.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4. SERTIFIKAT KOMPETENSI / STR / IJAZAH PENDUKUNG -->
        <div class="card border shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-award me-2 text-teal"></i> 4. Sertifikat / STR / Lisensi Keahlian
                </h6>
                <span class="badge text-bg-light border text-muted">Kredensial</span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="dossier-label">Nama Sertifikasi / Lisensi / STR</div>
                    <div class="dossier-value">
                        <?= !empty($p['credential_title']) ? e($p['credential_title']) : '<span class="text-muted fw-normal fst-italic">Tidak mencantumkan STR / Sertifikat khusus</span>' ?>
                    </div>
                </div>

                <div class="dossier-label mb-1">Dokumen Sertifikat</div>
                <?php if ($has_cert): ?>
                    <div class="p-2 border rounded-3 bg-light text-center">
                        <img src="<?= BASE_URL ?>/admin/view_doc.php?user_id=<?= $p['user_id'] ?>&type=str" 
                             alt="Sertifikat STR" 
                             class="img-fluid rounded-2 border shadow-xs doc-preview-print" 
                             style="max-height: 220px; width: 100%; object-fit: contain; background: #fff;"
                             onerror="this.src='<?= BASE_URL ?>/assets/images/placeholder.jpg'">
                        <div class="mt-2.5 d-flex align-items-center justify-content-center gap-2 no-print">
                            <a href="<?= BASE_URL ?>/admin/view_doc.php?user_id=<?= $p['user_id'] ?>&type=str" target="_blank" class="btn btn-sm btn-outline-teal py-1 px-2.5 shadow-xs">
                                <i class="fa-solid fa-up-right-from-square me-1"></i> Buka Ukuran Asli
                            </a>
                            <a href="<?= BASE_URL ?>/admin/view_doc.php?user_id=<?= $p['user_id'] ?>&type=str&download=1" class="btn btn-sm btn-outline-secondary py-1 px-2.5 shadow-xs">
                                <i class="fa-solid fa-download me-1"></i> Unduh File
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-3 border border-dashed rounded-3 text-center bg-light text-muted">
                        <i class="fa-solid fa-file-shield fs-2 text-secondary opacity-50 mb-1"></i>
                        <div class="small fw-semibold">Tidak Ada Berkas Sertifikat</div>
                        <div class="text-secondary" style="font-size: 0.72rem;">Mitra tidak melampirkan berkas sertifikat tambahan.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 5. GALERI PORTOFOLIO PEKERJAAN MITRA -->
<div class="card border shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-images me-2 text-teal"></i> 5. Portofolio Hasil Pengerjaan Lapangan (<?= count($portfolios) ?> Proyek)
        </h6>
        <span class="badge text-bg-light border text-muted">Bukti Pengerjaan</span>
    </div>
    <div class="card-body">
        <?php if (!empty($portfolios)): ?>
            <div class="row g-3">
                <?php foreach ($portfolios as $port): ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card h-100 border rounded-3 overflow-hidden shadow-2xs portfolio-thumb-card">
                            <div class="position-relative bg-dark" style="height: 160px;">
                                <?php if (!empty($port['image_after'])): ?>
                                    <img src="<?= BASE_URL ?>/<?= ltrim($port['image_after'], '/') ?>" alt="<?= e($port['title']) ?>" class="w-100 h-100 object-fit-cover" onerror="this.src='<?= BASE_URL ?>/assets/images/placeholder.jpg'">
                                <?php elseif (!empty($port['image_before'])): ?>
                                    <img src="<?= BASE_URL ?>/<?= ltrim($port['image_before'], '/') ?>" alt="<?= e($port['title']) ?>" class="w-100 h-100 object-fit-cover" onerror="this.src='<?= BASE_URL ?>/assets/images/placeholder.jpg'">
                                <?php endif; ?>
                                <?php if (!empty($port['image_before']) && !empty($port['image_after'])): ?>
                                    <span class="position-absolute bottom-0 start-0 m-2 badge bg-teal text-white py-1 px-1.5" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-code-compare me-0.5"></i> Before-After
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-2.5">
                                <div class="fw-bold text-dark small text-truncate" title="<?= e($port['title']) ?>"><?= e($port['title']) ?></div>
                                <?php if (!empty($port['service_date'])): ?>
                                    <div class="text-muted" style="font-size: 0.7rem;">
                                        <i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y', strtotime($port['service_date'])) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($port['description'])): ?>
                                    <div class="text-secondary small mt-1 line-clamp-2" style="font-size: 0.72rem; line-height: 1.3;">
                                        <?= e(mb_strimwidth($port['description'], 0, 85, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted">
                <i class="fa-solid fa-images fs-2 text-secondary opacity-50 mb-2"></i>
                <div class="fw-semibold small">Belum ada portofolio pekerjaan yang diunggah oleh mitra ini.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 6. RIWAYAT PESANAN MASUK TERKAIT MITRA INI -->
<div class="card border shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-clipboard-list me-2 text-teal"></i> 6. Riwayat Pesanan & Transaksi Masuk (<?= count($orders) ?> Pesanan)
        </h6>
        <a href="<?= BASE_URL ?>/admin/orders.php?q=<?= urlencode($p['business_name']) ?>" class="btn btn-sm btn-outline-teal py-0 px-2 no-print" style="font-size: 0.75rem;">
            Buka di Pusat Pesanan &rarr;
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;" class="text-center">No.</th>
                    <th>Judul Pesanan / Pekerjaan</th>
                    <th>Pemesan (Warga)</th>
                    <th>Wilayah</th>
                    <th>Biaya / Budget</th>
                    <th>Status</th>
                    <th class="text-end">Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php $no = 1; foreach ($orders as $o): ?>
                        <tr>
                            <td class="text-center fw-bold text-secondary"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($o['title']) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;">#ORD-<?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?> &bull; <?= e($o['category_name']) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= e($o['customer_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= e($o['customer_phone']) ?></div>
                            </td>
                            <td>
                                <div>Kec. <?= e($o['district_name'] ?: 'Inhu') ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= e($o['village_name'] ?: '-') ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-teal"><?= format_rupiah($o['final_price'] ?: $o['budget']) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><?= !empty($o['final_price']) ? 'Harga Final' : 'Estimasi Budget' ?></div>
                            </td>
                            <td>
                                <?php
                                $badgeClass = match($o['status']) {
                                    'completed'   => 'bg-success-subtle text-success border border-success-subtle',
                                    'in_progress' => 'bg-info-subtle text-info border border-info-subtle',
                                    'cancelled'   => 'bg-danger-subtle text-danger border border-danger-subtle',
                                    default       => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?> px-2 py-1">
                                    <?= strtoupper(e($o['status'])) ?>
                                </span>
                            </td>
                            <td class="text-end text-muted">
                                <?= format_date($o['created_at']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            Belum ada pesanan transaksi yang tercatat untuk mitra ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 7. ULASAN & TESTIMONI PELANGGAN -->
<div class="card border shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="fa-solid fa-comments me-2 text-teal"></i> 7. Ulasan & Testimoni Pelanggan (<?= count($reviews) ?> Ulasan)
        </h6>
        <span class="badge text-bg-light border text-muted">Kepuasan Pelanggan</span>
    </div>
    <div class="card-body">
        <?php if (!empty($reviews)): ?>
            <div class="row g-3">
                <?php foreach ($reviews as $rev): ?>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light h-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-dark small"><?= e($rev['customer_name']) ?></span>
                                <div class="text-warning small">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-<?= $i <= (int)$rev['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if (!empty($rev['request_title'])): ?>
                                <div class="text-teal small fw-semibold mb-1" style="font-size: 0.72rem;">
                                    <i class="fa-solid fa-tag me-1"></i> Pesanan: <?= e($rev['request_title']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="text-secondary small fst-italic" style="font-size: 0.78rem; line-height: 1.4;">
                                "<?= e($rev['comment'] ?: 'Tidak ada catatan ulasan.') ?>"
                            </div>
                            <div class="text-muted text-end mt-2" style="font-size: 0.68rem;">
                                <?= format_date($rev['created_at']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted">
                <i class="fa-regular fa-comment-dots fs-2 text-secondary opacity-50 mb-2"></i>
                <div class="fw-semibold small">Belum ada ulasan rating dari warga pelanggan.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- BLOK TANDA TANGAN & PENGESAHAN LEMBAR ARSIP (TAMPIL HANYA SAAT CETAK / PDF) -->
<div class="print-only mt-5 pt-3">
    <div class="row">
        <div class="col-6 text-center">
            <div class="small mb-1">Mengetahui,</div>
            <div class="fw-bold small">Mitra Penyedia Jasa / Penanggung Jawab</div>
            <div style="height: 75px;"></div>
            <div class="fw-bold text-decoration-underline"><?= e($p['owner_name']) ?></div>
            <div class="small text-muted"><?= e($p['business_name']) ?></div>
        </div>
        <div class="col-6 text-center">
            <div class="small mb-1">Pematang Reba, <?= date('d F Y') ?></div>
            <div class="fw-bold small">Administrator Validasi Berkas JASA INHU</div>
            <div style="height: 75px;"></div>
            <div class="fw-bold text-decoration-underline"><?= e($_SESSION['user_name'] ?? 'Petugas Administrator') ?></div>
            <div class="small text-muted">NIP / ID Petugas: ADM-<?= str_pad($_SESSION['user_id'] ?? 1, 4, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>
</div>

<!-- MODAL BLOKIR AKUN MITRA (Screen Only) -->
<div class="modal fade no-print" id="blockUserModal" tabindex="-1" aria-labelledby="blockUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="<?= BASE_URL ?>/admin/provider_detail.php?id=<?= $p['id'] ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_user_active">
                <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                <input type="hidden" name="is_active" value="0">
                <div class="modal-header bg-danger text-white border-0 py-3">
                    <h6 class="modal-title fw-bold" id="blockUserModalLabel">
                        <i class="fa-solid fa-triangle-exclamation me-1.5"></i> Blokir Akun Mitra: <?= e($p['business_name']) ?>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i> Akun yang diblokir tidak akan dapat login, profil toko disembunyikan dari pencarian publik, dan tidak menerima pesanan baru.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Alasan Pemblokiran <span class="text-danger">*</span></label>
                        <textarea name="block_reason" class="form-control" rows="3" placeholder="Contoh: Dokumen KTP tidak sesuai data, pelanggaran etika kerja, dll." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">
                        <i class="fa-solid fa-ban me-1"></i> Konfirmasi Blokir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Profil Usaha Penyedia Jasa JASA INHU
 */

$page_title = 'Profil Usaha Jasa';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('penyedia');

$user = current_user();
$db = get_db();
$error = '';
$_SESSION['active_profile_mode'] = 'penyedia';

$stmtProv = $db->prepare("
    SELECT sp.*, sc.name as category_name, sc.icon as category_icon, 
           d.name as district_name, v.name as village_name
    FROM service_providers sp
    LEFT JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    LEFT JOIN villages v ON sp.village_id = v.id
    WHERE sp.user_id = ? 
    LIMIT 1
");
$stmtProv->execute([$user['id']]);
$provider = $stmtProv->fetch();

if (!$provider) {
    set_flash('danger', 'Data penyedia jasa tidak ditemukan.');
    redirect('/user/profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir.';
    } else {
        $business_name = trim($_POST['business_name'] ?? '');
        $headline = trim($_POST['headline'] ?? '');
        $credential_title = trim($_POST['credential_title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $experience = (int)($_POST['experience_years'] ?? 1);
        $rate_min = (float)($_POST['hourly_rate_min'] ?? 0);
        $rate_max = (float)($_POST['hourly_rate_max'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
        $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
        $address = trim($_POST['address'] ?? '');
        $id_card_number = trim($_POST['id_card_number'] ?? ($provider['id_card_number'] ?? ''));

        $image_url = $provider['image_url'] ?? '';
        $certificate_url = $provider['certificate_url'] ?? '';
        $id_card_image = $provider['id_card_image'] ?? '';

        // Handle image upload jika ada file foto baru
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_exts)) {
                $error = 'Format file tidak didukung. Gunakan JPG, PNG, atau WebP.';
            } elseif ($file['size'] > 3 * 1024 * 1024) {
                $error = 'Ukuran file foto terlalu besar. Maksimal 3MB.';
            } else {
                $filename = 'provider_' . $provider['id'] . '_' . time() . '.' . $ext;
                $targetPath = __DIR__ . '/../uploads/providers/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    if (!empty($provider['image_url']) && file_exists(__DIR__ . '/../' . $provider['image_url'])) {
                        @unlink(__DIR__ . '/../' . $provider['image_url']);
                    }
                    $image_url = 'uploads/providers/' . $filename;
                }
            }
        }

        // Handle certificate/STR upload (Opsional khusus tenaga medis/homecare)
        if (empty($error) && isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
            $fileCert = $_FILES['certificate'];
            $allowed_cert_exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $extCert = strtolower(pathinfo($fileCert['name'], PATHINFO_EXTENSION));

            if (!in_array($extCert, $allowed_cert_exts)) {
                $error = 'Format sertifikat/STR tidak didukung. Gunakan JPG, PNG, WebP, atau PDF.';
            } elseif ($fileCert['size'] > 4 * 1024 * 1024) {
                $error = 'Ukuran berkas sertifikat/STR maksimal 4MB.';
            } else {
                $filenameCert = 'cert_' . $provider['id'] . '_' . time() . '.' . $extCert;
                $targetPathCert = __DIR__ . '/../uploads/certificates/' . $filenameCert;
                if (move_uploaded_file($fileCert['tmp_name'], $targetPathCert)) {
                    if (!empty($provider['certificate_url']) && file_exists(__DIR__ . '/../' . $provider['certificate_url'])) {
                        @unlink(__DIR__ . '/../' . $provider['certificate_url']);
                    }
                    $certificate_url = 'uploads/certificates/' . $filenameCert;
                }
            }
        }

        // Handle Foto KTP Upload (Untuk Verifikasi Identitas)
        if (empty($error) && isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] === UPLOAD_ERR_OK) {
            $fileKtp = $_FILES['id_card_image'];
            $allowed_ktp_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $extKtp = strtolower(pathinfo($fileKtp['name'], PATHINFO_EXTENSION));

            if (!in_array($extKtp, $allowed_ktp_exts)) {
                $error = 'Format foto KTP tidak didukung. Gunakan file JPG, PNG, atau WebP.';
            } elseif ($fileKtp['size'] > 4 * 1024 * 1024) {
                $error = 'Ukuran berkas foto KTP maksimal 4MB.';
            } else {
                $filenameKtp = 'ktp_' . $provider['id'] . '_' . time() . '.' . $extKtp;
                $targetPathKtp = __DIR__ . '/../uploads/ktp/' . $filenameKtp;
                if (move_uploaded_file($fileKtp['tmp_name'], $targetPathKtp)) {
                    if (!empty($provider['id_card_image']) && file_exists(__DIR__ . '/../' . $provider['id_card_image'])) {
                        @unlink(__DIR__ . '/../' . $provider['id_card_image']);
                    }
                    $id_card_image = 'uploads/ktp/' . $filenameKtp;
                }
            }
        }

        if (empty($error)) {
            if (empty($business_name) || empty($category_id)) {
                $error = 'Nama usaha dan kategori utama wajib diisi.';
            } else {
                try {
                    $db->beginTransaction();
                    $stmtU = $db->prepare("UPDATE users SET phone = ?, updated_at = NOW() WHERE id = ?");
                    $stmtU->execute([$phone, $user['id']]);

                    $stmtP = $db->prepare("
                        UPDATE service_providers 
                        SET business_name = ?, image_url = ?, headline = ?, credential_title = ?, certificate_url = ?,
                            id_card_number = ?, id_card_image = ?,
                            description = ?, primary_category_id = ?,
                            experience_years = ?, hourly_rate_min = ?, hourly_rate_max = ?,
                            district_id = ?, village_id = ?, address = ?, updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $stmtP->execute([$business_name, $image_url, $headline, $credential_title, $certificate_url, $id_card_number, $id_card_image, $description, $category_id, $experience, $rate_min, $rate_max, $district_id, $village_id, $address, $user['id']]);

                    // Sinkronkan juga ke tabel profiles pengguna
                    $stmtProf = $db->prepare("UPDATE profiles SET district_id = ?, village_id = ?, address = ?, updated_at = NOW() WHERE user_id = ?");
                    $stmtProf->execute([$district_id, $village_id, $address, $user['id']]);

                    // Daftarkan ke basis wilayah operasional jika ada kecamatan
                    if ($district_id) {
                        $stmtArea = $db->prepare("INSERT IGNORE INTO service_areas (provider_id, district_id) VALUES (?, ?)");
                        $stmtArea->execute([$provider['id'], $district_id]);
                    }

                    $db->commit();

                    set_flash('success', 'Profil usaha jasa, dokumen KTP, dan informasi operasional Anda berhasil diperbarui!');
                    redirect('/provider/profile.php');
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Gagal menyimpan profil: ' . $e->getMessage();
                }
            }
        }
    }
}

$categories = get_active_categories();
$districts = get_all_districts();
$current_villages = [];
if (!empty($provider['district_id'])) {
    $stmtV = $db->prepare("SELECT id, name, postal_code FROM villages WHERE district_id = ? ORDER BY name ASC");
    $stmtV->execute([$provider['district_id']]);
    $current_villages = $stmtV->fetchAll();
}

// Hitung total pekerjaan selesai
$stmtComp = $db->prepare("
    SELECT COUNT(*) 
    FROM service_requests 
    WHERE (provider_id = ? OR id IN (SELECT request_id FROM service_request_responses WHERE provider_id = ? AND status = 'accepted'))
      AND status = 'completed'
");
$stmtComp->execute([$provider['id'], $provider['id']]);
$total_completed = (int)$stmtComp->fetchColumn();

// Hitung portofolio foto kerja
$stmtPort = $db->prepare("SELECT COUNT(*) FROM provider_portfolios WHERE provider_id = ?");
$stmtPort->execute([$provider['id']]);
$portfolio_count = (int)$stmtPort->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-3 py-md-4" style="max-width: 760px;">
    <!-- Top Navigation Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-2xs">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard Orderan
        </a>
        <span class="badge bg-teal-subtle text-teal fw-semibold rounded-pill px-3 py-1.5" style="font-size: 0.75rem;">
            <i class="fa-solid fa-store me-1"></i> Akun Mitra Penyedia Jasa
        </span>
    </div>

    <!-- Alert Notifikasi -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs small py-2.5 px-3 mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-1.5"></i> <?= e($error) ?>
            <button type="button" class="btn-close py-2.5" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 1. KARTU IDENTITAS MITRA UTAMA (Hero Profile Card) -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3 text-white" style="background: linear-gradient(135deg, #0d9488 0%, #064e3b 100%);">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-start gap-3 flex-wrap flex-sm-nowrap">
                <!-- Foto Profil / Usaha -->
                <div class="position-relative flex-shrink-0">
                    <div class="rounded-4 overflow-hidden border border-2 border-white shadow-xs" style="width: 76px; height: 76px; background: #e2e8f0;">
                        <?php if (!empty($provider['image_url']) && file_exists(__DIR__ . '/../' . $provider['image_url'])): ?>
                            <img src="<?= BASE_URL ?>/<?= e($provider['image_url']) ?>" alt="Foto Usaha" class="w-100 h-100 object-fit-cover">
                        <?php else: ?>
                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-25 text-white">
                                <i class="fa-solid fa-store fs-3"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informasi Usaha -->
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h5 class="fw-bold text-white mb-0 text-truncate" style="font-size: 1.15rem;">
                            <?= e($provider['business_name'] ?: $user['name']) ?>
                        </h5>
                        <?php if (!empty($provider['is_verified'])): ?>
                            <span class="badge bg-white text-teal rounded-pill px-2.5 py-1 fw-bold shadow-2xs" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-circle-check text-success me-1"></i> Terverifikasi KTP
                            </span>
                        <?php elseif (!empty($provider['id_card_image'])): ?>
                            <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-clock me-1"></i> Validasi KTP Menunggu
                            </span>
                        <?php else: ?>
                            <span class="badge bg-black bg-opacity-25 text-white-50 border border-white border-opacity-25 rounded-pill px-2.5 py-1" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-circle-exclamation text-warning me-1"></i> Belum Verifikasi KTP
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="text-white-50 small mb-2.5 d-flex align-items-center gap-2 flex-wrap" style="font-size: 0.78rem;">
                        <span><i class="fa-solid fa-user me-1 opacity-75"></i> <?= e($user['name']) ?></span>
                        <span>&bull;</span>
                        <span><i class="fa-brands fa-whatsapp text-white me-1"></i> <?= e($user['phone'] ?? '-') ?></span>
                        <span>&bull;</span>
                        <span><i class="<?= e($provider['category_icon'] ?? 'fa-solid fa-tag') ?> me-1 opacity-75"></i> <?= e($provider['category_name'] ?? 'Penyedia Jasa') ?></span>
                    </div>

                    <!-- 2 Tombol Aksi Utama yang Jelas -->
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $provider['id'] ?>" target="_blank" class="btn btn-sm btn-white fw-bold rounded-pill px-3 shadow-xs bg-white text-teal" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-eye me-1 text-teal"></i> Lihat Tampilan Publik di Katalog ↗
                        </a>
                        <a href="<?= BASE_URL ?>/provider/portfolio.php" class="btn btn-sm btn-outline-light rounded-pill px-3" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-camera me-1"></i> Portofolio Foto (<?= $portfolio_count ?>)
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mini Stats Bar di dalam Hero Card -->
        <div class="bg-black bg-opacity-20 px-3 py-2.5 border-top border-white border-opacity-10">
            <div class="row g-2 text-center text-sm-start align-items-center">
                <div class="col-4 border-end border-white border-opacity-15">
                    <div class="text-white-50" style="font-size: 0.68rem;">Saldo Kuota Pesanan</div>
                    <div class="fw-bold text-white small d-flex align-items-center gap-1.5 justify-content-center justify-content-sm-start">
                        <span>Rp <?= number_format($provider['wallet_balance'] ?? 0, 0, ',', '.') ?></span>
                        <a href="<?= BASE_URL ?>/provider/wallet.php" class="badge bg-warning text-dark text-decoration-none rounded-pill" style="font-size: 0.62rem;">+ Topup</a>
                    </div>
                </div>
                <div class="col-4 border-end border-white border-opacity-15">
                    <div class="text-white-50" style="font-size: 0.68rem;">Rating & Ulasan</div>
                    <div class="fw-bold text-white small">
                        <i class="fa-solid fa-star text-warning me-0.5"></i> <?= number_format($provider['rating_avg'] ?? 5.0, 1) ?>
                        <span class="text-white-50" style="font-size: 0.68rem;">(<?= (int)($provider['total_reviews'] ?? 0) ?>)</span>
                    </div>
                </div>
                <div class="col-4">
                    <div class="text-white-50" style="font-size: 0.68rem;">Pekerjaan Selesai</div>
                    <div class="fw-bold text-white small">
                        <i class="fa-solid fa-circle-check text-white me-0.5"></i> <?= $total_completed ?> Order
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. FORMULIR PENGATURAN BERBASIS TAB (ANTI-BINGUNG) -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-3">
        <form method="POST" action="<?= BASE_URL ?>/provider/profile.php" enctype="multipart/form-data" id="formProviderProfile">
            <?= csrf_field() ?>

            <!-- Nav Tabs Sederhana & Ramah -->
            <div class="p-2.5 bg-light border-bottom">
                <ul class="nav nav-pills nav-fill gap-1" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-2 px-2 rounded-3 fw-bold small d-flex align-items-center justify-content-center gap-1.5" id="tab-info-btn" data-bs-toggle="pill" data-bs-target="#tab-info" type="button" role="tab" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-briefcase"></i>
                            <span>1. Info Jasa & Tarif</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-2 rounded-3 fw-bold small d-flex align-items-center justify-content-center gap-1.5" id="tab-location-btn" data-bs-toggle="pill" data-bs-target="#tab-location" type="button" role="tab" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-location-dot"></i>
                            <span>2. Lokasi Layanan</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-2 rounded-3 fw-bold small d-flex align-items-center justify-content-center gap-1.5 position-relative" id="tab-ktp-btn" data-bs-toggle="pill" data-bs-target="#tab-ktp" type="button" role="tab" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-id-card"></i>
                            <span>3. Verifikasi KTP</span>
                            <?php if (empty($provider['is_verified']) && empty($provider['id_card_image'])): ?>
                                <span class="position-absolute top-0 end-0 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 8px; height: 8px;"></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-3 p-md-4">
                <div class="tab-content" id="profileTabsContent">
                    <!-- ================= TAB 1: INFO JASA & TARIF ================= -->
                    <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                        <!-- Petunjuk Singkat -->
                        <div class="p-2.5 rounded-3 mb-3 border bg-light small text-muted d-flex align-items-center gap-2" style="font-size: 0.74rem;">
                            <i class="fa-solid fa-circle-info text-teal fs-6 flex-shrink-0"></i>
                            <div>
                                Data ini akan dilihat oleh calon pelanggan saat mencari tukang & jasa di Indragiri Hulu.
                            </div>
                        </div>

                        <!-- Upload Foto Usaha / Profil -->
                        <div class="p-3 mb-3 rounded-3 border bg-light">
                            <label class="form-label fw-bold text-dark mb-1 small d-flex align-items-center justify-content-between flex-wrap gap-1">
                                <span><i class="fa-solid fa-camera text-teal me-1"></i> Foto Profil / Hasil Kerja / Tempat Usaha</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-normal" style="font-size: 0.7rem;">
                                    <i class="fa-solid fa-check me-0.5"></i> Tidak Wajib Punya Toko Fisik
                                </span>
                            </label>
                            <div class="text-muted small mb-2.5" style="font-size: 0.73rem;">
                                Boleh foto diri Anda yang rapi, hasil pekerjaan sebelumnya, atau tempat kerja Anda jika ada.
                            </div>

                            <div class="d-flex align-items-center gap-3">
                                <div id="imagePreviewContainer" class="position-relative rounded-3 overflow-hidden border shadow-xs flex-shrink-0" style="width: 100px; height: 75px; background: #e2e8f0;">
                                    <?php if (!empty($provider['image_url']) && file_exists(__DIR__ . '/../' . $provider['image_url'])): ?>
                                        <img id="imagePreview" src="<?= BASE_URL ?>/<?= e($provider['image_url']) ?>" alt="Foto Usaha" class="w-100 h-100 object-fit-cover">
                                    <?php else: ?>
                                        <div id="imagePlaceholder" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                                            <i class="fa-solid fa-image fs-4 mb-0.5 opacity-50"></i>
                                            <span style="font-size: 0.65rem;">Belum ada</span>
                                        </div>
                                        <img id="imagePreview" src="" alt="Preview Foto" class="w-100 h-100 object-fit-cover d-none">
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="image" id="providerImageInput" class="form-control form-control-sm mb-1" accept="image/jpeg,image/png,image/webp" onchange="previewProviderPhoto(this)">
                                    <div class="text-muted" style="font-size: 0.68rem;">Format: JPG, PNG, WebP (Maks. 3 MB). Disarankan foto horizontal.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Baris Input -->
                        <div class="row g-2.5">
                            <div class="col-md-7 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Nama Usaha / Merek Layanan Jasa <span class="text-danger">*</span></label>
                                <input type="text" name="business_name" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" value="<?= e($provider['business_name'] ?? '') ?>" placeholder="Contoh: Bengkel AC Berkah / Tukang <?= e($user['name']) ?>" required>
                                <div class="text-muted" style="font-size: 0.68rem; margin-top: 2px;">Bisa nama pribadi atau nama merek usaha Anda.</div>
                            </div>
                            <div class="col-md-5 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Nomor WhatsApp Usaha <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" value="<?= e($user['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                                <div class="text-muted" style="font-size: 0.68rem; margin-top: 2px;">Untuk menerima chat pesanan dari pelanggan.</div>
                            </div>

                            <div class="col-12 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Slogan / Headline Promosi Singkat</label>
                                <input type="text" name="headline" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" value="<?= e($provider['headline'] ?? '') ?>" placeholder="Contoh: Ahli Servis AC & Kelistrikan Bergaransi di Rengat">
                            </div>

                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Kategori Utama Keahlian <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select form-select-sm rounded-3 py-1.5 px-2.5" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= (($provider['primary_category_id'] ?? 0) == $cat['id']) ? 'selected' : '' ?>>
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Pengalaman Kerja (Tahun)</label>
                                <input type="number" name="experience_years" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" value="<?= (int)($provider['experience_years'] ?? 1) ?>" min="0">
                            </div>

                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Perkiraan Biaya Mulai Dari (Rp)</label>
                                <input type="number" name="hourly_rate_min" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" value="<?= (float)($provider['hourly_rate_min'] ?? 0) ?>" placeholder="Contoh: 35000">
                                <div class="text-muted" style="font-size: 0.68rem; margin-top: 2px;">Boleh diisi 0 jika biaya dinegosiasikan setelah cek lokasi.</div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Hingga Sekitar (Rp)</label>
                                <input type="number" name="hourly_rate_max" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" value="<?= (float)($provider['hourly_rate_max'] ?? 0) ?>" placeholder="Contoh: 150000">
                            </div>

                            <div class="col-12 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Deskripsi Keahlian & Jangkauan Layanan</label>
                                <textarea name="description" rows="3" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" placeholder="Jelaskan jenis pekerjaan yang bisa Anda tangani, kelebihan layanan, garansi, atau jam buka Anda..."><?= e($provider['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- ================= TAB 2: WILAYAH & ALAMAT ================= -->
                    <div class="tab-pane fade" id="tab-location" role="tabpanel">
                        <!-- Petunjuk Singkat -->
                        <div class="p-2.5 rounded-3 mb-3 border bg-light small text-muted d-flex align-items-center gap-2" style="font-size: 0.74rem;">
                            <i class="fa-solid fa-map-location-dot text-danger fs-6 flex-shrink-0"></i>
                            <div>
                                Tentukan wilayah domisili Anda di Kabupaten Indragiri Hulu agar calon pelanggan terdekat dapat menemukan jasa Anda.
                            </div>
                        </div>

                        <div class="row g-2.5">
                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Kecamatan Domisili / Layanan (Inhu)</label>
                                <select name="district_id" id="district_select" class="form-select form-select-sm rounded-3 py-1.5 px-2.5">
                                    <option value="">-- Pilih Kecamatan (Opsional) --</option>
                                    <?php foreach ($districts as $dist): ?>
                                        <option value="<?= $dist['id'] ?>" <?= (($provider['district_id'] ?? 0) == $dist['id']) ? 'selected' : '' ?>>
                                            Kec. <?= e($dist['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Desa / Kelurahan</label>
                                <select name="village_id" id="village_select" class="form-select form-select-sm rounded-3 py-1.5 px-2.5" <?= empty($current_villages) ? 'disabled' : '' ?>>
                                    <?php if (empty($current_villages)): ?>
                                        <option value="">-- Pilih Kecamatan Dahulu --</option>
                                    <?php else: ?>
                                        <option value="">-- Pilih Desa / Kelurahan (Opsional) --</option>
                                        <?php foreach ($current_villages as $vil): ?>
                                            <option value="<?= $vil['id'] ?>" <?= (($provider['village_id'] ?? 0) == $vil['id']) ? 'selected' : '' ?>>
                                                <?= e($vil['name']) ?> <?= !empty($vil['postal_code']) ? '(' . e($vil['postal_code']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-12 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Alamat Lengkap / Patokan Tempat Tinggal</label>
                                <textarea name="address" rows="2" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" placeholder="Contoh: Jl. Narasinga, Rengat (Dekat Pasar / Masjid)"><?= e($provider['address'] ?? '') ?></textarea>
                                <div class="text-muted" style="font-size: 0.68rem; margin-top: 2px;">
                                    Bagi pekerja mandiri tanpa toko fisik, cukup tuliskan patokan domisili tempat tinggal Anda.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ================= TAB 3: VERIFIKASI KTP & SERTIFIKAT ================= -->
                    <div class="tab-pane fade" id="tab-ktp" role="tabpanel">
                        <!-- Banner Status Verifikasi -->
                        <div class="p-3 rounded-3 mb-3 border <?php if (!empty($provider['is_verified'])): ?>bg-success bg-opacity-10 border-success<?php elseif (!empty($provider['id_card_image'])): ?>bg-warning bg-opacity-10 border-warning<?php else: ?>bg-light border-secondary-subtle<?php endif; ?>">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1.5">
                                <div class="fw-bold small text-dark d-flex align-items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved text-teal fs-6"></i>
                                    <span>Status Verifikasi Identitas KTP</span>
                                </div>
                                <?php if (!empty($provider['is_verified'])): ?>
                                    <span class="badge bg-success text-white px-2.5 py-1 rounded-pill fw-semibold" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-circle-check me-1"></i> Terverifikasi KTP Resmi
                                    </span>
                                <?php elseif (!empty($provider['id_card_image'])): ?>
                                    <span class="badge bg-warning text-dark px-2.5 py-1 rounded-pill fw-semibold" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-clock me-1"></i> Berkas KTP Sedang Divalidasi Admin
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2.5 py-1 rounded-pill fw-semibold" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i> Belum Verifikasi KTP
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted small mb-0" style="font-size: 0.74rem; line-height: 1.45;">
                                Unggah foto KTP Anda agar mendapatkan lencana centang resmi. Profil yang terverifikasi diprioritaskan di hasil pencarian dan lebih dipercaya warga Indragiri Hulu.
                            </p>
                        </div>

                        <!-- Form NIK & KTP -->
                        <div class="row g-2.5 mb-3">
                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Nomor Induk Kependudukan (NIK KTP)</label>
                                <input type="text" name="id_card_number" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" maxlength="20" placeholder="16 digit NIK KTP Anda" value="<?= e($provider['id_card_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label mb-1 small fw-bold text-dark">Unggah Foto KTP Asli</label>
                                <input type="file" name="id_card_image" class="form-control form-control-sm rounded-3" accept="image/jpeg,image/png,image/webp">
                                <?php if (!empty($provider['id_card_image']) && file_exists(__DIR__ . '/../' . $provider['id_card_image'])): ?>
                                    <div class="mt-1 small d-flex align-items-center justify-content-between" style="font-size: 0.72rem;">
                                        <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i> Foto KTP tersimpan</span>
                                        <a href="<?= BASE_URL ?>/<?= e($provider['id_card_image']) ?>" target="_blank" class="fw-semibold text-teal text-decoration-none">
                                            <i class="fa-solid fa-eye me-0.5"></i> Lihat Foto ↗
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-2.5 rounded-3 border bg-light small text-muted d-flex align-items-start gap-2 mb-3" style="font-size: 0.73rem;">
                            <i class="fa-solid fa-lock text-success mt-0.5 fs-6 flex-shrink-0"></i>
                            <div>
                                <strong>Privasi Dijamin Aman:</strong> Foto KTP hanya disimpan untuk validasi internal pengelola <?= APP_NAME ?>. Foto KTP <strong>tidak akan pernah ditampilkan ke publik atau pelanggan</strong>.
                            </div>
                        </div>

                        <!-- Khusus Tenaga Medis: STR / Ijazah Keperawatan (Collapsed) -->
                        <div class="border rounded-3 p-2.5 bg-light">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 0.78rem;">
                                        <i class="fa-solid fa-user-doctor text-teal me-1"></i> Khusus Tenaga Medis / Perawat Homecare (Opsional)
                                    </div>
                                    <div class="text-muted" style="font-size: 0.7rem;">
                                        Tukang umum, montir, teknisi AC, ART dll <strong>tidak perlu mengisi ini</strong>.
                                    </div>
                                </div>
                                <button class="btn btn-xs btn-outline-teal rounded-pill px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCredentials">
                                    <i class="fa-solid fa-award me-1"></i> <?= !empty($provider['credential_title']) ? 'Ubah STR' : '+ Punya STR Medis?' ?>
                                </button>
                            </div>

                            <div class="collapse <?= !empty($provider['credential_title']) ? 'show' : '' ?> mt-2.5 pt-2.5 border-top" id="collapseCredentials">
                                <div class="row g-2">
                                    <div class="col-md-6 mb-1">
                                        <label class="form-label mb-1 small fw-bold">Gelar / Kualifikasi Medis</label>
                                        <input type="text" name="credential_title" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" placeholder="Contoh: STR Perawat Aktif / D3 Keperawatan" value="<?= e($provider['credential_title'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-1">
                                        <label class="form-label mb-1 small fw-bold">Scan Dokumen STR (JPG, PNG, PDF)</label>
                                        <input type="file" name="certificate" class="form-control form-control-sm rounded-3" accept="image/jpeg,image/png,image/webp,application/pdf">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Simpan yang Selalu Jelas di Bawah Tab -->
                <div class="mt-3 pt-3 border-top">
                    <button type="submit" class="btn btn-teal w-100 py-2.5 rounded-3 fw-bold shadow-xs d-flex align-items-center justify-content-center gap-2" style="font-size: 0.88rem;">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Simpan Perubahan Profil Usaha</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- PENGATURAN NOTIFIKASI & NADA DERING ORDERAN HP -->
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 p-md-4 mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <div class="fw-bold text-dark small d-flex align-items-center gap-1.5 fs-6">
                <i class="fa-solid fa-bell text-warning fs-5"></i>
                <span>Pengaturan Suara Dering & Notifikasi HP</span>
            </div>
            <span class="badge bg-teal-subtle text-teal rounded-pill small px-2.5 py-1">Real-time Chime</span>
        </div>
        <p class="text-muted small mb-3" style="font-size: 0.75rem; line-height: 1.45;">
            Agar Anda tidak ketinggalan saat ada warga Indragiri Hulu yang memesan jasa Anda, pastikan suara notifikasi aktif dan browser diizinkan berdering.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-teal fw-bold rounded-pill px-3 sound-toggle-btn shadow-2xs" onclick="ProviderNotification.toggleSound()" style="font-size: 0.78rem;">
                <i class="fa-solid fa-volume-high me-1 text-success"></i> Suara Order: Aktif
            </button>
            <button type="button" class="btn btn-sm btn-teal text-white fw-bold rounded-pill px-3 shadow-2xs" onclick="ProviderNotification.testSound()" style="font-size: 0.78rem;">
                <i class="fa-solid fa-play me-1"></i> Uji Coba Suara Dering HP
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="ProviderNotification.requestBrowserNotification()" style="font-size: 0.78rem;">
                <i class="fa-solid fa-mobile-screen me-1 text-primary"></i> Izinkan Pop-up Notifikasi HP
            </button>
        </div>
    </div>

    <!-- 3. MENU PINTASAN AKUN & BANTUAN MITRA -->
    <div class="card border shadow-2xs rounded-4 bg-white p-3 mb-4">
        <div class="fw-bold text-dark small mb-2 d-flex align-items-center gap-1.5">
            <i class="fa-solid fa-gear text-teal"></i>
            <span>Menu & Pintasan Mitra Lainnya</span>
        </div>
        <div class="list-group list-group-flush rounded-3 border overflow-hidden">
            <a href="<?= BASE_URL ?>/provider/wallet.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-warning bg-opacity-15 text-warning-emphasis d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div>
                        <div class="fw-bold small text-dark mb-0">Dompet & Riwayat Saldo Deposit</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Cek riwayat biaya kontak pesanan & isi saldo kuota</div>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>

            <a href="<?= BASE_URL ?>/provider/portfolio.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-teal bg-opacity-10 text-teal d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                        <i class="fa-solid fa-images"></i>
                    </div>
                    <div>
                        <div class="fw-bold small text-dark mb-0">Galeri Portofolio & Foto Kerja</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Upload hasil kerja Anda untuk meyakinkan calon pelanggan</div>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>

            <a href="<?= BASE_URL ?>/switch_mode.php?to=pengguna" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold small text-dark mb-0">Beralih ke Profil Pengguna Biasa</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Gunakan akun Anda untuk memesan jasa warga lain di Inhu</div>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>

            <a href="<?= BASE_URL ?>/logout.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 text-danger">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </div>
                    <div>
                        <div class="fw-bold small text-danger mb-0">Keluar dari Akun (Logout)</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Akhiri sesi akun di perangkat ini</div>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/provider_sound.js"></script>
<script>
function previewProviderPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('imagePlaceholder');
            if (preview) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            }
            if (placeholder) {
                placeholder.classList.add('d-none');
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formProviderProfile');
    if (form) {
        form.addEventListener('submit', function(e) {
            const bName = form.querySelector('input[name="business_name"]');
            const catId = form.querySelector('select[name="category_id"]');
            const phone = form.querySelector('input[name="phone"]');

            if (!bName || !bName.value.trim()) {
                e.preventDefault();
                const tab1 = document.getElementById('tab-info-btn');
                if (tab1) tab1.click();
                if (bName) bName.focus();
                alert('Nama Usaha / Merek Layanan Jasa wajib diisi.');
                return false;
            }

            if (!catId || !catId.value) {
                e.preventDefault();
                const tab1 = document.getElementById('tab-info-btn');
                if (tab1) tab1.click();
                if (catId) catId.focus();
                alert('Kategori Utama Keahlian wajib dipilih.');
                return false;
            }

            if (!phone || !phone.value.trim()) {
                e.preventDefault();
                const tab1 = document.getElementById('tab-info-btn');
                if (tab1) tab1.click();
                if (phone) phone.focus();
                alert('Nomor WhatsApp Usaha wajib diisi.');
                return false;
            }

            const btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan Perubahan Profil...';
                btn.style.opacity = '0.85';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

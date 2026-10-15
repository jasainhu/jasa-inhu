<?php
/**
 * Profil Usaha Penyedia Jasa JASA INHU (Gaya Seller Hub / Tokopedia Seller)
 */

$page_title = 'Profil & Pengaturan Usaha';
$hide_mobile_search = true; // Sembunyikan kolom pencarian pelanggan di panel mitra

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

// Ensure required upload directories exist
foreach ([
    __DIR__ . '/../uploads/providers',
    __DIR__ . '/../uploads/certificates',
    __DIR__ . '/../uploads/ktp'
] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir, silakan coba lagi.';
    } else {
        $action = trim($_POST['action'] ?? '');
        $form_section = trim($_POST['form_section'] ?? '');

        // 1. Ubah Kata Sandi Akun
        if ($action === 'change_password') {
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';

            if (empty($old_password) || empty($new_password)) {
                $error = 'Kata sandi saat ini dan kata sandi baru wajib diisi.';
            } elseif (strlen($new_password) < 6) {
                $error = 'Kata sandi baru minimal 6 karakter.';
            } else {
                $stmtAuth = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmtAuth->execute([$user['id']]);
                $currHash = $stmtAuth->fetchColumn();

                if (!$currHash || !password_verify($old_password, $currHash)) {
                    $error = 'Kata sandi saat ini tidak sesuai.';
                } else {
                    $newHash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmtUpPass = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $stmtUpPass->execute([$newHash, $user['id']]);

                    set_flash('success', 'Kata sandi akun Anda berhasil diperbarui!');
                    redirect('/provider/profile.php');
                }
            }
        }
        // 2. Edit Informasi Jasa & Tarif
        elseif ($form_section === 'services') {
            $business_name = trim($_POST['business_name'] ?? '');
            $headline = trim($_POST['headline'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0);
            $experience = (int)($_POST['experience_years'] ?? 1);
            $rate_min = (float)($_POST['hourly_rate_min'] ?? 0);
            $rate_max = (float)($_POST['hourly_rate_max'] ?? 0);
            $phone = trim($_POST['phone'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $image_url = $provider['image_url'] ?? '';

            if (empty($business_name)) {
                $error = 'Nama Usaha / Merek Layanan Jasa wajib diisi.';
            } elseif (empty($category_id)) {
                $error = 'Kategori utama keahlian wajib dipilih.';
            } elseif (empty($phone)) {
                $error = 'Nomor WhatsApp usaha wajib diisi.';
            } else {
                // Upload foto baru jika ada (dukung base64 dari kompresor client-side & file multipart)
                $base64Image = trim($_POST['image_base64'] ?? '');
                if (!empty($base64Image) && str_starts_with($base64Image, 'data:image/')) {
                    $image_url = $base64Image;
                } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $newImageUrl = save_uploaded_media($_FILES['image'], 'providers', 'provider_' . $provider['id'], 1200, 82);
                        if (!empty($provider['image_url']) && !str_starts_with($provider['image_url'], 'data:') && file_exists(__DIR__ . '/../' . $provider['image_url'])) {
                            @unlink(__DIR__ . '/../' . $provider['image_url']);
                        }
                        $image_url = $newImageUrl;
                    } catch (Exception $e) {
                        $error = 'Gagal mengunggah foto profil usaha: ' . $e->getMessage();
                    }
                }

                if (empty($error)) {
                    try {
                        $db->beginTransaction();
                        $stmtU = $db->prepare("UPDATE users SET phone = ?, updated_at = NOW() WHERE id = ?");
                        $stmtU->execute([$phone, $user['id']]);

                        $stmtP = $db->prepare("
                            UPDATE service_providers 
                            SET business_name = ?, headline = ?, primary_category_id = ?,
                                experience_years = ?, hourly_rate_min = ?, hourly_rate_max = ?,
                                description = ?, image_url = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmtP->execute([$business_name, $headline, $category_id, $experience, $rate_min, $rate_max, $description, $image_url, $provider['id']]);

                        $db->commit();
                        set_flash('success', 'Informasi jasa & tarif berhasil diperbarui!');
                        redirect('/provider/profile.php');
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Gagal menyimpan data jasa: ' . $e->getMessage();
                    }
                }
            }
        }
        // 3. Edit Wilayah Layanan Domisili
        elseif ($form_section === 'location') {
            $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
            $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
            $address = trim($_POST['address'] ?? '');

            try {
                $db->beginTransaction();

                $stmtP = $db->prepare("
                    UPDATE service_providers 
                    SET district_id = ?, village_id = ?, address = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmtP->execute([$district_id, $village_id, $address, $provider['id']]);

                // Sinkronkan ke tabel profiles
                $stmtProf = $db->prepare("UPDATE profiles SET district_id = ?, village_id = ?, address = ?, updated_at = NOW() WHERE user_id = ?");
                $stmtProf->execute([$district_id, $village_id, $address, $user['id']]);

                if ($district_id) {
                    $stmtArea = $db->prepare("INSERT IGNORE INTO service_areas (provider_id, district_id) VALUES (?, ?)");
                    $stmtArea->execute([$provider['id'], $district_id]);
                }

                $db->commit();
                set_flash('success', 'Wilayah jangkauan layanan domisili berhasil diperbarui!');
                redirect('/provider/profile.php');
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Gagal menyimpan lokasi layanan: ' . $e->getMessage();
            }
        }
        // 4. Verifikasi KTP / Kartu Pelajar & Sertifikasi STR
        elseif ($form_section === 'ktp') {
            $id_card_type = in_array($_POST['id_card_type'] ?? '', ['ktp', 'pelajar']) ? $_POST['id_card_type'] : 'ktp';
            $id_card_number = trim($_POST['id_card_number'] ?? '');
            $school_name = trim($_POST['school_name'] ?? '');
            $credential_title = trim($_POST['credential_title'] ?? '');
            $id_card_image = $provider['id_card_image'] ?? '';
            $certificate_url = $provider['certificate_url'] ?? '';

            // Validasi persetujuan eksplisit (UU PDP No. 27/2022)
            $is_uploading_new = (isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] !== UPLOAD_ERR_NO_FILE);
            $has_existing = has_media($provider['id_card_image'] ?? '');
            if (($is_uploading_new || !$has_existing) && empty($_POST['id_card_consent'])) {
                $error = 'Anda wajib mencentang persetujuan dan pernyataan keabsahan identitas sebelum menyimpan dokumen.';
            }

            // Handle KTP / Kartu Pelajar Upload
            if (isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $newKtpUrl = save_uploaded_media($_FILES['id_card_image'], 'ktp', 'ktp_' . $provider['id'], 1600, 85, false);
                    if (!empty($provider['id_card_image']) && !str_starts_with($provider['id_card_image'], 'data:') && file_exists(__DIR__ . '/../' . $provider['id_card_image'])) {
                        @unlink(__DIR__ . '/../' . $provider['id_card_image']);
                    }
                    $id_card_image = $newKtpUrl;
                } catch (Exception $e) {
                    $error = 'Gagal mengunggah foto kartu identitas: ' . $e->getMessage();
                }
            }

            // Handle Certificate / STR Upload
            if (empty($error) && isset($_FILES['certificate']) && $_FILES['certificate']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $newCertUrl = save_uploaded_media($_FILES['certificate'], 'certificates', 'cert_' . $provider['id'], 1400, 85, true);
                    if (!empty($provider['certificate_url']) && !str_starts_with($provider['certificate_url'], 'data:') && file_exists(__DIR__ . '/../' . $provider['certificate_url'])) {
                        @unlink(__DIR__ . '/../' . $provider['certificate_url']);
                    }
                    $certificate_url = $newCertUrl;
                } catch (Exception $e) {
                    $error = 'Gagal mengunggah berkas STR: ' . $e->getMessage();
                }
            }

            if (empty($error)) {
                try {
                    $stmtP = $db->prepare("
                        UPDATE service_providers 
                        SET id_card_type = ?, id_card_number = ?, school_name = ?, id_card_image = ?, credential_title = ?, certificate_url = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmtP->execute([$id_card_type, $id_card_number, $school_name, $id_card_image, $credential_title, $certificate_url, $provider['id']]);

                    $docName = ($id_card_type === 'pelajar') ? 'Kartu Pelajar' : 'KTP';
                    set_flash('success', "Dokumen {$docName} berhasil disimpan dan sedang ditinjau pengelola!");
                    redirect('/provider/profile.php');
                } catch (Exception $e) {
                    $error = 'Gagal menyimpan dokumen verifikasi: ' . $e->getMessage();
                }
            }
        }
    }
}

// Muat data kategori & wilayah
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

// Data kuota & saldo dompet deposit
$lead_fee_amount = (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE);
$current_balance = (float)($provider['wallet_balance'] ?? 0.00);
$est_orders = $lead_fee_amount > 0 ? floor($current_balance / $lead_fee_amount) : 'Tak Terbatas';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-3 py-md-4" style="max-width: 680px;">

    <!-- Top Navigation Header -->
    <div class="d-flex align-items-center justify-content-between mb-3 px-1">
        <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-2xs">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard Order
        </a>
        <span class="badge bg-teal-subtle text-teal fw-bold rounded-pill px-3 py-1.5 border border-teal-subtle" style="font-size: 0.75rem;">
            <i class="fa-solid fa-store me-1"></i> Pusat Akun Mitra
        </span>
    </div>

    <!-- Alert Notifikasi / Pesan Error -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs small py-2.5 px-3 mb-3 mx-1" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-1.5"></i> <?= e($error) ?>
            <button type="button" class="btn-close py-2.5" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 1. KARTU IDENTITAS MITRA UTAMA (Sleek Tokopedia Seller Hero Card) -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3 text-white" style="background: linear-gradient(135deg, #0d9488 0%, #064e3b 100%);">
        <div class="card-body p-3.5 p-md-4">
            <div class="d-flex align-items-center gap-3">
                <!-- Foto Profil / Usaha -->
                <div class="position-relative flex-shrink-0">
                    <div class="rounded-circle overflow-hidden border border-2 border-white shadow-xs" style="width: 72px; height: 72px; background: #e2e8f0;">
                        <?php if (has_media($provider['image_url'] ?? '')): ?>
                            <img src="<?= e(get_media_url($provider['image_url'])) ?>" alt="Foto Usaha" class="w-100 h-100 object-fit-cover">
                        <?php else: ?>
                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-25 text-white">
                                <i class="fa-solid fa-store fs-3"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informasi Nama & Lencana Usaha -->
                <div class="flex-grow-1 min-w-0">
                    <h5 class="fw-bold text-white mb-1 text-truncate fs-6">
                        <?= e($provider['business_name'] ?: $user['name']) ?>
                    </h5>

                    <div class="d-flex align-items-center gap-1.5 flex-wrap mb-2">
                        <?php if (!empty($provider['is_verified'])): ?>
                            <?php if (($provider['id_card_type'] ?? '') === 'pelajar'): ?>
                                <span class="badge bg-purple text-white rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.65rem;">
                                    <i class="fa-solid fa-graduation-cap me-1"></i> Pelajar Terverifikasi
                                </span>
                            <?php else: ?>
                                <span class="badge bg-white text-teal rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.65rem;">
                                    <i class="fa-solid fa-circle-check text-success me-1"></i> Terverifikasi KTP
                                </span>
                            <?php endif; ?>
                        <?php elseif (!empty($provider['id_card_image'])): ?>
                            <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.65rem;">
                                <i class="fa-solid fa-clock me-1"></i> <?= (($provider['id_card_type'] ?? '') === 'pelajar') ? 'Kartu Pelajar Divalidasi' : 'KTP Sedang Divalidasi' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-black bg-opacity-30 text-white-50 border border-white border-opacity-20 rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">
                                <i class="fa-solid fa-circle-exclamation text-warning me-1"></i> Belum Verifikasi Identitas
                            </span>
                        <?php endif; ?>

                        <?php if (has_media($provider['certificate_url'] ?? '') || !empty($provider['credential_title'])): ?>
                            <span class="badge bg-white text-teal rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.65rem;">
                                <i class="fa-solid fa-user-doctor text-teal me-1"></i> <?= !empty($provider['credential_title']) ? e($provider['credential_title']) : 'STR Medis' ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $provider['id'] ?>" target="_blank" class="btn btn-xs btn-white fw-bold rounded-pill px-2.5 py-1 shadow-xs bg-white text-teal text-decoration-none" style="font-size: 0.72rem;">
                            <i class="fa-solid fa-eye me-1 text-teal"></i> Lihat Tampilan Publik ↗
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mini Stats Bar 3-Kolom -->
        <div class="bg-black bg-opacity-20 px-3 py-2.5 border-top border-white border-opacity-10">
            <div class="row g-2 text-center align-items-center">
                <div class="col-4 border-end border-white border-opacity-15" role="button" data-bs-toggle="modal" data-bs-target="#modalDompetInfo" style="cursor: pointer;" title="Ketuk untuk isi saldo / info kuota">
                    <div class="text-white-50" style="font-size: 0.68rem;">Saldo Kuota</div>
                    <div class="fw-bold text-white small d-flex align-items-center gap-1 justify-content-center">
                        <span>Rp <?= number_format($provider['wallet_balance'] ?? 0, 0, ',', '.') ?></span>
                        <span class="badge bg-warning text-dark rounded-pill fw-bold" style="font-size: 0.6rem;">+Topup</span>
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
                    <div class="text-white-50" style="font-size: 0.68rem;">Pesanan Selesai</div>
                    <div class="fw-bold text-white small">
                        <i class="fa-solid fa-circle-check text-white me-0.5"></i> <?= $total_completed ?> Order
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. PUSAT MENU TOKO MITRA (Clean Menu Hub ala Shopee / Tokopedia Seller) -->
    
    <!-- Section 1: Manajemen Toko & Layanan -->
    <div class="shopee-settings-section-title px-2">Manajemen Toko & Layanan</div>
    <div class="shopee-list-group">
        <!-- Menu 1: Info Jasa & Tarif -->
        <a href="javascript:void(0)" class="shopee-list-item" data-bs-toggle="modal" data-bs-target="#modalEditJasa">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-briefcase shopee-list-icon text-teal"></i>
                <div>
                    <span class="d-block fw-bold text-dark">Informasi Jasa & Tarif</span>
                    <span class="text-muted small" style="font-size: 0.72rem;">
                        <?= e($provider['category_name'] ?? 'Penyedia Jasa') ?> &bull; Tarif Rp <?= number_format($provider['hourly_rate_min'], 0, ',', '.') ?> - <?= number_format($provider['hourly_rate_max'], 0, ',', '.') ?>
                    </span>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
        </a>

        <!-- Menu 2: Wilayah Jangkauan Layanan -->
        <a href="javascript:void(0)" class="shopee-list-item" data-bs-toggle="modal" data-bs-target="#modalEditLokasi">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-location-dot shopee-list-icon text-danger"></i>
                <div>
                    <span class="d-block fw-bold text-dark">Wilayah Jangkauan Domisili</span>
                    <span class="text-muted small" style="font-size: 0.72rem;">
                        <?= !empty($provider['district_name']) ? 'Kec. ' . e($provider['district_name']) : 'Atur kecamatan & desa layanan' ?>
                    </span>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
        </a>

        <!-- Menu 3: Portofolio Foto Kerja -->
        <a href="<?= BASE_URL ?>/provider/portfolio.php" class="shopee-list-item">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-images shopee-list-icon text-primary"></i>
                <div>
                    <span class="d-block fw-bold text-dark">Galeri Portofolio & Foto Kerja</span>
                    <span class="text-muted small" style="font-size: 0.72rem;">Upload foto hasil pekerjaan untuk meyakinkan pelanggan</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1.5">
                <span class="badge bg-teal-subtle text-teal py-0.5 px-2 rounded-pill small" style="font-size: 0.68rem;"><?= $portfolio_count ?> Foto</span>
                <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
            </div>
        </a>

        <!-- Menu 4: Verifikasi KTP & STR -->
        <a href="javascript:void(0)" class="shopee-list-item" data-bs-toggle="modal" data-bs-target="#modalEditKtp">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-shield-halved shopee-list-icon text-success"></i>
                <div>
                    <span class="d-block fw-bold text-dark">Verifikasi KTP / Kartu Pelajar</span>
                    <span class="text-muted small" style="font-size: 0.72rem;">Lencana resmi agar toko Anda diprioritaskan</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1.5">
                <?php if (!empty($provider['is_verified'])): ?>
                    <span class="badge <?= (($provider['id_card_type'] ?? '') === 'pelajar') ? 'bg-purple-subtle text-purple border border-purple-subtle' : 'bg-success-subtle text-success' ?> py-0.5 px-2 rounded-pill" style="font-size: 0.68rem;">
                        <i class="fa-solid <?= (($provider['id_card_type'] ?? '') === 'pelajar') ? 'fa-graduation-cap' : 'fa-circle-check' ?> me-0.5"></i> <?= (($provider['id_card_type'] ?? '') === 'pelajar') ? 'Pelajar' : 'KTP' ?>
                    </span>
                <?php elseif (!empty($provider['id_card_image'])): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis py-0.5 px-2 rounded-pill" style="font-size: 0.68rem;">Menunggu</span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary py-0.5 px-2 rounded-pill" style="font-size: 0.68rem;">Belum Upload</span>
                <?php endif; ?>
                <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
            </div>
        </a>
    </div>

    <!-- Section 2: Keuangan & Pesanan -->
    <div class="shopee-settings-section-title px-2">Keuangan & Bursa Pekerjaan</div>
    <div class="shopee-list-group">
        <!-- Menu 5: Riwayat Mutasi Saldo & Pembukuan -->
        <a href="<?= BASE_URL ?>/provider/wallet.php" class="shopee-list-item">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-clock-rotate-left shopee-list-icon text-warning"></i>
                <div>
                    <span class="d-block fw-bold text-dark">Riwayat Mutasi Saldo & Pembukuan</span>
                    <span class="text-muted small" style="font-size: 0.72rem;">Catatan keluar-masuk kuota pesanan & pengajuan saldo</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1.5">
                <span class="badge bg-light text-secondary border py-0.5 px-2 rounded-pill small" style="font-size: 0.68rem;">Lihat Mutasi</span>
                <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
            </div>
        </a>

        <!-- Menu 6: Bursa Permintaan Jasa -->
        <a href="<?= BASE_URL ?>/provider/index.php#pills-leads" class="shopee-list-item">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-bullhorn shopee-list-icon text-info"></i>
                <div>
                    <span class="d-block fw-bold text-dark">Bursa Permintaan Jasa Terbuka</span>
                    <span class="text-muted small" style="font-size: 0.72rem;">Kirim penawaran harga untuk orderan umum pelanggan Inhu</span>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
        </a>
    </div>

    <!-- Section 3: Pengaturan Akun & Aplikasi -->
    <div class="shopee-settings-section-title px-2">Pengaturan Akun & Dering</div>
    <div class="shopee-list-group">
        <!-- Menu 7: Pengaturan Suara Dering -->
        <a href="javascript:void(0)" class="shopee-list-item" data-bs-toggle="modal" data-bs-target="#modalSoundSettings">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-bell shopee-list-icon text-warning"></i>
                <div>
                    <span class="d-block fw-bold text-dark">Pengaturan Suara Dering & Notifikasi HP</span>
                    <span class="text-muted small" style="font-size: 0.72rem;">Bunyi lonceng dan dering saat ada pesanan baru masuk</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1.5">
                <span class="badge bg-teal-subtle text-teal py-0.5 px-2 rounded-pill small" style="font-size: 0.68rem;">Atur Suara</span>
                <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
            </div>
        </a>

        <!-- Menu 8: Ubah Kata Sandi -->
        <a href="javascript:void(0)" class="shopee-list-item" data-bs-toggle="modal" data-bs-target="#changePassModal">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-key shopee-list-icon text-secondary"></i>
                <div>
                    <span class="d-block fw-bold text-dark">Ubah Kata Sandi Akun</span>
                    <span class="text-muted small" style="font-size: 0.72rem;">Perbarui kata sandi login untuk keamanan toko</span>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
        </a>

        <!-- Menu 9: Beralih ke Akun Pelanggan -->
        <a href="<?= BASE_URL ?>/switch_mode.php?to=pengguna" class="shopee-list-item bg-primary-subtle bg-opacity-20 text-primary">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-repeat shopee-list-icon text-primary"></i>
                <div>
                    <span class="d-block fw-bold text-primary">Beralih ke Profil Pelanggan</span>
                    <span class="text-muted small" style="font-size: 0.72rem;">Pesan jasa teknisi lain di Kab. Indragiri Hulu</span>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
        </a>
    </div>

    <!-- Section 4: Panduan Keluar & Logout -->
    <div class="p-2.5 px-3 bg-light rounded-3 border text-center mx-2 mb-3">
        <div class="small fw-bold text-dark mb-0.5" style="font-size: 0.76rem;">
            <i class="fa-solid fa-mobile-screen-button text-teal me-1"></i> Ingin Menutup Aplikasi?
        </div>
        <p class="text-muted mb-0" style="font-size: 0.71rem; line-height: 1.4;">
            Cukup tekan tombol <strong>Home / Back 2x</strong> di HP. Akun toko Anda otomatis tetap aktif dan notifikasi pesanan tetap bisa berdering.
        </p>
    </div>

    <div class="px-2 mb-4">
        <a href="<?= BASE_URL ?>/logout.php" class="shopee-btn-logout-mobile">
            <i class="fa-solid fa-arrow-right-from-bracket me-1.5"></i> Logout (Ganti Akun Lain)
        </a>
    </div>

</div>

<!-- ======================================================================
     MODAL 1: EDIT INFORMASI JASA & TARIF
     ====================================================================== -->
<div class="modal fade" id="modalEditJasa" tabindex="-1" aria-labelledby="modalEditJasaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content rounded-4 border-0 shadow" method="POST" action="<?= BASE_URL ?>/provider/profile.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="form_section" value="services">

            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-teal-subtle text-teal d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa-solid fa-briefcase"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="modalEditJasaLabel">Informasi Jasa & Tarif</h6>
                        <div class="text-muted" style="font-size: 0.72rem;">Dilihat calon pelanggan saat mencari jasa di Inhu</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
                <!-- Upload Foto Usaha / Profil -->
                <div class="p-3 mb-3 rounded-3 border bg-light">
                    <label class="form-label fw-bold text-dark mb-1 small d-flex align-items-center justify-content-between flex-wrap gap-1">
                        <span><i class="fa-solid fa-camera text-teal me-1"></i> Foto Profil / Hasil Kerja / Tempat Usaha</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fw-normal" style="font-size: 0.68rem;">
                            <i class="fa-solid fa-check me-0.5"></i> Bebas / Tanpa Toko Fisik
                        </span>
                    </label>
                    <div class="text-muted small mb-2" style="font-size: 0.72rem;">
                        Boleh foto diri Anda yang rapi, hasil kerja, atau tempat kerja Anda.
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div id="imagePreviewContainer" class="position-relative rounded-3 overflow-hidden border shadow-xs flex-shrink-0" style="width: 80px; height: 64px; background: #e2e8f0;">
                            <?php if (has_media($provider['image_url'] ?? '')): ?>
                                <img id="imagePreview" src="<?= e(get_media_url($provider['image_url'])) ?>" alt="Foto Usaha" class="w-100 h-100 object-fit-cover">
                            <?php else: ?>
                                <div id="imagePlaceholder" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                                    <i class="fa-solid fa-image fs-5 mb-0.5 opacity-50"></i>
                                </div>
                                <img id="imagePreview" src="" alt="Preview" class="w-100 h-100 object-fit-cover d-none">
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <input type="file" name="image" id="providerImageInput" class="form-control form-control-sm mb-1" accept="image/jpeg,image/png,image/webp,image/*" onchange="previewProviderPhoto(this)">
                            <input type="hidden" name="image_base64" id="providerImageBase64" value="">
                            <div id="imageCompressStatus"></div>
                            <div class="text-muted" style="font-size: 0.68rem;">Format: JPG, PNG, WebP (Otomatis dikompresi).</div>
                        </div>
                    </div>
                </div>

                <!-- Input Nama & Kontak -->
                <div class="mb-3">
                    <label class="form-label mb-1 small fw-bold text-dark">Nama Usaha / Merek Layanan Jasa <span class="text-danger">*</span></label>
                    <input type="text" name="business_name" class="form-control form-control-sm rounded-3 py-2 px-2.5" value="<?= e($provider['business_name'] ?? '') ?>" placeholder="Contoh: Bengkel AC Berkah / Servis <?= e($user['name']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label mb-1 small fw-bold text-dark">Nomor WhatsApp Usaha <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control form-control-sm rounded-3 py-2 px-2.5" value="<?= e($user['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                    <div class="text-muted" style="font-size: 0.68rem; margin-top: 2px;">Untuk menerima chat dan pesanan langsung dari pelanggan.</div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0 small fw-bold text-dark">Slogan / Headline Promosi Singkat</label>
                        <span class="text-muted" style="font-size: 0.68rem;">Maks. 500 karakter</span>
                    </div>
                    <textarea name="headline" rows="2" class="form-control form-control-sm rounded-3 py-2 px-2.5" maxlength="500" placeholder="Contoh: Joki tugas SD, SMP, SMA rapi, tepat waktu & terpercaya di Inhu"><?= e($provider['headline'] ?? '') ?></textarea>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="form-label mb-1 small fw-bold text-dark">Kategori Utama Keahlian <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select form-select-sm rounded-3 py-2 px-2.5" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (($provider['primary_category_id'] ?? 0) == $cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label mb-1 small fw-bold text-dark">Pengalaman</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="experience_years" class="form-control rounded-3 py-2 px-2" value="<?= (int)($provider['experience_years'] ?? 1) ?>" min="0">
                            <span class="input-group-text small" style="font-size: 0.72rem;">Thn</span>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label mb-1 small fw-bold text-dark">Biaya Mulai Dari (Rp)</label>
                        <input type="number" name="hourly_rate_min" class="form-control form-control-sm rounded-3 py-2 px-2.5" value="<?= (float)($provider['hourly_rate_min'] ?? 0) ?>" placeholder="Contoh: 35000">
                    </div>
                    <div class="col-6">
                        <label class="form-label mb-1 small fw-bold text-dark">Hingga Sekitar (Rp)</label>
                        <input type="number" name="hourly_rate_max" class="form-control form-control-sm rounded-3 py-2 px-2.5" value="<?= (float)($provider['hourly_rate_max'] ?? 0) ?>" placeholder="Contoh: 150000">
                    </div>
                    <div class="col-12">
                        <span class="text-muted" style="font-size: 0.68rem;">Boleh diisi 0 jika biaya disepakati setelah cek lokasi pengerjaan.</span>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label mb-1 small fw-bold text-dark">Deskripsi Layanan & Garansi</label>
                    <textarea name="description" rows="3" class="form-control form-control-sm rounded-3 py-2 px-2.5" placeholder="Jelaskan jenis pekerjaan yang bisa Anda tangani, kelebihan layanan, garansi hasil kerja, dll..."><?= e($provider['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="modal-footer border-top p-3 bg-white">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-teal fw-bold px-4">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Informasi Jasa
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================================
     MODAL 2: EDIT WILAYAH LAYANAN DOMISILI
     ====================================================================== -->
<div class="modal fade" id="modalEditLokasi" tabindex="-1" aria-labelledby="modalEditLokasiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content rounded-4 border-0 shadow" method="POST" action="<?= BASE_URL ?>/provider/profile.php">
            <?= csrf_field() ?>
            <input type="hidden" name="form_section" value="location">

            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="modalEditLokasiLabel">Wilayah Layanan Domisili</h6>
                        <div class="text-muted" style="font-size: 0.72rem;">Agar pelanggan terdekat dapat menemukan Anda</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
                <div class="mb-3">
                    <label class="form-label mb-1 small fw-bold text-dark">Kecamatan Domisili / Layanan (Inhu)</label>
                    <select name="district_id" id="district_select" class="form-select form-select-sm rounded-3 py-2 px-2.5">
                        <option value="">-- Pilih Kecamatan --</option>
                        <?php foreach ($districts as $dist): ?>
                            <option value="<?= $dist['id'] ?>" <?= (($provider['district_id'] ?? 0) == $dist['id']) ? 'selected' : '' ?>>
                                Kec. <?= e($dist['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label mb-1 small fw-bold text-dark">Desa / Kelurahan</label>
                    <select name="village_id" id="village_select" class="form-select form-select-sm rounded-3 py-2 px-2.5" <?= empty($current_villages) ? 'disabled' : '' ?>>
                        <?php if (empty($current_villages)): ?>
                            <option value="">-- Pilih Kecamatan Terlebih Dahulu --</option>
                        <?php else: ?>
                            <option value="">-- Pilih Desa / Kelurahan --</option>
                            <?php foreach ($current_villages as $vil): ?>
                                <option value="<?= $vil['id'] ?>" <?= (($provider['village_id'] ?? 0) == $vil['id']) ? 'selected' : '' ?>>
                                    <?= e($vil['name']) ?> <?= !empty($vil['postal_code']) ? '(' . e($vil['postal_code']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label mb-1 small fw-bold text-dark">Alamat Lengkap / Patokan Domisili</label>
                    <textarea name="address" rows="2" class="form-control form-control-sm rounded-3 py-2 px-2.5" placeholder="Contoh: Jl. Narasinga, Rengat (Dekat Pasar / Simpang Lima)"><?= e($provider['address'] ?? '') ?></textarea>
                    <div class="text-muted" style="font-size: 0.68rem; margin-top: 2px;">
                        Bagi pekerja mandiri tanpa toko fisik, cukup tuliskan patokan domisili tempat tinggal Anda.
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top p-3 bg-white">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-teal fw-bold px-4">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Lokasi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================================
     MODAL 3: VERIFIKASI KTP & DOKUMEN STR
     ====================================================================== -->
<div class="modal fade" id="modalEditKtp" tabindex="-1" aria-labelledby="modalEditKtpLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content rounded-4 border-0 shadow" method="POST" action="<?= BASE_URL ?>/provider/profile.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="form_section" value="ktp">

            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="modalEditKtpLabel">Verifikasi KTP & Berkas Resmi</h6>
                        <div class="text-muted" style="font-size: 0.72rem;">Mendapatkan lencana centang verifikasi toko</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
                <!-- Status Badge -->
                <div class="p-3 rounded-3 mb-3 border <?php if (!empty($provider['is_verified'])): ?>bg-success bg-opacity-10 border-success<?php elseif (!empty($provider['id_card_image'])): ?>bg-warning bg-opacity-10 border-warning<?php else: ?>bg-light border-secondary-subtle<?php endif; ?>">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                        <span class="small fw-bold text-dark">Status Verifikasi Identitas:</span>
                        <?php if (!empty($provider['is_verified'])): ?>
                            <?php if (($provider['id_card_type'] ?? 'ktp') === 'pelajar'): ?>
                                <span class="badge bg-purple text-white px-2.5 py-1 rounded-pill fw-semibold shadow-xs" style="font-size: 0.72rem; background-color: #7c3aed;">
                                    <i class="fa-solid fa-graduation-cap me-1"></i> Terverifikasi Pelajar Mandiri
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success text-white px-2.5 py-1 rounded-pill fw-semibold shadow-xs" style="font-size: 0.72rem;">
                                    <i class="fa-solid fa-circle-check me-1"></i> Terverifikasi KTP Resmi
                                </span>
                            <?php endif; ?>
                        <?php elseif (!empty($provider['id_card_image'])): ?>
                            <span class="badge bg-warning text-dark px-2.5 py-1 rounded-pill fw-semibold" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-clock me-1"></i> Berkas Sedang Divalidasi Admin
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border px-2.5 py-1 rounded-pill fw-semibold" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-circle-exclamation me-1"></i> Belum Mengunggah Identitas
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small" style="font-size: 0.72rem; line-height: 1.4;">
                        Toko/jasa yang terverifikasi resmi diprioritaskan di hasil pencarian dan lebih dipercaya pelanggan se-Kabupaten Indragiri Hulu.
                    </div>
                </div>

                <!-- Pilihan Jenis Identitas (KTP vs Pelajar) -->
                <div class="mb-3">
                    <label class="form-label mb-1.5 small fw-bold text-dark">Jenis Kartu Identitas</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="card p-2 border rounded-3 cursor-pointer h-100 d-flex flex-row align-items-center gap-2 <?= (($provider['id_card_type'] ?? 'ktp') !== 'pelajar') ? 'border-primary bg-primary bg-opacity-10' : 'bg-light' ?>" id="labelTypeKtp">
                                <input type="radio" name="id_card_type" value="ktp" class="form-check-input mt-0" <?= (($provider['id_card_type'] ?? 'ktp') !== 'pelajar') ? 'checked' : '' ?> onchange="toggleIdCardType(this.value)">
                                <div>
                                    <div class="fw-bold text-dark small" style="font-size: 0.78rem;"><i class="fa-solid fa-id-card text-primary me-1"></i> KTP Umum</div>
                                    <div class="text-muted" style="font-size: 0.68rem;">Warga usia 17+ thn</div>
                                </div>
                            </label>
                        </div>
                        <div class="col-6">
                            <label class="card p-2 border rounded-3 cursor-pointer h-100 d-flex flex-row align-items-center gap-2 <?= (($provider['id_card_type'] ?? '') === 'pelajar') ? 'border-purple bg-purple-subtle' : 'bg-light' ?>" id="labelTypePelajar">
                                <input type="radio" name="id_card_type" value="pelajar" class="form-check-input mt-0" <?= (($provider['id_card_type'] ?? '') === 'pelajar') ? 'checked' : '' ?> onchange="toggleIdCardType(this.value)">
                                <div>
                                    <div class="fw-bold text-dark small" style="font-size: 0.78rem;"><i class="fa-solid fa-graduation-cap text-purple me-1"></i> Kartu Pelajar</div>
                                    <div class="text-muted" style="font-size: 0.68rem;">Siswa SMK/SMA/SMP</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Input Nomor Identitas -->
                <div class="mb-3">
                    <label class="form-label mb-1 small fw-bold text-dark" id="labelCardNumber">
                        <?= (($provider['id_card_type'] ?? '') === 'pelajar') ? 'Nomor Kartu Pelajar / NISN' : 'Nomor Induk Kependudukan (NIK KTP)' ?>
                    </label>
                    <input type="text" name="id_card_number" id="inputCardNumber" class="form-control form-control-sm rounded-3 py-2 px-2.5" maxlength="30" placeholder="<?= (($provider['id_card_type'] ?? '') === 'pelajar') ? 'Contoh: 0072381923 (NISN) atau No. Pelajar' : '16 digit NIK KTP Anda' ?>" value="<?= e($provider['id_card_number'] ?? '') ?>">
                </div>

                <!-- Input Nama Sekolah (Khusus Pelajar) -->
                <div class="mb-3 <?= (($provider['id_card_type'] ?? '') === 'pelajar') ? '' : 'd-none' ?>" id="boxSchoolName">
                    <label class="form-label mb-1 small fw-bold text-dark">Nama Asal Sekolah / Madrasah / Kampus</label>
                    <input type="text" name="school_name" class="form-control form-control-sm rounded-3 py-2 px-2.5" placeholder="Contoh: SMKN 1 Pasir Penyu / SMAN 1 Rengat" value="<?= e($provider['school_name'] ?? '') ?>">
                    <div class="text-muted" style="font-size: 0.68rem; margin-top: 3px;">
                        Nama sekolah akan ditampilkan di profil agar pelanggan percaya dan bangga memesan jasa pelajar mandiri.
                    </div>
                </div>

                <!-- Upload Kartu Identitas -->
                <div class="mb-3">
                    <label class="form-label mb-1 small fw-bold text-dark" id="labelCardUpload">
                        <?= (($provider['id_card_type'] ?? '') === 'pelajar') ? 'Unggah Foto Kartu Pelajar (Depan)' : 'Unggah Foto KTP Asli' ?>
                    </label>
                    <input type="file" name="id_card_image" class="form-control form-control-sm rounded-3" accept="image/jpeg,image/png,image/webp,image/*">
                    <?php if (has_media($provider['id_card_image'] ?? '')): ?>
                        <div class="mt-2 d-flex align-items-center justify-content-between p-2 rounded-2 bg-light border" style="font-size: 0.72rem;">
                            <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i> Foto kartu identitas tersimpan aman</span>
                            <a href="<?= BASE_URL ?>/view_doc.php?user_id=<?= $user['id'] ?>&type=ktp" target="_blank" class="fw-semibold text-teal text-decoration-none">
                                <i class="fa-solid fa-eye me-0.5"></i> Lihat Dokumen ↗
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-2.5 rounded-3 border bg-light small text-muted d-flex align-items-start gap-2 mb-3" style="font-size: 0.72rem;">
                    <i class="fa-solid fa-lock text-success mt-0.5 fs-6 flex-shrink-0"></i>
                    <div>
                        <strong>Privasi Terjamin (UU PDP No. 27/2022):</strong> Foto identitas hanya digunakan untuk validasi internal pengelola <?= APP_NAME ?> dan <strong>tidak akan pernah disebarkan ke publik</strong>.
                    </div>
                </div>

                <!-- Checkbox Pernyataan & Persetujuan Sukarela -->
                <div class="p-3 rounded-3 border bg-light-subtle mb-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                    <div class="form-check d-flex align-items-start gap-2 mb-0">
                        <input class="form-check-input mt-1 flex-shrink-0" type="checkbox" name="id_card_consent" value="1" id="idCardConsent" <?= (has_media($provider['id_card_image'] ?? '') || !empty($provider['is_verified'])) ? 'checked' : '' ?> required>
                        <label class="form-check-label text-dark" for="idCardConsent" style="font-size: 0.75rem; line-height: 1.45;">
                            <span class="fw-bold d-block text-dark mb-0.5">
                                <i class="fa-solid fa-file-signature text-teal me-1"></i> Pernyataan Persetujuan & Keaslian Identitas
                            </span>
                            <span class="text-secondary">
                                Saya menyatakan bahwa data dan dokumen identitas yang saya unggah adalah <strong>asli dan sah milik saya sendiri</strong>. Saya mengunggahnya secara <strong>sadar, sukarela, dan tanpa paksaan</strong> khusus untuk keperluan verifikasi resmi mitra di <strong><?= APP_NAME ?></strong>. Saya memahami bahwa data ini dilindungi kerahasiaannya sesuai ketentuan UU Pelindungan Data Pribadi.
                            </span>
                        </label>
                    </div>
                    <?php if (has_media($provider['id_card_image'] ?? '') || !empty($provider['is_verified'])): ?>
                        <div class="mt-2 pt-2 border-top d-flex align-items-center gap-1.5 text-success small" style="font-size: 0.72rem;">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Persetujuan terverifikasi sah dan tercatat di sistem JASA INHU.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Khusus Tenaga Medis (STR) -->
                <div class="border rounded-3 p-2.5 bg-light">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <span class="fw-bold text-dark small" style="font-size: 0.76rem;">
                            <i class="fa-solid fa-user-doctor text-teal me-1"></i> Khusus Tenaga Medis / STR (Opsional)
                        </span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1 small fw-bold">Gelar / Kualifikasi</label>
                        <input type="text" name="credential_title" class="form-control form-control-sm rounded-3 py-1.5 px-2" placeholder="Contoh: STR Perawat Aktif / Bidan" value="<?= e($provider['credential_title'] ?? '') ?>">
                    </div>
                    <div class="mb-1">
                        <label class="form-label mb-1 small fw-bold">Berkas Scan Dokumen STR (JPG, PNG, PDF)</label>
                        <input type="file" name="certificate" class="form-control form-control-sm rounded-3" accept="image/jpeg,image/png,image/webp,application/pdf">
                        <?php if (has_media($provider['certificate_url'] ?? '')): ?>
                            <div class="mt-1 small">
                                <a href="<?= BASE_URL ?>/view_doc.php?user_id=<?= $user['id'] ?>&type=str" target="_blank" class="fw-semibold text-teal text-decoration-none" style="font-size: 0.72rem;">
                                    <i class="fa-solid fa-file-shield me-1"></i> Lihat Berkas STR Tersimpan ↗
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top p-3 bg-white">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-teal fw-bold px-4">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Dokumen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================================
     MODAL 4: PENGATURAN SUARA DERING & NOTIFIKASI
     ====================================================================== -->
<div class="modal fade" id="modalSoundSettings" tabindex="-1" aria-labelledby="modalSoundSettingsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="modalSoundSettingsLabel">Suara Dering & Notifikasi HP</h6>
                        <div class="text-muted" style="font-size: 0.72rem;">Pemberitahuan saat ada pesanan jasa masuk</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-4 text-center">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                    <i class="fa-solid fa-volume-high text-teal fs-3"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">Dering Pesanan Baru Masuk</h6>
                <p class="text-muted small mb-4 mx-auto" style="max-width: 360px; font-size: 0.78rem;">
                    Pastikan suara notifikasi diizinkan di browser agar Anda tidak melewatkan pesanan masuk saat aplikasi sedang dibuka.
                </p>

                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-teal text-white fw-bold py-2.5 rounded-3 shadow-xs sound-toggle-btn" onclick="ProviderNotification.toggleSound()">
                        <i class="fa-solid fa-volume-high me-1"></i> Status Suara: Aktif
                    </button>
                    <button type="button" class="btn btn-outline-teal fw-bold py-2.5 rounded-3 shadow-xs" onclick="ProviderNotification.testSound()">
                        <i class="fa-solid fa-play me-1"></i> Uji Coba Suara Dering
                    </button>
                    <button type="button" class="btn btn-outline-secondary py-2 rounded-3 small" onclick="ProviderNotification.requestBrowserNotification()">
                        <i class="fa-solid fa-mobile-screen me-1 text-primary"></i> Izinkan Notifikasi Pop-up HP
                    </button>
                </div>
            </div>

            <div class="modal-footer border-top p-3 bg-white justify-content-center">
                <button type="button" class="btn btn-sm btn-light border px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================
     MODAL 5: UBAH KATA SANDI AKUN
     ====================================================================== -->
<div class="modal fade" id="changePassModal" tabindex="-1" aria-labelledby="changePassModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content rounded-4 border-0 shadow" method="POST" action="<?= BASE_URL ?>/provider/profile.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="changePassModalLabel">Ubah Kata Sandi</h6>
                        <div class="text-muted" style="font-size: 0.72rem;">Amankan akun mitra Anda secara berkala</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Kata Sandi Saat Ini <span class="text-danger">*</span></label>
                    <input type="password" name="old_password" class="form-control form-control-sm rounded-3 py-2 px-2.5" required placeholder="Masukkan kata sandi lama Anda">
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark">Kata Sandi Baru <span class="text-danger">*</span></label>
                    <input type="password" name="new_password" class="form-control form-control-sm rounded-3 py-2 px-2.5" minlength="6" required placeholder="Minimal 6 karakter">
                </div>
            </div>

            <div class="modal-footer border-top p-3 bg-white">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-teal fw-bold px-4">
                    <i class="fa-solid fa-check me-1"></i> Simpan Sandi Baru
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================================
     MODAL 6: POP-UP INFO & STATUS DOMPET DEPOSIT MITRA
     ====================================================================== -->
<div class="modal fade" id="modalDompetInfo" tabindex="-1" aria-labelledby="modalDompetInfoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 460px;">
        <div class="modal-content rounded-4 border-0 shadow overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa-solid fa-wallet fs-6"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="modalDompetInfoLabel">Dompet & Saldo Deposit</h6>
                        <div class="text-muted" style="font-size: 0.72rem;">Status kuota pesanan masuk & biaya kontak</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
                <!-- Mini Hero Card Saldo Ramping -->
                <div class="card border-0 rounded-4 overflow-hidden text-white p-3 mb-3 shadow-xs" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                    <!-- Badge Promo dengan Kontras Jelas -->
                    <div class="d-inline-flex align-items-center gap-1.5 px-2.5 py-0.5 rounded-pill mb-2 fw-semibold shadow-xs" style="background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(253, 224, 71, 0.4); font-size: 0.68rem; color: #fde047;">
                        <i class="fa-solid fa-gift text-warning"></i>
                        <span>PROMO: 15 PESANAN PERTAMA GRATIS</span>
                    </div>

                    <div class="text-white-50 text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.3px;">Saldo Kuota Deposit:</div>
                    <div class="fs-3 fw-bold text-white my-0.5">
                        Rp <?= number_format($current_balance, 0, ',', '.') ?>
                    </div>
                    <div class="d-inline-flex align-items-center gap-1 px-2 py-0.5 rounded-pill mb-2" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.15); font-size: 0.65rem; color: rgba(255,255,255,0.85);">
                        <i class="fa-solid fa-lock text-warning"></i>
                        <span>Kredit Kuota Aplikasi &bull; Non-Tunai</span>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-1.5 pt-2 border-top border-white border-opacity-15" style="font-size: 0.70rem;">
                        <span class="badge bg-black bg-opacity-25 text-white fw-normal px-2 py-0.5 rounded-pill">
                            <i class="fa-solid fa-bolt text-warning me-0.5"></i> Kapasitas: <strong>~<?= $est_orders ?> Pesanan</strong>
                        </span>
                        <span class="badge bg-black bg-opacity-25 text-white fw-normal px-2 py-0.5 rounded-pill">
                            <i class="fa-solid fa-tag text-info me-0.5"></i> Biaya Kontak: <strong><?= $lead_fee_amount > 0 ? 'Rp ' . number_format($lead_fee_amount, 0, ',', '.') : 'GRATIS' ?></strong>
                        </span>
                    </div>
                </div>

                <!-- Keunggulan Sistem Deposit Singkat -->
                <div class="p-2.5 bg-light rounded-3 border mb-3 small" style="font-size: 0.73rem; line-height: 1.45;">
                    <div class="fw-bold text-dark mb-1 d-flex align-items-center gap-1">
                        <i class="fa-solid fa-shield-halved text-teal"></i> Keuntungan Sistem Kuota Mitra:
                    </div>
                    <ul class="mb-0 ps-3 text-muted">
                        <li><strong>Biaya flat ringan:</strong> <?= $lead_fee_amount > 0 ? 'Hanya Rp ' . number_format($lead_fee_amount, 0, ',', '.') . ' per kontak order.' : 'Gratis Rp 0 selama masa promo!' ?></li>
                        <li><strong>Hasil kerja 100% hak Anda:</strong> Tanpa potongan komisi ataupun persenan omzet.</li>
                        <li><strong>Prioritas order:</strong> Saldo aktif diprioritaskan tampil teratas di Inhu.</li>
                    </ul>
                </div>

                <!-- Tombol Aksi Cepat -->
                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>/provider/wallet.php" class="btn btn-warning fw-bold py-2 rounded-3 shadow-xs d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.84rem;">
                        <i class="fa-solid fa-circle-plus fs-6"></i> Isi Saldo (Top-Up) Sekarang
                    </a>
                    <a href="<?= BASE_URL ?>/provider/wallet.php" class="btn btn-outline-teal fw-semibold py-2 rounded-3 small d-flex align-items-center justify-content-center gap-1.5">
                        <i class="fa-solid fa-clock-rotate-left"></i> Buka Halaman Riwayat Mutasi Lengkap &rsaquo;
                    </a>
                </div>
            </div>

            <div class="modal-footer border-top p-2.5 px-3 bg-light justify-content-between">
                <span class="text-muted small" style="font-size: 0.70rem;">Saldo aktif: Rp <?= number_format($current_balance, 0, ',', '.') ?></span>
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleIdCardType(type) {
    const isPelajar = (type === 'pelajar');
    const labelCardNumber = document.getElementById('labelCardNumber');
    const inputCardNumber = document.getElementById('inputCardNumber');
    const boxSchoolName = document.getElementById('boxSchoolName');
    const labelCardUpload = document.getElementById('labelCardUpload');
    const labelTypeKtp = document.getElementById('labelTypeKtp');
    const labelTypePelajar = document.getElementById('labelTypePelajar');

    if (isPelajar) {
        if (labelCardNumber) labelCardNumber.textContent = 'Nomor Kartu Pelajar / NISN';
        if (inputCardNumber) inputCardNumber.placeholder = 'Contoh: 0072381923 (NISN) atau No. Pelajar';
        if (boxSchoolName) boxSchoolName.classList.remove('d-none');
        if (labelCardUpload) labelCardUpload.textContent = 'Unggah Foto Kartu Pelajar (Depan)';
        if (labelTypePelajar) {
            labelTypePelajar.classList.add('border-purple', 'bg-purple-subtle');
            labelTypePelajar.classList.remove('bg-light');
        }
        if (labelTypeKtp) {
            labelTypeKtp.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
            labelTypeKtp.classList.add('bg-light');
        }
    } else {
        if (labelCardNumber) labelCardNumber.textContent = 'Nomor Induk Kependudukan (NIK KTP)';
        if (inputCardNumber) inputCardNumber.placeholder = '16 digit NIK KTP Anda';
        if (boxSchoolName) boxSchoolName.classList.add('d-none');
        if (labelCardUpload) labelCardUpload.textContent = 'Unggah Foto KTP Asli';
        if (labelTypeKtp) {
            labelTypeKtp.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
            labelTypeKtp.classList.remove('bg-light');
        }
        if (labelTypePelajar) {
            labelTypePelajar.classList.remove('border-purple', 'bg-purple-subtle');
            labelTypePelajar.classList.add('bg-light');
        }
    }
}

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

document.addEventListener('DOMContentLoaded', () => {
    // Dynamic District -> Village selector
    const districtSelect = document.getElementById('district_select');
    const villageSelect = document.getElementById('village_select');

    if (districtSelect && villageSelect) {
        districtSelect.addEventListener('change', async function() {
            const districtId = this.value;
            villageSelect.innerHTML = '<option value="">Memuat desa / kelurahan...</option>';
            villageSelect.disabled = true;

            if (!districtId) {
                villageSelect.innerHTML = '<option value="">-- Pilih Kecamatan Terlebih Dahulu --</option>';
                villageSelect.disabled = true;
                return;
            }

            try {
                const response = await fetch(`${window.APP_BASE_URL || ''}/api/villages.php?district_id=${districtId}`);
                const result = await response.json();

                if (result.status === 'success' && Array.isArray(result.data)) {
                    if (result.data.length === 0) {
                        villageSelect.innerHTML = '<option value="">-- Tidak ada data desa --</option>';
                    } else {
                        let options = '<option value="">-- Pilih Desa / Kelurahan --</option>';
                        result.data.forEach(village => {
                            options += `<option value="${village.id}">${village.name} ${village.postal_code ? `(${village.postal_code})` : ''}</option>`;
                        });
                        villageSelect.innerHTML = options;
                        villageSelect.disabled = false;
                    }
                } else {
                    villageSelect.innerHTML = '<option value="">-- Gagal memuat data --</option>';
                }
            } catch (err) {
                console.error('Error fetching villages:', err);
                villageSelect.innerHTML = '<option value="">-- Gagal menghubungi server --</option>';
            }
        });
    }

    // Client-side image compressor
    const provPhotoInput = document.getElementById('providerImageInput');
    if (provPhotoInput && window.attachAutoCompressor) {
        window.attachAutoCompressor(provPhotoInput, {
            previewEl: 'imagePreview',
            statusEl: 'imageCompressStatus',
            maxWidth: 1400,
            maxHeight: 1400,
            quality: 0.82
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

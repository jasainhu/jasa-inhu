<?php
/**
 * Profil & Menu Akun Pengguna: JASA INHU (Gaya Shopee Hub Desktop & Mobile)
 */

$page_title = 'Akun Saya';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$user = current_user();
$db = get_db();
$error = '';
$flashData = get_flash();
$success = is_array($flashData) ? (($flashData['type'] ?? '') === 'success' ? ($flashData['message'] ?? '') : '') : (is_string($flashData) ? $flashData : '');
if (is_array($flashData) && ($flashData['type'] ?? '') === 'danger') {
    $error = $flashData['message'] ?? '';
}
$active_tab = $_GET['tab'] ?? 'profile';

// Pastikan mode profil aktif tersimpan sebagai pengguna biasa saat mengakses halaman ini
if (($user['role_name'] ?? '') === 'penyedia') {
    $_SESSION['active_profile_mode'] = 'pengguna';
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
        if (!empty($_POST['action']) && $_POST['action'] === 'upload_avatar_ajax') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    } else {
        $action = $_POST['action'] ?? 'update_profile';

        // Direct AJAX Avatar Upload (Sekali Klik Kamera / Foto di HP)
        if ($action === 'upload_avatar_ajax') {
            header('Content-Type: application/json');
            if (empty($_FILES['avatar']['name']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Berkas foto tidak ditemukan atau gagal diunggah.']);
                exit;
            }

            try {
                $avatarVal = save_avatar_upload($_FILES['avatar'], (int)$user['id']);

                // Simpan ke database
                $stmtCheck = $db->prepare("SELECT id FROM profiles WHERE user_id = ?");
                $stmtCheck->execute([$user['id']]);
                if ($stmtCheck->fetch()) {
                    $stmtUp = $db->prepare("UPDATE profiles SET avatar = ?, updated_at = NOW() WHERE user_id = ?");
                    $stmtUp->execute([$avatarVal, $user['id']]);
                } else {
                    $stmtIn = $db->prepare("INSERT INTO profiles (user_id, avatar, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                    $stmtIn->execute([$user['id'], $avatarVal]);
                }

                $finalUrl = get_avatar_url($avatarVal);
                echo json_encode([
                    'success' => true,
                    'message' => 'Foto profil berhasil diperbarui!',
                    'avatar_url' => $finalUrl
                ]);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }

        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $gender = !empty($_POST['gender']) && in_array($_POST['gender'], ['male', 'female', 'other']) ? $_POST['gender'] : null;
            $birth_date = !empty($_POST['birth_date']) ? trim($_POST['birth_date']) : null;
            if ($birth_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
                $birth_date = null;
            }
            $bio = trim($_POST['bio'] ?? '');

            if (empty($name) || empty($phone)) {
                $error = 'Nama lengkap dan nomor telepon/WhatsApp wajib diisi.';
            } else {
                try {
                    $db->beginTransaction();

                    // Handle upload foto avatar jika ada
                    $avatar_filename = $user['avatar'] ?? null;
                    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $avatar_filename = save_avatar_upload($_FILES['avatar'], (int)$user['id']);
                    }

                    // Update data pengguna di tabel users
                    $stmtU = $db->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                    $stmtU->execute([$name, $phone, $user['id']]);

                    // Update profil pengguna di tabel profiles (pertahankan address & district_id yang sudah ada)
                    $stmtCheckP = $db->prepare("SELECT id FROM profiles WHERE user_id = ?");
                    $stmtCheckP->execute([$user['id']]);
                    if ($stmtCheckP->fetch()) {
                        $stmtP = $db->prepare("
                            UPDATE profiles 
                            SET bio = ?, avatar = ?, gender = ?, birth_date = ?, updated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $stmtP->execute([$bio, $avatar_filename, $gender, $birth_date, $user['id']]);
                    } else {
                        $stmtP = $db->prepare("
                            INSERT INTO profiles (user_id, bio, avatar, gender, birth_date, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        $stmtP->execute([$user['id'], $bio, $avatar_filename, $gender, $birth_date]);
                    }

                    $db->commit();
                    set_flash('success', 'Profil Anda berhasil diperbarui!');
                    redirect('/user/profile.php?tab=profile');
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Gagal menyimpan profil: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'update_address') {
            $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
            $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
            $address = trim($_POST['address'] ?? '');

            if (empty($district_id)) {
                $error = 'Silakan pilih kecamatan domisili Anda di Kabupaten Indragiri Hulu.';
            } else {
                try {
                    $stmtCheckP = $db->prepare("SELECT id FROM profiles WHERE user_id = ?");
                    $stmtCheckP->execute([$user['id']]);
                    if ($stmtCheckP->fetch()) {
                        $stmtP = $db->prepare("
                            UPDATE profiles 
                            SET district_id = ?, village_id = ?, address = ?, updated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $stmtP->execute([$district_id, $village_id, $address, $user['id']]);
                    } else {
                        $stmtP = $db->prepare("
                            INSERT INTO profiles (user_id, district_id, village_id, address, created_at, updated_at)
                            VALUES (?, ?, ?, ?, NOW(), NOW())
                        ");
                        $stmtP->execute([$user['id'], $district_id, $village_id, $address]);
                    }

                    set_flash('success', 'Alamat domisili Anda berhasil diperbarui!');
                    redirect('/user/profile.php?tab=address');
                } catch (Exception $e) {
                    $error = 'Gagal menyimpan alamat: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'change_password') {
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($old_password) || empty($new_password)) {
                $error = 'Kata sandi lama dan baru wajib diisi.';
            } elseif (strlen($new_password) < 6) {
                $error = 'Kata sandi baru minimal 6 karakter.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Konfirmasi kata sandi baru tidak cocok.';
            } else {
                // Ambil password hash saat ini
                $stmtPass = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmtPass->execute([$user['id']]);
                $currHash = $stmtPass->fetchColumn();

                if (!password_verify($old_password, $currHash)) {
                    $error = 'Kata sandi lama yang Anda masukkan salah.';
                } else {
                    $newHash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmtUpPass = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    $stmtUpPass->execute([$newHash, $user['id']]);

                    set_flash('success', 'Kata sandi akun Anda berhasil diperbarui!');
                    redirect('/user/profile.php?tab=password');
                }
            }
        } elseif ($action === 'upgrade_to_provider') {
            $business_name = trim($_POST['business_name'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : (!empty($user['district_id']) ? (int)$user['district_id'] : null);
            $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : (!empty($user['village_id']) ? (int)$user['village_id'] : null);
            $address = trim($_POST['address'] ?? ($user['address'] ?? ''));

            if (empty($business_name)) {
                $error = 'Nama usaha atau merek jasa mandiri Anda wajib diisi.';
            } elseif ($category_id <= 0) {
                $error = 'Silakan pilih bidang kategori keahlian jasa Anda.';
            } else {
                try {
                    $db->beginTransaction();

                    // 1. Ambil role_id untuk 'penyedia'
                    $stmtRole = $db->prepare("SELECT id FROM roles WHERE name = 'penyedia' LIMIT 1");
                    $stmtRole->execute();
                    $penyedia_role_id = (int)$stmtRole->fetchColumn();

                    if (!$penyedia_role_id) {
                        throw new Exception("Role penyedia tidak ditemukan di sistem.");
                    }

                    // 2. Update role_id user menjadi penyedia
                    $stmtUpUser = $db->prepare("UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?");
                    $stmtUpUser->execute([$penyedia_role_id, $user['id']]);

                    // 3. Update profile jika ada data alamat/kecamatan baru
                    if ($district_id) {
                        $stmtUpProf = $db->prepare("UPDATE profiles SET district_id = ?, village_id = ?, address = ?, updated_at = NOW() WHERE user_id = ?");
                        $stmtUpProf->execute([$district_id, $village_id, $address, $user['id']]);
                    }

                    // 4. Periksa apakah sudah ada record di service_providers
                    $stmtCheckProv = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
                    $stmtCheckProv->execute([$user['id']]);
                    $existingProvId = $stmtCheckProv->fetchColumn();

                    $starter_bonus = (float)get_setting('welcome_bonus_amount', defined('WELCOME_BONUS_WALLET') ? (float)WELCOME_BONUS_WALLET : 45000.00);
                    $default_desc = !empty($description) ? $description : ("Penyedia jasa " . $business_name . " di wilayah Indragiri Hulu.");

                    if ($existingProvId) {
                        $provider_id = (int)$existingProvId;
                        $stmtUpdProv = $db->prepare("
                            UPDATE service_providers 
                            SET primary_category_id = ?, business_name = ?, description = ?, district_id = ?, village_id = ?, address = ?
                            WHERE id = ?
                        ");
                        $stmtUpdProv->execute([$category_id, $business_name, $default_desc, $district_id, $village_id, $address, $provider_id]);
                    } else {
                        $stmtInsProv = $db->prepare("
                            INSERT INTO service_providers 
                            (user_id, primary_category_id, business_name, description, address, district_id, village_id, is_verified, wallet_balance, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())
                        ");
                        $stmtInsProv->execute([$user['id'], $category_id, $business_name, $default_desc, $address, $district_id, $village_id, $starter_bonus]);
                        $provider_id = (int)$db->lastInsertId();

                        // Catat mutasi bonus sambutan jika saldo > 0
                        if ($starter_bonus > 0) {
                            $cur_fee = (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE);
                            $free_quota_txt = ($cur_fee > 0) ? floor($starter_bonus / $cur_fee) . " Pesanan Pertama Gratis" : "Saldo Awal Kuota";
                            $stmtBonus = $db->prepare("
                                INSERT INTO provider_wallet_transactions 
                                (provider_id, type, amount, balance_after, description, created_at)
                                VALUES (?, 'bonus', ?, ?, ?, NOW())
                            ");
                            $stmtBonus->execute([$provider_id, $starter_bonus, $starter_bonus, "Bonus Sambutan Mitra Baru JASA INHU (" . $free_quota_txt . ")"]);
                        }
                    }

                    // 5. Tambahkan service_areas
                    if ($district_id) {
                        $stmtArea = $db->prepare("INSERT IGNORE INTO service_areas (provider_id, district_id) VALUES (?, ?)");
                        $stmtArea->execute([$provider_id, $district_id]);
                    }

                    // 6. Notifikasi selamat datang sebagai mitra
                    $stmtNotif = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, link, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmtNotif->execute([
                        $user['id'],
                        "Selamat Bergabung Menjadi Mitra!",
                        "Akun Anda kini telah aktif sebagai Mitra Penyedia Jasa di JASA INHU. Silakan kelola tarif dan terima pesanan pekerjaan dari warga.",
                        "/provider/index.php"
                    ]);

                    $db->commit();

                    // Update session pengguna yang sedang login
                    $_SESSION['user_role'] = 'penyedia';
                    $_SESSION['role_name'] = 'penyedia';
                    $_SESSION['role_id'] = $penyedia_role_id;

                    set_flash('success', '🎉 Selamat! Akun Anda berhasil di-upgrade menjadi Mitra Penyedia Jasa JASA INHU. Bonus saldo pesanan gratis telah ditambahkan!');
                    redirect('/provider/index.php');
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Gagal meng-upgrade akun: ' . $e->getMessage();
                }
            }
        }
    }
}

// Refresh data user
$user = current_user();

// Hitung data ringkasan pesanan & aktivitas secara real-time
$stmtStats = $db->prepare("
    SELECT 
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as count_open,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as count_in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as count_completed,
        COUNT(*) as total_orders
    FROM service_requests
    WHERE user_id = ?
");
$stmtStats->execute([$user['id']]);
$orderStats = $stmtStats->fetch() ?: [];

$count_open = (int)($orderStats['count_open'] ?? 0);
$count_in_progress = (int)($orderStats['count_in_progress'] ?? 0);
$count_completed = (int)($orderStats['count_completed'] ?? 0);
$total_orders = (int)($orderStats['total_orders'] ?? 0);

// Hitung pesanan selesai yang butuh review/ulasan
$stmtUnreviewed = $db->prepare("
    SELECT COUNT(*) 
    FROM service_requests sr
    WHERE sr.user_id = ? AND sr.status = 'completed'
      AND sr.id NOT IN (SELECT request_id FROM reviews WHERE user_id = ?)
");
$stmtUnreviewed->execute([$user['id'], $user['id']]);
$count_review_needed = (int)$stmtUnreviewed->fetchColumn();

// Hitung pesan unread
$stmtChats = $db->prepare("
    SELECT COUNT(*) FROM chat_messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmtChats->execute([$user['id']]);
$unread_chats = (int)$stmtChats->fetchColumn();

// Ambil daftar kecamatan dan kategori aktif di Inhu
$districts = get_all_districts();
$categories = get_active_categories();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- ==============================================================
     TAMPILAN MOBILE (KHUSUS SMARTPHONE / HANDPHONE - GAYA SHOPEE)
     ============================================================== -->
<div class="shopee-mobile-hub d-lg-none">
    <!-- 1. Profile Hero Banner Atas -->
    <div class="shopee-profile-hero">
        <!-- Action bar atas (Ikon Pengaturan Roda Gigi & Obrolan) -->
        <div class="shopee-hero-actions">
            <button type="button" class="shopee-hero-icon-btn" data-bs-toggle="offcanvas" data-bs-target="#settingsOffcanvas" title="Pengaturan Akun">
                <i class="fa-solid fa-gear"></i>
            </button>
            <a href="<?= BASE_URL ?>/chat.php" class="shopee-hero-icon-btn" title="Pesan Obrolan">
                <i class="fa-solid fa-comments"></i>
                <?php if ($unread_chats > 0): ?>
                    <span class="shopee-hero-badge"><?= $unread_chats ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Baris Identitas Pengguna -->
        <div class="shopee-profile-user-row">
            <div class="shopee-avatar-wrapper position-relative" onclick="document.getElementById('directAvatarInput').click()" style="cursor: pointer;" title="Ketuk untuk ganti foto profil">
                <div id="shopeeAvatarContainer">
                    <?php $avatarUrl = get_avatar_url($user['avatar'] ?? null); ?>
                    <?php if (!empty($avatarUrl)): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="<?= e($user['name']) ?>" class="shopee-avatar-img">
                    <?php else: ?>
                        <div class="shopee-avatar-initials">
                            <?= strtoupper(substr($user['name'] ?: 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <span class="position-absolute bottom-0 end-0 bg-white text-dark rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 24px; height: 24px; font-size: 0.75rem; border: 2px solid #0d9488;">
                    <i class="fa-solid fa-camera text-teal" style="color: #0d9488;"></i>
                </span>
            </div>
            <!-- Input tersembunyi untuk langsung upload foto dari HP / Laptop -->
            <input type="file" id="directAvatarInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="handleDirectAvatarUpload(this)">

            <div class="shopee-user-info-meta">
                <div class="shopee-user-name text-truncate">
                    <?= e($user['name']) ?>
                </div>
                <div class="shopee-user-sub text-truncate">
                    <?= !empty($user['phone']) ? e($user['phone']) : e($user['email']) ?>
                </div>
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                    <span class="shopee-badge-pill">
                        <i class="fa-solid fa-id-badge text-teal"></i>
                        <span>ID: <?= format_user_id($user['id']) ?></span>
                    </span>
                    <?php if (!empty($user['email_verified_at'])): ?>
                        <span class="shopee-badge-pill" style="background: rgba(16, 185, 129, 0.15); color: #047857; border-color: rgba(16, 185, 129, 0.3);">
                            <i class="fa-solid fa-shield-check text-success"></i>
                            <span>Warga Terverifikasi Inhu</span>
                        </span>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/verify.php" class="shopee-badge-pill text-decoration-none" style="background: rgba(245, 158, 11, 0.15); color: #b45309; border-color: rgba(245, 158, 11, 0.35);" title="Klik untuk verifikasi via WhatsApp / Gmail">
                            <i class="fa-solid fa-triangle-exclamation text-warning"></i>
                            <span>Belum Verifikasi &rsaquo;</span>
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-xs py-0 px-2 rounded-pill bg-white text-teal fw-bold" style="font-size: 0.68rem;" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        Ubah Profil &rsaquo;
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Banner Promo / VIP Warga Inhu -->
    <a href="<?= BASE_URL ?>/#promo" class="shopee-vip-banner">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-crown text-warning fs-5"></i>
            <div>
                <div class="fw-bold small mb-0" style="font-size: 0.78rem;">Program Warga Aktif Inhu</div>
                <div class="small opacity-80" style="font-size: 0.7rem;">Cari tukang terdekat & konsultasi langsung via WA</div>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right fs-6"></i>
    </a>

    <!-- Notifikasi Sukses / Gagal jika ada -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show mx-3 my-2 rounded-3 shadow-xs small py-2 px-3" role="alert">
            <i class="fa-solid fa-circle-check me-1.5"></i> <?= e(is_array($success) ? ($success['message'] ?? '') : $success) ?>
            <button type="button" class="btn-close py-2.5" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mx-3 my-2 rounded-3 shadow-xs small py-2 px-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-1.5"></i> <?= e(is_array($error) ? ($error['message'] ?? '') : $error) ?>
            <button type="button" class="btn-close py-2.5" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- 3. Kartu Pesanan Jasa Saya (4 Status Grid Lengkap) -->
    <div class="shopee-card">
        <div class="shopee-card-head">
            <h6 class="shopee-card-title">
                <i class="fa-solid fa-clipboard-list text-teal"></i>
                <span>Pesanan Jasa Saya</span>
            </h6>
            <a href="<?= BASE_URL ?>/user/requests.php" class="shopee-card-more">
                <span>Lihat Semua Riwayat</span>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
            </a>
        </div>

        <div class="shopee-status-grid">
            <!-- 1. Menunggu Respon -->
            <a href="<?= BASE_URL ?>/user/requests.php?status=open" class="shopee-status-item">
                <div class="shopee-status-icon-box">
                    <i class="fa-regular fa-clock text-secondary"></i>
                    <?php if ($count_open > 0): ?>
                        <span class="shopee-status-badge"><?= $count_open ?></span>
                    <?php endif; ?>
                </div>
                <span class="shopee-status-label">Menunggu</span>
            </a>

            <!-- 2. Sedang Dikerjakan -->
            <a href="<?= BASE_URL ?>/user/requests.php?status=in_progress" class="shopee-status-item">
                <div class="shopee-status-icon-box">
                    <i class="fa-solid fa-screwdriver-wrench text-teal"></i>
                    <?php if ($count_in_progress > 0): ?>
                        <span class="shopee-status-badge"><?= $count_in_progress ?></span>
                    <?php endif; ?>
                </div>
                <span class="shopee-status-label">Dikerjakan</span>
            </a>

            <!-- 3. Selesai (Kotak Baru) -->
            <a href="<?= BASE_URL ?>/user/requests.php?status=completed" class="shopee-status-item">
                <div class="shopee-status-icon-box">
                    <i class="fa-solid fa-circle-check text-success"></i>
                    <?php if ($count_completed > 0): ?>
                        <span class="shopee-status-badge bg-success"><?= $count_completed ?></span>
                    <?php endif; ?>
                </div>
                <span class="shopee-status-label">Selesai</span>
            </a>

            <!-- 4. Beri Ulasan -->
            <a href="<?= BASE_URL ?>/user/requests.php?status=completed" class="shopee-status-item">
                <div class="shopee-status-icon-box">
                    <i class="fa-regular fa-star text-warning"></i>
                    <?php if ($count_review_needed > 0): ?>
                        <span class="shopee-status-badge"><?= $count_review_needed ?></span>
                    <?php endif; ?>
                </div>
                <span class="shopee-status-label">Beri Ulasan</span>
            </a>
        </div>
    </div>

    <!-- 4. Card Buka Jasa Mandiri / Upgrade Jadi Mitra (Pusat Akuisisi & Monetisasi) -->
    <?php if ($user['role_name'] === 'pengguna'): ?>
        <div class="mx-3 my-3">
            <div class="p-3 rounded-4 border bg-gradient shadow-xs position-relative overflow-hidden" style="background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%); border-color: #99f6e4 !important;">
                <div class="d-flex align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center flex-shrink-0 shadow-xs" style="width: 44px; height: 44px; font-size: 1.15rem;">
                            <i class="fa-solid fa-store"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-1.5 mb-0.5">
                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.85rem;">Buka Jasa Mandiri</h6>
                                <span class="badge rounded-pill bg-warning text-dark px-1.5 py-0.5 fw-bold" style="font-size: 0.62rem;">GRATIS</span>
                            </div>
                            <div class="text-secondary" style="font-size: 0.72rem; line-height: 1.35;">Mulai terima order pekerjaan dari warga Inhu & dapatkan saldo awal gratis!</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-teal btn-sm fw-bold px-3 py-2 rounded-3 text-nowrap shadow-xs" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#upgradeProviderModal">
                        Buka Sekarang &rsaquo;
                    </button>
                </div>
            </div>
        </div>
    <?php elseif ($user['role_name'] === 'penyedia'): ?>
        <div class="mx-3 my-3">
            <div class="p-3 rounded-4 border border-warning-subtle bg-warning-subtle shadow-xs d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center flex-shrink-0 shadow-xs" style="width: 42px; height: 42px;">
                        <i class="fa-solid fa-store fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-1.5 mb-0.5">
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.85rem;">Akun Mitra Jasa Anda</h6>
                            <span class="badge rounded-pill bg-warning text-dark px-2 py-0.5 fw-bold" style="font-size: 0.65rem;">Mitra Terdaftar</span>
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;">Beralih kembali ke halaman profil & dashboard usaha mitra Anda.</div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/switch_mode.php?to=penyedia" class="btn btn-warning btn-sm fw-bold px-3 py-2 rounded-3 text-nowrap shadow-xs text-dark" style="font-size: 0.75rem;">
                    <i class="fa-solid fa-repeat me-1"></i> Beralih ke Mitra
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- 5. Menu Pengaturan & Informasi Akun -->
    <div class="shopee-list-group">
        <!-- 1. Alamat Domisili -->
        <a href="javascript:void(0)" class="shopee-list-item" data-bs-toggle="modal" data-bs-target="#editAddressModal">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-location-dot shopee-list-icon text-danger"></i>
                <div>
                    <span class="d-block">Alamat Domisili</span>
                    <span class="text-muted small" style="font-size: 0.7rem;"><?= !empty($user['district_name']) ? 'Kec. ' . e($user['district_name']) : 'Atur lokasi rumah Anda di Inhu' ?></span>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
        </a>

        <!-- 2. Pengaturan Notifikasi & Suara HP -->
        <a href="javascript:void(0)" class="shopee-list-item" data-bs-toggle="modal" data-bs-target="#notificationSettingsModal">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-bell shopee-list-icon text-warning"></i>
                <span>Pengaturan Notifikasi & Suara HP</span>
            </div>
            <div class="d-flex align-items-center gap-1.5">
                <span class="badge bg-teal-subtle text-teal py-0.5 px-2 rounded-pill small" style="font-size: 0.68rem;">Atur Suara</span>
                <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
            </div>
        </a>

        <?php if ($user['role_name'] === 'penyedia'): ?>
        <!-- 3. Beralih ke Profil Mitra di Mobile -->
        <a href="<?= BASE_URL ?>/switch_mode.php?to=penyedia" class="shopee-list-item bg-warning-subtle text-warning-emphasis">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-store shopee-list-icon text-warning"></i>
                <span class="fw-bold">Beralih ke Profil Mitra Jasa</span>
            </div>
            <div class="d-flex align-items-center gap-1.5">
                <span class="badge bg-warning text-dark py-0.5 px-2 rounded-pill small" style="font-size: 0.68rem;">Mode Mitra</span>
                <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
            </div>
        </a>
        <?php endif; ?>

        <!-- 4. Syarat & Ketentuan Layanan -->
        <a href="<?= BASE_URL ?>/terms.php" class="shopee-list-item">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-file-contract shopee-list-icon text-secondary"></i>
                <span>Syarat & Ketentuan Layanan</span>
            </div>
            <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
        </a>

        <!-- 5. Kebijakan Privasi -->
        <a href="<?= BASE_URL ?>/privacy.php" class="shopee-list-item">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-user-shield shopee-list-icon text-secondary"></i>
                <span>Kebijakan Privasi</span>
            </div>
            <i class="fa-solid fa-chevron-right shopee-list-chevron"></i>
        </a>

        <!-- 6. Keluar dari Akun (Logout) -->
        <a href="<?= BASE_URL ?>/logout.php" class="shopee-list-item text-danger">
            <div class="shopee-list-item-left">
                <i class="fa-solid fa-arrow-right-from-bracket shopee-list-icon text-danger"></i>
                <span class="text-danger fw-semibold">Keluar dari Akun (Logout)</span>
            </div>
            <i class="fa-solid fa-chevron-right shopee-list-chevron text-danger"></i>
        </a>
    </div>

    <!-- Tombol Buka Pengaturan Cepat di Mobile -->
    <div class="px-3 pt-1 pb-4">
        <button type="button" class="btn btn-outline-secondary btn-sm w-100 py-2 rounded-3 fw-bold bg-white" data-bs-toggle="offcanvas" data-bs-target="#settingsOffcanvas">
            <i class="fa-solid fa-gear me-1"></i> Buka Pengaturan Akun & Keamanan
        </button>
    </div>
</div>

<!-- ==============================================================
     TAMPILAN DESKTOP (LAYAR KOMPUTER / LAPTOP - GAYA SHOPEE FOTO 1)
     ============================================================== -->
<div class="container d-none d-lg-block shopee-desktop-container">
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-xs mb-3" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= e($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-xs mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- SIDEBAR KIRI: Navigasi Akun Shopee-Style (Foto 1) -->
        <div class="col-lg-3">
            <div class="shopee-desktop-sidebar">
                <!-- User Info Header -->
                <div class="shopee-sidebar-user">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= BASE_URL ?>/uploads/avatars/<?= e($user['avatar']) ?>" alt="<?= e($user['name']) ?>" class="shopee-sidebar-avatar">
                    <?php else: ?>
                        <div class="shopee-sidebar-avatar-init">
                            <?= strtoupper(substr($user['name'] ?: 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="text-truncate">
                        <div class="shopee-sidebar-name text-truncate"><?= e($user['name']) ?></div>
                        <div class="text-muted small" style="font-size: 0.72rem;">ID: <span class="fw-bold text-teal"><?= format_user_id($user['id']) ?></span></div>
                        <a href="?tab=profile" class="shopee-sidebar-edit-link">
                            <i class="fa-solid fa-pencil" style="font-size: 0.7rem;"></i> Ubah Profil
                        </a>
                    </div>
                </div>

                <!-- Menu Item: Akun Saya -->
                <div class="shopee-nav-header">
                    <i class="fa-regular fa-user text-primary"></i>
                    <span>Akun Saya</span>
                </div>
                <nav class="nav flex-column mb-2">
                    <a href="?tab=profile" class="shopee-nav-sub-item <?= $active_tab === 'profile' ? 'active' : '' ?>">
                        Profil Saya
                    </a>
                    <a href="?tab=address" class="shopee-nav-sub-item <?= $active_tab === 'address' ? 'active' : '' ?>">
                        Alamat Domisili
                    </a>
                    <a href="?tab=password" class="shopee-nav-sub-item <?= $active_tab === 'password' ? 'active' : '' ?>">
                        Ubah Password
                    </a>
                    <a href="javascript:void(0)" class="shopee-nav-sub-item text-dark" data-bs-toggle="modal" data-bs-target="#notificationSettingsModal">
                        <i class="fa-solid fa-bell me-1 text-warning"></i> Notifikasi & Suara
                    </a>
                    <?php if ($user['role_name'] === 'penyedia'): ?>
                        <a href="<?= BASE_URL ?>/switch_mode.php?to=penyedia" class="shopee-nav-sub-item fw-bold text-warning-emphasis">
                            <i class="fa-solid fa-store me-1 text-warning"></i> Beralih ke Profil Mitra
                        </a>
                    <?php endif; ?>
                </nav>

                <!-- Menu Item: Pesanan Saya -->
                <a href="<?= BASE_URL ?>/user/requests.php" class="shopee-nav-single">
                    <i class="fa-solid fa-clipboard-list text-teal"></i>
                    <span>Pesanan Saya</span>
                    <?php if ($count_open + $count_in_progress > 0): ?>
                        <span class="badge rounded-pill bg-danger ms-auto"><?= $count_open + $count_in_progress ?></span>
                    <?php endif; ?>
                </a>

                <!-- Menu Item: Tender Kilat (Disembunyikan Sementara) -->
                <?php if (false): ?>
                <a href="<?= BASE_URL ?>/tender.php" class="shopee-nav-single">
                    <i class="fa-solid fa-bullhorn text-warning"></i>
                    <span>Tender Jasa Saya</span>
                </a>
                <?php endif; ?>

                <!-- Menu Item: Obrolan -->
                <a href="<?= BASE_URL ?>/chat.php" class="shopee-nav-single">
                    <i class="fa-solid fa-comments text-info"></i>
                    <span>Obrolan / Chat</span>
                    <?php if ($unread_chats > 0): ?>
                        <span class="badge rounded-pill bg-danger ms-auto"><?= $unread_chats ?></span>
                    <?php endif; ?>
                </a>

                <!-- Menu Item: Pusat Bantuan -->
                <a href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>?text=Halo%20Admin%20Jasa%20Inhu,%20saya%20butuh%20bantuan%20layanan" target="_blank" class="shopee-nav-single">
                    <i class="fa-solid fa-headset text-success"></i>
                    <span>Pusat Bantuan CS</span>
                </a>

                <?php if ($user['role_name'] === 'pengguna'): ?>
                    <div class="mt-3 p-3 rounded-3 border text-start" style="background: linear-gradient(135deg, #f0fdfa 0%, #e6fffa 100%); border-color: #99f6e4 !important;">
                        <div class="d-flex align-items-center gap-2 mb-1.5">
                            <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                <i class="fa-solid fa-store"></i>
                            </div>
                            <span class="fw-bold text-dark small" style="font-size: 0.8rem;">Buka Jasa Mandiri</span>
                        </div>
                        <p class="text-muted mb-2.5" style="font-size: 0.72rem; line-height: 1.4;">
                            Punya keahlian tukang, servis, atau rental? Daftar jadi mitra & dapatkan bonus saldo kuota order gratis!
                        </p>
                        <button type="button" class="btn btn-teal btn-sm w-100 fw-bold py-1.5 rounded-2 shadow-xs" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#upgradeProviderModal">
                            <i class="fa-solid fa-circle-plus me-1"></i> Buka Jasa Sekarang
                        </button>
                    </div>
                <?php elseif ($user['role_name'] === 'penyedia'): ?>
                    <div class="mt-3 p-3 rounded-3 border border-warning-subtle bg-warning-subtle text-start shadow-xs">
                        <div class="d-flex align-items-center gap-2 mb-1.5">
                            <i class="fa-solid fa-store text-warning fs-5"></i>
                            <span class="fw-bold text-dark small" style="font-size: 0.82rem;">Akun Mitra Terdaftar</span>
                        </div>
                        <p class="text-muted mb-2.5" style="font-size: 0.72rem; line-height: 1.4;">
                            Anda saat ini sedang dalam <strong>Mode Pengguna Biasa</strong>. Ingin kembali mengelola layanan jasa & pesanan pelanggan?
                        </p>
                        <a href="<?= BASE_URL ?>/switch_mode.php?to=penyedia" class="btn btn-warning btn-sm w-100 fw-bold py-2 rounded-2 shadow-xs text-dark" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-repeat me-1"></i> Beralih ke Profil Mitra &rsaquo;
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KONTEN KANAN: Formulir Profil / Password (Foto 1) -->
        <div class="col-lg-9">
            <div class="shopee-desktop-card">
                <?php if ($active_tab === 'password'): ?>
                    <!-- Form Ubah Password -->
                    <div class="shopee-desktop-card-head">
                        <h4 class="shopee-desktop-title">Ubah Kata Sandi</h4>
                        <p class="shopee-desktop-subtitle">Untuk keamanan akun Anda, mohon jangan bagikan kata sandi Anda kepada orang lain.</p>
                    </div>

                    <form method="POST" action="<?= BASE_URL ?>/user/profile.php?tab=password" style="max-width: 580px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="shopee-form-row">
                            <label class="shopee-form-label">Kata Sandi Saat Ini</label>
                            <div class="shopee-form-control-wrap">
                                <input type="password" name="old_password" class="form-control" required>
                            </div>
                        </div>

                        <div class="shopee-form-row">
                            <label class="shopee-form-label">Kata Sandi Baru</label>
                            <div class="shopee-form-control-wrap">
                                <input type="password" name="new_password" class="form-control" minlength="6" required>
                                <div class="form-text" style="font-size: 0.72rem;">Minimal 6 karakter.</div>
                            </div>
                        </div>

                        <div class="shopee-form-row">
                            <label class="shopee-form-label">Konfirmasi Kata Sandi</label>
                            <div class="shopee-form-control-wrap">
                                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                            </div>
                        </div>

                        <div class="shopee-form-row mt-4">
                            <div class="shopee-form-label"></div>
                            <div class="shopee-form-control-wrap">
                                <button type="submit" class="btn btn-teal px-4 py-2 fw-bold shadow-xs">
                                    Konfirmasi Perubahan
                                </button>
                            </div>
                        </div>
                    </form>

                <?php elseif ($active_tab === 'address'): ?>
                    <!-- Form Alamat Domisili (Tab Alamat Domisili) -->
                    <div class="shopee-desktop-card-head">
                        <h4 class="shopee-desktop-title">Alamat Domisili Saya</h4>
                        <p class="shopee-desktop-subtitle">Kelola alamat domisili dan kecamatan Anda di Kabupaten Indragiri Hulu untuk memudahkan pencarian jasa dan kedatangan mitra tukang.</p>
                    </div>

                    <?php if (!empty($user['district_id']) || !empty($user['address'])): ?>
                        <!-- Kartu Info Alamat Domisili Saat Ini -->
                        <div class="p-3 mb-4 rounded-3 border bg-light d-flex align-items-start justify-content-between">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width: 40px; height: 40px;">
                                    <i class="fa-solid fa-location-dot fs-5"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="fw-bold text-dark"><?= e($user['name']) ?></span>
                                        <span class="text-secondary small">(<?= e($user['phone']) ?>)</span>
                                        <span class="badge bg-teal text-white py-0.5 px-2" style="font-size: 0.68rem;">Alamat Utama Domisili</span>
                                    </div>
                                    <div class="text-dark fw-semibold small mb-1">
                                        <?= !empty($user['district_name']) ? 'Kecamatan ' . e($user['district_name']) : '<span class="text-danger">Belum memilih kecamatan</span>' ?>, Kab. Indragiri Hulu, Riau
                                    </div>
                                    <div class="text-muted small">
                                        <?= !empty($user['address']) ? e($user['address']) : '<span class="fst-italic text-muted">Belum ada rincian jalan / patokan detail.</span>' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>/user/profile.php?tab=address" style="max-width: 650px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_address">

                        <div class="shopee-form-row">
                            <label class="shopee-form-label">Kecamatan di Inhu <span class="text-danger">*</span></label>
                            <div class="shopee-form-control-wrap">
                                <select name="district_id" class="form-select" required>
                                    <option value="">-- Pilih Kecamatan di Inhu --</option>
                                    <?php foreach ($districts as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= ($user['district_id'] == $d['id']) ? 'selected' : '' ?>>
                                            Kecamatan <?= e($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text text-muted" style="font-size: 0.72rem;">Wajib dipilih agar sistem mencocokkan tukang / penyedia jasa terdekat di sekitar Anda.</div>
                            </div>
                        </div>

                        <div class="shopee-form-row">
                            <label class="shopee-form-label">Alamat / Patokan</label>
                            <div class="shopee-form-control-wrap">
                                <textarea name="address" rows="3" class="form-control" placeholder="Contoh: Jl. Lintas Timur, RT 02 / RW 01, Gang Kenanga No. 12 (Patokan: simpang classic / depan masjid)"><?= e($user['address'] ?? '') ?></textarea>
                                <div class="form-text text-muted" style="font-size: 0.72rem;">Sertakan nomor rumah, nama gang, atau patokan yang mudah ditemukan oleh penyedia jasa.</div>
                            </div>
                        </div>

                        <div class="shopee-form-row mt-4">
                            <div class="shopee-form-label"></div>
                            <div class="shopee-form-control-wrap">
                                <button type="submit" class="btn btn-teal px-4 py-2 fw-bold shadow-xs">
                                    Simpan Alamat Domisili
                                </button>
                            </div>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- Form Profil Saya (Matches Foto 1) -->
                    <div class="shopee-desktop-card-head">
                        <h4 class="shopee-desktop-title">Profil Saya</h4>
                        <p class="shopee-desktop-subtitle">Kelola informasi profil Anda untuk mengontrol, melindungi dan mengamankan akun</p>
                    </div>

                    <form method="POST" action="<?= BASE_URL ?>/user/profile.php" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row">
                            <!-- Kolom Kiri Form Fields -->
                            <div class="col-lg-8">
                                <div class="shopee-form-row">
                                    <div class="shopee-form-label">Username / Akun</div>
                                    <div class="shopee-form-control-wrap">
                                        <div class="fw-semibold text-dark pt-1"><?= e($user['name']) ?></div>
                                    </div>
                                </div>

                                <div class="shopee-form-row">
                                    <label class="shopee-form-label">Nama Lengkap</label>
                                    <div class="shopee-form-control-wrap">
                                        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                                    </div>
                                </div>

                                <div class="shopee-form-row">
                                    <div class="shopee-form-label">Email</div>
                                    <div class="shopee-form-control-wrap">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-secondary"><?= e($user['email']) ?></span>
                                            <span class="badge text-bg-success px-2 py-0.5" style="font-size: 0.7rem;">
                                                <i class="fa-solid fa-circle-check me-0.5"></i> Terverifikasi
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="shopee-form-row">
                                    <label class="shopee-form-label">Nomor WhatsApp</label>
                                    <div class="shopee-form-control-wrap">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-success fw-bold"><i class="fa-brands fa-whatsapp"></i></span>
                                            <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="shopee-form-row">
                                    <label class="shopee-form-label">Jenis Kelamin</label>
                                    <div class="shopee-form-control-wrap">
                                        <div class="d-flex align-items-center gap-4 pt-1 flex-wrap">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="gender" id="genderMale" value="male" <?= ($user['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                                                <label class="form-check-label text-nowrap" for="genderMale" style="cursor: pointer;">
                                                    Laki-laki
                                                </label>
                                            </div>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="gender" id="genderFemale" value="female" <?= ($user['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                                                <label class="form-check-label text-nowrap" for="genderFemale" style="cursor: pointer;">
                                                    Perempuan
                                                </label>
                                            </div>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="gender" id="genderOther" value="other" <?= ($user['gender'] ?? '') === 'other' ? 'checked' : '' ?>>
                                                <label class="form-check-label text-muted text-nowrap" for="genderOther" style="cursor: pointer;">
                                                    Lainnya
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="shopee-form-row">
                                    <label class="shopee-form-label">Tanggal Lahir</label>
                                    <div class="shopee-form-control-wrap">
                                        <div class="input-group" style="max-width: 260px;">
                                            <span class="input-group-text bg-light text-secondary"><i class="fa-regular fa-calendar"></i></span>
                                            <input type="date" name="birth_date" class="form-control" value="<?= e($user['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="form-text text-muted" style="font-size: 0.72rem;">Opsional. Digunakan untuk verifikasi usia & penyesuaian layanan.</div>
                                    </div>
                                </div>


                                <div class="shopee-form-row">
                                    <label class="shopee-form-label">Bio / Catatan</label>
                                    <div class="shopee-form-control-wrap">
                                        <textarea name="bio" rows="2" class="form-control" placeholder="Catatan singkat tentang profil Anda..."><?= e($user['bio'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="shopee-form-row mt-4">
                                    <div class="shopee-form-label"></div>
                                    <div class="shopee-form-control-wrap">
                                        <button type="submit" class="btn btn-teal px-4 py-2 fw-bold shadow-xs">
                                            Simpan Perubahan
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Kolom Kanan Upload Avatar (Foto 1) -->
                            <div class="col-lg-4 shopee-desktop-avatar-col">
                                <?php if (!empty($user['avatar'])): ?>
                                    <img id="desktopAvatarPreview" src="<?= BASE_URL ?>/uploads/avatars/<?= e($user['avatar']) ?>" alt="Avatar" class="shopee-desktop-avatar-preview">
                                <?php else: ?>
                                    <div id="desktopAvatarPlaceholder" class="shopee-desktop-avatar-init">
                                        <?= strtoupper(substr($user['name'] ?: 'U', 0, 1)) ?>
                                    </div>
                                    <img id="desktopAvatarPreview" src="" alt="Avatar" class="shopee-desktop-avatar-preview d-none">
                                <?php endif; ?>

                                <label for="inputDesktopAvatar" class="btn btn-outline-secondary btn-sm px-3 py-1.5 fw-semibold mb-2" style="cursor: pointer;">
                                    Pilih Gambar
                                </label>
                                <input type="file" id="inputDesktopAvatar" name="avatar" class="d-none" accept="image/*" onchange="previewAvatar(this, 'desktopAvatarPreview', 'desktopAvatarPlaceholder')">

                                <div class="text-muted text-center small" style="font-size: 0.75rem; max-width: 170px;">
                                    Ukuran gambar: maks. 2 MB<br>Format: .JPEG, .PNG, .WEBP
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================
     MODAL EDIT PROFIL CEPAT (UNTUK MOBILE SAAT KLIK UBAH PROFIL)
     ============================================================== -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <h6 class="modal-title fw-bold text-dark mb-0">Ubah Profil & Alamat</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/user/profile.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="modal-body p-4">
                    <!-- Preview Foto Avatar -->
                    <div class="text-center mb-3">
                        <div class="d-inline-block position-relative">
                            <?php if (!empty($user['avatar'])): ?>
                                <img id="mobileAvatarPreview" src="<?= BASE_URL ?>/uploads/avatars/<?= e($user['avatar']) ?>" alt="Avatar" class="rounded-circle border shadow-xs" style="width: 80px; height: 80px; object-fit: cover;">
                            <?php else: ?>
                                <div id="mobileAvatarPlaceholder" class="rounded-circle bg-teal text-white fw-bold d-flex align-items-center justify-content-center shadow-xs mx-auto" style="width: 80px; height: 80px; font-size: 2rem;">
                                    <?= strtoupper(substr($user['name'] ?: 'U', 0, 1)) ?>
                                </div>
                                <img id="mobileAvatarPreview" src="" alt="Avatar" class="rounded-circle border shadow-xs d-none" style="width: 80px; height: 80px; object-fit: cover;">
                            <?php endif; ?>

                            <label for="inputMobileAvatar" class="btn btn-sm btn-dark position-absolute bottom-0 end-0 rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; cursor: pointer;">
                                <i class="fa-solid fa-camera" style="font-size: 0.75rem;"></i>
                            </label>
                            <input type="file" id="inputMobileAvatar" name="avatar" class="d-none" accept="image/*" onchange="previewAvatar(this, 'mobileAvatarPreview', 'mobileAvatarPlaceholder')">
                        </div>
                        <div class="text-muted small mt-1" style="font-size: 0.72rem;">Sentuh ikon kamera untuk ganti foto</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nomor WhatsApp</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis Kelamin</label>
                        <div class="d-flex align-items-center gap-3 pt-1 flex-wrap">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="radio" name="gender" id="mGenderMale" value="male" <?= ($user['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                                <label class="form-check-label small text-nowrap" for="mGenderMale" style="cursor: pointer;">
                                    Laki-laki
                                </label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="radio" name="gender" id="mGenderFemale" value="female" <?= ($user['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                                <label class="form-check-label small text-nowrap" for="mGenderFemale" style="cursor: pointer;">
                                    Perempuan
                                </label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="radio" name="gender" id="mGenderOther" value="other" <?= ($user['gender'] ?? '') === 'other' ? 'checked' : '' ?>>
                                <label class="form-check-label small text-muted text-nowrap" for="mGenderOther" style="cursor: pointer;">
                                    Lainnya
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tanggal Lahir</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-secondary"><i class="fa-regular fa-calendar"></i></span>
                            <input type="date" name="birth_date" class="form-control" value="<?= e($user['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.72rem;">Opsional. Digunakan untuk verifikasi usia.</div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Bio / Catatan</label>
                        <textarea name="bio" rows="2" class="form-control" placeholder="Deskripsi singkat profil Anda..."><?= e($user['bio'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="modal-footer border-top py-2.5 px-4 bg-light">
                    <button type="button" class="btn btn-light btn-sm fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal btn-sm fw-bold px-3">Simpan Profil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================
     MODAL EDIT ALAMAT DOMISILI (UNTUK MOBILE)
     ============================================================== -->
<div class="modal fade" id="editAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <h6 class="modal-title fw-bold text-dark mb-0">Alamat Domisili Saya</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/user/profile.php?tab=address">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_address">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kecamatan di Inhu <span class="text-danger">*</span></label>
                        <select name="district_id" class="form-select" required>
                            <option value="">-- Pilih Kecamatan di Inhu --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($user['district_id'] == $d['id']) ? 'selected' : '' ?>>
                                    Kecamatan <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted" style="font-size: 0.72rem;">Wajib dipilih agar sistem mencocokkan tukang terdekat di wilayah Anda.</div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Alamat / Patokan</label>
                        <textarea name="address" rows="3" class="form-control" placeholder="Jl. Lintas Timur, Gang Kenanga, Patokan dekat..."><?= e($user['address'] ?? '') ?></textarea>
                        <div class="form-text text-muted" style="font-size: 0.72rem;">Sertakan nomor rumah, nama gang, atau patokan terdekat.</div>
                    </div>
                </div>

                <div class="modal-footer border-top py-2.5 px-4 bg-light">
                    <button type="button" class="btn btn-light btn-sm fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal btn-sm fw-bold px-3">Simpan Alamat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================
     MODAL KUPON & VOUCHER PROMO WARGA INHU
     ============================================================== -->
<div class="modal fade" id="modalKuponPromo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa-solid fa-ticket fs-6"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Voucher & Kupon Hemat Inhu</h6>
                        <div class="text-muted" style="font-size: 0.72rem;">Gunakan saat pesan jasa untuk dapat potongan harga</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 bg-light">
                <!-- Voucher 1 -->
                <div class="p-3 bg-white rounded-3 border shadow-xs mb-3 position-relative overflow-hidden" style="border-left: 5px solid #0d9488 !important;">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <span class="badge bg-teal-subtle text-teal fw-bold px-2 py-0.5 mb-1" style="font-size: 0.65rem;">DISKON KHUSUS WARGA</span>
                            <h6 class="fw-bold mb-0 text-dark">Potongan Rp 15.000</h6>
                            <p class="text-muted mb-0" style="font-size: 0.75rem;">Semua Jasa Servis & Tukang di Kabupaten Inhu</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-teal fw-bold px-2.5 py-1" style="font-size: 0.75rem;" onclick="copyCouponCode('WARGAINHU15', this)">
                            <i class="fa-regular fa-copy me-1"></i> Salin
                        </button>
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted" style="font-size: 0.72rem;">
                        <span>Kode: <strong class="text-dark font-monospace">WARGAINHU15</strong></span>
                        <span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> Siap Digunakan</span>
                    </div>
                </div>

                <!-- Voucher 2 -->
                <div class="p-3 bg-white rounded-3 border shadow-xs position-relative overflow-hidden" style="border-left: 5px solid #f59e0b !important;">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <span class="badge bg-warning-subtle text-warning-emphasis fw-bold px-2 py-0.5 mb-1" style="font-size: 0.65rem;">BEBAS BIAYA KUNJUNGAN</span>
                            <h6 class="fw-bold mb-0 text-dark">Gratis Biaya Transport</h6>
                            <p class="text-muted mb-0" style="font-size: 0.75rem;">Untuk teknisi di kecamatan tempat tinggal Anda</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold px-2.5 py-1" style="font-size: 0.75rem;" onclick="copyCouponCode('BEBASONGKIR', this)">
                            <i class="fa-regular fa-copy me-1"></i> Salin
                        </button>
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted" style="font-size: 0.72rem;">
                        <span>Kode: <strong class="text-dark font-monospace">BEBASONGKIR</strong></span>
                        <span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> Siap Digunakan</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top p-3 bg-white justify-content-between">
                <span class="text-muted small" style="font-size: 0.75rem;">Salin kode voucher saat order jasa</span>
                <a href="<?= BASE_URL ?>/search.php" class="btn btn-sm btn-teal fw-bold px-3">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Cari Jasa Sekarang
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================
     MODAL POIN LOYALITAS WARGA INHU
     ============================================================== -->
<div class="modal fade" id="modalPoinLoyalitas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4 bg-white">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa-solid fa-coins fs-6"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Poin Loyalitas Warga</h6>
                        <div class="text-muted" style="font-size: 0.72rem;">Reward setiap Anda memesan jasa di JASA INHU</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="p-4 rounded-4 text-white mb-3 shadow-xs" style="background: linear-gradient(135deg, #0d9488 0%, #047857 100%);">
                    <div class="small opacity-80 mb-1" style="font-size: 0.8rem;">Saldo Poin Anda Saat Ini</div>
                    <div class="display-6 fw-bold mb-1"><?= $count_completed * 10 ?> <span class="fs-5">Poin</span></div>
                    <div class="small opacity-90" style="font-size: 0.75rem;">Dari total <?= $count_completed ?> pesanan jasa yang diselesaikan</div>
                </div>

                <div class="p-3 bg-light rounded-3 text-start mb-3 border">
                    <h6 class="fw-bold text-dark small mb-2"><i class="fa-solid fa-gift text-teal me-1.5"></i> Keuntungan Poin Loyalitas:</h6>
                    <ul class="list-unstyled mb-0 small text-muted" style="font-size: 0.78rem; line-height: 1.6;">
                        <li><i class="fa-solid fa-check text-success me-1.5"></i> Dapatkan <strong>+10 Poin</strong> otomatis setiap 1 pesanan selesai.</li>
                        <li><i class="fa-solid fa-check text-success me-1.5"></i> Kumpulkan <strong>50 Poin</strong>: Potongan Biaya Pesanan Rp 25.000.</li>
                        <li><i class="fa-solid fa-check text-success me-1.5"></i> Kumpulkan <strong>100 Poin</strong>: Kaos Eksklusif Warga Inhu.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer border-top p-3 bg-white justify-content-between">
                <button type="button" class="btn btn-sm btn-light border text-muted" data-bs-dismiss="modal">Tutup</button>
                <a href="<?= BASE_URL ?>/search.php" class="btn btn-sm btn-teal fw-bold px-3">
                    <i class="fa-solid fa-screwdriver-wrench me-1"></i> Pesan Jasa & Tambah Poin
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================
     OFFCANVAS: PENGATURAN AKUN MOBILE (GAYA SHOPEE FOTO 2)
     ============================================================== -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="settingsOffcanvas" aria-labelledby="settingsOffcanvasLabel" style="max-width: 420px; width: 100%;">
    <div class="offcanvas-header border-bottom py-3 px-3 bg-white">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light border-0 rounded-circle d-flex align-items-center justify-content-center" data-bs-dismiss="offcanvas" style="width: 32px; height: 32px;">
                <i class="fa-solid fa-arrow-left text-dark"></i>
            </button>
            <h6 class="offcanvas-title fw-bold text-dark mb-0" id="settingsOffcanvasLabel">Pengaturan Akun</h6>
        </div>
        <a href="<?= BASE_URL ?>/chat.php" class="btn btn-sm btn-light border-0 rounded-circle text-muted" title="Pesan">
            <i class="fa-solid fa-comments"></i>
        </a>
    </div>

    <div class="offcanvas-body p-0 bg-light">
        <!-- Section: Akun Saya -->
        <div class="shopee-settings-section-title">Akun Saya</div>
        <div class="list-group list-group-flush border-top border-bottom bg-white mb-2">
            <?php if ($user['role_name'] === 'pengguna'): ?>
                <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 text-teal text-decoration-none bg-teal-subtle bg-opacity-10" data-bs-toggle="modal" data-bs-target="#upgradeProviderModal">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-store text-teal"></i>
                        <div>
                            <span class="small fw-bold d-block text-dark">Buka Jasa Mandiri (Jadi Mitra)</span>
                            <span class="text-muted" style="font-size: 0.7rem;">Mulai cari rezeki & terima orderan dari warga Inhu</span>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-warning text-dark px-2 py-0.5 fw-bold" style="font-size: 0.65rem;">GRATIS</span>
                </a>
            <?php elseif ($user['role_name'] === 'penyedia'): ?>
                <a href="<?= BASE_URL ?>/provider/index.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 text-dark text-decoration-none">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-briefcase text-success"></i>
                        <span class="small fw-semibold">Dashboard Mitra Penyedia Jasa</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
                </a>
            <?php endif; ?>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 text-dark text-decoration-none" data-bs-toggle="modal" data-bs-target="#changePassModal">
                <span class="small fw-semibold">Keamanan & Ubah Kata Sandi</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>
            <a href="javascript:void(0)" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 text-dark text-decoration-none" data-bs-toggle="modal" data-bs-target="#editAddressModal">
                <div>
                    <span class="small fw-semibold d-block">Alamat Saya (Inhu)</span>
                    <span class="text-muted" style="font-size: 0.72rem;"><?= e($user['district_name'] ? 'Kec. ' . $user['district_name'] : 'Kabupaten Indragiri Hulu') ?></span>
                </div>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>
            <div class="list-group-item d-flex align-items-center justify-content-between py-3 px-3">
                <span class="small fw-semibold">Status Verifikasi</span>
                <span class="badge text-bg-success px-2 py-1" style="font-size: 0.7rem;">
                    <i class="fa-solid fa-shield-halved me-1"></i> Aktif & Terverifikasi
                </span>
            </div>
        </div>

        <!-- Section: Pengaturan -->
        <div class="shopee-settings-section-title">Pengaturan Platform</div>
        <div class="list-group list-group-flush border-top border-bottom bg-white mb-2">
            <a href="<?= BASE_URL ?>/chat.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 text-dark text-decoration-none">
                <span class="small fw-semibold">Pengaturan Obrolan & Pesan</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>
            <a href="<?= BASE_URL ?>/user/requests.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 text-dark text-decoration-none">
                <span class="small fw-semibold">Pengaturan Pesanan Jasa</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>
            <div class="list-group-item d-flex align-items-center justify-content-between py-3 px-3">
                <span class="small fw-semibold">Bahasa / Language</span>
                <span class="text-muted small" style="font-size: 0.78rem;">Bahasa Indonesia</span>
            </div>
        </div>

        <!-- Section: Bantuan & Regulasi -->
        <div class="shopee-settings-section-title">Bantuan & Ketentuan</div>
        <div class="list-group list-group-flush border-top border-bottom bg-white mb-3">
            <a href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>?text=Halo%20Admin%20Jasa%20Inhu,%20saya%20butuh%20bantuan%20layanan" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 text-dark text-decoration-none">
                <span class="small fw-semibold">Pusat Bantuan CS (WhatsApp Admin)</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>
            <a href="<?= BASE_URL ?>/terms.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 text-dark text-decoration-none">
                <span class="small fw-semibold">Peraturan & Syarat Layanan</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>
            <a href="<?= BASE_URL ?>/privacy.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 text-dark text-decoration-none">
                <span class="small fw-semibold">Kebijakan Privasi</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.72rem;"></i>
            </a>
        </div>

        <!-- Tombol Keluar dari Akun (Logout) Khusus Mobile (Foto 2) -->
        <a href="<?= BASE_URL ?>/logout.php" class="shopee-btn-logout-mobile">
            <i class="fa-solid fa-arrow-right-from-bracket me-1.5"></i> Ganti Akun / Keluar
        </a>
    </div>
</div>

<!-- Modal Ubah Kata Sandi Mobile -->
<div class="modal fade" id="changePassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom py-3 px-4">
                <h6 class="modal-title fw-bold text-dark mb-0">Ubah Kata Sandi Akun</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/user/profile.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kata Sandi Saat Ini</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kata Sandi Baru</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                        <div class="form-text" style="font-size: 0.72rem;">Minimal 6 karakter.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Ulangi Kata Sandi Baru</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                </div>

                <div class="modal-footer border-top py-2.5 px-4 bg-light">
                    <button type="button" class="btn btn-light btn-sm fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal btn-sm fw-bold px-3">Perbarui Kata Sandi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==============================================================
     MODAL BUKA JASA MANDIRI / UPGRADE JADI MITRA PENYEDIA
     ============================================================== -->
<div class="modal fade" id="upgradeProviderModal" tabindex="-1" aria-labelledby="upgradeProviderModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable my-2 mx-auto" style="max-width: 440px; max-height: calc(100dvh - 24px);">
        <form method="POST" action="<?= BASE_URL ?>/user/profile.php" id="formUpgradeProvider" class="modal-content rounded-4 border-0 shadow overflow-hidden" style="max-height: calc(100dvh - 24px); height: 100%; display: flex; flex-direction: column;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upgrade_to_provider">

            <div class="modal-header border-bottom py-2.5 px-3 bg-white flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center shadow-xs flex-shrink-0" style="width: 32px; height: 32px;">
                        <i class="fa-solid fa-store" style="font-size: 0.82rem;"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="upgradeProviderModalLabel" style="font-size: 0.88rem;">Buka Jasa Mandiri</h6>
                        <div class="text-muted" style="font-size: 0.68rem;">Daftar Jadi Mitra Penyedia Jasa di JASA INHU</div>
                    </div>
                </div>
                <button type="button" class="btn-close" style="font-size: 0.7rem;" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body px-3 py-2.5" style="flex: 1 1 auto; overflow-y: auto !important; -webkit-overflow-scrolling: touch; overscroll-behavior: contain;">
                <!-- Banner Mini Keuntungan Mitra -->
                <div class="px-2.5 py-2 rounded-3 mb-2.5 d-flex align-items-center gap-2 border" style="background: linear-gradient(135deg, #f0fdfa 0%, #e6fffa 100%); border-color: #99f6e4 !important;">
                    <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 24px; height: 24px; font-size: 0.72rem;">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <div style="font-size: 0.72rem; line-height: 1.35; color: #0f766e;">
                        <span class="fw-bold">Keuntungan Mitra Baru:</span> Akun & nomor WA tetap sama, langsung dapat bonus kuota pesanan awal gratis.
                    </div>
                </div>

                <!-- 1. Nama Usaha / Merek Jasa -->
                <div class="mb-2">
                    <label class="form-label mb-1 fw-bold text-dark" style="font-size: 0.75rem;">Nama Usaha / Merek Layanan Jasa <span class="text-danger">*</span></label>
                    <input type="text" name="business_name" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" style="font-size: 0.82rem;" placeholder="Contoh: Bengkel AC Berkah / Tukang <?= e($user['name']) ?>" value="<?= e($user['name']) ?>" required>
                    <div class="text-muted" style="font-size: 0.67rem; margin-top: 2px;">Bisa nama pribadi atau merek usaha Anda.</div>
                </div>

                <!-- 2. Kategori Keahlian Utama -->
                <div class="mb-2">
                    <label class="form-label mb-1 fw-bold text-dark" style="font-size: 0.75rem;">Kategori Keahlian Utama <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select form-select-sm rounded-3 py-1.5 px-2.5" style="font-size: 0.82rem;" required>
                        <option value="">-- Pilih Kategori Jasa --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>">
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Kecamatan Operasional -->
                <div class="mb-2">
                    <label class="form-label mb-1 fw-bold text-dark" style="font-size: 0.75rem;">Kecamatan Domisili / Layanan <span class="text-danger">*</span></label>
                    <select name="district_id" class="form-select form-select-sm rounded-3 py-1.5 px-2.5" style="font-size: 0.82rem;" required>
                        <option value="">-- Pilih Kecamatan di Inhu --</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= ($user['district_id'] == $d['id']) ? 'selected' : '' ?>>
                                Kecamatan <?= e($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Alamat / Lokasi -->
                <div class="mb-2">
                    <label class="form-label mb-1 fw-bold text-dark" style="font-size: 0.75rem;">Alamat / Patokan Usaha</label>
                    <textarea name="address" rows="2" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" style="font-size: 0.82rem;" placeholder="Contoh: Jl. Narasinga, Rengat (dekat Pasar)"><?= e($user['address'] ?? '') ?></textarea>
                </div>

                <!-- 5. Deskripsi Layanan -->
                <div class="mb-1">
                    <label class="form-label mb-1 fw-bold text-dark" style="font-size: 0.75rem;">Deskripsi Singkat Keahlian</label>
                    <textarea name="description" rows="2" class="form-control form-control-sm rounded-3 py-1.5 px-2.5" style="font-size: 0.82rem;" placeholder="Jelaskan keahlian Anda, jam buka layanan, pengalaman kerja..."></textarea>
                </div>
            </div>

            <div class="modal-footer border-top py-2 px-3 bg-light justify-content-between flex-shrink-0">
                <button type="button" class="btn btn-light btn-sm fw-semibold rounded-3 py-1 px-3" style="font-size: 0.78rem;" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-teal btn-sm fw-bold px-3 py-1.5 rounded-3 shadow-xs" style="font-size: 0.8rem;" id="btnSubmitUpgrade">
                    <i class="fa-solid fa-store me-1"></i> Aktifkan Jasa & Masuk Dashboard &rsaquo;
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==============================================================
     MODAL PENGATURAN NOTIFIKASI & SUARA HP PENGGUNA
     ============================================================== -->
<div class="modal fade" id="notificationSettingsModal" tabindex="-1" aria-labelledby="notifModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content rounded-4 border-0 shadow overflow-hidden">
            <div class="modal-header border-bottom py-2.5 px-3 bg-white">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning bg-opacity-15 text-warning-emphasis d-flex align-items-center justify-content-center flex-shrink-0" style="width: 34px; height: 34px;">
                        <i class="fa-solid fa-bell fs-6 text-warning"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0 fs-6" id="notifModalLabel">Notifikasi & Suara Aplikasi</h6>
                        <span class="text-muted small" style="font-size: 0.72rem;">Atur nada dering & pemberitahuan pesanan Anda</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3.5">
                <!-- Info Status Suara -->
                <div class="p-3 rounded-3 bg-light border mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="small fw-bold text-dark">Suara Notifikasi Dering</span>
                        <button type="button" class="btn btn-sm btn-light text-teal fw-bold rounded-pill sound-toggle-btn shadow-2xs" onclick="AppNotification.toggleSound('customer')" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-volume-high me-1 text-success"></i> Suara: Aktif (On)
                        </button>
                    </div>
                    <p class="text-muted small mb-0" style="font-size: 0.73rem;">
                        Berbunyi halus (*soft chime*) saat mitra menerima pesanan Anda, mulai OTW ke rumah Anda, atau mengirimkan chat pesan.
                    </p>
                </div>

                <!-- Tombol Tes Suara & Izin HP -->
                <div class="d-grid gap-2 mb-3">
                    <button type="button" class="btn btn-teal text-white fw-bold py-2 rounded-3 shadow-xs" onclick="AppNotification.testSound('customer')">
                        <i class="fa-solid fa-play me-1.5"></i> Uji Coba Suara Notifikasi Pelanggan
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-2 rounded-3 fw-semibold" onclick="AppNotification.requestBrowserNotification('customer')">
                        <i class="fa-solid fa-mobile-screen me-1.5 text-primary"></i> Izinkan Notifikasi Pop-up di Layar HP
                    </button>
                </div>

                <!-- Edukasi WhatsApp -->
                <div class="p-2.5 rounded-3 border bg-success-subtle border-success-subtle small text-success-emphasis d-flex align-items-center gap-2" style="font-size: 0.74rem;">
                    <i class="fa-brands fa-whatsapp fs-5 text-success flex-shrink-0"></i>
                    <div>
                        <strong>Notifikasi WhatsApp Otomatis:</strong> Setiap ada pembaruan status teknisi, sistem juga otomatis mengirim kabar ke WhatsApp Anda.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm rounded-3 w-100 fw-semibold" data-bs-dismiss="modal">Tutup & Simpan Pengaturan</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/provider_sound.js"></script>
<script>
function previewAvatar(input, imgId, placeholderId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(imgId);
            const placeholder = document.getElementById(placeholderId);
            if (img) {
                img.src = e.target.result;
                img.classList.remove('d-none');
            }
            if (placeholder) {
                placeholder.classList.add('d-none');
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Upload Avatar Langsung Sekali Klik Kamera
function handleDirectAvatarUpload(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            icon: 'warning',
            title: 'File Terlalu Besar',
            text: 'Ukuran foto maksimal 5 MB.'
        });
        input.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('action', 'upload_avatar_ajax');
    formData.append('avatar', file);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    Swal.fire({
        title: 'Mengunggah Foto...',
        text: 'Mohon tunggu sebentar',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const container = document.getElementById('shopeeAvatarContainer');
            if (container) {
                container.innerHTML = `<img src="${res.avatar_url}" alt="Avatar" class="shopee-avatar-img">`;
            }
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: res.message,
                timer: 1600,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: res.message
            });
        }
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Terjadi gangguan jaringan saat mengunggah foto.'
        });
    })
    .finally(() => {
        input.value = '';
    });
}

// Salin Kode Voucher Kupon Promo
function copyCouponCode(code, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check text-success me-1"></i> Tersalin!';
            btn.classList.add('btn-success', 'text-white');
            btn.classList.remove('btn-outline-teal', 'btn-outline-warning');
            setTimeout(() => {
                btn.innerHTML = orig;
                btn.classList.remove('btn-success', 'text-white');
                if (code === 'WARGAINHU15') btn.classList.add('btn-outline-teal');
                else btn.classList.add('btn-outline-warning');
            }, 2000);
        });
    } else {
        const dummy = document.createElement('textarea');
        document.body.appendChild(dummy);
        dummy.value = code;
        dummy.select();
        document.execCommand('copy');
        document.body.removeChild(dummy);
        alert('Kode voucher ' + code + ' berhasil disalin!');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

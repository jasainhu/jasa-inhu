<?php
/**
 * Global Frontend Header: JASA INHU
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$current_user = current_user();
$page_title = $page_title ?? 'Marketplace Jasa Lokal Kabupaten Indragiri Hulu';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="JASA INHU - Platform digital penghubung masyarakat yang membutuhkan jasa dengan penyedia jasa terpercaya di Kabupaten Indragiri Hulu, Riau.">
    <meta name="keywords" content="jasa inhu, servis motor rengat, servis ac belilas, tukang bangunan air molek, jasa perkebunan sawit inhu">
    <meta name="theme-color" content="#0d9488">
    <title><?= e($page_title) ?> | <?= APP_NAME ?></title>

    <!-- Favicon & PWA App Manifest -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_url('images/favicon-32x32.png') ?>?v=2">
    <link rel="icon" type="image/png" sizes="48x48" href="<?= asset_url('images/favicon.png') ?>?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset_url('images/apple-touch-icon.png') ?>?v=2">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json?v=2">

    <!-- OpenGraph Meta Tags (WhatsApp, Facebook, Twitter Preview) -->
    <meta property="og:site_name" content="<?= APP_NAME ?>">
    <meta property="og:title" content="<?= e($page_title) ?> | <?= APP_NAME ?>">
    <meta property="og:description" content="Temukan dan pesan jasa servis motor, AC, tukang bangunan, las, dan kebutuhan harian terpercaya di Kabupaten Indragiri Hulu.">
    <meta property="og:image" content="<?= asset_url('images/logo.png') ?>">
    <meta property="og:type" content="website">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- SweetAlert2 Modern Popups -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
    <script>
        window.APP_BASE_URL = '<?= BASE_URL ?>';
    </script>
</head>
<body>

<?php
$header_districts = function_exists('get_all_districts') ? get_all_districts() : [];
$header_categories = function_exists('get_active_categories') ? get_active_categories() : [];
$header_q = trim($_GET['q'] ?? '');
$header_dist = (int)($_GET['kecamatan'] ?? 0);

// Global Stats for Both Desktop & Mobile Headers
$header_active_orders = 0;
$header_unread_notifs = 0;
$header_unread_chats = 0;
$header_provider_id = 0;
$header_first_name = '';

if (is_logged_in() && !empty($current_user)) {
    $db_hdr = get_db();
    if (($current_user['role_name'] ?? '') === 'pengguna') {
        try {
            $header_active_orders = (int)$db_hdr->query("SELECT COUNT(*) FROM service_requests WHERE user_id = {$current_user['id']} AND status IN ('open', 'in_progress')")->fetchColumn();
        } catch (Exception $e) {}
    }
    try {
        $header_unread_notifs = (int)$db_hdr->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$current_user['id']} AND is_read = 0")->fetchColumn();
    } catch (Exception $e) {}
    try {
        $stmtUChat = $db_hdr->prepare("SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0");
        $stmtUChat->execute([$current_user['id']]);
        $header_unread_chats = (int)$stmtUChat->fetchColumn();
    } catch (Exception $e) {}
    if (($current_user['role_name'] ?? '') === 'penyedia') {
        try {
            $header_provider_id = (int)$db_hdr->query("SELECT id FROM service_providers WHERE user_id = {$current_user['id']} LIMIT 1")->fetchColumn();
        } catch (Exception $e) {}
    }
    $name_parts = explode(' ', trim($current_user['name']));
    $header_first_name = $name_parts[0] ?? $current_user['name'];
}
?>
<!-- Desktop Navigation Bar (Tokopedia Style) -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top d-none d-lg-block">
    <div class="container gap-2 gap-lg-3">
        <!-- Logo JASA INHU Resmi (Otomatis ke Beranda) -->
        <a class="navbar-brand d-flex align-items-center flex-shrink-0 py-0 me-2" href="<?= BASE_URL ?>/">
            <img src="<?= asset_url('images/logo.png') ?>" alt="JASA INHU - Pusat Layanan Terpadu Indragiri Hulu" class="navbar-logo-img">
        </a>

        <!-- Menu Kategori Tepat di Sebelah Logo (Tokopedia Style) -->
        <div class="dropdown d-none d-md-block flex-shrink-0">
            <button class="btn-tokopedia-cat dropdown-toggle d-flex align-items-center gap-1" type="button" id="tokopediaCatDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <span>Kategori</span>
            </button>
            <div class="dropdown-menu dropdown-menu-start shadow-lg p-3 border-0 mt-2 tokopedia-cat-menu" aria-labelledby="tokopediaCatDropdown">
                <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                    <span class="fw-bold small text-dark"><i class="fa-solid fa-shapes text-teal me-1"></i> Pilih Kategori Jasa</span>
                    <a href="<?= BASE_URL ?>/#kategori" class="small text-teal fw-semibold text-decoration-none">Semua (<?= count($header_categories) ?>) &rarr;</a>
                </div>
                <div class="tokopedia-cat-grid">
                    <?php foreach ($header_categories as $hcat): ?>
                        <a href="<?= BASE_URL ?>/search.php?kategori=<?= $hcat['id'] ?>" class="tokopedia-cat-item">
                            <div class="tokopedia-cat-icon">
                                <i class="fa-solid <?= e($hcat['icon'] ?: 'fa-wrench') ?>"></i>
                            </div>
                            <span class="tokopedia-cat-title"><?= e($hcat['name']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Kolom Pencarian Luas di Tengah (Tokopedia Modern Inset Style) -->
        <div class="header-search-wrap flex-grow-1 mx-2 mx-xl-3 d-none d-lg-block">
            <form action="<?= BASE_URL ?>/search.php" method="GET" class="header-search-form">
                <div class="search-box-unified">
                    <i class="fa-solid fa-magnifying-glass text-teal me-1"></i>
                    <input type="text" name="q" class="search-input" placeholder="Cari jasa: Servis AC, Motor, Tukang..." value="<?= e($header_q) ?>">
                    
                    <div class="district-dropdown-wrap">
                        <i class="fa-solid fa-location-dot text-teal" style="font-size: 0.78rem;"></i>
                        <select name="kecamatan" class="district-select">
                            <option value="">Semua Inhu</option>
                            <?php foreach ($header_districts as $hd): ?>
                                <option value="<?= $hd['id'] ?>" <?= $header_dist === (int)$hd['id'] ? 'selected' : '' ?>>
                                    Kec. <?= e($hd['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-search-pill">
                        Cari
                    </button>
                </div>
            </form>
        </div>

        <!-- Bagian Kanan: Tombol Masuk / Daftar atau Akun Profil (Tokopedia Style) -->
        <div class="navbar-right-actions flex-shrink-0 ms-auto d-flex align-items-center">

            <!-- Tombol Unduh Aplikasi Mobile (Android & iOS) -->
            <a href="<?= BASE_URL ?>/download.php" class="btn btn-sm d-none d-xl-flex align-items-center gap-1.5 px-2.5 py-1.5 rounded-pill border border-success-subtle bg-success-subtle text-success-emphasis text-decoration-none fw-semibold me-2" style="font-size: 0.78rem;" title="Download Aplikasi JASA INHU (Android & iOS)">
                <i class="fa-brands fa-android text-success"></i>
                <i class="fa-brands fa-apple text-dark"></i>
                <span>Download App</span>
            </a>

            <?php if (is_logged_in() && $current_user): ?>

                <!-- 1. Icon Pesanan Saya (Khusus Pengguna Warga) -->
                <?php if ($current_user['role_name'] === 'pengguna'): ?>
                    <a href="<?= BASE_URL ?>/user/requests.php" class="btn-nav-icon" title="Pesanan Jasa Saya">
                        <i class="fa-solid fa-clipboard-list"></i>
                        <?php if ($header_active_orders > 0): ?>
                            <span class="nav-badge"><?= $header_active_orders > 99 ? '99+' : $header_active_orders ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <!-- 2. Icon Kotak Obrolan & Pesan (In-App Live Chat) -->
                <a href="<?= BASE_URL ?>/chat.php" class="btn-nav-icon position-relative" title="Kotak Obrolan & Pesan">
                    <i class="fa-regular fa-comment-dots"></i>
                    <?php if ($header_unread_chats > 0): ?>
                        <span class="nav-badge bg-teal" id="headerChatBadge"><?= $header_unread_chats > 99 ? '99+' : $header_unread_chats ?></span>
                    <?php endif; ?>
                </a>

                <!-- 3. Icon Lonceng Pemberitahuan / Notifikasi -->
                <div class="dropdown">
                    <button class="btn-nav-icon" type="button" id="notifDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Pemberitahuan">
                        <i class="fa-regular fa-bell"></i>
                        <?php if ($header_unread_notifs > 0): ?>
                            <span class="nav-badge" id="headerNotifBadge"><?= $header_unread_notifs > 99 ? '99+' : $header_unread_notifs ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-notif mt-2" aria-labelledby="notifDropdownBtn">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2.5 bg-light border-bottom">
                            <span class="fw-bold small text-dark"><i class="fa-solid fa-bell text-teal me-1.5"></i> Pemberitahuan</span>
                            <?php if ($header_unread_notifs > 0): ?>
                                <button type="button" class="btn btn-link p-0 text-teal small fw-semibold text-decoration-none" style="font-size: 0.74rem;" onclick="markAllNotificationsRead(event)">
                                    <i class="fa-solid fa-check-double me-1"></i> Tandai Dibaca
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="notif-list-container" style="max-height: 320px; overflow-y: auto;">
                            <?php if (!empty($header_recent_notifs)): ?>
                                <?php foreach ($header_recent_notifs as $hn): ?>
                                    <a href="<?= BASE_URL . ($hn['link'] ? (str_starts_with($hn['link'], '/') ? $hn['link'] : '/' . $hn['link']) : '#') ?>" class="notif-item <?= !$hn['is_read'] ? 'unread' : '' ?>">
                                        <div class="notif-icon-circle">
                                            <i class="fa-solid <?= str_contains(strtolower($hn['title']), 'pesanan') ? 'fa-clipboard-check text-success' : (str_contains(strtolower($hn['title']), 'selamat datang') ? 'fa-champagne-glasses text-warning' : 'fa-bell text-teal') ?>"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="d-flex align-items-center justify-content-between mb-0.5">
                                                <div class="fw-bold small text-dark text-truncate" style="font-size: 0.82rem;"><?= e($hn['title']) ?></div>
                                                <?php if (!$hn['is_read']): ?>
                                                    <span class="badge rounded-circle bg-teal p-1 ms-1" style="width: 6px; height: 6px;" title="Belum dibaca"></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small lh-sm text-truncate" style="font-size: 0.76rem;"><?= e($hn['message']) ?></div>
                                            <div class="text-muted small mt-1" style="font-size: 0.68rem;">
                                                <i class="fa-regular fa-clock me-1"></i> <?= date('d M, H:i', strtotime($hn['created_at'])) ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 px-3 text-muted">
                                    <i class="fa-regular fa-bell-slash fs-3 mb-2 opacity-40"></i>
                                    <div class="small fw-semibold">Belum Ada Pemberitahuan</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Kabar terkait pesanan Anda akan muncul di sini.</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-center py-2 bg-light border-top">
                            <a href="<?= BASE_URL . get_dashboard_url_for_role($current_user['role_name']) ?>" class="small fw-semibold text-teal text-decoration-none" style="font-size: 0.78rem;">
                                Buka Dashboard & Aktivitas &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Pembatas Vertikal Tipis -->
                <div class="nav-vertical-divider"></div>

                <!-- 3. Avatar & Profil Pengguna (Tokopedia Modern Dropdown) -->
                <div class="dropdown">
                    <button class="btn-nav-profile dropdown-toggle" type="button" id="userProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="nav-profile-avatar">
                            <?php if (!empty($current_user['avatar']) && file_exists(__DIR__ . '/../' . $current_user['avatar'])): ?>
                                <img src="<?= BASE_URL ?>/<?= e($current_user['avatar']) ?>" alt="<?= e($current_user['name']) ?>">
                            <?php else: ?>
                                <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <span class="nav-profile-name d-none d-sm-inline"><?= e($header_first_name) ?></span>
                        <i class="fa-solid fa-chevron-down text-muted" style="font-size: 0.65rem;"></i>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-profile mt-2" aria-labelledby="userProfileDropdown">
                        <!-- Header Kartu Profil -->
                        <li class="p-2.5 mb-1 rounded-3 bg-light border">
                            <div class="d-flex align-items-center gap-2">
                                <div class="nav-profile-avatar" style="width: 38px; height: 38px; font-size: 0.95rem;">
                                    <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-dark small text-truncate lh-sm"><?= e($current_user['name']) ?></div>
                                    <div class="text-muted text-truncate" style="font-size: 0.72rem;"><?= e($current_user['email']) ?></div>
                                    <div class="mt-1">
                                        <?php if ($current_user['role_name'] === 'pengguna'): ?>
                                            <?php if (!empty($current_user['email_verified_at'])): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-2" style="font-size: 0.65rem;">
                                                    <i class="fa-solid fa-user-check me-1"></i> Warga Terverifikasi
                                                </span>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/verify.php" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle text-decoration-none py-0.5 px-2" style="font-size: 0.65rem;" title="Klik untuk verifikasi via WhatsApp atau Gmail">
                                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Belum Verifikasi
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($current_user['role_name'] === 'penyedia'): ?>
                                            <span class="badge bg-teal-subtle text-teal border border-teal-subtle py-0.5 px-2" style="font-size: 0.65rem;">
                                                <i class="fa-solid fa-wrench me-1"></i> Mitra Jasa Inhu
                                            </span>
                                        <?php elseif ($current_user['role_name'] === 'admin'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0.5 px-2" style="font-size: 0.65rem;">
                                                <i class="fa-solid fa-shield-halved me-1"></i> Administrator
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <!-- Menu Navigasi Berdasarkan Role (Pusat Akun & Pengaturan) -->
                        <?php if ($current_user['role_name'] === 'admin'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php">
                                    <i class="fa-solid fa-gauge-high me-2 text-primary"></i> Dashboard Admin
                                </a>
                            </li>
                        <?php elseif ($current_user['role_name'] === 'penyedia'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/index.php">
                                    <i class="fa-solid fa-gauge-high me-2 text-primary"></i> Dashboard Mitra Jasa
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($current_user['role_name'] === 'pengguna'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/user/profile.php">
                                    <i class="fa-solid fa-user-gear me-2 text-secondary"></i> Profil & Alamat
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/user/requests.php">
                                    <i class="fa-solid fa-clipboard-list me-2 text-teal"></i> Pesanan Saya
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>?text=Halo%20Admin%20Jasa%20Inhu,%20saya%20butuh%20bantuan%20layanan" target="_blank">
                                    <i class="fa-solid fa-headset me-2 text-info"></i> Pusat Bantuan CS
                                </a>
                            </li>
                        <?php elseif ($current_user['role_name'] === 'penyedia'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/wallet.php">
                                    <i class="fa-solid fa-wallet me-2 text-warning"></i> Dompet & Saldo Deposit
                                </a>
                            </li>
                            <?php if ($header_provider_id > 0): ?>
                                <li>
                                    <a class="dropdown-item fw-semibold text-teal" href="<?= BASE_URL ?>/provider_detail.php?id=<?= $header_provider_id ?>" target="_blank">
                                        <i class="fa-solid fa-store me-2 text-teal"></i> Lihat Profil Publik Saya ↗
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/leads.php">
                                    <i class="fa-solid fa-briefcase me-2 text-secondary"></i> Pekerjaan Masuk
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/portfolio.php">
                                    <i class="fa-solid fa-camera me-2 text-teal"></i> Portofolio Hasil Kerja
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/profile.php">
                                    <i class="fa-solid fa-user-gear me-2 text-secondary"></i> Profil & Tarif Jasa
                                </a>
                            </li>
                        <?php elseif ($current_user['role_name'] === 'admin'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/admin/banners.php">
                                    <i class="fa-solid fa-rectangle-ad me-2 text-secondary"></i> Kelola Banner & Iklan
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/admin/categories.php">
                                    <i class="fa-solid fa-list-check me-2 text-secondary"></i> Kelola Kategori
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/admin/users.php">
                                    <i class="fa-solid fa-users me-2 text-secondary"></i> Kelola Pengguna
                                </a>
                            </li>
                        <?php endif; ?>

                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">
                                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <!-- Tombol Masuk & Daftar (Tokopedia Style) -->
                <button type="button" class="btn-tokopedia-masuk" data-bs-toggle="modal" data-bs-target="#loginModal">
                    Masuk
                </button>
                <a href="<?= BASE_URL ?>/register.php" class="btn-tokopedia-daftar">
                    Daftar
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
function markAllNotificationsRead(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    fetch(window.APP_BASE_URL + '/api/mark_notifications_read.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const badge = document.getElementById('headerNotifBadge');
            if (badge) badge.remove();
            const mBadge = document.getElementById('mobileHeaderNotifBadge');
            if (mBadge) mBadge.remove();
            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
            document.querySelectorAll('.notif-item .badge.bg-teal').forEach(el => el.remove());
            if (e && e.target) {
                const markBtn = e.target.closest('button');
                if (markBtn) markBtn.remove();
            }
        }
    })
    .catch(() => {});
}
</script>

<!-- =========================================================
     GOJEK / GRAB STYLE PREMIUM MOBILE HEADER (d-lg-none)
     Tingkat 1: Chip Lokasi Glassmorphism + Action Icons
     Tingkat 2: Capsule Search Bar Lebar 100% Super Nyaman
     ========================================================= -->
<?php
$current_page_basename = basename($_SERVER['SCRIPT_NAME'] ?? '');
$is_site_homepage = ($current_page_basename === 'index.php' && ($active_nav ?? '') === 'home') || !empty($hide_back_btn);
?>
<header class="gojek-mobile-header d-lg-none sticky-top">
    <!-- Baris 1: Chip Lokasi Glassmorphism di Kiri + Action Icons di Kanan -->
    <div class="gojek-header-top">
        <div class="d-flex align-items-center gap-2 overflow-hidden flex-grow-1 me-2">
            <?php if (!$is_site_homepage): ?>
                <!-- Tombol Panah Kembali (Hanya Tampil di Luar Beranda) -->
                <a href="<?= BASE_URL ?>/" onclick="if (document.referrer && document.referrer.includes(window.location.host)) { window.history.back(); } else { window.location.href = '<?= BASE_URL ?>/'; } return false;" class="gojek-back-btn" title="Kembali" aria-label="Kembali">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
            <?php endif; ?>

            <!-- Chip Lokasi Glassmorphism Gojek Style -->
            <button type="button" class="gojek-location-chip" data-bs-toggle="modal" data-bs-target="#districtSelectModal">
                <i class="fa-solid fa-location-dot location-dot-icon"></i>
                <span class="location-name text-truncate">
                    <?= $header_dist ? ('Kec. ' . e(array_column($header_districts, 'name', 'id')[$header_dist] ?? 'Pilihan')) : 'Seluruh Kab. Inhu' ?>
                </span>
                <i class="fa-solid fa-chevron-down location-arrow-icon"></i>
            </button>
        </div>

        <!-- Aksi Kanan (Chat, Notifikasi, Akun) -->
        <div class="gojek-header-actions">
            <?php if (is_logged_in() && $current_user): ?>
                <!-- 1. Ikon Live Chat -->
                <a href="<?= BASE_URL ?>/chat.php" class="gojek-action-btn" title="Kotak Obrolan & Pesan">
                    <i class="fa-regular fa-comment-dots"></i>
                    <?php if ($header_unread_chats > 0): ?>
                        <span class="gojek-badge" id="mobileHeaderChatBadge"><?= $header_unread_chats > 99 ? '99+' : $header_unread_chats ?></span>
                    <?php endif; ?>
                </a>

                <!-- 2. Ikon Lonceng Pemberitahuan / Notifikasi -->
                <div class="dropdown">
                    <button class="gojek-action-btn btn p-0 border-0 bg-transparent" type="button" id="mobileNotifDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Pemberitahuan">
                        <i class="fa-regular fa-bell"></i>
                        <?php if ($header_unread_notifs > 0): ?>
                            <span class="gojek-badge" id="mobileHeaderNotifBadge"><?= $header_unread_notifs > 99 ? '99+' : $header_unread_notifs ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-notif mt-2 shadow-lg" aria-labelledby="mobileNotifDropdownBtn">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2.5 bg-light border-bottom">
                            <span class="fw-bold small text-dark"><i class="fa-solid fa-bell text-teal me-1.5"></i> Pemberitahuan</span>
                            <?php if ($header_unread_notifs > 0): ?>
                                <button type="button" class="btn btn-link p-0 text-teal small fw-semibold text-decoration-none" style="font-size: 0.74rem;" onclick="markAllNotificationsRead(event)">
                                    <i class="fa-solid fa-check-double me-1"></i> Tandai Dibaca
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="notif-list-container" style="max-height: 300px; overflow-y: auto;">
                            <?php if (!empty($header_recent_notifs)): ?>
                                <?php foreach ($header_recent_notifs as $hn): ?>
                                    <a href="<?= BASE_URL . ($hn['link'] ? (str_starts_with($hn['link'], '/') ? $hn['link'] : '/' . $hn['link']) : '#') ?>" class="notif-item <?= !$hn['is_read'] ? 'unread' : '' ?>">
                                        <div class="notif-icon-circle">
                                            <i class="fa-solid <?= str_contains(strtolower($hn['title']), 'pesanan') ? 'fa-clipboard-check text-success' : (str_contains(strtolower($hn['title']), 'selamat datang') ? 'fa-champagne-glasses text-warning' : 'fa-bell text-teal') ?>"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="d-flex align-items-center justify-content-between mb-0.5">
                                                <div class="fw-bold small text-dark text-truncate" style="font-size: 0.82rem;"><?= e($hn['title']) ?></div>
                                                <?php if (!$hn['is_read']): ?>
                                                    <span class="badge rounded-circle bg-teal p-1 ms-1" style="width: 6px; height: 6px;"></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small lh-sm text-truncate" style="font-size: 0.76rem;"><?= e($hn['message']) ?></div>
                                            <div class="text-muted small mt-1" style="font-size: 0.68rem;">
                                                <i class="fa-regular fa-clock me-1"></i> <?= date('d M, H:i', strtotime($hn['created_at'])) ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 px-3 text-muted">
                                    <i class="fa-regular fa-bell-slash fs-3 mb-2 opacity-40"></i>
                                    <div class="small fw-semibold">Belum Ada Pemberitahuan</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">Kabar terkait pesanan Anda akan muncul di sini.</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-center py-2 bg-light border-top">
                            <a href="<?= BASE_URL . get_dashboard_url_for_role($current_user['role_name']) ?>" class="small fw-semibold text-teal text-decoration-none" style="font-size: 0.78rem;">
                                Buka Dashboard & Aktivitas &rarr;
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 3. Profil Avatar Pengguna -->
                <div class="dropdown">
                    <button class="gojek-user-avatar-btn btn p-0 border-0" type="button" id="mobileUserProfileDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Akun: <?= e($header_first_name) ?>">
                        <?php if (!empty($current_user['avatar']) && file_exists(__DIR__ . '/../' . $current_user['avatar'])): ?>
                            <img src="<?= BASE_URL ?>/<?= e($current_user['avatar']) ?>" alt="<?= e($current_user['name']) ?>">
                        <?php else: ?>
                            <span><?= strtoupper(substr($current_user['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-profile mt-2 shadow-lg" aria-labelledby="mobileUserProfileDropdownBtn" style="min-width: 260px;">
                        <!-- Header Kartu Profil Singkat -->
                        <li class="p-2.5 mb-1 rounded-3 bg-light border">
                            <div class="d-flex align-items-center gap-2">
                                <div class="nav-profile-avatar" style="width: 36px; height: 36px; font-size: 0.9rem;">
                                    <?= strtoupper(substr($current_user['name'], 0, 1)) ?>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-dark small text-truncate lh-sm"><?= e($current_user['name']) ?></div>
                                    <div class="text-muted text-truncate" style="font-size: 0.7rem;"><?= e($current_user['email']) ?></div>
                                    <div class="mt-1">
                                        <?php if ($current_user['role_name'] === 'pengguna'): ?>
                                            <?php if (!empty($current_user['email_verified_at'])): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-2" style="font-size: 0.62rem;">
                                                    <i class="fa-solid fa-user-check me-1"></i> Warga Terverifikasi
                                                </span>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/verify.php" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle text-decoration-none py-0.5 px-2" style="font-size: 0.62rem;" title="Klik untuk verifikasi via WhatsApp atau Gmail">
                                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Belum Verifikasi
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($current_user['role_name'] === 'penyedia'): ?>
                                            <span class="badge bg-teal-subtle text-teal border border-teal-subtle py-0.5 px-2" style="font-size: 0.62rem;">
                                                <i class="fa-solid fa-wrench me-1"></i> Mitra Jasa Inhu
                                            </span>
                                        <?php elseif ($current_user['role_name'] === 'admin'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-0.5 px-2" style="font-size: 0.62rem;">
                                                <i class="fa-solid fa-shield-halved me-1"></i> Administrator
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </li>

                        <!-- Menu Navigasi Sesuai Role -->
                        <?php if ($current_user['role_name'] === 'admin'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php">
                                    <i class="fa-solid fa-gauge-high me-2 text-primary"></i> Dashboard Admin
                                </a>
                            </li>
                        <?php elseif ($current_user['role_name'] === 'penyedia'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/index.php">
                                    <i class="fa-solid fa-gauge-high me-2 text-primary"></i> Dashboard Mitra
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ($current_user['role_name'] === 'pengguna'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/user/profile.php">
                                    <i class="fa-solid fa-user-gear me-2 text-secondary"></i> Profil & Alamat
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/user/requests.php">
                                    <i class="fa-solid fa-clipboard-list me-2 text-teal"></i> Pesanan Saya
                                </a>
                            </li>
                        <?php elseif ($current_user['role_name'] === 'penyedia'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/profile.php">
                                    <i class="fa-solid fa-user-gear me-2 text-secondary"></i> Profil & Tarif Jasa
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/wallet.php">
                                    <i class="fa-solid fa-wallet me-2 text-warning"></i> Dompet & Saldo Deposit
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/leads.php">
                                    <i class="fa-solid fa-briefcase me-2 text-teal"></i> Pekerjaan Masuk (Leads)
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/provider/portfolio.php">
                                    <i class="fa-solid fa-camera me-2 text-secondary"></i> Portofolio Hasil Kerja
                                </a>
                            </li>
                        <?php elseif ($current_user['role_name'] === 'admin'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/admin/banners.php">
                                    <i class="fa-solid fa-rectangle-ad me-2 text-secondary"></i> Kelola Banner
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/admin/profile.php">
                                    <i class="fa-solid fa-user-shield me-2 text-secondary"></i> Profil Admin
                                </a>
                            </li>
                        <?php endif; ?>

                        <li>
                            <a class="dropdown-item" href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>?text=Halo%20Admin%20Jasa%20Inhu,%20saya%20butuh%20bantuan%20layanan" target="_blank">
                                <i class="fa-solid fa-headset me-2 text-info"></i> Pusat Bantuan CS
                            </a>
                        </li>

                        <li><hr class="dropdown-divider my-1"></li>
                        <!-- Tombol Keluar dari Akun (Logout) -->
                        <li>
                            <a class="dropdown-item text-danger fw-bold py-2.5 d-flex align-items-center" href="<?= BASE_URL ?>/logout.php">
                                <i class="fa-solid fa-arrow-right-from-bracket me-2 text-danger"></i>
                                <span>Keluar dari Akun (Logout)</span>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <!-- Belum Masuk Akun: Tombol Masuk & Daftar Elegan -->
                <button type="button" class="gojek-login-btn" data-bs-toggle="modal" data-bs-target="#loginModal">
                    Masuk
                </button>
                <a href="<?= BASE_URL ?>/register.php" class="gojek-register-btn">
                    Daftar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Baris 2: Search Bar Kapsul Lebar 100% Penuh ala Gojek / Grab -->
    <div class="gojek-header-search-row">
        <form action="<?= BASE_URL ?>/search.php" method="GET" class="gojek-search-capsule">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" name="q" class="search-input" placeholder="Cari jasa: Tukang, Servis AC, Montir..." value="<?= e($header_q) ?>" autocomplete="off">
            <?php if ($header_dist): ?>
                <input type="hidden" name="kecamatan" value="<?= $header_dist ?>">
            <?php endif; ?>
            <button type="submit" class="gojek-search-btn" title="Cari Jasa">
                Cari
            </button>
        </form>
    </div>
</header>

<!-- Modal Pilih Kecamatan (Shopee / Gojek Style Location Picker) -->
<div class="modal fade" id="districtSelectModal" tabindex="-1" aria-labelledby="districtSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center" id="districtSelectModalLabel">
                    <i class="fa-solid fa-location-dot text-teal me-2 fs-5"></i> Pilih Wilayah Kecamatan di Inhu
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-3">
                <div class="list-group list-group-flush rounded-3">
                    <a href="<?= BASE_URL ?>/search.php<?= !empty($header_q) ? '?q=' . urlencode($header_q) : '' ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 border-0 rounded-2 mb-1 <?= empty($header_dist) ? 'bg-primary-subtle text-teal fw-bold' : '' ?>">
                        <span class="d-flex align-items-center">
                            <i class="fa-solid fa-map me-2 <?= empty($header_dist) ? 'text-teal' : 'text-muted' ?>"></i>
                            Semua Wilayah (Seluruh Kab. Inhu)
                        </span>
                        <?php if (empty($header_dist)): ?>
                            <i class="fa-solid fa-check text-teal"></i>
                        <?php endif; ?>
                    </a>
                    <?php foreach ($header_districts as $hd): ?>
                        <a href="<?= BASE_URL ?>/search.php?kecamatan=<?= $hd['id'] ?><?= !empty($header_q) ? '&q=' . urlencode($header_q) : '' ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2.5 px-3 border-0 rounded-2 mb-1 <?= $header_dist === (int)$hd['id'] ? 'bg-primary-subtle text-teal fw-bold' : '' ?>">
                            <span class="d-flex align-items-center">
                                <i class="fa-solid fa-location-dot me-2 <?= $header_dist === (int)$hd['id'] ? 'text-teal' : 'text-muted' ?>"></i>
                                Kecamatan <?= e($hd['name']) ?>
                            </span>
                            <?php if ($header_dist === (int)$hd['id']): ?>
                                <i class="fa-solid fa-check text-teal"></i>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Kategori Jasa Cepat (Trigger dari Kapsul Search) -->
<div class="modal fade" id="headerMobileCatModal" tabindex="-1" aria-labelledby="headerMobileCatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center" id="headerMobileCatModalLabel">
                    <i class="fa-solid fa-shapes text-teal me-2 fs-5"></i> Pilih Kategori Layanan Jasa
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2">
                    <?php foreach ($header_categories as $hcat): ?>
                        <div class="col-6">
                            <a href="<?= BASE_URL ?>/search.php?kategori=<?= $hcat['id'] ?>" class="d-flex align-items-center gap-2 p-2.5 rounded-3 border text-decoration-none text-dark bg-white shadow-sm transition">
                                <div class="rounded-circle bg-teal-subtle text-teal d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                    <i class="fa-solid <?= e($hcat['icon'] ?: 'fa-wrench') ?>"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="small fw-bold text-truncate" style="font-size: 0.8rem;"><?= e($hcat['name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.68rem;">Lihat jasa &rarr;</div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer border-top py-2 bg-light justify-content-center">
                <a href="<?= BASE_URL ?>/#kategori" class="btn btn-sm btn-teal px-4 rounded-pill fw-bold">
                    Lihat Semua di Halaman Utama
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Global Flash Messages -->
<div class="container mt-3">
    <?php render_flash(); ?>
</div>

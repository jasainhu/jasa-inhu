<?php
/**
 * Admin Layout Header: JASA INHU
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Wajib role Admin
require_role('admin');

$current_user = current_user();
$page_title = $page_title ?? 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | Admin JASA INHU</title>
 
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_url('images/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="48x48" href="<?= asset_url('images/favicon.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset_url('images/apple-touch-icon.png') ?>">

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
<body class="bg-light">

<!-- Admin Topbar -->
<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-2 shadow-sm" style="background-color: #0f172a !important;">
    <div class="container-fluid">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6 fw-bold text-white d-flex align-items-center gap-2" href="<?= BASE_URL ?>/admin/index.php">
            <i class="fa-solid fa-shield-halved text-teal" style="color: #2dd4bf;"></i>
            <span>ADMIN JASA INHU</span>
        </a>
        <div class="d-flex align-items-center gap-3 pe-3">
            <a href="<?= BASE_URL ?>/" class="btn btn-sm btn-outline-light" target="_blank">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Lihat Web
            </a>
            <a href="<?= BASE_URL ?>/admin/profile.php" class="btn btn-sm btn-outline-light text-white text-decoration-none">
                <i class="fa-solid fa-user-pen me-1"></i> Edit Akun Admin
            </a>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-danger" title="Keluar">
                <i class="fa-solid fa-power-off"></i>
            </a>
        </div>
    </div>
</header>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block dashboard-sidebar collapse show" id="sidebarMenu">
            <div class="position-sticky pt-2">
                <div class="px-3 py-2 mb-3 bg-light rounded-3 border">
                    <div class="small text-muted">Akses Wilayah:</div>
                    <div class="fw-bold small text-dark"><i class="fa-solid fa-map-pin text-danger me-1"></i> Kab. Indragiri Hulu</div>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="sidebar-link <?= ($admin_active ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/index.php">
                            <i class="fa-solid fa-gauge"></i> Ringkasan Utama
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link <?= ($admin_active ?? '') === 'categories' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/categories.php">
                            <i class="fa-solid fa-shapes"></i> Kategori Jasa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link <?= ($admin_active ?? '') === 'banners' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/banners.php">
                            <i class="fa-solid fa-rectangle-ad"></i> Banner & Iklan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link <?= ($admin_active ?? '') === 'users' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/users.php">
                            <i class="fa-solid fa-users"></i> Kelola Pengguna
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link <?= ($admin_active ?? '') === 'wallet' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/wallet.php">
                            <i class="fa-solid fa-wallet text-warning"></i> Saldo & Biaya Kontak
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link <?= ($admin_active ?? '') === 'settings' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/settings.php">
                            <i class="fa-solid fa-sliders text-teal"></i> Pengaturan Sistem
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link <?= ($admin_active ?? '') === 'profile' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/profile.php">
                            <i class="fa-solid fa-user-gear"></i> Edit Akun Admin
                        </a>
                    </li>
                </ul>

                <hr class="my-4">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="sidebar-link text-danger" href="<?= BASE_URL ?>/logout.php">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content Wrapper -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <?php render_flash(); ?>

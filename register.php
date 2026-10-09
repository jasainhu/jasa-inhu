<?php
/**
 * Halaman Registrasi JASA INHU
 */

$page_title = 'Daftar Akun Baru';

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$redirect_to = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');

// Jika sudah login, redirect ke halaman tujuan
if (is_logged_in()) {
    $current = current_user();
    if ($current) {
        redirect(get_post_login_url($current['role_name'], $redirect_to));
    }
}

$error_message = '';
$prefill_role = ($_GET['role'] ?? 'pengguna') === 'penyedia' ? 'penyedia' : 'pengguna';

$districts = get_all_districts();
$categories = get_active_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error_message = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $result = register_user($_POST);
        if ($result['success']) {
            set_flash('success', $result['message']);
            redirect($result['redirect']);
        } else {
            $error_message = $result['message'];
            $prefill_role = $_POST['role_type'] ?? 'pengguna';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 620px;">
        <div class="auth-card-header">
            <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-3" style="background: var(--primary-subtle); color: var(--primary);">
                <i class="fa-solid fa-user-plus fs-3"></i>
            </div>
            <h3 class="fw-bold mb-1">Daftar Akun JASA INHU</h3>
            <p class="text-muted small">Pilih peran akun Anda di Kabupaten Indragiri Hulu</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <div class="small"><?= e($error_message) ?></div>
            </div>
        <?php endif; ?>

        <!-- Role Pill Selector -->
        <div class="role-pill-selector">
            <button type="button" class="role-pill-btn <?= $prefill_role === 'pengguna' ? 'active' : '' ?>" data-role="pengguna">
                <i class="fa-solid fa-user me-1"></i> Saya Butuh Jasa (Masyarakat)
            </button>
            <button type="button" class="role-pill-btn <?= $prefill_role === 'penyedia' ? 'active' : '' ?>" data-role="penyedia">
                <i class="fa-solid fa-briefcase me-1"></i> Saya Buka Jasa (Penyedia)
            </button>
        </div>

        <form action="<?= BASE_URL ?>/register.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="role_type" id="role_type_input" value="<?= e($prefill_role) ?>">
            <?php if (!empty($redirect_to)): ?>
                <input type="hidden" name="redirect" value="<?= e($redirect_to) ?>">
            <?php endif; ?>

            <div class="row g-3">
                <!-- Kolom Khusus Penyedia Jasa -->
                <div id="provider_fields" class="col-12" style="display: <?= $prefill_role === 'penyedia' ? 'block' : 'none' ?>;">
                    <div class="alert alert-warning border-0 p-3 rounded-3 mb-3 d-flex align-items-center gap-2.5 shadow-xs">
                        <i class="fa-solid fa-gift text-warning fs-3"></i>
                        <div>
                            <div class="fw-bold text-dark small">🎉 PROMO KHUSUS PELUNCURAN INHU:</div>
                            <div class="text-muted" style="font-size: 0.78rem;">
                                Daftar sekarang <strong>GRATIS 100%</strong> dan dapatkan modal saldo <strong>15 Pesanan Pertama Bebas Biaya</strong>! Tidak perlu bayar atau deposit apapun di awal.
                            </div>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 border mb-2">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-1 mb-2">
                            <div class="fw-bold small text-teal">
                                <i class="fa-solid fa-briefcase me-1"></i> Informasi Jasa & Keahlian Anda
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.7rem;">
                                <i class="fa-solid fa-circle-check me-1"></i> Bebas Syarat Toko Fisik
                            </span>
                        </div>
                        <p class="text-muted small mb-2.5" style="font-size: 0.76rem;">
                            Bagi pekerja mandiri (tukang batu/kayu, buruh, ART, teknisi panggilan, dll) Anda tidak perlu punya toko fisik atau bengkel.
                        </p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Nama Usaha / Merek Jasa Mandiri</label>
                                <input type="text" name="business_name" class="form-control form-control-sm" placeholder="Contoh: Pak Herman Tukang Bangunan / Bengkel Berkah" value="<?= e($_POST['business_name'] ?? '') ?>" data-required>
                                <div class="form-text small" style="font-size: 0.72rem;">
                                    Tulis nama usaha, atau nama panggilan & keahlian Anda.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Kategori Bidang Keahlian</label>
                                <select name="category_id" class="form-select form-select-sm" data-required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small" style="font-size: 0.72rem;">
                                    Pilih bidang jasa yang paling sesuai dengan keahlian Anda.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informasi Akun Dasar -->
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Nama Lengkap (Sesuai KTP) <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Nama Anda" value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Nomor WhatsApp / HP <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" placeholder="0812xxxxxxxx" value="<?= e($_POST['phone'] ?? '') ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="nama@email.com" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>

                <!-- Wilayah Domisili di Inhu -->
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Kecamatan (Kab. Inhu)</label>
                    <select name="district_id" id="district_select" class="form-select">
                        <option value="">-- Pilih Kecamatan --</option>
                        <?php foreach ($districts as $dist): ?>
                            <option value="<?= $dist['id'] ?>" <?= (isset($_POST['district_id']) && $_POST['district_id'] == $dist['id']) ? 'selected' : '' ?>>
                                Kec. <?= e($dist['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Desa / Kelurahan</label>
                    <select name="village_id" id="village_select" class="form-select" disabled>
                        <option value="">-- Pilih Kecamatan Dahulu --</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Alamat Domisili / Patokan Tempat Tinggal</label>
                    <input type="text" name="address" class="form-control" placeholder="Nama jalan, RT/RW, nomor rumah, atau patokan..." value="<?= e($_POST['address'] ?? '') ?>">
                    <div class="form-text small" style="font-size: 0.72rem;">
                        Bagi pekerja mandiri tanpa toko fisik, cukup masukkan alamat rumah atau patokan tempat tinggal di Inhu.
                    </div>
                </div>

                <!-- Kata Sandi -->
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Kata Sandi <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Konfirmasi Kata Sandi <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" placeholder="Ulangi kata sandi" required>
                </div>
            </div>

            <div class="mt-3 mb-2 small text-muted text-center" style="font-size: 0.78rem;">
                Dengan mendaftar, Anda menyatakan menyetujui <a href="<?= BASE_URL ?>/terms.php" target="_blank" class="text-teal fw-semibold">Syarat & Ketentuan Layanan</a> serta pedoman perlindungan platform JASA INHU.
            </div>

            <button type="submit" class="btn btn-primary-custom w-100 py-2 mt-2">
                <i class="fa-solid fa-user-plus me-1"></i> Selesaikan Pendaftaran
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top small text-muted">
            Sudah punya akun? <a href="<?= BASE_URL ?>/login.php<?= !empty($redirect_to) ? '?redirect=' . urlencode($redirect_to) : '' ?>" class="fw-bold text-teal">Masuk di sini</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

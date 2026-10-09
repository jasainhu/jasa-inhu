<?php
/**
 * Admin: Edit Akun & Profil Admin
 */

$page_title = 'Pengaturan Akun Admin';
$admin_active = 'profile';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();
$user_id = (int)$_SESSION['user_id'];
$error = '';

// Ambil data admin terkini
$stmtAdmin = $db->prepare("
    SELECT u.id, u.name, u.email, u.phone, p.address, p.district_id, p.village_id, p.bio,
           d.name as district_name, v.name as village_name
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    LEFT JOIN districts d ON p.district_id = d.id
    LEFT JOIN villages v ON p.village_id = v.id
    WHERE u.id = ?
    LIMIT 1
");
$stmtAdmin->execute([$user_id]);
$admin = $stmtAdmin->fetch();

if (!$admin) {
    set_flash('danger', 'Akun tidak ditemukan.');
    redirect('/admin/index.php');
}

// Handle submit update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
        $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
        $bio = trim($_POST['bio'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($name) || empty($email) || empty($phone)) {
            $error = 'Nama lengkap, email, dan nomor handphone wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format alamat email tidak valid.';
        } else {
            // Cek duplikasi email atau nomor telepon pada user lain
            $stmtCheck = $db->prepare("SELECT id, email, phone FROM users WHERE (email = ? OR phone = ?) AND id != ? LIMIT 1");
            $stmtCheck->execute([$email, $phone, $user_id]);
            $conflict = $stmtCheck->fetch();

            if ($conflict) {
                if ($conflict['email'] === $email) {
                    $error = 'Email ini sudah digunakan oleh akun lain.';
                } else {
                    $error = 'Nomor handphone ini sudah digunakan oleh akun lain.';
                }
            } elseif (!empty($password) && strlen($password) < 6) {
                $error = 'Kata sandi baru minimal 6 karakter.';
            } elseif (!empty($password) && $password !== $password_confirm) {
                $error = 'Konfirmasi kata sandi baru tidak cocok.';
            } else {
                try {
                    $db->beginTransaction();

                    // Update users table
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmtUpdateU = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
                        $stmtUpdateU->execute([$name, $email, $phone, $hash, $user_id]);
                    } else {
                        $stmtUpdateU = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                        $stmtUpdateU->execute([$name, $email, $phone, $user_id]);
                    }

                    // Update / Insert profiles table
                    $stmtCheckP = $db->prepare("SELECT id FROM profiles WHERE user_id = ?");
                    $stmtCheckP->execute([$user_id]);
                    if ($stmtCheckP->fetch()) {
                        $stmtUpdateP = $db->prepare("
                            UPDATE profiles 
                            SET address = ?, district_id = ?, village_id = ?, bio = ?, updated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $stmtUpdateP->execute([$address, $district_id, $village_id, $bio, $user_id]);
                    } else {
                        $stmtInsertP = $db->prepare("
                            INSERT INTO profiles (user_id, address, district_id, village_id, bio, created_at)
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $stmtInsertP->execute([$user_id, $address, $district_id, $village_id, $bio]);
                    }

                    $db->commit();

                    // Perbarui sesi
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;

                    set_flash('success', 'Akun admin dan nomor handphone berhasil diperbarui!');
                    redirect('/admin/profile.php');
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Gagal menyimpan perubahan: ' . $e->getMessage();
                }
            }
        }
    }
}

$districts = get_all_districts();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">Pengaturan Akun & Kontak Admin</h1>
        <p class="text-muted small mb-0">Perbarui nama, nomor handphone/WhatsApp, dan informasi akun pengelola JASA INHU</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-4">
        <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card border shadow-sm p-4 bg-white mb-4" style="border-radius: 14px;">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 58px; height: 58px; background: linear-gradient(135deg, #0f172a 0%, var(--primary) 100%); font-size: 1.5rem; font-weight: 700;">
                    <?= strtoupper(substr($admin['name'], 0, 1)) ?>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><?= e($admin['name']) ?></h5>
                    <span class="badge text-bg-danger me-1">Administrator Utama</span>
                    <span class="text-muted small">&bull; ID Pengguna: #<?= $admin['id'] ?></span>
                </div>
            </div>

            <form method="POST" action="<?= BASE_URL ?>/admin/profile.php">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Nama Lengkap Admin <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-user"></i></span>
                            <input type="text" name="name" class="form-control" value="<?= e($admin['name']) ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Nomor Handphone / WhatsApp <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-success"><i class="fa-brands fa-whatsapp"></i></span>
                            <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx" value="<?= e($admin['phone']) ?>" required>
                        </div>
                        <div class="form-text" style="font-size: 0.75rem;">
                            Nomor ini digunakan untuk kontak administratif dan koordinasi layanan di Indragiri Hulu.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Alamat Email Login <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" value="<?= e($admin['email']) ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Kecamatan di Inhu</label>
                        <select name="district_id" id="district_select" class="form-select">
                            <option value="">-- Pilih Kecamatan --</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($admin['district_id'] == $d['id']) ? 'selected' : '' ?>>
                                    Kec. <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Desa / Kelurahan</label>
                        <select name="village_id" id="village_select" class="form-select">
                            <option value="<?= $admin['village_id'] ?? '' ?>">
                                <?= e($admin['village_name'] ?? '-- Pilih Desa / Kelurahan --') ?>
                            </option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Alamat Kantor / Domisili</label>
                        <input type="text" name="address" class="form-control" placeholder="Contoh: Jl. Bupati Tulus No. 1, Rengat" value="<?= e($admin['address'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Catatan / Bio Admin</label>
                        <textarea name="bio" rows="2" class="form-control" placeholder="Deskripsi peran pengelola..."><?= e($admin['bio'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12 pt-3 border-top">
                        <h6 class="fw-bold text-dark mb-2">Ganti Kata Sandi (Opsional)</h6>
                        <p class="text-muted small mb-3">Kosongkan kolom berikut jika tidak ingin mengubah kata sandi.</p>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Kata Sandi Baru</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Konfirmasi Kata Sandi Baru</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password_confirm" class="form-control" placeholder="Ulangi kata sandi baru">
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-2">
                    <button type="submit" class="btn btn-primary-custom px-4 py-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan Akun Admin
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Info Box -->
    <div class="col-lg-4">
        <div class="card border shadow-sm p-3 bg-white mb-3" style="border-radius: 14px;">
            <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-circle-info text-primary me-2"></i> Tips Kontak Admin</h6>
            <p class="text-secondary small mb-2">
                Pastikan nomor handphone yang Anda masukkan adalah nomor WhatsApp yang aktif.
            </p>
            <p class="text-secondary small mb-0">
                Nomor ini mempermudah masyarakat dan penyedia jasa lokal di Rengat, Belilas, Air Molek, dan kecamatan lainnya untuk menghubungi admin ketika memerlukan verifikasi akun atau bantuan teknis.
            </p>
        </div>

        <div class="card border shadow-sm p-3 bg-light" style="border-radius: 14px;">
            <div class="small fw-bold text-muted mb-2">RINGKASAN AKUN SAAT INI</div>
            <div class="d-flex justify-content-between small py-1 border-bottom">
                <span class="text-muted">Nama:</span>
                <span class="fw-semibold text-dark"><?= e($admin['name']) ?></span>
            </div>
            <div class="d-flex justify-content-between small py-1 border-bottom">
                <span class="text-muted">No. WhatsApp:</span>
                <span class="fw-semibold text-teal"><?= e($admin['phone']) ?></span>
            </div>
            <div class="d-flex justify-content-between small py-1 border-bottom">
                <span class="text-muted">Email:</span>
                <span class="fw-semibold text-dark"><?= e($admin['email']) ?></span>
            </div>
            <div class="d-flex justify-content-between small py-1">
                <span class="text-muted">Wilayah:</span>
                <span class="fw-semibold text-dark"><?= e($admin['district_name'] ?: 'Kab. Inhu') ?></span>
            </div>
        </div>

        <!-- Kartu Keluar Akun Admin -->
        <div class="card border border-danger-subtle shadow-sm p-3 bg-white mt-3" style="border-radius: 14px;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fa-solid fa-arrow-right-from-bracket text-danger fs-5"></i>
                <h6 class="fw-bold text-dark mb-0">Keluar Sesi Admin</h6>
            </div>
            <p class="text-muted small mb-3" style="font-size: 0.75rem;">
                Keluar dari sistem dashboard administrasi di perangkat ini.
            </p>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm w-100 fw-bold py-2 d-flex align-items-center justify-content-center gap-1.5">
                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i>
                <span>Keluar dari Akun (Logout)</span>
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

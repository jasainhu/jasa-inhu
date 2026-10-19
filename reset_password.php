<?php
/**
 * Buat Kata Sandi Baru - JASA INHU
 * Memvalidasi token dari Gmail dan memperbarui kata sandi akun pengguna
 */

$page_title = 'Atur Ulang Kata Sandi - JASA INHU';
$hide_mobile_search = true;

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Jika pengguna sudah login, arahkan ke profil/dashboard
if (is_logged_in()) {
    redirect(current_user()['role'] === 'penyedia' ? '/provider/index.php' : '/user/profile.php');
}

$db = get_db();
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$is_valid_token = false;
$reset_record = null;
$error_message = '';

// Validasi format token (64 hex characters)
if (!empty($token) && preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $stmtCheck = $db->prepare("
        SELECT pr.*, u.name as user_name, u.email as user_email, u.phone as user_phone
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? 
          AND pr.used_at IS NULL 
          AND pr.expires_at > NOW() 
          AND u.is_active = 1
        LIMIT 1
    ");
    $stmtCheck->execute([$token]);
    $reset_record = $stmtCheck->fetch();

    if ($reset_record) {
        $is_valid_token = true;
    }
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid_token) {
    if (!validate_csrf()) {
        $error_message = 'Sesi keamanan berakhir. Silakan ulangi.';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $error_message = 'Semua kolom kata sandi wajib diisi.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Kata sandi baru minimal harus 6 karakter.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'Konfirmasi kata sandi tidak cocok. Pastikan pengetikan sama persis.';
        } else {
            try {
                // 1. Enkripsi kata sandi baru
                $password_hash = password_hash($new_password, PASSWORD_BCRYPT);

                // 2. Perbarui kata sandi di tabel users
                $stmtUpdate = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmtUpdate->execute([$password_hash, $reset_record['user_id']]);

                // 3. Tandai token ini telah digunakan
                $stmtUsed = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
                $stmtUsed->execute([$reset_record['id']]);

                // 4. Bersihkan token lama lainnya untuk user ini
                $stmtClean = $db->prepare("DELETE FROM password_resets WHERE user_id = ? AND id != ?");
                $stmtClean->execute([$reset_record['user_id'], $reset_record['id']]);

                // 5. Buat notifikasi keamanan di dalam aplikasi jika ada tabel notifications
                try {
                    $stmtNotif = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, link, is_read, created_at)
                        VALUES (?, 'Kata Sandi Berhasil Diperbarui', 'Kata sandi akun Anda baru saja berhasil diatur ulang melalui email.', '/user/profile.php', 0, NOW())
                    ");
                    $stmtNotif->execute([$reset_record['user_id']]);
                } catch (Exception $e) {
                    // Ignore jika notifikasi gagal
                }

                // 6. Alihkan ke login dengan pesan sukses
                set_flash('success', 'Kata sandi Anda berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.');
                redirect('/login.php');

            } catch (Exception $e) {
                error_log("Error reset_password submit: " . $e->getMessage());
                $error_message = 'Terjadi kesalahan sistem saat menyimpan kata sandi. Silakan coba lagi.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper" style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2.5rem 1rem;">
    <div class="auth-card bg-white shadow-lg border-0 position-relative" style="max-width: 440px; width: 100%; border-radius: 20px; padding: 28px 32px;">
        <!-- Close button linking back to login -->
        <a href="<?= BASE_URL ?>/login.php" class="btn-close position-absolute top-0 end-0 m-3 shadow-none text-decoration-none" aria-label="Tutup" style="z-index: 10;"></a>

        <?php if ($is_valid_token): ?>
            <!-- Form Buat Kata Sandi Baru -->
            <div class="text-center pt-2 pb-1 mb-3">
                <div class="d-inline-block position-relative mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto shadow-xs" style="width: 62px; height: 62px; background: #ccfbf1; color: #0d9488;">
                        <i class="fa-solid fa-lock-open fs-3"></i>
                    </div>
                </div>
                <h4 class="fw-bold text-dark mb-1" style="letter-spacing: -0.3px;">Buat Kata Sandi Baru</h4>
                <p class="text-muted small mb-0">Akun: <strong><?= e($reset_record['user_email']) ?></strong></p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3 small rounded-3" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                    <div><?= e($error_message) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/reset_password.php?token=<?= urlencode($token) ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <!-- Input Kata Sandi Baru -->
                <div class="form-floating mb-2.5 position-relative fb-floating-group">
                    <input type="password" name="new_password" id="new_password" class="form-control rounded-3 pe-5" 
                           placeholder="Kata sandi baru" required minlength="6" autofocus>
                    <label for="new_password" class="text-secondary">Kata Sandi Baru (Min. 6 Karakter)</label>
                    <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" 
                            onclick="togglePasswordVisibility('new_password', 'eyeIcon1')" style="font-size: 0.95rem; z-index: 5;" tabindex="-1">
                        <i class="fa-solid fa-eye" id="eyeIcon1"></i>
                    </button>
                </div>

                <!-- Input Konfirmasi Kata Sandi -->
                <div class="form-floating mb-3 position-relative fb-floating-group">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control rounded-3 pe-5" 
                           placeholder="Ulangi kata sandi baru" required minlength="6">
                    <label for="confirm_password" class="text-secondary">Ulangi Kata Sandi Baru</label>
                    <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" 
                            onclick="togglePasswordVisibility('confirm_password', 'eyeIcon2')" style="font-size: 0.95rem; z-index: 5;" tabindex="-1">
                        <i class="fa-solid fa-eye" id="eyeIcon2"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-1.5 text-muted small mb-3" style="font-size: 0.78rem;">
                    <i class="fa-solid fa-shield-check text-success"></i>
                    <span>Kata sandi akan otomatis dienkripsi dengan standar keamanan tinggi.</span>
                </div>

                <button type="submit" class="btn w-100 text-white fw-bold shadow-sm py-2.5 rounded-pill mb-2" style="background: #0d9488; border: none; font-size: 1rem;">
                    <i class="fa-solid fa-circle-check me-1.5"></i> Simpan Kata Sandi Baru
                </button>
            </form>

        <?php else: ?>
            <!-- Tampilan Token Tidak Valid atau Kedaluwarsa -->
            <div class="text-center py-3">
                <div class="d-inline-block position-relative mb-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto shadow-xs" style="width: 64px; height: 64px; background: #fee2e2; color: #dc2626;">
                        <i class="fa-solid fa-clock-rotate-left fs-3"></i>
                    </div>
                </div>
                <h4 class="fw-bold text-dark mb-2">Tautan Kedaluwarsa atau Tidak Valid</h4>
                <p class="text-muted small mb-4" style="font-size: 0.85rem; line-height: 1.55;">
                    Tautan pemulihan kata sandi ini sudah pernah digunakan atau telah melewati batas waktu <strong>30 menit</strong> demi menjaga keamanan akun Anda.
                </p>

                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>/forgot_password.php" class="btn btn-primary-custom fw-bold py-2.5 rounded-pill shadow-xs">
                        <i class="fa-solid fa-paper-plane me-1.5"></i> Minta Tautan Baru via Gmail
                    </a>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-secondary py-2 rounded-pill fw-semibold" style="font-size: 0.9rem;">
                        Kembali ke Halaman Masuk
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

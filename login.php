<?php
/**
 * Halaman Masuk (Login) JASA INHU
 */

$page_title = 'Masuk ke Akun Anda';

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$redirect_to = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');

// Jika sudah login, langsung arahkan ke tujuan yang sesuai
if (is_logged_in()) {
    $current = current_user();
    if ($current) {
        redirect(get_post_login_url($current['role_name'], $redirect_to));
    }
}

$error_message = '';
$login_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = !empty($_POST['is_ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (!validate_csrf()) {
        $error_message = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }
    } else {
        $login_val = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = login_user($login_val, $password, $redirect_to);
        if ($result['success']) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'redirect' => $result['redirect']
                ]);
                exit;
            }
            set_flash('success', $result['message']);
            redirect($result['redirect']);
        } else {
            $error_message = $result['message'];
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error_message]);
                exit;
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper" style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2.5rem 1rem;">
    <div class="auth-card bg-white shadow-lg border-0 position-relative" style="max-width: 440px; width: 100%; border-radius: 20px; padding: 28px 32px;">
        <!-- Close button linking back to home -->
        <a href="<?= BASE_URL ?>/" class="btn-close position-absolute top-0 end-0 m-3 shadow-none text-decoration-none" aria-label="Tutup" style="z-index: 10;"></a>

        <!-- Header Facebook Style: Logo & Judul Tengah -->
        <div class="text-center pt-2 pb-1 mb-3">
            <div class="d-inline-block position-relative mb-2">
                <img src="<?= asset_url('images/icon-192.png') ?>" alt="JASA INHU" width="62" height="62" class="rounded-circle shadow-xs border p-1 bg-white">
            </div>
            <h4 class="fw-bold text-dark mb-1" style="letter-spacing: -0.3px;">Masuk ke JASA INHU</h4>
            <p class="text-muted small mb-0">Layanan Jasa & Tukang Kab. Indragiri Hulu</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3 small rounded-3" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <div><?= e($error_message) ?></div>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/login.php" method="POST">
            <?= csrf_field() ?>
            <?php if (!empty($redirect_to)): ?>
                <input type="hidden" name="redirect" value="<?= e($redirect_to) ?>">
            <?php endif; ?>

            <!-- Floating Label 1: Email atau nomor ponsel -->
            <div class="form-floating mb-2.5 fb-floating-group">
                <input type="text" name="login" id="login_input" class="form-control rounded-3" placeholder="Email atau nomor ponsel" value="<?= e($login_val) ?>" required autofocus>
                <label for="login_input" class="text-secondary">Email atau nomor ponsel</label>
            </div>

            <!-- Floating Label 2: Kata sandi dengan tombol mata -->
            <div class="form-floating mb-3 position-relative fb-floating-group">
                <input type="password" name="password" id="password_input" class="form-control rounded-3 pe-5" placeholder="Kata sandi" required>
                <label for="password_input" class="text-secondary">Kata sandi</label>
                <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" onclick="toggleLoginPagePassword()" style="font-size: 0.95rem; z-index: 5;" tabindex="-1" aria-label="Lihat kata sandi">
                    <i class="fa-solid fa-eye" id="loginEyeIcon"></i>
                </button>
            </div>

            <!-- Tombol Masuk Utama (Primary) -->
            <button type="submit" class="btn w-100 text-white fw-bold shadow-sm py-2.5 rounded-pill" style="background: #0d9488; border: none; font-size: 1rem;">
                Masuk
            </button>
        </form>

        <!-- Lupa Kata Sandi (Centered Link gaya Facebook) -->
        <div class="text-center my-3">
            <a href="https://wa.me/6281234567890?text=Halo%20Admin%20JASA%20INHU,%20saya%20lupa%20kata%20sandi%20akun%20saya" target="_blank" class="small text-decoration-none text-dark fw-semibold" style="font-size: 0.88rem;">
                Lupa kata sandi?
            </a>
        </div>

        <!-- Tombol Buat Akun Baru (Daftar) Gaya Facebook -->
        <div class="pt-1">
            <a href="<?= BASE_URL ?>/register.php<?= !empty($redirect_to) ? '?redirect=' . urlencode($redirect_to) : '' ?>" class="btn w-100 py-2 rounded-pill fw-bold text-decoration-none d-flex align-items-center justify-content-center" style="border: 1.5px solid #0d9488; color: #0d9488; font-size: 0.92rem; background: transparent; transition: all 0.2s ease;">
                Buat akun baru
            </a>
        </div>

        <!-- Footer Branding (Gaya Meta) -->
        <div class="text-center mt-3 pt-1 text-muted d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.76rem;">
            <i class="fa-solid fa-shield-halved" style="color: #0d9488;"></i>
            <span>JASA INHU &bull; Terpercaya & Aman</span>
        </div>
    </div>
</div>

<script>

function toggleLoginPagePassword() {
    const passInput = document.getElementById('password_input');
    const eyeIcon = document.getElementById('loginEyeIcon');
    if (passInput) {
        if (passInput.type === 'password') {
            passInput.type = 'text';
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            passInput.type = 'password';
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    }
}
</script>

<style>
.fb-floating-group.form-floating > .form-control {
    height: 54px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    padding-top: 1.5rem;
    padding-bottom: 0.5rem;
    font-size: 0.95rem;
    color: #1e293b;
    background-color: #ffffff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.fb-floating-group.form-floating > .form-control:focus {
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
}
.fb-floating-group.form-floating > label {
    padding: 0.95rem 1rem;
    color: #64748b;
    font-size: 0.92rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.fb-floating-group.form-floating > .form-control:focus ~ label {
    color: #0d9488;
    font-weight: 500;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

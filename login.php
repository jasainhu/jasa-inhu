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

        <!-- Header Tokopedia: Masuk di Kiri, Daftar di Kanan -->
        <div class="d-flex align-items-center justify-content-between pt-1 pb-1 mb-3 pe-4">
            <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.85rem; letter-spacing: -0.5px;">Masuk</h3>
            <a href="<?= BASE_URL ?>/register.php<?= !empty($redirect_to) ? '?redirect=' . urlencode($redirect_to) : '' ?>" class="fw-bold text-decoration-none" style="font-size: 0.95rem; color: #00AA5B;">
                Daftar
            </a>
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

            <div class="mb-3">
                <label for="login_input" class="form-label fw-semibold small text-muted mb-1">Nomor HP atau Email</label>
                <input type="text" name="login" id="login_input" class="form-control py-2 px-3 rounded-3 border" placeholder="nama@email.com atau 0812..." value="<?= e($login_val) ?>" required autofocus>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password_input" class="form-label fw-semibold small text-muted mb-0">Kata Sandi</label>
                    <a href="https://wa.me/6281234567890?text=Halo%20Admin%20JASA%20INHU,%20saya%20lupa%20kata%20sandi%20akun%20saya" target="_blank" class="small text-decoration-none fw-semibold" style="color: #00AA5B;">
                        Lupa kata sandi?
                    </a>
                </div>
                <div class="position-relative">
                    <input type="password" name="password" id="password_input" class="form-control py-2 px-3 rounded-3 border pe-5" placeholder="Masukkan kata sandi..." required>
                    <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" onclick="toggleLoginPagePassword()" style="font-size: 0.9rem;" tabindex="-1">
                        <i class="fa-solid fa-eye" id="loginEyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn w-100 text-white fw-bold shadow-sm" style="background: #00AA5B; border: none; border-radius: 10px; padding: 11px 16px; font-size: 0.95rem;">
                Masuk
            </button>
        </form>

        <!-- Bantuan Layanan (Tokopedia Care Style) -->
        <div class="text-center mt-3 pt-2 text-muted" style="font-size: 0.82rem;">
            Butuh bantuan? <a href="https://wa.me/6281234567890?text=Halo%20Admin%20JASA%20INHU,%20saya%20butuh%20bantuan" target="_blank" class="fw-bold text-decoration-none" style="color: #00AA5B;">Hubungi CS Jasa Inhu</a>
        </div>

        <!-- Akun Demo Cepat (Quick Fill) -->
        <div class="mt-4 p-3 bg-light rounded-3 border">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-bold small text-dark" style="font-size: 0.75rem;"><i class="fa-solid fa-key text-warning me-1"></i> Uji Coba Cepat (Akun Demo 1-Klik):</span>
            </div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary text-start py-1 px-2" style="font-size: 0.8rem;" onclick="fillDemoAccount('admin@jasainhu.id', 'password123')">
                    <span class="badge text-bg-danger me-1">Admin</span> admin@jasainhu.id
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start py-1 px-2" style="font-size: 0.8rem;" onclick="fillDemoAccount('budi@gmail.com', 'password123')">
                    <span class="badge text-bg-primary me-1">Pengguna</span> budi@gmail.com (Warga Pematang Reba)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary text-start py-1 px-2" style="font-size: 0.8rem;" onclick="fillDemoAccount('ahmad.servis@jasainhu.id', 'password123')">
                    <span class="badge text-bg-success me-1">Penyedia</span> ahmad.servis@jasainhu.id (Bengkel Motor Rengat)
                </button>
            </div>
            <div class="text-muted text-center mt-2" style="font-size: 0.72rem;">
                Semua akun demo menggunakan sandi: <code>password123</code>
            </div>
        </div>
    </div>
</div>

<script>
function fillDemoAccount(email, password) {
    const loginInput = document.getElementById('login_input');
    const passInput = document.getElementById('password_input');
    if (loginInput && passInput) {
        loginInput.value = email;
        passInput.value = password;
        loginInput.focus();
    }
}

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>

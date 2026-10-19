<?php
/**
 * Lupa Kata Sandi - JASA INHU
 * Pengguna dapat memulihkan & mengatur ulang kata sandi sendiri melalui tautan aman ke Gmail
 */

$page_title = 'Lupa Kata Sandi - JASA INHU';
$hide_mobile_search = true;

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// Jika pengguna sudah login, arahkan ke dashboard/halaman utama
if (is_logged_in()) {
    redirect(current_user()['role'] === 'penyedia' ? '/provider/index.php' : '/user/profile.php');
}

$error_message = '';
$success_message = '';
$submitted_email = '';
$email_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error_message = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $submitted_email = $email;

        // Rate limiting sederhana: maksimal 4 kali request per 10 menit per IP
        $client_ip = get_client_ip();
        $rate_key = 'rate_pwd_reset_' . md5($client_ip);
        $attempts = (int)($_SESSION[$rate_key] ?? 0);
        $last_attempt = (int)($_SESSION[$rate_key . '_time'] ?? 0);

        if ($attempts >= 4 && (time() - $last_attempt) < 600) {
            $error_message = 'Terlalu banyak permintaan reset kata sandi. Mohon tunggu beberapa menit sebelum mencoba kembali.';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Masukkan format alamat email yang valid.';
        } else {
            $_SESSION[$rate_key] = $attempts + 1;
            $_SESSION[$rate_key . '_time'] = time();

            try {
                $db = get_db();

                // Cari user berdasarkan email
                $stmtUser = $db->prepare("SELECT id, name, email, is_active FROM users WHERE email = ? LIMIT 1");
                $stmtUser->execute([$email]);
                $user = $stmtUser->fetch();

                if ($user && (int)$user['is_active'] === 1) {
                    // Buat token kriptografi acak sepanjang 64 karakter
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', time() + (30 * 60)); // Berlaku 30 menit

                    // Hapus token reset sebelumnya yang belum digunakan untuk user ini
                    $stmtClean = $db->prepare("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL");
                    $stmtClean->execute([$user['id']]);

                    // Simpan token baru ke database
                    $stmtIns = $db->prepare("
                        INSERT INTO password_resets (user_id, email, token, expires_at, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmtIns->execute([$user['id'], $user['email'], $token, $expires_at]);

                    // Siapkan tautan pemulihan
                    $resetLink = BASE_URL . "/reset_password.php?token=" . urlencode($token);
                    $subject = "Atur Ulang Kata Sandi Akun JASA INHU";
                    $htmlBody = render_password_reset_email_html($user['name'], $resetLink, 30);
                    $plainBody = "Halo " . $user['name'] . ",\n\n"
                               . "Kami menerima permintaan untuk mengatur ulang kata sandi akun JASA INHU Anda.\n\n"
                               . "Silakan klik atau buka tautan berikut untuk membuat kata sandi baru:\n"
                               . $resetLink . "\n\n"
                               . "Tautan ini hanya berlaku selama 30 menit.\n\n"
                               . "Jika Anda tidak pernah meminta reset kata sandi, abaikan email ini.\n\n"
                               . "Salam hangat,\nTim JASA INHU";

                    // Kirimkan email resmi via Gmail SMTP / Webhook
                    send_system_email($user['email'], $subject, $htmlBody, $plainBody, $user['name']);
                }

                // Tampilkan pesan konfirmasi (selalu ramah & aman dari enumerasi)
                $email_sent = true;
                $success_message = "Tautan pemulihan kata sandi telah dikirim ke <strong>" . e($email) . "</strong>. Silakan periksa kotak masuk (Inbox) atau folder Spam di Gmail Anda.";

            } catch (Exception $e) {
                error_log("Error forgot_password: " . $e->getMessage());
                $error_message = "Terjadi kendala teknis saat memproses permintaan. Silakan hubungi Admin jika kendala berlanjut.";
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

        <!-- Header -->
        <div class="text-center pt-2 pb-1 mb-3">
            <div class="d-inline-block position-relative mb-2">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto shadow-xs" style="width: 62px; height: 62px; background: #ccfbf1; color: #0d9488;">
                    <i class="fa-solid fa-key fs-3"></i>
                </div>
            </div>
            <h4 class="fw-bold text-dark mb-1" style="letter-spacing: -0.3px;">Lupa Kata Sandi?</h4>
            <p class="text-muted small mb-0">Atur ulang kata sandi akun Anda melalui Gmail</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3 small rounded-3" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                <div><?= $error_message ?></div>
            </div>
        <?php endif; ?>

        <?php if ($email_sent): ?>
            <!-- Tampilan Berhasil Kirim Email -->
            <div class="text-center py-2 mb-3">
                <div class="alert alert-success text-start py-3 px-3 rounded-3 mb-3" style="background: #f0fdf4; border-color: #bbf7d0; color: #166534; font-size: 0.88rem; line-height: 1.55;">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-circle-check text-success fs-5 mt-0.5"></i>
                        <div><?= $success_message ?></div>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    <a href="https://mail.google.com/" target="_blank" rel="noopener" class="btn btn-primary-custom fw-bold py-2.5 rounded-pill shadow-xs d-flex align-items-center justify-content-center gap-2">
                        <i class="fa-brands fa-google"></i> Buka Aplikasi Gmail ↗
                    </a>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-secondary py-2 rounded-pill fw-semibold" style="font-size: 0.9rem;">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Halaman Masuk
                    </a>
                </div>

                <div class="bg-light rounded-3 p-2.5 text-muted small" style="font-size: 0.76rem;">
                    <i class="fa-solid fa-circle-info me-1 text-teal"></i>
                    Belum menerima email? Periksa folder <strong>Spam / Promosi</strong> di Gmail Anda, atau tunggu 1-2 menit.
                </div>
            </div>
        <?php else: ?>
            <!-- Form Input Email -->
            <p class="text-muted small mb-3" style="font-size: 0.84rem; line-height: 1.5;">
                Masukkan alamat email yang terdaftar pada akun JASA INHU Anda. Kami akan mengirimkan tautan aman untuk membuat kata sandi baru.
            </p>

            <form action="<?= BASE_URL ?>/forgot_password.php" method="POST">
                <?= csrf_field() ?>

                <div class="form-floating mb-3 fb-floating-group">
                    <input type="email" name="email" id="email_input" class="form-control rounded-3" 
                           placeholder="nama@gmail.com" value="<?= e($submitted_email) ?>" required autofocus>
                    <label for="email_input" class="text-secondary">Alamat Gmail / Email Terdaftar</label>
                </div>

                <button type="submit" class="btn w-100 text-white fw-bold shadow-sm py-2.5 rounded-pill mb-3" style="background: #0d9488; border: none; font-size: 1rem;">
                    <i class="fa-solid fa-paper-plane me-1.5"></i> Kirim Tautan Pemulihan
                </button>
            </form>

            <div class="text-center pt-1 mb-2">
                <a href="<?= BASE_URL ?>/login.php" class="text-decoration-none text-muted small fw-semibold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Ingat kata sandi? Masuk di sini
                </a>
            </div>

            <div class="border-top pt-3 mt-3 text-center">
                <span class="text-muted small d-block mb-1" style="font-size: 0.78rem;">Lupa email atau akun bermasalah?</span>
                <a href="<?= format_wa_url(get_setting('admin_wa', ADMIN_PHONE_WA), 'Halo Admin JASA INHU, saya lupa email dan kata sandi akun saya. Mohon bantuan pemulihan akun.') ?>" target="_blank" class="small text-teal text-decoration-none fw-bold" style="font-size: 0.82rem;">
                    <i class="fa-brands fa-whatsapp me-1"></i> Bantuan Pemulihan via Admin WhatsApp
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

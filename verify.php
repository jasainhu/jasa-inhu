<?php
/**
 * Halaman Verifikasi Akun JASA INHU (Otomatis Gmail SMTP & WhatsApp Gateway)
 * Mencegah Pengguna & Pesanan Fiktif / Spam
 */

$page_title = 'Verifikasi Akun';

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$error = '';
$success = '';
$otp_sent = false;
$active_channel = $_POST['channel'] ?? $_GET['channel'] ?? '';
$direct_link = '';
$redirect_after = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');

// 1. Tangani pembatalan pendaftaran / ganti nomor
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    unset($_SESSION['pending_verification_user_id']);
    unset($_SESSION['pending_verification_name']);
    unset($_SESSION['pending_verification_phone']);
    unset($_SESSION['pending_verification_email']);
    unset($_SESSION['pending_verification_role']);
    set_flash('info', 'Silakan masukkan nomor atau data pendaftaran yang benar.');
    redirect('/register.php');
}

// 2. Tangani verifikasi langsung via link email (?token=...)
if (!empty($_GET['token'])) {
    $tokenRes = verify_user_token($_GET['token']);
    if ($tokenRes['success']) {
        set_flash('success', 'Akun Anda berhasil diverifikasi via Gmail! Selamat datang di JASA INHU.');
        $u = current_user();
        redirect(!empty($redirect_after) ? $redirect_after : get_post_login_url($u['role_name'] ?? 'pengguna'));
    } else {
        $error = $tokenRes['message'];
    }
}

// 3. Tentukan pengguna yang sedang diverifikasi (Bisa session login ATAU session pending)
$is_pending = false;
$current = null;

if (is_logged_in()) {
    $current = current_user();
} elseif (!empty($_SESSION['pending_verification_user_id'])) {
    $is_pending = true;
    $db = get_db();
    $stmt = $db->prepare("
        SELECT u.id, u.role_id, u.name, u.email, u.phone, u.is_active, u.email_verified_at,
               r.name as role_name, r.display_name as role_display
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([(int)$_SESSION['pending_verification_user_id']]);
    $current = $stmt->fetch() ?: null;
}

if (!$current) {
    redirect('/login.php');
}

// Jika akun sudah terverifikasi sebelumnya, berikan login jika pending lalu redirect
if (!empty($current['email_verified_at'])) {
    if ($is_pending) {
        $_SESSION['user_id'] = (int)$current['id'];
        $_SESSION['user_name'] = $current['name'];
        $_SESSION['user_email'] = $current['email'];
        $_SESSION['user_role'] = $current['role_name'];
        unset($_SESSION['pending_verification_user_id']);
        unset($_SESSION['pending_verification_name']);
        unset($_SESSION['pending_verification_phone']);
        unset($_SESSION['pending_verification_email']);
        unset($_SESSION['pending_verification_role']);
    }
    set_flash('info', 'Akun Anda sudah terverifikasi.');
    redirect(!empty($redirect_after) ? $redirect_after : get_post_login_url($current['role_name']));
}

$db = get_db();

// 4. Tangani Form Kirim Ulang / Submit OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan coba kembali.';
    } else {
        $action = $_POST['action'] ?? '';

        // Aksi A: Minta Kode OTP (WhatsApp atau Gmail)
        if ($action === 'request_otp') {
            $selected_channel = ($_POST['channel'] === 'email') ? 'email' : 'whatsapp';
            $otpRes = generate_verification_otp((int)$current['id'], $selected_channel);
            
            if ($otpRes['success']) {
                $otp_sent = true;
                $active_channel = $selected_channel;
                $success = $otpRes['message'];
                $direct_link = $otpRes['direct_url'] ?? '';
            } else {
                $error = $otpRes['message'];
            }
        }
        
        // Aksi B: Submit Kode OTP 6 Digit
        elseif ($action === 'submit_otp') {
            $code = trim($_POST['otp_code'] ?? '');
            $verifyRes = verify_user_otp((int)$current['id'], $code);

            if ($verifyRes['success']) {
                // Aktifkan login resmi HANYA setelah OTP berhasil diverifikasi!
                $_SESSION['user_id'] = (int)$current['id'];
                $_SESSION['user_name'] = $current['name'];
                $_SESSION['user_email'] = $current['email'];
                $_SESSION['user_role'] = $current['role_name'];
                unset($_SESSION['pending_verification_user_id']);
                unset($_SESSION['pending_verification_name']);
                unset($_SESSION['pending_verification_phone']);
                unset($_SESSION['pending_verification_email']);
                unset($_SESSION['pending_verification_role']);

                set_flash('success', 'Selamat, akun Anda berhasil diverifikasi! Selamat datang di JASA INHU.');
                redirect(!empty($redirect_after) ? $redirect_after : get_post_login_url($current['role_name']));
            } else {
                $error = $verifyRes['message'];
                $otp_sent = true;
                $active_channel = $_POST['active_channel'] ?? 'whatsapp';
            }
        }
    }
}

// Cek apakah ada record verifikasi aktif yang belum kadaluarsa
$stmtActive = $db->prepare("
    SELECT id, channel, target, code, token, expires_at, created_at 
    FROM user_verifications 
    WHERE user_id = ? AND is_verified = 0 AND expires_at > NOW() 
    ORDER BY id DESC LIMIT 1
");
$stmtActive->execute([(int)$current['id']]);
$active_verif = $stmtActive->fetch();

if ($active_verif) {
    $otp_sent = true;
    if (empty($active_channel)) {
        $active_channel = $active_verif['channel'];
    }
} else {
    // Jika belum ada verifikasi sama sekali (atau sudah expired), buat otomatis via WhatsApp
    $autoOtp = generate_verification_otp((int)$current['id'], 'whatsapp');
    if ($autoOtp['success']) {
        $otp_sent = true;
        $active_channel = 'whatsapp';
        $active_verif = [
            'code'       => $autoOtp['code'],
            'token'      => $autoOtp['token'],
            'channel'    => 'whatsapp',
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + 900)
        ];
        $direct_link = $autoOtp['direct_url'] ?? '';
    }
}

$mailer = new SimpleSmtpMailer();
$has_webhook = !empty(get_setting('gmail_webhook_url'));
$is_smtp_ready = $mailer->isConfigured() || $has_webhook;

// Cek status email log terakhir untuk pengguna ini
$stmtLastLog = $db->prepare("
    SELECT status, error_message 
    FROM email_logs 
    WHERE recipient_email = ? 
    ORDER BY id DESC LIMIT 1
");
$stmtLastLog->execute([$current['email']]);
$lastEmailLog = $stmtLastLog->fetch();

$email_failed = ($lastEmailLog && $lastEmailLog['status'] === 'failed');
$email_simulated = ($lastEmailLog && $lastEmailLog['status'] === 'simulated');

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper py-5">
    <div class="auth-card" style="max-width: 580px;">
        <div class="auth-card-header text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center p-3.5 rounded-circle mb-3" style="background: rgba(13, 148, 136, 0.1); color: var(--primary);">
                <i class="fa-solid fa-shield-halved fs-2"></i>
            </div>
            <h3 class="fw-bold mb-1 text-dark">Verifikasi Keamanan Akun</h3>
            <p class="text-muted small mb-0">
                Halo, <strong><?= e($current['name']) ?></strong>! Demi keamanan & pencegahan order fiktif, akun Anda perlu diverifikasi.
            </p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success d-flex align-items-center rounded-3 shadow-xs mb-3" role="alert">
                <i class="fa-solid fa-circle-check me-2 fs-5"></i>
                <div class="small fw-semibold"><?= e($success) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center rounded-3 shadow-xs mb-3" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                <div class="small fw-semibold"><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- FORM INPUT KODE OTP 6 DIGIT -->
        <form method="POST" action="<?= BASE_URL ?>/verify.php<?= !empty($redirect_after) ? '?redirect=' . urlencode($redirect_after) : '' ?>" class="mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_otp">
            <input type="hidden" name="active_channel" value="<?= e($active_channel) ?>">
            <input type="hidden" name="redirect" value="<?= e($redirect_after) ?>">

            <div class="p-4 bg-light rounded-4 border text-center mb-3">
                <div class="mb-3">
                    <?php if ($active_channel === 'whatsapp'): ?>
                        <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill bg-success text-white small fw-bold mb-2 shadow-xs" style="max-width: 100%;">
                            <i class="fa-brands fa-whatsapp fs-6 flex-shrink-0"></i>
                            <span class="text-truncate">Kode dikirim ke WA: <?= e($current['phone']) ?></span>
                        </div>
                        <p class="text-muted small mb-0">
                            Masukkan 6 digit kode OTP yang terkirim ke WhatsApp Anda:
                        </p>
                    <?php else: ?>
                        <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill bg-danger text-white small fw-bold mb-2 shadow-xs" style="max-width: 100%;">
                            <i class="fa-regular fa-envelope fs-6 flex-shrink-0"></i>
                            <span class="text-truncate" style="max-width: 250px;">Terkirim ke: <?= e($current['email']) ?></span>
                        </div>
                        <p class="text-muted small mb-0">
                            Kode verifikasi 6 digit telah diproses untuk email Anda:
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Notifikasi Info Email / Spam Tip / Fallback jika Port SMTP Diblokir ISP -->
                <?php if ($active_channel === 'email'): ?>
                    <?php if ($email_failed && !empty($active_verif['code'])): ?>
                        <!-- PERLINDUNGAN FALLBACK: Jika koneksi port SMTP diblokir oleh ISP lokal komputer -->
                        <div class="alert alert-warning border-0 rounded-3 mb-3 p-3 text-start shadow-xs">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-warning fs-5 mt-0.5"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">Koneksi Port SMTP Diblokir oleh Jaringan ISP</h6>
                                    <p class="small text-muted mb-2">
                                        Server lokal terhalang port SMTP (587/465) oleh provider internet lokal. Demi kenyamanan aktivasi akun Anda, berikut kode OTP resmi Anda:
                                    </p>
                                    <div class="p-2.5 bg-white rounded border d-flex align-items-center justify-content-between mb-2">
                                        <span class="small text-muted fw-semibold">Kode OTP Verifikasi Anda:</span>
                                        <span class="badge bg-dark font-monospace fs-4 px-3 py-1"><?= e($active_verif['code']) ?></span>
                                    </div>
                                    <div class="text-muted small" style="font-size: 0.78rem;">
                                        Ketik 6 angka di atas ke kolom di bawah untuk mengaktifkan akun secara instan, atau gunakan opsi WhatsApp di bawah.
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($is_smtp_ready): ?>
                        <div class="alert alert-info border-0 rounded-3 mb-3 p-2.5 text-start shadow-xs">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-envelope-open-text text-primary fs-5"></i>
                                <div class="small">
                                    Silakan cek <strong>Kotak Masuk (Inbox)</strong> email <strong><?= e($current['email']) ?></strong>.
                                    <div class="text-muted" style="font-size: 0.78rem;">
                                        Jika belum muncul dalam 1 menit, periksa folder <strong>Spam</strong> atau <strong>Promosi</strong>.
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif (!empty($active_verif['code'])): ?>
                        <!-- Mode Simulasi Dev jika admin belum mengonfigurasi SMTP Gmail -->
                        <div class="alert alert-warning border-0 rounded-3 mb-3 p-2.5 text-start shadow-xs">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="small">
                                    <i class="fa-solid fa-flask text-warning me-1"></i>
                                    <strong>Mode Simulasi Dev:</strong> SMTP Gmail belum diisi di Pengaturan Admin. Kode verifikasi Anda adalah:
                                </div>
                                <span class="badge bg-dark font-monospace fs-5 px-3 py-1"><?= e($active_verif['code']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Input Digit OTP -->
                <div class="d-flex justify-content-center mb-3">
                    <input type="text" name="otp_code" maxlength="6" autofocus required
                           class="form-control text-center font-monospace fw-bold fs-1 shadow-xs" 
                           style="max-width: 260px; letter-spacing: 0.45rem;" 
                           placeholder="------" autocomplete="one-time-code">
                </div>

                <?php if (!empty($direct_link) && $active_channel === 'whatsapp'): ?>
                    <div class="mb-3">
                        <a href="<?= e($direct_link) ?>" target="_blank" class="btn btn-sm btn-outline-success fw-semibold">
                            <i class="fa-brands fa-whatsapp me-1"></i> Buka Chat WhatsApp untuk Lihat Pesan
                        </a>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-teal fw-bold px-4 py-2.5 w-100 shadow-xs fs-6">
                    <i class="fa-solid fa-circle-check me-1.5"></i> Verifikasi Akun Saya Sekarang
                </button>
            </div>
        </form>

        <!-- KOTAK INFORMASI PERLINDUNGAN ANTI-ORDER FIKTIF -->
        <div class="p-3 bg-white rounded-3 border mb-3 shadow-xs">
            <div class="d-flex align-items-start gap-2.5">
                <div class="p-2 rounded-circle bg-teal-subtle text-teal mt-0.5">
                    <i class="fa-solid fa-shield-halved fs-6"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-1 small">Mengapa Verifikasi Ini Penting?</h6>
                    <p class="text-muted mb-0" style="font-size: 0.8rem; line-height: 1.45;">
                        Untuk mencegah <strong>pesanan fiktif (prank orders)</strong> dan akun bot. Kami berkomitmen melindungi waktu, tenaga, dan operasional teknisi lokal di Indragiri Hulu agar hanya melayani pemesan yang asli & terpercaya.
                    </p>
                </div>
            </div>
        </div>

        <!-- PILIHAN JALUR & KIRIM ULANG (Wajib Verifikasi, Lewati Dihapus) -->
        <div class="p-3 bg-light rounded-3 border text-center">
            <div class="small text-muted mb-2 fw-semibold">Tidak menerima kode verifikasi?</div>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <?php if ($active_channel === 'email'): ?>
                    <!-- Form Kirim Ulang Gmail -->
                    <form method="POST" action="<?= BASE_URL ?>/verify.php" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request_otp">
                        <input type="hidden" name="channel" value="email">
                        <input type="hidden" name="redirect" value="<?= e($redirect_after) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary fw-semibold">
                            <i class="fa-solid fa-rotate-right me-1"></i> Kirim Ulang Email
                        </button>
                    </form>

                    <!-- Form Switch ke WhatsApp -->
                    <form method="POST" action="<?= BASE_URL ?>/verify.php" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request_otp">
                        <input type="hidden" name="channel" value="whatsapp">
                        <input type="hidden" name="redirect" value="<?= e($redirect_after) ?>">
                        <button type="submit" class="btn btn-sm btn-success text-white fw-semibold">
                            <i class="fa-brands fa-whatsapp me-1"></i> Alternatif: Kirim ke WhatsApp
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Form Kirim Ulang WhatsApp -->
                    <form method="POST" action="<?= BASE_URL ?>/verify.php" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request_otp">
                        <input type="hidden" name="channel" value="whatsapp">
                        <input type="hidden" name="redirect" value="<?= e($redirect_after) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary fw-semibold">
                            <i class="fa-solid fa-rotate-right me-1"></i> Kirim Ulang WhatsApp
                        </button>
                    </form>

                    <!-- Form Switch ke Gmail -->
                    <form method="POST" action="<?= BASE_URL ?>/verify.php" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request_otp">
                        <input type="hidden" name="channel" value="email">
                        <input type="hidden" name="redirect" value="<?= e($redirect_after) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger fw-semibold">
                            <i class="fa-regular fa-envelope me-1"></i> Kirim ke Gmail Saja
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_pending): ?>
            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/verify.php?action=cancel" class="text-muted small text-decoration-none">
                    <i class="fa-solid fa-arrow-left me-1"></i> Salah input nomor HP? Batal & Daftar Ulang
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

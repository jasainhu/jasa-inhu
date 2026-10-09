<?php
/**
 * Admin: Pengaturan Sistem & Rekening Resmi JASA INHU
 */

$page_title = 'Pengaturan Sistem & Rekening';
$admin_active = 'settings';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

require_role('admin');

$db = get_db();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (validate_csrf()) {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_settings') {
            $admin_wa = preg_replace('/[^0-9]/', '', $_POST['admin_wa'] ?? '');
            if (str_starts_with($admin_wa, '08')) {
                $admin_wa = '628' . substr($admin_wa, 2);
            }
            $admin_email = strtolower(trim($_POST['admin_email'] ?? ''));

            $bank_name_1 = trim($_POST['bank_name_1'] ?? '');
            $bank_acc_1 = trim($_POST['bank_acc_1'] ?? '');
            $bank_owner_1 = trim($_POST['bank_owner_1'] ?? '');

            $bank_name_2 = trim($_POST['bank_name_2'] ?? '');
            $bank_acc_2 = trim($_POST['bank_acc_2'] ?? '');
            $bank_owner_2 = trim($_POST['bank_owner_2'] ?? '');

            $ewallet_name = trim($_POST['ewallet_name'] ?? '');
            $ewallet_acc = trim($_POST['ewallet_acc'] ?? '');
            $ewallet_owner = trim($_POST['ewallet_owner'] ?? '');

            $qris_info = trim($_POST['qris_info'] ?? '');
            $lead_fee_amount = (float)($_POST['lead_fee_amount'] ?? 3000);
            $welcome_bonus_amount = (float)($_POST['welcome_bonus_amount'] ?? 45000);

            // SMTP & Webhook Settings
            $smtp_user = strtolower(trim($_POST['smtp_user'] ?? ''));
            $smtp_pass = trim($_POST['smtp_pass'] ?? '');
            $smtp_from_name = trim($_POST['smtp_from_name'] ?? 'JASA INHU Resmi');
            $smtp_host = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
            $smtp_port = preg_replace('/[^0-9]/', '', (string)($_POST['smtp_port'] ?? '587')) ?: '587';
            $smtp_secure = trim($_POST['smtp_secure'] ?? 'tls');
            $gmail_webhook_url = trim($_POST['gmail_webhook_url'] ?? '');

            if (empty($admin_wa)) {
                $error = 'Nomor WhatsApp Admin tidak boleh kosong.';
            } else {
                update_setting('admin_wa', $admin_wa);
                if (!empty($admin_email)) {
                    update_setting('admin_email', $admin_email);
                }
                update_setting('bank_name_1', $bank_name_1);
                update_setting('bank_acc_1', $bank_acc_1);
                update_setting('bank_owner_1', $bank_owner_1);

                update_setting('bank_name_2', $bank_name_2);
                update_setting('bank_acc_2', $bank_acc_2);
                update_setting('bank_owner_2', $bank_owner_2);

                update_setting('ewallet_name', $ewallet_name);
                update_setting('ewallet_acc', $ewallet_acc);
                update_setting('ewallet_owner', $ewallet_owner);

                update_setting('qris_info', $qris_info);
                update_setting('lead_fee_amount', (string)$lead_fee_amount);
                update_setting('welcome_bonus_amount', (string)$welcome_bonus_amount);

                // Simpan SMTP & Webhook
                update_setting('smtp_user', $smtp_user);
                update_setting('smtp_pass', $smtp_pass);
                update_setting('smtp_from_name', $smtp_from_name);
                update_setting('smtp_host', $smtp_host);
                update_setting('smtp_port', $smtp_port);
                update_setting('smtp_secure', $smtp_secure);
                update_setting('gmail_webhook_url', $gmail_webhook_url);

                $success = 'Pengaturan sistem, rekening bank, e-wallet, dan konfigurasi email berhasil disimpan!';
            }
        } elseif ($action === 'test_email') {
            $test_to = trim($_POST['test_recipient'] ?? '');
            if (empty($test_to) || !filter_var($test_to, FILTER_VALIDATE_EMAIL)) {
                $error = 'Masukkan alamat email tujuan uji coba yang valid.';
            } else {
                $subject = "Uji Coba Pengiriman Email Otomatis JASA INHU";
                $html = render_verification_email_html('Admin Jasa Inhu', '123456', BASE_URL . '/verify.php?token=test-sample');
                $plain = "Halo Admin,\n\nIni adalah email uji coba dari sistem JASA INHU untuk memastikan koneksi pengiriman email bekerja dengan baik.\n\nKode sample: 123456\n";
                
                $testRes = send_system_email($test_to, $subject, $html, $plain, 'Admin Uji Coba');
                if ($testRes['success']) {
                    $success = "Email uji coba BERHASIL dikirim ke {$test_to}! Layanan email berfungsi dengan normal.";
                } elseif (!empty($testRes['simulated'])) {
                    $error = "Akun Gmail & Sandi Aplikasi (App Password) atau URL Webhook belum diisi. Pengiriman masih dalam mode simulasi.";
                } else {
                    $error = "Gagal mengirim email uji coba: " . ($testRes['error'] ?? 'Terjadi kesalahan pengiriman.');
                }
            }

            if (!empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => !empty($success),
                    'message' => $success ?: $error
                ]);
                exit;
            }
        }
    } else {
        $error = 'Sesi keamanan tidak valid. Silakan coba kembali.';
    }
}

// Ambil nilai terkini
$current_wa = get_setting('admin_wa', ADMIN_PHONE_WA);
$current_admin_email = get_setting('admin_email', defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'jasainhu@gmail.com');
$current_b1_name = get_setting('bank_name_1', 'Bank Riau Kepri Syariah');
$current_b1_acc = get_setting('bank_acc_1', '102-20-12345');
$current_b1_owner = get_setting('bank_owner_1', 'Jasa Inhu Official');

$current_b2_name = get_setting('bank_name_2', '');
$current_b2_acc = get_setting('bank_acc_2', '');
$current_b2_owner = get_setting('bank_owner_2', '');

$current_ewallet_name = get_setting('ewallet_name', 'DANA');
$current_ewallet_acc = get_setting('ewallet_acc', '0851-2624-1679');
$current_ewallet_owner = get_setting('ewallet_owner', 'Admin Jasa Inhu');

$current_qris = get_setting('qris_info', 'Scan QRIS Langsung via WhatsApp Admin');
$current_lead_fee = (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE);
$current_welcome_bonus = (float)get_setting('welcome_bonus_amount', WELCOME_BONUS_WALLET);

// Nilai SMTP
$current_smtp_host = get_setting('smtp_host', 'smtp.gmail.com');
$current_smtp_port = get_setting('smtp_port', '587');
$current_smtp_user = get_setting('smtp_user', '');
$current_smtp_pass = get_setting('smtp_pass', '');
$current_smtp_from_name = get_setting('smtp_from_name', 'JASA INHU Resmi');
$current_smtp_secure = get_setting('smtp_secure', 'tls');
$current_webhook_url = get_setting('gmail_webhook_url', '');

$recent_emails = [];
try {
    $stmtE = $db->query("SELECT id, recipient_email, subject, status, error_message, created_at FROM email_logs ORDER BY id DESC LIMIT 5");
    $recent_emails = $stmtE->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">Pengaturan Sistem & Rekening Resmi</h1>
        <p class="text-muted small mb-0">Kelola nomor kontak WhatsApp CS, rekening tujuan top-up mitra, dan tarif kuota lead fee</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>/provider/wallet.php" target="_blank" class="btn btn-outline-teal btn-sm fw-semibold">
            <i class="fa-solid fa-eye me-1"></i> Pratinjau Tampilan Top-Up Mitra
        </a>
        <button type="submit" form="settingsForm" class="btn btn-teal btn-sm fw-bold shadow-xs">
            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Pengaturan
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/admin/settings.php" id="settingsForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_settings">

    <div class="row g-4 mb-4">
        <!-- 1. Kontak Layanan & WhatsApp CS -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="fa-brands fa-whatsapp text-success fs-4"></i>
                        <span>Kontak Resmi Layanan & Bantuan</span>
                    </h5>
                    <p class="text-muted small mb-0">Digunakan untuk konfirmasi top-up saldo, bantuan pelanggan, dan kontak legal</p>
                </div>
                <div class="card-body p-4 pt-1">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Nomor WhatsApp Admin (Format 62xxx atau 08xxx):</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-success fw-bold"><i class="fa-brands fa-whatsapp"></i></span>
                            <input type="text" name="admin_wa" class="form-control font-monospace fw-bold fs-6" value="<?= e($current_wa) ?>" placeholder="Contoh: 6285126241679" required>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            Nomor ini akan otomatis dihubungkan saat mitra klik <em>"Buka WhatsApp Admin"</em> untuk konfirmasi saldo atau pelanggan klik tombol bantuan.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Alamat Email Resmi Admin & Layanan:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-teal fw-bold"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="admin_email" class="form-control fw-semibold fs-6" value="<?= e($current_admin_email) ?>" placeholder="Contoh: jasainhu@gmail.com" required>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            Alamat email resmi yang tercantum di Syarat & Ketentuan, Kebijakan Privasi, dan saluran aduan.
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="fa-solid fa-lightbulb text-warning"></i>
                            <strong class="text-dark small">Tips Peluncuran di Inhu:</strong>
                        </div>
                        <p class="text-secondary small mb-0" style="font-size: 0.78rem;">
                            Gunakan WhatsApp Business dengan foto profil logo JASA INHU agar terlihat resmi dan terpercaya bagi warga dan mitra di Kabupaten Indragiri Hulu.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Biaya Kontak (Lead Fee) & Kuota Gratis -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-coins text-warning fs-4"></i>
                        <span>Monetisasi Biaya Kontak (Lead Fee)</span>
                    </h5>
                    <p class="text-muted small mb-0">Kebijakan saldo kuota dompet penyedia jasa</p>
                </div>
                <div class="card-body p-4 pt-1">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold text-dark small mb-0">Biaya Kontak per Pesanan (Rp):</label>
                            <span id="promoBadge" class="badge bg-light text-secondary border" style="font-size: 0.72rem;">
                                <?= $current_lead_fee == 0 ? 'Promo Gratis Rp 0' : ($current_lead_fee < 3000 ? 'Promo Diskon Aktif' : 'Tarif Normal') ?>
                            </span>
                        </div>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-light fw-bold">Rp</span>
                            <input type="number" id="inputLeadFee" name="lead_fee_amount" class="form-control fw-bold font-monospace text-teal fs-6" value="<?= (int)$current_lead_fee ?>" min="0" step="100" required>
                        </div>
                        <!-- Tombol Cepat Promo -->
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="text-muted small align-self-center me-1" style="font-size: 0.7rem;">Pilihan Cepat:</span>
                            <button type="button" class="btn btn-xs btn-outline-success py-0.5 px-2" style="font-size: 0.72rem;" onclick="setLeadFee(0)">
                                <i class="fa-solid fa-gift me-0.5"></i> Promo Rp 0 (Gratis)
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-teal py-0.5 px-2" style="font-size: 0.72rem;" onclick="setLeadFee(1000)">
                                Promo Rp 1.000
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-teal py-0.5 px-2" style="font-size: 0.72rem;" onclick="setLeadFee(2000)">
                                Promo Rp 2.000
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-2" style="font-size: 0.72rem;" onclick="setLeadFee(3000)">
                                Normal Rp 3.000
                            </button>
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            Bebas diubah kapan saja. Jika disetel <strong>Rp 0</strong>, mitra bebas mengambil order tanpa potongan saldo (cocok untuk promo peluncuran).
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1">Bonus Saldo Mitra Baru ("Cicipi Madu Dulu") (Rp):</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-light fw-bold">Rp</span>
                            <input type="number" id="inputBonus" name="welcome_bonus_amount" class="form-control fw-bold font-monospace text-success fs-6" value="<?= (int)$current_welcome_bonus ?>" min="0" step="1000" required>
                        </div>
                        <div class="p-2.5 bg-light rounded-3 border" style="font-size: 0.78rem;">
                            <div class="d-flex align-items-center gap-1.5 text-dark fw-semibold mb-0.5">
                                <i class="fa-solid fa-calculator text-teal"></i>
                                <span>Simulasi Real-Time Kuota Mitra Baru:</span>
                            </div>
                            <div id="simulasiCalcText" class="text-secondary">
                                <?= $current_lead_fee > 0 ? "Bonus <strong>Rp " . number_format($current_welcome_bonus, 0, ',', '.') . "</strong> setara dengan <strong>" . floor($current_welcome_bonus / $current_lead_fee) . " pesanan pertama GRATIS</strong> bagi mitra baru tanpa perlu setor deposit." : "Biaya kontak Rp 0 (GRATIS), mitra baru dapat langsung menerima pesanan tanpa batas!" ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Rekening Bank Transfer & E-Wallet Top-Up -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-wallet text-teal fs-4"></i>
                            <span>Rekening Bank & E-Wallet Resmi untuk Top-Up Mitra</span>
                        </h5>
                        <p class="text-muted small mb-0">Bebas masukkan rekening bank & dompet digital apa saja yang Anda miliki (fleksibel tanpa batasan bank tertentu)</p>
                    </div>
                    <button type="submit" form="settingsForm" class="btn btn-teal btn-sm fw-bold shadow-xs">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Rekening
                    </button>
                </div>
                <div class="card-body p-4 pt-1">
                    <div class="row g-4">
                        <!-- Rekening 1 (Bebas Bank Apa Saja) -->
                        <div class="col-lg-4 col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100 shadow-xs">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge text-bg-primary"><i class="fa-solid fa-building-columns me-1"></i> Rekening Bank 1</span>
                                    <span class="badge bg-white text-secondary border fw-normal" style="font-size: 0.7rem;">Bebas Bank Apa Saja</span>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-muted mb-1">Nama Bank:</label>
                                    <input type="text" name="bank_name_1" class="form-control form-control-sm fw-bold" value="<?= e($current_b1_name) ?>" placeholder="Contoh: BRI, Mandiri, BCA, Bank Riau Kepri, BSI dll">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-muted mb-1">Nomor Rekening:</label>
                                    <input type="text" name="bank_acc_1" class="form-control form-control-sm font-monospace fw-bold" value="<?= e($current_b1_acc) ?>" placeholder="Nomor rekening bank...">
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold text-muted mb-1">Atas Nama Pemilik Rekening:</label>
                                    <input type="text" name="bank_owner_1" class="form-control form-control-sm fw-bold" value="<?= e($current_b1_owner) ?>" placeholder="Nama pemilik rekening...">
                                </div>
                            </div>
                        </div>

                        <!-- Rekening 2 (Bebas / Opsional) -->
                        <div class="col-lg-4 col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100 shadow-xs">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge text-bg-secondary"><i class="fa-solid fa-building-columns me-1"></i> Rekening Bank 2</span>
                                    <span class="badge bg-white text-muted border fw-normal" style="font-size: 0.7rem;">Bebas / Opsional</span>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-muted mb-1">Nama Bank:</label>
                                    <input type="text" name="bank_name_2" class="form-control form-control-sm fw-bold" value="<?= e($current_b2_name) ?>" placeholder="Contoh: Mandiri, BRI, BNI dll (opsional)">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-muted mb-1">Nomor Rekening:</label>
                                    <input type="text" name="bank_acc_2" class="form-control form-control-sm font-monospace fw-bold" value="<?= e($current_b2_acc) ?>" placeholder="Nomor rekening kedua (opsional)...">
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold text-muted mb-1">Atas Nama Pemilik Rekening:</label>
                                    <input type="text" name="bank_owner_2" class="form-control form-control-sm fw-bold" value="<?= e($current_b2_owner) ?>" placeholder="Nama pemilik rekening kedua...">
                                </div>
                            </div>
                        </div>

                        <!-- E-Wallet / Dompet Digital (Bebas Apa Saja) -->
                        <div class="col-lg-4 col-md-12">
                            <div class="p-3 bg-light rounded-3 border h-100 shadow-xs" style="border-left: 4px solid #10b981 !important;">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge text-bg-success"><i class="fa-solid fa-mobile-screen-button me-1"></i> Akun E-Wallet</span>
                                    <span class="badge bg-white text-success border fw-normal" style="font-size: 0.7rem;">DANA, OVO, GoPay dll</span>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-muted mb-1">Nama Penyedia E-Wallet:</label>
                                    <input type="text" name="ewallet_name" class="form-control form-control-sm fw-bold" value="<?= e($current_ewallet_name) ?>" placeholder="Contoh: DANA, OVO, GoPay, ShopeePay dll">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-muted mb-1">Nomor Akun / No HP E-Wallet:</label>
                                    <input type="text" name="ewallet_acc" class="form-control form-control-sm font-monospace fw-bold" value="<?= e($current_ewallet_acc) ?>" placeholder="Contoh: 0851-xxxx-xxxx">
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold text-muted mb-1">Atas Nama Akun E-Wallet:</label>
                                    <input type="text" name="ewallet_owner" class="form-control form-control-sm fw-bold" value="<?= e($current_ewallet_owner) ?>" placeholder="Contoh: Admin Jasa Inhu / Nama Pemilik">
                                </div>
                            </div>
                        </div>

                        <!-- QRIS Info -->
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <span class="badge text-bg-dark mb-2"><i class="fa-solid fa-qrcode me-1"></i> Opsi QRIS & Catatan Pembayaran</span>
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <label class="form-label small fw-semibold text-muted mb-1">Petunjuk Pembayaran QRIS / Keterangan Transfer:</label>
                                        <input type="text" name="qris_info" class="form-control form-control-sm fw-semibold" value="<?= e($current_qris) ?>" placeholder="Contoh: Scan Barcode QRIS via Chat WhatsApp Admin">
                                    </div>
                                    <div class="col-md-4 text-muted small mt-2 mt-md-0">
                                        <em>Mitra dapat meminta barcode QRIS resmi langsung ke nomor WhatsApp Admin saat verifikasi pengisian saldo.</em>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Simpan di dalam Card -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top gap-2 bg-light p-3 rounded-3">
                        <span class="text-muted small">
                            <i class="fa-solid fa-circle-check text-success me-1"></i> Data rekening & e-wallet yang disimpan akan otomatis muncul pada pop-up <strong>Isi Saldo</strong> di dashboard seluruh mitra.
                        </span>
                        <button type="submit" form="settingsForm" class="btn btn-teal btn-sm fw-bold px-3 py-2 shadow-xs">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Data Rekening & E-Wallet
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Integrasi Email Otomatis Gmail & Anti-Pesanan Fiktif -->
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-danger-subtle text-danger">
                            <i class="fa-solid fa-envelope-circle-check fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">Integrasi Email Otomatis Gmail & Anti-Akun Fiktif</h5>
                            <small class="text-muted">Kirim kode OTP & aktivasi langsung ke kotak masuk pengguna untuk memvalidasi akun asli</small>
                        </div>
                    </div>
                    <div>
                        <?php if (!empty($current_webhook_url)): ?>
                            <span class="badge text-bg-success px-3 py-1.5 fw-semibold">
                                <i class="fa-solid fa-cloud-arrow-up me-1"></i> Webhook Google HTTPS Aktif (Bebas Blokir ISP)
                            </span>
                        <?php elseif (!empty($current_smtp_user) && !empty($current_smtp_pass)): ?>
                            <span class="badge text-bg-info px-3 py-1.5 fw-semibold text-white">
                                <i class="fa-solid fa-server me-1"></i> SMTP Socket Gmail Diatur
                            </span>
                        <?php else: ?>
                            <span class="badge text-bg-warning px-3 py-1.5 fw-semibold text-dark">
                                <i class="fa-solid fa-flask me-1"></i> Mode Simulasi Dev (Belum Diisi)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body p-4">

                    <!-- METODE 1: GOOGLE APPS SCRIPT WEBHOOK (100% BEBAS BLOKIR ISP) -->
                    <div class="p-3.5 rounded-4 border bg-white mb-4" style="border-left: 5px solid #0d9488 !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="fa-solid fa-shield-halved text-teal me-1.5"></i> Metode Rekomendasi: Webhook Google Apps Script (Port 443 HTTPS)
                            </h6>
                            <span class="badge text-bg-success px-2 py-1 small">
                                <i class="fa-solid fa-bolt me-1"></i> 100% Bebas Blokir ISP
                            </span>
                        </div>
                        <p class="small text-muted mb-3">
                            Jika jaringan WiFi/provider internet rumahan Anda memblokir port SMTP 587 (Error 10060 Timeout), gunakan metode resmi Google ini. Email dikirim langsung dari akun Gmail Anda (<strong><?= e($current_smtp_user ?: 'Gmail Anda') ?></strong>) melalui jalur HTTPS yang tidak akan pernah diblokir provider manapun.
                        </p>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">
                                URL Webhook Google Apps Script:
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-link text-muted"></i></span>
                                <input type="url" name="gmail_webhook_url" class="form-control font-monospace" 
                                       value="<?= e($current_webhook_url) ?>" 
                                       placeholder="https://script.google.com/macros/s/AKfycb.../exec">
                            </div>
                            <small class="text-muted">Cukup tempelkan Web App URL yang Anda dapatkan dari Google Apps Script.</small>
                        </div>

                        <!-- Panduan Cara Buat Webhook 2 Menit -->
                        <div class="accordion accordion-flush border rounded-3" id="accGuideWebhook">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 px-3 small fw-semibold text-teal" type="button" data-bs-toggle="collapse" data-bs-target="#colGuideWebhook">
                                        <i class="fa-solid fa-circle-question me-1.5"></i> Lihat Cara Membuat Webhook Google Apps Script Gratis (Hanya 2 Menit)
                                    </button>
                                </h2>
                                <div id="colGuideWebhook" class="accordion-collapse collapse" data-bs-parent="#accGuideWebhook">
                                    <div class="accordion-body small text-muted p-3 bg-light">
                                        <ol class="mb-2 ps-3">
                                            <li>Buka <a href="https://script.google.com/home/start" target="_blank" class="fw-bold text-teal">script.google.com</a> dengan akun Gmail pengirim Anda &raquo; Klik <strong>Proyek Baru (New Project)</strong>.</li>
                                            <li>Hapus semua teks yang ada di editor, lalu salin dan tempel kode berikut:
                                                <pre class="bg-dark text-white p-2.5 rounded my-2 font-monospace" style="font-size: 0.78rem;"><code>function doPost(e) {
  var data = JSON.parse(e.postData.contents);
  GmailApp.sendEmail(data.to, data.subject, data.plain, {
    name: data.from_name || "JASA INHU Resmi",
    htmlBody: data.html
  });
  return ContentService.createTextOutput(JSON.stringify({status:"ok"})).setMimeType(ContentService.MimeType.JSON);
}</code></pre>
                                            </li>
                                            <li>Klik tombol biru <strong>Terapkan (Deploy)</strong> di kanan atas &raquo; Pilih <strong>Penerapan Baru (New Deployment)</strong>.</li>
                                            <li>Klik ikon roda gigi (Setelan) &raquo; Pilih <strong>Aplikasi Web (Web App)</strong>.</li>
                                            <li>Atur <strong>Jalankan sebagai (Execute as)</strong>: <em>Saya (Me)</em>, dan <strong>Siapa yang memiliki akses (Who has access)</strong>: <em>Siapa saja (Anyone)</em>.</li>
                                            <li>Klik <strong>Terapkan (Deploy)</strong> &raquo; Berikan izin akses (Authorize) &raquo; Salin <strong>URL Aplikasi Web (Web App URL)</strong> yang berakhiran <code>/exec</code> lalu tempelkan ke kolom di atas.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- METODE 2: SMTP SOCKET STANDAR -->
                    <div class="p-3.5 rounded-4 border bg-light mb-4">
                        <h6 class="fw-bold text-dark mb-2">
                            <i class="fa-solid fa-server text-muted me-1.5"></i> Metode Alternatif: Pengaturan SMTP Socket Port 587
                        </h6>
                        <p class="small text-muted mb-3">
                            Digunakan jika server telah dihosting di VPS/Cloud Server yang mengizinkan koneksi port SMTP keluar.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">
                                    Alamat Email Pengirim Gmail:
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-at text-muted"></i></span>
                                    <input type="email" name="smtp_user" class="form-control fw-semibold" 
                                           value="<?= e($current_smtp_user) ?>" 
                                           placeholder="contoh: jasainhu.official@gmail.com">
                                </div>
                                <small class="text-muted">Akun Gmail resmi platform.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">
                                    Sandi Aplikasi Google (16 Karakter):
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-key text-muted"></i></span>
                                    <input type="password" id="inputSmtpPass" name="smtp_pass" class="form-control font-monospace fw-bold" 
                                           value="<?= e($current_smtp_pass) ?>" 
                                           placeholder="16 karakter sandi aplikasi Google">
                                    <button class="btn btn-outline-secondary" type="button" onclick="toggleSmtpPass()">
                                        <i class="fa-regular fa-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Google App Password 16 huruf tanpa spasi.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Nama Pengirim Tampil di Inbox:</label>
                                <input type="text" name="smtp_from_name" class="form-control fw-semibold" 
                                       value="<?= e($current_smtp_from_name) ?>" 
                                       placeholder="JASA INHU Resmi">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-dark">Host Server SMTP:</label>
                                <input type="text" name="smtp_host" class="form-control font-monospace" 
                                       value="<?= e($current_smtp_host) ?>" readonly>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-dark">Port SMTP:</label>
                                <input type="text" name="smtp_port" class="form-control font-monospace" 
                                       value="<?= e($current_smtp_port) ?>" placeholder="587">
                                <input type="hidden" name="smtp_secure" value="<?= e($current_smtp_secure) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Simpan Kartu Email -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top gap-2 bg-light p-3 rounded-3">
                        <span class="text-muted small">
                            <i class="fa-solid fa-shield-halved text-success me-1"></i> Setelah disimpan, seluruh email pendaftar baru akan otomatis dikirimkan via metode yang aktif.
                        </span>
                        <button type="submit" form="settingsForm" class="btn btn-teal btn-sm fw-bold px-3 py-2 shadow-xs">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Konfigurasi Email
                        </button>
                    </div>

                    <!-- Alat Uji Coba Kirim Email Langsung -->
                    <div class="mt-4 pt-3 border-top">
                        <h6 class="fw-bold text-dark mb-2">
                            <i class="fa-solid fa-paper-plane text-teal me-1"></i> Uji Coba Pengiriman Email (Test Email)
                        </h6>
                        <p class="small text-muted mb-3">
                            Kirim satu email uji coba ke alamat Anda untuk memastikan kredensial Gmail di atas berfungsi dengan sempurna sebelum digunakan pengguna umum.
                        </p>
                        <div class="row g-2 align-items-center">
                            <div class="col-md-7">
                                <input type="email" id="testEmailTarget" class="form-control form-control-sm" 
                                       placeholder="Masukkan alamat email Anda untuk menerima uji coba (misal: email.anda@gmail.com)"
                                       value="<?= !empty($current_smtp_user) ? e($current_smtp_user) : 'admin@jasainhu.id' ?>">
                            </div>
                            <div class="col-md-5">
                                <button type="button" id="btnTestEmail" class="btn btn-outline-danger btn-sm fw-bold w-100" onclick="submitTestEmail()">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Kirim Email Uji Coba Sekarang
                                </button>
                            </div>
                        </div>
                        <div id="testEmailAlert" class="mt-2.5"></div>
                    </div>

                    <!-- Tabel Riwayat Log Email Terakhir -->
                    <?php if (!empty($recent_emails)): ?>
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="fw-bold text-dark mb-2">
                                <i class="fa-solid fa-clock-rotate-left text-muted me-1"></i> 5 Pengiriman Email Terakhir (Audit Trail)
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0 small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Penerima</th>
                                            <th>Subjek</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_emails as $em): ?>
                                            <tr>
                                                <td class="text-nowrap text-muted"><?= e(date('d/m/Y H:i', strtotime($em['created_at']))) ?></td>
                                                <td class="fw-semibold"><?= e($em['recipient_email']) ?></td>
                                                <td><?= e($em['subject']) ?></td>
                                                <td>
                                                    <?php if ($em['status'] === 'sent'): ?>
                                                        <span class="badge text-bg-success">Terkirim</span>
                                                    <?php elseif ($em['status'] === 'simulated'): ?>
                                                        <span class="badge text-bg-warning text-dark">Simulasi</span>
                                                    <?php else: ?>
                                                        <span class="badge text-bg-danger" title="<?= e($em['error_message']) ?>">Gagal</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Tombol Simpan Sticky di Layar Bawah -->
    <div class="position-sticky bottom-0 bg-white p-3 rounded-4 border shadow-lg mb-5 d-flex align-items-center justify-content-between" style="z-index: 1020;">
        <div class="small text-muted d-none d-md-block">
            <i class="fa-solid fa-shield-halved text-teal me-1"></i> Perubahan pengaturan dan rekening akan langsung aktif secara real-time di seluruh platform.
        </div>
        <div class="d-flex gap-2 ms-auto">
            <button type="submit" form="settingsForm" class="btn btn-teal px-4 py-2.5 fw-bold shadow-xs">
                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan Pengaturan
            </button>
        </div>
    </div>
</form>

<!-- Form Tersembunyi untuk Uji Coba Kirim Email -->
<form id="testEmailForm" method="POST" action="<?= BASE_URL ?>/admin/settings.php" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test_email">
    <input type="hidden" id="hiddenTestRecipient" name="test_recipient" value="">
</form>

<script>
function toggleSmtpPass() {
    const input = document.getElementById('inputSmtpPass');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
}

async function submitTestEmail() {
    const targetInput = document.getElementById('testEmailTarget');
    const btn = document.getElementById('btnTestEmail');
    const alertBox = document.getElementById('testEmailAlert');
    const form = document.getElementById('testEmailForm');

    if (!targetInput || !targetInput.value.trim()) {
        if (alertBox) {
            alertBox.innerHTML = '<div class="alert alert-warning py-2 px-3 small mb-0"><i class="fa-solid fa-triangle-exclamation me-1"></i> Silakan ketik alamat email tujuan untuk uji coba.</div>';
        }
        targetInput?.focus();
        return;
    }

    const email = targetInput.value.trim();
    
    // Status visual sedang mengirim
    const originalBtnHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5" role="status"></span> Mengirim via Webhook Google...';
    
    if (alertBox) {
        alertBox.innerHTML = '<div class="alert alert-info py-2.5 px-3 small mb-0 d-flex align-items-center gap-2"><div class="spinner-border spinner-border-sm text-info flex-shrink-0"></div><div>Sedang menghubungi Google Apps Script & mengirim email ke <strong>' + email + '</strong>... Mohon tunggu 5–15 detik.</div></div>';
    }

    try {
        const csrfToken = form.querySelector('input[name="csrf_token"]')?.value || '';
        const formData = new FormData();
        formData.append('action', 'test_email');
        formData.append('test_recipient', email);
        formData.append('csrf_token', csrfToken);
        formData.append('ajax', '1');

        const response = await fetch('<?= BASE_URL ?>/admin/settings.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            alertBox.innerHTML = '<div class="alert alert-success py-2.5 px-3 small mb-0 d-flex align-items-start gap-2"><i class="fa-solid fa-circle-check fs-5 text-success mt-0.5"></i><div><strong>Email Uji Coba BERHASIL Terkirim!</strong><br>' + data.message + ' Silakan buka kotak masuk (inbox) atau folder spam Gmail Anda sekarang.</div></div>';
        } else {
            alertBox.innerHTML = '<div class="alert alert-danger py-2.5 px-3 small mb-0 d-flex align-items-start gap-2"><i class="fa-solid fa-circle-xmark fs-5 text-danger mt-0.5"></i><div><strong>Pengiriman Gagal:</strong><br>' + data.message + '</div></div>';
        }
    } catch (err) {
        console.error(err);
        alertBox.innerHTML = '<div class="alert alert-danger py-2.5 px-3 small mb-0"><i class="fa-solid fa-circle-xmark me-1"></i> Terjadi kesalahan jaringan saat mengirim email uji coba. Silakan muat ulang halaman.</div>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalBtnHtml;
    }
}

function setLeadFee(val) {
    const input = document.getElementById('inputLeadFee');
    if (input) {
        input.value = val;
        updateSimulation();
    }
}

function updateSimulation() {
    const feeInput = document.getElementById('inputLeadFee');
    const bonusInput = document.getElementById('inputBonus');
    const simText = document.getElementById('simulasiCalcText');
    const badge = document.getElementById('promoBadge');

    if (!feeInput || !bonusInput || !simText || !badge) return;

    const fee = parseFloat(feeInput.value) || 0;
    const bonus = parseFloat(bonusInput.value) || 0;

    if (fee <= 0) {
        badge.className = 'badge bg-success text-white border-0';
        badge.innerHTML = '<i class="fa-solid fa-gift me-1"></i> Promo 100% GRATIS';
        simText.innerHTML = 'Biaya kontak <strong>Rp 0 (GRATIS)</strong>! Mitra bebas menerima semua pesanan tanpa potongan saldo dompet.';
    } else {
        const freeOrders = Math.floor(bonus / fee);
        if (fee < 3000) {
            badge.className = 'badge bg-info-subtle text-info border';
            badge.innerHTML = '<i class="fa-solid fa-tag me-1"></i> Diskon Promo (Rp ' + fee.toLocaleString('id-ID') + ')';
        } else if (fee === 3000) {
            badge.className = 'badge bg-light text-secondary border';
            badge.innerHTML = 'Tarif Normal (Rp 3.000)';
        } else {
            badge.className = 'badge bg-light text-dark border';
            badge.innerHTML = 'Tarif Kustom (Rp ' + fee.toLocaleString('id-ID') + ')';
        }

        simText.innerHTML = 'Bonus <strong>Rp ' + bonus.toLocaleString('id-ID') + '</strong> setara dengan <strong>' + freeOrders + ' pesanan pertama GRATIS</strong> bagi mitra baru tanpa deposit awal.';
    }
}

document.getElementById('inputLeadFee')?.addEventListener('input', updateSimulation);
document.getElementById('inputBonus')?.addEventListener('input', updateSimulation);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

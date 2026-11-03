<?php
/**
 * Halaman Konfirmasi Penerimaan Pesanan Langsung via Magic Link WhatsApp
 * JASA INHU - Solusi Praktis Mitra Tanpa Ribet Login, Keuntungan Platform Terjamin
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = 'Konfirmasi Penerimaan Pesanan - JASA INHU';

$request_id = (int)($_GET['id'] ?? 0);
$provider_id = (int)($_GET['p'] ?? 0);
$token = trim($_GET['t'] ?? '');

$error = '';
$success = false;
$order = null;
$provider = null;
$custWaUrl = '';
$lead_fee_deducted = 0;
$balance_after = 0;

$db = get_db();

// 1. Validasi Token Keamanan
if ($request_id <= 0 || $provider_id <= 0 || empty($token) || !verify_order_magic_token($request_id, $provider_id, $token)) {
    $error = 'Tautan konfirmasi tidak valid atau telah kedaluwarsa. Mohon periksa kembali pesan WhatsApp resmi dari Jasa Inhu.';
} else {
    // Ambil data pesanan
    $stmtReq = $db->prepare("
        SELECT sr.*, u.name as customer_name, u.phone as customer_phone, d.name as district_name, v.name as village_name, sc.name as category_name
        FROM service_requests sr
        JOIN users u ON sr.user_id = u.id
        LEFT JOIN service_categories sc ON sr.category_id = sc.id
        LEFT JOIN districts d ON sr.district_id = d.id
        LEFT JOIN villages v ON sr.village_id = v.id
        WHERE sr.id = ? AND sr.provider_id = ?
        LIMIT 1
    ");
    $stmtReq->execute([$request_id, $provider_id]);
    $order = $stmtReq->fetch();

    if (!$order) {
        $error = 'Pesanan tidak ditemukan atau bukan ditujukan untuk akun usaha Anda.';
    } else {
        // Ambil data mitra & akun pengguna mitra
        $stmtProv = $db->prepare("
            SELECT sp.*, u.id as owner_user_id, u.name as owner_name, u.phone as owner_phone, u.email as owner_email
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            WHERE sp.id = ?
            LIMIT 1
        ");
        $stmtProv->execute([$provider_id]);
        $provider = $stmtProv->fetch();

        // Siapkan link WhatsApp ke pelanggan
        $cleanCustPhone = preg_replace('/[^0-9]/', '', $order['customer_phone'] ?? '');
        if (str_starts_with($cleanCustPhone, '0')) {
            $cleanCustPhone = '62' . substr($cleanCustPhone, 1);
        } elseif (!str_starts_with($cleanCustPhone, '62')) {
            $cleanCustPhone = '62' . $cleanCustPhone;
        }

        $pLocation = trim(($order['village_name'] ?? '') . ', ' . ($order['district_name'] ?? ''));
        $waGreetMsg = "Halo Bapak/Ibu *" . ($order['customer_name'] ?? 'Pelanggan') . "*,\n\n"
                    . "Saya *" . ($provider['business_name'] ?? 'Penyedia Jasa') . "* dari platform *JASA INHU* terkait pesanan Anda No. *#" . $request_id . "*:\n"
                    . "📌 *Layanan*: " . $order['title'] . "\n\n"
                    . "Pesanan sudah saya terima dan siap dikerjakan. Boleh kami konfirmasi patokan alamat di " . ($pLocation ?: 'Inhu') . " dan waktu yang tepat? Terima kasih! 🙏";
        $custWaUrl = "https://wa.me/" . $cleanCustPhone . "?text=" . urlencode($waGreetMsg);

        // Jika form POST disubmit (Aksi Terima Pesanan)
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_accept') {
            $result = accept_direct_service_order($request_id, $provider_id);

            if ($result['success']) {
                $success = true;
                $lead_fee_deducted = $result['lead_fee_deducted'] ?? 0;
                $balance_after = $result['balance_after'] ?? 0;

                // Otomatis login session mitra di HP
                if (!is_logged_in() || (int)current_user()['id'] !== (int)$provider['owner_user_id']) {
                    $_SESSION['user_id'] = (int)$provider['owner_user_id'];
                    $_SESSION['user_name'] = $provider['owner_name'];
                    $_SESSION['user_email'] = $provider['owner_email'];
                    $_SESSION['user_role'] = 'penyedia';
                }

                // Segera refresh status order
                $order['status'] = 'in_progress';
                $order['progress_step'] = 'accepted';
            } else {
                $error = $result['message'];
            }
        } elseif ($order['status'] !== 'open') {
            // Sudah pernah diterima sebelumnya
            $success = true;
            $balance_after = (float)($provider['wallet_balance'] ?? 0);
            
            // Login otomatis jika belum
            if (!is_logged_in() || (int)current_user()['id'] !== (int)$provider['owner_user_id']) {
                $_SESSION['user_id'] = (int)$provider['owner_user_id'];
                $_SESSION['user_name'] = $provider['owner_name'];
                $_SESSION['user_email'] = $provider['owner_email'];
                $_SESSION['user_role'] = 'penyedia';
            }
        }
    }
}

$lead_fee_amount = (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE);
$provider_balance = (float)($provider['wallet_balance'] ?? 0);
$can_accept = ($provider_balance >= $lead_fee_amount);

// Admin WhatsApp for quick topup help
$adminWa = get_setting('admin_whatsapp') ?: (get_setting('admin_phone') ?: '085126241679');
$adminCleanWa = preg_replace('/[^0-9]/', '', $adminWa);
if (str_starts_with($adminCleanWa, '0')) $adminCleanWa = '62' . substr($adminCleanWa, 1);
$adminTopupUrl = "https://wa.me/{$adminCleanWa}?text=" . urlencode("Halo Admin Jasa Inhu, saya mitra *" . ($provider['business_name'] ?? 'Mitra') . "* ingin isi saldo deposit kuota pesanan. Mohon bantuannya ya.");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #00AA5B;
            --brand-dark: #042f2e;
            --brand-teal: #0d9488;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
        }
        .accept-card {
            max-width: 480px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .header-gradient {
            background: linear-gradient(135deg, #042f2e 0%, #0d9488 100%);
            padding: 1.75rem 1.25rem;
            color: #ffffff;
            text-align: center;
            position: relative;
        }
        .btn-wa-action {
            background: #25D366;
            color: #ffffff;
            font-weight: 700;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-wa-action:hover, .btn-wa-action:focus {
            background: #1eb855;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 211, 102, 0.35);
        }
        .btn-accept-big {
            background: linear-gradient(135deg, #00AA5B 0%, #059669 100%);
            color: #ffffff;
            font-weight: 800;
            border: none;
            padding: 0.95rem 1.25rem;
            font-size: 1.05rem;
            border-radius: 999px;
            transition: all 0.25s ease;
            box-shadow: 0 6px 18px rgba(0, 170, 91, 0.3);
        }
        .btn-accept-big:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 170, 91, 0.4);
            color: #ffffff;
        }
        .info-pill {
            background: #f1f5f9;
            border-radius: 14px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
        }
        .pulse-btn {
            animation: pulse-green 2s infinite;
        }
        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); }
            70% { box-shadow: 0 0 0 14px rgba(37, 211, 102, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
        }
    </style>
</head>
<body class="py-3 py-md-5">

<div class="container px-3">
    <div class="accept-card">

        <!-- Top Header Banner -->
        <div class="header-gradient">
            <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle p-2 shadow-sm mb-2" style="width: 52px; height: 52px;">
                <img src="<?= BASE_URL ?>/assets/images/logo-jasa-inhu.png" alt="Jasa Inhu" style="height: 32px; width: auto;" onerror="this.src='https://placehold.co/100x100/00AA5B/ffffff?text=JI';">
            </div>
            <h5 class="fw-bold mb-0 text-white">JASA INHU</h5>
            <div class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill mt-1" style="font-size: 0.75rem;">
                <i class="fa-solid fa-bolt me-1"></i> Fast-Track Konfirmasi Pesanan
            </div>
        </div>

        <div class="p-3.5 p-md-4">
            <?php if (!empty($error) && !$success): ?>
                <!-- Error State -->
                <div class="text-center py-4">
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 64px; height: 64px;">
                        <i class="fa-solid fa-triangle-exclamation fs-2"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Mohon Maaf</h5>
                    <p class="text-muted small mb-4 px-2"><?= htmlspecialchars($error) ?></p>

                    <?php if (isset($can_accept) && !$can_accept): ?>
                        <div class="alert alert-warning text-start small mb-4 rounded-3 border-0 bg-warning-subtle">
                            <div class="fw-bold text-dark mb-1"><i class="fa-solid fa-wallet me-1"></i> Saldo Deposit Kurang</div>
                            <div>Saldo Anda: <strong>Rp <?= number_format($provider_balance, 0, ',', '.') ?></strong></div>
                            <div>Dibutuhkan: <strong>Rp <?= number_format($lead_fee_amount, 0, ',', '.') ?></strong></div>
                        </div>
                        <a href="<?= $adminTopupUrl ?>" target="_blank" class="btn btn-success fw-bold rounded-pill w-100 py-2.5 mb-2 shadow-sm">
                            <i class="fa-brands fa-whatsapp me-1"></i> Chat Admin untuk Top Up Saldo Instan
                        </a>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-outline-secondary rounded-pill w-100 py-2">
                        Buka Dashboard Mitra
                    </a>
                </div>

            <?php elseif ($success): ?>
                <!-- Success State: Order Accepted! -->
                <div class="text-center py-2">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center p-3 mb-2" style="width: 68px; height: 68px;">
                        <i class="fa-solid fa-circle-check fs-1"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Pesanan Berhasil Diterima!</h5>
                    <p class="text-muted small mb-3">
                        Nomor pesanan: <strong class="text-teal">#<?= $request_id ?></strong>
                        <?php if ($lead_fee_deducted > 0): ?>
                            <br><span class="text-success fw-semibold">Biaya kontak Rp <?= number_format($lead_fee_deducted, 0, ',', '.') ?> telah dipotong dari saldo Anda.</span>
                        <?php endif; ?>
                    </p>

                    <!-- Contact Details Box -->
                    <div class="info-pill text-start mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="fa-solid fa-user small"></i>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size: 0.7rem;">Pemesan:</div>
                                <strong class="text-dark"><?= htmlspecialchars($order['customer_name'] ?? 'Pelanggan') ?></strong>
                            </div>
                        </div>

                        <div class="small mb-1.5">
                            <i class="fa-solid fa-phone text-primary me-1.5"></i>
                            <strong>No. WhatsApp / HP:</strong>
                            <a href="tel:<?= htmlspecialchars($order['customer_phone'] ?? '') ?>" class="text-decoration-none fw-bold text-dark ms-1">
                                <?= htmlspecialchars($order['customer_phone'] ?? '-') ?>
                            </a>
                        </div>

                        <div class="small mb-1.5">
                            <i class="fa-solid fa-location-dot text-danger me-1.5"></i>
                            <strong>Lokasi:</strong>
                            <span class="text-dark"><?= htmlspecialchars($order['address_detail'] ?: ($order['village_name'] . ', ' . $order['district_name'])) ?></span>
                        </div>

                        <div class="small">
                            <i class="fa-solid fa-wrench text-warning me-1.5"></i>
                            <strong>Keluhan / Layanan:</strong>
                            <span class="text-dark"><?= htmlspecialchars($order['title']) ?></span>
                        </div>
                    </div>

                    <!-- Main Action: Open WhatsApp -->
                    <div class="d-grid gap-2 mb-3">
                        <a href="<?= $custWaUrl ?>" class="btn btn-wa-action btn-lg py-3 rounded-pill d-flex align-items-center justify-content-center gap-2 pulse-btn shadow">
                            <i class="fa-brands fa-whatsapp fs-4"></i>
                            <span>CHAT WHATSAPP PEMESAN SEKARANG</span>
                        </a>

                        <a href="tel:<?= htmlspecialchars($order['customer_phone'] ?? '') ?>" class="btn btn-outline-primary py-2.5 rounded-pill fw-semibold">
                            <i class="fa-solid fa-phone me-1"></i> Telepon Pemesan Langsung
                        </a>

                        <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-light py-2 rounded-pill text-muted small">
                            <i class="fa-solid fa-gauge me-1"></i> Buka Halaman Kerja di Dashboard
                        </a>
                    </div>

                    <div class="alert alert-info border-0 bg-info-subtle small text-start py-2 px-3 rounded-3 mb-0" style="font-size: 0.75rem;">
                        <i class="fa-solid fa-shield-halved text-info me-1"></i> <strong>Selesai Pengerjaan:</strong> Jangan lupa klik "Selesaikan Pekerjaan" di dashboard agar pelanggan dapat memberikan ulasan bintang 5 untuk usaha Anda!
                    </div>
                </div>

            <?php else: ?>
                <!-- Pending Confirmation State: Review Order Details & 1-Click Accept -->
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge bg-success-subtle text-success fw-bold px-2.5 py-1.5 rounded-pill">
                            <i class="fa-solid fa-bell me-1"></i> Pesanan Baru Menunggu
                        </span>
                        <span class="text-muted small">No: #<?= $request_id ?></span>
                    </div>

                    <!-- Mitra Target Box -->
                    <div class="p-2.5 rounded-3 bg-light border mb-3 d-flex align-items-center gap-2.5">
                        <div class="bg-teal text-white rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; background: #0d9488;">
                            <i class="fa-solid fa-store"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="text-muted" style="font-size: 0.68rem;">Ditujukan untuk usaha:</div>
                            <strong class="text-dark text-truncate d-block" style="font-size: 0.88rem;"><?= htmlspecialchars($provider['business_name'] ?? 'Mitra Jasa') ?></strong>
                        </div>
                    </div>

                    <!-- Job Details -->
                    <div class="info-pill mb-3">
                        <h6 class="fw-bold text-dark mb-1">
                            <i class="fa-solid fa-wrench text-teal me-1"></i> <?= htmlspecialchars($order['title']) ?>
                        </h6>
                        <p class="text-muted small mb-2" style="white-space: pre-line; line-height: 1.4;">
                            <?= htmlspecialchars(mb_substr($order['description'], 0, 300)) ?><?= mb_strlen($order['description']) > 300 ? '...' : '' ?>
                        </p>

                        <div class="pt-2 border-top small">
                            <div class="mb-1 text-muted">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                <strong>Patokan Alamat:</strong> <?= htmlspecialchars($order['address_detail'] ?: ($order['village_name'] . ', ' . $order['district_name'])) ?>
                            </div>
                            <div class="text-muted">
                                <i class="fa-solid fa-clock text-primary me-1"></i>
                                <strong>Waktu Diminta:</strong> <?= htmlspecialchars($order['urgency'] === 'high' ? 'Darurat / Segera' : 'Hari ini') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Wallet & Fee Transparency -->
                    <div class="p-3 rounded-3 border mb-3" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">Biaya Kontak Platform:</span>
                            <strong class="text-dark"><?= $lead_fee_amount > 0 ? 'Rp ' . number_format($lead_fee_amount, 0, ',', '.') : 'GRATIS (Rp 0)' ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">Saldo Dompet Anda:</span>
                            <strong class="<?= $can_accept ? 'text-success' : 'text-danger' ?>">Rp <?= number_format($provider_balance, 0, ',', '.') ?></strong>
                        </div>
                        <div class="small text-muted border-top border-success-subtle pt-1 mt-1" style="font-size: 0.72rem;">
                            <i class="fa-solid fa-circle-check text-success me-1"></i> Tanpa potongan komisi. Pembayaran pelanggan 100% hak Anda!
                        </div>
                    </div>

                    <?php if ($can_accept): ?>
                        <!-- Form 1-Click Accept -->
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="confirm_accept">
                            <button type="submit" class="btn btn-accept-big w-100 mb-2">
                                <i class="fa-solid fa-check-circle me-1"></i> TERIMA & HUBUNGI PEMESAN
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Insufficient balance alert -->
                        <div class="alert alert-danger small py-2 px-3 mb-2 rounded-3">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Saldo dompet Anda tidak cukup untuk menerima pesanan ini.
                        </div>
                        <a href="<?= $adminTopupUrl ?>" target="_blank" class="btn btn-success fw-bold rounded-pill w-100 py-2.5 mb-2 shadow-sm">
                            <i class="fa-brands fa-whatsapp me-1"></i> Top Up Saldo Cepat via Admin
                        </a>
                    <?php endif; ?>

                    <div class="text-center mt-2">
                        <a href="<?= BASE_URL ?>/provider/index.php" class="text-muted small text-decoration-none">
                            <i class="fa-solid fa-arrow-left me-1"></i> Masuk ke Dashboard Lengkap
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="bg-light px-3 py-2.5 border-top text-center text-muted" style="font-size: 0.72rem;">
            Platform Resmi JASA INHU • Menghubungkan Warga & Jasa Terpercaya
        </div>
    </div>
</div>

</body>
</html>

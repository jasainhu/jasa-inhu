<?php
/**
 * Halaman Manajemen Dompet Deposit & Biaya Kontak (Lead Fee) Mitra
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */

$page_title = 'Dompet & Saldo Deposit Mitra';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('penyedia');

$user = current_user();
$db = get_db();
$error = '';
$success = '';

// Ambil data profil mitra
$stmtProv = $db->prepare("
    SELECT sp.*, sc.name as category_name, sc.icon as category_icon, d.name as district_name
    FROM service_providers sp
    JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    WHERE sp.user_id = ?
    LIMIT 1
");
$stmtProv->execute([$user['id']]);
$provider = $stmtProv->fetch();

if (!$provider) {
    set_flash('danger', 'Data profil mitra belum terdaftar.');
    redirect('/provider/profile.php');
}

// Handle Form Pengajuan Top-up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'];

        if ($action === 'request_topup') {
            $amount = (float)($_POST['amount'] ?? 0);
            $payment_method = trim($_POST['payment_method'] ?? 'Transfer Bank');

            if ($amount < MIN_TOPUP_AMOUNT) {
                $error = 'Nominal isi saldo minimal adalah Rp ' . number_format(MIN_TOPUP_AMOUNT, 0, ',', '.') . '.';
            } else {
                try {
                    $proof_image = null;
                    if (!empty($_FILES['proof_image']['name']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                        if (in_array($ext, $allowed)) {
                            $uploadDir = __DIR__ . '/../uploads/topups/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                            $filename = 'topup_' . $provider['id'] . '_' . time() . '.' . $ext;
                            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $uploadDir . $filename)) {
                                $proof_image = 'uploads/topups/' . $filename;
                            }
                        }
                    }

                    $stmtIns = $db->prepare("
                        INSERT INTO wallet_topup_requests 
                        (provider_id, amount, payment_method, proof_image, status, created_at)
                        VALUES (?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmtIns->execute([$provider['id'], $amount, $payment_method, $proof_image]);

                    $topup_id = $db->lastInsertId();

                    // Format pesan WhatsApp ke Admin
                    $waMsg = "Halo Admin JASA INHU, saya mengajukan konfirmasi Top-Up Saldo Dompet Mitra:\n\n"
                           . "• ID Top-Up: #" . $topup_id . "\n"
                           . "• Usaha: " . $provider['business_name'] . "\n"
                           . "• Pemilik: " . $user['name'] . " (" . $user['phone'] . ")\n"
                           . "• Nominal: Rp " . number_format($amount, 0, ',', '.') . "\n"
                           . "• Metode: " . $payment_method . "\n\n"
                           . "Mohon bantu verifikasi dan tambahkan ke saldo deposit saya. Terima kasih!";

                    $adminPhone = get_setting('admin_wa', ADMIN_PHONE_WA);
                    $adminWaUrl = format_wa_url($adminPhone, $waMsg);

                    set_flash('success', 'Permintaan isi saldo sebesar Rp ' . number_format($amount, 0, ',', '.') . ' berhasil dicatat! Silakan kirimkan bukti transfer melalui WhatsApp Admin di bawah ini.');
                    redirect('/provider/wallet.php?wa_redirect=' . urlencode($adminWaUrl));
                } catch (Exception $e) {
                    $error = 'Gagal mengajukan isi saldo: ' . $e->getMessage();
                }
            }
        }
    }
}

// Ambil Riwayat Mutasi Saldo
$stmtTx = $db->prepare("
    SELECT * FROM provider_wallet_transactions
    WHERE provider_id = ?
    ORDER BY created_at DESC
    LIMIT 30
");
$stmtTx->execute([$provider['id']]);
$transactions = $stmtTx->fetchAll();

// Ambil Permintaan Top-up Pending
$stmtPending = $db->prepare("
    SELECT * FROM wallet_topup_requests
    WHERE provider_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmtPending->execute([$provider['id']]);
$topup_requests = $stmtPending->fetchAll();

$current_balance = (float)($provider['wallet_balance'] ?? 0.00);
$lead_fee_amount = (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE);
$est_orders = $lead_fee_amount > 0 ? floor($current_balance / $lead_fee_amount) : 'Tak Terbatas';

// Hitung total penghasilan yang telah didapatkan mitra dari order selesai di JASA INHU
$stmtRevTotal = $db->prepare("
    SELECT SUM(COALESCE(sr.final_price, srr.offer_price, sr.budget, 0))
    FROM service_requests sr
    LEFT JOIN service_request_responses srr ON (srr.request_id = sr.id AND srr.provider_id = ? AND srr.status = 'accepted')
    WHERE sr.status = 'completed'
      AND (sr.provider_id = ? OR srr.id IS NOT NULL)
");
$stmtRevTotal->execute([$provider['id'], $provider['id']]);
$total_earned_by_provider = (float)($stmtRevTotal->fetchColumn() ?: 0.00);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb & Navigasi -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/provider/index.php" class="text-teal text-decoration-none">Dashboard Mitra</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dompet & Saldo Deposit</li>
            </ol>
        </nav>
        <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger mb-4"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['wa_redirect'])): ?>
        <div class="alert alert-success alert-dismissible fade show p-3 mb-4 rounded-3 shadow-sm border-0 bg-success bg-opacity-10" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-brands fa-whatsapp text-success fs-1"></i>
                <div class="flex-grow-1">
                    <h6 class="fw-bold text-dark mb-1">Kirim Bukti Pembayaran ke WhatsApp Admin</h6>
                    <p class="text-muted small mb-2">Klik tombol di bawah ini untuk membuka WhatsApp Admin JASA INHU dan verifikasi saldo Anda segera diaktifkan.</p>
                    <a href="<?= e($_GET['wa_redirect']) ?>" target="_blank" class="btn btn-success btn-sm fw-bold px-3 py-1.5 shadow-xs">
                        <i class="fa-brands fa-whatsapp me-1"></i> Buka WhatsApp Admin Sekarang
                    </a>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Kartu Saldo Utama (Hero Card) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden text-white" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-white bg-opacity-15 mb-3 small fw-semibold">
                        <i class="fa-solid fa-gift text-warning"></i>
                        <span>PROMO PELUNCURAN INHU: 15 PESANAN PERTAMA GRATIS</span>
                    </div>
                    <div class="text-white-50 small text-uppercase fw-semibold mb-1">Saldo Kuota Deposit Pesanan:</div>
                    <div class="display-4 fw-bold text-white mb-1">
                        Rp <?= number_format($current_balance, 0, ',', '.') ?>
                    </div>
                    <div class="d-inline-flex align-items-center gap-1.5 px-2.5 py-1 rounded-pill bg-black bg-opacity-30 border border-white border-opacity-20 text-white-50 small mb-3" style="font-size: 0.72rem;">
                        <i class="fa-solid fa-lock text-warning"></i>
                        <span>Kredit Kuota Aplikasi &bull; Non-Tunai / Tidak Dapat Ditarik ke Rekening</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3 text-white-50 small">
                        <span>
                            <i class="fa-solid fa-bolt text-warning me-1"></i>
                            Kapasitas: <strong class="text-white">~<?= $est_orders ?> Pesanan Tersedia</strong>
                        </span>
                        <span>&bull;</span>
                        <span>
                            <i class="fa-solid fa-coins text-warning me-1"></i>
                            Total Omzet Didapat: <strong class="text-white">Rp <?= number_format($total_earned_by_provider, 0, ',', '.') ?></strong>
                        </span>
                        <span>&bull;</span>
                        <span>
                            <i class="fa-solid fa-tag text-info me-1"></i>
                            Biaya Flat: <strong class="text-white"><?= $lead_fee_amount > 0 ? 'Rp ' . number_format($lead_fee_amount, 0, ',', '.') : 'GRATIS (Rp 0)' ?> / Pesanan</strong>
                        </span>
                    </div>
                </div>
                <div class="col-lg-5 text-lg-end">
                    <button type="button" class="btn btn-warning fw-bold px-4 py-3 shadow rounded-3 fs-6 d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#topupModal">
                        <i class="fa-solid fa-circle-plus fs-5"></i>
                        <span>Isi Saldo (Top-Up) Sekarang</span>
                    </button>
                    <div class="text-white-50 small mt-2">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Proses verifikasi cepat via WhatsApp Admin
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edukasi Manfaat Sistem Biaya Kontak Flat -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex align-items-center gap-2.5 mb-2">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success p-2">
                        <i class="fa-solid fa-coins fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0">Biaya Murah & Flat</h6>
                </div>
                <p class="text-muted small mb-0" style="line-height: 1.45;">
                    <?= $lead_fee_amount > 0 ? 'Hanya dipotong <strong>Rp ' . number_format($lead_fee_amount, 0, ',', '.') . '</strong> saat Anda mengambil order. Tidak ada potongan persentase persenan yang memberatkan.' : 'Saat ini berlaku <strong>Promo Bebas Biaya Kontak (GRATIS Rp 0)</strong>! Seluruh pesanan dapat diambil tanpa memotong saldo deposit Anda.' ?>
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex align-items-center gap-2.5 mb-2">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2">
                        <i class="fa-solid fa-wrench fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0">Sparepart 100% Milik Anda</h6>
                </div>
                <p class="text-muted small mb-0" style="line-height: 1.45;">
                    Jika ada pergantian onderdil motor/AC senilai Rp 200.000 atau lebih, seluruh pembayaran pelanggan 100% jadi hak Anda tanpa potongan aplikasi.
                </p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex align-items-center gap-2.5 mb-2">
                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-2">
                        <i class="fa-solid fa-award fs-5"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0">Prioritas Order Nomor 1</h6>
                </div>
                <p class="text-muted small mb-0" style="line-height: 1.45;">
                    Mitra dengan saldo aktif selalu diprioritaskan tampil di urutan atas pencarian warga Kab. Indragiri Hulu dan langsung menerima pesanan kilat.
                </p>
            </div>
        </div>
    </div>

    <!-- Riwayat Permintaan Top-up Pending (Jika Ada) -->
    <?php if (!empty($topup_requests)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-receipt text-teal me-1"></i> Pengajuan Isi Saldo Terakhir
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Nominal</th>
                            <th>Metode Pembayaran</th>
                            <th>Status</th>
                            <th>Keterangan Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topup_requests as $tr): ?>
                            <tr>
                                <td class="fw-bold">#<?= $tr['id'] ?></td>
                                <td><?= format_date($tr['created_at'], true) ?></td>
                                <td class="fw-bold text-teal">Rp <?= number_format((float)$tr['amount'], 0, ',', '.') ?></td>
                                <td><?= e($tr['payment_method']) ?></td>
                                <td>
                                    <?php if ($tr['status'] === 'pending'): ?>
                                        <span class="badge text-bg-warning">Menunggu Konfirmasi Admin</span>
                                    <?php elseif ($tr['status'] === 'approved'): ?>
                                        <span class="badge text-bg-success">Berhasil Masuk Saldo</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger">Ditolak</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= e($tr['admin_notes'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Riwayat Mutasi Dompet (Log Transaksi) -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-clock-rotate-left text-teal me-1"></i> Riwayat Mutasi Saldo Dompet
            </h6>
            <span class="small text-muted">Menampilkan 30 riwayat terakhir</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 20%;">Waktu</th>
                        <th style="width: 15%;">Tipe Mutasi</th>
                        <th style="width: 40%;">Rincian & Keterangan</th>
                        <th style="width: 10%;" class="text-end">Nominal</th>
                        <th style="width: 10%;" class="text-end">Sisa Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $idx => $tx): ?>
                            <?php $isCredit = ((float)$tx['amount'] > 0); ?>
                            <tr>
                                <td class="text-muted"><?= $idx + 1 ?></td>
                                <td><?= format_date($tx['created_at'], true) ?></td>
                                <td>
                                    <?php if ($tx['type'] === 'lead_fee'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="fa-solid fa-minus me-0.5"></i> Biaya Kontak
                                        </span>
                                    <?php elseif ($tx['type'] === 'topup'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="fa-solid fa-plus me-0.5"></i> Top-Up Saldo
                                        </span>
                                    <?php elseif ($tx['type'] === 'bonus'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                            <i class="fa-solid fa-gift me-0.5"></i> Bonus Saldo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Penyesuaian</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($tx['description']) ?></div>
                                    <?php if (!empty($tx['reference_id'])): ?>
                                        <div class="text-muted" style="font-size: 0.72rem;">ID Pesanan: #<?= $tx['reference_id'] ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold font-monospace <?= $isCredit ? 'text-success' : 'text-danger' ?>">
                                    <?= $isCredit ? '+' : '' ?>Rp <?= number_format((float)$tx['amount'], 0, ',', '.') ?>
                                </td>
                                <td class="text-end fw-bold text-dark font-monospace">
                                    Rp <?= number_format((float)$tx['balance_after'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                Belum ada riwayat transaksi mutasi saldo.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Isi Saldo (Top-Up) -->
<div class="modal fade" id="topupModal" tabindex="-1" aria-labelledby="topupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-wallet fs-4 text-warning"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="topupModalLabel">Isi Saldo Dompet Deposit</h5>
                        <span class="text-white-50 small" style="font-size: 0.78rem;">Dapatkan kuota pesanan pelanggan tanpa potongan komisi</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="<?= BASE_URL ?>/provider/wallet.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="request_topup">

                <div class="modal-body p-4">
                    <!-- Pilihan Paket Cepat -->
                    <label class="form-label fw-bold text-dark small mb-2">1. Pilih Nominal Top-Up:</label>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-teal w-100 py-2.5 text-center rounded-3 quick-amount-btn" onclick="selectAmount(20000)">
                                <div class="fw-bold small">Rp 20.000</div>
                                <div class="text-muted" style="font-size: 0.68rem;">~6 Pesanan</div>
                            </button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-teal w-100 py-2.5 text-center rounded-3 quick-amount-btn active" onclick="selectAmount(50000)">
                                <div class="fw-bold small text-warning">Rp 50.000</div>
                                <div class="text-muted" style="font-size: 0.68rem;">~16 Pesanan</div>
                            </button>
                        </div>
                        <div class="col-4">
                            <button type="button" class="btn btn-outline-teal w-100 py-2.5 text-center rounded-3 quick-amount-btn" onclick="selectAmount(100000)">
                                <div class="fw-bold small">Rp 100.000</div>
                                <div class="text-muted" style="font-size: 0.68rem;">~33 Pesanan</div>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Atau masukkan nominal manual (Rp):</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">Rp</span>
                            <input type="number" id="inputTopupAmount" name="amount" class="form-control fw-bold fs-5 text-teal" value="50000" min="<?= MIN_TOPUP_AMOUNT ?>" step="5000" required>
                        </div>
                        <span class="text-muted" style="font-size: 0.72rem;">Minimal isi saldo: Rp <?= number_format(MIN_TOPUP_AMOUNT, 0, ',', '.') ?></span>
                    </div>

                    <!-- Rekening Pembayaran -->
                    <label class="form-label fw-bold text-dark small mb-2">2. Transfer ke Rekening & E-Wallet Resmi JASA INHU:</label>
                    <div class="p-3 bg-light rounded-3 border mb-3 small">
                        <?php 
                        $b1_name = get_setting('bank_name_1', '');
                        $b1_acc = get_setting('bank_acc_1', '');
                        $b1_owner = get_setting('bank_owner_1', '');

                        $b2_name = get_setting('bank_name_2', '');
                        $b2_acc = get_setting('bank_acc_2', '');
                        $b2_owner = get_setting('bank_owner_2', '');

                        $ew_name = get_setting('ewallet_name', '');
                        $ew_acc = get_setting('ewallet_acc', '');
                        $ew_owner = get_setting('ewallet_owner', '');

                        $qris_note = get_setting('qris_info', 'Scan QRIS Langsung via WhatsApp Admin');
                        ?>

                        <?php if ($b1_name && $b1_acc): ?>
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <div>
                                <span class="badge text-bg-primary me-1"><i class="fa-solid fa-building-columns me-1"></i><?= e($b1_name) ?></span>
                                <strong class="text-dark font-monospace"><?= e($b1_acc) ?></strong>
                            </div>
                            <span class="text-muted">a.n. <strong><?= e($b1_owner ?: 'Admin JASA INHU') ?></strong></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($b2_name && $b2_acc): ?>
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <div>
                                <span class="badge text-bg-secondary me-1"><i class="fa-solid fa-building-columns me-1"></i><?= e($b2_name) ?></span>
                                <strong class="text-dark font-monospace"><?= e($b2_acc) ?></strong>
                            </div>
                            <span class="text-muted">a.n. <strong><?= e($b2_owner ?: 'Admin JASA INHU') ?></strong></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($ew_name && $ew_acc): ?>
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <div>
                                <span class="badge text-bg-success me-1"><i class="fa-solid fa-mobile-screen-button me-1"></i><?= e($ew_name) ?></span>
                                <strong class="text-dark font-monospace"><?= e($ew_acc) ?></strong>
                            </div>
                            <span class="text-muted">a.n. <strong><?= e($ew_owner ?: 'Admin JASA INHU') ?></strong></span>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="badge text-bg-dark me-1"><i class="fa-solid fa-qrcode me-1"></i>QRIS</span>
                                <strong class="text-dark">Semua Pembayaran Digital</strong>
                            </div>
                            <span class="text-teal fw-semibold"><?= e($qris_note) ?></span>
                        </div>
                    </div>

                    <!-- Pilihan Metode -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Metode Pembayaran yang Anda Gunakan:</label>
                        <select name="payment_method" class="form-select form-select-sm">
                            <?php if ($b1_name): ?>
                                <option value="Transfer <?= e($b1_name) ?>">Transfer <?= e($b1_name) ?></option>
                            <?php else: ?>
                                <option value="Transfer Bank">Transfer Bank</option>
                            <?php endif; ?>

                            <?php if ($b2_name): ?>
                                <option value="Transfer <?= e($b2_name) ?>">Transfer <?= e($b2_name) ?></option>
                            <?php endif; ?>

                            <?php if ($ew_name): ?>
                                <option value="E-Wallet <?= e($ew_name) ?>">E-Wallet <?= e($ew_name) ?></option>
                            <?php else: ?>
                                <option value="E-Wallet (DANA/OVO/GoPay)">E-Wallet (DANA / OVO / GoPay)</option>
                            <?php endif; ?>

                            <option value="Scan QRIS">Scan Barcode QRIS</option>
                            <option value="Setor Tunai Langsung">Setor Tunai Langsung</option>
                        </select>
                    </div>

                    <!-- Unggah Bukti Transfer (Opsional) -->
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted">Unggah Bukti Struk Transfer (Opsional):</label>
                        <input type="file" name="proof_image" class="form-control form-control-sm" accept="image/*">
                        <span class="text-muted" style="font-size: 0.72rem;">Bisa juga langsung kirim foto struk ke WhatsApp Admin setelah klik tombol di bawah.</span>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal text-white fw-bold px-4 py-2 shadow-xs">
                        <i class="fa-solid fa-paper-plane me-1"></i> Ajukan & Konfirmasi via WA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function selectAmount(amt) {
    document.getElementById('inputTopupAmount').value = amt;
    document.querySelectorAll('.quick-amount-btn').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

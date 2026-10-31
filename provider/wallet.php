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
                    if (!empty($_FILES['proof_image']['name']) && $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        try {
                            $proof_image = save_uploaded_media($_FILES['proof_image'], 'topups', 'topup_' . $provider['id'], 1200, 85);
                        } catch (Exception $e) {
                            // Abaikan error jika bukti transfer opsional saat buat tiket
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

// URL Bantuan / Pengembalian (Refund) Sisa Saldo Top-Up ke Admin via WhatsApp
$adminPhone = get_setting('admin_wa', ADMIN_PHONE_WA);
$refundMsg = "Halo Admin JASA INHU,\n" .
             "Saya mitra " . $provider['business_name'] . " (" . $user['name'] . " - " . $user['phone'] . ")\n" .
             "Ingin mengajukan informasi / pengembalian (refund) sisa saldo top-up mandiri saya di akun aplikasi.\n\n" .
             "Mohon bantuannya. Terima kasih!";
$refundWaUrl = format_wa_url($adminPhone, $refundMsg);

$hide_ai_widget = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-3 py-md-4" style="max-width: 920px;">
    <!-- Header Ramping & Bersih -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 36px; height: 36px;" title="Kembali ke Dashboard">
                <i class="fa-solid fa-arrow-left text-dark"></i>
            </a>
            <div>
                <h5 class="fw-bold text-dark mb-0 fs-6">Dompet & Saldo Deposit</h5>
                <span class="text-muted small" style="font-size: 0.72rem;">Kuota kontak pesanan mitra Kab. Indragiri Hulu</span>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-warning fw-bold px-3 py-1.5 rounded-pill shadow-xs d-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#topupModal">
            <i class="fa-solid fa-circle-plus"></i>
            <span>Isi Saldo</span>
        </button>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger mb-3 p-2.5 small rounded-3"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['wa_redirect'])): ?>
        <div class="alert alert-success alert-dismissible fade show p-3 mb-3 rounded-3 shadow-xs border-0 bg-success bg-opacity-10" role="alert">
            <div class="d-flex align-items-center gap-2.5">
                <i class="fa-brands fa-whatsapp text-success fs-3 flex-shrink-0"></i>
                <div class="flex-grow-1">
                    <h6 class="fw-bold text-dark mb-0.5" style="font-size: 0.85rem;">Kirim Bukti Pembayaran ke WhatsApp Admin</h6>
                    <p class="text-muted small mb-2" style="font-size: 0.75rem;">Konfirmasi bukti transfer agar saldo deposit segera aktif.</p>
                    <a href="<?= e($_GET['wa_redirect']) ?>" target="_blank" class="btn btn-success btn-sm fw-bold px-3 py-1 shadow-xs" style="font-size: 0.78rem;">
                        <i class="fa-brands fa-whatsapp me-1"></i> Buka WhatsApp Admin Sekarang
                    </a>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Kartu Saldo Utama (Ramping & Modern ala E-Wallet) -->
    <div class="card border-0 shadow-sm rounded-4 mb-3 overflow-hidden text-white" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
        <div class="card-body p-3 p-md-4">
            <!-- Badge Promo Peluncuran dengan Kontras Jelas -->
            <div class="d-inline-flex align-items-center gap-1.5 px-2.5 py-1 rounded-pill mb-2.5 fw-semibold shadow-xs" style="background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(253, 224, 71, 0.4); font-size: 0.72rem; color: #fde047;">
                <i class="fa-solid fa-gift text-warning"></i>
                <span>PROMO PELUNCURAN INHU: 15 PESANAN PERTAMA GRATIS</span>
            </div>

            <div class="row align-items-center g-3">
                <div class="col-sm-7">
                    <div class="text-white-50 text-uppercase fw-semibold" style="font-size: 0.70rem; letter-spacing: 0.5px;">Saldo Kuota Pesanan Mitra (Non-Tunai):</div>
                    <div class="fs-2 fw-bold text-white my-1" style="letter-spacing: -0.5px;">
                        Rp <?= number_format($current_balance, 0, ',', '.') ?>
                    </div>
                    <div class="d-inline-flex align-items-center gap-1.5 px-2.5 py-1 rounded-pill mb-2" style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.15); font-size: 0.70rem; color: #fff;">
                        <i class="fa-solid fa-hand-holding-dollar text-warning"></i>
                        <span>Uang jasa pelanggan 100% diterima tunai di tempat</span>
                    </div>
                </div>
                <div class="col-sm-5 text-sm-end">
                    <button type="button" class="btn btn-warning fw-bold px-3 py-2 rounded-3 shadow-sm d-inline-flex align-items-center gap-1.5 w-100 w-sm-auto justify-content-center" data-bs-toggle="modal" data-bs-target="#topupModal">
                        <i class="fa-solid fa-circle-plus"></i>
                        <span>Isi Saldo (Top-Up)</span>
                    </button>
                    <div class="text-white-50 small mt-1.5" style="font-size: 0.70rem;">
                        <i class="fa-solid fa-bolt me-1 text-warning"></i> Verifikasi cepat via WA
                    </div>
                </div>
            </div>

            <!-- Rincian Kapasitas Kuota -->
            <div class="d-flex flex-wrap align-items-center gap-2 pt-2.5 mt-2.5 border-top border-white border-opacity-15" style="font-size: 0.72rem;">
                <span class="badge bg-black bg-opacity-25 text-white fw-normal px-2.5 py-1 rounded-pill">
                    <i class="fa-solid fa-bolt text-warning me-1"></i>
                    Kapasitas: <strong class="text-white">~<?= $est_orders ?> Pesanan Tersedia</strong>
                </span>
                <span class="badge bg-black bg-opacity-25 text-white fw-normal px-2.5 py-1 rounded-pill">
                    <i class="fa-solid fa-coins text-warning me-1"></i>
                    Total Omzet: <strong class="text-white">Rp <?= number_format($total_earned_by_provider, 0, ',', '.') ?></strong>
                </span>
                <span class="badge bg-black bg-opacity-25 text-white fw-normal px-2.5 py-1 rounded-pill">
                    <i class="fa-solid fa-tag text-info me-1"></i>
                    Biaya Kontak: <strong class="text-white"><?= $lead_fee_amount > 0 ? 'Rp ' . number_format($lead_fee_amount, 0, ',', '.') : 'GRATIS (Rp 0)' ?></strong> / Pesanan
                </span>
            </div>
        </div>
    </div>

    <!-- Edukasi Manfaat Sistem Deposit & Jaminan Pengembalian Saldo -->
    <div class="accordion mb-3" id="accordionInfoDeposit">
        <div class="accordion-item border-0 shadow-xs rounded-3 overflow-hidden bg-white">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed py-2 px-3 bg-light text-dark fw-bold small" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDepositInfo" aria-expanded="false" style="font-size: 0.78rem;">
                    <i class="fa-solid fa-circle-info text-teal me-2"></i>
                    <span>Bagaimana Sistem Kuota & Jaminan Saldo Mitra Bekerja?</span>
                    <span class="badge bg-teal-subtle text-teal ms-auto me-2" style="font-size: 0.65rem;">Info & Garansi</span>
                </button>
            </h2>
            <div id="collapseDepositInfo" class="accordion-collapse collapse" data-bs-parent="#accordionInfoDeposit">
                <div class="accordion-body p-3 pt-2">
                    <div class="row g-2 small">
                        <div class="col-md-4">
                            <div class="p-2.5 rounded-3 bg-light border-start border-3 border-success h-100">
                                <div class="fw-bold text-dark mb-1" style="font-size: 0.78rem;"><i class="fa-solid fa-coins text-success me-1"></i> Biaya Flat & Ringan</div>
                                <div class="text-muted" style="font-size: 0.72rem; line-height: 1.4;">
                                    <?= $lead_fee_amount > 0 ? 'Hanya dipotong <strong>Rp ' . number_format($lead_fee_amount, 0, ',', '.') . '</strong> per order kontak. Tanpa potongan persenan hasil kerja.' : 'Saat ini berlaku <strong>Promo Bebas Biaya Kontak (GRATIS Rp 0)</strong>! Ambil pesanan tanpa memotong saldo deposit Anda.' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2.5 rounded-3 bg-light border-start border-3 border-primary h-100">
                                <div class="fw-bold text-dark mb-1" style="font-size: 0.78rem;"><i class="fa-solid fa-wrench text-primary me-1"></i> Sparepart 100% Hak Anda</div>
                                <div class="text-muted" style="font-size: 0.72rem; line-height: 1.4;">
                                    Seluruh ongkos kerja dan pergantian onderdil dari pelanggan sepenuhnya milik Anda 100% langsung dibayar tunai di tempat.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2.5 rounded-3 bg-light border-start border-3 border-warning h-100">
                                <div class="fw-bold text-dark mb-1" style="font-size: 0.78rem;"><i class="fa-solid fa-award text-warning me-1"></i> Prioritas Order Nomor 1</div>
                                <div class="text-muted" style="font-size: 0.72rem; line-height: 1.4;">
                                    Toko mitra dengan saldo aktif selalu diprioritaskan tampil di urutan atas pencarian warga se-Kabupaten Inhu.
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <div class="p-2.5 rounded-3 bg-light border-start border-3 border-teal d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div style="max-width: 600px;">
                                    <div class="fw-bold text-dark mb-0.5" style="font-size: 0.78rem;">
                                        <i class="fa-solid fa-shield-halved text-teal me-1"></i> Garansi Pengembalian Sisa Saldo Top-Up (Bebas Resiko)
                                    </div>
                                    <div class="text-muted" style="font-size: 0.72rem; line-height: 1.4;">
                                        Saldo bonus pendaftaran adalah kuota promo non-tunai. Namun jika Anda pernah melakukan isi saldo dengan uang pribadi dan di kemudian hari ingin berhenti/pindah kota, sisa uang top-up asli Anda dijamin dapat diajukan pengembaliannya (refund) dengan menghubungi Admin.
                                    </div>
                                </div>
                                <a href="<?= e($refundWaUrl) ?>" target="_blank" class="btn btn-xs btn-outline-teal rounded-pill px-3 py-1.5 fw-bold text-nowrap shadow-2xs">
                                    <i class="fa-brands fa-whatsapp text-success me-1"></i> Bantuan / Ajukan Refund
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Permintaan Top-up Pending (Jika Ada) -->
    <?php if (!empty($topup_requests)): ?>
        <div class="card border-0 shadow-xs rounded-4 mb-3">
            <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark small">
                    <i class="fa-solid fa-receipt text-teal me-1"></i> Pengajuan Isi Saldo Terakhir
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 small" style="font-size: 0.75rem;">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Nominal</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Bukti Struk</th>
                            <th>Keterangan</th>
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
                                        <span class="badge text-bg-warning">Menunggu Validasi</span>
                                    <?php elseif ($tr['status'] === 'approved'): ?>
                                        <span class="badge text-bg-success">Berhasil Masuk</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger">Ditolak</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (has_media($tr['proof_image'] ?? '')): ?>
                                        <button type="button" class="btn btn-xs btn-outline-teal py-0.5 px-2 rounded-2" data-bs-toggle="modal" data-bs-target="#modalStrukProv<?= $tr['id'] ?>">
                                            <i class="fa-solid fa-receipt me-1"></i> Struk
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= e($tr['admin_notes'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Pratinjau Struk untuk Mitra -->
        <?php foreach ($topup_requests as $tr): ?>
            <?php if (has_media($tr['proof_image'] ?? '')): ?>
                <div class="modal fade" id="modalStrukProv<?= $tr['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                            <div class="modal-header bg-teal text-white py-2.5 px-3">
                                <h6 class="modal-title fw-bold mb-0 text-white fs-6">
                                    <i class="fa-solid fa-receipt me-1.5"></i> Bukti Transfer Top-Up #<?= $tr['id'] ?>
                                </h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-3 text-center bg-light">
                                <div class="bg-white p-2 rounded-3 border mb-2">
                                    <img src="<?= e(get_media_url($tr['proof_image'])) ?>" 
                                         alt="Bukti Transfer Top-Up #<?= $tr['id'] ?>" 
                                         class="img-fluid rounded" 
                                         style="max-height: 400px; width: auto; object-fit: contain;">
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="fw-bold text-teal">Rp <?= number_format((float)$tr['amount'], 0, ',', '.') ?> (<?= e($tr['payment_method']) ?>)</span>
                                    <a href="<?= BASE_URL ?>/view_doc.php?topup_id=<?= $tr['id'] ?>&type=topup" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Buka Penuh
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Riwayat Mutasi Dompet (Log Transaksi) -->
    <div class="card border-0 shadow-xs rounded-4 overflow-hidden">
        <div class="card-header bg-white py-2.5 px-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark small">
                <i class="fa-solid fa-clock-rotate-left text-teal me-1"></i> Riwayat Mutasi Saldo Dompet
            </h6>
            <span class="text-muted" style="font-size: 0.70rem;">30 riwayat terakhir</span>
        </div>

        <?php if (!empty($transactions)): ?>
            <!-- Tampilan List Mobile (Sleek App View) -->
            <div class="d-md-none divide-y">
                <?php foreach ($transactions as $idx => $tx): ?>
                    <?php $isCredit = ((float)$tx['amount'] > 0); ?>
                    <div class="p-3 border-bottom d-flex align-items-center justify-content-between gap-2.5">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 <?= $isCredit ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>" style="width: 36px; height: 36px;">
                                <i class="fa-solid <?= $isCredit ? 'fa-arrow-down-left' : 'fa-arrow-up-right' ?>" style="font-size: 0.85rem;"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark small" style="font-size: 0.78rem; line-height: 1.3;">
                                    <?= e($tx['description']) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.68rem;">
                                    <?= format_date($tx['created_at'], true) ?>
                                    <?php if (!empty($tx['reference_id'])): ?>
                                        &bull; Order #<?= $tx['reference_id'] ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="fw-bold font-monospace <?= $isCredit ? 'text-success' : 'text-danger' ?>" style="font-size: 0.82rem;">
                                <?= $isCredit ? '+' : '' ?>Rp <?= number_format((float)$tx['amount'], 0, ',', '.') ?>
                            </div>
                            <div class="text-muted" style="font-size: 0.66rem;">
                                Sisa: Rp <?= number_format((float)$tx['balance_after'], 0, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Tampilan Tabel Desktop -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0 small" style="font-size: 0.78rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th style="width: 20%;">Waktu</th>
                            <th style="width: 15%;">Tipe</th>
                            <th style="width: 40%;">Rincian & Keterangan</th>
                            <th style="width: 10%;" class="text-end">Nominal</th>
                            <th style="width: 10%;" class="text-end">Sisa Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $idx => $tx): ?>
                            <?php $isCredit = ((float)$tx['amount'] > 0); ?>
                            <tr>
                                <td class="text-muted"><?= $idx + 1 ?></td>
                                <td><?= format_date($tx['created_at'], true) ?></td>
                                <td>
                                    <?php if ($tx['type'] === 'lead_fee'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Biaya Kontak</span>
                                    <?php elseif ($tx['type'] === 'topup'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Top-Up Saldo</span>
                                    <?php elseif ($tx['type'] === 'bonus'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Bonus Saldo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Penyesuaian</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($tx['description']) ?></div>
                                    <?php if (!empty($tx['reference_id'])): ?>
                                        <div class="text-muted" style="font-size: 0.70rem;">ID Pesanan: #<?= $tx['reference_id'] ?></div>
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
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">
                <i class="fa-solid fa-receipt text-secondary opacity-50 fs-3 mb-2 d-block"></i>
                Belum ada riwayat transaksi mutasi saldo.
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Modal Isi Saldo (Top-Up) -->
<div class="modal fade" id="topupModal" tabindex="-1" aria-labelledby="topupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 520px;">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" method="POST" action="<?= BASE_URL ?>/provider/wallet.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="request_topup">

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
                        <input type="file" name="proof_image" id="walletProofInput" class="form-control form-control-sm" accept="image/*">
                        <div id="walletProofStatus"></div>
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

<script>
function selectAmount(amt) {
    document.getElementById('inputTopupAmount').value = amt;
    document.querySelectorAll('.quick-amount-btn').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const proofInput = document.getElementById('walletProofInput');
    if (proofInput && window.attachAutoCompressor) {
        window.attachAutoCompressor(proofInput, {
            statusEl: 'walletProofStatus',
            maxWidth: 1600,
            maxHeight: 1600,
            quality: 0.82
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

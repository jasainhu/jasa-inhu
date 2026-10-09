<?php
/**
 * Kelola Saldo Dompet & Pendapatan Biaya Kontak (Lead Fee)
 * Admin Panel JASA INHU - Kabupaten Indragiri Hulu
 */

$page_title = 'Kelola Saldo & Biaya Kontak Mitra';
$admin_active = 'wallet';

require_once __DIR__ . '/includes/header.php';

$db = get_db();
$error = '';
$success = '';

// Handle Aksi Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'];

        // 1. Setujui Permintaan Top-up
        if ($action === 'approve_topup') {
            $topup_id = (int)$_POST['topup_id'];
            $admin_notes = trim($_POST['admin_notes'] ?? 'Disetujui oleh Admin JASA INHU');

            try {
                $stmtCheck = $db->prepare("SELECT * FROM wallet_topup_requests WHERE id = ? AND status = 'pending' LIMIT 1");
                $stmtCheck->execute([$topup_id]);
                $topup = $stmtCheck->fetch();

                if (!$topup) {
                    $error = 'Permintaan top-up tidak ditemukan atau sudah diproses.';
                } else {
                    $db->beginTransaction();

                    // Tambahkan saldo ke mitra
                    $addRes = add_provider_wallet_balance(
                        (int)$topup['provider_id'],
                        (float)$topup['amount'],
                        'topup',
                        "Top-up Saldo disetujui Admin (Req #$topup_id): " . $admin_notes,
                        $topup_id
                    );

                    // Update status permintaan topup
                    $stmtUpd = $db->prepare("UPDATE wallet_topup_requests SET status = 'approved', admin_notes = ?, approved_at = NOW() WHERE id = ?");
                    $stmtUpd->execute([$admin_notes, $topup_id]);

                    // Ambil user_id pemilik mitra untuk notifikasi
                    $stmtUser = $db->prepare("SELECT user_id, business_name FROM service_providers WHERE id = ?");
                    $stmtUser->execute([(int)$topup['provider_id']]);
                    $provUser = $stmtUser->fetch();

                    if ($provUser) {
                        $notifMsg = "Isi saldo sebesar Rp " . number_format($topup['amount'], 0, ',', '.') . " telah berhasil disetujui. Sisa saldo Anda sekarang Rp " . number_format($addRes['balance_after'], 0, ',', '.') . ".";
                        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, 'Top-Up Saldo Berhasil Disetujui!', ?, '/provider/wallet.php', NOW())");
                        $stmtNotif->execute([$provUser['user_id'], $notifMsg]);
                    }

                    $db->commit();
                    set_flash('success', 'Permintaan Top-Up #' . $topup_id . ' berhasil disetujui! Saldo mitra telah bertambah Rp ' . number_format($topup['amount'], 0, ',', '.') . '.');
                    redirect('/admin/wallet.php');
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Gagal menyetujui top-up: ' . $e->getMessage();
            }

        // 2. Tolak Permintaan Top-up
        } elseif ($action === 'reject_topup') {
            $topup_id = (int)$_POST['topup_id'];
            $admin_notes = trim($_POST['admin_notes'] ?? 'Bukti pembayaran tidak valid');

            try {
                $stmtUpd = $db->prepare("UPDATE wallet_topup_requests SET status = 'rejected', admin_notes = ? WHERE id = ? AND status = 'pending'");
                $stmtUpd->execute([$admin_notes, $topup_id]);

                set_flash('info', 'Permintaan Top-Up #' . $topup_id . ' telah ditolak.');
                redirect('/admin/wallet.php');
            } catch (Exception $e) {
                $error = 'Gagal menolak top-up: ' . $e->getMessage();
            }

        // 3. Tambah Saldo Manual oleh Admin
        } elseif ($action === 'manual_adjust') {
            $provider_id = (int)$_POST['provider_id'];
            $amount = (float)$_POST['amount'];
            $type = $_POST['type'] ?? 'topup'; // topup, bonus
            $notes = trim($_POST['notes'] ?? 'Penambahan saldo manual oleh Admin');

            if ($amount <= 0) {
                $error = 'Nominal penambahan saldo harus lebih dari 0.';
            } else {
                try {
                    $addRes = add_provider_wallet_balance($provider_id, $amount, $type, $notes);
                    if ($addRes['success']) {
                        set_flash('success', 'Saldo mitra berhasil ditambahkan sebesar Rp ' . number_format($amount, 0, ',', '.') . '! Sisa saldo baru: Rp ' . number_format($addRes['balance_after'], 0, ',', '.'));
                        redirect('/admin/wallet.php');
                    } else {
                        $error = $addRes['message'];
                    }
                } catch (Exception $e) {
                    $error = 'Gagal menyesuaikan saldo: ' . $e->getMessage();
                }
            }
        }
    }
}

// 1. Metrik Keuangan Platform
// Total Pendapatan Biaya Kontak (Lead Fee) yang Diterima Platform
$stmtRev = $db->query("SELECT SUM(ABS(amount)) FROM provider_wallet_transactions WHERE type = 'lead_fee'");
$total_lead_revenue = (float)($stmtRev->fetchColumn() ?: 0.00);

// Total Saldo Mengendap Seluruh Mitra
$stmtHold = $db->query("SELECT SUM(wallet_balance) FROM service_providers");
$total_wallet_holding = (float)($stmtHold->fetchColumn() ?: 0.00);

// Jumlah Pesanan yang Diambil via Lead Fee
$stmtOrderCount = $db->query("SELECT COUNT(*) FROM provider_wallet_transactions WHERE type = 'lead_fee'");
$total_lead_orders = (int)($stmtOrderCount->fetchColumn() ?: 0);

// Permintaan Top-up Pending
$stmtPendingCount = $db->query("SELECT COUNT(*) FROM wallet_topup_requests WHERE status = 'pending'");
$pending_topups_count = (int)($stmtPendingCount->fetchColumn() ?: 0);

// 2. Daftar Permintaan Top-up Pending
$stmtPending = $db->query("
    SELECT wtr.*, sp.business_name, sp.wallet_balance, u.name as owner_name, u.phone as owner_phone, d.name as district_name
    FROM wallet_topup_requests wtr
    JOIN service_providers sp ON wtr.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN districts d ON sp.district_id = d.id
    WHERE wtr.status = 'pending'
    ORDER BY wtr.created_at ASC
");
$pending_topups = $stmtPending->fetchAll();

// 3. Daftar Semua Mitra & Saldo Saat Ini
$stmtAllProv = $db->query("
    SELECT sp.*, u.name as owner_name, u.phone as owner_phone, sc.name as category_name, d.name as district_name,
           (SELECT COUNT(*) FROM provider_wallet_transactions pwt WHERE pwt.provider_id = sp.id AND pwt.type = 'lead_fee') as total_leads_taken
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    ORDER BY sp.wallet_balance DESC, sp.business_name ASC
");
$all_providers = $stmtAllProv->fetchAll();

// 4. Log Mutasi Transaksi Terbaru Se-Platform
$stmtRecentTx = $db->query("
    SELECT pwt.*, sp.business_name, u.name as owner_name
    FROM provider_wallet_transactions pwt
    JOIN service_providers sp ON pwt.provider_id = sp.id
    JOIN users u ON sp.user_id = u.id
    ORDER BY pwt.created_at DESC
    LIMIT 20
");
$recent_transactions = $stmtRecentTx->fetchAll();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">
            <i class="fa-solid fa-wallet text-warning me-2"></i>Kelola Saldo Dompet & Biaya Kontak (Lead Fee)
        </h1>
        <p class="text-muted small mb-0">Monetisasi platform transparan, anti-bocor, dan adil bagi seluruh mitra di Kabupaten Indragiri Hulu</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <button type="button" class="btn btn-sm btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#manualTopupModal">
            <i class="fa-solid fa-plus me-1"></i> Tambah Saldo Manual
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-4"><?= e($error) ?></div>
<?php endif; ?>

<!-- 4 Kartu Metrik Keuangan Platform -->
<div class="row g-3 mb-4">
    <!-- Metrik 1: Pendapatan Biaya Kontak -->
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-top border-4 border-success">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Keuntungan Platform (Lead Fee)</span>
                <div class="stat-icon" style="background: #dcfce7; color: #16a34a; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-hand-holding-dollar fs-5"></i>
                </div>
            </div>
            <div class="h3 fw-bold text-dark mb-1">Rp <?= number_format($total_lead_revenue, 0, ',', '.') ?></div>
            <div class="small text-muted">Dari <?= $total_lead_orders ?> order yang diambil mitra</div>
        </div>
    </div>

    <!-- Metrik 2: Saldo Mengendap Mitra -->
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-top border-4 border-teal">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Total Saldo Deposit Mengendap</span>
                <div class="stat-icon" style="background: #ccfbf1; color: #0d9488; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-piggy-bank fs-5"></i>
                </div>
            </div>
            <div class="h3 fw-bold text-teal mb-1">Rp <?= number_format($total_wallet_holding, 0, ',', '.') ?></div>
            <div class="small text-muted">Tersimpan di seluruh dompet mitra</div>
        </div>
    </div>

    <!-- Metrik 3: Tarif Flat per Pesanan -->
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-top border-4 border-warning">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Tarif Biaya Kontak (Flat)</span>
                <div class="stat-icon" style="background: #fef3c7; color: #d97706; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-tag fs-5"></i>
                </div>
            </div>
            <div class="h3 fw-bold text-dark mb-1">
                <?= (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE) > 0 ? 'Rp ' . number_format((float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE), 0, ',', '.') : '<span class="text-success">PROMO GRATIS</span>' ?>
            </div>
            <div class="small text-muted">Per 1 pesanan yang diterima mitra</div>
        </div>
    </div>

    <!-- Metrik 4: Permintaan Top-up Pending -->
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-top border-4 border-danger">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Konfirmasi Top-Up Pending</span>
                <div class="stat-icon" style="background: #fee2e2; color: #dc2626; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-bell fs-5"></i>
                </div>
            </div>
            <div class="h3 fw-bold text-danger mb-1"><?= $pending_topups_count ?></div>
            <div class="small text-muted">Memerlukan persetujuan admin</div>
        </div>
    </div>
</div>

<!-- Permintaan Top-up Menunggu Persetujuan (Pending) -->
<?php if (!empty($pending_topups)): ?>
    <div class="card border-warning shadow-sm rounded-4 mb-4" style="border-width: 2px;">
        <div class="card-header bg-warning bg-opacity-10 py-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> Perlu Verifikasi</span>
                <h5 class="fw-bold mb-0 text-dark">Permintaan Isi Saldo Menunggu Persetujuan (<?= count($pending_topups) ?>)</h5>
            </div>
            <span class="small text-muted">Periksa bukti transfer dan setujui untuk mengisi saldo mitra</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>ID</th>
                        <th>Mitra Usaha</th>
                        <th>Pemilik & No. WhatsApp</th>
                        <th>Nominal Top-Up</th>
                        <th>Metode</th>
                        <th>Bukti Transfer</th>
                        <th class="text-end">Aksi Admin</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($pending_topups as $pt): ?>
                        <tr>
                            <td class="fw-bold">#<?= $pt['id'] ?></td>
                            <td>
                                <strong class="text-dark"><?= e($pt['business_name']) ?></strong>
                                <div class="text-muted" style="font-size: 0.75rem;">Kec. <?= e($pt['district_name']) ?> &bull; Saldo saat ini: Rp <?= number_format($pt['wallet_balance'], 0, ',', '.') ?></div>
                            </td>
                            <td>
                                <div><?= e($pt['owner_name']) ?></div>
                                <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $pt['owner_phone'])) ?>" target="_blank" class="text-success text-decoration-none fw-semibold">
                                    <i class="fa-brands fa-whatsapp me-1"></i> <?= e($pt['owner_phone']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="fw-bold text-teal fs-6">Rp <?= number_format((float)$pt['amount'], 0, ',', '.') ?></span>
                            </td>
                            <td><?= e($pt['payment_method']) ?></td>
                            <td>
                                <?php if (!empty($pt['proof_image']) && file_exists(__DIR__ . '/../' . $pt['proof_image'])): ?>
                                    <a href="<?= BASE_URL ?>/<?= e($pt['proof_image']) ?>" target="_blank" class="btn btn-sm btn-outline-teal py-0.5 px-2">
                                        <i class="fa-solid fa-image me-1"></i> Lihat Struk
                                    </a>
                                <?php else: ?>
                                    <span class="badge text-bg-light border text-muted">Konfirmasi via WA</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1.5">
                                    <form method="POST" action="<?= BASE_URL ?>/admin/wallet.php"
                                          data-confirm="Apakah Anda sudah memeriksa bukti transfer dan yakin ingin MENYETUJUI penambahan saldo Rp <?= number_format($pt['amount'], 0, ',', '.') ?> untuk <?= e($pt['business_name']) ?>?"
                                          data-confirm-title="Setujui Top-Up Saldo?"
                                          data-confirm-btn="Ya, Setujui Top-Up"
                                          data-confirm-type="success">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="approve_topup">
                                        <input type="hidden" name="topup_id" value="<?= $pt['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                                            <i class="fa-solid fa-check me-1"></i> Setujui
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= BASE_URL ?>/admin/wallet.php"
                                          data-confirm="Apakah Anda yakin ingin MENOLAK permintaan top-up saldo Rp <?= number_format($pt['amount'], 0, ',', '.') ?> dari <?= e($pt['business_name']) ?>?"
                                          data-confirm-title="Tolak Permintaan Top-Up?"
                                          data-confirm-btn="Ya, Tolak Permintaan"
                                          data-confirm-type="danger">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="reject_topup">
                                        <input type="hidden" name="topup_id" value="<?= $pt['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2">
                                            <i class="fa-solid fa-xmark"></i> Tolak
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Tabel Daftar Saldo Seluruh Mitra di Inhu -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-users-viewfinder text-teal me-1"></i> Daftar Saldo Dompet Seluruh Mitra
            </h5>
            <span class="small text-muted">Total <?= count($all_providers) ?> mitra penyedia jasa terdaftar di Indragiri Hulu</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Nama Usaha & Bidang</th>
                    <th>Wilayah Inhu</th>
                    <th>Kontak Pemilik</th>
                    <th class="text-end">Saldo Deposit (Rp)</th>
                    <th class="text-center">Order Diambil</th>
                    <th class="text-center">Status Kuota</th>
                    <th class="text-end">Aksi Admin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_providers as $idx => $p): ?>
                    <?php 
                    $bal = (float)$p['wallet_balance'];
                    $cur_lead_fee = (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE);
                    $canTakeOrders = ($cur_lead_fee <= 0 || $bal >= $cur_lead_fee);
                    ?>
                    <tr>
                        <td class="text-muted"><?= $idx + 1 ?></td>
                        <td>
                            <strong class="text-dark d-block"><?= e($p['business_name']) ?></strong>
                            <span class="badge text-bg-light border text-teal" style="font-size: 0.7rem;">
                                <?= e($p['category_name']) ?>
                            </span>
                        </td>
                        <td>Kec. <?= e($p['district_name'] ?: 'Inhu') ?></td>
                        <td>
                            <div><?= e($p['owner_name']) ?></div>
                            <span class="text-muted" style="font-size: 0.72rem;"><?= e($p['owner_phone']) ?></span>
                        </td>
                        <td class="text-end fw-bold font-monospace fs-6 <?= $bal > 0 ? 'text-teal' : 'text-danger' ?>">
                            Rp <?= number_format($bal, 0, ',', '.') ?>
                        </td>
                        <td class="text-center">
                            <span class="badge text-bg-light border fw-semibold">
                                <?= (int)$p['total_leads_taken'] ?> Pesanan
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($canTakeOrders): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="fa-solid fa-circle-check me-0.5"></i> Siap Order (~<?= $cur_lead_fee > 0 ? floor($bal / $cur_lead_fee) : '∞' ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="fa-solid fa-triangle-exclamation me-0.5"></i> Saldo Habis
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-teal py-1 px-2.5 rounded-3 fw-semibold" onclick="openManualTopupFor(<?= $p['id'] ?>, '<?= e(addslashes($p['business_name'])) ?>', <?= $bal ?>)">
                                <i class="fa-solid fa-wallet me-1"></i> + Saldo
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Saldo Manual oleh Admin -->
<div class="modal fade" id="manualTopupModal" tabindex="-1" aria-labelledby="manualTopupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-coins fs-4 text-warning"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="manualTopupModalLabel">Tambah Saldo Mitra Manual</h5>
                        <span class="text-white-50 small" style="font-size: 0.78rem;">Tambahkan saldo untuk setor tunai atau bonus promo</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="<?= BASE_URL ?>/admin/wallet.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="manual_adjust">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1">Pilih Mitra Penyedia Jasa: <span class="text-danger">*</span></label>
                        <select name="provider_id" id="manualProviderSelect" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Mitra --</option>
                            <?php foreach ($all_providers as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= e($p['business_name']) ?> (Kec. <?= e($p['district_name']) ?>) - Saldo: Rp <?= number_format($p['wallet_balance'], 0, ',', '.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1">Jenis Penambahan Saldo:</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="topup">Top-Up Tunai / Transfer Langsung</option>
                            <option value="bonus">Bonus Promosi / Loyalitas</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1">Nominal Saldo Ditambahkan (Rp): <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">Rp</span>
                            <input type="number" name="amount" class="form-control fw-bold fs-5 text-teal" placeholder="Contoh: 50000" min="5000" step="5000" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1">Catatan / Keterangan Admin:</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Contoh: Setor tunai kantor Belilas / Bonus awal bulan">
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-teal text-white fw-bold px-4 py-2 shadow-xs">
                        <i class="fa-solid fa-plus me-1"></i> Tambahkan Saldo Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openManualTopupFor(providerId, businessName, currentBal) {
    const select = document.getElementById('manualProviderSelect');
    if (select) {
        select.value = providerId;
    }
    const modalEl = document.getElementById('manualTopupModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Admin: Kelola Laporan Masalah & Pelanggan Nakal
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */

$page_title = 'Laporan Masalah & Pengaduan';
$admin_active = 'reports';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();
$error = '';

// Handle aksi: Update Status Laporan atau Blokir Akun Pelapor/Terlapor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'];
        $report_id = (int)($_POST['report_id'] ?? 0);

        if ($action === 'block_and_resolve') {
            $reported_user_id = (int)$_POST['reported_user_id'];
            $block_note = trim($_POST['block_note'] ?? 'Diblacklist karena terbukti melakukan pelanggaran / penipuan mitra');

            try {
                $db->beginTransaction();

                // 1. Blokir akun terlapor
                $stmtBlock = $db->prepare("UPDATE users SET is_active = 0, block_reason = ?, blocked_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmtBlock->execute([$block_note, $reported_user_id]);

                // 2. Tandai laporan jadi resolved
                $stmtRep = $db->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
                $stmtRep->execute([$report_id]);

                $db->commit();
                set_flash('success', 'Akun pelanggan berhasil DIBLOKIR / DIBLACKLIST secara permanen dan laporan ditandai Selesai.');
                redirect('/admin/reports.php');
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Gagal memproses tindakan: ' . $e->getMessage();
            }
        } elseif ($action === 'update_status') {
            $new_status = trim($_POST['status'] ?? 'pending');
            try {
                $stmtUpd = $db->prepare("UPDATE reports SET status = ? WHERE id = ?");
                $stmtUpd->execute([$new_status, $report_id]);
                set_flash('success', 'Status laporan berhasil diperbarui menjadi: ' . strtoupper($new_status));
                redirect('/admin/reports.php');
            } catch (Exception $e) {
                $error = 'Gagal memperbarui status: ' . $e->getMessage();
            }
        }
    }
}

// Filter Status
$filter_status = trim($_GET['status'] ?? '');
$params = [];
$where = [];

if (!empty($filter_status)) {
    $where[] = "r.status = ?";
    $params[] = $filter_status;
}

$where_sql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// Ambil data laporan
$sqlReports = "
    SELECT r.*,
           u_rep.name as reporter_name, u_rep.phone as reporter_phone, u_rep.email as reporter_email,
           r_rep.name as reporter_role,
           sp_rep.business_name as reporter_business,
           u_tgt.name as reported_name, u_tgt.phone as reported_phone, u_tgt.email as reported_email, u_tgt.is_active as reported_is_active,
           r_tgt.name as reported_role,
           sr.title as request_title, sr.status as request_status
    FROM reports r
    JOIN users u_rep ON r.reporter_user_id = u_rep.id
    LEFT JOIN roles r_rep ON u_rep.role_id = r_rep.id
    LEFT JOIN service_providers sp_rep ON u_rep.id = sp_rep.user_id
    JOIN users u_tgt ON r.reported_user_id = u_tgt.id
    LEFT JOIN roles r_tgt ON u_tgt.role_id = r_tgt.id
    LEFT JOIN service_requests sr ON r.request_id = sr.id
    {$where_sql}
    ORDER BY r.created_at DESC
";
$stmtReports = $db->prepare($sqlReports);
$stmtReports->execute($params);
$reports = $stmtReports->fetchAll();

// Hitung rekap status
$pending_count = (int)$db->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
$resolved_count = (int)$db->query("SELECT COUNT(*) FROM reports WHERE status = 'resolved'")->fetchColumn();
$total_count = (int)$db->query("SELECT COUNT(*) FROM reports")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">
            <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Laporan Masalah & Pengaduan
        </h1>
        <p class="text-muted small mb-0">Tinjau aduan order fiktif, penipuan transfer, atau ketidaknyamanan dari mitra dan pelanggan di Inhu</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-users me-1"></i> Kelola Pengguna
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Tab Filter Status -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-sm rounded-pill px-3 <?= empty($filter_status) ? 'btn-teal' : 'btn-light border' ?>">
        Semua Laporan (<?= $total_count ?>)
    </a>
    <a href="<?= BASE_URL ?>/admin/reports.php?status=pending" class="btn btn-sm rounded-pill px-3 <?= $filter_status === 'pending' ? 'btn-danger' : 'btn-light border text-danger' ?>">
        <i class="fa-solid fa-clock me-1"></i> Menunggu Ditinjau (<?= $pending_count ?>)
    </a>
    <a href="<?= BASE_URL ?>/admin/reports.php?status=resolved" class="btn btn-sm rounded-pill px-3 <?= $filter_status === 'resolved' ? 'btn-success' : 'btn-light border text-success' ?>">
        <i class="fa-solid fa-circle-check me-1"></i> Sudah Selesai (<?= $resolved_count ?>)
    </a>
</div>

<?php if (empty($reports)): ?>
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
        <div class="mx-auto mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center text-muted" style="width: 70px; height: 70px;">
            <i class="fa-solid fa-shield-heart fs-2 text-teal"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1">Tidak Ada Laporan Masalah</h5>
        <p class="text-muted small mb-0">Semua aktivitas pemesanan jasa di Indragiri Hulu saat ini berjalan aman dan kondusif.</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($reports as $rep): ?>
            <?php
            $is_pending = ($rep['status'] === 'pending');
            $status_class = match($rep['status']) {
                'pending' => 'bg-danger text-white',
                'reviewed' => 'bg-warning text-dark',
                'resolved' => 'bg-success text-white',
                default => 'bg-secondary text-white'
            };
            $reported_phone = preg_replace('/[^0-9]/', '', $rep['reported_phone'] ?? '');
            if (str_starts_with($reported_phone, '0')) {
                $reported_phone = '62' . substr($reported_phone, 1);
            }
            ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 p-4 <?= $is_pending ? 'border-start border-4 border-danger' : '' ?>">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1.5">
                                <span class="badge <?= $status_class ?> rounded-pill px-2.5 py-1 text-uppercase" style="font-size: 0.72rem;">
                                    <?= e($rep['status']) ?>
                                </span>
                                <h5 class="fw-bold text-dark mb-0"><?= e($rep['reason']) ?></h5>
                            </div>
                            <div class="text-muted small">
                                <i class="fa-solid fa-calendar-day me-1"></i> Dilaporkan pada: <?= format_date($rep['created_at']) ?> &bull; 
                                <?php if (!empty($rep['request_title'])): ?>
                                    Terkait Pesanan: <strong class="text-dark">#ORD-<?= str_pad($rep['request_id'], 5, '0', STR_PAD_LEFT) ?> (<?= e($rep['request_title']) ?>)</strong>
                                <?php else: ?>
                                    Laporan Umum Akun
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Badge Status Akun Terlapor -->
                        <div class="text-md-end">
                            <?php if ($rep['reported_is_active'] == 0): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1.5 rounded-pill fw-bold">
                                    <i class="fa-solid fa-ban me-1"></i> Akun Sudah Diblokir
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill">
                                    <i class="fa-solid fa-check me-1"></i> Akun Terlapor Masih Aktif
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Informasi Pelapor vs Terlapor -->
                    <div class="row g-3 p-3 bg-light rounded-3 mb-3 small">
                        <div class="col-md-6 border-end-md">
                            <div class="text-muted fw-bold mb-1"><i class="fa-solid fa-user me-1 text-teal"></i> Identitas Pelapor (Korban):</div>
                            <div class="fw-bold text-dark fs-6"><?= e($rep['reporter_name']) ?></div>
                            <?php if (!empty($rep['reporter_business'])): ?>
                                <div class="text-teal fw-semibold">Toko: <?= e($rep['reporter_business']) ?></div>
                            <?php endif; ?>
                            <div class="text-muted">Nomor WA/HP: <?= e($rep['reporter_phone'] ?: '-') ?></div>
                            <div class="text-muted">Peran: <?= e($rep['reporter_role'] ?? 'Pengguna') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fw-bold mb-1"><i class="fa-solid fa-user-xmark me-1 text-danger"></i> Identitas Terlapor:</div>
                            <div class="fw-bold text-danger fs-6"><?= e($rep['reported_name']) ?></div>
                            <div class="text-muted">Nomor WA/HP: <strong><?= e($rep['reported_phone'] ?: '-') ?></strong></div>
                            <div class="text-muted">Email: <?= e($rep['reported_email'] ?: '-') ?></div>
                            <div class="text-muted">Peran: <?= e($rep['reported_role'] ?? 'Pelanggan') ?></div>
                        </div>
                    </div>

                    <!-- Rincian Kronologi -->
                    <div class="mb-3">
                        <div class="fw-bold text-dark small mb-1">Rincian Kronologi / Catatan Kejadian:</div>
                        <div class="p-3 bg-white border rounded-3 text-secondary small" style="white-space: pre-line;">
                            <?= e($rep['details']) ?>
                        </div>
                    </div>

                    <!-- Aksi Admin -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
                        <!-- Hubungi Terlapor / Pelapor via WA -->
                        <div class="d-flex gap-2">
                            <?php if (!empty($reported_phone)): ?>
                                <a href="https://wa.me/<?= $reported_phone ?>?text=<?= urlencode('Halo ' . $rep['reported_name'] . ', kami dari Tim Pengelola JASA INHU ingin mengonfirmasi terkait laporan masalah pada pesanan jasa Anda.') ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                    <i class="fa-brands fa-whatsapp text-success me-1"></i> Klarifikasi Terlapor via WA
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <!-- Tombol Blokir Akun Pelanggan Langsung -->
                            <?php if ($rep['reported_is_active'] == 1): ?>
                                <form method="POST" action="" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin memblokir/blacklist akun ini secara permanen dari JASA INHU?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="block_and_resolve">
                                    <input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
                                    <input type="hidden" name="reported_user_id" value="<?= $rep['reported_user_id'] ?>">
                                    <input type="hidden" name="block_note" value="Diblacklist admin karena aduan: <?= e($rep['reason']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold shadow-xs">
                                        <i class="fa-solid fa-ban me-1"></i> Blokir Akun Terlapor & Selesaikan
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Form Update Status Laporan Saja -->
                            <form method="POST" action="" class="d-flex align-items-center gap-1.5">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
                                <select name="status" class="form-select form-select-sm rounded-pill" style="width: auto;">
                                    <option value="pending" <?= $rep['status'] === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                                    <option value="reviewed" <?= $rep['status'] === 'reviewed' ? 'selected' : '' ?>>Sedang Ditinjau</option>
                                    <option value="resolved" <?= $rep['status'] === 'resolved' ? 'selected' : '' ?>>Selesai Diselesaikan</option>
                                    <option value="dismissed" <?= $rep['status'] === 'dismissed' ? 'selected' : '' ?>>Abaikan / Palsu</option>
                                </select>
                                <button type="submit" class="btn btn-outline-teal btn-sm rounded-pill px-3">
                                    Simpan
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Admin Dashboard JASA INHU
 */

$page_title = 'Dashboard Pengelola';
$admin_active = 'dashboard';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();

// Handle quick verify provider action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_verify') {
    if (validate_csrf()) {
        $provider_id = (int)$_POST['provider_id'];
        $new_status = (int)$_POST['new_status'];
        $stmtVer = $db->prepare("UPDATE service_providers SET is_verified = ? WHERE id = ?");
        $stmtVer->execute([$new_status, $provider_id]);
        set_flash('success', 'Status verifikasi mitra berhasil diperbarui.');
        redirect('/admin/index.php');
    }
}

// 1. Ambil statistik
$total_users = $db->query("SELECT COUNT(*) FROM users WHERE role_id = 2")->fetchColumn();
$total_providers = $db->query("SELECT COUNT(*) FROM users WHERE role_id = 3")->fetchColumn();
$total_categories = $db->query("SELECT COUNT(*) FROM service_categories")->fetchColumn();
$total_requests = $db->query("SELECT COUNT(*) FROM service_requests")->fetchColumn();

// 2. Daftar mitra penyedia jasa terbaru
$stmtProviders = $db->query("
    SELECT sp.*, u.name as user_name, u.email, u.phone, sc.name as category_name, d.name as district_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    ORDER BY sp.created_at DESC
    LIMIT 6
");
$recent_providers = $stmtProviders->fetchAll();

// 3. Daftar permintaan jasa terbaru
$stmtRequests = $db->query("
    SELECT sr.*, u.name as customer_name, sc.name as category_name, d.name as district_name
    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id
    JOIN service_categories sc ON sr.category_id = sc.id
    JOIN districts d ON sr.district_id = d.id
    ORDER BY sr.created_at DESC
    LIMIT 6
");
$recent_requests = $stmtRequests->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">Dashboard Pengelola Platform</h1>
        <p class="text-muted small mb-0">Pemantauan aktivitas marketplace jasa Kabupaten Indragiri Hulu</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/admin/banners.php" class="btn btn-primary-custom btn-sm fw-semibold">
            <i class="fa-solid fa-rectangle-ad me-1"></i> Kelola Banner & Iklan
        </a>
        <span class="badge text-bg-light border p-2">
            <i class="fa-solid fa-clock me-1 text-primary"></i> <?= date('d M Y, H:i') ?> WIB
        </span>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Masyarakat / Pengguna</span>
                <div class="stat-icon" style="background: #e0f2fe; color: #0284c7;">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <div class="h3 fw-bold mb-1"><?= $total_users ?></div>
            <div class="small text-muted">Warga terdaftar di Inhu</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Mitra Penyedia Jasa</span>
                <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
                    <i class="fa-solid fa-briefcase"></i>
                </div>
            </div>
            <div class="h3 fw-bold mb-1"><?= $total_providers ?></div>
            <div class="small text-muted">Tukang & teknisi lokal</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Kategori Jasa</span>
                <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                    <i class="fa-solid fa-shapes"></i>
                </div>
            </div>
            <div class="h3 fw-bold mb-1"><?= $total_categories ?></div>
            <div class="small text-muted">Bidang layanan aktif</div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Permintaan Jasa</span>
                <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
            </div>
            <div class="h3 fw-bold mb-1"><?= $total_requests ?></div>
            <div class="small text-muted">Total kebutuhan dibuat</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Tabel Mitra Penyedia Jasa -->
    <div class="col-lg-7">
        <div class="card border shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-users-gear me-2 text-primary"></i> Mitra Penyedia Jasa Terdaftar</h6>
                <a href="<?= BASE_URL ?>/admin/users.php" class="small text-decoration-none">Semua Pengguna &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Usaha / Pemilik</th>
                            <th>Kategori</th>
                            <th>Kecamatan</th>
                            <th>Status Verifikasi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_providers)): ?>
                            <?php foreach ($recent_providers as $p): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($p['business_name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= e($p['user_name']) ?> &bull; <?= e($p['phone']) ?></div>
                                    </td>
                                    <td><span class="badge text-bg-light border"><?= e($p['category_name']) ?></span></td>
                                    <td><?= e($p['district_name'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($p['is_verified']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="fa-solid fa-check"></i> Terverifikasi
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                Menunggu
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= BASE_URL ?>/admin/index.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_verify">
                                            <input type="hidden" name="provider_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= $p['is_verified'] ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-xs <?= $p['is_verified'] ? 'btn-outline-danger' : 'btn-success' ?> btn-sm py-1 px-2" style="font-size: 0.72rem;">
                                                <?= $p['is_verified'] ? 'Batalkan' : 'Verifikasi' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">Belum ada penyedia jasa.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabel Permintaan Jasa Terbaru -->
    <div class="col-lg-5">
        <div class="card border shadow-sm">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-bullhorn me-2 text-warning"></i> Permintaan Jasa Masuk</h6>
            </div>
            <div class="list-group list-group-flush small">
                <?php if (!empty($recent_requests)): ?>
                    <?php foreach ($recent_requests as $req): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge text-bg-light border text-primary"><?= e($req['category_name']) ?></span>
                                <span class="badge <?= $req['status'] === 'open' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= strtoupper(e($req['status'])) ?></span>
                            </div>
                            <div class="fw-bold text-dark mb-1"><?= e($req['title']) ?></div>
                            <div class="text-muted" style="font-size: 0.78rem;">
                                Pemohon: <strong><?= e($req['customer_name']) ?></strong> &bull; Kec. <?= e($req['district_name']) ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <span class="fw-semibold text-teal"><?= format_rupiah($req['budget']) ?></span>
                                <span class="text-muted" style="font-size: 0.72rem;"><?= format_date($req['created_at']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">Belum ada permintaan pekerjaan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

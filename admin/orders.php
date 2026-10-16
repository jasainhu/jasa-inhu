<?php
/**
 * Pusat Manajemen Pesanan & Transaksi Masuk (Order Control Center)
 * JASA INHU - Portal Layanan Terpadu Kabupaten Indragiri Hulu
 */

$page_title = 'Kelola Pesanan Masuk & Transaksi';
$admin_active = 'orders';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();
$error = '';
$success = '';

// Handle Admin Actions (e.g., Update status, cancel order, reassign)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        set_flash('danger', 'Sesi keamanan berakhir, silakan coba lagi.');
        redirect('/admin/orders.php');
    }

    $action = trim($_POST['action'] ?? '');
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($order_id > 0) {
        // 1. Admin Batalkan Pesanan
        if ($action === 'cancel_order') {
            $reason = trim($_POST['cancel_reason'] ?? 'Dibatalkan oleh Administrator Platform');
            $stmt = $db->prepare("
                UPDATE service_requests 
                SET status = 'cancelled', progress_step = 'cancelled', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$order_id]);

            // Catat timeline
            $db->prepare("
                INSERT INTO service_request_timeline (request_id, status, notes, created_at)
                VALUES (?, 'cancelled', ?, NOW())
            ")->execute([$order_id, 'Admin: ' . $reason]);

            set_flash('warning', "Pesanan #{$order_id} berhasil dibatalkan. Alasan: \"{$reason}\"");
            redirect('/admin/orders.php');
        }

        // 2. Admin Tandai Selesai Manual
        if ($action === 'complete_order') {
            $final_price = !empty($_POST['final_price']) ? (float)$_POST['final_price'] : null;
            $stmt = $db->prepare("
                UPDATE service_requests 
                SET status = 'completed', progress_step = 'completed', final_price = COALESCE(?, final_price), updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$final_price, $order_id]);

            $db->prepare("
                INSERT INTO service_request_timeline (request_id, status, notes, created_at)
                VALUES (?, 'completed', 'Diselesaikan manual oleh Administrator Jasa Inhu', NOW())
            ")->execute([$order_id]);

            set_flash('success', "Pesanan #{$order_id} berhasil ditandai selesai.");
            redirect('/admin/orders.php');
        }
    }
}

// Filters
$status_filter = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');
$category_filter = (int)($_GET['category_id'] ?? 0);
$district_filter = (int)($_GET['district_id'] ?? 0);

$where = ["1=1"];
$params = [];

if ($status_filter === 'open') {
    $where[] = "sr.status = 'open'";
} elseif ($status_filter === 'in_progress') {
    $where[] = "sr.status = 'in_progress'";
} elseif ($status_filter === 'completed') {
    $where[] = "sr.status = 'completed'";
} elseif ($status_filter === 'cancelled') {
    $where[] = "sr.status = 'cancelled'";
}

if (!empty($q)) {
    $where[] = "(sr.title LIKE ? OR cust.name LIKE ? OR cust.phone LIKE ? OR sp.business_name LIKE ?)";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}

if ($category_filter > 0) {
    $where[] = "sr.category_id = ?";
    $params[] = $category_filter;
}

if ($district_filter > 0) {
    $where[] = "sr.district_id = ?";
    $params[] = $district_filter;
}

$where_sql = implode(' AND ', $where);

// Ambil Daftar Pesanan Lengkap
$sql = "
    SELECT sr.*, 
           sc.name as category_name, sc.icon as category_icon,
           d.name as district_name, v.name as village_name,
           cust.name as customer_name, cust.phone as customer_phone, cust.email as customer_email,
           sp.business_name, sp.id as prov_id, sp.is_verified as prov_verified,
           prov_u.name as prov_owner_name, prov_u.phone as prov_phone,
           r.rating as review_rating, r.comment as review_comment
    FROM service_requests sr
    JOIN users cust ON sr.user_id = cust.id
    JOIN service_categories sc ON sr.category_id = sc.id
    LEFT JOIN districts d ON sr.district_id = d.id
    LEFT JOIN villages v ON sr.village_id = v.id
    LEFT JOIN service_providers sp ON sr.provider_id = sp.id
    LEFT JOIN users prov_u ON sp.user_id = prov_u.id
    LEFT JOIN reviews r ON r.request_id = sr.id
    WHERE {$where_sql}
    ORDER BY sr.id DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung Statistik Tab
$count_all = (int)$db->query("SELECT COUNT(*) FROM service_requests")->fetchColumn();
$count_open = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE status = 'open'")->fetchColumn();
$count_in_progress = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE status = 'in_progress'")->fetchColumn();
$count_completed = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE status = 'completed'")->fetchColumn();
$count_cancelled = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE status = 'cancelled'")->fetchColumn();

$categories = $db->query("SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$districts = get_all_districts();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">
            <i class="fa-solid fa-clipboard-list text-teal me-2"></i>Pusat Kelola Pesanan & Transaksi
        </h1>
        <p class="text-muted small mb-0">Pantau seluruh permintaan jasa masyarakat, respon mitra, dan status pengerjaan se-Inhu</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
            <i class="fa-solid fa-rotate-right me-1"></i> Refresh Data
        </button>
    </div>
</div>

<!-- Tab Filter Status Pesanan -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="btn-group shadow-xs flex-wrap">
                <a href="?status=" class="btn btn-sm <?= empty($status_filter) ? 'btn-teal text-white fw-bold' : 'btn-outline-secondary bg-white' ?>">
                    Semua Pesanan (<?= $count_all ?>)
                </a>
                <a href="?status=open" class="btn btn-sm <?= $status_filter === 'open' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark bg-white' ?>">
                    <i class="fa-solid fa-clock me-1"></i> Menunggu Respon (<?= $count_open ?>)
                </a>
                <a href="?status=in_progress" class="btn btn-sm <?= $status_filter === 'in_progress' ? 'btn-primary text-white fw-bold' : 'btn-outline-primary bg-white' ?>">
                    <i class="fa-solid fa-person-running me-1"></i> Diproses / OTW (<?= $count_in_progress ?>)
                </a>
                <a href="?status=completed" class="btn btn-sm <?= $status_filter === 'completed' ? 'btn-success text-white fw-bold' : 'btn-outline-success bg-white' ?>">
                    <i class="fa-solid fa-circle-check me-1"></i> Selesai (<?= $count_completed ?>)
                </a>
                <a href="?status=cancelled" class="btn btn-sm <?= $status_filter === 'cancelled' ? 'btn-danger text-white fw-bold' : 'btn-outline-danger bg-white' ?>">
                    <i class="fa-solid fa-ban me-1"></i> Dibatalkan (<?= $count_cancelled ?>)
                </a>
            </div>
        </div>

        <!-- Filter Pencarian & Kategori -->
        <form method="GET" action="" class="row g-2 align-items-center">
            <?php if (!empty($status_filter)): ?>
                <input type="hidden" name="status" value="<?= e($status_filter) ?>">
            <?php endif; ?>
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama pelanggan, mitra, atau jasa..." value="<?= e($q) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">-- Semua Kategori Jasa --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_filter === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="district_id" class="form-select form-select-sm">
                    <option value="">-- Semua Kecamatan --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $district_filter === (int)$d['id'] ? 'selected' : '' ?>>
                            Kec. <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1.5">
                <button type="submit" class="btn btn-sm btn-teal text-white w-100 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i> Saring
                </button>
                <?php if (!empty($q) || $category_filter > 0 || $district_filter > 0 || !empty($status_filter)): ?>
                    <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-sm btn-outline-secondary" title="Reset Saringan">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Daftar Pesanan -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5 bg-white">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            Daftar Pesanan Jasa (Total: <?= count($orders) ?> Pesanan Ditemukan)
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
            <thead class="table-light text-muted small text-uppercase fw-semibold">
                <tr>
                    <th class="ps-4" style="width: 70px;">ID & Tgl</th>
                    <th>Layanan & Masalah</th>
                    <th>Pelanggan</th>
                    <th>Mitra Dituju</th>
                    <th>Lokasi & Patokan</th>
                    <th>Tarif / Biaya</th>
                    <th class="text-center">Status</th>
                    <th class="pe-4 text-end">Aksi Pengawasan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $ord): ?>
                        <?php
                            $badgeClass = 'bg-secondary';
                            $statusLabel = 'Menunggu';
                            if ($ord['status'] === 'open') {
                                $badgeClass = 'bg-warning text-dark';
                                $statusLabel = 'Menunggu Respon';
                            } elseif ($ord['status'] === 'in_progress') {
                                $badgeClass = 'bg-primary text-white';
                                $statusLabel = 'Sedang Berjalan';
                                if ($ord['progress_step'] === 'on_the_way') $statusLabel = 'Mitra OTW';
                                if ($ord['progress_step'] === 'working') $statusLabel = 'Sedang Dikerjakan';
                            } elseif ($ord['status'] === 'completed') {
                                $badgeClass = 'bg-success text-white';
                                $statusLabel = 'Selesai';
                            } elseif ($ord['status'] === 'cancelled') {
                                $badgeClass = 'bg-danger text-white';
                                $statusLabel = 'Dibatalkan';
                            }

                            $custWaUrl = format_wa_url($ord['customer_phone'] ?? '', "Halo Kak {$ord['customer_name']}, kami dari Pengelola JASA INHU terkait pesanan jasa #{$ord['id']} ({$ord['title']})...");
                            $provWaUrl = !empty($ord['prov_phone']) ? format_wa_url($ord['prov_phone'], "Halo {$ord['business_name']}, kami dari Pengelola JASA INHU ingin konfirmasi respon Anda terkait pesanan #{$ord['id']} ({$ord['title']})...") : '';
                        ?>
                        <tr>
                            <!-- 1. ID & Tanggal -->
                            <td class="ps-4">
                                <span class="badge bg-light text-dark border font-monospace fw-bold">#<?= $ord['id'] ?></span>
                                <div class="text-muted" style="font-size: 0.72rem; margin-top: 3px;">
                                    <?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?>
                                </div>
                            </td>

                            <!-- 2. Layanan & Judul -->
                            <td style="max-width: 240px; white-space: normal;">
                                <div class="d-flex align-items-center gap-1.5 mb-1">
                                    <span class="badge bg-teal-subtle text-teal" style="font-size: 0.68rem;">
                                        <i class="fa-solid <?= e($ord['category_icon'] ?: 'fa-wrench') ?> me-1"></i>
                                        <?= e($ord['category_name']) ?>
                                    </span>
                                </div>
                                <div class="fw-bold text-dark lh-sm mb-1"><?= e($ord['title']) ?></div>
                                <div class="text-muted small text-truncate-2" style="font-size: 0.74rem; line-height: 1.35;">
                                    <?= e($ord['description']) ?>
                                </div>
                            </td>

                            <!-- 3. Pelanggan -->
                            <td>
                                <div class="fw-bold text-dark"><?= e($ord['customer_name']) ?></div>
                                <div class="font-monospace text-muted small" style="font-size: 0.76rem;">
                                    <?= e($ord['customer_phone']) ?>
                                </div>
                                <a href="<?= $custWaUrl ?>" target="_blank" class="badge bg-success-subtle text-success border border-success-subtle text-decoration-none py-1 px-2 mt-1 d-inline-flex align-items-center gap-1">
                                    <i class="fa-brands fa-whatsapp"></i> Chat WA Pelanggan
                                </a>
                            </td>

                            <!-- 4. Mitra Dituju -->
                            <td>
                                <?php if (!empty($ord['business_name'])): ?>
                                    <div class="fw-bold text-teal d-flex align-items-center gap-1">
                                        <a href="<?= BASE_URL ?>/admin/provider_detail.php?id=<?= $ord['prov_id'] ?>" class="text-teal text-decoration-none hover-underline">
                                            <?= e($ord['business_name']) ?>
                                        </a>
                                        <?php if ($ord['prov_verified']): ?>
                                            <i class="fa-solid fa-circle-check text-success small" title="Terverifikasi KTP"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small" style="font-size: 0.72rem;">
                                        Pemilik: <?= e($ord['prov_owner_name'] ?: '-') ?>
                                    </div>
                                    <?php if (!empty($provWaUrl)): ?>
                                        <a href="<?= $provWaUrl ?>" target="_blank" class="badge bg-light text-teal border text-decoration-none py-1 px-2 mt-1 d-inline-flex align-items-center gap-1">
                                            <i class="fa-brands fa-whatsapp text-success"></i> Chat WA Mitra
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border">
                                        <i class="fa-solid fa-bullhorn me-1"></i> Permintaan Tender Terbuka
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- 5. Lokasi -->
                            <td style="max-width: 180px; white-space: normal;">
                                <div class="fw-semibold text-dark small">
                                    <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                    Kec. <?= e($ord['district_name'] ?: 'Inhu') ?>
                                </div>
                                <div class="text-muted small text-truncate" style="font-size: 0.72rem;">
                                    <?= e($ord['address_detail'] ?: 'Tidak ada patokan') ?>
                                </div>
                            </td>

                            <!-- 6. Tarif / Biaya -->
                            <td>
                                <?php if (!empty($ord['final_price']) && $ord['final_price'] > 0): ?>
                                    <div class="fw-bold text-success fs-6">
                                        Rp <?= number_format((float)$ord['final_price'], 0, ',', '.') ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.68rem;">Kesepakatan Akhir</div>
                                <?php elseif (!empty($ord['budget']) && $ord['budget'] > 0): ?>
                                    <div class="fw-bold text-dark">
                                        Rp <?= number_format((float)$ord['budget'], 0, ',', '.') ?>
                                    </div>
                                    <div class="text-muted" style="font-size: 0.68rem;">Estimasi / Budget</div>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Sesuai Kesepakatan</span>
                                <?php endif; ?>
                            </td>

                            <!-- 7. Status -->
                            <td class="text-center">
                                <span class="badge <?= $badgeClass ?> px-2.5 py-1.5 rounded-pill shadow-xs fw-semibold">
                                    <?= $statusLabel ?>
                                </span>
                                <?php if ($ord['review_rating']): ?>
                                    <div class="text-warning small mt-1" title="<?= e($ord['review_comment'] ?? '') ?>">
                                        <i class="fa-solid fa-star"></i> <?= number_format($ord['review_rating'], 1) ?> Ulasan
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- 8. Aksi Pengawasan -->
                            <td class="pe-4 text-end">
                                <div class="btn-group shadow-xs">
                                    <a href="<?= BASE_URL ?>/invoice.php?id=<?= $ord['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Lihat Kwitansi / Rincian">
                                        <i class="fa-solid fa-receipt"></i> Kwitansi
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        Aksi
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li>
                                            <a class="dropdown-item small" href="<?= BASE_URL ?>/invoice.php?id=<?= $ord['id'] ?>" target="_blank">
                                                <i class="fa-solid fa-eye me-2 text-primary"></i> Buka Halaman Kwitansi
                                            </a>
                                        </li>
                                        <?php if (!empty($ord['prov_id'])): ?>
                                            <li>
                                                <a class="dropdown-item small" href="<?= BASE_URL ?>/admin/provider_detail.php?id=<?= $ord['prov_id'] ?>">
                                                    <i class="fa-solid fa-address-card me-2 text-teal"></i> Buka Arsip Mitra Ini
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if ($ord['status'] !== 'completed'): ?>
                                            <li>
                                                <button class="dropdown-item small text-success" onclick="openCompleteModal(<?= $ord['id'] ?>, '<?= addslashes($ord['title']) ?>')">
                                                    <i class="fa-solid fa-circle-check me-2"></i> Tandai Selesai Manual
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                        <?php if ($ord['status'] !== 'cancelled'): ?>
                                            <li>
                                                <button class="dropdown-item small text-danger" onclick="openCancelModal(<?= $ord['id'] ?>, '<?= addslashes($ord['title']) ?>')">
                                                    <i class="fa-solid fa-ban me-2"></i> Batalkan Pesanan Ini
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-clipboard-check fs-1 opacity-25 d-block mb-2"></i>
                            <div class="fw-bold">Belum Ada Pesanan yang Sesuai Saringan</div>
                            <div class="small">Pesanan yang dibuat warga Inhu di web akan otomatis tampil dan terpantau secara real-time di sini.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Batalkan Pesanan -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_order">
            <input type="hidden" name="order_id" id="cancelOrderId" value="">

            <div class="modal-header bg-danger text-white border-0 py-3 px-4">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Batalkan Pesanan
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3" id="cancelOrderText">
                    Anda yakin ingin membatalkan pesanan ini?
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Alasan Pembatalan (Terekam di sistem):</label>
                    <textarea name="cancel_reason" rows="3" class="form-control form-control-sm" placeholder="Contoh: Mitra berhalangan hadir / Permintaan pelanggan tidak dapat dipenuhi" required></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-light">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Tutup</button>
                <button type="submit" class="btn btn-sm btn-danger fw-bold px-4">Ya, Batalkan Pesanan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Selesaikan Pesanan Manual -->
<div class="modal fade" id="completeOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="complete_order">
            <input type="hidden" name="order_id" id="completeOrderId" value="">

            <div class="modal-header bg-success text-white border-0 py-3 px-4">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-circle-check me-1"></i> Selesaikan Pesanan Secara Manual
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3" id="completeOrderText">
                    Tandai pesanan ini telah selesai dikerjakan dengan sukses.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Nominal Kesepakatan Biaya Akhir (Rp):</label>
                    <input type="number" name="final_price" class="form-control form-control-sm" placeholder="Contoh: 150000">
                    <div class="text-muted" style="font-size: 0.68rem; margin-top: 2px;">Kosongkan jika nominal sudah sesuai sebelumnya.</div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-light">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-success fw-bold px-4">Tandai Selesai</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelModal(orderId, title) {
    document.getElementById('cancelOrderId').value = orderId;
    document.getElementById('cancelOrderText').textContent = `Anda akan membatalkan pesanan #${orderId}: "${title}".`;
    new bootstrap.Modal(document.getElementById('cancelOrderModal')).show();
}

function openCompleteModal(orderId, title) {
    document.getElementById('completeOrderId').value = orderId;
    document.getElementById('completeOrderText').textContent = `Tandai pesanan #${orderId}: "${title}" telah berhasil diselesaikan oleh mitra.`;
    new bootstrap.Modal(document.getElementById('completeOrderModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

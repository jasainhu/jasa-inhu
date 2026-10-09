<?php
/**
 * Jelajahi Permintaan Pekerjaan Terbuka (Leads) JASA INHU
 */

$page_title = 'Peluang Pekerjaan Terbuka';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('penyedia');

$user = current_user();
$db = get_db();

// Ambil provider record
$stmtProv = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
$stmtProv->execute([$user['id']]);
$prov_id = (int)$stmtProv->fetchColumn();

// Filter
$cat_filter = (int)($_GET['kategori'] ?? 0);
$dist_filter = (int)($_GET['kecamatan'] ?? 0);

$where = ["sr.status = 'open'"];
$params = [$prov_id];

if ($cat_filter > 0) {
    $where[] = "sr.category_id = ?";
    $params[] = $cat_filter;
}

if ($dist_filter > 0) {
    $where[] = "sr.district_id = ?";
    $params[] = $dist_filter;
}

$where_sql = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT sr.*, u.name as customer_name, sc.name as category_name, sc.icon as category_icon, d.name as district_name,
           (SELECT COUNT(*) FROM service_request_responses srr WHERE srr.request_id = sr.id) as total_offers,
           (SELECT id FROM service_request_responses srr WHERE srr.request_id = sr.id AND srr.provider_id = ?) as my_response_id
    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id
    JOIN service_categories sc ON sr.category_id = sc.id
    JOIN districts d ON sr.district_id = d.id
    WHERE {$where_sql}
    ORDER BY sr.created_at DESC
");
$stmt->execute($params);
$leads = $stmt->fetchAll();

$categories = get_active_categories();
$districts = get_all_districts();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Peluang Pekerjaan Terbuka</h3>
            <p class="text-muted small mb-0">Cari permintaan jasa dari masyarakat di seluruh kecamatan Kabupaten Indragiri Hulu</p>
        </div>
        <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-outline-custom btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="card border shadow-sm p-3 mb-4 bg-white">
        <form method="GET" action="<?= BASE_URL ?>/provider/leads.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Filter Kategori</label>
                <select name="kategori" class="form-select form-select-sm">
                    <option value="">Semua Kategori Jasa</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($cat_filter === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Filter Kecamatan di Inhu</label>
                <select name="kecamatan" class="form-select form-select-sm">
                    <option value="">Semua Kecamatan</option>
                    <?php foreach ($districts as $dst): ?>
                        <option value="<?= $dst['id'] ?>" <?= ($dist_filter === (int)$dst['id']) ? 'selected' : '' ?>>Kec. <?= e($dst['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1 d-none d-md-block">&nbsp;</label>
                <button type="submit" class="btn btn-primary-custom btn-sm w-100 py-1">
                    <i class="fa-solid fa-filter me-1"></i> Terapkan
                </button>
            </div>
        </form>
    </div>

    <!-- Hasil Leads -->
    <div class="row g-3">
        <?php if (!empty($leads)): ?>
            <?php foreach ($leads as $ld): ?>
                <div class="col-md-6">
                    <div class="p-4 rounded-3 border bg-white shadow-sm h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge text-bg-light border text-primary">
                                    <i class="fa-solid <?= e($ld['category_icon']) ?> me-1"></i> <?= e($ld['category_name']) ?>
                                </span>
                                <span class="fw-bold text-teal fs-6"><?= format_rupiah($ld['budget']) ?></span>
                            </div>
                            <h5 class="fw-bold text-dark mb-1"><?= e($ld['title']) ?></h5>
                            <p class="small text-muted mb-3"><?= e($ld['description']) ?></p>
                        </div>

                        <div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top small text-muted mb-3">
                                <span><i class="fa-solid fa-location-dot text-danger me-1"></i> Kec. <?= e($ld['district_name']) ?></span>
                                <span>Pemohon: <?= e($ld['customer_name']) ?></span>
                            </div>

                            <?php if ($ld['my_response_id']): ?>
                                <button type="button" class="btn btn-sm btn-outline-success w-100 disabled">
                                    <i class="fa-solid fa-check me-1"></i> Anda Sudah Mengirim Penawaran
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-primary-custom w-100" data-bs-toggle="collapse" data-bs-target="#leadOffer<?= $ld['id'] ?>">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Beri Penawaran
                                </button>

                                <div class="collapse mt-3" id="leadOffer<?= $ld['id'] ?>">
                                    <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="p-3 bg-light rounded border">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="submit_offer">
                                        <input type="hidden" name="request_id" value="<?= $ld['id'] ?>">
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Harga Penawaran (Rp) <span class="text-danger">*</span></label>
                                            <input type="number" name="offer_price" class="form-control form-control-sm" placeholder="150000" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Estimasi Waktu Pengerjaan</label>
                                            <input type="text" name="estimated_duration" class="form-control form-control-sm" placeholder="2 Jam">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Pesan Penjelasan <span class="text-danger">*</span></label>
                                            <textarea name="message" rows="2" class="form-control form-control-sm" placeholder="Jelaskan penawaran Anda..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary-custom w-100">Kirim Penawaran Sekarang</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-light border text-center py-5">
                    <i class="fa-solid fa-folder-open fs-2 text-muted mb-3 d-block"></i>
                    <h5 class="fw-bold text-dark">Tidak Ada Permintaan yang Cocok</h5>
                    <p class="text-muted small mb-0">Coba ubah filter kategori atau kecamatan di atas.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

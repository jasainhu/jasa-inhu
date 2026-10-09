<?php
/**
 * Admin: Kelola Kategori Jasa
 */

$page_title = 'Kelola Kategori Jasa';
$admin_active = 'categories';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();
$error = '';

// Handle tambah kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir.';
    } else {
        $action = $_POST['action'];

        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $icon = trim($_POST['icon'] ?? 'fa-wrench');
            $desc = trim($_POST['description'] ?? '');
            $order = (int)($_POST['sort_order'] ?? 0);

            if (empty($name)) {
                $error = 'Nama kategori wajib diisi.';
            } else {
                // Buat slug sederhana
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

                try {
                    $stmt = $db->prepare("INSERT INTO service_categories (name, slug, icon, description, sort_order, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                    $stmt->execute([$name, $slug, $icon, $desc, $order]);
                    set_flash('success', "Kategori '{$name}' berhasil ditambahkan.");
                    redirect('/admin/categories.php');
                } catch (Exception $e) {
                    $error = 'Gagal menambah kategori (mungkin nama/slug sudah ada): ' . $e->getMessage();
                }
            }
        } elseif ($action === 'toggle_status') {
            $id = (int)$_POST['category_id'];
            $new_status = (int)$_POST['new_status'];
            $stmt = $db->prepare("UPDATE service_categories SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            set_flash('success', 'Status kategori berhasil diperbarui.');
            redirect('/admin/categories.php');
        }
    }
}

// Ambil semua kategori
$categories = $db->query("
    SELECT sc.*,
           (SELECT COUNT(*) FROM service_providers sp WHERE sp.primary_category_id = sc.id) as total_providers,
           (SELECT COUNT(*) FROM service_requests sr WHERE sr.category_id = sc.id) as total_requests
    FROM service_categories sc
    ORDER BY sc.sort_order ASC, sc.name ASC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">Kategori Jasa Lokal</h1>
        <p class="text-muted small mb-0">Kelola master bidang keahlian dan jasa yang tersedia di Kabupaten Indragiri Hulu</p>
    </div>
    <button type="button" class="btn btn-primary-custom btn-sm" data-bs-toggle="collapse" data-bs-target="#addCategoryForm">
        <i class="fa-solid fa-plus me-1"></i> Tambah Kategori Baru
    </button>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-4"><?= e($error) ?></div>
<?php endif; ?>

<!-- Form Tambah Kategori (Collapsible) -->
<div class="collapse mb-4" id="addCategoryForm">
    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 14px;">
        <h5 class="fw-bold mb-3"><i class="fa-solid fa-circle-plus text-primary me-2"></i> Tambah Kategori Jasa</h5>
        <form method="POST" action="<?= BASE_URL ?>/admin/categories.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Nama Kategori</label>
                    <input type="text" name="name" class="form-control" placeholder="Contoh: Servis Kulkas & Freon" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">FontAwesome Icon Class</label>
                    <input type="text" name="icon" class="form-control" placeholder="fa-snowflake" value="fa-wrench" required>
                    <div class="form-text" style="font-size: 0.72rem;">Gunakan class FontAwesome 6 (misal: fa-car, fa-bolt)</div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Urutan (Sort)</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">&nbsp;</label>
                    <button type="submit" class="btn btn-primary-custom w-100">
                        <i class="fa-solid fa-save me-1"></i> Simpan Kategori
                    </button>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Deskripsi Singkat</label>
                    <input type="text" name="description" class="form-control" placeholder="Penjelasan singkat ruang lingkup jasa ini...">
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Daftar Kategori Table -->
<div class="card border shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">Icon</th>
                    <th>Nama Kategori</th>
                    <th>Slug</th>
                    <th>Deskripsi</th>
                    <th class="text-center">Mitra</th>
                    <th class="text-center">Permintaan</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="text-center">
                            <div class="category-icon-wrapper mx-auto" style="width: 36px; height: 36px; font-size: 1rem; border-radius: 8px;">
                                <i class="fa-solid <?= e($cat['icon']) ?>"></i>
                            </div>
                        </td>
                        <td>
                            <strong class="text-dark"><?= e($cat['name']) ?></strong>
                        </td>
                        <td><code><?= e($cat['slug']) ?></code></td>
                        <td class="text-muted" style="max-width: 250px;"><?= e($cat['description']) ?></td>
                        <td class="text-center">
                            <span class="badge text-bg-light border"><?= $cat['total_providers'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge text-bg-light border"><?= $cat['total_requests'] ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($cat['is_active']): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="<?= BASE_URL ?>/admin/categories.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $cat['is_active'] ? 0 : 1 ?>">
                                <button type="submit" class="btn btn-sm <?= $cat['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> py-0 px-2" style="font-size: 0.75rem;">
                                    <?= $cat['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Kelola Galeri & Portofolio Hasil Kerja Mitra JASA INHU
 */

$page_title = 'Portofolio Hasil Kerja';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('penyedia');

$user = current_user();
$db = get_db();
$error = '';

// Ambil provider record lengkap beserta kategori
$stmtProv = $db->prepare("
    SELECT sp.*, sc.name as category_name, sc.slug as category_slug 
    FROM service_providers sp
    LEFT JOIN service_categories sc ON sp.primary_category_id = sc.id
    WHERE sp.user_id = ? 
    LIMIT 1
");
$stmtProv->execute([$user['id']]);
$provider = $stmtProv->fetch();

if (!$provider) {
    set_flash('danger', 'Data profil penyedia belum terdaftar.');
    redirect('/provider/profile.php');
}

$is_culinary = is_culinary_category($provider['category_name'] ?? '');
$page_title = $is_culinary ? 'Katalog Menu & Harga Makanan' : 'Portofolio Hasil Kerja';

// Handle Add Portfolio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_portfolio') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan ulangi.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $service_date = !empty($_POST['service_date']) ? $_POST['service_date'] : date('Y-m-d');

        if (empty($title)) {
            $error = $is_culinary ? 'Nama menu atau makanan wajib diisi.' : 'Judul pekerjaan atau nama proyek wajib diisi.';
        } elseif (!isset($_FILES['image_after']) || $_FILES['image_after']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = $is_culinary ? 'Foto menu/makanan wajib diunggah.' : 'Foto hasil pengerjaan (sesudah) wajib diunggah.';
        } elseif ($_FILES['image_after']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['image_after']['error'] === UPLOAD_ERR_FORM_SIZE) {
            $error = 'Ukuran foto terlalu besar melebihi batas upload server. Silakan coba kembali, foto akan dikompresi otomatis.';
        } elseif ($_FILES['image_after']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Gagal mengunggah foto (Kode error: ' . $_FILES['image_after']['error'] . '). Silakan pilih ulang foto.';
        } else {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
            $max_size = 10 * 1024 * 1024; // 10MB

            // Pastikan folder uploads/portfolios tersedia
            $portfolioDir = __DIR__ . '/../uploads/portfolios';
            if (!is_dir($portfolioDir)) {
                @mkdir($portfolioDir, 0777, true);
            }

            try {
                $pathAfter = save_uploaded_media($_FILES['image_after'], 'portfolios', 'after_' . $provider['id'], 1200, 82);
                $pathBefore = null;

                if (isset($_FILES['image_before']) && $_FILES['image_before']['error'] === UPLOAD_ERR_OK) {
                    $pathBefore = save_uploaded_media($_FILES['image_before'], 'portfolios', 'before_' . $provider['id'], 1200, 82);
                }

                $stmtIns = $db->prepare("
                    INSERT INTO provider_portfolios 
                    (provider_id, title, price, description, image_before, image_after, service_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtIns->execute([$provider['id'], $title, $price ?: null, $description, $pathBefore, $pathAfter, $service_date]);

                set_flash('success', $is_culinary ? 'Menu makanan berhasil ditambahkan ke daftar menu Anda!' : 'Foto hasil kerja berhasil ditambahkan ke portofolio Anda!');
                redirect('/provider/portfolio.php');
            } catch (Exception $e) {
                $error = 'Gagal menyimpan portofolio: ' . $e->getMessage();
            }
        }
    }
}

// Handle Delete Portfolio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_portfolio') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir.';
    } else {
        $portfolio_id = (int)($_POST['portfolio_id'] ?? 0);
        $stmtGet = $db->prepare("SELECT * FROM provider_portfolios WHERE id = ? AND provider_id = ?");
        $stmtGet->execute([$portfolio_id, $provider['id']]);
        $item = $stmtGet->fetch();

        if ($item) {
            if (!empty($item['image_after']) && file_exists(__DIR__ . '/../' . $item['image_after'])) {
                @unlink(__DIR__ . '/../' . $item['image_after']);
            }
            if (!empty($item['image_before']) && file_exists(__DIR__ . '/../' . $item['image_before'])) {
                @unlink(__DIR__ . '/../' . $item['image_before']);
            }

            $stmtDel = $db->prepare("DELETE FROM provider_portfolios WHERE id = ? AND provider_id = ?");
            $stmtDel->execute([$portfolio_id, $provider['id']]);

            set_flash('success', $is_culinary ? 'Menu makanan berhasil dihapus.' : 'Foto portofolio berhasil dihapus.');
            $redirect_to = !empty($_POST['return_to']) ? $_POST['return_to'] : '/provider/portfolio.php';
            redirect($redirect_to);
        } else {
            $error = 'Data portofolio tidak ditemukan atau Anda tidak memiliki akses.';
        }
    }
}

// Ambil list portofolio
$stmtList = $db->prepare("SELECT * FROM provider_portfolios WHERE provider_id = ? ORDER BY created_at DESC");
$stmtList->execute([$provider['id']]);
$portfolios = $stmtList->fetchAll();

$hide_ai_widget = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5">
                    <i class="fa-solid fa-arrow-left"></i> Dashboard
                </a>
                <span class="badge bg-teal-subtle text-teal fw-bold">Mitra JASA INHU</span>
                <?php if ($is_culinary): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis fw-bold">
                        <i class="fa-solid fa-utensils me-1"></i> Kuliner & Katering
                    </span>
                <?php endif; ?>
            </div>
            <h3 class="fw-bold mb-1 text-dark">
                <?= $is_culinary ? 'Katalog Menu & Daftar Harga Makanan' : 'Galeri Portofolio Hasil Kerja' ?>
            </h3>
            <p class="text-muted small mb-0">
                <?= $is_culinary 
                    ? 'Kelola foto menu makanan, minuman, dan paket katering beserta harganya agar pembeli se-Inhu dapat langsung memilih hidangan favorit mereka.' 
                    : 'Tampilkan foto nyata hasil pengerjaan Anda (Sebelum & Sesudah) agar calon pelanggan di Inhu makin percaya.' ?>
            </p>
        </div>
        <button type="button" class="btn btn-primary-custom fw-bold px-3 py-2 shadow-sm rounded-3 text-nowrap" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
            <i class="fa-solid <?= $is_culinary ? 'fa-plus' : 'fa-cloud-arrow-up' ?> me-1"></i> 
            <?= $is_culinary ? 'Tambah Menu Makanan' : 'Tambah Foto Hasil Kerja' ?>
        </button>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger mb-4 shadow-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Portfolio Grid -->
    <?php if (!empty($portfolios)): ?>
        <div class="row g-4">
            <?php foreach ($portfolios as $p): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border shadow-sm rounded-4 overflow-hidden bg-white">
                        <!-- Before & After or Single Image View -->
                        <?php if (has_media($p['image_before'] ?? '')): ?>
                            <div class="row g-0 border-bottom" style="height: 190px;">
                                <div class="col-6 position-relative border-end overflow-hidden">
                                    <img src="<?= e(get_portfolio_image_url($p, 'before')) ?>" alt="<?= $is_culinary ? 'Foto 1' : 'Sebelum' ?>" class="w-100 h-100 object-fit-cover" loading="lazy">
                                    <span class="badge <?= $is_culinary ? 'bg-secondary' : 'bg-danger' ?> position-absolute top-0 start-0 m-2 fw-bold" style="font-size: 0.65rem;">
                                        <?= $is_culinary ? 'FOTO 1' : 'SEBELUM' ?>
                                    </span>
                                </div>
                                <div class="col-6 position-relative overflow-hidden">
                                    <img src="<?= e(get_portfolio_image_url($p, 'after')) ?>" alt="<?= $is_culinary ? 'Foto 2' : 'Sesudah' ?>" class="w-100 h-100 object-fit-cover" loading="lazy">
                                    <span class="badge bg-success position-absolute top-0 end-0 m-2 fw-bold" style="font-size: 0.65rem;">
                                        <?= $is_culinary ? 'FOTO 2' : 'SESUDAH' ?>
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="position-relative overflow-hidden border-bottom" style="height: 190px;">
                                <img src="<?= e(get_portfolio_image_url($p, 'after')) ?>" alt="<?= e($p['title']) ?>" class="w-100 h-100 object-fit-cover" loading="lazy">
                                <span class="badge bg-success position-absolute top-0 end-0 m-2 fw-bold" style="font-size: 0.65rem;">
                                    <?= $is_culinary ? 'MENU AKTIF' : 'HASIL PENGERJAAN' ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Card Body -->
                        <div class="card-body p-3 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <?php if ($is_culinary): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 rounded-pill" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-circle-check me-1"></i> Siap Dipesan
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small" style="font-size: 0.75rem;">
                                        <i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y', strtotime($p['service_date'] ?: $p['created_at'])) ?>
                                    </span>
                                    <?php if (!empty($p['image_before'])): ?>
                                        <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.68rem;">Sebelum vs Sesudah</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <h5 class="fw-bold mb-1 text-dark fs-6"><?= e($p['title']) ?></h5>

                            <?php if (!empty($p['price'])): ?>
                                <div class="mb-2">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold px-2 py-1" style="font-size: 0.85rem;">
                                        <i class="fa-solid fa-tag me-1"></i> <?= e(format_portfolio_price($p['price'])) ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($p['description'])): ?>
                                <p class="text-muted small mb-3 flex-grow-1" style="font-size: 0.82rem; line-height: 1.4;">
                                    <?= nl2br(e($p['description'])) ?>
                                </p>
                            <?php else: ?>
                                <div class="flex-grow-1"></div>
                            <?php endif; ?>

                            <div class="pt-2 border-top d-flex align-items-center justify-content-end">
                                <form method="POST" action="<?= BASE_URL ?>/provider/portfolio.php"
                                      data-confirm="<?= $is_culinary ? 'Menu makanan ini akan dihapus permanen dari daftar menu Anda.' : 'Foto portofolio hasil kerja ini akan dihapus permanen dari etalase profil Anda.' ?>"
                                      data-confirm-title="<?= $is_culinary ? 'Hapus Menu Makanan?' : 'Hapus Foto Hasil Kerja?' ?>"
                                      data-confirm-btn="Ya, Hapus"
                                      data-confirm-cancel="Batal"
                                      data-confirm-type="danger">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_portfolio">
                                    <input type="hidden" name="portfolio_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2.5">
                                        <i class="fa-solid fa-trash me-1"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" style="width: 72px; height: 72px; background: #ccfbf1; color: #0d9488;">
                <i class="fa-solid <?= $is_culinary ? 'fa-utensils' : 'fa-camera-retro' ?> fs-2"></i>
            </div>
            <h4 class="fw-bold mb-1 text-dark">
                <?= $is_culinary ? 'Belum Ada Menu Makanan yang Ditambahkan' : 'Belum Ada Foto Portofolio' ?>
            </h4>
            <p class="text-muted small mx-auto mb-4" style="max-width: 480px;">
                <?= $is_culinary 
                    ? 'Calon pembeli di Indragiri Hulu 3x lebih cepat memesan jika melihat foto hidangan yang menggugah selera dan daftar harga yang jelas.' 
                    : 'Calon pelanggan di Indragiri Hulu 3x lebih cepat memesan jasa jika melihat bukti foto nyata pekerjaan Anda sebelumnya.' ?>
            </p>
            <div>
                <button type="button" class="btn btn-primary-custom fw-bold px-4 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                    <i class="fa-solid fa-plus me-1"></i> 
                    <?= $is_culinary ? 'Tambah Menu Makanan Pertama' : 'Unggah Foto Hasil Kerja Pertama' ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Portofolio -->
<div class="modal fade" id="addPortfolioModal" tabindex="-1" aria-labelledby="addPortfolioModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-bottom px-4 py-3">
                <div>
                    <h5 class="modal-title fw-bold mb-0 text-dark" id="addPortfolioModalLabel">
                        <?php if ($is_culinary): ?>
                            <i class="fa-solid fa-bowl-food text-teal me-1"></i> Tambah Menu Makanan & Minuman
                        <?php else: ?>
                            <i class="fa-solid fa-camera text-teal me-1"></i> Tambah Foto Hasil Pengerjaan
                        <?php endif; ?>
                    </h5>
                    <span class="text-muted small" style="font-size: 0.78rem;">
                        <?= $is_culinary ? 'Unggah foto menu beserta harga dan porsi hidangan' : 'Unggah bukti nyata hasil perbaikan atau pekerjaan Anda' ?>
                    </span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/provider/portfolio.php" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_portfolio">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">
                            <?= $is_culinary ? 'Nama Menu / Makanan / Minuman' : 'Judul Pekerjaan / Layanan' ?> <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" class="form-control" 
                               placeholder="<?= $is_culinary ? 'Contoh: Ayam Geprek Sambal Korek + Nasi' : 'Contoh: Pemasangan Kanopi Baja Ringan di Belilas' ?>" required>
                    </div>

                    <!-- Harga / Tarif -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">
                            <?= $is_culinary ? 'Harga Menu' : 'Estimasi Tarif / Biaya' ?> 
                            <span class="text-muted small fw-normal">(Opsional)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted fw-bold">Rp</span>
                            <input type="text" name="price" class="form-control" 
                                   placeholder="<?= $is_culinary ? 'Contoh: 15.000 atau 15.000 / porsi' : 'Contoh: 75.000 atau 60.000 - 80.000' ?>">
                        </div>
                        <div class="form-text small" style="font-size: 0.72rem;">
                            <?= $is_culinary ? 'Bisa diisi harga per porsi, per kotak, atau paket.' : 'Biarkan kosong jika biaya perlu survei lapangan terlebih dahulu.' ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Foto Utama (Sesudah / Foto Menu) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success">
                                <i class="fa-solid fa-circle-check me-1"></i> 
                                <?= $is_culinary ? 'Foto Menu / Makanan' : 'Foto Hasil Selesai (Sesudah)' ?> <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="image_after" id="portfolioImageAfterInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/*" required>
                            <div id="portfolioAfterStatus"></div>
                            <div class="form-text small" style="font-size: 0.72rem;">Wajib diisi. Format JPG, PNG, WebP (Otomatis dikompresi).</div>
                        </div>

                        <!-- Foto Sebelum / Tambahan (Opsional) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold <?= $is_culinary ? 'text-secondary' : 'text-danger' ?>">
                                <i class="fa-solid <?= $is_culinary ? 'fa-image' : 'fa-clock-rotate-left' ?> me-1"></i> 
                                <?= $is_culinary ? 'Foto Tambahan / Variasi' : 'Foto Kondisi Awal (Sebelum)' ?> 
                                <span class="text-muted small fw-normal">(Opsional)</span>
                            </label>
                            <input type="file" name="image_before" id="portfolioImageBeforeInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/*">
                            <div id="portfolioBeforeStatus"></div>
                            <div class="form-text small" style="font-size: 0.72rem;">
                                <?= $is_culinary ? 'Opsional untuk foto varian atau kemasan.' : 'Opsional untuk perbandingan Sebelum vs Sesudah.' ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!$is_culinary): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-dark">Tanggal Selesai Pengerjaan</label>
                                <input type="date" name="service_date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="service_date" value="<?= date('Y-m-d') ?>">
                    <?php endif; ?>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark">
                            <?= $is_culinary ? 'Keterangan Menu / Porsi / Komposisi' : 'Keterangan Singkat / Catatan Pekerjaan' ?> 
                            <span class="text-muted small fw-normal">(Opsional)</span>
                        </label>
                        <textarea name="description" rows="3" class="form-control" 
                                  placeholder="<?= $is_culinary ? 'Contoh: Sudah termasuk lalapan timun, kemangi, dan sambal terasi. Tersedia level pedas 1-5.' : 'Contoh: Pengerjaan selesai dalam 2 hari, bahan tebal galvanis tahan karat dan rapi.' ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom fw-bold px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i> 
                        <?= $is_culinary ? 'Simpan ke Buku Menu' : 'Simpan ke Portofolio' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const afterInput = document.getElementById('portfolioImageAfterInput');
    if (afterInput && window.attachAutoCompressor) {
        window.attachAutoCompressor(afterInput, {
            statusEl: 'portfolioAfterStatus',
            maxWidth: 1600,
            maxHeight: 1600,
            quality: 0.85
        });
    }

    const beforeInput = document.getElementById('portfolioImageBeforeInput');
    if (beforeInput && window.attachAutoCompressor) {
        window.attachAutoCompressor(beforeInput, {
            statusEl: 'portfolioBeforeStatus',
            maxWidth: 1600,
            maxHeight: 1600,
            quality: 0.85
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

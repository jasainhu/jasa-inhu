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

// Ambil provider record
$stmtProv = $db->prepare("SELECT * FROM service_providers WHERE user_id = ? LIMIT 1");
$stmtProv->execute([$user['id']]);
$provider = $stmtProv->fetch();

if (!$provider) {
    set_flash('danger', 'Data profil penyedia belum terdaftar.');
    redirect('/provider/profile.php');
}

// Handle Add Portfolio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_portfolio') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan ulangi.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $service_date = !empty($_POST['service_date']) ? $_POST['service_date'] : date('Y-m-d');

        if (empty($title)) {
            $error = 'Judul pekerjaan atau nama proyek wajib diisi.';
        } elseif (!isset($_FILES['image_after']) || $_FILES['image_after']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Foto hasil pengerjaan (sesudah) wajib diunggah.';
        } else {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size = 4 * 1024 * 1024; // 4MB

            // Upload Image After
            $fileAfter = $_FILES['image_after'];
            $extAfter = strtolower(pathinfo($fileAfter['name'], PATHINFO_EXTENSION));

            if (!in_array($extAfter, $allowed)) {
                $error = 'Format foto hasil pengerjaan harus JPG, PNG, atau WebP.';
            } elseif ($fileAfter['size'] > $max_size) {
                $error = 'Ukuran foto hasil pengerjaan terlalu besar (Maks 4MB).';
            } else {
                $filenameAfter = 'after_' . $provider['id'] . '_' . time() . '_' . rand(100, 999) . '.' . $extAfter;
                $targetAfter = __DIR__ . '/../uploads/portfolios/' . $filenameAfter;

                if (move_uploaded_file($fileAfter['tmp_name'], $targetAfter)) {
                    $pathAfter = 'uploads/portfolios/' . $filenameAfter;
                    $pathBefore = null;

                    // Handle Optional Image Before
                    if (isset($_FILES['image_before']) && $_FILES['image_before']['error'] === UPLOAD_ERR_OK) {
                        $fileBefore = $_FILES['image_before'];
                        $extBefore = strtolower(pathinfo($fileBefore['name'], PATHINFO_EXTENSION));
                        if (in_array($extBefore, $allowed) && $fileBefore['size'] <= $max_size) {
                            $filenameBefore = 'before_' . $provider['id'] . '_' . time() . '_' . rand(100, 999) . '.' . $extBefore;
                            $targetBefore = __DIR__ . '/../uploads/portfolios/' . $filenameBefore;
                            if (move_uploaded_file($fileBefore['tmp_name'], $targetBefore)) {
                                $pathBefore = 'uploads/portfolios/' . $filenameBefore;
                            }
                        }
                    }

                    try {
                        $stmtIns = $db->prepare("
                            INSERT INTO provider_portfolios 
                            (provider_id, title, description, image_before, image_after, service_date, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmtIns->execute([$provider['id'], $title, $description, $pathBefore, $pathAfter, $service_date]);

                        set_flash('success', 'Foto hasil kerja berhasil ditambahkan ke portofolio Anda!');
                        redirect('/provider/portfolio.php');
                    } catch (Exception $e) {
                        $error = 'Gagal menyimpan portofolio: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Gagal mengunggah file foto. Pastikan izin folder uploads aktif.';
                }
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

            set_flash('success', 'Foto portofolio berhasil dihapus.');
            redirect('/provider/portfolio.php');
        } else {
            $error = 'Data portofolio tidak ditemukan atau Anda tidak memiliki akses.';
        }
    }
}

// Ambil list portofolio
$stmtList = $db->prepare("SELECT * FROM provider_portfolios WHERE provider_id = ? ORDER BY created_at DESC");
$stmtList->execute([$provider['id']]);
$portfolios = $stmtList->fetchAll();

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
            </div>
            <h3 class="fw-bold mb-1 text-dark">Galeri Portofolio Hasil Kerja</h3>
            <p class="text-muted small mb-0">Tampilkan foto nyata hasil pengerjaan Anda (Sebelum & Sesudah) agar calon pelanggan di Inhu makin percaya.</p>
        </div>
        <button type="button" class="btn btn-primary-custom fw-bold px-3 py-2 shadow-sm rounded-3 text-nowrap" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
            <i class="fa-solid fa-cloud-arrow-up me-1"></i> Tambah Foto Hasil Kerja
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
                        <?php if (!empty($p['image_before']) && file_exists(__DIR__ . '/../' . $p['image_before'])): ?>
                            <div class="row g-0 border-bottom" style="height: 190px;">
                                <div class="col-6 position-relative border-end overflow-hidden">
                                    <img src="<?= BASE_URL ?>/<?= e($p['image_before']) ?>" alt="Sebelum" class="w-100 h-100 object-fit-cover">
                                    <span class="badge bg-danger position-absolute top-0 start-0 m-2 fw-bold" style="font-size: 0.65rem;">
                                        SEBELUM
                                    </span>
                                </div>
                                <div class="col-6 position-relative overflow-hidden">
                                    <img src="<?= BASE_URL ?>/<?= e($p['image_after']) ?>" alt="Sesudah" class="w-100 h-100 object-fit-cover">
                                    <span class="badge bg-success position-absolute top-0 end-0 m-2 fw-bold" style="font-size: 0.65rem;">
                                        SESUDAH
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="position-relative overflow-hidden border-bottom" style="height: 190px;">
                                <img src="<?= BASE_URL ?>/<?= e($p['image_after']) ?>" alt="<?= e($p['title']) ?>" class="w-100 h-100 object-fit-cover">
                                <span class="badge bg-success position-absolute top-0 end-0 m-2 fw-bold" style="font-size: 0.65rem;">
                                    HASIL PENGERJAAN
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Card Body -->
                        <div class="card-body p-3 d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted small" style="font-size: 0.75rem;">
                                    <i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y', strtotime($p['service_date'] ?: $p['created_at'])) ?>
                                </span>
                                <?php if (!empty($p['image_before'])): ?>
                                    <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.68rem;">Sebelum vs Sesudah</span>
                                <?php endif; ?>
                            </div>

                            <h5 class="fw-bold mb-1 text-dark fs-6"><?= e($p['title']) ?></h5>

                            <?php if (!empty($p['description'])): ?>
                                <p class="text-muted small mb-3 flex-grow-1" style="font-size: 0.82rem; line-height: 1.4;">
                                    <?= nl2br(e($p['description'])) ?>
                                </p>
                            <?php else: ?>
                                <div class="flex-grow-1"></div>
                            <?php endif; ?>

                            <div class="pt-2 border-top d-flex align-items-center justify-content-end">
                                <form method="POST" action="<?= BASE_URL ?>/provider/portfolio.php"
                                      data-confirm="Foto portofolio hasil kerja ini akan dihapus permanen dari etalase profil Anda."
                                      data-confirm-title="Hapus Foto Hasil Kerja?"
                                      data-confirm-btn="Ya, Hapus Foto"
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
                <i class="fa-solid fa-camera-retro fs-2"></i>
            </div>
            <h4 class="fw-bold mb-1 text-dark">Belum Ada Foto Portofolio</h4>
            <p class="text-muted small mx-auto mb-4" style="max-width: 480px;">
                Calon pelanggan di Indragiri Hulu 3x lebih cepat memesan jasa jika melihat bukti foto nyata pekerjaan Anda sebelumnya.
            </p>
            <div>
                <button type="button" class="btn btn-primary-custom fw-bold px-4 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addPortfolioModal">
                    <i class="fa-solid fa-plus me-1"></i> Unggah Foto Hasil Kerja Pertama
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
                        <i class="fa-solid fa-camera text-teal me-1"></i> Tambah Foto Hasil Pengerjaan
                    </h5>
                    <span class="text-muted small" style="font-size: 0.78rem;">Unggah bukti nyata hasil perbaikan atau pekerjaan Anda</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/provider/portfolio.php" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_portfolio">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Judul Pekerjaan / Layanan <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Contoh: Pemasangan Kanopi Baja Ringan di Belilas" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <!-- Foto Hasil Jadi (Sesudah) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success">
                                <i class="fa-solid fa-circle-check me-1"></i> Foto Hasil Selesai (Sesudah) <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="image_after" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" required>
                            <div class="form-text small" style="font-size: 0.72rem;">Wajib diisi. Format JPG, PNG, WebP (Maks 4MB).</div>
                        </div>

                        <!-- Foto Sebelum (Opsional) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-danger">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i> Foto Kondisi Awal (Sebelum) <span class="text-muted small fw-normal">(Opsional)</span>
                            </label>
                            <input type="file" name="image_before" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small" style="font-size: 0.72rem;">Opsional untuk perbandingan Sebelum vs Sesudah.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-dark">Tanggal Selesai Pengerjaan</label>
                            <input type="date" name="service_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark">Keterangan Singkat / Catatan Pekerjaan</label>
                        <textarea name="description" rows="3" class="form-control" placeholder="Contoh: Pengerjaan selesai dalam 2 hari, bahan tebal galvanis tahan karat dan rapi."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom fw-bold px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan ke Portofolio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Admin: Kelola Banner Promo & Pengumuman Beranda
 * JASA INHU
 */

$page_title = 'Kelola Banner & Iklan Beranda';
$admin_active = 'banners';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();
$error = '';

// Helper upload gambar banner
function handle_banner_upload(?array $file, ?string &$error_msg): ?string {
    if (!$file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $max_size = 3 * 1024 * 1024; // 3MB
    if ($file['size'] > $max_size) {
        $error_msg = 'Ukuran file gambar maksimal 3MB.';
        return null;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        $error_msg = 'Format gambar harus JPG, PNG, atau WebP.';
        return null;
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg'
    };

    $target_dir = __DIR__ . '/../uploads/banners';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $filename = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target_path = $target_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return 'uploads/banners/' . $filename;
    }

    $error_msg = 'Gagal menyimpan file gambar ke server.';
    return null;
}

// Handle Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'];

        // 1. TAMBAH BANNER BARU
        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $subtitle = trim($_POST['subtitle'] ?? '');
            $badge_text = trim($_POST['badge_text'] ?? 'PENGUMUMAN');
            $badge_color = trim($_POST['badge_color'] ?? '#0d9488');
            $link_url = trim($_POST['link_url'] ?? '');
            $button_text = trim($_POST['button_text'] ?? 'Lihat Selengkapnya');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $show_overlay = isset($_POST['show_overlay']) ? 1 : 0;
            $position = in_array($_POST['position'] ?? '', ['carousel', 'side_top', 'side_bottom', 'tender']) ? $_POST['position'] : 'carousel';

            if (empty($title)) {
                $error = 'Judul banner wajib diisi.';
            } else {
                $upload_err = null;
                $uploaded_image = handle_banner_upload($_FILES['banner_image'] ?? null, $upload_err);
                if ($upload_err) {
                    $error = $upload_err;
                } else {
                    $image_url = $uploaded_image ?? '';
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO banners 
                            (title, subtitle, badge_text, badge_color, image_url, link_url, button_text, show_overlay, sort_order, is_active, position, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$title, $subtitle, $badge_text, $badge_color, $image_url, $link_url, $button_text, $show_overlay, $sort_order, $is_active, $position]);
                        set_flash('success', 'Banner promosi/pengumuman baru berhasil ditambahkan.');
                        redirect('/admin/banners.php');
                    } catch (Exception $e) {
                        $error = 'Gagal menyimpan banner: ' . $e->getMessage();
                    }
                }
            }
        }

        // 2. UPDATE BANNER
        elseif ($action === 'update') {
            $id = (int)($_POST['banner_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $subtitle = trim($_POST['subtitle'] ?? '');
            $badge_text = trim($_POST['badge_text'] ?? 'PENGUMUMAN');
            $badge_color = trim($_POST['badge_color'] ?? '#0d9488');
            $link_url = trim($_POST['link_url'] ?? '');
            $button_text = trim($_POST['button_text'] ?? 'Lihat Selengkapnya');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $show_overlay = isset($_POST['show_overlay']) ? 1 : 0;
            $position = in_array($_POST['position'] ?? '', ['carousel', 'side_top', 'side_bottom', 'tender']) ? $_POST['position'] : 'carousel';

            if (empty($title) || $id <= 0) {
                $error = 'Data banner tidak valid.';
            } else {
                $upload_err = null;
                $uploaded_image = handle_banner_upload($_FILES['banner_image'] ?? null, $upload_err);
                if ($upload_err) {
                    $error = $upload_err;
                } else {
                    try {
                        if ($uploaded_image) {
                            $stmt = $db->prepare("
                                UPDATE banners 
                                SET title = ?, subtitle = ?, badge_text = ?, badge_color = ?, image_url = ?, link_url = ?, button_text = ?, show_overlay = ?, sort_order = ?, is_active = ?, position = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$title, $subtitle, $badge_text, $badge_color, $uploaded_image, $link_url, $button_text, $show_overlay, $sort_order, $is_active, $position, $id]);
                        } else {
                            $stmt = $db->prepare("
                                UPDATE banners 
                                SET title = ?, subtitle = ?, badge_text = ?, badge_color = ?, link_url = ?, button_text = ?, show_overlay = ?, sort_order = ?, is_active = ?, position = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$title, $subtitle, $badge_text, $badge_color, $link_url, $button_text, $show_overlay, $sort_order, $is_active, $position, $id]);
                        }
                        set_flash('success', 'Banner berhasil diperbarui.');
                        redirect('/admin/banners.php');
                    } catch (Exception $e) {
                        $error = 'Gagal memperbarui banner: ' . $e->getMessage();
                    }
                }
            }
        }

        // 3. TOGGLE STATUS AKTIF/NON-AKTIF
        elseif ($action === 'toggle_status') {
            $id = (int)$_POST['banner_id'];
            $new_status = (int)$_POST['new_status'];
            $stmt = $db->prepare("UPDATE banners SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            set_flash('success', 'Status banner berhasil diubah.');
            redirect('/admin/banners.php');
        }

        // 4. HAPUS BANNER
        elseif ($action === 'delete') {
            $id = (int)$_POST['banner_id'];
            // Cari data gambar untuk dihapus
            $stmtFind = $db->prepare("SELECT image_url FROM banners WHERE id = ?");
            $stmtFind->execute([$id]);
            $b = $stmtFind->fetch();
            if ($b && !empty($b['image_url'])) {
                $filePath = __DIR__ . '/../' . $b['image_url'];
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                }
            }
            $stmt = $db->prepare("DELETE FROM banners WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'Banner berhasil dihapus.');
            redirect('/admin/banners.php');
        }
    }
}

// Ambil semua banner diurutkan berdasarkan sort_order & ID
$banners = $db->query("SELECT * FROM banners ORDER BY sort_order ASC, id DESC")->fetchAll();
$side_top_banner = current(array_filter($banners, fn($b) => ($b['position'] ?? '') === 'side_top')) ?: null;
$side_bottom_banner = current(array_filter($banners, fn($b) => ($b['position'] ?? '') === 'side_bottom')) ?: null;
$tender_banner = current(array_filter($banners, fn($b) => ($b['position'] ?? '') === 'tender')) ?: null;
$carousel_banners = array_values(array_filter($banners, fn($b) => ($b['position'] ?? 'carousel') === 'carousel'));

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">Kelola Banner & Iklan Beranda</h1>
        <p class="text-muted small mb-0">Kelola gambar untuk 3 slot banner beranda: Slider Carousel Utama (Kiri), Banner Samping Kanan (Atas), dan Banner Samping Kanan (Bawah).</p>
    </div>
    <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalAddBanner" onclick="setAddBannerSlot('carousel', '', 'PROMO', '#0d9488', '', '');">
        <i class="fa-solid fa-plus me-1"></i> Tambah Banner Baru
    </button>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Ringkasan Info Slot Beranda -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 border-start border-4 border-teal" style="border-left-color: #0d9488 !important;">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Total Banner</span>
                    <h3 class="fw-bold mb-0 text-dark"><?= count($banners) ?></h3>
                </div>
                <div class="bg-teal-subtle text-teal p-3 rounded-circle" style="background: #ccfbf1; color: #0d9488;">
                    <i class="fa-solid fa-rectangle-ad fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 border-start border-4 border-success">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Banner Aktif (Tayang)</span>
                    <h3 class="fw-bold mb-0 text-success">
                        <?= count(array_filter($banners, fn($b) => $b['is_active'] == 1)) ?>
                    </h3>
                </div>
                <div class="bg-success-subtle text-success p-3 rounded-circle">
                    <i class="fa-solid fa-circle-check fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3 bg-white rounded-3 border-start border-4 border-info">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Pembagian Slot Banner</span>
                    <div class="small mt-1 text-dark fw-bold">
                        Slider Kiri: <?= count($carousel_banners) ?> &bull; 
                        Samping Atas: <?= $side_top_banner ? '1' : '0' ?> &bull; 
                        Samping Bawah: <?= $side_bottom_banner ? '1' : '0' ?> &bull;
                        Tender: <?= $tender_banner ? '1' : '0' ?>
                    </div>
                </div>
                <div class="bg-info-subtle text-info p-3 rounded-circle">
                    <i class="fa-solid fa-layer-group fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TATA LETAK VISUAL PENGATURAN SLOT BANNER BERANDA -->
<div class="card border shadow-sm mb-4 rounded-3 overflow-hidden">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-table-columns text-teal me-2"></i> Tata Letak Slot Banner Beranda & Halaman Tender
            </h6>
            <span class="small text-muted">Klik tombol <strong>"Masukkan / Ganti Gambar"</strong> di bawah untuk memasukkan banner pada slot yang diinginkan.</span>
        </div>
        <span class="badge bg-light text-dark border px-2.5 py-1.5"><i class="fa-solid fa-desktop me-1 text-teal"></i> Layout Banner Responsif</span>
    </div>
    <div class="card-body p-4 bg-light bg-opacity-50">
        <div class="row g-3">
            <!-- 1. SLOT KIRI: SLIDER CAROUSEL UTAMA -->
            <div class="col-lg-7">
                <div class="card border rounded-3 h-100 shadow-xs bg-white">
                    <div class="card-header bg-primary text-white py-2.5 px-3 d-flex justify-content-between align-items-center">
                        <span class="fw-bold small"><i class="fa-solid fa-images me-1.5"></i> Slot Kiri: Slider Carousel Utama (<?= count($carousel_banners) ?> Slide)</span>
                        <span class="badge bg-white text-primary">Rasio ~16:7 (800 x 350 px)</span>
                    </div>
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                            <p class="small text-muted mb-2">Banner besar yang berputar otomatis di sebelah kiri halaman beranda.</p>
                            <div class="d-flex gap-2 overflow-auto pb-2" style="max-height: 110px;">
                                <?php foreach ($carousel_banners as $cb): ?>
                                    <div class="border rounded-2 p-1 text-center bg-light flex-shrink-0" style="width: 130px;">
                                        <?php if (!empty($cb['image_url']) && file_exists(__DIR__ . '/../' . $cb['image_url'])): ?>
                                            <img src="<?= BASE_URL ?>/<?= e($cb['image_url']) ?>" class="rounded w-100 mb-1" style="height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded w-100 d-flex align-items-center justify-content-center text-white mb-1" style="height: 50px; background: linear-gradient(135deg, <?= e($cb['badge_color'] ?: '#0d9488') ?>, #0f172a); font-size: 0.65rem;">
                                                Mode Teks
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-truncate fw-semibold text-dark" style="font-size: 0.72rem;"><?= e($cb['title']) ?></div>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 mt-1" style="font-size: 0.65rem;" data-bs-toggle="modal" data-bs-target="#modalEditBanner<?= $cb['id'] ?>">
                                            Edit
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="pt-2 mt-2 border-top">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#modalAddBanner" onclick="setAddBannerSlot('carousel', '', 'PROMO', '#0d9488', '', '');">
                                <i class="fa-solid fa-plus me-1"></i> Tambah Banner Carousel Baru
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. SLOT KANAN: 2 MINI BANNER SAMPING -->
            <div class="col-lg-5 d-flex flex-column justify-content-between gap-3">
                <!-- SLOT ATAS: Mini Banner 1 (Siaga/Darurat) -->
                <div class="card border rounded-3 shadow-xs bg-white">
                    <div class="card-header bg-danger text-white py-2 px-3 d-flex justify-content-between align-items-center">
                        <span class="fw-bold small"><i class="fa-solid fa-bolt me-1.5"></i> Slot Kanan (Atas): Banner Siaga / Promo 1</span>
                        <span class="badge bg-white text-danger">Rasio ~2.5:1 (380 x 132 px)</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row align-items-center g-2">
                            <div class="col-5">
                                <?php if ($side_top_banner && !empty($side_top_banner['image_url']) && file_exists(__DIR__ . '/../' . $side_top_banner['image_url'])): ?>
                                    <div class="rounded overflow-hidden border shadow-xs" style="height: 68px;">
                                        <img src="<?= BASE_URL ?>/<?= e($side_top_banner['image_url']) ?>" class="w-100 h-100 object-fit-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="rounded border p-2 text-white text-center d-flex flex-column justify-content-center" style="height: 68px; background: linear-gradient(135deg, #0f172a, #b91c1c);">
                                        <span class="badge bg-danger mb-0.5 mx-auto" style="font-size: 0.6rem;">SIAGA 24 JAM</span>
                                        <span class="small fw-bold lh-1" style="font-size: 0.68rem;">Belum ada gambar</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-7">
                                <div class="fw-bold text-dark text-truncate small mb-1">
                                    <?= $side_top_banner ? e($side_top_banner['title']) : 'Butuh Tukang Cepat & Urgent?' ?>
                                </div>
                                <?php if ($side_top_banner && !empty($side_top_banner['image_url'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle mb-2" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-check me-1"></i> Gambar Terpasang
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100 py-1" style="font-size: 0.78rem;" data-bs-toggle="modal" data-bs-target="#modalEditBanner<?= $side_top_banner['id'] ?>">
                                        <i class="fa-solid fa-image me-1"></i> Ganti Gambar Banner
                                    </button>
                                <?php elseif ($side_top_banner): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle mb-2" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i> Mode Kartu Standar
                                    </span>
                                    <button type="button" class="btn btn-sm btn-danger w-100 py-1 fw-bold" style="font-size: 0.78rem;" data-bs-toggle="modal" data-bs-target="#modalEditBanner<?= $side_top_banner['id'] ?>">
                                        <i class="fa-solid fa-upload me-1"></i> Masukkan Gambar Banner
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-danger w-100 py-1 fw-bold" style="font-size: 0.78rem;" data-bs-toggle="modal" data-bs-target="#modalAddBanner" onclick="setAddBannerSlot('side_top', 'Layanan Darurat Siaga 24 Jam', 'SIAGA 24 JAM', '#ef4444', '/index.php?q=siaga#penyedia', 'Panggil Teknisi Sekarang');">
                                        <i class="fa-solid fa-upload me-1"></i> Masukkan Gambar Banner
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SLOT BAWAH: Mini Banner 2 (Mitra/Usaha) -->
                <div class="card border rounded-3 shadow-xs bg-white">
                    <div class="card-header text-white py-2 px-3 d-flex justify-content-between align-items-center" style="background-color: #0d9488;">
                        <span class="fw-bold small"><i class="fa-solid fa-handshake me-1.5"></i> Slot Kanan (Bawah): Banner Mitra / Usaha 2</span>
                        <span class="badge bg-white" style="color: #0d9488;">Rasio ~2.5:1 (380 x 132 px)</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row align-items-center g-2">
                            <div class="col-5">
                                <?php if ($side_bottom_banner && !empty($side_bottom_banner['image_url']) && file_exists(__DIR__ . '/../' . $side_bottom_banner['image_url'])): ?>
                                    <div class="rounded overflow-hidden border shadow-xs" style="height: 68px;">
                                        <img src="<?= BASE_URL ?>/<?= e($side_bottom_banner['image_url']) ?>" class="w-100 h-100 object-fit-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="rounded border p-2 text-white text-center d-flex flex-column justify-content-center" style="height: 68px; background: linear-gradient(135deg, #042f2e, #0d9488);">
                                        <span class="badge bg-white text-teal mb-0.5 mx-auto" style="font-size: 0.6rem;">BUKA USAHA JASA</span>
                                        <span class="small fw-bold lh-1" style="font-size: 0.68rem;">Belum ada gambar</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-7">
                                <div class="fw-bold text-dark text-truncate small mb-1">
                                    <?= $side_bottom_banner ? e($side_bottom_banner['title']) : 'Punya Keahlian di Inhu?' ?>
                                </div>
                                <?php if ($side_bottom_banner && !empty($side_bottom_banner['image_url'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle mb-2" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-check me-1"></i> Gambar Terpasang
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-teal w-100 py-1" style="font-size: 0.78rem; border-color: #0d9488; color: #0d9488;" data-bs-toggle="modal" data-bs-target="#modalEditBanner<?= $side_bottom_banner['id'] ?>">
                                        <i class="fa-solid fa-image me-1"></i> Ganti Gambar Banner
                                    </button>
                                <?php elseif ($side_bottom_banner): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle mb-2" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i> Mode Kartu Standar
                                    </span>
                                    <button type="button" class="btn btn-sm text-white w-100 py-1 fw-bold" style="background-color: #0d9488; font-size: 0.78rem;" data-bs-toggle="modal" data-bs-target="#modalEditBanner<?= $side_bottom_banner['id'] ?>">
                                        <i class="fa-solid fa-upload me-1"></i> Masukkan Gambar Banner
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm text-white w-100 py-1 fw-bold" style="background-color: #0d9488; font-size: 0.78rem;" data-bs-toggle="modal" data-bs-target="#modalAddBanner" onclick="setAddBannerSlot('side_bottom', 'Punya Keahlian di Inhu? Buka Usaha Jasa', 'BUKA USAHA JASA', '#0d9488', '/register.php?role=penyedia', 'Daftar Jadi Mitra Inhu');">
                                        <i class="fa-solid fa-upload me-1"></i> Masukkan Gambar Banner
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. SLOT KHUSUS: BANNER HALAMAN TENDER KILAT -->
            <div class="col-12 mt-3 pt-3 border-top">
                <div class="card border rounded-3 shadow-xs bg-white">
                    <div class="card-header text-white py-2.5 px-3 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #0d9488, #042f2e);">
                        <span class="fw-bold small"><i class="fa-solid fa-bolt text-warning me-1.5"></i> Slot Khusus: Banner Hero Halaman Tender Kilat Warga (/tender.php)</span>
                        <span class="badge bg-warning text-dark fw-bold">Rasio ~3.5:1 (1200 x 350 px)</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row align-items-center g-3">
                            <div class="col-md-5 col-lg-4">
                                <?php if ($tender_banner && !empty($tender_banner['image_url']) && file_exists(__DIR__ . '/../' . $tender_banner['image_url'])): ?>
                                    <div class="rounded overflow-hidden border shadow-xs" style="height: 100px;">
                                        <img src="<?= BASE_URL ?>/<?= e($tender_banner['image_url']) ?>" class="w-100 h-100 object-fit-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="rounded border p-3 text-white text-center d-flex flex-column justify-content-center" style="height: 100px; background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                                        <span class="badge bg-warning text-dark mb-1 mx-auto" style="font-size: 0.65rem;">TENDER KILAT</span>
                                        <span class="small fw-bold lh-1" style="font-size: 0.75rem;">Mode Gradien Bawaan</span>
                                        <span class="text-white-50 mt-1" style="font-size: 0.68rem;">(Belum ada gambar kustom)</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-7 col-lg-8">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                    <div class="fw-bold text-dark fs-6">
                                        <?= $tender_banner ? e($tender_banner['title']) : 'Pasang Kebutuhan Jasa Terbuka' ?>
                                    </div>
                                    <?php if ($tender_banner && !empty($tender_banner['image_url'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="fa-solid fa-check me-1"></i> Gambar Terpasang (<?= !empty($tender_banner['show_overlay']) ? 'Mode Teks Overlay' : 'Gambar Banner Penuh' ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                            <i class="fa-solid fa-circle-info me-1"></i> Mode Desain Standar
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted small mb-2 text-truncate" style="max-width: 650px;">
                                    <?= $tender_banner && !empty($tender_banner['subtitle']) ? e($tender_banner['subtitle']) : 'Banner pengantar di bagian paling atas halaman Tender Kilat Warga.' ?>
                                </p>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if ($tender_banner): ?>
                                        <button type="button" class="btn btn-sm btn-teal text-white fw-bold py-1 px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#modalEditBanner<?= $tender_banner['id'] ?>">
                                            <i class="fa-solid fa-upload me-1"></i> <?= !empty($tender_banner['image_url']) ? 'Ganti Banner Tender Kilat' : 'Upload Banner Tender Kilat' ?>
                                        </button>
                                        <a href="<?= BASE_URL ?>/tender.php" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2.5">
                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Lihat Halaman Tender ↗
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-teal text-white fw-bold py-1 px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#modalAddBanner" onclick="setAddBannerSlot('tender', 'Pasang Kebutuhan Jasa Terbuka', 'Tender Kilat & Siaran Warga Inhu', '#f59e0b', '/tender.php', 'Pasang Kebutuhan Sekarang');">
                                            <i class="fa-solid fa-plus me-1"></i> Buat Banner Tender Kilat
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Banner -->
<div class="card border shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list me-2 text-teal"></i> Daftar Banner yang Terdaftar</h6>
        <span class="badge text-bg-light border text-muted">Diurutkan berdasarkan Urutan Tayang</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px;">Urutan</th>
                    <th style="width: 120px;">Tampilan</th>
                    <th>Judul & Pesan</th>
                    <th style="width: 150px;">Posisi Slot</th>
                    <th>Badge / Kategori</th>
                    <th>Tujuan Link</th>
                    <th>Status Tayang</th>
                    <th class="text-end" style="width: 160px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($banners)): ?>
                    <?php foreach ($banners as $b): ?>
                        <tr>
                            <td class="text-center fw-bold text-muted">
                                <span class="badge bg-light text-dark border">#<?= (int)$b['sort_order'] ?></span>
                            </td>
                            <td>
                                <?php if (!empty($b['image_url']) && file_exists(__DIR__ . '/../' . $b['image_url'])): ?>
                                    <img src="<?= BASE_URL ?>/<?= e($b['image_url']) ?>" alt="Banner" class="rounded border shadow-xs" style="width: 100px; height: 55px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded d-flex align-items-center justify-content-center text-white p-2 text-center" style="width: 100px; height: 55px; background: linear-gradient(135deg, <?= e($b['badge_color'] ?: '#0d9488') ?>, #0f172a); font-size: 0.65rem; font-weight: bold;">
                                        <i class="fa-solid fa-bullhorn me-1"></i> Mode Card
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($b['title']) ?></div>
                                <?php if (!empty($b['subtitle'])): ?>
                                    <div class="text-muted small text-truncate" style="max-width: 320px;"><?= e($b['subtitle']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $pos = $b['position'] ?? 'carousel';
                                if ($pos === 'carousel') {
                                    echo '<span class="badge bg-primary text-white py-1 px-2" style="font-size:0.75rem;"><i class="fa-solid fa-images me-1"></i> Slider Utama</span>';
                                } elseif ($pos === 'side_top') {
                                    echo '<span class="badge bg-danger text-white py-1 px-2" style="font-size:0.75rem;"><i class="fa-solid fa-bolt me-1"></i> Samping Atas</span>';
                                } elseif ($pos === 'side_bottom') {
                                    echo '<span class="badge text-white py-1 px-2" style="background:#0d9488; font-size:0.75rem;"><i class="fa-solid fa-handshake me-1"></i> Samping Bawah</span>';
                                } elseif ($pos === 'tender') {
                                    echo '<span class="badge text-dark py-1 px-2" style="background:#f59e0b; font-size:0.75rem;"><i class="fa-solid fa-bullhorn me-1"></i> Tender Kilat</span>';
                                } else {
                                    echo '<span class="badge bg-secondary text-white py-1 px-2">' . e($pos) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge" style="background-color: <?= e($b['badge_color'] ?: '#0d9488') ?>; color: #fff; font-size: 0.75rem; letter-spacing: 0.5px;">
                                    <?= e($b['badge_text'] ?: 'PENGUMUMAN') ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($b['link_url'])): ?>
                                    <a href="<?= e($b['link_url']) ?>" target="_blank" class="small text-decoration-none fw-semibold text-primary">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> <?= e($b['button_text'] ?: 'Kunjungi Link') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">- Tidak ada link -</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="<?= BASE_URL ?>/admin/banners.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $b['is_active'] ? 0 : 1 ?>">
                                    <?php if ($b['is_active']): ?>
                                        <button type="submit" class="btn btn-sm btn-success py-1 px-2 fw-semibold" style="font-size: 0.75rem;" title="Klik untuk nonaktifkan">
                                            <i class="fa-solid fa-circle-check me-1"></i> Aktif (Tayang)
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size: 0.75rem;" title="Klik untuk aktifkan">
                                            <i class="fa-solid fa-eye-slash me-1"></i> Non-aktif
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td class="text-end">
                                <!-- Tombol Edit -->
                                <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalEditBanner<?= $b['id'] ?>"
                                    title="Edit Banner">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>

                                <!-- Tombol Hapus -->
                                <form method="POST" action="<?= BASE_URL ?>/admin/banners.php" class="d-inline"
                                      data-confirm="Apakah Anda yakin ingin menghapus banner ini secara permanen dari sistem?"
                                      data-confirm-title="Hapus Banner Promosi?"
                                      data-confirm-btn="Ya, Hapus Banner"
                                      data-confirm-type="danger">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Hapus">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal Edit Banner -->
                        <div class="modal fade" id="modalEditBanner<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                                <div class="modal-content">
                                    <form method="POST" action="<?= BASE_URL ?>/admin/banners.php" enctype="multipart/form-data">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">

                                        <div class="modal-header border-bottom">
                                            <h5 class="modal-title fw-bold text-dark">
                                                <i class="fa-solid fa-pen-to-square text-teal me-2"></i> Edit Banner #<?= $b['id'] ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body p-4">
                                            <div class="row g-3">
                                                <div class="col-md-7">
                                                    <label class="form-label fw-semibold small">Judul Banner <span class="text-danger">*</span></label>
                                                    <input type="text" name="title" class="form-control" value="<?= e($b['title']) ?>" required>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label fw-semibold small">Posisi Slot di Beranda / Halaman <span class="text-danger">*</span></label>
                                                    <select name="position" class="form-select" required>
                                                        <option value="carousel" <?= ($b['position'] ?? 'carousel') === 'carousel' ? 'selected' : '' ?>>🎠 Slider Utama (Kiri - Berputar)</option>
                                                        <option value="side_top" <?= ($b['position'] ?? '') === 'side_top' ? 'selected' : '' ?>>⚡ Samping Kanan - Atas (Siaga/Promo 1)</option>
                                                        <option value="side_bottom" <?= ($b['position'] ?? '') === 'side_bottom' ? 'selected' : '' ?>>🤝 Samping Kanan - Bawah (Mitra/Promo 2)</option>
                                                        <option value="tender" <?= ($b['position'] ?? '') === 'tender' ? 'selected' : '' ?>>📢 Banner Halaman Tender Kilat (Siaran Warga)</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label fw-semibold small">Urutan Tampil</label>
                                                    <input type="number" name="sort_order" class="form-control" value="<?= (int)$b['sort_order'] ?>">
                                                </div>
                                                <div class="col-md-9">
                                                    <label class="form-label fw-semibold small">Deskripsi Singkat / Subtitle</label>
                                                    <input type="text" name="subtitle" class="form-control" value="<?= e($b['subtitle'] ?? '') ?>" placeholder="Deskripsi ringkas banner...">
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold small">Teks Badge (Label Kecil)</label>
                                                    <input type="text" name="badge_text" class="form-control" value="<?= e($b['badge_text'] ?: 'PENGUMUMAN') ?>" placeholder="Contoh: PROMO, TIPS, INFO">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold small">Warna Badge</label>
                                                    <div class="d-flex gap-2">
                                                        <input type="color" name="badge_color" class="form-control form-control-color" value="<?= e($b['badge_color'] ?: '#0d9488') ?>" title="Pilih Warna">
                                                        <input type="text" class="form-control font-monospace" value="<?= e($b['badge_color'] ?: '#0d9488') ?>" readonly>
                                                    </div>
                                                </div>

                                                <div class="col-md-8">
                                                    <label class="form-label fw-semibold small">Link Tujuan (Klik Banner)</label>
                                                    <input type="text" name="link_url" class="form-control" value="<?= e($b['link_url'] ?? '') ?>" placeholder="Misal: /register.php atau https://wa.me/...">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fw-semibold small">Teks Tombol</label>
                                                    <input type="text" name="button_text" class="form-control" value="<?= e($b['button_text'] ?: 'Lihat Selengkapnya') ?>">
                                                </div>

                                                <div class="col-12">
                                                    <label class="form-label fw-semibold small">Ganti Gambar Banner (Opsional)</label>
                                                    <?php if (!empty($b['image_url'])): ?>
                                                        <div class="mb-2">
                                                            <span class="text-muted small">Gambar saat ini:</span><br>
                                                            <img src="<?= BASE_URL ?>/<?= e($b['image_url']) ?>" alt="Preview" class="rounded border mt-1" style="max-height: 80px;">
                                                        </div>
                                                    <?php endif; ?>
                                                    <input type="file" name="banner_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                                    <div class="form-text small mt-1">
                                                        <i class="fa-solid fa-circle-info text-teal me-1"></i> <strong>Rekomendasi Ukuran Gambar:</strong><br>
                                                        &bull; <strong>Slider Utama:</strong> Rasio ~16:7 (misal 800x350px atau 1200x525px).<br>
                                                        &bull; <strong>Samping Kanan (Atas & Bawah):</strong> Rasio ~2.5:1 (misal 380x132px atau 760x264px).<br>
                                                        &bull; <strong>Banner Tender Kilat:</strong> Rasio ~3.5:1 (misal 1200x350px atau 1000x300px).<br>
                                                        Format JPG, PNG, atau WebP (Maks 3MB).
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="p-3 bg-light rounded-3 border">
                                                        <div class="form-check form-switch mb-1">
                                                            <input class="form-check-input" type="checkbox" role="switch" name="show_overlay" id="edit_overlay_<?= $b['id'] ?>" <?= (!empty($b['show_overlay'])) ? 'checked' : '' ?>>
                                                            <label class="form-check-label fw-bold text-dark small" for="edit_overlay_<?= $b['id'] ?>">
                                                                Tampilkan Tulisan Judul, Badge & Tombol di Atas Gambar (Mode Overlay)?
                                                            </label>
                                                        </div>
                                                        <div class="form-text small text-muted">
                                                            &bull; <strong>Centang (ON):</strong> Tulisan judul, badge label, dan tombol akan dimunculkan di atas gambar.<br>
                                                            &bull; <strong>Jangan Centang (OFF):</strong> Gambar tampil bersih penuh tanpa ada teks yang menutupi (cocok untuk poster/banner yang sudah ada teks grafisnya sendiri).
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="form-check form-switch mt-2">
                                                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="edit_active_<?= $b['id'] ?>" <?= $b['is_active'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label fw-semibold small" for="edit_active_<?= $b['id'] ?>">Tayangkan banner ini di beranda</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal-footer border-top">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary-custom">
                                                <i class="fa-solid fa-save me-1"></i> Simpan Perubahan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-image-slash fa-2x mb-2 d-block text-secondary opacity-50"></i>
                            Belum ada banner yang ditambahkan. Silakan klik tombol <strong>"Tambah Banner Baru"</strong> di atas.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Banner Baru -->
<div class="modal fade" id="modalAddBanner" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/admin/banners.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="fa-solid fa-plus-circle text-teal me-2"></i> Tambah Banner & Iklan Beranda Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small">Judul Banner <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="add_title" class="form-control" placeholder="Contoh: Promo Spesial Cuci AC Musim Kemarau di Belilas" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Posisi Slot di Beranda / Halaman <span class="text-danger">*</span></label>
                            <select name="position" id="add_position" class="form-select" required>
                                <option value="carousel">🎠 Slider Utama (Kiri - Berputar)</option>
                                <option value="side_top">⚡ Samping Kanan - Atas (Siaga/Promo 1)</option>
                                <option value="side_bottom">🤝 Samping Kanan - Bawah (Mitra/Promo 2)</option>
                                <option value="tender">📢 Banner Halaman Tender Kilat (Siaran Warga)</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Urutan Tayang (Sort Order)</label>
                            <input type="number" name="sort_order" class="form-control" value="0">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-semibold small">Deskripsi Singkat / Subtitle</label>
                            <input type="text" name="subtitle" id="add_subtitle" class="form-control" placeholder="Contoh: Dapatkan diskon jasa servis AC panggilan untuk area Seberida dan sekitarnya...">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Teks Badge (Label Kategori)</label>
                            <input type="text" name="badge_text" id="add_badge_text" class="form-control" value="PROMO LOKAL" placeholder="Contoh: PROMO, PENGUMUMAN, INFO MITRA">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Warna Badge</label>
                            <div class="d-flex gap-2">
                                <input type="color" name="badge_color" id="add_badge_color" class="form-control form-control-color" value="#0d9488" title="Pilih Warna">
                                <select class="form-select small" onchange="document.getElementById('add_badge_color').value = this.value">
                                    <option value="#0d9488">Hijau Teal (Standar Jasa Inhu)</option>
                                    <option value="#2563eb">Biru Info (#2563eb)</option>
                                    <option value="#d97706">Oranye Promo (#d97706)</option>
                                    <option value="#dc2626">Merah Penting (#dc2626)</option>
                                    <option value="#7c3aed">Ungu Spesial (#7c3aed)</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold small">Link Tujuan (Klik Banner)</label>
                            <input type="text" name="link_url" id="add_link_url" class="form-control" placeholder="Contoh: https://wa.me/628123456789 atau /register.php">
                            <div class="form-text small">Bisa berupa link internal atau link WhatsApp sponsor / pengiklan.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Teks Tombol</label>
                            <input type="text" name="button_text" id="add_button_text" class="form-control" value="Hubungi / Lihat">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Upload Gambar Banner (Opsional)</label>
                            <input type="file" name="banner_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small mt-1">
                                <i class="fa-solid fa-circle-info text-teal me-1"></i> <strong>Rekomendasi Ukuran Gambar:</strong><br>
                                &bull; <strong>Slider Utama:</strong> Rasio ~16:7 (misal 800x350px atau 1200x525px).<br>
                                &bull; <strong>Samping Kanan (Atas & Bawah):</strong> Rasio ~2.5:1 (misal 380x132px atau 760x264px).<br>
                                &bull; <strong>Banner Tender Kilat:</strong> Rasio ~3.5:1 (misal 1200x350px atau 1000x300px).<br>
                                Format JPG, PNG, atau WebP (Maks 3MB). Jika dikosongkan, akan tampil sebagai kartu bergradien modern.
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" role="switch" name="show_overlay" id="add_overlay">
                                    <label class="form-check-label fw-bold text-dark small" for="add_overlay">
                                        Tampilkan Tulisan Judul, Badge & Tombol di Atas Gambar (Mode Overlay)?
                                    </label>
                                </div>
                                <div class="form-text small text-muted">
                                    &bull; <strong>Centang (ON):</strong> Tulisan judul, badge label, dan tombol akan dimunculkan di atas gambar.<br>
                                    &bull; <strong>Jangan Centang (OFF):</strong> Gambar tampil bersih penuh tanpa ada teks yang menutupi (cocok untuk poster/banner yang sudah ada teks grafisnya sendiri).
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="add_active" checked>
                                <label class="form-check-label fw-semibold small" for="add_active">Langsung tayangkan banner ini di beranda</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="fa-solid fa-plus me-1"></i> Tambahkan Banner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setAddBannerSlot(position, title, badgeText, badgeColor, linkUrl, buttonText) {
    const posElem = document.getElementById('add_position');
    const titleElem = document.getElementById('add_title');
    const badgeTextElem = document.getElementById('add_badge_text');
    const badgeColorElem = document.getElementById('add_badge_color');
    const linkUrlElem = document.getElementById('add_link_url');
    const buttonTextElem = document.getElementById('add_button_text');

    if (posElem) posElem.value = position;
    if (title && titleElem) titleElem.value = title;
    if (badgeText && badgeTextElem) badgeTextElem.value = badgeText;
    if (badgeColor && badgeColorElem) badgeColorElem.value = badgeColor;
    if (linkUrl && linkUrlElem) linkUrlElem.value = linkUrl;
    if (buttonText && buttonTextElem) buttonTextElem.value = buttonText;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

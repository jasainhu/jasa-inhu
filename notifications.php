<?php
/**
 * Pusat Notifikasi & Pemberitahuan
 * JASA INHU - Menghubungkan kebutuhan warga dengan puluhan teknisi & mitra terverifikasi
 */

$page_title = 'Pemberitahuan & Notifikasi';

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    set_flash('info', 'Silakan masuk terlebih dahulu untuk melihat notifikasi Anda.');
    redirect('/login.php?redirect=' . urlencode('/notifications.php'));
}

$user = current_user();
$db = get_db();

// Handle aksi "Tandai Semua Dibaca"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    if (validate_csrf()) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        set_flash('success', 'Semua notifikasi telah ditandai sudah dibaca.');
    }
    redirect('/notifications.php');
}

// Handle aksi "Hapus Notifikasi"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_notif') {
    if (validate_csrf()) {
        $del_id = (int)($_POST['notif_id'] ?? 0);
        if ($del_id > 0) {
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$del_id, $user['id']]);
            set_flash('success', 'Pemberitahuan berhasil dihapus.');
        }
    }
    redirect('/notifications.php');
}

// Jika ada param `id` (diklik dari dropdown), tandai notifikasi tersebut sebagai dibaca
$highlight_id = (int)($_GET['id'] ?? 0);
if ($highlight_id > 0) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$highlight_id, $user['id']]);
}

// Filter tab
$current_filter = trim($_GET['filter'] ?? 'all');
$where_clauses = ["user_id = ?"];
$params = [$user['id']];

if ($current_filter === 'unread') {
    $where_clauses[] = "is_read = 0";
} elseif ($current_filter === 'orders') {
    $where_clauses[] = "(LOWER(title) LIKE '%pesanan%' OR LOWER(title) LIKE '%order%')";
} elseif ($current_filter === 'info') {
    $where_clauses[] = "(LOWER(title) NOT LIKE '%pesanan%' AND LOWER(title) NOT LIKE '%order%')";
}

$sql = "SELECT * FROM notifications WHERE " . implode(' AND ', $where_clauses) . " ORDER BY created_at DESC LIMIT 60";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik
$unread_count = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$user['id']} AND is_read = 0")->fetchColumn();
$total_count  = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$user['id']}")->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="py-4 bg-light min-vh-100">
    <div class="container" style="max-width: 820px;">
        
        <!-- Breadcrumb & Header Halaman -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0" style="font-size: 0.8rem;">
                        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/" class="text-decoration-none text-muted">Beranda</a></li>
                        <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Pemberitahuan</li>
                    </ol>
                </nav>
                <h1 class="h4 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <span class="text-teal"><i class="fa-solid fa-bell"></i></span>
                    <span>Pusat Pemberitahuan</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-teal rounded-pill fs-6"><?= $unread_count ?> Baru</span>
                    <?php endif; ?>
                </h1>
                <p class="text-muted small mb-0">Semua kabar terbaru terkait pesanan, akun, dan aktivitas Anda.</p>
            </div>

            <!-- Tombol Aksi Tandai Semua Dibaca -->
            <?php if ($unread_count > 0): ?>
                <div>
                    <form method="POST" action="<?= BASE_URL ?>/notifications.php" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-check-double"></i>
                            <span>Tandai Semua Dibaca</span>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filter Tab Kapsul -->
        <div class="d-flex align-items-center gap-2 mb-3 overflow-auto pb-2" style="scrollbar-width: none;">
            <a href="<?= BASE_URL ?>/notifications.php" class="btn btn-sm <?= $current_filter === 'all' ? 'btn-teal text-white fw-bold shadow-sm' : 'btn-outline-secondary bg-white text-dark' ?> rounded-pill px-3 py-1" style="font-size: 0.8rem; white-space: nowrap;">
                Semua (<?= $total_count ?>)
            </a>
            <a href="<?= BASE_URL ?>/notifications.php?filter=unread" class="btn btn-sm <?= $current_filter === 'unread' ? 'btn-teal text-white fw-bold shadow-sm' : 'btn-outline-secondary bg-white text-dark' ?> rounded-pill px-3 py-1" style="font-size: 0.8rem; white-space: nowrap;">
                Belum Dibaca <?= $unread_count > 0 ? "($unread_count)" : '' ?>
            </a>
            <a href="<?= BASE_URL ?>/notifications.php?filter=orders" class="btn btn-sm <?= $current_filter === 'orders' ? 'btn-teal text-white fw-bold shadow-sm' : 'btn-outline-secondary bg-white text-dark' ?> rounded-pill px-3 py-1" style="font-size: 0.8rem; white-space: nowrap;">
                <i class="fa-solid fa-clipboard-check me-1"></i> Pesanan
            </a>
            <a href="<?= BASE_URL ?>/notifications.php?filter=info" class="btn btn-sm <?= $current_filter === 'info' ? 'btn-teal text-white fw-bold shadow-sm' : 'btn-outline-secondary bg-white text-dark' ?> rounded-pill px-3 py-1" style="font-size: 0.8rem; white-space: nowrap;">
                <i class="fa-solid fa-circle-info me-1"></i> Info & Sistem
            </a>
        </div>

        <!-- Daftar Kartu Pemberitahuan -->
        <?php if (!empty($notifications)): ?>
            <div class="d-flex flex-column gap-2.5">
                <?php foreach ($notifications as $n): 
                    $is_unread = !(bool)$n['is_read'];
                    $is_highlight = ($highlight_id === (int)$n['id']);
                    $n_title_low = strtolower($n['title']);
                    
                    // Normalisasi link tujuan
                    $target_url = '';
                    if (!empty($n['link']) && $n['link'] !== '#' && !str_starts_with($n['link'], '#')) {
                        if (str_starts_with($n['link'], 'http://') || str_starts_with($n['link'], 'https://')) {
                            $target_url = $n['link'];
                        } elseif (str_starts_with($n['link'], '/')) {
                            $target_url = BASE_URL . $n['link'];
                        } else {
                            $target_url = BASE_URL . '/' . $n['link'];
                        }
                    }
                ?>
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden position-relative <?= $is_unread ? 'border-start border-4 border-teal bg-white' : 'bg-white opacity-95' ?> <?= $is_highlight ? 'ring-highlight' : '' ?>" id="notif-<?= (int)$n['id'] ?>" style="transition: all 0.2s ease;">
                        <div class="card-body p-3 p-md-4">
                            <div class="d-flex align-items-start gap-3">
                                
                                <!-- Icon Bundar sesuai jenis pesan -->
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background: <?= $is_unread ? '#ccfbf1' : '#f1f5f9' ?>; color: <?= $is_unread ? '#0d9488' : '#64748b' ?>; font-size: 1.15rem;">
                                    <?php if (str_contains($n_title_low, 'pesanan') || str_contains($n_title_low, 'order')): ?>
                                        <i class="fa-solid fa-clipboard-check text-success"></i>
                                    <?php elseif (str_contains($n_title_low, 'selamat') || str_contains($n_title_low, 'welcome')): ?>
                                        <i class="fa-solid fa-wand-magic-sparkles text-warning"></i>
                                    <?php elseif (str_contains($n_title_low, 'chat') || str_contains($n_title_low, 'pesan') || str_contains($n_title_low, 'diskusi')): ?>
                                        <i class="fa-solid fa-comment-dots text-teal"></i>
                                    <?php elseif (str_contains($n_title_low, 'saldo') || str_contains($n_title_low, 'dompet') || str_contains($n_title_low, 'bayar')): ?>
                                        <i class="fa-solid fa-wallet text-primary"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-bell text-teal"></i>
                                    <?php endif; ?>
                                </div>

                                <!-- Konten Pesan Lengkap -->
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5 flex-wrap gap-1">
                                        <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                            <span><?= e($n['title']) ?></span>
                                            <?php if ($is_unread): ?>
                                                <span class="badge bg-teal-subtle text-teal border border-teal-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem; font-weight: 700;">Baru</span>
                                            <?php endif; ?>
                                        </h2>
                                        <span class="text-muted small d-inline-flex align-items-center gap-1" style="font-size: 0.74rem;">
                                            <i class="fa-regular fa-clock opacity-75"></i>
                                            <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
                                        </span>
                                    </div>

                                    <!-- Pesan Utuh 100% (Tidak dipotong titik-titik) -->
                                    <div class="text-secondary small lh-base mb-3" style="font-size: 0.85rem; color: #334155;">
                                        <?= nl2br(e($n['message'])) ?>
                                    </div>

                                    <!-- Tombol Aksi & Hapus -->
                                    <div class="d-flex align-items-center justify-content-between pt-2 border-top border-light">
                                        <div>
                                            <?php if (!empty($target_url)): ?>
                                                <a href="<?= e($target_url) ?>" class="btn btn-sm btn-teal text-white rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm" style="font-size: 0.78rem;">
                                                    <?php if (str_contains($n_title_low, 'pesanan') || str_contains($n_title_low, 'order')): ?>
                                                        <span>Lihat Detail Pesanan</span>
                                                    <?php elseif (str_contains($n_title_low, 'selamat')): ?>
                                                        <span>Jelajahi Jasa Sekarang</span>
                                                    <?php else: ?>
                                                        <span>Buka Halaman Terkait</span>
                                                    <?php endif; ?>
                                                    <i class="fa-solid fa-arrow-right small"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 0.72rem;">
                                                    <i class="fa-solid fa-circle-check text-success me-1"></i> Informasi Resmi Sistem
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Hapus Notifikasi -->
                                        <form method="POST" action="<?= BASE_URL ?>/notifications.php" onsubmit="return confirm('Hapus pemberitahuan ini?');" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_notif">
                                            <input type="hidden" name="notif_id" value="<?= (int)$n['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-link text-muted p-0 text-decoration-none" title="Hapus pemberitahuan" style="font-size: 0.78rem;">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Tampilan Kosong (Empty State) -->
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white my-3">
                <div class="mb-3">
                    <div class="rounded-circle bg-teal-subtle text-teal d-inline-flex align-items-center justify-content-center" style="width: 72px; height: 72px;">
                        <i class="fa-regular fa-bell-slash fs-2"></i>
                    </div>
                </div>
                <h3 class="h5 fw-bold text-dark mb-1">Belum Ada Pemberitahuan</h3>
                <p class="text-muted small mx-auto mb-4" style="max-width: 380px;">
                    <?= $current_filter === 'unread' ? 'Semua notifikasi sudah Anda baca. Bagus sekali!' : 'Kabar terkait status pesanan, verifikasi akun, dan informasi penting lainnya akan muncul di halaman ini.' ?>
                </p>
                <div>
                    <a href="<?= BASE_URL ?>/#penyedia" class="btn btn-teal text-white rounded-pill px-4 py-2 fw-semibold shadow-sm">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Cari Penyedia Jasa
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
.ring-highlight {
    animation: highlightFade 3s ease forwards;
}
@keyframes highlightFade {
    0% {
        box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.45);
        transform: scale(1.01);
    }
    100% {
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        transform: scale(1);
    }
}
</style>

<?php if ($highlight_id > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('notif-<?= $highlight_id ?>');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

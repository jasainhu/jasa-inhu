<?php
/**
 * Detail & Pengelolaan Permintaan Jasa Pengguna
 */

$page_title = 'Permintaan Jasa Saya';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('pengguna');

$user = current_user();
$db = get_db();
$selected_id = (int)($_GET['id'] ?? 0);

// Handle aksi terima penawaran atau selesaikan permintaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (validate_csrf()) {
        $action = $_POST['action'];
        $req_id = (int)$_POST['request_id'];

        if ($action === 'accept_offer') {
            $resp_id = (int)$_POST['response_id'];
            
            // Ambil data penawaran untuk tahu siapa mitra yang terpilih
            $stmtResp = $db->prepare("SELECT provider_id, offer_price FROM service_request_responses WHERE id = ? AND request_id = ? LIMIT 1");
            $stmtResp->execute([$resp_id, $req_id]);
            $respData = $stmtResp->fetch();

            if ($respData) {
                // Potong Lead Fee dari mitra pemenang tawaran
                deduct_provider_lead_fee(
                    (int)$respData['provider_id'],
                    $req_id,
                    null,
                    "Biaya Kontak / Tawaran Terpilih Pesanan #" . $req_id
                );

                $stmt = $db->prepare("UPDATE service_request_responses SET status = 'accepted' WHERE id = ? AND request_id = ?");
                $stmt->execute([$resp_id, $req_id]);

                $stmt2 = $db->prepare("UPDATE service_requests SET status = 'in_progress', progress_step = 'accepted', provider_id = ? WHERE id = ? AND user_id = ?");
                $stmt2->execute([(int)$respData['provider_id'], $req_id, $user['id']]);

                // Catat ke Timeline Garis Waktu
                add_order_timeline_event(
                    $req_id,
                    'accepted',
                    'Tawaran Mitra Diterima oleh Pelanggan',
                    'Pelanggan menyepakati tawaran harga dan menunjuk mitra pemenang.',
                    'customer'
                );

                set_flash('success', 'Tawaran diterima! Mitra penyedia jasa telah terhubung. Anda dapat mulai berkoordinasi via Chat atau WhatsApp.');
                redirect('/user/requests.php?id=' . $req_id);
            }
        } elseif ($action === 'complete_request') {
            $stmt = $db->prepare("UPDATE service_requests SET status = 'completed', progress_step = 'completed' WHERE id = ? AND user_id = ?");
            $stmt->execute([$req_id, $user['id']]);

            // Catat ke Timeline Garis Waktu
            add_order_timeline_event(
                $req_id,
                'completed',
                'Pekerjaan Dikonfirmasi Tuntas oleh Pelanggan',
                'Pelanggan telah memverifikasi pengerjaan di lokasi dan menandai selesai.',
                'customer'
            );

            set_flash('success', 'Pekerjaan telah ditandai selesai. Terima kasih telah menggunakan JASA INHU!');
            redirect('/user/requests.php?id=' . $req_id);
        } elseif ($action === 'cancel_request') {
            $stmt = $db->prepare("UPDATE service_requests SET status = 'cancelled', progress_step = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'open'");
            $stmt->execute([$req_id, $user['id']]);

            // Catat ke Timeline Garis Waktu
            add_order_timeline_event(
                $req_id,
                'cancelled',
                'Pesanan Dibatalkan oleh Pelanggan',
                'Pelanggan membatalkan permintaan jasa ini sebelum diproses lebih lanjut.',
                'customer'
            );

            set_flash('info', 'Pesanan berhasil dibatalkan.');
            redirect('/user/requests.php?id=' . $req_id);
        }
    }
}

// Ambil semua permintaan milik user (baik lelang terbuka maupun pesanan langsung)
$stmtAll = $db->prepare("
    SELECT sr.*, sc.name as category_name, sc.icon as category_icon, d.name as district_name,
           target_sp.business_name as target_business_name, target_u.phone as target_provider_phone,
           target_sp.user_id as target_provider_user_id,
           (SELECT COUNT(*) FROM service_request_responses srr WHERE srr.request_id = sr.id) as total_offers
    FROM service_requests sr
    JOIN service_categories sc ON sr.category_id = sc.id
    JOIN districts d ON sr.district_id = d.id
    LEFT JOIN service_providers target_sp ON sr.provider_id = target_sp.id
    LEFT JOIN users target_u ON target_sp.user_id = target_u.id
    WHERE sr.user_id = ?
    ORDER BY sr.created_at DESC
");
$stmtAll->execute([$user['id']]);
$requests = $stmtAll->fetchAll();

// Deteksi apakah sedang membuka rincian pesanan di HP
$is_mobile_detail = !empty($_GET['id']);
$filter_status = trim($_GET['status'] ?? 'all');

// Filter daftar berdasarkan tab status
$filtered_requests = $requests;
if ($filter_status !== 'all' && in_array($filter_status, ['open', 'in_progress', 'completed'])) {
    $filtered_requests = array_values(array_filter($requests, fn($r) => $r['status'] === $filter_status));
}

$count_all = count($requests);
$count_open = count(array_filter($requests, fn($r) => $r['status'] === 'open'));
$count_in_progress = count(array_filter($requests, fn($r) => $r['status'] === 'in_progress'));
$count_completed = count(array_filter($requests, fn($r) => $r['status'] === 'completed'));

// Jika belum memilih request, pilih yang pertama jika ada
if ($selected_id === 0 && !empty($filtered_requests)) {
    $selected_id = (int)$filtered_requests[0]['id'];
} elseif ($selected_id === 0 && !empty($requests)) {
    $selected_id = (int)$requests[0]['id'];
}

// Ambil detail request yang dipilih beserta tawaran masuk
$current_req = null;
$offers = [];

if ($selected_id > 0) {
    foreach ($requests as $r) {
        if ((int)$r['id'] === $selected_id) {
            $current_req = $r;
            break;
        }
    }

    if ($current_req) {
        $stmtOffers = $db->prepare("
            SELECT srr.*, sp.business_name, sp.rating_avg, sp.completed_jobs, sp.is_verified,
                   u.name as provider_name, u.phone as provider_phone
            FROM service_request_responses srr
            JOIN service_providers sp ON srr.provider_id = sp.id
            JOIN users u ON sp.user_id = u.id
            WHERE srr.request_id = ?
            ORDER BY srr.created_at DESC
        ");
        $stmtOffers->execute([$selected_id]);
        $offers = $stmtOffers->fetchAll();

        // Ambil ulasan jika pesanan sudah selesai
        $existing_review = null;
        $stmtRev = $db->prepare("SELECT * FROM reviews WHERE request_id = ? LIMIT 1");
        $stmtRev->execute([$selected_id]);
        $existing_review = $stmtRev->fetch();

        // Tentukan nama mitra penyedia jasa
        $resolved_provider_name = $current_req['target_business_name'] ?? '';
        if (empty($resolved_provider_name) && !empty($offers)) {
            foreach ($offers as $off) {
                if ($off['status'] === 'accepted') {
                    $resolved_provider_name = $off['business_name'];
                    break;
                }
            }
        }

        // Ambil riwayat timeline kejadian pesanan (Order Tracker)
        $timeline_events = get_order_timeline($selected_id);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-3 py-md-4 mb-5">
    <!-- Header Ramping & Bersih (Tidak Boros Tempat di HP) -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <?php if ($is_mobile_detail): ?>
                <a href="<?= BASE_URL ?>/user/requests.php<?= $filter_status !== 'all' ? '?status=' . $filter_status : '' ?>" class="btn btn-sm btn-light border d-lg-none py-1.5 px-3 rounded-pill text-dark shadow-xs" title="Kembali ke Semua Pesanan">
                    <i class="fa-solid fa-arrow-left me-1"></i> <span class="fw-semibold small">Daftar</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/" class="btn btn-sm btn-light border d-lg-none py-1.5 px-3 rounded-pill text-dark shadow-xs" title="Kembali ke Beranda">
                    <i class="fa-solid fa-arrow-left me-1"></i> <span class="fw-semibold small">Beranda</span>
                </a>
            <?php endif; ?>
            <div>
                <h4 class="fw-bold mb-0 text-dark fs-5 fs-md-4 d-flex align-items-center gap-2">
                    <span><?= $is_mobile_detail ? 'Rincian Pesanan #' . $selected_id : 'Pesanan Jasa Saya' ?></span>
                    <span class="badge bg-teal-subtle text-teal rounded-pill" style="font-size: 0.72rem;"><?= $count_all ?></span>
                </h4>
                <p class="text-muted small mb-0 d-none d-md-block">Kelola dan pantau respon dari mitra penyedia jasa di Indragiri Hulu</p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/" class="btn btn-outline-secondary btn-sm py-1.5 px-3 rounded-pill d-none d-lg-inline-flex align-items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda
        </a>
    </div>

    <!-- Tab Filter Status Ala Shopee / Tokopedia (Tampil di HP & Desktop) -->
    <?php if (!$is_mobile_detail): ?>
        <div class="status-tabs-nav mb-3 d-flex gap-1.5 overflow-x-auto pb-1" style="white-space: nowrap;">
            <a href="<?= BASE_URL ?>/user/requests.php" class="status-tab-pill <?= $filter_status === 'all' ? 'active' : '' ?>">
                Semua (<?= $count_all ?>)
            </a>
            <a href="<?= BASE_URL ?>/user/requests.php?status=open" class="status-tab-pill <?= $filter_status === 'open' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock me-1 text-warning"></i> Menunggu (<?= $count_open ?>)
            </a>
            <a href="<?= BASE_URL ?>/user/requests.php?status=in_progress" class="status-tab-pill <?= $filter_status === 'in_progress' ? 'active' : '' ?>">
                <i class="fa-solid fa-wrench me-1 text-teal"></i> Dikerjakan (<?= $count_in_progress ?>)
            </a>
            <a href="<?= BASE_URL ?>/user/requests.php?status=completed" class="status-tab-pill <?= $filter_status === 'completed' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-check me-1 text-success"></i> Selesai (<?= $count_completed ?>)
            </a>
        </div>
    <?php endif; ?>

    <div class="row g-3 g-lg-4">
        <!-- Kolom Kiri: Daftar Permintaan (Di HP hanya tampil jika bukan mode rincian) -->
        <div class="col-lg-4 <?= $is_mobile_detail ? 'd-none d-lg-block' : 'd-block' ?>">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-header bg-white py-2.5 px-3 fw-bold small text-muted border-bottom d-flex align-items-center justify-content-between">
                    <span>DAFTAR PESANAN (<?= count($filtered_requests) ?>)</span>
                    <?php if ($filter_status !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/user/requests.php" class="text-danger small fw-semibold text-decoration-none" style="font-size: 0.72rem;">
                            Reset Filter
                        </a>
                    <?php endif; ?>
                </div>
                <div class="user-order-list-box small" style="max-height: 640px; overflow-y: auto;">
                    <?php if (!empty($filtered_requests)): ?>
                        <?php foreach ($filtered_requests as $r): ?>
                            <?php $isAct = ($selected_id === (int)$r['id']); ?>
                            <a href="<?= BASE_URL ?>/user/requests.php?id=<?= $r['id'] ?><?= $filter_status !== 'all' ? '&status=' . $filter_status : '' ?>" class="user-order-item p-3 border-bottom text-decoration-none d-block transition <?= $isAct ? 'active-teal-item' : 'bg-white hover-bg-light' ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1.5">
                                    <span class="badge bg-light text-teal border" style="font-size: 0.68rem;">
                                        <i class="fa-solid <?= e($r['category_icon'] ?: 'fa-wrench') ?> me-1"></i> <?= e($r['category_name']) ?>
                                    </span>
                                    <div class="d-flex align-items-center gap-1">
                                        <?php if (!empty($r['provider_id'])): ?>
                                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle" style="font-size: 0.62rem;">Langsung</span>
                                        <?php endif; ?>
                                        <?php if ($r['status'] === 'open'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.65rem;">
                                                <i class="fa-solid fa-clock me-0.5"></i> MENUNGGU
                                            </span>
                                        <?php elseif ($r['status'] === 'in_progress'): ?>
                                            <span class="badge bg-teal-subtle text-teal border border-teal-subtle" style="font-size: 0.65rem;">
                                                <i class="fa-solid fa-wrench me-0.5"></i> DIKERJAKAN
                                            </span>
                                        <?php elseif ($r['status'] === 'completed'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">
                                                <i class="fa-solid fa-circle-check me-0.5"></i> SELESAI
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">
                                                <?= strtoupper(e($r['status'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="fw-bold mb-1 text-dark" style="font-size: 0.88rem; line-height: 1.35;">
                                    <?= e($r['title']) ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center text-muted mt-2" style="font-size: 0.74rem;">
                                    <span><i class="fa-solid fa-location-dot text-danger me-1"></i> Kec. <?= e($r['district_name']) ?></span>
                                    <?php if (!empty($r['provider_id'])): ?>
                                        <span class="text-teal fw-semibold"><i class="fa-solid fa-store me-1"></i> <?= e($r['target_business_name']) ?></span>
                                    <?php else: ?>
                                        <span class="fw-semibold text-secondary"><?= $r['total_offers'] ?> Tawaran</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fa-solid fa-inbox fs-3 mb-2 opacity-40"></i>
                            <div class="small fw-semibold">Tidak ada pesanan di kategori ini</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Detail Permintaan & Respon (Di HP hanya tampil jika mode rincian) -->
        <div class="col-lg-8 <?= !$is_mobile_detail ? 'd-none d-lg-block' : 'd-block' ?>">
            <?php if ($current_req): ?>

                <?php
                $current_step_num = 1;
                $step_key = $current_req['progress_step'] ?? 'created';
                $status_val = $current_req['status'];

                if ($status_val === 'cancelled') {
                    $current_step_num = -1;
                } elseif ($status_val === 'completed') {
                    $current_step_num = 5;
                } elseif ($step_key === 'working') {
                    $current_step_num = 4;
                } elseif ($step_key === 'on_the_way') {
                    $current_step_num = 3;
                } elseif ($step_key === 'accepted' || $status_val === 'in_progress' || !empty($current_req['provider_id'])) {
                    $current_step_num = ($status_val === 'in_progress') ? 3 : 2;
                }
                ?>

                <!-- 1. Interactive Visual Order Stepper (5-Tahap Live Tracker) -->
                <div class="card border shadow-sm mb-3 rounded-4 p-3 bg-white">
                    <div class="d-flex align-items-center justify-content-between position-relative stepper-bar px-1 px-sm-2">
                        <!-- Step 1: Pesanan Terkirim -->
                        <div class="stepper-step text-center <?= $current_step_num >= 1 ? 'active' : '' ?> <?= $current_step_num > 1 ? 'completed' : '' ?>">
                            <div class="stepper-icon mx-auto mb-1">
                                <i class="fa-solid fa-paper-plane"></i>
                            </div>
                            <div class="stepper-label fw-bold">Dibuat</div>
                            <div class="stepper-time text-muted small" style="font-size: 0.65rem;"><?= date('d M, H:i', strtotime($current_req['created_at'])) ?></div>
                        </div>

                        <div class="stepper-line <?= $current_step_num >= 2 ? 'active' : '' ?>"></div>

                        <!-- Step 2: Dikonfirmasi Mitra -->
                        <div class="stepper-step text-center <?= $current_step_num >= 2 ? 'active' : '' ?> <?= $current_step_num > 2 ? 'completed' : ($current_step_num === 2 ? 'current' : '') ?>">
                            <div class="stepper-icon mx-auto mb-1">
                                <i class="fa-solid fa-handshake"></i>
                            </div>
                            <div class="stepper-label fw-bold">Mitra Terhubung</div>
                            <div class="stepper-time text-muted small text-truncate px-1" style="font-size: 0.65rem; max-width: 100px;">
                                <?= !empty($resolved_provider_name) ? e($resolved_provider_name) : (count($offers) . ' Tawaran') ?>
                            </div>
                        </div>

                        <div class="stepper-line <?= $current_step_num >= 3 ? 'active' : '' ?>"></div>

                        <!-- Step 3: Menuju Lokasi -->
                        <div class="stepper-step text-center <?= $current_step_num >= 3 ? 'active' : '' ?> <?= $current_step_num > 3 ? 'completed' : ($current_step_num === 3 ? 'current' : '') ?>">
                            <div class="stepper-icon mx-auto mb-1">
                                <i class="fa-solid fa-motorcycle"></i>
                            </div>
                            <div class="stepper-label fw-bold">Menuju Lokasi</div>
                            <div class="stepper-time text-muted small" style="font-size: 0.65rem;">
                                <?= $current_step_num === 3 ? 'Dalam Perjalanan' : ($current_step_num > 3 ? 'Tiba di Lokasi' : 'Menunggu') ?>
                            </div>
                        </div>

                        <div class="stepper-line <?= $current_step_num >= 4 ? 'active' : '' ?>"></div>

                        <!-- Step 4: Pengerjaan Jasa -->
                        <div class="stepper-step text-center <?= $current_step_num >= 4 ? 'active' : '' ?> <?= $current_step_num > 4 ? 'completed' : ($current_step_num === 4 ? 'current' : '') ?>">
                            <div class="stepper-icon mx-auto mb-1">
                                <i class="fa-solid fa-wrench"></i>
                            </div>
                            <div class="stepper-label fw-bold">Pengerjaan</div>
                            <div class="stepper-time text-muted small" style="font-size: 0.65rem;">
                                <?= $current_step_num === 4 ? 'Sedang Servis' : ($current_step_num > 4 ? 'Pekerjaan Beres' : 'Menunggu') ?>
                            </div>
                        </div>

                        <div class="stepper-line <?= $current_step_num >= 5 ? 'active' : '' ?>"></div>

                        <!-- Step 5: Selesai & Kwitansi -->
                        <div class="stepper-step text-center <?= $current_step_num >= 5 ? 'active completed' : '' ?>">
                            <div class="stepper-icon mx-auto mb-1">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="stepper-label fw-bold">Selesai</div>
                            <div class="stepper-time text-muted small" style="font-size: 0.65rem;">
                                <?= !empty($existing_review) ? ('⭐ ' . number_format((float)$existing_review['rating'], 1)) : ($current_step_num === 5 ? 'Kwitansi Siap' : '-') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Progress Banner -->
                <?php if ($current_req['status'] === 'open'): ?>
                    <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between p-3 rounded-4 mb-3 shadow-xs">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                <i class="fa-solid fa-clock-rotate-left fs-5"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark">Menunggu Respon Mitra</strong>
                                <span class="small text-muted">Pesanan Anda telah masuk dan sedang menunggu persetujuan dari mitra.</span>
                            </div>
                        </div>
                        <form method="POST" action="<?= BASE_URL ?>/user/requests.php" 
                              class="d-inline ms-2 flex-shrink-0"
                              data-confirm="Apakah Anda yakin ingin membatalkan pesanan jasa ini? Pesanan yang dibatalkan tidak dapat diproses lagi oleh mitra."
                              data-confirm-title="Batalkan Pesanan Jasa?"
                              data-confirm-btn="Ya, Batalkan Pesanan"
                              data-confirm-cancel="Kembali"
                              data-confirm-type="danger">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel_request">
                            <input type="hidden" name="request_id" value="<?= $current_req['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold shadow-xs">
                                <i class="fa-solid fa-xmark me-1"></i> Batalkan
                            </button>
                        </form>
                    </div>
                <?php elseif ($current_req['status'] === 'in_progress' && $step_key === 'on_the_way'): ?>
                    <div class="alert alert-primary border-primary d-flex align-items-center p-3 rounded-4 mb-3 shadow-xs">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-motorcycle fs-5"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark">🛵 Mitra Sedang Dalam Perjalanan!</strong>
                            <span class="small text-muted">Mitra sedang meluncur menuju lokasi Anda di Kec. <?= e($current_req['district_name']) ?>. Mohon pastikan telepon Anda aktif.</span>
                        </div>
                    </div>
                <?php elseif ($current_req['status'] === 'in_progress' && $step_key === 'working'): ?>
                    <div class="alert alert-info border-info d-flex align-items-center p-3 rounded-4 mb-3 shadow-xs">
                        <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-wrench fs-5"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark">🔧 Pengerjaan Sedang Berlangsung</strong>
                            <span class="small text-muted">Mitra telah tiba di lokasi dan sedang melaksanakan pengerjaan servis/jasa Anda.</span>
                        </div>
                    </div>
                <?php elseif ($current_req['status'] === 'completed'): ?>
                    <div class="alert alert-success border-success d-flex align-items-center justify-content-between p-3 rounded-4 mb-3 shadow-xs flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                <i class="fa-solid fa-circle-check fs-5"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark">🎉 Pekerjaan Telah Selesai Tuntas!</strong>
                                <span class="small text-muted">Kwitansi resmi elektronik telah diterbitkan untuk transaksi ini.</span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= BASE_URL ?>/invoice.php?id=<?= $current_req['id'] ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-semibold">
                                <i class="fa-solid fa-receipt me-1"></i> Kwitansi Resmi ↗
                            </a>
                            <?php if (empty($existing_review)): ?>
                                <button type="button" class="btn btn-teal text-white btn-sm rounded-pill px-3 fw-bold shadow-xs" onclick="openReviewModal(<?= $current_req['id'] ?>, '<?= e(addslashes($resolved_provider_name ?: 'Mitra Jasa')) ?>')">
                                    <i class="fa-solid fa-star text-warning me-1"></i> Beri Ulasan
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($current_req['status'] === 'cancelled'): ?>
                    <div class="alert alert-secondary border-secondary d-flex align-items-center p-3 rounded-4 mb-3 shadow-xs">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width: 38px; height: 38px;">
                            <i class="fa-solid fa-ban fs-5"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark">Pesanan Dibatalkan</strong>
                            <span class="small text-muted">Pesanan ini telah dibatalkan. Anda dapat membuat permintaan jasa baru kapan saja.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 2. Kartu Rincian Pesanan -->
                <div class="card border shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    <span class="badge text-bg-light border text-primary">
                                        <i class="fa-solid <?= e($current_req['category_icon']) ?> me-1"></i> <?= e($current_req['category_name']) ?>
                                    </span>
                                    <?php if (!empty($current_req['provider_id'])): ?>
                                        <span class="badge text-bg-warning text-dark">
                                            <i class="fa-solid fa-handshake-angle me-1"></i> Pesanan Langsung: <?= e($current_req['target_business_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h4 class="fw-bold text-dark mb-1"><?= e($current_req['title']) ?></h4>
                                <div class="text-muted small">
                                    <i class="fa-solid fa-location-dot text-danger me-1"></i> Kec. <?= e($current_req['district_name']) ?> &bull; Dibuat pada <?= format_date($current_req['created_at'], true) ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($current_req['status'] === 'open'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-1.5 px-2.5">
                                        <i class="fa-solid fa-clock me-1"></i> MENUNGGU MITRA
                                    </span>
                                <?php elseif ($current_req['status'] === 'in_progress'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-1.5 px-2.5">
                                        <i class="fa-solid fa-spinner fa-spin me-1"></i> SEDANG DIKERJAKAN
                                    </span>
                                <?php elseif ($current_req['status'] === 'completed'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle py-1.5 px-2.5">
                                        <i class="fa-solid fa-circle-check me-1"></i> SELESAI
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary py-1.5 px-2.5">
                                        STATUS: <?= strtoupper(e($current_req['status'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-3 small">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <span class="text-muted">Estimasi Biaya / Anggaran:</span>
                                    <div class="fw-bold text-teal fs-6"><?= $current_req['budget'] > 0 ? format_rupiah($current_req['budget']) : 'Sesuai Kesepakatan' ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted">Urgensi Waktu:</span>
                                    <div class="fw-bold text-dark text-capitalize"><?= e($current_req['urgency'] ?: 'Normal') ?></div>
                                </div>
                                <?php if (!empty($current_req['address_detail'])): ?>
                                    <div class="col-12 mt-2 pt-2 border-top">
                                        <span class="text-muted">Alamat & Patokan di Inhu:</span>
                                        <div class="text-dark fw-semibold"><?= e($current_req['address_detail']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark mb-2">Deskripsi Masalah / Kebutuhan Jasa:</h6>
                        <p class="text-secondary small mb-3 p-3 bg-light rounded-3 border" style="white-space: pre-line; line-height: 1.55;"><?= e($current_req['description']) ?></p>

                        <?php if (!empty($current_req['provider_id'])): ?>
                            <div class="p-3 mb-3 rounded-3 border bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <div class="fw-bold text-dark"><i class="fa-solid fa-store text-teal me-1"></i> <?= e($current_req['target_business_name']) ?></div>
                                    <div class="small text-muted">Pesanan ini khusus Anda percayakan kepada mitra ini.</div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="<?= BASE_URL ?>/chat.php?provider_id=<?= $current_req['provider_id'] ?>" class="btn btn-sm btn-teal text-white fw-bold py-1.5 px-3">
                                        <i class="fa-solid fa-comments me-1"></i> Buka Obrolan
                                    </a>
                                    <?php if (!empty($current_req['target_provider_phone'])): 
                                        $custWaMsg = "Halo " . ($current_req['target_business_name'] ?: 'Mitra Jasa') . ", saya pemesan jasa \"" . $current_req['title'] . "\" (No. Pesanan #" . $current_req['id'] . ") di JASA INHU. Mohon konfirmasi jadwal & info perkembangannya.";
                                    ?>
                                        <a href="<?= e(format_wa_url($current_req['target_provider_phone'], $custWaMsg)) ?>" target="_blank" class="btn btn-sm btn-success fw-bold py-1.5 px-3">
                                            <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Action Sesuai Status: In Progress -->
                        <?php if ($current_req['status'] === 'in_progress'): ?>
                            <div class="p-3 rounded-3 bg-light border d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold text-dark small"><i class="fa-solid fa-wrench text-warning me-1"></i> Pekerjaan Sedang Berlangsung</div>
                                    <div class="text-muted small">Jika teknisi telah tuntas mengerjakan perbaikan di lokasi, konfirmasi dan berikan nilai bintang Anda.</div>
                                </div>
                                <button type="button" class="btn btn-success fw-bold px-3 py-2 shadow-xs" onclick="openReviewModal(<?= $current_req['id'] ?>, '<?= e(addslashes($resolved_provider_name ?: 'Mitra Jasa')) ?>')">
                                    <i class="fa-solid fa-circle-check me-1"></i> Tandai Selesai & Beri Bintang
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Action Sesuai Status: Completed -->
                        <?php if ($current_req['status'] === 'completed'): ?>
                            <?php if ($existing_review): ?>
                                <div class="p-3 bg-light rounded-3 border">
                                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="text-warning fs-5">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa-<?= $i <= (int)$existing_review['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="fw-bold text-dark fs-6"><?= number_format((float)$existing_review['rating'], 1) ?> / 5.0</span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-2" style="font-size: 0.68rem;">
                                                <i class="fa-solid fa-check me-1"></i> Ulasan Terverifikasi
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <a href="<?= BASE_URL ?>/invoice.php?id=<?= $current_req['id'] ?>" target="_blank" class="btn btn-sm btn-outline-teal py-1 px-2.5 rounded-3 fw-semibold">
                                                <i class="fa-solid fa-receipt me-1"></i> Kwitansi Resmi ↗
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2.5 rounded-3 fw-semibold" onclick="openReviewModal(<?= $current_req['id'] ?>, '<?= e(addslashes($resolved_provider_name ?: 'Mitra Jasa')) ?>', <?= (int)$existing_review['rating'] ?>, '<?= e(addslashes($existing_review['comment'])) ?>', '<?= e(addslashes($existing_review['tags'] ?? '')) ?>', '<?= e(addslashes($existing_review['photo_url'] ?? '')) ?>')">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Ulasan
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Tag Kesan Layanan -->
                                    <?php if (!empty($existing_review['tags'])): 
                                        $revTags = array_filter(array_map('trim', explode(',', $existing_review['tags'])));
                                    ?>
                                        <div class="d-flex flex-wrap gap-1.5 my-2">
                                            <?php foreach ($revTags as $tag): ?>
                                                <span class="badge bg-teal-subtle text-teal border border-teal-subtle py-1 px-2.5 rounded-pill" style="font-size: 0.72rem;">
                                                    <i class="fa-solid fa-tag me-1"></i> <?= e($tag) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Foto Bukti Hasil Kerja -->
                                    <?php if (!empty($existing_review['photo_url'])): ?>
                                        <div class="my-2 p-2 bg-white rounded-3 border">
                                            <span class="text-muted small d-block mb-1.5 fw-semibold" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-camera text-teal me-1"></i> Foto Bukti Hasil Pengerjaan:
                                            </span>
                                            <a href="<?= BASE_URL ?>/<?= e($existing_review['photo_url']) ?>" target="_blank" class="d-inline-block position-relative border rounded-3 overflow-hidden shadow-xs">
                                                <img src="<?= BASE_URL ?>/<?= e($existing_review['photo_url']) ?>" alt="Bukti Hasil Kerja" class="img-fluid rounded-3" style="object-fit: cover; width: 140px; height: 95px;">
                                                <div class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-70 text-white text-center py-0.5" style="font-size: 0.65rem;">
                                                    <i class="fa-solid fa-magnifying-glass-plus me-1"></i> Perbesar
                                                </div>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($existing_review['comment'])): ?>
                                        <p class="text-dark small mb-1 fst-italic bg-white p-2.5 rounded border border-light">"<?= e($existing_review['comment']) ?>"</p>
                                    <?php endif; ?>

                                    <!-- Tanggapan Resmi Mitra -->
                                    <?php if (!empty($existing_review['reply_text'])): ?>
                                        <div class="mt-2.5 p-2.5 bg-teal-subtle bg-opacity-30 rounded-3 border-start border-4 border-teal">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="fw-bold text-teal small" style="font-size: 0.78rem;">
                                                    <i class="fa-solid fa-reply me-1"></i> Tanggapan Resmi Mitra (<?= e($resolved_provider_name ?: 'Penyedia Jasa') ?>)
                                                </span>
                                                <?php if (!empty($existing_review['replied_at'])): ?>
                                                    <span class="text-muted" style="font-size: 0.68rem;"><?= format_date($existing_review['replied_at'], true) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="small text-dark mb-0 fst-italic">"<?= e($existing_review['reply_text']) ?>"</p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-muted small mt-2" style="font-size: 0.72rem;">
                                        <i class="fa-regular fa-clock me-1"></i> Diulas pada <?= format_date($existing_review['created_at'], true) ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning border-0 p-3 rounded-3 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="rounded-circle bg-warning bg-opacity-20 text-warning d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 1.25rem;">
                                            <i class="fa-solid fa-star"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0.5">Pekerjaan Jasa Telah Selesai!</div>
                                            <div class="text-muted small">Bantu warga Indragiri Hulu lainnya dengan memberikan rating bintang & ulasan untuk teknisi ini.</div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="<?= BASE_URL ?>/invoice.php?id=<?= $current_req['id'] ?>" target="_blank" class="btn btn-outline-teal btn-sm fw-semibold px-3 py-2">
                                            <i class="fa-solid fa-receipt me-1"></i> Kwitansi ↗
                                        </a>
                                        <button type="button" class="btn btn-warning fw-bold px-3 py-2 text-dark shadow-xs" onclick="openReviewModal(<?= $current_req['id'] ?>, '<?= e(addslashes($resolved_provider_name ?: 'Mitra Jasa')) ?>')">
                                            <i class="fa-solid fa-star me-1"></i> Beri Bintang & Ulasan
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Kartu Riwayat Garis Waktu Pengerjaan (Activity Feed) -->
                <div class="card border shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-timeline text-teal fs-5"></i>
                            <h6 class="fw-bold mb-0 text-dark">Garis Waktu & Riwayat Aktivitas Pesanan</h6>
                        </div>
                        <span class="badge bg-light text-muted border px-2.5 py-1" style="font-size: 0.72rem;">#<?= $current_req['id'] ?></span>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($timeline_events)): ?>
                            <div class="order-activity-timeline">
                                <?php foreach ($timeline_events as $evt): ?>
                                    <div class="order-timeline-item">
                                        <div class="order-timeline-dot dot-<?= e($evt['status_key']) ?>">
                                            <?php if ($evt['status_key'] === 'created'): ?>
                                                <i class="fa-solid fa-paper-plane"></i>
                                            <?php elseif ($evt['status_key'] === 'accepted'): ?>
                                                <i class="fa-solid fa-handshake"></i>
                                            <?php elseif ($evt['status_key'] === 'on_the_way'): ?>
                                                <i class="fa-solid fa-motorcycle"></i>
                                            <?php elseif ($evt['status_key'] === 'working'): ?>
                                                <i class="fa-solid fa-wrench"></i>
                                            <?php elseif ($evt['status_key'] === 'completed'): ?>
                                                <i class="fa-solid fa-check"></i>
                                            <?php elseif ($evt['status_key'] === 'cancelled'): ?>
                                                <i class="fa-solid fa-xmark"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="order-timeline-content">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-bold text-dark small" style="font-size: 0.85rem;"><?= e($evt['title']) ?></span>
                                                <span class="text-muted small" style="font-size: 0.72rem;">
                                                    <i class="fa-regular fa-clock me-1"></i><?= date('d M Y, H:i', strtotime($evt['created_at'])) ?> WIB
                                                </span>
                                            </div>
                                            <?php if (!empty($evt['note'])): ?>
                                                <p class="text-muted small mb-0" style="font-size: 0.8rem; line-height: 1.4;"><?= nl2br(e($evt['note'])) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3 text-muted small">Belum ada riwayat aktivitas untuk pesanan ini.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tawaran dari Mitra Jasa -->
                <div class="card border shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-comments-dollar text-success me-2"></i> Tawaran Masuk dari Penyedia Jasa (<?= count($offers) ?>)
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($offers)): ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($offers as $off): ?>
                                    <div class="p-3 rounded-3 border bg-white shadow-sm">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark">
                                                    <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $off['provider_id'] ?>" target="_blank" class="text-decoration-none text-dark hover-teal">
                                                        <?= e($off['business_name']) ?> <i class="fa-solid fa-arrow-up-right-from-square small text-muted ms-1" style="font-size: 0.7rem;"></i>
                                                    </a>
                                                    <?php if ($off['is_verified']): ?>
                                                        <span class="badge-verified ms-1"><i class="fa-solid fa-check"></i> Terverifikasi</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <div class="text-muted small" style="font-size: 0.75rem;">
                                                    Mekanik/Teknisi: <?= e($off['provider_name']) ?> &bull; <i class="fa-solid fa-star text-warning"></i> <?= $off['rating_avg'] ?> &bull;
                                                    <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $off['provider_id'] ?>#section-testimoni" target="_blank" class="text-teal text-decoration-none fw-semibold">
                                                        Lihat Testimoni &rarr;
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold fs-5 text-teal"><?= format_rupiah($off['offer_price']) ?></div>
                                                <span class="badge <?= $off['status'] === 'accepted' ? 'text-bg-success' : 'text-bg-light border text-muted' ?>" style="font-size: 0.72rem;">
                                                    <?= strtoupper(e($off['status'])) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <p class="small text-secondary mb-3 bg-light p-2 rounded">
                                            "<?= e($off['message']) ?>"
                                        </p>

                                        <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 border-top gap-2">
                                            <span class="small text-muted">
                                                <i class="fa-solid fa-stopwatch me-1"></i> Estimasi Pengerjaan: <?= e($off['estimated_duration'] ?: '1 Hari') ?>
                                            </span>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="<?= BASE_URL ?>/chat.php?provider_id=<?= $off['provider_id'] ?>" class="btn btn-sm btn-outline-teal py-1 px-3 fw-semibold">
                                                    <i class="fa-solid fa-comments me-1"></i> Chat Mitra
                                                </a>
                                                <?php if ($current_req['status'] === 'open' && $off['status'] !== 'accepted'): ?>
                                                    <form method="POST" action="<?= BASE_URL ?>/user/requests.php" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="accept_offer">
                                                        <input type="hidden" name="request_id" value="<?= $current_req['id'] ?>">
                                                        <input type="hidden" name="response_id" value="<?= $off['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary-custom py-1 px-3">
                                                            Terima Tawaran
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fa-solid fa-clock-rotate-left fs-2 text-muted mb-2 d-block"></i>
                                Belum ada penyedia jasa yang merespons permintaan ini.<br>
                                <span class="small">Penyedia jasa di sekitar Indragiri Hulu akan segera melihat permintaan Anda.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border shadow-sm p-5 text-center text-muted">
                    <p class="mb-0">Pilih salah satu permintaan di panel kiri untuk melihat rincian dan tawaran yang masuk.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Beri Penilaian & Ulasan Bintang (Tokopedia Review Modal) -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-white text-warning d-flex align-items-center justify-content-center shadow-xs" style="width: 38px; height: 38px; font-size: 1.2rem;">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="reviewModalLabel">Beri Ulasan & Bintang</h5>
                        <span class="text-white-50 small" style="font-size: 0.78rem;">Ulasan Anda membantu kualitas layanan di Inhu</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="p-2.5 rounded-3 bg-light border mb-3 text-center">
                    <span class="text-muted small">Mitra Jasa yang Dinilai:</span>
                    <h6 class="fw-bold text-dark mb-0 fs-6" id="reviewModalProviderName">Nama Mitra</h6>
                </div>

                <!-- Alert Error Asinkron -->
                <div id="reviewAlert" class="alert alert-danger d-none py-2 px-3 small align-items-center mb-3 rounded-3" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <span id="reviewAlertText"></span>
                </div>

                <form id="reviewForm" onsubmit="event.preventDefault(); submitReview();">
                    <input type="hidden" id="reviewRequestId" value="">
                    <input type="hidden" id="reviewRatingValue" value="5">

                    <!-- Interactive Star Selector -->
                    <div class="text-center mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">Seberapa puas Anda dengan hasil pekerjaan?</label>
                        <div class="star-rating-selector my-2" id="starRatingSelector">
                            <i class="fa-solid fa-star star-item active" data-val="1"></i>
                            <i class="fa-solid fa-star star-item active" data-val="2"></i>
                            <i class="fa-solid fa-star star-item active" data-val="3"></i>
                            <i class="fa-solid fa-star star-item active" data-val="4"></i>
                            <i class="fa-solid fa-star star-item active" data-val="5"></i>
                        </div>
                        <div id="starRatingLabel" class="fw-bold text-teal small">Luar Biasa Memuaskan! (5/5)</div>
                    </div>

                    <!-- Quick Impression Chips Multi-Select -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">Kesan Layanan (Pilih yang sesuai):</label>
                        <div class="d-flex flex-wrap gap-1.5" id="reviewTagsContainer">
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill review-tag-btn" data-tag="Kerja Cepat & Tepat Waktu" onclick="toggleReviewTag(this)">⚡ Kerja Cepat</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill review-tag-btn" data-tag="Hasil Rapi & Bersih" onclick="toggleReviewTag(this)">🧹 Rapi & Bersih</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill review-tag-btn" data-tag="Tarif Sangat Terjangkau" onclick="toggleReviewTag(this)">💰 Harga Terjangkau</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill review-tag-btn" data-tag="Ramah & Komunikatif" onclick="toggleReviewTag(this)">🤝 Ramah & Sopan</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill review-tag-btn" data-tag="Sangat Berpengalaman" onclick="toggleReviewTag(this)">🏆 Berpengalaman</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2.5 rounded-pill review-tag-btn" data-tag="Sangat Direkomendasikan" onclick="toggleReviewTag(this)">👍 Recommended</button>
                        </div>
                    </div>

                    <!-- Detail Comment Textarea -->
                    <div class="mb-3">
                        <label for="reviewComment" class="form-label small fw-bold text-dark mb-1">Tulis Ulasan Anda (Opsional):</label>
                        <textarea id="reviewComment" rows="2" class="form-control" placeholder="Ceritakan bagaimana hasil servis/pekerjaan teknisi ini..."></textarea>
                    </div>

                    <!-- Upload Foto Bukti Hasil Pengerjaan -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1 d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-camera text-teal me-1"></i> Foto Bukti Hasil Pengerjaan <span class="fw-normal text-muted">(Opsional)</span></span>
                            <span class="text-muted" style="font-size: 0.7rem;">Maks 5MB</span>
                        </label>
                        <input type="file" id="reviewPhotoInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" onchange="previewReviewPhoto(this)">
                        
                        <div id="reviewPhotoPreviewBox" class="d-none mt-2 position-relative d-inline-block border rounded-3 p-1 bg-light">
                            <img id="reviewPhotoPreviewImg" src="" alt="Preview Bukti" class="rounded-2" style="max-height: 90px; max-width: 130px; object-fit: cover;">
                            <button type="button" class="btn btn-danger btn-xs rounded-circle position-absolute top-0 end-0 m-1 p-0 d-flex align-items-center justify-content-center" style="width: 20px; height: 20px;" onclick="removeReviewPhoto()">
                                <i class="fa-solid fa-xmark" style="font-size: 0.65rem;"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="btnSubmitReview" class="btn btn-primary-custom w-100 py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="fa-solid fa-paper-plane" id="btnReviewIcon"></i>
                        <span id="btnReviewText">Kirim Ulasan & Bintang</span>
                        <span id="btnReviewSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const starRatingLabels = {
    1: 'Kurang Memuaskan (1/5)',
    2: 'Cukup Memuaskan (2/5)',
    3: 'Baik & Sesuai Harapan (3/5)',
    4: 'Sangat Bagus & Rapi (4/5)',
    5: 'Luar Biasa Memuaskan! (5/5)'
};

function initStarSelector() {
    const stars = document.querySelectorAll('#starRatingSelector .star-item');
    stars.forEach(star => {
        star.addEventListener('mouseenter', function() {
            const val = parseInt(this.getAttribute('data-val'));
            highlightStars(val);
            document.getElementById('starRatingLabel').textContent = starRatingLabels[val];
        });
        star.addEventListener('mouseleave', function() {
            const currentVal = parseInt(document.getElementById('reviewRatingValue').value);
            highlightStars(currentVal);
            document.getElementById('starRatingLabel').textContent = starRatingLabels[currentVal];
        });
        star.addEventListener('click', function() {
            const val = parseInt(this.getAttribute('data-val'));
            document.getElementById('reviewRatingValue').value = val;
            highlightStars(val);
            document.getElementById('starRatingLabel').textContent = starRatingLabels[val];
        });
    });
}

function highlightStars(val) {
    const stars = document.querySelectorAll('#starRatingSelector .star-item');
    stars.forEach(s => {
        const sVal = parseInt(s.getAttribute('data-val'));
        if (sVal <= val) {
            s.classList.add('active');
        } else {
            s.classList.remove('active');
        }
    });
}

function toggleReviewTag(btn) {
    btn.classList.toggle('active');
    if (btn.classList.contains('active')) {
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-teal', 'text-white');
    } else {
        btn.classList.remove('btn-teal', 'text-white');
        btn.classList.add('btn-outline-secondary');
    }
}

function previewReviewPhoto(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran foto terlalu besar. Maksimum 5MB.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('reviewPhotoPreviewImg').src = e.target.result;
            document.getElementById('reviewPhotoPreviewBox').classList.remove('d-none');
        };
        reader.readAsDataURL(file);
    }
}

function removeReviewPhoto() {
    const input = document.getElementById('reviewPhotoInput');
    if (input) input.value = '';
    const box = document.getElementById('reviewPhotoPreviewBox');
    if (box) box.classList.add('d-none');
    const img = document.getElementById('reviewPhotoPreviewImg');
    if (img) img.src = '';
}

function openReviewModal(requestId, providerName, initialRating = 5, initialComment = '', initialTags = '', initialPhoto = '') {
    document.getElementById('reviewRequestId').value = requestId;
    document.getElementById('reviewModalProviderName').textContent = providerName;
    document.getElementById('reviewRatingValue').value = initialRating;
    document.getElementById('reviewComment').value = initialComment;
    document.getElementById('reviewAlert').classList.add('d-none');
    
    highlightStars(initialRating);
    document.getElementById('starRatingLabel').textContent = starRatingLabels[initialRating];

    // Reset tags
    const tagList = initialTags ? initialTags.split(',').map(t => t.trim().toLowerCase()) : [];
    document.querySelectorAll('#reviewTagsContainer .review-tag-btn').forEach(btn => {
        const tagVal = (btn.getAttribute('data-tag') || '').toLowerCase();
        if (tagList.includes(tagVal)) {
            btn.classList.add('active', 'btn-teal', 'text-white');
            btn.classList.remove('btn-outline-secondary');
        } else {
            btn.classList.remove('active', 'btn-teal', 'text-white');
            btn.classList.add('btn-outline-secondary');
        }
    });

    // Reset / Set Foto
    removeReviewPhoto();
    if (initialPhoto) {
        document.getElementById('reviewPhotoPreviewImg').src = '<?= BASE_URL ?>/' + initialPhoto;
        document.getElementById('reviewPhotoPreviewBox').classList.remove('d-none');
    }

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('reviewModal'));
    modal.show();
}

function submitReview() {
    const requestId = document.getElementById('reviewRequestId').value;
    const rating = document.getElementById('reviewRatingValue').value;
    const comment = document.getElementById('reviewComment').value.trim();

    const alertEl = document.getElementById('reviewAlert');
    const alertText = document.getElementById('reviewAlertText');
    alertEl.classList.add('d-none');

    const btn = document.getElementById('btnSubmitReview');
    const btnText = document.getElementById('btnReviewText');
    const btnSpinner = document.getElementById('btnReviewSpinner');
    const btnIcon = document.getElementById('btnReviewIcon');

    if (btn) btn.disabled = true;
    if (btnText) btnText.textContent = 'Menyimpan Ulasan...';
    if (btnSpinner) btnSpinner.classList.remove('d-none');
    if (btnIcon) btnIcon.classList.add('d-none');

    const formData = new FormData();
    formData.append('csrf_token', '<?= csrf_token() ?>');
    formData.append('request_id', requestId);
    formData.append('rating', rating);
    formData.append('comment', comment);

    // Ambil semua tag aktif
    const activeTags = [];
    document.querySelectorAll('#reviewTagsContainer .review-tag-btn.active').forEach(b => {
        activeTags.push(b.getAttribute('data-tag'));
    });
    activeTags.forEach(t => formData.append('tags[]', t));

    // Ambil file foto jika diunggah
    const photoInput = document.getElementById('reviewPhotoInput');
    if (photoInput && photoInput.files && photoInput.files[0]) {
        formData.append('photo', photoInput.files[0]);
    }

    fetch('<?= BASE_URL ?>/api/submit_review.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Kirim Ulasan & Bintang';
        if (btnSpinner) btnSpinner.classList.add('d-none');
        if (btnIcon) btnIcon.classList.remove('d-none');

        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('reviewModal'));
            if (modal) modal.hide();
            window.location.reload();
        } else {
            alertText.textContent = data.message || 'Gagal menyimpan ulasan.';
            alertEl.classList.remove('d-none');
        }
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Kirim Ulasan & Bintang';
        if (btnSpinner) btnSpinner.classList.add('d-none');
        if (btnIcon) btnIcon.classList.remove('d-none');

        alertText.textContent = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        alertEl.classList.remove('d-none');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initStarSelector();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

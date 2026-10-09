<?php
/**
 * Papan Permintaan Jasa Terbuka (Tender Kilat / Open Broadcast Warga)
 * JASA INHU - Menghubungkan kebutuhan warga dengan puluhan teknisi & mitra terverifikasi
 */

$page_title = 'Papan Permintaan Jasa Terbuka (Tender Kilat)';

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$user = current_user();
$db = get_db();
$error = '';
$success = '';

// Handle pembuatan permintaan terbuka oleh pelanggan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_broadcast_request') {
    if (!is_logged_in()) {
        set_flash('info', 'Silakan masuk atau daftar terlebih dahulu untuk memasang permintaan jasa terbuka.');
        redirect('/login.php?redirect=' . urlencode('/tender.php'));
    }

    // Perlindungan Anti-Order Fiktif: Wajib verifikasi akun Gmail sebelum pasang tender
    if (empty($user['email_verified_at']) && ($user['role_name'] ?? '') !== 'admin') {
        set_flash('warning', 'Demi mencegah permintaan jasa fiktif dan melindungi teknisi lokal kami, silakan verifikasi akun Gmail Anda terlebih dahulu.');
        redirect('/verify.php?redirect=' . urlencode('/tender.php'));
    }

    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $district_id = (int)($_POST['district_id'] ?? 0);
        $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;
        $urgency = in_array($_POST['urgency'] ?? '', ['normal', 'urgent', 'scheduled']) ? $_POST['urgency'] : 'normal';
        $address_detail = trim($_POST['address_detail'] ?? '');
        $preferred_schedule = trim($_POST['preferred_schedule'] ?? '');

        if (!empty($preferred_schedule)) {
            $description .= "\n\nWaktu yang diharapkan: " . $preferred_schedule;
        }

        if (empty($title) || empty($description) || $category_id <= 0 || $district_id <= 0) {
            $error = 'Judul kebutuhan, kategori jasa, kecamatan, dan rincian pekerjaan wajib diisi.';
        } else {
            try {
                $db->beginTransaction();

                // 1. Masukkan ke service_requests dengan provider_id = NULL (Terbuka / Broadcast)
                $stmtIns = $db->prepare("
                    INSERT INTO service_requests 
                    (user_id, category_id, provider_id, district_id, village_id, title, description, budget, address_detail, urgency, status, progress_step, created_at)
                    VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'open', 'created', NOW())
                ");
                $stmtIns->execute([
                    $user['id'], $category_id, $district_id, $village_id,
                    $title, $description, $budget, $address_detail, $urgency
                ]);
                $req_id = (int)$db->lastInsertId();

                // 2. Catat ke timeline aktivitas pesanan
                add_order_timeline_event(
                    $req_id,
                    'created',
                    '📢 Permintaan Terbuka Diterbitkan ke Semua Mitra',
                    "Warga {$user['name']} menyiarkan permintaan jasa: '{$title}'. Menunggu penawaran dari para teknisi terdaftar di Inhu.",
                    'customer'
                );

                // 3. Notifikasi broadcast otomatis ke semua mitra di kategori jasa ini
                $stmtMitra = $db->prepare("
                    SELECT sp.user_id, sp.business_name 
                    FROM service_providers sp 
                    JOIN users u ON sp.user_id = u.id 
                    WHERE sp.primary_category_id = ? AND u.is_active = 1
                    LIMIT 20
                ");
                $stmtMitra->execute([$category_id]);
                $matched_mitras = $stmtMitra->fetchAll();

                $notifTitle = "Peluang Kerja Baru di Inhu: " . mb_strimwidth($title, 0, 45, '...');
                $notifMsg = "Ada permintaan jasa terbuka baru dari warga. Segera berikan penawaran harga Anda sebelum diambil mitra lain!";
                $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                foreach ($matched_mitras as $mitra) {
                    if ((int)$mitra['user_id'] !== (int)$user['id']) {
                        $stmtNotif->execute([$mitra['user_id'], $notifTitle, $notifMsg, '/provider/leads.php']);
                    }
                }

                $db->commit();

                set_flash('success', 'Permintaan jasa Anda berhasil disiarkan ke semua teknisi di Indragiri Hulu! Silakan pantau penawaran harga yang masuk.');
                redirect('/user/requests.php?id=' . $req_id);
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Gagal memasang permintaan: ' . $e->getMessage();
            }
        }
    }
}

// Ambil data kategori & kecamatan
$categories = get_active_categories();
$districts = get_all_districts();

// Ambil daftar permintaan terbuka terbaru dari warga (Live Broadcast Ticker)
$stmtOpenRecent = $db->query("
    SELECT sr.*, u.name as customer_name, sc.name as category_name, sc.icon as category_icon, d.name as district_name,
           (SELECT COUNT(*) FROM service_request_responses srr WHERE srr.request_id = sr.id) as total_offers
    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id
    JOIN service_categories sc ON sr.category_id = sc.id
    JOIN districts d ON sr.district_id = d.id
    WHERE sr.status = 'open' AND sr.provider_id IS NULL
    ORDER BY sr.created_at DESC
    LIMIT 8
");
$open_broadcasts = $stmtOpenRecent->fetchAll();

// Ambil Banner Hero Tender Kilat dari tabel banners
try {
    $stmtTenderBanner = $db->query("SELECT * FROM banners WHERE position = 'tender' AND is_active = 1 ORDER BY sort_order ASC, id DESC LIMIT 1");
    $tender_banner = $stmtTenderBanner->fetch();
} catch (Exception $e) {
    $tender_banner = null;
}

$has_banner_img = !empty($tender_banner['image_url']) && file_exists(__DIR__ . '/' . $tender_banner['image_url']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Hero Banner Tender Kilat (Dinamis dari Admin Banner) -->
    <?php if ($has_banner_img && empty($tender_banner['show_overlay'])): ?>
        <div class="mb-4 rounded-4 overflow-hidden shadow-sm">
            <?php if (!empty($tender_banner['link_url'])): ?>
                <a href="<?= e($tender_banner['link_url']) ?>" target="_blank">
                    <img src="<?= BASE_URL ?>/<?= e($tender_banner['image_url']) ?>" alt="<?= e($tender_banner['title']) ?>" class="w-100 object-fit-cover rounded-4" style="max-height: 360px;">
                </a>
            <?php else: ?>
                <img src="<?= BASE_URL ?>/<?= e($tender_banner['image_url']) ?>" alt="<?= e($tender_banner['title']) ?>" class="w-100 object-fit-cover rounded-4" style="max-height: 360px;">
            <?php endif; ?>
        </div>
    <?php elseif ($has_banner_img && !empty($tender_banner['show_overlay'])): ?>
        <div class="card border-0 shadow-sm p-4 p-md-5 mb-4 text-white overflow-hidden position-relative" style="background: linear-gradient(135deg, rgba(13, 148, 136, 0.90) 0%, rgba(4, 47, 46, 0.95) 100%), url('<?= BASE_URL ?>/<?= e($tender_banner['image_url']) ?>') center/cover no-repeat; border-radius: 20px;">
            <div class="position-absolute end-0 top-0 bottom-0 opacity-10 d-none d-lg-block p-4" style="pointer-events: none;">
                <i class="fa-solid fa-bullhorn" style="font-size: 16rem; transform: rotate(-15deg);"></i>
            </div>
            <div class="position-relative" style="z-index: 2; max-width: 760px;">
                <span class="badge fw-bold px-3 py-1.5 rounded-pill mb-2 shadow-xs" style="background-color: <?= e($tender_banner['badge_color'] ?: '#f59e0b') ?>; color: #0f172a;">
                    <i class="fa-solid fa-bolt me-1"></i> <?= e($tender_banner['badge_text'] ?: 'Tender Kilat & Siaran Warga Inhu') ?>
                </span>
                <h1 class="fw-bold mb-2 display-6"><?= e($tender_banner['title'] ?: 'Pasang Kebutuhan Jasa Terbuka') ?></h1>
                <p class="text-white-50 fs-6 mb-3">
                    <?= e($tender_banner['subtitle'] ?: 'Bingung memilih teknisi atau ingin membandingkan harga? Siarkan keluhan atau proyek pekerjaan Anda secara gratis. Teknisi & bengkel terverifikasi di sekitarmu akan mengajukan penawaran harga terbaik!') ?>
                </p>
                <div class="d-flex flex-wrap gap-3 small text-white-50">
                    <div class="d-flex align-items-center gap-1.5"><i class="fa-solid fa-circle-check text-warning"></i> 100% Gratis Pasang</div>
                    <div class="d-flex align-items-center gap-1.5"><i class="fa-solid fa-circle-check text-warning"></i> Bandingkan Tawaran & Biaya</div>
                    <div class="d-flex align-items-center gap-1.5"><i class="fa-solid fa-circle-check text-warning"></i> Mitra Terverifikasi di 14 Kecamatan</div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm p-4 p-md-5 mb-4 text-white overflow-hidden position-relative" style="background: linear-gradient(135deg, <?= e($tender_banner['badge_color'] ?? '#0d9488') ?> 0%, #042f2e 100%); border-radius: 20px;">
            <div class="position-absolute end-0 top-0 bottom-0 opacity-10 d-none d-lg-block p-4" style="pointer-events: none;">
                <i class="fa-solid fa-bullhorn" style="font-size: 16rem; transform: rotate(-15deg);"></i>
            </div>
            <div class="position-relative" style="z-index: 2; max-width: 760px;">
                <span class="badge fw-bold px-3 py-1.5 rounded-pill mb-2 shadow-xs" style="background-color: <?= e($tender_banner['badge_color'] ?? '#f59e0b') ?>; color: #0f172a;">
                    <i class="fa-solid fa-bolt me-1"></i> <?= e($tender_banner['badge_text'] ?? 'Tender Kilat & Siaran Warga Inhu') ?>
                </span>
                <h1 class="fw-bold mb-2 display-6"><?= e($tender_banner['title'] ?? 'Pasang Kebutuhan Jasa Terbuka') ?></h1>
                <p class="text-white-50 fs-6 mb-3">
                    <?= e($tender_banner['subtitle'] ?? 'Bingung memilih teknisi atau ingin membandingkan harga? Siarkan keluhan atau proyek pekerjaan Anda secara gratis. Teknisi & bengkel terverifikasi di sekitarmu akan mengajukan penawaran harga terbaik!') ?>
                </p>
                <div class="d-flex flex-wrap gap-3 small text-white-50">
                    <div class="d-flex align-items-center gap-1.5"><i class="fa-solid fa-circle-check text-warning"></i> 100% Gratis Pasang</div>
                    <div class="d-flex align-items-center gap-1.5"><i class="fa-solid fa-circle-check text-warning"></i> Bandingkan Tawaran & Biaya</div>
                    <div class="d-flex align-items-center gap-1.5"><i class="fa-solid fa-circle-check text-warning"></i> Mitra Terverifikasi di 14 Kecamatan</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger shadow-sm rounded-3 py-2.5 px-3 mb-4 small d-flex align-items-center">
            <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

<style>
/* Modern Tender UI Styles */
.form-tender-card {
    border-radius: 20px;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
    border: 1px solid rgba(13, 148, 136, 0.15);
}
.cat-chip {
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    color: #334155;
    padding: 7px 15px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    gap: 7px;
    user-select: none;
}
.cat-chip:hover {
    border-color: #0d9488;
    color: #0d9488;
    background: #f0fdfa;
    transform: translateY(-2px);
}
.cat-chip.active {
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%) !important;
    color: #ffffff !important;
    border-color: #0d9488 !important;
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.28);
}
.urgency-card {
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    border-radius: 14px;
    padding: 14px 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}
.urgency-card:hover {
    border-color: #0d9488;
    background: #f8fafc;
    transform: translateY(-2px);
}
.urgency-card.active {
    border-color: #0d9488 !important;
    background: #f0fdfa !important;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.18);
}
.urgency-card.active .urgency-title {
    color: #0d9488 !important;
}
.quick-template-pill {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    border-radius: 50px;
    padding: 5px 13px;
    font-size: 0.74rem;
    font-weight: 600;
    transition: all 0.15s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.quick-template-pill:hover {
    border-color: #0d9488;
    color: #0d9488;
    background: #f0fdfa;
    transform: translateY(-1px);
}
.input-group-modern {
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s ease;
    background: #ffffff;
}
.input-group-modern:focus-within {
    border-color: #0d9488;
    box-shadow: 0 0 0 3.5px rgba(13, 148, 136, 0.14);
}
.input-group-modern .input-group-text {
    background: #f8fafc;
    border: none;
    color: #0d9488;
    font-size: 0.95rem;
    padding-left: 14px;
    padding-right: 12px;
}
.input-group-modern .form-control,
.input-group-modern .form-select {
    border: none;
    box-shadow: none !important;
    padding: 10px 14px 10px 8px;
    font-size: 0.9rem;
}
.budget-chip {
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 4px 11px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.budget-chip:hover {
    border-color: #0d9488;
    color: #0d9488;
    background: #f0fdfa;
}
.btn-broadcast-submit {
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    border: none;
    color: #ffffff;
    font-size: 1rem;
    font-weight: 700;
    border-radius: 12px;
    padding: 14px 20px;
    box-shadow: 0 6px 20px rgba(13, 148, 136, 0.28);
    transition: all 0.2s ease;
}
.btn-broadcast-submit:hover {
    background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(13, 148, 136, 0.35);
}
.step-number-badge {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #0d9488;
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
}
</style>

    <div class="row g-4">
        <!-- Kolom Kiri: Form Pasang Permintaan Terbuka Modern -->
        <div class="col-lg-7">
            <div class="card form-tender-card bg-white overflow-hidden">
                <!-- Top Accent Line -->
                <div style="height: 5px; background: linear-gradient(90deg, #0d9488 0%, #14b8a6 50%, #f59e0b 100%);"></div>

                <div class="card-header bg-white py-3.5 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center shadow-xs" style="width: 40px; height: 40px; background: linear-gradient(135deg, #0d9488, #0f766e); font-size: 1.05rem;">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">Formulir Tender Kilat Warga</h5>
                            <span class="text-muted small">Disiarkan gratis ke puluhan teknisi & mitra terverifikasi se-Inhu</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1.5 px-2.5 py-1 rounded-pill bg-teal-subtle text-teal fw-semibold" style="font-size: 0.74rem;">
                        <span class="badge rounded-circle bg-teal p-1" style="width: 6px; height: 6px;"></span>
                        <span>Broadcast Siaga 14 Kecamatan</span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form method="POST" action="<?= BASE_URL ?>/tender.php" id="broadcastTenderForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_broadcast_request">

                        <!-- 1. KATEGORI JASA VISUAL SELECTOR -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between mb-2">
                                <span><span class="step-number-badge">1</span> Kategori Jasa yang Dibutuhkan <span class="text-danger">*</span></span>
                                <span class="text-muted fw-normal" style="font-size: 0.72rem;">Klik ikon kategori di bawah</span>
                            </label>

                            <!-- Chips Kategori Cepat -->
                            <div class="d-flex flex-wrap gap-2 mb-2.5" id="categoryChipsContainer">
                                <?php
                                $top_cat_ids = [3, 1, 5, 8, 16, 4, 6, 11]; // AC, Motor, Listrik, Las, Rumah/Pompa, Elektronik, Bangunan, Kebersihan
                                $quick_cats = array_filter($categories, fn($c) => in_array((int)$c['id'], $top_cat_ids));
                                foreach ($quick_cats as $qc):
                                ?>
                                    <div class="cat-chip" data-cat-id="<?= $qc['id'] ?>" onclick="selectCategoryChip(<?= $qc['id'] ?>)">
                                        <i class="fa-solid <?= e($qc['icon']) ?> text-teal"></i>
                                        <span><?= e($qc['name']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Dropdown Lengkap Sinkron -->
                            <div class="input-group-modern d-flex align-items-center">
                                <span class="input-group-text"><i class="fa-solid fa-shapes"></i></span>
                                <select name="category_id" id="tender_category_select" class="form-select" onchange="syncCategoryFromSelect(this)" required>
                                    <option value="">-- Atau Pilih dari Seluruh 17 Kategori Layanan Inhu --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- 2. PINTASAN KELUHAN CEPAT 1-KLIK -->
                        <div class="mb-4 p-3 rounded-3 bg-light border border-teal-subtle">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.68rem;">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i> PINTASAN KELUHAN 1-KLIK
                                </span>
                                <span class="text-muted small" style="font-size: 0.72rem;">Klik untuk isi formulir otomatis:</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1.5">
                                <button type="button" class="quick-template-pill" onclick="fillTemplate('Servis Cuci AC & Perbaikan Bocor', 'AC ruangan meneteskan air, tiupan kurang dingin, dan berisik. Butuh cuci blower dan cek tekanan freon.', 'urgent', 3)">
                                    ❄️ Cuci & Servis AC Bocor
                                </button>
                                <button type="button" class="quick-template-pill" onclick="fillTemplate('Perbaikan Mesin Pompa Air Macet / Tidak Narik', 'Mesin pompa air menyala tapi air tidak naik ke tandon. Sudah dipancing tetap tidak keluar. Mohon cek pipa hisap atau dinamo.', 'urgent', 16)">
                                    🚰 Pompa Air Macet
                                </button>
                                <button type="button" class="quick-template-pill" onclick="fillTemplate('Servis Motor Mogok / Ganti Oli & Karburator', 'Motor mogok mendadak di jalan/rumah, tidak bisa distarter. Butuh montir terdekat bawa kunci dan busi cadangan.', 'urgent', 1)">
                                    🏍️ Motor Mogok / Servis
                                </button>
                                <button type="button" class="quick-template-pill" onclick="fillTemplate('Perbaikan Konslet Listrik Rumah / MCB Turun Terus', 'Listrik padam sebagian di rumah, MCB meteran jeglek setiap dinyalakan. Butuh pengecekan jalur kabel & stop kontak.', 'urgent', 5)">
                                    ⚡ Listrik Konslet / MCB
                                </button>
                                <button type="button" class="quick-template-pill" onclick="fillTemplate('Pengelasan Tralis Jendela & Engsel Pintu Pagar Besi', 'Engsel pagar besi patah dan tralis jendela agak renggang. Butuh tukang las panggilan bawa mesin las trafo ke lokasi.', 'normal', 8)">
                                    🚪 Las Pagar & Tralis
                                </button>
                            </div>
                        </div>

                        <!-- 3. TINGKAT URGENSI / WAKTU PENGERJAAN -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between mb-2">
                                <span><span class="step-number-badge">2</span> Kapan Layanan Jasa Dibutuhkan? <span class="text-danger">*</span></span>
                                <span class="text-muted fw-normal" style="font-size: 0.72rem;">Pilih salah satu</span>
                            </label>
                            <div class="row g-2">
                                <!-- Card 1: Normal -->
                                <div class="col-4">
                                    <div class="urgency-card active" id="card_urgency_normal" onclick="selectUrgency('normal')">
                                        <input type="radio" name="urgency" value="normal" checked class="d-none">
                                        <div class="fs-4 mb-1">🟢</div>
                                        <div class="fw-bold small urgency-title lh-sm" style="font-size: 0.82rem;">Standar</div>
                                        <div class="text-muted small mt-0.5" style="font-size: 0.68rem;">Bisa kapan saja minggu ini</div>
                                    </div>
                                </div>
                                <!-- Card 2: Urgent -->
                                <div class="col-4">
                                    <div class="urgency-card position-relative" id="card_urgency_urgent" onclick="selectUrgency('urgent')">
                                        <span class="badge bg-danger position-absolute top-0 start-50 translate-middle py-0.5 px-2 shadow-xs" style="font-size: 0.6rem;">DARURAT</span>
                                        <input type="radio" name="urgency" value="urgent" class="d-none">
                                        <div class="fs-4 mb-1">⚡</div>
                                        <div class="fw-bold small urgency-title lh-sm" style="font-size: 0.82rem;">Mendesak</div>
                                        <div class="text-muted small mt-0.5" style="font-size: 0.68rem;">Butuh hari ini / cepat</div>
                                    </div>
                                </div>
                                <!-- Card 3: Scheduled -->
                                <div class="col-4">
                                    <div class="urgency-card" id="card_urgency_scheduled" onclick="selectUrgency('scheduled')">
                                        <input type="radio" name="urgency" value="scheduled" class="d-none">
                                        <div class="fs-4 mb-1">📅</div>
                                        <div class="fw-bold small urgency-title lh-sm" style="font-size: 0.82rem;">Terjadwal</div>
                                        <div class="text-muted small mt-0.5" style="font-size: 0.68rem;">Tentukan hari & jam</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. DETAIL KEBUTUHAN & DESKRIPSI -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between mb-1.5">
                                <span><span class="step-number-badge">3</span> Judul & Rincian Kerusakan <span class="text-danger">*</span></span>
                            </label>

                            <!-- Judul -->
                            <div class="input-group-modern d-flex align-items-center mb-2">
                                <span class="input-group-text"><i class="fa-solid fa-pen-nib"></i></span>
                                <input type="text" name="title" id="tender_title_input" class="form-control" placeholder="Contoh: Perbaikan Pompa Air Macet & Tidak Mau Narik" required>
                            </div>
                            <span class="text-muted d-block mb-3" style="font-size: 0.72rem;">Ringkas dan jelas agar mudah dipahami mitra teknisi saat membaca notifikasi.</span>

                            <!-- Deskripsi -->
                            <div class="input-group-modern p-1">
                                <textarea name="description" id="tender_desc_input" rows="3" class="form-control" placeholder="Jelaskan kendala secara spesifik, tipe/merk alat, atau bahan yang sudah Anda siapkan agar teknisi dapat menaksir biaya secara akurat..." required></textarea>
                            </div>
                        </div>

                        <!-- 5. WILAYAH KECAMATAN & BUDGET ESTIMASI -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark d-flex align-items-center justify-content-between mb-2">
                                <span><span class="step-number-badge">4</span> Lokasi Wilayah & Perkiraan Anggaran</span>
                            </label>
                            <div class="row g-3">
                                <!-- Kecamatan -->
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">Kecamatan di Inhu <span class="text-danger">*</span></label>
                                    <div class="input-group-modern d-flex align-items-center">
                                        <span class="input-group-text"><i class="fa-solid fa-location-dot text-danger"></i></span>
                                        <select name="district_id" class="form-select" required>
                                            <option value="">-- Pilih Kecamatan di Inhu --</option>
                                            <?php foreach ($districts as $d): ?>
                                                <option value="<?= $d['id'] ?>" <?= (!empty($user['district_id']) && (int)$user['district_id'] === (int)$d['id']) ? 'selected' : '' ?>>
                                                    Kec. <?= e($d['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Budget -->
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">Estimasi Budget / Anggaran (Rp)</label>
                                    <div class="input-group-modern d-flex align-items-center">
                                        <span class="input-group-text fw-bold text-teal">Rp</span>
                                        <input type="number" name="budget" id="tender_budget_input" class="form-control" placeholder="Kosongkan jika ingin negosiasi">
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mt-1.5">
                                        <span class="budget-chip" onclick="setBudget(50000)">50rb</span>
                                        <span class="budget-chip" onclick="setBudget(100000)">100rb</span>
                                        <span class="budget-chip" onclick="setBudget(150000)">150rb</span>
                                        <span class="budget-chip" onclick="setBudget(250000)">250rb</span>
                                        <span class="budget-chip" onclick="setBudget('')">Nego</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 6. ALAMAT LENGKAP & JADWAL KEDATANGAN -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Patokan Alamat / Area</label>
                                <div class="input-group-modern d-flex align-items-center">
                                    <span class="input-group-text"><i class="fa-solid fa-map-location-dot text-teal"></i></span>
                                    <input type="text" name="address_detail" class="form-control" placeholder="Contoh: Jl. Narasinga dekat jembatan, Rengat" value="<?= e($user['address'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">Waktu Kedatangan yang Diharapkan</label>
                                <div class="input-group-modern d-flex align-items-center">
                                    <span class="input-group-text"><i class="fa-regular fa-clock text-warning"></i></span>
                                    <input type="text" name="preferred_schedule" class="form-control" placeholder="Contoh: Besok pagi jam 09.00 / Sore">
                                </div>
                            </div>
                        </div>

                        <!-- TOMBOL SUBMIT CTA -->
                        <div class="pt-2">
                            <?php if (is_logged_in()): ?>
                                <button type="submit" class="btn btn-broadcast-submit w-100 d-flex align-items-center justify-content-center gap-2">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    <span>Siarkan Kebutuhan Jasa ke Mitra Sekarang (Gratis)</span>
                                </button>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/login.php?redirect=<?= urlencode('/tender.php') ?>" class="btn btn-broadcast-submit w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none">
                                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                                    <span>Masuk untuk Siarkan Kebutuhan Jasa Anda</span>
                                </a>
                            <?php endif; ?>

                            <div class="text-center mt-2.5 text-muted d-flex align-items-center justify-content-center gap-2 flex-wrap" style="font-size: 0.75rem;">
                                <span><i class="fa-solid fa-shield-halved text-success me-1"></i> 100% Gratis Tanpa Potongan</span>
                                <span>&bull;</span>
                                <span><i class="fa-solid fa-comments text-teal me-1"></i> Mitra Terverifikasi Akan Mengajukan Penawaran</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Live Feed Permintaan Terbuka Warga & Informasi Mitra -->
        <div class="col-lg-5">
            <!-- Banner untuk Penyedia Jasa -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 text-white overflow-hidden" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-warning text-dark fw-bold">Khusus Penyedia Jasa</span>
                    </div>
                    <h6 class="fw-bold text-white mb-1 fs-6">Cari Pekerjaan Baru di Sekitarmu?</h6>
                    <p class="text-white-50 small mb-3" style="font-size: 0.8rem; line-height: 1.5;">
                        Masyarakat Inhu memposting kebutuhan jasa setiap hari. Kunjungi papan peluang pekerjaan untuk melihat rincian dan mengajukan penawaran harga Anda!
                    </p>
                    <a href="<?= BASE_URL ?>/provider/leads.php" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1.5 fw-semibold shadow-xs">
                        <i class="fa-solid fa-briefcase me-1"></i> Buka Papan Pekerjaan (Leads) &rarr;
                    </a>
                </div>
            </div>

            <!-- Live Feed Permintaan Warga -->
            <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <div class="fw-bold small text-dark d-flex align-items-center gap-2">
                        <span class="badge rounded-circle bg-danger p-1" style="width: 8px; height: 8px;"></span>
                        <span style="letter-spacing: 0.5px;">PERMINTAAN TERBUKA TERBARU</span>
                    </div>
                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">Live Inhu</span>
                </div>

                <div class="list-group list-group-flush small" style="max-height: 520px; overflow-y: auto;">
                    <?php if (!empty($open_broadcasts)): ?>
                        <?php foreach ($open_broadcasts as $bc): ?>
                            <div class="list-group-item p-3 hover-bg-light transition-all">
                                <div class="d-flex justify-content-between align-items-start mb-1.5">
                                    <span class="badge bg-teal-subtle text-teal border border-teal-subtle" style="font-size: 0.7rem;">
                                        <i class="fa-solid <?= e($bc['category_icon']) ?> me-1"></i> <?= e($bc['category_name']) ?>
                                    </span>
                                    <span class="fw-bold text-teal" style="font-size: 0.88rem;">
                                        <?= $bc['budget'] ? format_rupiah($bc['budget']) : 'Negosiasi' ?>
                                    </span>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 fs-6"><?= e($bc['title']) ?></h6>
                                <p class="text-muted small mb-2 text-truncate-2" style="font-size: 0.78rem; line-height: 1.45;">
                                    <?= e($bc['description']) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top text-muted" style="font-size: 0.72rem;">
                                    <span><i class="fa-solid fa-location-dot text-danger me-1"></i> Kec. <?= e($bc['district_name']) ?></span>
                                    <span><i class="fa-solid fa-gavel text-teal me-1"></i> <?= (int)$bc['total_offers'] ?> Tawaran</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted small">
                            <i class="fa-solid fa-bullhorn fs-3 text-muted opacity-50 mb-2 d-block"></i>
                            Belum ada permintaan terbuka aktif. Jadilah yang pertama memasang kebutuhan jasa Anda!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectCategoryChip(catId) {
    const select = document.getElementById('tender_category_select');
    if (select) {
        select.value = catId;
    }
    document.querySelectorAll('.cat-chip').forEach(el => {
        el.classList.toggle('active', el.dataset.catId == catId);
    });
}

function syncCategoryFromSelect(select) {
    const val = select.value;
    document.querySelectorAll('.cat-chip').forEach(el => {
        el.classList.toggle('active', el.dataset.catId == val);
    });
}

function selectUrgency(val) {
    document.querySelectorAll('.urgency-card').forEach(c => {
        const isMatch = (c.id === 'card_urgency_' + val);
        c.classList.toggle('active', isMatch);
        const radio = c.querySelector('input[type="radio"]');
        if (radio) radio.checked = isMatch;
    });
}

function fillTemplate(title, desc, urgency, catId) {
    const titleInput = document.getElementById('tender_title_input');
    const descInput = document.getElementById('tender_desc_input');
    if (titleInput) titleInput.value = title;
    if (descInput) descInput.value = desc;
    if (urgency) selectUrgency(urgency);
    if (catId) selectCategoryChip(catId);
    
    // Focus smoothly to title
    titleInput?.focus();
}

function setBudget(amount) {
    const budgetInput = document.getElementById('tender_budget_input');
    if (budgetInput) {
        budgetInput.value = amount;
        budgetInput.focus();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

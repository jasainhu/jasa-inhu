<?php
/**
 * Admin: Studio Konten & Iklan Medsos AI (Agent 2)
 * JASA INHU - Marketplace Jasa Lokal Kabupaten Indragiri Hulu
 */

$page_title = 'Studio Konten & Promosi AI';
$admin_active = 'ai_studio';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_content_generator.php';

require_role('admin');

$db = get_db();
$success = '';
$error = '';

// Handle AJAX Request dari Generator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'];

    if ($action === 'generate_video') {
        $category = trim($_POST['category'] ?? 'Servis AC');
        $district = trim($_POST['district'] ?? 'Rengat');
        $angle = trim($_POST['angle'] ?? 'Masalah Darurat & Solusi Cepat');

        $result = JasaInhuAIContentStudio::generateVideoScript($category, $district, $angle);
        echo json_encode($result);
        exit;
    }

    if ($action === 'generate_social') {
        $category = trim($_POST['category'] ?? 'Servis AC');
        $district = trim($_POST['district'] ?? 'Rengat');
        $platform = trim($_POST['platform'] ?? 'whatsapp_story');

        $result = JasaInhuAIContentStudio::generateSocialCopy($category, $district, $platform);
        echo json_encode($result);
        exit;
    }

    if ($action === 'generate_spotlight') {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $result = JasaInhuAIContentStudio::generatePartnerSpotlight($providerId);
        echo json_encode($result);
        exit;
    }

    // Aksi 4: Kirim Langsung ke Nomor WA Target via Fonnte Gateway
    if ($action === 'send_wa_direct') {
        $target = trim($_POST['target'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($target) || empty($message)) {
            echo json_encode(['status' => 'error', 'message' => 'Nomor WhatsApp tujuan dan isi pesan tidak boleh kosong']);
            exit;
        }

        $res = send_wa_notification($target, $message, 'ai_studio_direct');
        if ($res['status'] === 'sent') {
            echo json_encode(['status' => 'success', 'message' => 'Pesan promosi berhasil terkirim via WhatsApp Gateway!']);
        } else {
            $errMsg = is_string($res['response']) ? $res['response'] : ($res['message'] ?? 'Koneksi Fonnte sedang kendala atau nomor tidak valid');
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim: ' . $errMsg]);
        }
        exit;
    }

    // Aksi 5: Broadcast Promosi Otomatis ke Mitra di Kecamatan Tertentu
    if ($action === 'broadcast_to_partners') {
        $district = trim($_POST['district'] ?? 'Seluruh Inhu');
        $category = trim($_POST['category'] ?? 'Semua');
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            echo json_encode(['status' => 'error', 'message' => 'Pesan promosi tidak boleh kosong']);
            exit;
        }

        $query = "
            SELECT DISTINCT u.phone, sp.business_name
            FROM service_providers sp
            JOIN users u ON sp.user_id = u.id
            LEFT JOIN districts d ON sp.district_id = d.id
            LEFT JOIN service_categories sc ON sp.primary_category_id = sc.id
            WHERE u.is_active = 1 AND u.phone IS NOT NULL AND LENGTH(u.phone) >= 9
        ";
        $params = [];
        if ($district !== 'Seluruh Inhu' && !empty($district)) {
            $query .= " AND d.name = ?";
            $params[] = $district;
        }
        if ($category !== 'Semua' && !empty($category)) {
            $query .= " AND sc.name = ?";
            $params[] = $category;
        }
        $query .= " LIMIT 20";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($recipients)) {
            echo json_encode(['status' => 'error', 'message' => 'Tidak ditemukan mitra aktif dengan nomor HP pada filter ini']);
            exit;
        }

        $sentCount = 0;
        foreach ($recipients as $rec) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $rec['phone']);
            if (!empty($cleanPhone)) {
                $personalized = "Halo Mitra *" . $rec['business_name'] . "*,\n\n" . $message;
                $res = send_wa_notification($cleanPhone, $personalized, 'ai_mitra_broadcast');
                if ($res['status'] === 'sent') {
                    $sentCount++;
                }
                usleep(1200000); // jeda 1.2 detik per pesan agar aman dari rate-limit
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Berhasil mengirim broadcast otomatis ke {$sentCount} dari " . count($recipients) . " mitra via WhatsApp Gateway!"
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid']);
    exit;
}

// Ambil Kategori Aktif untuk Dropdown
$categories = $db->query("SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ambil Kecamatan untuk Dropdown
$districts = $db->query("SELECT id, name FROM districts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ambil 30 Mitra Teratas untuk Spotlight
$providers = $db->query("
    SELECT sp.id, sp.business_name, sc.name as category_name, d.name as district_name, sp.rating_avg, sp.completed_jobs
    FROM service_providers sp
    JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    JOIN users u ON sp.user_id = u.id
    WHERE u.is_active = 1
    ORDER BY sp.is_verified DESC, sp.rating_avg DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

$geminiKey = get_setting('gemini_api_key', '');
$fonnte_info = get_fonnte_account_info(false);
$admin_wa = get_setting('admin_wa', '6285126241679');

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="h3 fw-bold text-dark mb-0">Studio Konten & Promosi AI</h1>
            <span class="badge text-bg-warning text-dark px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">
                <i class="fa-solid fa-sparkles me-1"></i> Agent 2 (Kreatif & Medsos)
            </span>
        </div>
        <p class="text-muted small mb-0">Pabrik script video TikTok/Reels viral, materi promosi WA Story, dan postingan grup Facebook lokal Inhu</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 d-flex gap-2 align-items-center flex-wrap">
        <?php if (!empty($fonnte_info['is_connected'])): ?>
            <span class="badge text-bg-success px-3 py-2 fw-semibold shadow-xs" title="WhatsApp Gateway Fonnte Aktif (<?= e($fonnte_info['device']) ?>)">
                <i class="fa-brands fa-whatsapp me-1 text-white"></i> Bot WA: Terhubung (Kuota: <?= number_format($fonnte_info['quota']) ?>)
            </span>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/admin/settings.php" class="badge text-bg-secondary px-3 py-2 fw-semibold text-decoration-none shadow-xs" title="Klik untuk hubungkan WhatsApp Gateway">
                <i class="fa-brands fa-whatsapp me-1"></i> Bot WA: Offline
            </a>
        <?php endif; ?>

        <?php if (!empty($geminiKey)): ?>
            <span class="badge text-bg-dark px-3 py-2 fw-semibold shadow-xs">
                <i class="fa-solid fa-bolt me-1 text-warning"></i> Gemini Flash AI
            </span>
        <?php else: ?>
            <span class="badge text-white px-3 py-2 fw-semibold shadow-xs" style="background-color: #0d9488 !important;">
                <i class="fa-solid fa-microchip me-1"></i> Smart Local Matrix
            </span>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-outline-secondary btn-sm" title="Pengaturan AI & WA Gateway">
            <i class="fa-solid fa-sliders"></i>
        </a>
    </div>
</div>

<style>
/* Custom Studio Tabs High-Contrast Styling */
#studioTabs {
    background-color: #f1f5f9 !important;
    border: 1px solid #e2e8f0 !important;
    padding: 6px !important;
}
#studioTabs .nav-item {
    margin: 0;
}
#studioTabs .nav-link {
    color: #334155 !important;
    background-color: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    font-weight: 700 !important;
    font-size: 0.95rem !important;
    padding: 12px 18px !important;
    border-radius: 12px !important;
    transition: all 0.2s ease-in-out !important;
}
#studioTabs .nav-link i {
    transition: all 0.2s ease-in-out !important;
}
#studioTabs .nav-link:hover:not(.active) {
    background-color: #f8fafc !important;
    color: #0f172a !important;
    border-color: #cbd5e1 !important;
}
#studioTabs .nav-link.active {
    background-color: #0d9488 !important;
    color: #ffffff !important;
    border-color: #0d9488 !important;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.35) !important;
}
#studioTabs .nav-link.active span,
#studioTabs .nav-link.active i {
    color: #ffffff !important;
}
.ai-hero-banner {
    background: #0f172a !important;
    background: linear-gradient(135deg, #042f2e 0%, #0f172a 60%, #1e1b4b 100%) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
}
.ai-hero-banner h4 {
    color: #ffffff !important;
    font-weight: 800 !important;
}
.ai-hero-banner p {
    color: #cbd5e1 !important;
}
.ai-hero-banner .badge-pill-custom {
    background: rgba(255, 255, 255, 0.12) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    font-size: 0.8rem !important;
    font-weight: 600 !important;
}
.output-code-box {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
    font-size: 0.88rem !important;
    line-height: 1.65 !important;
}
</style>

<!-- Penjelasan Alur Agensi AI (High Contrast) -->
<div class="card border-0 shadow-sm rounded-4 mb-4 ai-hero-banner overflow-hidden position-relative">
    <div class="card-body p-4 position-relative" style="z-index: 2;">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge fw-bold px-3 py-1.5 mb-2 shadow-xs" style="background-color: #f59e0b !important; color: #000000 !important; font-size: 0.75rem; letter-spacing: 0.5px;">
                    🚀 CARA MEMBAKAR ORDERAN DENGAN AI
                </span>
                <h4 class="fw-bold mb-2 fs-3 text-white">Punya 30+ Mitra tapi Pesanan Masih Sepi?</h4>
                <p class="small mb-3" style="max-width: 650px; line-height: 1.6; font-size: 0.9rem;">
                    Kuncinya adalah <strong class="text-white">konsistensi konten</strong>. Gunakan AI Studio ini setiap hari untuk membuat 1 video TikTok/Reels atau 1 postingan di grup Facebook komunitas Inhu (<em>Info Warga Rengat / Belilas / Pasir Penyu</em>). Arahkan penonton untuk buka website dan tanya <strong style="color: #2dd4bf !important;">"Kak Inhu"</strong>!
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge badge-pill-custom py-2 px-3 rounded-pill"><i class="fa-solid fa-check text-warning me-1.5"></i> Script Video 3 Detik Hook</span>
                    <span class="badge badge-pill-custom py-2 px-3 rounded-pill"><i class="fa-solid fa-check text-warning me-1.5"></i> Copywriting Grup FB</span>
                    <span class="badge badge-pill-custom py-2 px-3 rounded-pill"><i class="fa-solid fa-check text-warning me-1.5"></i> Spotlight Mitra Harian</span>
                </div>
            </div>
            <div class="col-lg-4 text-center text-lg-end mt-3 mt-lg-0">
                <div class="display-3"><i class="fa-solid fa-wand-magic-sparkles text-warning animate__animated animate__pulse animate__infinite"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigasi Fitur Generator -->
<ul class="nav nav-pills nav-fill gap-2 p-1 rounded-4 shadow-sm mb-4" id="studioTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active d-flex align-items-center justify-content-center gap-2" id="tab-tiktok" data-bs-toggle="pill" data-bs-target="#content-tiktok" type="button" role="tab">
            <i class="fa-brands fa-tiktok fs-5"></i>
            <span>1. Script Video TikTok & Reels</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link d-flex align-items-center justify-content-center gap-2" id="tab-social" data-bs-toggle="pill" data-bs-target="#content-social" type="button" role="tab">
            <i class="fa-brands fa-facebook fs-5 text-primary"></i>
            <span>2. Teks Status WA & Grup Facebook</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link d-flex align-items-center justify-content-center gap-2" id="tab-spotlight" data-bs-toggle="pill" data-bs-target="#content-spotlight" type="button" role="tab">
            <i class="fa-solid fa-star fs-5 text-warning"></i>
            <span>3. Spotlight Rekomendasi Mitra</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="studioTabContent">

    <!-- ========================================================
         TAB 1: SCRIPT VIDEO TIKTOK & REELS
         ======================================================== -->
    <div class="tab-pane fade show active" id="content-tiktok" role="tabpanel">
        <div class="row g-4">
            <!-- Form Input Parameters -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-clapperboard text-teal"></i>
                            <span>Parameter Video</span>
                        </h5>
                        <p class="text-muted small mb-0">Pilih tema dan wilayah target yang ingin dipromosikan hari ini</p>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <form id="formTikTok">
                            <input type="hidden" name="action" value="generate_video">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Pilih Kategori Layanan:</label>
                                <select class="form-select fw-semibold" id="ttCategory" name="category" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Target Wilayah di Inhu:</label>
                                <select class="form-select fw-semibold" id="ttDistrict" name="district">
                                    <option value="Seluruh Inhu">🌟 Seluruh 14 Kecamatan Inhu</option>
                                    <?php foreach ($districts as $dis): ?>
                                        <option value="<?= e($dis['name']) ?>"><?= e($dis['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Sudut Pandang / Angle Video:</label>
                                <select class="form-select fw-semibold" id="ttAngle" name="angle">
                                    <option value="Masalah Darurat & Solusi Cepat">🚨 Masalah Darurat & Solusi Cepat (Pain Point)</option>
                                    <option value="Bandingkan Tarif Hemat vs Calo">💰 Bandingkan Tarif Hemat & Transparan</option>
                                    <option value="Cerita Mitra Sukses Lokal">🌟 Cerita Warga yang Terbantu</option>
                                    <option value="Tips Praktis Perawatan Rumah">💡 Tips Edukasi Seputar Barang/Kendaraan</option>
                                </select>
                            </div>

                            <button type="submit" id="btnGenTikTok" class="btn btn-teal w-100 py-2.5 fw-bold shadow-xs">
                                <i class="fa-solid fa-wand-magic-sparkles me-1.5"></i> Generate Script Video Sekarang
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Output Display Box -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-file-video text-danger"></i>
                            <span>Hasil Script Video Siap Rekam</span>
                        </h5>
                        <button type="button" id="btnCopyScript" class="btn btn-xs btn-outline-teal d-none py-1 px-2.5 fw-semibold" style="font-size: 0.76rem;" onclick="copyScriptContent()">
                            <i class="fa-regular fa-copy me-1"></i> Salin Semua
                        </button>
                    </div>
                    <div class="card-body p-4 pt-1" id="boxScriptOutput">
                        <div class="text-center py-5 text-muted">
                            <div class="fs-1 text-teal opacity-50 mb-2"><i class="fa-solid fa-video"></i></div>
                            <h6 class="fw-bold text-dark">Belum ada script yang dibuat</h6>
                            <p class="small text-muted mb-0">Pilih kategori di sebelah kiri dan klik tombol <strong>Generate Script</strong> untuk membuat ide konten TikTok/Reels viral lokal.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================
         TAB 2: TEKS STATUS WA & GRUP FACEBOOK
         ======================================================== -->
    <div class="tab-pane fade" id="content-social" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-bullhorn text-teal"></i>
                            <span>Parameter Broadcast Medsos</span>
                        </h5>
                        <p class="text-muted small mb-0">Buat teks promosi siap tempel untuk menjaring pelanggan lokal</p>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <form id="formSocial">
                            <input type="hidden" name="action" value="generate_social">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Platform Tujuan:</label>
                                <select class="form-select fw-semibold" id="socPlatform" name="platform" required>
                                    <option value="whatsapp_story">📱 Status & Story WhatsApp (Singkat & Akrab)</option>
                                    <option value="facebook_group">👥 Postingan Grup Facebook Komunitas Inhu (Sopan & Storytelling)</option>
                                    <option value="instagram_feed">📸 Caption Feed Instagram / Facebook Page</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Pilih Kategori Layanan:</label>
                                <select class="form-select fw-semibold" id="socCategory" name="category" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= e($cat['name']) ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Target Wilayah:</label>
                                <select class="form-select fw-semibold" id="socDistrict" name="district">
                                    <option value="Seluruh Inhu">🌟 Seluruh 14 Kecamatan Inhu</option>
                                    <?php foreach ($districts as $dis): ?>
                                        <option value="<?= e($dis['name']) ?>"><?= e($dis['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" id="btnGenSocial" class="btn btn-teal w-100 py-2.5 fw-bold shadow-xs">
                                <i class="fa-solid fa-pen-nib me-1.5"></i> Buat Teks Promosi Sekarang
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-copy text-success"></i>
                            <span>Teks Siap Salin & Posting</span>
                        </h5>
                        <div class="d-flex gap-2">
                            <button type="button" id="btnCopySocial" class="btn btn-xs btn-teal d-none py-1 px-2.5 fw-semibold" style="font-size: 0.76rem;" onclick="copySocialText()">
                                <i class="fa-regular fa-copy me-1"></i> Salin Teks
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4 pt-1" id="boxSocialOutput">
                        <div class="text-center py-5 text-muted">
                            <div class="fs-1 text-success opacity-50 mb-2"><i class="fa-brands fa-whatsapp"></i></div>
                            <h6 class="fw-bold text-dark">Teks belum di-generate</h6>
                            <p class="small text-muted mb-0">Pilih platform dan kategori lalu klik tombol <strong>Buat Teks Promosi</strong>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================
         TAB 3: SPOTLIGHT REKOMENDASI MITRA HARIAN
         ======================================================== -->
    <div class="tab-pane fade" id="content-spotlight" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-store text-warning"></i>
                            <span>Pilih Mitra yang Ingin Disorot</span>
                        </h5>
                        <p class="text-muted small mb-0">Bantu promosikan mitra aktif agar mereka senang bergabung di platform Anda</p>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <form id="formSpotlight">
                            <input type="hidden" name="action" value="generate_spotlight">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Pilih Mitra dari Database:</label>
                                <select class="form-select fw-semibold" id="spotlightProvider" name="provider_id" required>
                                    <?php foreach ($providers as $prov): ?>
                                        <option value="<?= $prov['id'] ?>">
                                            <?= e($prov['business_name']) ?> (<?= e($prov['category_name']) ?> - <?= e($prov['district_name'] ?: 'Inhu') ?>) ⭐ <?= number_format($prov['rating_avg'], 1) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" id="btnGenSpotlight" class="btn btn-warning text-dark w-100 py-2.5 fw-bold shadow-xs">
                                <i class="fa-solid fa-star me-1.5"></i> Buat Spotlight Promosi Mitra
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-bullseye text-warning"></i>
                            <span>Hasil Spotlight Mitra</span>
                        </h5>
                        <button type="button" id="btnCopySpotlight" class="btn btn-xs btn-outline-warning text-dark d-none py-1 px-2.5 fw-semibold" style="font-size: 0.76rem;" onclick="copySpotlightText()">
                            <i class="fa-regular fa-copy me-1"></i> Salin Spotlight
                        </button>
                    </div>
                    <div class="card-body p-4 pt-1" id="boxSpotlightOutput">
                        <div class="text-center py-5 text-muted">
                            <div class="fs-1 text-warning opacity-50 mb-2"><i class="fa-solid fa-certificate"></i></div>
                            <h6 class="fw-bold text-dark">Pilih mitra di sebelah kiri</h6>
                            <p class="small text-muted mb-0">AI akan merangkai ulasan dan keunggulan mitra tersebut agar warga sekitar tertarik order.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab 1: Generate TikTok Script
document.getElementById('formTikTok').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGenTikTok');
    const box = document.getElementById('boxScriptOutput');
    const origHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5"></span> Sedang Menulis Script Viral...';
    box.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-teal mb-2"></div><p class="small text-muted mb-0">AI sedang merancang hook 3 detik dan alur adegan video...</p></div>';

    try {
        const formData = new FormData(this);
        const resp = await fetch('<?= BASE_URL ?>/admin/ai_studio.php', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (data.status === 'success') {
            document.getElementById('btnCopyScript').classList.remove('d-none');
            
            let scenesHtml = '';
            if (data.scenes && data.scenes.length > 0) {
                data.scenes.forEach(s => {
                    scenesHtml += `
                        <div class="p-3 mb-2 bg-light rounded-3 border">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge text-bg-dark" style="font-size: 0.68rem;">Adegan ${s.scene}</span>
                                <small class="text-muted"><i class="fa-solid fa-camera me-1"></i> Visual: ${escapeHtml(s.visual)}</small>
                            </div>
                            <div class="small fw-semibold text-dark ps-2 border-start border-3 border-teal">
                                💬 Voiceover: "${escapeHtml(s.voiceover)}"
                            </div>
                        </div>
                    `;
                });
            }

            box.innerHTML = `
                <div class="mb-3">
                    <span class="badge bg-teal-subtle text-teal border border-teal-subtle mb-1.5" style="font-size: 0.7rem;">
                        ${escapeHtml(data.engine || 'AI Engine')}
                    </span>
                    <h6 class="fw-bold text-dark mb-0">${escapeHtml(data.title || 'Script Video')}</h6>
                </div>

                <!-- Hook 3 Detik -->
                <div class="p-3 bg-warning-subtle rounded-3 border border-warning-subtle mb-3">
                    <div class="d-flex align-items-center gap-1.5 text-dark fw-bold small mb-1">
                        <i class="fa-solid fa-fire text-danger"></i>
                        <span>HOOK 3 DETIK PERTAMA (Penentu Viral / Anti Skip):</span>
                    </div>
                    <div class="fw-bold text-dark fs-6 mb-1">"${escapeHtml(data.hook)}"</div>
                    <small class="text-secondary"><i class="fa-solid fa-video me-1"></i> Arah Visual Kamera: ${escapeHtml(data.visual_hook || '')}</small>
                </div>

                <!-- Scenes -->
                <div class="mb-3">
                    <label class="small fw-bold text-dark mb-2">Alur Adegan & Dialog Narasi:</label>
                    ${scenesHtml}
                </div>

                <!-- CTA -->
                <div class="p-2.5 bg-light rounded-3 border mb-3 small">
                    <strong class="text-dark"><i class="fa-solid fa-bullhorn text-teal me-1"></i> Call to Action (Penutup):</strong>
                    <div class="text-secondary">${escapeHtml(data.call_to_action || '')}</div>
                </div>

                <!-- Caption & Hashtag -->
                <div class="p-3 bg-dark text-white rounded-3 small">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong class="text-warning"><i class="fa-solid fa-hashtag me-1"></i> Teks Caption & Hashtag:</strong>
                        <button type="button" class="btn btn-xs btn-outline-light py-0.5 px-2" style="font-size: 0.7rem;" onclick="copyToClipboard('${escapeJs(data.caption + '\\n\\n' + data.hashtags)}')">
                            Salin Caption
                        </button>
                    </div>
                    <p class="mb-2 text-white-50" style="white-space: pre-line;">${escapeHtml(data.caption || '')}</p>
                    <span class="text-teal font-monospace" style="font-size: 0.78rem;">${escapeHtml(data.hashtags || '')}</span>
                </div>
            `;
            window.currentScriptData = data;
        } else {
            box.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.message || 'Gagal membuat script')}</div>`;
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        box.innerHTML = `<div class="alert alert-danger">Terjadi kendala koneksi saat menghubungi generator AI.</div>`;
    }
});

// Tab 2: Generate Social Copy
document.getElementById('formSocial').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGenSocial');
    const box = document.getElementById('boxSocialOutput');
    const origHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5"></span> Merangkai Teks Persuasif...';
    box.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-teal mb-2"></div><p class="small text-muted mb-0">AI sedang menulis teks promosi yang menarik...</p></div>';

    try {
        const formData = new FormData(this);
        const resp = await fetch('<?= BASE_URL ?>/admin/ai_studio.php', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (data.status === 'success') {
            document.getElementById('btnCopySocial').classList.remove('d-none');
            window.currentSocialCopy = data.copy;

            const selectedCat = document.getElementById('socCategory').value;
            const selectedDis = document.getElementById('socDistrict').value;
            const waShareUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(data.copy);

            box.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-teal-subtle text-teal border border-teal-subtle" style="font-size: 0.7rem;">
                        ${escapeHtml(data.engine || 'AI Engine')}
                    </span>
                    <a href="${waShareUrl}" target="_blank" class="btn btn-xs btn-outline-secondary py-1 px-2.5 fw-semibold" style="font-size: 0.74rem;">
                        <i class="fa-brands fa-whatsapp me-1 text-success"></i> Buka WA Manual
                    </a>
                </div>
                <div class="p-3 output-code-box rounded-3 mb-3" style="white-space: pre-wrap; line-height: 1.6;" id="textSocialPreview">${escapeHtml(data.copy)}</div>

                <!-- Panel Kirim Otomatis via Bot WA Gateway -->
                <div class="card border border-success-subtle bg-success bg-opacity-10 rounded-3 p-3">
                    <div class="d-flex align-items-center gap-2 mb-1.5">
                        <i class="fa-brands fa-whatsapp text-success fs-5"></i>
                        <h6 class="fw-bold text-dark mb-0">Robot WA: Kirim Otomatis (Tanpa Buka WhatsApp)</h6>
                    </div>
                    <p class="small text-muted mb-2.5" style="font-size: 0.8rem;">
                        Pilih ke mana bot AI harus menembakkan pesan ini secara langsung:
                    </p>

                    <div class="bg-white p-2.5 rounded-2 border mb-2.5">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="sendTargetType" id="targetAdmin" value="admin" checked onchange="toggleTargetInput()">
                            <label class="form-check-label small fw-bold text-dark" for="targetAdmin">
                                Kirim ke Nomor WA Saya Sendiri (<code><?= e($admin_wa) ?></code>)
                                <span class="badge text-bg-success ms-1" style="font-size: 0.65rem;">Instan</span>
                            </label>
                            <div class="text-muted" style="font-size: 0.72rem;">Bot akan mengirimkan teks ini ke WhatsApp Anda, sehingga Anda tinggal buat status / forward.</div>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="sendTargetType" id="targetCustom" value="custom" onchange="toggleTargetInput()">
                            <label class="form-check-label small fw-bold text-dark" for="targetCustom">
                                Kirim ke Nomor Lain / Nomor HP Mitra / ID Grup Tertentu
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="sendTargetType" id="targetBroadcast" value="broadcast" onchange="toggleTargetInput()">
                            <label class="form-check-label small fw-bold text-dark" for="targetBroadcast">
                                Broadcast Otomatis ke Seluruh Mitra Kategori <strong>${escapeHtml(selectedCat)}</strong> di <strong>${escapeHtml(selectedDis)}</strong>
                            </label>
                            <div class="text-muted" style="font-size: 0.72rem;">Bot akan mengirim pesan satu per satu ke HP mitra dengan jeda anti-spam.</div>
                        </div>
                    </div>

                    <div id="customTargetBox" class="mb-2.5 d-none">
                        <label class="form-label small fw-bold text-dark mb-1">Ketik Nomor HP WhatsApp Tujuan:</label>
                        <input type="text" class="form-control form-control-sm" id="inputCustomPhone" placeholder="Contoh: 081234567890 atau 6281234567890">
                    </div>

                    <button type="button" id="btnAutoSendSocial" class="btn btn-success w-100 py-2 fw-bold shadow-xs" onclick="executeAutoSendSocial()">
                        <i class="fa-solid fa-paper-plane me-1.5"></i> Kirim Otomatis Sekarang via Bot WA
                    </button>
                </div>
            `;
        } else {
            box.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.message || 'Gagal')}</div>`;
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        box.innerHTML = `<div class="alert alert-danger">Kendala koneksi. Silakan coba kembali.</div>`;
    }
});

// Tab 3: Generate Spotlight
document.getElementById('formSpotlight').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnGenSpotlight');
    const box = document.getElementById('boxSpotlightOutput');
    const origHtml = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5"></span> Menyiapkan Spotlight...';
    box.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-warning mb-2"></div><p class="small text-muted mb-0">AI sedang mengekstrak profil mitra dan ulasan pelanggan...</p></div>';

    try {
        const formData = new FormData(this);
        const resp = await fetch('<?= BASE_URL ?>/admin/ai_studio.php', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (data.status === 'success') {
            document.getElementById('btnCopySpotlight').classList.remove('d-none');
            window.currentSpotlightCopy = data.copy;

            const prov = data.provider || {};
            let cleanPhone = (prov.phone || '').replace(/[^0-9]/g, '');
            if (cleanPhone.startsWith('08')) cleanPhone = '628' + cleanPhone.substring(2);

            const sendToMitraUrl = cleanPhone ? 'https://wa.me/' + cleanPhone + '?text=' + encodeURIComponent("Halo " + (prov.business_name || 'Mitra') + ", selamat! Toko Anda hari ini terpilih sebagai Spotlight Rekomendasi di media sosial Jasa Inhu! Berikut materi postingannya:\n\n" + data.copy) : null;

            box.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <span class="badge text-bg-warning text-dark px-2.5 py-1" style="font-size: 0.7rem;">
                        <i class="fa-solid fa-star me-1"></i> Spotlight Terpilih
                    </span>
                    <div class="d-flex gap-2">
                        ${cleanPhone ? `
                            <button type="button" class="btn btn-xs btn-success py-1 px-2.5 fw-bold" style="font-size: 0.74rem;" onclick="sendSpotlightAutoViaGateway('${cleanPhone}', '${escapeJs(prov.business_name || 'Mitra')}')">
                                <i class="fa-solid fa-paper-plane me-1"></i> Kirim Otomatis ke WA Mitra
                            </button>
                        ` : ''}
                        ${sendToMitraUrl ? `
                            <a href="${sendToMitraUrl}" target="_blank" class="btn btn-xs btn-outline-secondary py-1 px-2 fw-semibold" style="font-size: 0.72rem;">
                                <i class="fa-brands fa-whatsapp me-1 text-success"></i> Buka WA Web
                            </a>
                        ` : ''}
                    </div>
                </div>
                <div class="p-3 output-code-box rounded-3 mb-2" style="white-space: pre-wrap; line-height: 1.6;">${escapeHtml(data.copy)}</div>
                <div class="small text-muted fst-italic" style="font-size: 0.78rem;">
                    <i class="fa-solid fa-circle-check text-success me-1"></i> Tombol <strong>"Kirim Otomatis ke WA Mitra"</strong> akan langsung menembakkan pesan ucapan selamat & materi promosi ini ke WhatsApp pemilik toko (<code>${cleanPhone || 'Nomor HP tidak ada'}</code>) via Bot Gateway tanpa perlu membuka WhatsApp Web.
                </div>
            `;
        } else {
            box.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.message || 'Gagal')}</div>`;
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        box.innerHTML = `<div class="alert alert-danger">Kendala koneksi.</div>`;
    }
});

// Helper Function: Kirim Otomatis Social Copy
async function executeAutoSendSocial() {
    if (!window.currentSocialCopy) {
        Swal.fire('Peringatan', 'Silakan buat teks promosi terlebih dahulu.', 'warning');
        return;
    }

    const targetType = document.querySelector('input[name="sendTargetType"]:checked').value;
    const btn = document.getElementById('btnAutoSendSocial');
    const origHtml = btn.innerHTML;

    let targetPhone = '';
    let isBroadcast = false;

    if (targetType === 'admin') {
        targetPhone = '<?= e($admin_wa) ?>';
    } else if (targetType === 'custom') {
        targetPhone = document.getElementById('inputCustomPhone').value.trim();
        if (!targetPhone) {
            Swal.fire('Peringatan', 'Masukkan nomor WhatsApp tujuan terlebih dahulu.', 'warning');
            return;
        }
    } else if (targetType === 'broadcast') {
        isBroadcast = true;
    }

    if (isBroadcast) {
        const cat = document.getElementById('socCategory').value;
        const dis = document.getElementById('socDistrict').value;

        const confirm = await Swal.fire({
            title: 'Mulai Broadcast Mitra?',
            html: `Bot WA akan mengirimkan teks promosi ini satu per satu ke seluruh mitra kategori <b>${cat}</b> di wilayah <b>${dis}</b>.<br><br><small class="text-muted">Proses berjalan otomatis di latar belakang dengan jeda aman anti-spam.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Kirim Sekarang',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#198754'
        });

        if (!confirm.isConfirmed) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5"></span> Sedang Mengirim Broadcast...';

        try {
            const formData = new FormData();
            formData.append('action', 'broadcast_to_partners');
            formData.append('district', dis);
            formData.append('category', cat);
            formData.append('message', window.currentSocialCopy);

            const resp = await fetch('<?= BASE_URL ?>/admin/ai_studio.php', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();
            btn.disabled = false;
            btn.innerHTML = origHtml;

            if (res.status === 'success') {
                Swal.fire('Sukses Terkirim!', res.message, 'success');
            } else {
                Swal.fire('Gagal', res.message, 'error');
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            Swal.fire('Error', 'Kendala koneksi ke server.', 'error');
        }
    } else {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1.5"></span> Mengirim ke WhatsApp...';

        try {
            const formData = new FormData();
            formData.append('action', 'send_wa_direct');
            formData.append('target', targetPhone);
            formData.append('message', window.currentSocialCopy);

            const resp = await fetch('<?= BASE_URL ?>/admin/ai_studio.php', {
                method: 'POST',
                body: formData
            });
            const res = await resp.json();
            btn.disabled = false;
            btn.innerHTML = origHtml;

            if (res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Pesan Berhasil Terkirim!',
                    text: `Robot AI berhasil menembak pesan ke nomor ${targetPhone} via WhatsApp Gateway Fonnte.`,
                    timer: 3500
                });
            } else {
                Swal.fire('Gagal Mengirim', res.message, 'error');
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            Swal.fire('Error', 'Kendala koneksi ke server.', 'error');
        }
    }
}

function toggleTargetInput() {
    const custom = document.getElementById('targetCustom');
    const box = document.getElementById('customTargetBox');
    if (custom && custom.checked) {
        box.classList.remove('d-none');
    } else {
        box.classList.add('d-none');
    }
}

async function sendSpotlightAutoViaGateway(phone, businessName) {
    if (!window.currentSpotlightCopy) {
        Swal.fire('Peringatan', 'Silakan generate spotlight terlebih dahulu.', 'warning');
        return;
    }

    const confirm = await Swal.fire({
        title: 'Kirim ke WA Mitra?',
        html: `Kirim materi spotlight ini langsung ke nomor WhatsApp mitra <b>${escapeHtml(businessName)}</b> (${phone})?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Kirim Otomatis',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#198754'
    });

    if (!confirm.isConfirmed) return;

    Swal.fire({
        title: 'Mengirim...',
        text: 'Robot WhatsApp sedang mengirimkan pesan ke HP mitra...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const formData = new FormData();
        formData.append('action', 'send_wa_direct');
        formData.append('target', phone);
        formData.append('message', "Halo *" + businessName + "*,\n\nSelamat! Usaha Anda hari ini terpilih sebagai Spotlight Rekomendasi di platform JASA INHU! 🌟\n\nBerikut materi promosinya:\n\n" + window.currentSpotlightCopy);

        const resp = await fetch('<?= BASE_URL ?>/admin/ai_studio.php', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.status === 'success') {
            Swal.fire('Berhasil Terkirim!', `Materi spotlight berhasil dikirim langsung ke WhatsApp ${businessName}.`, 'success');
        } else {
            Swal.fire('Gagal Mengirim', res.message, 'error');
        }
    } catch (err) {
        Swal.fire('Error', 'Kendala koneksi ke server.', 'error');
    }
}

// Helper Clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Disalin!',
                text: 'Teks sudah tersimpan di clipboard, siap ditempel ke TikTok / WhatsApp / Facebook.',
                timer: 1800,
                showConfirmButton: false
            });
        } else {
            alert('Teks berhasil disalin!');
        }
    });
}

function copySocialText() {
    if (window.currentSocialCopy) {
        copyToClipboard(window.currentSocialCopy);
    }
}

function copySpotlightText() {
    if (window.currentSpotlightCopy) {
        copyToClipboard(window.currentSpotlightCopy);
    }
}

function copyScriptContent() {
    if (window.currentScriptData) {
        const d = window.currentScriptData;
        let full = `JUDUL: ${d.title}\n\nHOOK 3 DETIK: "${d.hook}"\nVISUAL: ${d.visual_hook || ''}\n\nALUR ADEGAN:\n`;
        if (d.scenes) {
            d.scenes.forEach(s => {
                full += `Adegan ${s.scene} (Visual: ${s.visual}) -> Dialog: "${s.voiceover}"\n`;
            });
        }
        full += `\nCTA: ${d.call_to_action || ''}\n\nCAPTION & HASHTAGS:\n${d.caption || ''}\n${d.hashtags || ''}`;
        copyToClipboard(full);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escapeJs(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

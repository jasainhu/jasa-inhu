<?php
/**
 * Pemesanan Jasa Langsung ke Penyedia Tertentu (Direct Order)
 * JASA INHU
 */

$page_title = 'Pesan Jasa Langsung';

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Wajib login untuk membuat pesanan langsung
$provider_id = (int)($_GET['provider_id'] ?? 0);
if ($provider_id <= 0) {
    set_flash('danger', 'Penyedia jasa tidak valid.');
    redirect('/');
}

if (!is_logged_in()) {
    set_flash('info', 'Silakan masuk atau daftar terlebih dahulu untuk memesan penyedia jasa ini.');
    redirect('/login.php?redirect=' . urlencode('/order.php?provider_id=' . $provider_id));
}

$user = current_user();

// Perlindungan Anti-Order Fiktif: Pengguna wajib memverifikasi email/akun sebelum membuat pesanan langsung
if (empty($user['email_verified_at']) && ($user['role_name'] ?? '') !== 'admin') {
    set_flash('warning', 'Demi mencegah pesanan fiktif dan melindungi teknisi lokal kami, silakan selesaikan verifikasi akun Gmail Anda terlebih dahulu.');
    redirect('/verify.php?redirect=' . urlencode('/order.php?provider_id=' . $provider_id));
}

$db = get_db();
$error = '';

// Ambil profil penyedia jasa yang dipilih
$stmtProv = $db->prepare("
    SELECT sp.*, u.name as owner_name, u.phone as provider_phone, u.email as provider_email,
           sc.name as category_name, sc.icon as category_icon,
           d.name as district_name, v.name as village_name
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    LEFT JOIN villages v ON sp.village_id = v.id
    WHERE sp.id = ? AND u.is_active = 1
    LIMIT 1
");
$stmtProv->execute([$provider_id]);
$provider = $stmtProv->fetch();

$stmtPort = $db->prepare("SELECT * FROM provider_portfolios WHERE provider_id = ? ORDER BY id DESC LIMIT 4");
$stmtPort->execute([$provider_id]);
$provider_portfolios = $stmtPort->fetchAll();

if (!$provider) {
    set_flash('danger', 'Penyedia jasa yang Anda pilih tidak ditemukan atau sedang tidak aktif.');
    redirect('/');
}

// Cegah memesan ke diri sendiri jika login sebagai akun yang sama
if ((int)$provider['user_id'] === (int)$user['id']) {
    set_flash('warning', 'Anda tidak dapat memesan jasa ke akun usaha Anda sendiri.');
    redirect('/');
}

// Handle pengiriman pesanan langsung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $district_id = (int)($_POST['district_id'] ?? 0);
        $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
        $address_detail = trim($_POST['address_detail'] ?? '');
        $budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;
        $urgency = in_array($_POST['urgency'] ?? '', ['normal', 'urgent', 'scheduled']) ? $_POST['urgency'] : 'normal';
        $preferred_time = trim($_POST['preferred_time'] ?? '');

        // Smart fallback: jika pengguna hanya mengisi judul atau memilih chip masalah
        if (empty($description) && !empty($title)) {
            $description = "Kebutuhan jasa: " . $title;
        }
        if (empty($title) && !empty($description)) {
            $title = mb_substr($description, 0, 70);
        }

        if (!empty($preferred_time)) {
            $description .= "\n\nWaktu yang diinginkan pemohon: " . $preferred_time;
        }

        if (empty($title) || empty($district_id)) {
            $error = 'Judul / jenis masalah dan kecamatan wajib diisi.';
        } else {
            try {
                $db->beginTransaction();

                // 1. Simpan ke service_requests dengan provider_id terkunci ke penyedia terpilih
                $stmtIns = $db->prepare("
                    INSERT INTO service_requests 
                    (user_id, category_id, provider_id, district_id, village_id, title, description, budget, address_detail, urgency, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
                ");
                $stmtIns->execute([
                    $user['id'], $provider['primary_category_id'], $provider['id'],
                    $district_id, $village_id, $title, $description, $budget, $address_detail, $urgency
                ]);
                $request_id = (int)$db->lastInsertId();

                // 2. Catat ke Timeline Garis Waktu Pesanan
                add_order_timeline_event(
                    $request_id,
                    'created',
                    'Pesanan Jasa Dibuat & Menunggu Respon Mitra',
                    "Pelanggan {$user['name']} memesan jasa: '{$title}'. Menunggu respon ketersediaan dari mitra.",
                    'customer'
                );

                // 3. Beri notifikasi ke akun penyedia jasa
                $notifTitle = "Pesanan Jasa Baru Langsung Masuk!";
                $notifMsg = "Warga " . $user['name'] . " telah memesan jasa langsung ke usaha Anda untuk: '" . $title . "'.";
                $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmtNotif->execute([$provider['user_id'], $notifTitle, $notifMsg, '/provider/index.php']);

                // 4. Kirim notifikasi WhatsApp otomatis ke HP Mitra jika nomor tersedia
                if (!empty($provider['provider_phone'])) {
                    $waMsg = "🔔 *PESANAN JASA BARU MASUK!* (No: #{$request_id})\n\n" .
                             "Halo *{$provider['business_name']}*, warga atas nama *{$user['name']}* memesan jasa Anda untuk:\n" .
                             "📌 *Pekerjaan*: {$title}\n" .
                             "📍 *Alamat*: " . ($address_detail ?: 'Sesuai kesepakatan') . "\n\n" .
                             "Segera buka dashboard mitra Jasa Inhu untuk menanggapi: " . BASE_URL . "/provider/index.php";
                    send_wa_notification($provider['provider_phone'], $waMsg, 'new_order', $request_id, $provider['business_name']);
                }

                $db->commit();

                set_flash('success', "Pesanan jasa Anda berhasil dikirim langsung ke '{$provider['business_name']}'! Silakan hubungi via WhatsApp untuk konfirmasi cepat.");
                redirect('/user/requests.php?id=' . $request_id . '&new=1');
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Gagal mengirim pesanan: ' . $e->getMessage();
            }
        }
    }
}

$districts = get_all_districts();

// Preset chip masalah sesuai kategori mitra
$catNameLower = strtolower($provider['category_name'] ?? '');
$quick_problems = [];
if (str_contains($catNameLower, 'ac') || str_contains($catNameLower, 'elektronik') || str_contains($catNameLower, 'kulkas')) {
    $quick_problems = ['❄️ AC Tidak Dingin', '💧 AC Bocor Air', '🧼 Cuci AC Rutin', '⚡ Isi Freon', '🔌 Kulkas / Mesin Cuci'];
} elseif (str_contains($catNameLower, 'motor') || str_contains($catNameLower, 'otomotif') || str_contains($catNameLower, 'montir')) {
    $quick_problems = ['🔧 Servis & Ganti Oli', '🔋 Motor Mogok / Mati', '🛞 Ganti / Tambal Ban', '⚡ Kelistrikan & Lampu'];
} elseif (str_contains($catNameLower, 'bangun') || str_contains($catNameLower, 'renov') || str_contains($catNameLower, 'tukang')) {
    $quick_problems = ['🧱 Renovasi Rumah', '💧 Atap / Seng Bocor', '🎨 Cat Dinding', '🔨 Tukang Harian'];
} elseif (str_contains($catNameLower, 'kebun') || str_contains($catNameLower, 'sawit')) {
    $quick_problems = ['🌴 Regu Panen TBS', '🌾 Babat Rumput', '🚜 Angkut Sawit', '🌱 Rawat / Pupuk'];
} else {
    $quick_problems = ['⚡ Panggilan Cepat', '🛠️ Perbaikan / Servis', '🔍 Cek Kondisi & Biaya', '📦 Bantuan Kerja'];
}

$hide_floating_wa = true; // Sembunyikan WA admin agar layar tidak padat

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Modern Compact Order Form Styles */
.order-container {
    max-width: 640px;
    margin: 0 auto;
    padding-bottom: 90px;
}
.order-provider-card {
    background: linear-gradient(135deg, #064e3b 0%, #0d9488 100%);
    border-radius: 16px;
    color: #fff;
    box-shadow: 0 4px 20px rgba(13, 148, 136, 0.15);
}
.order-urgency-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.urgency-pill {
    cursor: pointer;
    margin-bottom: 0;
}
.urgency-pill input {
    display: none;
}
.urgency-box {
    border: 1.5px solid #e2e8f0;
    background: #fff;
    border-radius: 12px;
    padding: 8px 6px;
    text-align: center;
    transition: all 0.2s ease;
}
.urgency-box .pill-title {
    font-size: 0.8rem;
    font-weight: 700;
    display: block;
    color: #334155;
}
.urgency-box .pill-sub {
    font-size: 0.68rem;
    color: #64748b;
    display: block;
}
.urgency-pill input:checked + .urgency-box {
    border-color: #0d9488;
    background: #f0fdfa;
    box-shadow: 0 2px 8px rgba(13, 148, 136, 0.15);
}
.urgency-pill input:checked + .urgency-box .pill-title {
    color: #0f766e;
}
.chip-btn {
    font-size: 0.74rem;
    padding: 4px 10px;
    border-radius: 20px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.chip-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.chip-btn.active {
    background: #0d9488;
    border-color: #0d9488;
    color: #fff;
    font-weight: 600;
}
.order-form-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}
.section-tag {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #0d9488;
    background: #f0fdfa;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-block;
}
</style>

<div class="container py-2 order-container">
    <!-- Top Back Navigation Bar -->
    <div class="d-flex justify-content-between align-items-center mb-2 px-1">
        <a href="<?= BASE_URL ?>/" class="btn btn-sm btn-light border text-muted py-1 px-2.5 rounded-pill" style="font-size: 0.78rem;">
            <i class="fa-solid fa-arrow-left me-1"></i> Beranda
        </a>
        <span class="badge bg-teal-subtle text-teal fw-semibold" style="font-size: 0.72rem;">
            <i class="fa-solid fa-bolt text-warning me-1"></i> Pesanan Langsung
        </span>
    </div>

    <!-- 1. Header Ringkas Mitra (Hemat 60% Tempat) -->
    <div class="order-provider-card mb-3 p-3 position-relative overflow-hidden">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2.5" style="min-width: 0;">
                <div class="position-relative flex-shrink-0">
                    <?php $prov_cover_url = get_provider_cover_url($provider); ?>
                    <?php if (!empty($prov_cover_url)): ?>
                        <img src="<?= e($prov_cover_url) ?>" alt="<?= e($provider['business_name']) ?>" class="rounded-3 shadow-sm object-fit-cover" style="width: 46px; height: 46px; border: 2px solid rgba(255,255,255,0.4);">
                    <?php else: ?>
                        <div class="rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 46px; height: 46px; background: rgba(255,255,255,0.2); font-size: 1.25rem;">
                            <i class="fa-solid <?= e($provider['category_icon']) ?>"></i>
                        </div>
                    <?php endif; ?>
                    <?php if ($provider['is_verified']): ?>
                        <span class="position-absolute bottom-0 end-0 bg-success text-white rounded-circle d-flex align-items-center justify-content-center border border-white" style="width: 16px; height: 16px; font-size: 0.55rem;" title="Terverifikasi">
                            <i class="fa-solid fa-check"></i>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="text-truncate">
                    <div class="d-flex align-items-center gap-1.5 mb-0.5">
                        <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.62rem; padding: 2px 6px;">Mitra Dipilih</span>
                        <?php if ($provider['is_verified']): ?>
                            <span class="badge bg-success small text-white" style="font-size: 0.62rem; padding: 2px 6px;">Resmi</span>
                        <?php endif; ?>
                    </div>
                    <h6 class="fw-bold mb-0 text-white text-truncate" style="font-size: 0.95rem;"><?= e($provider['business_name']) ?></h6>
                    <div class="small opacity-90 text-light text-truncate" style="font-size: 0.72rem;">
                        <?= e($provider['category_name']) ?> &bull; Kec. <?= e($provider['district_name'] ?: 'Kab. Inhu') ?>
                    </div>
                </div>
            </div>

            <div class="text-end flex-shrink-0">
                <div class="small opacity-75" style="font-size: 0.65rem;">Estimasi Jasa</div>
                <div class="fw-bold text-warning" style="font-size: 0.88rem;">
                    <?= format_rupiah($provider['hourly_rate_min']) ?>
                </div>
                <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $provider['id'] ?>" class="btn btn-sm btn-light py-0 px-2 mt-0.5 rounded-pill fw-semibold" style="font-size: 0.65rem; color: #0f766e;">
                    Profil <i class="fa-solid fa-arrow-right" style="font-size: 0.6rem;"></i>
                </a>
            </div>
        </div>

        <!-- Strip Portofolio Mini Horizontal -->
        <?php if (!empty($provider_portfolios)): ?>
            <div class="mt-2.5 pt-2 border-top border-white border-opacity-15 d-flex align-items-center gap-2 overflow-x-auto" style="scrollbar-width: none;">
                <span class="small opacity-80 flex-shrink-0" style="font-size: 0.65rem;">
                    <i class="fa-solid fa-camera me-1"></i> Bukti Kerja (<?= count($provider_portfolios) ?>):
                </span>
                <?php foreach ($provider_portfolios as $pp): ?>
                    <a href="<?= e(get_portfolio_image_url($pp, 'after')) ?>" target="_blank" class="flex-shrink-0 rounded-2 overflow-hidden border border-white border-opacity-30" style="width: 40px; height: 28px;" title="<?= e($pp['title']) ?>">
                        <img src="<?= e(get_portfolio_image_url($pp, 'after')) ?>" alt="<?= e($pp['title']) ?>" class="w-100 h-100 object-fit-cover" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-3 rounded-3">
            <i class="fa-solid fa-triangle-exclamation me-2 fs-6"></i>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- 2. Formulir Modern Berdesain Kompak -->
    <div class="order-form-card p-3 p-sm-4">
        <form method="POST" action="<?= BASE_URL ?>/order.php?provider_id=<?= $provider['id'] ?>" id="orderForm">
            <?= csrf_field() ?>

            <!-- A. Bagian Kebutuhan & Masalah -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1.5">
                    <label class="form-label small fw-bold text-dark mb-0">
                        <i class="fa-solid fa-wrench text-teal me-1"></i> Masalah / Jasa yang Dibutuhkan <span class="text-danger">*</span>
                    </label>
                    <span class="text-muted" style="font-size: 0.7rem;">Pilih cepat:</span>
                </div>

                <!-- Chips Cepat Sesuai Kategori -->
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <?php foreach ($quick_problems as $qp): 
                        $cleanText = preg_replace('/^[^\p{L}\p{N}]+/u', '', $qp);
                    ?>
                        <button type="button" class="chip-btn problem-chip" data-val="<?= e($cleanText) ?>">
                            <?= e($qp) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <input type="text" name="title" id="order_title" class="form-control rounded-3 py-2 fw-semibold" placeholder="Contoh: Butuh Servis Kulkas 2 Pintu / AC Bocor" required autofocus style="font-size: 0.9rem;">
            </div>

            <!-- Detail Kerusakan (Kompak 2 Baris) -->
            <div class="mb-3">
                <label class="form-label small fw-semibold text-muted mb-1">
                    <i class="fa-regular fa-comment-dots me-1"></i> Detail Kerusakan / Catatan Tambahan
                </label>
                <textarea name="description" id="order_desc" rows="2" class="form-control rounded-3 small py-2" placeholder="Tuliskan merk barang atau ciri-ciri kerusakan jika ada (opsional)..." style="font-size: 0.85rem;"></textarea>
            </div>

            <!-- B. Tingkat Urgensi (Pill Selector 1-Klik) -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
                    <span><i class="fa-solid fa-bolt text-warning me-1"></i> Kapan Perlu Dikerjakan?</span>
                </label>
                <div class="order-urgency-grid">
                    <label class="urgency-pill">
                        <input type="radio" name="urgency" value="urgent" checked>
                        <div class="urgency-box">
                            <span class="pill-title text-danger"><i class="fa-solid fa-fire me-0.5"></i> Cepat</span>
                            <span class="pill-sub">Hari ini / Segera</span>
                        </div>
                    </label>
                    <label class="urgency-pill">
                        <input type="radio" name="urgency" value="normal">
                        <div class="urgency-box">
                            <span class="pill-title text-teal"><i class="fa-regular fa-clock me-0.5"></i> Santai</span>
                            <span class="pill-sub">1-2 hari ke depan</span>
                        </div>
                    </label>
                    <label class="urgency-pill">
                        <input type="radio" name="urgency" value="scheduled">
                        <div class="urgency-box">
                            <span class="pill-title text-primary"><i class="fa-regular fa-calendar-days me-0.5"></i> Jadwal</span>
                            <span class="pill-sub">Tanggal khusus</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- C. Waktu Kedatangan (Quick Time Chips) -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-dark mb-1">
                    <i class="fa-regular fa-calendar-check text-teal me-1"></i> Jam Kedatangan yang Diinginkan
                </label>
                <div class="d-flex flex-wrap gap-1 mb-1.5">
                    <button type="button" class="chip-btn time-chip" data-time="Pagi (08.00 - 12.00 WIB)">🌅 Pagi</button>
                    <button type="button" class="chip-btn time-chip" data-time="Siang (13.00 - 16.00 WIB)">☀️ Siang</button>
                    <button type="button" class="chip-btn time-chip" data-time="Sore (16.00 - 18.00 WIB)">🌇 Sore</button>
                    <button type="button" class="chip-btn time-chip" data-time="Malam (19.00 - 21.00 WIB)">🌙 Malam</button>
                    <button type="button" class="chip-btn time-chip" data-time="Kapan saja mitra senggang">⏱️ Bebas</button>
                </div>
                <input type="text" name="preferred_time" id="preferred_time_input" class="form-control form-control-sm rounded-3 py-1.5" placeholder="Pilih tombol jam di atas atau ketik jadwal Anda...">
            </div>

            <!-- D. Lokasi (Berdampingan 50% - 50%) -->
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark mb-1">Kecamatan <span class="text-danger">*</span></label>
                    <select name="district_id" id="district_select" class="form-select form-select-sm rounded-3 py-1.5" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= (($user['district_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>>
                                Kec. <?= e($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold text-dark mb-1">Desa / Kelurahan</label>
                    <select name="village_id" id="village_select" class="form-select form-select-sm rounded-3 py-1.5">
                        <option value="<?= $user['village_id'] ?? '' ?>">
                            <?= e($user['village_name'] ?? '-- Pilih Desa --') ?>
                        </option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold text-dark mb-1">Alamat Lengkap / Patokan</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0 rounded-start-3"><i class="fa-solid fa-location-dot text-danger"></i></span>
                    <input type="text" name="address_detail" class="form-control rounded-end-3 py-1.5" placeholder="Nama jalan, RT/RW, atau dekat masjid/toko..." value="<?= e($user['address'] ?? '') ?>">
                </div>
            </div>

            <!-- E. Accordion Lipat (Opsional: Penawaran Anggaran) -->
            <div class="mb-3">
                <a class="d-flex align-items-center justify-content-between p-2 px-3 rounded-3 bg-light text-decoration-none border text-muted small" data-bs-toggle="collapse" href="#optionalFields" role="button" aria-expanded="false">
                    <span><i class="fa-solid fa-sliders text-teal me-1.5"></i> <strong>Atur Penawaran Anggaran (Opsional)</strong></span>
                    <i class="fa-solid fa-chevron-down opacity-50" style="font-size: 0.72rem;"></i>
                </a>
                <div class="collapse mt-2" id="optionalFields">
                    <div class="p-3 bg-light rounded-3 border">
                        <label class="form-label small fw-bold text-dark mb-1">Tawaran Anggaran Anda (Rp)</label>
                        <input type="number" name="budget" class="form-control form-control-sm rounded-3" placeholder="Contoh: 150000 (Kosongkan jika negosiasi di tempat)">
                        <div class="form-text" style="font-size: 0.72rem;">Kosongkan jika ingin disepakati langsung saat mitra memeriksa kerusakan.</div>
                    </div>
                </div>
            </div>

            <!-- F. Notifikasi WhatsApp Bar (Ramping) -->
            <div class="d-flex align-items-center gap-2 p-2 px-3 bg-teal-subtle text-teal-emphasis rounded-3 small mb-3 border border-teal-subtle">
                <i class="fa-brands fa-whatsapp fs-5 text-success flex-shrink-0"></i>
                <div style="font-size: 0.74rem;">
                    Status pesanan & konfirmasi mitra dikirim ke WA Anda: <strong><?= e($user['phone']) ?></strong>
                </div>
            </div>

            <!-- G. Tombol Aksi (Kompak & Modern) -->
            <div class="d-flex align-items-center gap-2 pt-2 border-top">
                <a href="<?= BASE_URL ?>/chat.php?provider_id=<?= $provider['id'] ?>" class="btn btn-outline-secondary rounded-3 py-2 px-3 flex-shrink-0 text-center" title="Chat Dulu">
                    <i class="fa-solid fa-comments"></i> <span class="d-none d-sm-inline ms-1">Tanya Dulu</span>
                </a>
                <button type="submit" class="btn btn-success flex-grow-1 py-2 px-3 rounded-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" style="background: linear-gradient(135deg, #0d9488 0%, #059669 100%); border: none;">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Kirim Pesanan Sekarang</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Chip Cepat Masalah
    const problemChips = document.querySelectorAll('.problem-chip');
    const orderTitle = document.getElementById('order_title');
    
    problemChips.forEach(chip => {
        chip.addEventListener('click', function() {
            problemChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            orderTitle.value = this.getAttribute('data-val') || this.textContent.trim();
            orderTitle.focus();
        });
    });

    // 2. Chip Cepat Waktu
    const timeChips = document.querySelectorAll('.time-chip');
    const timeInput = document.getElementById('preferred_time_input');
    
    timeChips.forEach(chip => {
        chip.addEventListener('click', function() {
            timeChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            timeInput.value = this.getAttribute('data-time') || this.textContent.trim();
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


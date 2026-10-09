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

        if (!empty($preferred_time)) {
            $description .= "\n\nWaktu yang diinginkan pemohon: " . $preferred_time;
        }

        if (empty($title) || empty($description) || empty($district_id)) {
            $error = 'Judul pekerjaan, deskripsi, dan kecamatan wajib diisi.';
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

                $db->commit();

                set_flash('success', "Pesanan jasa Anda berhasil dikirim langsung ke '{$provider['business_name']}'! Silakan hubungi via WhatsApp untuk konfirmasi cepat.");
                redirect('/user/requests.php?id=' . $request_id);
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Gagal mengirim pesanan: ' . $e->getMessage();
            }
        }
    }
}

$districts = get_all_districts();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4" style="max-width: 850px;">
    <!-- Breadcrumb & Back -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="<?= BASE_URL ?>/" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda
        </a>
        <span class="badge text-bg-light border text-muted">
            <i class="fa-solid fa-handshake-simple text-teal me-1"></i> Pesanan Langsung (Direct Order)
        </span>
    </div>

    <!-- Kartu Informasi Penyedia Jasa yang Dipilih -->
    <div class="card border-0 shadow-sm p-4 mb-4 text-white" style="background: linear-gradient(135deg, var(--dark) 0%, #0f766e 100%); border-radius: 16px;">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <?php if (!empty($provider['image_url']) && file_exists(__DIR__ . '/' . $provider['image_url'])): ?>
                    <img src="<?= BASE_URL ?>/<?= e($provider['image_url']) ?>" alt="<?= e($provider['business_name']) ?>" style="width: 68px; height: 68px; object-fit: cover; border-radius: 16px; border: 2px solid rgba(255,255,255,0.3);" class="shadow-sm">
                <?php else: ?>
                    <div class="provider-avatar" style="width: 64px; height: 64px; font-size: 1.6rem; border-radius: 16px; background: rgba(255,255,255,0.15); color: #fff; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid <?= e($provider['category_icon']) ?>"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning text-dark fw-bold small">Penyedia Jasa Pilihan Anda</span>
                        <?php if ($provider['is_verified']): ?>
                            <span class="badge bg-success small"><i class="fa-solid fa-check"></i> Terverifikasi</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="fw-bold mb-1"><?= e($provider['business_name']) ?></h3>
                    <p class="text-light opacity-90 small mb-0">
                        <?= e($provider['category_name']) ?> &bull; 
                        <i class="fa-solid fa-location-dot ms-1 text-warning"></i> Kec. <?= e($provider['district_name'] ?: 'Kab. Inhu') ?>
                    </p>
                </div>
            </div>

            <div class="text-md-end bg-white bg-opacity-10 p-3 rounded-3">
                <div class="small text-light opacity-75">Estimasi Biaya Jasa:</div>
                <div class="fw-bold fs-5 text-warning">
                    <?= format_rupiah($provider['hourly_rate_min']) ?>
                    <?php if ($provider['hourly_rate_max'] > $provider['hourly_rate_min']): ?>
                        - <?= format_rupiah($provider['hourly_rate_max']) ?>
                    <?php endif; ?>
                </div>
                <div class="small opacity-75">Pengalaman: <?= (int)$provider['experience_years'] ?> Tahun</div>
            </div>
    </div>

    <!-- Foto Hasil Kerja Nyata Mitra -->
    <?php if (!empty($provider_portfolios)): ?>
        <div class="card border shadow-sm p-3 mb-4 bg-white rounded-4">
            <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                <span class="fw-bold small text-dark">
                    <i class="fa-solid fa-camera text-teal me-1"></i> Bukti Hasil Kerja Nyata Mitra Ini
                </span>
                <span class="badge bg-teal-subtle text-teal fw-semibold" style="font-size: 0.72rem;"><?= count($provider_portfolios) ?> Hasil Pengerjaan</span>
            </div>
            <div class="row g-2">
                <?php foreach ($provider_portfolios as $pp): ?>
                    <div class="col-6 col-md-3">
                        <div class="border rounded-3 overflow-hidden position-relative shadow-2xs" style="height: 110px; background: #0f172a;">
                            <img src="<?= BASE_URL ?>/<?= e($pp['image_after']) ?>" alt="<?= e($pp['title']) ?>" class="w-100 h-100 object-fit-cover">
                            <div class="position-absolute bottom-0 start-0 end-0 p-1 text-white bg-dark bg-opacity-75 small text-truncate" style="font-size: 0.68rem;">
                                <?= e($pp['title']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- Formulir Pemesanan Langsung -->
    <div class="card border shadow-sm p-4 bg-white" style="border-radius: 16px;">
        <h5 class="fw-bold text-dark mb-1">
            <i class="fa-solid fa-file-signature text-primary me-2"></i> Formulir Pemesanan Jasa
        </h5>
        <p class="text-muted small mb-4">
            Pesanan ini akan langsung dikirimkan khusus kepada <strong><?= e($provider['business_name']) ?></strong>.
        </p>

        <form method="POST" action="<?= BASE_URL ?>/order.php?provider_id=<?= $provider['id'] ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold">Judul Pekerjaan / Masalah yang Dihadapi <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Butuh Servis Kulkas 2 Pintu Bocor / Ganti Ban Motor Mogok" required autofocus>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Tingkat Urgensi</label>
                    <select name="urgency" class="form-select">
                        <option value="normal">Biasa (1-2 hari ke depan)</option>
                        <option value="urgent">Mendesak (Hari ini / Secepatnya)</option>
                        <option value="scheduled">Terjadwal (Tanggal tertentu)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Waktu / Jadwal Kedatangan yang Diinginkan</label>
                    <input type="text" name="preferred_time" class="form-control" placeholder="Contoh: Besok pagi pukul 09.00 WIB">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Kecamatan Lokasi Anda di Inhu <span class="text-danger">*</span></label>
                    <select name="district_id" id="district_select" class="form-select" required>
                        <option value="">-- Pilih Kecamatan --</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= (($user['district_id'] ?? 0) == $d['id']) ? 'selected' : '' ?>>
                                Kec. <?= e($d['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Desa / Kelurahan</label>
                    <select name="village_id" id="village_select" class="form-select">
                        <option value="<?= $user['village_id'] ?? '' ?>">
                            <?= e($user['village_name'] ?? '-- Pilih Desa / Kelurahan --') ?>
                        </option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Alamat Lengkap & Patokan Lokasi</label>
                    <input type="text" name="address_detail" class="form-control" placeholder="Nama jalan, RT/RW, nomor rumah, atau patokan dekat masjid/toko..." value="<?= e($user['address'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Penawaran Anggaran Anda (Rp)</label>
                    <input type="number" name="budget" class="form-control" placeholder="Contoh: 150000 (Kosongkan jika negosiasi di tempat)">
                    <div class="form-text" style="font-size: 0.72rem;">Opsional. Boleh dikosongkan untuk disepakati langsung.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Nomor Kontak WhatsApp Anda</label>
                    <input type="text" class="form-control bg-light" value="<?= e($user['phone']) ?>" readonly>
                    <div class="form-text" style="font-size: 0.72rem;">Penyedia jasa akan menghubungi Anda via WhatsApp ke nomor ini.</div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Deskripsi Lengkap Kebutuhan / Kerusakan <span class="text-danger">*</span></label>
                    <textarea name="description" rows="4" class="form-control" placeholder="Jelaskan jenis barang/alat, gejala kerusakan, atau detail bantuan yang Anda butuhkan secara jelas..." required></textarea>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">
                <a href="<?= BASE_URL ?>/chat.php?provider_id=<?= $provider['id'] ?>" class="btn btn-outline-teal">
                    <i class="fa-solid fa-comments me-1"></i> Tanya-tanya Dulu di Obrolan
                </a>

                <button type="submit" class="btn btn-primary-custom px-4 py-2">
                    <i class="fa-solid fa-paper-plane me-1"></i> Kirim Pesanan Langsung
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

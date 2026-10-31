<?php
/**
 * Pusat Keamanan & Panduan Kerja Aman Mitra
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');

$page_title = 'Pusat Keamanan & Panduan Kerja Aman Mitra';
$active_nav = 'provider_dashboard';

$user = current_user();
$db = get_db();

// Ambil info mitra
$stmt = $db->prepare("SELECT * FROM service_providers WHERE user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$provider = $stmt->fetch();

$admin_wa_phone = defined('ADMIN_PHONE_WA') ? ADMIN_PHONE_WA : '6285126241679';
$admin_sos_message = "Halo Admin JASA INHU, saya " . ($provider['business_name'] ?? $user['name']) . " (Mitra #" . ($provider['id'] ?? $user['id']) . ") butuh bantuan darurat / mediasi terkait pelanggan di lapangan. Mohon arahannya.";
$admin_sos_url = "https://wa.me/" . $admin_wa_phone . "?text=" . urlencode($admin_sos_message);

$hide_ai_widget = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-light py-2 py-md-3 border-bottom mb-4">
    <div class="container d-flex align-items-center justify-content-between">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/provider/index.php" class="text-decoration-none text-muted"><i class="fa-solid fa-gauge me-1"></i> Dashboard Mitra</a></li>
                <li class="breadcrumb-item active fw-bold text-dark" aria-current="page">Pusat Keamanan Mitra</li>
            </ol>
        </nav>
        <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1 fw-semibold">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
        </a>
    </div>
</div>

<div class="container pb-5">

    <!-- Hero Banner Keamanan -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="background: linear-gradient(135deg, #092c28 0%, #064e3b 50%, #0d9488 100%);">
        <div class="card-body p-4 p-md-5 text-white position-relative">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-1.5 rounded-pill mb-2 shadow-xs">
                        <i class="fa-solid fa-shield-halved me-1"></i> Perlindungan Kerja Mitra JASA INHU
                    </span>
                    <h1 class="fw-bold fs-2 text-white mb-2">Panduan Kerja Aman & Anti Penipuan</h1>
                    <p class="text-white-50 mb-3" style="max-width: 620px;">
                        Keselamatan dan kenyamanan Anda sebagai teknisi serta pekerja jasa lokal di Kabupaten Indragiri Hulu adalah prioritas utama kami. Pelajari SOP perlindungan diri dan penanganan pelanggan nakal di bawah ini.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= $admin_sos_url ?>" target="_blank" class="btn btn-danger btn-sm fw-bold px-3 py-2 rounded-pill shadow-xs d-inline-flex align-items-center gap-1.5">
                            <i class="fa-brands fa-whatsapp fs-6"></i> Bantuan Darurat Hotline Admin Jasa Inhu ↗
                        </a>
                        <a href="#sop-points" class="btn btn-white bg-white text-teal btn-sm fw-bold px-3 py-2 rounded-pill shadow-xs">
                            <i class="fa-solid fa-book-open me-1"></i> Baca 6 Aturan Aman
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <div class="display-1 text-white opacity-25">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6 Pilar Keamanan Mitra -->
    <div id="sop-points" class="mb-5">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-dark mb-1">6 Aturan Emas Keselamatan Kerja di Lapangan</h3>
            <p class="text-muted small">Wajib diterapkan setiap kali Anda menerima panggilan atau order jasa</p>
        </div>

        <div class="row g-3 g-md-4">
            <!-- 1. Konfirmasi Sebelum Berangkat -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift">
                    <div class="d-inline-flex align-items-center justify-content-center bg-teal-subtle text-teal rounded-circle mb-3" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-phone-volume fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">1. Konfirmasi WA Sebelum Jalan</h5>
                    <p class="text-secondary small mb-3">
                        Jangan pernah langsung berangkat jika pelanggan belum membalas pesan WhatsApp atau telepon Anda. Minta patokan rumah yang jelas dan titik <em>Share Live Location</em> Google Maps.
                    </p>
                    <div class="bg-light p-2.5 rounded-3 border-start border-3 border-teal small text-dark">
                        <strong>Aturan:</strong> Jika nomor WA tidak aktif atau tidak bisa dihubungi saat Anda siap jalan, <strong>TUNDA KEBERANGKATAN</strong> untuk menghindari order fiktif / prank.
                    </div>
                </div>
            </div>

            <!-- 2. Waspada Struk Palsu -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift">
                    <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle mb-3" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-receipt fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">2. Waspada Struk Transfer Palsu</h5>
                    <p class="text-secondary small mb-3">
                        Pelanggan nakal kerap mengirimkan foto bukti transfer m-Banking editan / palsu. <strong>Jangan pernah percaya hanya dari gambar screenshot pelanggan!</strong>
                    </p>
                    <div class="bg-light p-2.5 rounded-3 border-start border-3 border-danger small text-dark">
                        <strong>Aturan:</strong> Buka aplikasi m-Banking Anda sendiri (BCA/BRI/Mandiri/DANA dll) dan pastikan <strong>mutasi saldo sudah benar-benar masuk</strong> sebelum Anda pamit meninggalkan lokasi.
                    </div>
                </div>
            </div>

            <!-- 3. Aturan DP untuk Jasa Berbahan -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift">
                    <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning-emphasis rounded-circle mb-3" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-hand-holding-dollar fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">3. Wajib DP untuk Modal Bahan</h5>
                    <p class="text-secondary small mb-3">
                        Khusus untuk pengerjaan jasa yang membutuhkan modal material (misal: Undangan Pernikahan, Sablon, Las Besi/Kanopi, Pembelian Sparepart, Catering, dll).
                    </p>
                    <div class="bg-light p-2.5 rounded-3 border-start border-3 border-warning small text-dark">
                        <strong>Aturan:</strong> Wajib minta <strong>Uang Muka (DP 30% - 50%)</strong> terlebih dahulu. Jangan sekali-kali menombokkan uang pribadi Anda sebelum DP diterima.
                    </div>
                </div>
            </div>

            <!-- 4. Keamanan Lokasi & Jam Malam -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift">
                    <div class="d-inline-flex align-items-center justify-content-center bg-purple-subtle text-purple rounded-circle mb-3" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-location-crosshairs fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">4. Lokasi Sepi & Jam Malam</h5>
                    <p class="text-secondary small mb-3">
                        Untuk order panggilan ke daerah perkebunan sepi atau di atas jam 18.00 WIB. Khususnya bagi mitra perempuan (Rias Make-up, Pijat Terapi, Perawat Lansia, Guru Les):
                    </p>
                    <div class="bg-light p-2.5 rounded-3 border-start border-3 border-purple small text-dark">
                        <strong>Aturan:</strong> Selalu ajak rekan pendamping dan aktifkan fitur <strong>Bagi Lokasi Terkini (Share Live Location)</strong> WhatsApp selama berada di rumah pelanggan.
                    </div>
                </div>
            </div>

            <!-- 5. Hak Menolak Pekerjaan -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift">
                    <div class="d-inline-flex align-items-center justify-content-center bg-info-subtle text-info-emphasis rounded-circle mb-3" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-hand fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">5. Hak Menolak / Membatalkan</h5>
                    <p class="text-secondary small mb-3">
                        Sebagai mitra mandiri, keselamatan diri dan martabat Anda nomor satu. Anda berhak menolak atau membatalkan pekerjaan secara sepihak jika:
                    </p>
                    <div class="bg-light p-2.5 rounded-3 border-start border-3 border-info small text-dark">
                        <strong>Kondisi:</strong> Pelanggan bertindak kasar, mabuk, melakukan pelecehan, atau memaksa meminta pekerjaan berbahaya di luar kesepakatan aplikasi.
                    </div>
                </div>
            </div>

            <!-- 6. Fitur Laporkan Pelanggan -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 hover-lift">
                    <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle mb-3" style="width: 50px; height: 50px;">
                        <i class="fa-solid fa-triangle-exclamation fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">6. Fitur Laporkan Pelanggan</h5>
                    <p class="text-secondary small mb-3">
                        Jika Anda mendapati pelanggan yang menolak membayar, melakukan penipuan, atau order fiktif, gunakan tombol <strong>"🚨 Laporkan Pelanggan"</strong> di dashboard pesanan Anda.
                    </p>
                    <div class="bg-light p-2.5 rounded-3 border-start border-3 border-danger small text-dark">
                        <strong>Tindakan Admin:</strong> Akun pelanggan nakal akan langsung dibekukan dan nomor HP-nya <strong>diblacklist permanen</strong> di seluruh Indragiri Hulu.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kontak Bantuan Mediasi & FAQ -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
        <div class="row align-items-center g-4">
            <div class="col-md-8">
                <h4 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-headset text-teal"></i>
                    Butuh Bantuan Mediasi Langsung dari Admin?
                </h4>
                <p class="text-secondary small mb-0">
                    Jika ada perselisihan pembayaran atau masalah di lokasi kerja dengan pelanggan di Indragiri Hulu, jangan ragu untuk menghubungi layanan bantuan pengelola JASA INHU. Kami siap menjadi penengah resmi dan melindungi hak mitra kerja lokal.
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="<?= $admin_sos_url ?>" target="_blank" class="btn btn-teal fw-bold px-4 py-2.5 rounded-pill shadow-xs d-inline-flex align-items-center gap-2">
                    <i class="fa-brands fa-whatsapp fs-5"></i>
                    <span>Hubungi Hotline Admin ↗</span>
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

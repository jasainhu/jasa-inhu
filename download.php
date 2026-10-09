<?php
/**
 * Halaman Download Aplikasi JASA INHU (Android & iPhone)
 */

$page_title = 'Download Aplikasi JASA INHU - Android & iPhone';

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isIos = str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad') || str_contains($userAgent, 'ipod');
$isAndroid = str_contains($userAgent, 'android');

require_once __DIR__ . '/includes/header.php';
?>

<div class="download-page-wrapper py-5" style="background: linear-gradient(180deg, #f0fdfa 0%, #f8fafc 100%); min-height: 85vh;">
    <div class="container">
        
        <!-- Hero Section -->
        <div class="row align-items-center justify-content-center g-4 mb-5">
            <div class="col-lg-6 text-center text-lg-start">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white shadow-sm border border-success-subtle mb-3">
                    <span class="badge bg-success text-white rounded-pill px-2 py-1"><i class="fa-solid fa-check"></i> Resmi</span>
                    <span class="small fw-semibold text-dark">Versi Terbaru v1.0.0 (Bebas Iklan & Ringan)</span>
                </div>
                
                <h1 class="display-5 fw-bold text-dark mb-3" style="letter-spacing: -0.5px;">
                    Pesan Jasa di <span style="color: #0d9488;">Indragiri Hulu</span> Lebih Cepat Lewat Aplikasi
                </h1>
                
                <p class="lead text-secondary mb-4" style="font-size: 1.05rem;">
                    Dapatkan akses instan ke ratusan tukang bangunan, teknisi AC, servis motor, mekanik, hingga jasa perkebunan sawit terpercaya di sekitar Anda langsung dari layar HP.
                </p>

                <!-- Tombol Aksi Cepat Sesuai Device -->
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start mb-4">
                    <a href="<?= BASE_URL ?>/download/jasainhu.apk" download="JasaInhu.apk" class="btn btn-lg text-white d-flex align-items-center gap-3 px-4 py-3 rounded-4 shadow-sm" style="background: linear-gradient(135deg, #0d9488 0%, #059669 100%); border: none;">
                        <i class="fa-brands fa-android fs-2"></i>
                        <div class="text-start lh-sm">
                            <div class="small text-white-50" style="font-size: 0.72rem;">DOWNLOAD GRATIS</div>
                            <div class="fw-bold fs-6">Android APK (1.8 MB)</div>
                        </div>
                    </a>

                    <a href="#panduan-ios" class="btn btn-lg btn-white bg-white border d-flex align-items-center gap-3 px-4 py-3 rounded-4 shadow-sm text-dark hover-shadow">
                        <i class="fa-brands fa-apple fs-2 text-dark"></i>
                        <div class="text-start lh-sm">
                            <div class="small text-muted" style="font-size: 0.72rem;">PANDUAN RESMI</div>
                            <div class="fw-bold fs-6">Pengguna iPhone (iOS)</div>
                        </div>
                    </a>
                </div>

                <div class="d-flex align-items-center gap-4 justify-content-center justify-content-lg-start text-muted small">
                    <div><i class="fa-solid fa-shield-halved text-success me-1"></i> 100% Aman & Terverifikasi</div>
                    <div><i class="fa-solid fa-bolt text-warning me-1"></i> Ukuran Hanya 1.8 MB</div>
                </div>
            </div>

            <!-- Visual Mockup Card -->
            <div class="col-lg-5 text-center">
                <div class="p-4 bg-white rounded-5 shadow-lg border text-center position-relative d-inline-block" style="max-width: 360px; width: 100%;">
                    <div class="mb-3">
                        <img src="<?= asset_url('images/icon-192.png') ?>" alt="Logo Jasa Inhu" width="96" height="96" class="rounded-4 shadow-sm">
                    </div>
                    <h4 class="fw-bold text-dark mb-1">JASA INHU Mobile</h4>
                    <p class="text-secondary small mb-3">Layanan Jasa Terpadu Indragiri Hulu, Riau</p>
                    
                    <div class="p-3 rounded-4 bg-light text-start mb-3 small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Ukuran Berkas:</span>
                            <span class="fw-bold text-dark">1.8 MB</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Kompatibilitas:</span>
                            <span class="fw-bold text-dark">Android 7.0+ & iOS 14+</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Pembaruan:</span>
                            <span class="fw-bold text-success">Otomatis Terhubung Cloud</span>
                        </div>
                    </div>

                    <a href="<?= BASE_URL ?>/download/jasainhu.apk" download="JasaInhu.apk" class="btn btn-teal w-100 py-2.5 rounded-pill fw-semibold text-white mb-2" style="background-color: #0d9488;">
                        <i class="fa-solid fa-download me-1.5"></i> Unduh File APK Sekarang
                    </a>
                    <span class="text-muted" style="font-size: 0.72rem;">Tanpa biaya berlangganan &bull; 100% Gratis</span>
                </div>
            </div>
        </div>

        <!-- Panduan Instalasi Tabs -->
        <div class="row justify-content-center mt-5">
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-bottom p-3">
                        <ul class="nav nav-pills nav-fill gap-2" id="installTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !$isIos ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="android-tab" data-bs-toggle="tab" data-bs-target="#android-pane" type="button" role="tab" style="<?= !$isIos ? 'background-color: #0d9488; color: white;' : '' ?>">
                                    <i class="fa-brands fa-android fs-5"></i> Panduan Pasang di Android
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $isIos ? 'active' : '' ?> fw-bold py-2.5 rounded-3 d-flex align-items-center justify-content-center gap-2" id="ios-tab" data-bs-toggle="tab" data-bs-target="#ios-pane" type="button" role="tab" style="<?= $isIos ? 'background-color: #0d9488; color: white;' : '' ?>">
                                    <i class="fa-brands fa-apple fs-5"></i> Panduan Pasang di iPhone (iOS)
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <div class="tab-content" id="installTabsContent">
                            
                            <!-- Tab Android -->
                            <div class="tab-pane fade <?= !$isIos ? 'show active' : '' ?>" id="android-pane" role="tabpanel">
                                <h4 class="fw-bold text-dark mb-4">Cara Memasang File APK di HP Android:</h4>
                                
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <div class="p-3 rounded-4 bg-light h-100 border text-center">
                                            <div class="badge bg-teal rounded-circle p-3 mb-3 text-white fs-5" style="background-color: #0d9488; width: 50px; height: 50px;">1</div>
                                            <h6 class="fw-bold text-dark">Klik Tombol Unduh</h6>
                                            <p class="text-secondary small mb-0">Klik tombol unduh APK di atas. Browser akan mengunduh file <strong>JasaInhu.apk</strong> ke ponsel Anda.</p>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="p-3 rounded-4 bg-light h-100 border text-center">
                                            <div class="badge bg-teal rounded-circle p-3 mb-3 text-white fs-5" style="background-color: #0d9488; width: 50px; height: 50px;">2</div>
                                            <h6 class="fw-bold text-dark">Buka Notifikasi Unduhan</h6>
                                            <p class="text-secondary small mb-0">Tarik bilah notifikasi HP Anda lalu ketuk berkas <strong>JasaInhu.apk</strong> yang baru selesai diunduh.</p>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="p-3 rounded-4 bg-light h-100 border text-center">
                                            <div class="badge bg-teal rounded-circle p-3 mb-3 text-white fs-5" style="background-color: #0d9488; width: 50px; height: 50px;">3</div>
                                            <h6 class="fw-bold text-dark">Pilih Install / Pasang</h6>
                                            <p class="text-secondary small mb-0">Jika muncul konfirmasi sumber tidak dikenal, ketuk <strong>Izinkan dari sumber ini</strong> lalu pilih <strong>Install</strong>.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 p-3 rounded-3 bg-warning-subtle border border-warning-subtle text-dark small">
                                    <i class="fa-solid fa-circle-info text-warning me-1.5"></i>
                                    <strong>Tips Tambahan:</strong> Anda juga bisa memasang tanpa download file APK cukup lewat browser Chrome: ketuk titik tiga (<strong>⋮</strong>) di kanan atas lalu pilih <strong>"Install Aplikasi"</strong>.
                                </div>
                            </div>

                            <!-- Tab iPhone iOS -->
                            <div class="tab-pane fade <?= $isIos ? 'show active' : '' ?>" id="ios-pane" role="tabpanel">
                                <div id="panduan-ios"></div>
                                <h4 class="fw-bold text-dark mb-2">Cara Memasang di iPhone / iPad (Apple):</h4>
                                <p class="text-secondary small mb-4">Pengguna Apple tidak memerlukan file APK. Sistem resmi iOS menggunakan fitur Progressive Web App bawaan Safari:</p>
                                
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <div class="p-3 rounded-4 bg-light h-100 border text-center">
                                            <div class="badge bg-dark rounded-circle p-3 mb-3 text-white fs-5" style="width: 50px; height: 50px;">1</div>
                                            <h6 class="fw-bold text-dark">Buka di Safari</h6>
                                            <p class="text-secondary small mb-0">Buka browser <strong>Safari</strong> di iPhone Anda dan kunjungi <strong>jasainhu.gafahrandi.my.id</strong>.</p>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="p-3 rounded-4 bg-light h-100 border text-center">
                                            <div class="badge bg-dark rounded-circle p-3 mb-3 text-white fs-5" style="width: 50px; height: 50px;">2</div>
                                            <h6 class="fw-bold text-dark">Ketuk Ikon Bagikan (Share)</h6>
                                            <p class="text-secondary small mb-0">Ketuk ikon <strong>Bagikan</strong> di bagian bawah layar iPhone (ikon kotak dengan panah mengarah ke atas <i class="fa-solid fa-arrow-up-from-bracket"></i>).</p>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="p-3 rounded-4 bg-light h-100 border text-center">
                                            <div class="badge bg-dark rounded-circle p-3 mb-3 text-white fs-5" style="width: 50px; height: 50px;">3</div>
                                            <h6 class="fw-bold text-dark">Tambah ke Layar Utama</h6>
                                            <p class="text-secondary small mb-0">Gulir ke bawah lalu ketuk <strong>"Tambahkan ke Layar Utama" (Add to Home Screen)</strong> lalu ketuk <strong>Tambah</strong>.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 p-3 rounded-3 bg-success-subtle border border-success-subtle text-success-emphasis small">
                                    <i class="fa-solid fa-circle-check text-success me-1.5"></i>
                                    <strong>Selesai!</strong> Ikon resmi JASA INHU akan langsung muncul di beranda iPhone Anda dan dapat dibuka satu ketukan dalam mode layar penuh tanpa bilah browser.
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Switch tab highlight style cleanly
document.querySelectorAll('#installTabs button').forEach(btn => {
    btn.addEventListener('shown.bs.tab', function(e) {
        document.querySelectorAll('#installTabs button').forEach(b => {
            b.style.backgroundColor = '';
            b.style.color = '';
        });
        const isIosTarget = this.id === 'ios-tab';
        this.style.backgroundColor = isIosTarget ? '#0f172a' : '#0d9488';
        this.style.color = '#ffffff';
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

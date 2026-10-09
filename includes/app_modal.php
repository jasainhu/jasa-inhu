<?php
/**
 * Smart Mobile App Banner & Install Modal (Tokopedia / Shopee Style)
 */
?>
<!-- 1. Floating Smart App Banner di Layar HP (Tampil di atas Mobile Bottom Nav) -->
<div id="smartAppBanner" class="smart-app-banner d-lg-none shadow-lg">
    <div class="d-flex align-items-center justify-content-between gap-2 p-2 px-3">
        <!-- Close button banner -->
        <button type="button" class="btn-close-banner" onclick="dismissSmartBanner()" aria-label="Tutup">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <!-- Icon & App Info -->
        <div class="d-flex align-items-center gap-2 overflow-hidden flex-grow-1" onclick="openInstallModal()" style="cursor: pointer;">
            <img src="<?= asset_url('images/icon-192.png') ?>" alt="Jasa Inhu" width="38" height="38" class="rounded-3 shadow-xs flex-shrink-0">
            <div class="text-truncate lh-sm">
                <div class="fw-bold text-dark small text-truncate">JASA INHU Mobile</div>
                <div class="text-secondary" style="font-size: 0.7rem;">Lebih cepat & hemat kuota di aplikasi</div>
            </div>
        </div>

        <!-- Action Button -->
        <button type="button" class="btn btn-sm btn-teal text-white rounded-pill px-3 py-1.5 fw-bold flex-shrink-0" onclick="openInstallModal()" style="background-color: #0d9488; font-size: 0.78rem;">
            Pasang
        </button>
    </div>
</div>

<!-- 2. Pop-up Modal Instalasi Aplikasi (Modern App Store Style) -->
<div class="modal fade" id="installAppModal" tabindex="-1" aria-labelledby="installAppModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" style="max-width: 380px;">
        <div class="modal-content border-0 rounded-4 shadow-xl overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-3 px-3">
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            
            <div class="modal-body text-center px-4 pt-1 pb-4">
                <div class="mb-3 position-relative d-inline-block">
                    <img src="<?= asset_url('images/icon-192.png') ?>" alt="Logo Jasa Inhu" width="76" height="76" class="rounded-4 shadow-sm border">
                    <span class="position-absolute bottom-0 end-0 bg-success text-white rounded-circle p-1 border border-white" style="font-size: 0.65rem;" title="Terverifikasi">
                        <i class="fa-solid fa-check"></i>
                    </span>
                </div>

                <h5 class="fw-bold text-dark mb-1">Aplikasi JASA INHU</h5>
                <p class="text-muted small mb-2">Layanan Jasa & Tukang Indragiri Hulu</p>

                <div class="d-flex justify-content-center align-items-center gap-3 py-2 px-3 bg-light rounded-3 mb-3 small text-secondary">
                    <div>
                        <i class="fa-solid fa-star text-warning"></i>
                        <span class="fw-bold text-dark">5.0</span>
                    </div>
                    <span>&bull;</span>
                    <div>
                        <span class="fw-bold text-dark">1.8 MB</span>
                    </div>
                    <span>&bull;</span>
                    <div class="text-success fw-semibold">
                        Gratis
                    </div>
                </div>

                <!-- Tombol Download APK Android -->
                <div class="mb-2">
                    <a href="<?= BASE_URL ?>/download/jasainhu.apk" download="JasaInhu.apk" class="btn btn-teal w-100 py-2.5 rounded-pill fw-bold text-white d-flex align-items-center justify-content-center gap-2 shadow-sm" style="background-color: #0d9488;">
                        <i class="fa-brands fa-android fs-5"></i>
                        <span>Download APK Android</span>
                    </a>
                </div>

                <!-- Tombol Install PWA Chrome -->
                <div class="mb-3">
                    <button type="button" id="btnTriggerPwaInstall" class="btn btn-outline-secondary w-100 py-2 rounded-pill small fw-semibold d-flex align-items-center justify-content-center gap-2" onclick="triggerPwaPrompt()">
                        <i class="fa-solid fa-bolt text-teal" style="color: #0d9488;"></i>
                        <span>Pasang Instan di Chrome</span>
                    </button>
                </div>

                <!-- Accordion Panduan iPhone (iOS) -->
                <div class="border rounded-3 p-2 text-start bg-light-subtle">
                    <a class="text-decoration-none d-flex align-items-center justify-content-between text-dark fw-semibold small" data-bs-toggle="collapse" href="#iosCollapse" role="button" aria-expanded="false" aria-controls="iosCollapse">
                        <span><i class="fa-brands fa-apple me-1.5"></i> Pengguna iPhone (Apple)?</span>
                        <i class="fa-solid fa-chevron-down text-muted" style="font-size: 0.7rem;"></i>
                    </a>
                    <div class="collapse mt-2 pt-2 border-top" id="iosCollapse">
                        <ol class="small text-secondary mb-0 ps-3" style="font-size: 0.75rem; line-height: 1.4;">
                            <li class="mb-1">Buka website di browser <strong>Safari</strong>.</li>
                            <li class="mb-1">Ketuk ikon <strong>Bagikan</strong> (<i class="fa-solid fa-arrow-up-from-bracket"></i>) di bawah.</li>
                            <li>Pilih <strong>Tambahkan ke Layar Utama</strong>.</li>
                        </ol>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="<?= BASE_URL ?>/download.php" class="small text-teal text-decoration-none fw-semibold" style="font-size: 0.75rem;">
                        Lihat Halaman Panduan Lengkap &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Smart App Banner Style */
.smart-app-banner {
    position: fixed;
    bottom: 64px; /* Tepat di atas mobile-bottom-nav */
    left: 10px;
    right: 10px;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(13, 148, 136, 0.2);
    border-radius: 16px;
    z-index: 1030;
    transition: all 0.3s ease;
}
.btn-close-banner {
    background: transparent;
    border: none;
    color: #94a3b8;
    padding: 4px 6px;
    font-size: 0.85rem;
    line-height: 1;
    cursor: pointer;
}
.btn-close-banner:hover {
    color: #475569;
}
</style>

<script>
let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    const pwaBtn = document.getElementById('btnTriggerPwaInstall');
    if (pwaBtn) pwaBtn.style.display = 'flex';
});

function openInstallModal() {
    const modalEl = document.getElementById('installAppModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

function dismissSmartBanner() {
    const banner = document.getElementById('smartAppBanner');
    if (banner) {
        banner.style.display = 'none';
        sessionStorage.setItem('dismissed_app_banner', 'true');
    }
}

function triggerPwaPrompt() {
    if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();
        deferredInstallPrompt.userChoice.then((choice) => {
            if (choice.outcome === 'accepted') {
                const modalEl = document.getElementById('installAppModal');
                if (modalEl) {
                    bootstrap.Modal.getInstance(modalEl)?.hide();
                }
            }
            deferredInstallPrompt = null;
        });
    } else {
        // Fallback jika browser tidak mendukung direct prompt
        window.location.href = window.APP_BASE_URL + '/download/jasainhu.apk';
    }
}

// Cek apakah banner sudah pernah ditutup pada sesi ini
document.addEventListener('DOMContentLoaded', () => {
    // Sembunyikan banner jika dibuka di dalam aplikasi PWA/TWA standalone
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    if (isStandalone || sessionStorage.getItem('dismissed_app_banner') === 'true') {
        const banner = document.getElementById('smartAppBanner');
        if (banner) banner.style.display = 'none';
    }
});
</script>

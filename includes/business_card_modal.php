<?php
/**
 * Modal & Generator Kartu Jasa Digital Resmi Mitra
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */
?>

<!-- Library QR Code & HTML2Canvas untuk Export Gambar Berkualitas Tinggi -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
/* Styling Mewah Kartu Jasa Digital */
.card-business-preview-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 10px 0;
}

.digital-business-card {
    width: 100%;
    max-width: 350px;
    background: linear-gradient(155deg, #042f2e 0%, #0d9488 45%, #115e59 80%, #042f2e 100%);
    border: 2px solid rgba(251, 191, 36, 0.55);
    border-radius: 24px;
    padding: 24px 20px;
    color: #ffffff;
    box-shadow: 0 20px 40px -10px rgba(4, 47, 46, 0.45);
    position: relative;
    overflow: hidden;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}

/* Watermark Pattern Halus di Latar Belakang Kartu */
.digital-business-card::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 180px;
    height: 180px;
    background: radial-gradient(circle, rgba(251, 191, 36, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.digital-business-card::after {
    content: '';
    position: absolute;
    bottom: -60px;
    left: -40px;
    width: 160px;
    height: 160px;
    background: radial-gradient(circle, rgba(45, 212, 191, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.card-gold-badge {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #0f172a;
    font-weight: 800;
    font-size: 0.65rem;
    padding: 4px 10px;
    border-radius: 30px;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
}

.card-avatar-frame {
    width: 82px;
    height: 82px;
    border-radius: 50%;
    border: 3px solid #f59e0b;
    box-shadow: 0 0 16px rgba(245, 158, 11, 0.4);
    object-fit: cover;
    background: #ffffff;
}

.card-qr-box {
    background: #ffffff;
    border-radius: 18px;
    padding: 14px 16px;
    text-align: center;
    color: #0f172a;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    margin-top: 14px;
}

#cardQrContainer {
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 6px auto;
}
#cardQrContainer img, #cardQrContainer canvas {
    border-radius: 8px;
    display: block;
    margin: 0 auto;
}
</style>

<!-- Modal Kartu Jasa Digital -->
<div class="modal fade" id="modalBusinessCard" tabindex="-1" aria-labelledby="modalBusinessCardLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white px-4 py-3 border-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-id-card text-warning fs-5"></i>
                    <div>
                        <h6 class="modal-title fw-bold mb-0" id="modalBusinessCardLabel">Kartu Jasa Digital Resmi</h6>
                        <small class="text-white-50" style="font-size: 0.72rem;">Siap dibagikan ke Status WhatsApp & Medsos</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light">
                <!-- Area Kartu Visual (Elemen yang di-capture ke gambar PNG) -->
                <div class="card-business-preview-wrap">
                    <div id="providerDigitalCard" class="digital-business-card text-center">
                        <!-- Top Bar: Logo Jasa Inhu & Badge Verifikasi -->
                        <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom border-white border-opacity-15">
                            <div class="bg-white rounded-2 px-2.5 py-1 shadow-2xs d-inline-flex align-items-center">
                                <img src="<?= asset_url('images/logo.png') ?>" alt="JASA INHU" height="22" style="object-fit: contain;">
                            </div>
                            <span class="card-gold-badge">
                                <i class="fa-solid fa-certificate me-1"></i> MITRA RESMI
                            </span>
                        </div>

                        <!-- Foto Profil / Logo Usaha Mitra -->
                        <div class="mb-2 position-relative d-inline-block">
                            <img id="cardProvAvatar" src="<?= asset_url('images/logo.png') ?>" alt="Logo Mitra" class="card-avatar-frame">
                        </div>

                        <!-- Nama Usaha & Kategori -->
                        <h4 id="cardProvName" class="fw-bold mb-1 text-white text-truncate px-2" style="font-size: 1.25rem; letter-spacing: -0.3px;">
                            Nama Mitra
                        </h4>
                        
                        <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill bg-white bg-opacity-15 text-warning fw-semibold small mb-2" style="font-size: 0.75rem;">
                            <i id="cardProvCatIcon" class="fa-solid fa-wrench"></i>
                            <span id="cardProvCategory">Kategori Layanan</span>
                        </div>

                        <!-- Wilayah & Rating -->
                        <div class="d-flex align-items-center justify-content-center gap-2 small text-white-50 mb-1" style="font-size: 0.75rem;">
                            <span><i class="fa-solid fa-location-dot text-danger me-1"></i> <span id="cardProvDistrict">Pasir Penyu</span></span>
                            <span>&bull;</span>
                            <span class="text-warning fw-bold"><i class="fa-solid fa-star me-0.5"></i> <span id="cardProvRating">5.0</span></span>
                            <span>&bull;</span>
                            <span id="cardProvJobs" class="text-white-50">10+ Selesai</span>
                        </div>

                        <!-- Kotak QR Code Toko -->
                        <div class="card-qr-box">
                            <div class="text-muted text-uppercase fw-bold" style="font-size: 0.62rem; letter-spacing: 0.5px;">
                                Scan untuk Konsultasi & Pesan Langsung:
                            </div>
                            <div id="cardQrContainer"></div>
                            <div class="fw-bold text-teal" style="font-size: 0.72rem; letter-spacing: -0.2px;">
                                jasainhu.gafahrandi.my.id
                            </div>
                        </div>

                        <!-- Bottom Tagline -->
                        <div class="pt-3 mt-2 text-white-50 d-flex justify-content-between align-items-center border-top border-white border-opacity-10" style="font-size: 0.65rem;">
                            <span>Kabupaten Indragiri Hulu</span>
                            <span class="text-warning"><i class="fa-solid fa-shield-halved me-1"></i> Terpercaya & Bergaransi</span>
                        </div>
                    </div>
                </div>

                <!-- Tombol-Tombol Aksi Pembagian -->
                <div class="row g-2 mt-3">
                    <div class="col-12 col-sm-6">
                        <button type="button" class="btn btn-teal w-100 py-2.5 fw-bold rounded-pill shadow-xs d-flex align-items-center justify-content-center gap-2" id="btnDownloadCard" onclick="downloadBusinessCardImage()">
                            <i class="fa-solid fa-download"></i>
                            <span>Unduh Gambar (PNG)</span>
                        </button>
                    </div>
                    <div class="col-12 col-sm-6">
                        <button type="button" class="btn btn-success w-100 py-2.5 fw-bold rounded-pill shadow-xs d-flex align-items-center justify-content-center gap-2" onclick="shareBusinessCardWA()">
                            <i class="fa-brands fa-whatsapp fs-5"></i>
                            <span>Status WhatsApp</span>
                        </button>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-light border w-100 py-2 fw-semibold rounded-pill d-flex align-items-center justify-content-center gap-2 text-dark small" onclick="copyBusinessCardLink()">
                            <i class="fa-regular fa-copy text-teal"></i>
                            <span>Salin Teks Promosi & Tautan Toko</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentCardData = {
    id: 0,
    name: 'Mitra Jasa Inhu',
    category: 'Layanan Jasa',
    categoryIcon: 'fa-wrench',
    district: 'Kab. Indragiri Hulu',
    rating: '5.0',
    jobs: 'Terpercaya',
    avatar: '<?= asset_url('images/logo.png') ?>',
    url: '<?= BASE_URL ?>'
};

function openBusinessCardModal(data) {
    if (data) {
        currentCardData = Object.assign(currentCardData, data);
    }

    // Set Text Konten
    document.getElementById('cardProvName').innerText = currentCardData.name;
    document.getElementById('cardProvCategory').innerText = currentCardData.category;
    document.getElementById('cardProvCatIcon').className = 'fa-solid ' + (currentCardData.categoryIcon || 'fa-wrench');
    document.getElementById('cardProvDistrict').innerText = currentCardData.district;
    document.getElementById('cardProvRating').innerText = currentCardData.rating;
    document.getElementById('cardProvJobs').innerText = currentCardData.jobs;
    
    // Set Avatar
    if (currentCardData.avatar) {
        document.getElementById('cardProvAvatar').src = currentCardData.avatar;
    }

    // Generate QR Code ke URL Toko
    const qrContainer = document.getElementById('cardQrContainer');
    qrContainer.innerHTML = '';
    const storeUrl = currentCardData.url || (window.BASE_URL + '/provider_detail.php?id=' + currentCardData.id);
    
    new QRCode(qrContainer, {
        text: storeUrl,
        width: 120,
        height: 120,
        colorDark: "#042f2e",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
    });

    // Tampilkan Modal
    const modalEl = document.getElementById('modalBusinessCard');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function downloadBusinessCardImage() {
    const cardEl = document.getElementById('providerDigitalCard');
    const btn = document.getElementById('btnDownloadCard');
    if (!cardEl) return;

    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyimpan...';
    btn.disabled = true;

    // Gunakan html2canvas dengan konfigurasi skala 2x untuk hasil HD
    html2canvas(cardEl, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: null
    }).then(canvas => {
        btn.innerHTML = originalText;
        btn.disabled = false;

        const link = document.createElement('a');
        const safeName = currentCardData.name.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
        link.download = 'Kartu-Jasa-' + safeName + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }).catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Gagal mengunduh gambar kartu. Anda juga bisa mengambil screenshot layar kartu ini.');
    });
}

function shareBusinessCardWA() {
    const storeUrl = currentCardData.url || (window.BASE_URL + '/provider_detail.php?id=' + currentCardData.id);
    const text = 
        `Halo semuanya! 🙏\n\n` +
        `Butuh jasa *${currentCardData.name}* (${currentCardData.category}) di wilayah ${currentCardData.district} & sekitarnya?\n\n` +
        `Kini Anda bisa konsultasi langsung, cek ulasan, dan memesan jasa kami secara resmi & aman lewat platform *JASA INHU*:\n` +
        `👉 ${storeUrl}\n\n` +
        `Siap melayani dengan profesional & bergaransi. Terima kasih!`;

    window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(text), '_blank');
}

function copyBusinessCardLink() {
    const storeUrl = currentCardData.url || (window.BASE_URL + '/provider_detail.php?id=' + currentCardData.id);
    const text = 
        `Pesan jasa resmi *${currentCardData.name}* (${currentCardData.category}) di JASA INHU:\n` +
        `${storeUrl}`;

    navigator.clipboard.writeText(text).then(() => {
        alert('Teks promosi dan link toko berhasil disalin ke clipboard!');
    }).catch(() => {
        prompt('Salin tautan ini:', storeUrl);
    });
}
</script>

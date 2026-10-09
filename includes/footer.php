<?php
/**
 * Global Frontend Footer: JASA INHU
 */
?>
<!-- Footer -->
<footer class="footer-custom">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="mb-3">
                    <div class="bg-white rounded-3 px-3 py-1 d-inline-block shadow-sm">
                        <img src="<?= asset_url('images/logo.png') ?>" alt="JASA INHU" height="34" style="object-fit: contain;">
                    </div>
                </div>
                <p class="text-secondary small mb-3">
                    Platform digital marketplace jasa lokal terpercaya khusus Kabupaten Indragiri Hulu, Riau. Mempertemukan masyarakat dengan para tukang, teknisi, mekanik, dan pekerja profesional lokal di sekitar Anda.
                </p>
                <div class="d-flex gap-2">
                    <span class="badge text-bg-dark border border-secondary text-secondary py-2 px-3 small">
                        <i class="fa-solid fa-map-location-dot me-1 text-teal" style="color: #2dd4bf;"></i> 14 Kecamatan Inhu
                    </span>
                    <span class="badge text-bg-dark border border-secondary text-secondary py-2 px-3 small">
                        <i class="fa-solid fa-shield-halved me-1 text-teal" style="color: #2dd4bf;"></i> Mitra Terverifikasi
                    </span>
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <h6 class="text-white fw-bold mb-3">Wilayah Layanan</h6>
                <ul class="list-unstyled small text-secondary">
                    <li class="mb-2"><i class="fa-solid fa-chevron-right me-1 text-secondary" style="font-size: 0.65rem;"></i> Rengat</li>
                    <li class="mb-2"><i class="fa-solid fa-chevron-right me-1 text-secondary" style="font-size: 0.65rem;"></i> Rengat Barat (Pematang Reba)</li>
                    <li class="mb-2"><i class="fa-solid fa-chevron-right me-1 text-secondary" style="font-size: 0.65rem;"></i> Pasir Penyu (Air Molek)</li>
                    <li class="mb-2"><i class="fa-solid fa-chevron-right me-1 text-secondary" style="font-size: 0.65rem;"></i> Seberida (Belilas)</li>
                    <li class="mb-2"><i class="fa-solid fa-chevron-right me-1 text-secondary" style="font-size: 0.65rem;"></i> Peranap & Lirik</li>
                    <li><i class="fa-solid fa-chevron-right me-1 text-secondary" style="font-size: 0.65rem;"></i> Seluruh 14 Kecamatan Inhu</li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3">Kategori Terpopuler</h6>
                <ul class="list-unstyled small text-secondary">
                    <li class="mb-2"><a href="<?= BASE_URL ?>/#kategori" class="text-decoration-none">Servis Motor & Panggilan</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/#kategori" class="text-decoration-none">Cuci & Perbaikan AC</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/#kategori" class="text-decoration-none">Tukang Bangunan & Renovasi</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/#kategori" class="text-decoration-none">Jasa Kebun Sawit & Panen</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/#kategori" class="text-decoration-none">Angkutan & Sewa Pickup</a></li>
                    <li><a href="<?= BASE_URL ?>/#kategori" class="text-decoration-none">Sound System & Tenda Acara</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3">Untuk Penyedia Jasa</h6>
                <p class="small text-secondary mb-3">
                    Punya keahlian tukang, teknisi, atau jasa di Indragiri Hulu? Daftarkan usaha Anda sekarang secara gratis dan dapatkan pelanggan dari sekitar Anda.
                </p>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-outline-light w-100 fw-semibold py-2">
                    <i class="fa-solid fa-briefcase me-1"></i> Daftar Jadi Mitra Jasa
                </a>
            </div>
        </div>

        <hr class="border-secondary my-4 opacity-25">

        <div class="row align-items-center small text-secondary">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                &copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong>. Platform Marketplace Jasa Lokal Kabupaten Indragiri Hulu.
                <div class="mt-1 d-flex gap-3 justify-content-center justify-content-md-start">
                    <a href="<?= BASE_URL ?>/terms.php" class="text-secondary text-decoration-none hover-teal">Syarat & Ketentuan</a>
                    <span>&bull;</span>
                    <a href="<?= BASE_URL ?>/privacy.php" class="text-secondary text-decoration-none hover-teal">Kebijakan Privasi</a>
                    <span>&bull;</span>
                    <a href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>?text=Halo%20Admin%20Jasa%20Inhu,%20saya%20ingin%20bertanya%20tentang%20layanan" target="_blank" class="text-secondary text-decoration-none hover-teal">
                        <i class="fa-brands fa-whatsapp text-success me-1"></i> Bantuan CS
                    </a>
                </div>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <span>Versi 1.0 (Tahap Pertama) &bull; Dibuat untuk Kemajuan Perekonomian Lokal Inhu</span>
            </div>
        </div>
    </div>
</footer>

<!-- Floating WhatsApp Support Button -->
<a href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>?text=Halo%20Admin%20Jasa%20Inhu,%20saya%20butuh%20bantuan%20layanan" target="_blank" class="floating-wa-btn shadow-lg" title="Hubungi CS Admin via WhatsApp" aria-label="Bantuan WhatsApp Admin">
    <i class="fa-brands fa-whatsapp"></i>
    <span class="floating-wa-text d-none d-sm-inline">Bantuan CS</span>
</a>

<!-- Mobile Bottom Navigation Bar (Tokopedia/Shopee Style on Mobile) -->
<nav class="mobile-bottom-nav d-lg-none" aria-label="Mobile Navigation">
    <a href="<?= BASE_URL ?>/" class="mobile-nav-item <?= empty($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === '/' || (str_contains($_SERVER['REQUEST_URI'], 'index.php') && !str_contains($_SERVER['REQUEST_URI'], '/user/') && !str_contains($_SERVER['REQUEST_URI'], '/provider/') && !str_contains($_SERVER['REQUEST_URI'], '/admin/')) ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i>
        <span>Beranda</span>
    </a>
    
    <a href="javascript:void(0)" class="mobile-nav-item" data-bs-toggle="modal" data-bs-target="#allCategoriesModal">
        <i class="fa-solid fa-shapes"></i>
        <span>Kategori</span>
    </a>

    <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
        <?php if (has_role('penyedia')): ?>
            <a href="<?= BASE_URL ?>/provider/index.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'provider/index.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Orderan</span>
            </a>
            <a href="<?= BASE_URL ?>/chat.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'chat.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-comments"></i>
                <span>Obrolan</span>
            </a>
            <a href="<?= BASE_URL ?>/provider/profile.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'profile.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-user-gear"></i>
                <span>Profil</span>
            </a>
        <?php elseif (has_role('admin')): ?>
            <a href="<?= BASE_URL ?>/admin/index.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/index.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/banners.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'banners.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-rectangle-ad"></i>
                <span>Banner</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/profile.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'profile.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-user-shield"></i>
                <span>Profil</span>
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/user/requests.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'requests.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Pesanan</span>
            </a>
            <a href="<?= BASE_URL ?>/chat.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'chat.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-comments"></i>
                <span>Obrolan</span>
            </a>
            <a href="<?= BASE_URL ?>/user/profile.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/user/profile.php') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/user/index.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-user"></i>
                <span>Akun</span>
            </a>
        <?php endif; ?>
    <?php else: ?>
        <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#loginModal" class="mobile-nav-item">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            <span>Masuk</span>
        </a>
        <a href="<?= BASE_URL ?>/register.php" class="mobile-nav-item <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'register.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-user-plus"></i>
            <span>Daftar</span>
        </a>
    <?php endif; ?>
</nav>

<!-- Global Tokopedia Style Login Modal -->
<?php require_once __DIR__ . '/login_modal.php'; ?>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 Modern Popups -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Custom App JS -->
<script src="<?= asset_url('js/app.js') ?>"></script>
</body>
</html>

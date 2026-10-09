<?php
/**
 * Halaman Kebijakan Privasi (Privacy Policy)
 * JASA INHU - Platform Marketplace Jasa Lokal Kab. Indragiri Hulu
 */
$page_title = 'Kebijakan Privasi';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <!-- Header Kartu -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="p-4 p-md-5 text-white" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                    <div class="badge bg-warning text-dark px-3 py-1.5 fw-bold mb-3 rounded-pill">
                        <i class="fa-solid fa-user-shield me-1"></i> Perlindungan Data Pribadi
                    </div>
                    <h1 class="h2 fw-bold mb-2">Kebijakan Privasi</h1>
                    <p class="text-white-50 mb-0 small">
                        Terakhir diperbarui: <?= date('d F Y') ?> &bull; Komitmen kami menjaga privasi seluruh warga & mitra di Indragiri Hulu.
                    </p>
                </div>

                <div class="card-body p-4 p-md-5 bg-white">
                    <p class="text-secondary small" style="line-height: 1.8;">
                        Selamat datang di <strong><?= APP_NAME ?></strong>. Kami sangat menghargai privasi dan kepercayaan Anda. Kebijakan Privasi ini menjelaskan bagaimana kami mengumpulkan, menggunakan, menyimpan, dan melindungi informasi pribadi pengguna dan mitra penyedia jasa di seluruh wilayah Kabupaten Indragiri Hulu.
                    </p>

                    <!-- Poin 1 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">1</span>
                            Informasi yang Kami Kumpulkan
                        </h5>
                        <ul class="text-secondary small ps-3" style="line-height: 1.8;">
                            <li><strong>Data Pendaftaran:</strong> Nama lengkap, alamat email, nomor telepon/WhatsApp, dan kata sandi terenkripsi.</li>
                            <li><strong>Data Khusus Penyedia Jasa:</strong> Nama usaha/bengkel, kategori keahlian, foto KTP (untuk keperluan verifikasi identitas internal), foto portofolio pengerjaan, dan wilayah kecamatan layanan di Inhu.</li>
                            <li><strong>Data Transaksi:</strong> Rincian permintaan jasa, riwayat obrolan dalam aplikasi (in-app chat), kwitansi tagihan, serta ulasan dan rating pelanggan.</li>
                        </ul>
                    </section>

                    <!-- Poin 2 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">2</span>
                            Penggunaan Data Pribadi
                        </h5>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            Informasi yang kami kumpulkan hanya digunakan untuk keperluan operasional platform, antara lain:
                        </p>
                        <ul class="text-secondary small ps-3" style="line-height: 1.8;">
                            <li>Menghubungkan pelanggan dengan penyedia jasa terdekat di kecamatan yang bersangkutan.</li>
                            <li>Menampilkan identitas usaha mitra secara profesional kepada calon pelanggan di Indragiri Hulu.</li>
                            <li>Mengirimkan notifikasi pemesanan dan bukti pembayaran/invoice digital.</li>
                            <li>Mencegah penipuan, akun palsu, atau penyalahgunaan layanan di platform kami.</li>
                        </ul>
                    </section>

                    <!-- Poin 3 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">3</span>
                            Perlindungan & Keamanan Data
                        </h5>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            <strong><?= APP_NAME ?> tidak pernah dan tidak akan pernah menjual, menyewakan, atau memperdagangkan data pribadi Anda kepada pihak ketiga</strong> untuk tujuan periklanan atau komersial di luar ekosistem platform.
                        </p>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            Kata sandi akun Anda disimpan dalam bentuk enkripsi kriptografi aman (<em>bcrypt hashing</em>) yang tidak dapat dibaca bahkan oleh administrator sekalipun.
                        </p>
                    </section>

                    <!-- Poin 4 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">4</span>
                            Pertanyaan & Kontak Layanan
                        </h5>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            Jika Anda memiliki pertanyaan tentang Kebijakan Privasi ini atau ingin mengajukan penghapusan akun Anda, silakan hubungi tim kami melalui:
                        </p>
                        <div class="p-3 bg-light rounded-3 border text-secondary small">
                            <div><i class="fa-solid fa-envelope text-teal me-2"></i> <strong>Email:</strong> <a href="mailto:<?= get_setting('admin_email', ADMIN_EMAIL) ?>" class="text-teal text-decoration-none"><?= get_setting('admin_email', ADMIN_EMAIL) ?></a></div>
                            <div class="mt-1"><i class="fa-brands fa-whatsapp text-success me-2"></i> <strong>WhatsApp Admin:</strong> <a href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>" target="_blank" class="text-teal text-decoration-none">+<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?></a></div>
                            <div class="mt-1"><i class="fa-solid fa-location-dot text-danger me-2"></i> <strong>Wilayah:</strong> Kabupaten Indragiri Hulu, Riau, Indonesia</div>
                        </div>
                    </section>

                    <div class="text-center pt-3">
                        <a href="<?= BASE_URL ?>/" class="btn btn-teal px-4 py-2 fw-semibold">
                            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda JASA INHU
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

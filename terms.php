<?php
/**
 * Halaman Syarat & Ketentuan Layanan (Terms of Service)
 * JASA INHU - Platform Marketplace Jasa Lokal Kab. Indragiri Hulu
 */
$page_title = 'Syarat & Ketentuan Layanan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <!-- Header Kartu -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="p-4 p-md-5 text-white" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                    <div class="badge bg-warning text-dark px-3 py-1.5 fw-bold mb-3 rounded-pill">
                        <i class="fa-solid fa-scale-balanced me-1"></i> Perlindungan Hukum & Pedoman Layanan
                    </div>
                    <h1 class="h2 fw-bold mb-2">Syarat & Ketentuan Layanan</h1>
                    <p class="text-white-50 mb-0 small">
                        Terakhir diperbarui: <?= date('d F Y') ?> &bull; Berlaku untuk seluruh pengguna dan mitra di Kabupaten Indragiri Hulu, Riau.
                    </p>
                </div>

                <div class="card-body p-4 p-md-5 bg-white">
                    <div class="alert alert-info border-0 rounded-3 mb-4 d-flex align-items-start gap-3">
                        <i class="fa-solid fa-circle-info fs-4 text-info mt-1"></i>
                        <div class="small">
                            <strong>Pemberitahuan Penting:</strong> Dengan mendaftar, mengakses, atau menggunakan layanan di platform <strong><?= APP_NAME ?></strong>, Anda menyatakan telah membaca, memahami, dan menyetujui seluruh isi Syarat & Ketentuan di bawah ini.
                        </div>
                    </div>

                    <!-- Poin 1 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">1</span>
                            Definisi & Kedudukan Platform
                        </h5>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            <strong><?= APP_NAME ?></strong> adalah platform marketplace digital perantara teknologi yang menghubungkan masyarakat di wilayah Kabupaten Indragiri Hulu ("Pelanggan") dengan tenaga profesional, tukang, montir, teknisi, dan pekerja independen lokal ("Mitra Penyedia Jasa").
                        </p>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            <?= APP_NAME ?> berkedudukan sebagai <strong>penyedia sarana komunikasi dan temu-janji digital</strong>, dan bukan merupakan majikan, pemberi kerja langsung, ataupun penjamin kualitas fisik dari mitra penyedia jasa independen.
                        </p>
                    </section>

                    <!-- Poin 2 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">2</span>
                            Hak & Kewajiban Pelanggan (Pengguna Jasa)
                        </h5>
                        <ul class="text-secondary small ps-3" style="line-height: 1.8;">
                            <li>Pelanggan wajib memberikan informasi kerusakan, lokasi, dan kebutuhan pekerjaan yang sebenarnya dan jelas.</li>
                            <li>Pelanggan berhak memeriksa hasil pengerjaan bersama teknisi/mitra sebelum melakukan pelunasan pembayaran.</li>
                            <li>Pelanggan wajib membayar biaya jasa dan penggantian suku cadang yang telah disepakati langsung kepada mitra setelah pekerjaan selesai sesuai nominal tagihan resmi.</li>
                            <li>Pelanggan berhak memberikan ulasan bintang dan testimoni secara jujur sesuai pengalaman yang dialami di lapangan.</li>
                        </ul>
                    </section>

                    <!-- Poin 3 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">3</span>
                            Ketentuan Khusus Mitra Penyedia Jasa
                        </h5>
                        <ul class="text-secondary small ps-3" style="line-height: 1.8;">
                            <li><strong>Integritas Pekerjaan:</strong> Mitra wajib mengedepankan etika kerja yang jujur, sopan, dan transparan mengenai rincian biaya suku cadang dan ongkos kerja.</li>
                            <li><strong>Sistem Biaya Kontak (Lead Fee):</strong> Platform membebankan biaya kontak sebesar <strong>Rp <?= number_format((float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE), 0, ',', '.') ?></strong> untuk setiap pesanan yang telah disepakati. Tidak ada pemotongan persentase atas omset atau tarif jasa yang diterima mitra.</li>
                            <li><strong>Kredit Saldo Aplikasi:</strong> Saldo dompet mitra (termasuk bonus selamat datang sebesar <strong>Rp <?= number_format((float)get_setting('welcome_bonus_amount', WELCOME_BONUS_WALLET), 0, ',', '.') ?></strong>) adalah kuota operasional digital untuk menerima pesanan dan <strong>tidak dapat dicairkan atau ditarik ke rekening tunai</strong>.</li>
                        </ul>
                    </section>

                    <!-- Poin 4 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">4</span>
                            Pemberdayaan Pekerja Mandiri Lokal (Bebas Syarat Toko / Bengkel Fisik)
                        </h5>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            Salah satu misi sosial utama <strong><?= APP_NAME ?></strong> adalah membuka lapangan kerja, memangkas angka pengangguran, dan memperluas peluang ekonomi bagi seluruh lapisan masyarakat di Kabupaten Indragiri Hulu yang memiliki keahlian jasa nyata.
                        </p>
                        <ul class="text-secondary small ps-3" style="line-height: 1.8;">
                            <li><strong>Inklusivitas & Tanpa Syarat Toko Fisik:</strong> Pekerja mandiri, tukang batu/kayu, buruh bangunan, asisten rumah tangga, pembersih kebun, montir panggilan, dan pekerja lepas <strong>TIDAK DIWAJIBKAN</strong> memiliki toko fisik, bengkel permanen, ataupun sertifikat formal untuk mendaftar dan menawarkan jasa.</li>
                            <li><strong>Verifikasi Identitas Nyata:</strong> Setiap mitra wajib mencantumkan identitas asli yang sesuai dengan KTP, domisili tempat tinggal yang jelas, serta nomor kontak WhatsApp yang dapat dihubungi pelanggan.</li>
                            <li><strong>Reputasi Berbasis Kualitas Kerja:</strong> Kepercayaan pelanggan dibangun melalui portofolio foto hasil kerja nyata, riwayat pesanan sukses, serta ulasan bintang jujur dari warga yang telah menggunakan jasa Anda.</li>
                        </ul>
                    </section>

                    <!-- Poin 5 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">5</span>
                            Pedoman Layanan Perawatan & Tindakan Medis Mandiri (Homecare)
                        </h5>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            Mengingat tingginya kebutuhan warga Indragiri Hulu akan pelayanan perawatan kesehatan di rumah (seperti infus vitamin/pemulihan, perawatan luka diabetes/pasca operasi, dan pendampingan pasca bersalin):
                        </p>
                        <ul class="text-secondary small ps-3" style="line-height: 1.8;">
                            <li><strong>Kualifikasi Tenaga Medis:</strong> Mitra yang menawarkan jasa tindakan medis (perawat/bidan mandiri) wajib memiliki latar belakang pendidikan profesi kesehatan yang sah (Ijazah D3/S1 Keperawatan/Kebidanan atau Surat Tanda Registrasi/STR yang berlaku). Mitra dapat mengunggah bukti STR untuk memperoleh lencana verifikasi tenaga profesional.</li>
                            <li><strong>Persetujuan Tindakan (Informed Consent):</strong> Setiap tindakan perawatan medis ke rumah pasien wajib didasari persetujuan sadar dari pasien atau keluarga pasien setelah mendapatkan penjelasan mengenai prosedur perawatan.</li>
                            <li><strong>Asal-Usul Cairan & Obat-Obatan:</strong> Penggunaan cairan infus, obat resep, maupun zat injeksi wajib berasal dari resep dokter resmi, anjuran fasilitas kesehatan berizin (Puskesmas/Klinik/RSUD), atau dibeli secara sah di apotek resmi. Mitra dilarang keras meracik atau memasukkan obat-obatan ilegal/terlarang tanpa instruksi medis resmi.</li>
                            <li><strong>Kedudukan Non-Faskes:</strong> <?= APP_NAME ?> berkedudukan sebagai platform penghubung informasi direktori tenaga kesehatan mandiri dan bukan merupakan Fasilitas Pelayanan Kesehatan (Faskes/Rumah Sakit/Klinik). Hubungan tindakan medis terjalin secara independen dan profesional antara tenaga medis dengan pasien/keluarga.</li>
                        </ul>
                    </section>

                    <!-- Poin 6 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">6</span>
                            Batasan Tanggung Jawab Platform (Doktrin Safe Harbor)
                        </h5>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            Sesuai ketentuan perundang-undangan Republik Indonesia dan Surat Edaran Menteri Komunikasi dan Informatika No. 5 Tahun 2016 mengenai batasan tanggung jawab Penyelenggara Sistem Elektronik (Platform Perantara Digital / <em>Safe Harbor Policy</em>):
                        </p>
                        <div class="p-3 bg-light rounded-3 border text-secondary small" style="line-height: 1.8;">
                            <i class="fa-solid fa-shield-halved text-teal me-1"></i>
                            <strong>Ketentuan Perlindungan Platform <?= APP_NAME ?>:</strong>
                            <ol class="ps-3 mt-2 mb-0">
                                <li><strong>Pemeriksaan Bersama Sebelum Pembayaran (Quality Inspection):</strong> Pelanggan diwajibkan memeriksa langsung hasil pengerjaan fisik atau tindakan jasa bersama mitra sebelum melakukan serah-terima dan pelunasan pembayaran.</li>
                                <li><strong>Batas Tanggung Jawab Operasional:</strong> <?= APP_NAME ?> tidak bertanggung jawab secara perdata maupun pidana atas kelalaian teknis di lapangan, kerusakan fisik properti, reaksi alergi/medis individual, maupun sengketa transaksi tunai yang terjadi di luar sistem aplikasi.</li>
                                <li><strong>Keadaan Kahar (Force Majeure):</strong> Pembatalan atau keterlambatan akibat bencana alam, cuaca ekstrem, kendala jalan raya, atau kondisi darurat di luar kendali wajar para pihak dibebaskan dari tuntutan ganti rugi.</li>
                            </ol>
                        </div>
                    </section>

                    <!-- Poin 7 -->
                    <section class="mb-4 pb-3 border-bottom">
                        <h5 class="fw-bold text-dark d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-teal rounded-circle px-2 py-1 text-white">7</span>
                            Pusat Bantuan, Mediasi & Musyawarah Kekeluargaan
                        </h5>
                        <p class="text-secondary small" style="line-height: 1.8;">
                            Apabila terjadi ketidaksesuaian atau aduan layanan, pelanggan dan mitra sepakat untuk menyelesaikan permasalahan secara musyawarah dan kekeluargaan yang menjunjung tinggi kearifan lokal masyarakat Indragiri Hulu. Tim Pengelola <?= APP_NAME ?> siap membantu menjembatani komunikasi melalui layanan WhatsApp resmi: <strong><a href="https://wa.me/<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?>" target="_blank" class="text-teal text-decoration-none">+<?= get_setting('admin_wa', ADMIN_PHONE_WA) ?></a></strong> atau Email: <strong><a href="mailto:<?= get_setting('admin_email', ADMIN_EMAIL) ?>" class="text-teal text-decoration-none"><?= get_setting('admin_email', ADMIN_EMAIL) ?></a></strong>.
                        </p>
                    </section>

                    <div class="text-center pt-3">
                        <a href="<?= BASE_URL ?>/" class="btn btn-teal px-4 py-2 fw-semibold">
                            <i class="fa-solid fa-check me-1"></i> Saya Memahami & Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

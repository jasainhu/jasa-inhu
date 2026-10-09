-- ========================================================
-- DATABASE SEED DATA: JASA INHU
-- Kabupaten Indragiri Hulu, Riau
-- Default demo password for all accounts: password123
-- ========================================================

-- 1. Roles
INSERT INTO roles (id, name, display_name, description) VALUES
(1, 'admin', 'Administrator', 'Pengelola penuh sistem dan moderasi platform JASA INHU'),
(2, 'pengguna', 'Masyarakat / Pengguna', 'Masyarakat pencari jasa dan pembuat permintaan pekerjaan'),
(3, 'penyedia', 'Penyedia Jasa', 'Penyedia jasa lokal yang menawarkan keahlian dan merespons pekerjaan');

-- 2. Regency (Kabupaten)
INSERT INTO regencies (id, code, name, province_name, is_active) VALUES
(1, '1402', 'Kabupaten Indragiri Hulu', 'Riau', 1);

-- 3. Districts (14 Kecamatan di Kab. Indragiri Hulu)
INSERT INTO districts (id, regency_id, code, name) VALUES
(1, 1, '140201', 'Rengat'),
(2, 1, '140202', 'Rengat Barat'),
(3, 1, '140203', 'Pasir Penyu'),
(4, 1, '140204', 'Seberida'),
(5, 1, '140205', 'Batang Cenaku'),
(6, 1, '140206', 'Batang Gansal'),
(7, 1, '140207', 'Kelayang'),
(8, 1, '140208', 'Rakit Kulim'),
(9, 1, '140209', 'Peranap'),
(10, 1, '140210', 'Batang Peranap'),
(11, 1, '140211', 'Kuala Cenaku'),
(12, 1, '140212', 'Sungai Lala'),
(13, 1, '140213', 'Lirik'),
(14, 1, '140214', 'Lubuk Batu Jaya');

-- 4. Seluruh 194 Desa & Kelurahan se-Kabupaten Indragiri Hulu (Kemendagri / BPS)
INSERT INTO villages (district_id, name, postal_code) VALUES
-- Rengat
(1, 'Kampung Besar Kota', '29315'),
(1, 'Kampung Besar Seberang', '29312'),
(1, 'Kampung Dagang', '29314'),
(1, 'Kampung Pulau', '29316'),
(1, 'Kuantan Baru', '29319'),
(1, 'Pasar Kota', '29314'),
(1, 'Pasir Kemilu', '29319'),
(1, 'Pulau Gajah', '29319'),
(1, 'Rantau Mapesai', '29319'),
(1, 'Rawa Bangun', '29319'),
(1, 'Sekip Hilir', '29312'),
(1, 'Sekip Hulu', '29313'),
(1, 'Sungai Beringin', '29319'),
(1, 'Sungai Guntung Hilir', '29319'),
(1, 'Sungai Guntung Tengah', '29319'),
(1, 'Sungai Raya', '29319'),
-- Rengat Barat
(2, 'Air Jernih', '29351'),
(2, 'Alang Kepayang', '29351'),
(2, 'Barangan', '29351'),
(2, 'Bukit Petaling', '29351'),
(2, 'Danau Baru', '29351'),
(2, 'Danau Tiga', '29351'),
(2, 'Kota Lama', '29351'),
(2, 'Pekan Heran', '29351'),
(2, 'Pematang Jaya', '29351'),
(2, 'Pematang Reba', '29351'),
(2, 'Rantau Bakung', '29351'),
(2, 'Redang', '29351'),
(2, 'Sialang Dua Dahan', '29351'),
(2, 'Sungai Baung', '29351'),
(2, 'Sungai Dawu', '29351'),
(2, 'Talang Jerinjing', '29351'),
(2, 'Tanah Datar', '29351'),
(2, 'Tanah Makmur', '29351'),
-- Pasir Penyu
(3, 'Air Molek I', '29352'),
(3, 'Air Molek II', '29352'),
(3, 'Batu Gajah', '29352'),
(3, 'Candirejo', '29352'),
(3, 'Jatirejo', '29352'),
(3, 'Kembang Harum', '29352'),
(3, 'Lembah Dusun Gading', '29352'),
(3, 'Pasir Keranji', '29352'),
(3, 'Petalongan', '29352'),
(3, 'Sekar Mawar', '29352'),
(3, 'Serumpun Jaya', '29352'),
(3, 'Tanah Merah', '29352'),
(3, 'Tanjung Gading', '29352'),
-- Seberida
(4, 'Bandar Padang', '29371'),
(4, 'Beligan', '29371'),
(4, 'Bukit Meranti', '29371'),
(4, 'Buluh Rampai', '29371'),
(4, 'Kelesa', '29371'),
(4, 'Pangkalan Kasai', '29371'),
(4, 'Payarumbai', '29371'),
(4, 'Petala Bumi', '29371'),
(4, 'Serasam', '29371'),
(4, 'Sibabat', '29371'),
(4, 'Titian Resak', '29371'),
-- Batang Cenaku
(5, 'Alim', '29372'),
(5, 'Anak Talang', '29372'),
(5, 'Aur Cina', '29372'),
(5, 'Batu Papan', '29372'),
(5, 'Bukit Lingkar', '29372'),
(5, 'Bukit Lipai', '29372'),
(5, 'Cenaku Kecil', '29372'),
(5, 'Kepayang Sari', '29372'),
(5, 'Kerubung Jaya', '29372'),
(5, 'Kuala Gading', '29372'),
(5, 'Kuala Kilan', '29372'),
(5, 'Lahai Kemuning', '29372'),
(5, 'Pataling Jaya', '29372'),
(5, 'Pejangki', '29372'),
(5, 'Pematang Manggis', '29372'),
(5, 'Punti Anai', '29372'),
(5, 'Sanglap', '29372'),
(5, 'Sipang', '29372'),
(5, 'Talang Bersemi', '29372'),
(5, 'Talang Mulya', '29372'),
-- Batang Gansal
(6, 'Belimbing', '29373'),
(6, 'Danau Rambai', '29373'),
(6, 'Penyaguan', '29373'),
(6, 'Rantau Langsat', '29373'),
(6, 'Ringin', '29373'),
(6, 'Seberida', '29373'),
(6, 'Siambul', '29373'),
(6, 'Sungai Akar', '29373'),
(6, 'Talang Lakat', '29373'),
(6, 'Usul', '29373'),
-- Kelayang
(7, 'Bongkal Malang', '29355'),
(7, 'Bukit Selanjut', '29355'),
(7, 'Dusun Tua', '29355'),
(7, 'Dusun Tua Pelang', '29355'),
(7, 'Kota Medan', '29355'),
(7, 'Pasir Beringin', '29355'),
(7, 'Pelangko', '29355'),
(7, 'Polak Pisang', '29355'),
(7, 'Pulau Sengkilo', '29355'),
(7, 'Simpang Kelayang', '29355'),
(7, 'Simpang Kota Medan', '29355'),
(7, 'Sungai Banyak Ikan', '29355'),
(7, 'Sungai Golang', '29355'),
(7, 'Sungai Kuning Benio', '29355'),
(7, 'Sungai Pasir Putih', '29355'),
(7, 'Tanjung Beludu', '29355'),
(7, 'Teluk Sejuah', '29355'),
-- Rakit Kulim
(8, 'Batu Sawar', '29356'),
(8, 'Bukit Indah', '29356'),
(8, 'Kampung Bunga', '29356'),
(8, 'Kelayang', '29356'),
(8, 'Kota Baru', '29356'),
(8, 'Kuantan Tenang', '29356'),
(8, 'Lubuk Sitarak', '29356'),
(8, 'Petonggan', '29356'),
(8, 'Rimba Seminai', '29356'),
(8, 'Sungai Ekok', '29356'),
(8, 'Talang Durian Cacar', '29356'),
(8, 'Talang Gedabu', '29356'),
(8, 'Talang Parigi', '29356'),
(8, 'Talang Pring Jaya', '29356'),
(8, 'Talang Selantai', '29356'),
(8, 'Talang Suka Maju', '29356'),
(8, 'Talang Sungai Limau', '29356'),
(8, 'Talang Sungai Parit', '29356'),
(8, 'Talang Tujuh Buah Tangga', '29356'),
-- Peranap
(9, 'Baturijal Barat', '29354'),
(9, 'Baturijal Hilir', '29354'),
(9, 'Baturijal Hulu', '29354'),
(9, 'Gumanti', '29354'),
(9, 'Katipo Pura', '29354'),
(9, 'Pandan Wangi', '29354'),
(9, 'Pauh Ranap', '29354'),
(9, 'Peranap', '29354'),
(9, 'Semelinang Darat', '29354'),
(9, 'Semelinang Tebing', '29354'),
(9, 'Serai Wangi', '29354'),
(9, 'Setako Raya', '29354'),
-- Batang Peranap
(10, 'Koto Tuo', '29357'),
(10, 'Peladangan', '29357'),
(10, 'Pematang', '29357'),
(10, 'Pematang Benteng', '29357'),
(10, 'Pesajian', '29357'),
(10, 'Puntikayu', '29357'),
(10, 'Selunak', '29357'),
(10, 'Sencano Jaya', '29357'),
(10, 'Suka Maju', '29357'),
(10, 'Sungai Aur', '29357'),
-- Kuala Cenaku
(11, 'Kuala Cenaku', '29381'),
(11, 'Kuala Mulia', '29381'),
(11, 'Pulau Gelang', '29381'),
(11, 'Pulau Jum\'at', '29381'),
(11, 'Rawa Asri', '29381'),
(11, 'Rawa Sekip', '29381'),
(11, 'Suka Jadi', '29381'),
(11, 'Tambak', '29381'),
(11, 'Tanjung Sari', '29381'),
(11, 'Teluk Sungkai', '29381'),
-- Sungai Lala
(12, 'Kelawat', '29361'),
(12, 'Kuala Lala', '29361'),
(12, 'Morong', '29361'),
(12, 'Pasir Batu Mandi', '29361'),
(12, 'Pasir Bongkal', '29361'),
(12, 'Pasir Kelampaian', '29361'),
(12, 'Pasir Selabau', '29361'),
(12, 'Perkebunan Sungai Lala', '29361'),
(12, 'Perkebunan Sungai Parit', '29361'),
(12, 'Sungai Air Putih', '29361'),
(12, 'Sungai Lala', '29361'),
(12, 'Tanjung Danau', '29361'),
-- Lirik
(13, 'Banjar Balam', '29353'),
(13, 'Gudang Batu', '29353'),
(13, 'Japura', '29353'),
(13, 'Lambang Sari I, II, III', '29353'),
(13, 'Lambang Sari IV', '29353'),
(13, 'Lambang Sari V', '29353'),
(13, 'Lirik Area', '29353'),
(13, 'Mekarsari', '29353'),
(13, 'Pasir Ringgit', '29353'),
(13, 'Pasir Sialang Jaya', '29353'),
(13, 'Redang Seko', '29353'),
(13, 'Rejosari', '29353'),
(13, 'Seko Lubuk Tigo', '29353'),
(13, 'Sidomulyo', '29353'),
(13, 'Sukajadi', '29353'),
(13, 'Sungai Sagu', '29353'),
(13, 'Wonosari', '29353'),
-- Lubuk Batu Jaya
(14, 'Air Putih', '29362'),
(14, 'Kulim Jaya', '29362'),
(14, 'Lubuk Batu Tinggal', '29362'),
(14, 'Pondok Gelugur', '29362'),
(14, 'Pontian Mekar', '29362'),
(14, 'Rimpian', '29362'),
(14, 'Sei Beras-beras', '29362'),
(14, 'Sei Seberas Hilir', '29362'),
(14, 'Tasik Juang', '29362');

-- 5. Service Categories (17 Kategori Sesuai Permintaan)
INSERT INTO service_categories (id, name, slug, icon, description, is_active, sort_order) VALUES
(1, 'Servis Motor', 'servis-motor', 'fa-motorcycle', 'Perbaikan mesin, servis berkala, ganti oli, servis ban & kelistrikan motor', 1, 1),
(2, 'Servis Mobil', 'servis-mobil', 'fa-car', 'Perbaikan mesin mobil, tune-up, ganti oli, bengkel kaki-kaki & panggilan darurat', 1, 2),
(3, 'Servis AC', 'servis-ac', 'fa-snowflake', 'Cuci AC berkala, tambah freon, perbaikan AC bocor atau tidak dingin', 1, 3),
(4, 'Elektronik', 'elektronik', 'fa-tv', 'Perbaikan TV, mesin cuci, kulkas, kipas angin dan alat elektronik rumah', 1, 4),
(5, 'Listrik', 'listrik', 'fa-bolt', 'Instalasi listrik baru, perbaikan korsleting, penambahan titik lampu & stop kontak', 1, 5),
(6, 'Bangunan', 'bangunan', 'fa-trowel-bricks', 'Tukang pasang bata, plester, renovasi rumah, pengecatan dan lantai keramik', 1, 6),
(7, 'Tukang Kayu', 'tukang-kayu', 'fa-hammer', 'Pembuatan kusen, pintu, lemari, kitchen set dan perbaikan mebel kayu', 1, 7),
(8, 'Tukang Las', 'tukang-las', 'fa-fire-burner', 'Pembuatan & servis pagar besi, kanopi, teralis, folding gate dan konstruksi baja', 1, 8),
(9, 'Pertanian & Perkebunan', 'pertanian-perkebunan', 'fa-seedling', 'Jasa panen sawit, semprot gulma, tebas tebang kebun, pupuk dan perawatan kebun sawit/karet', 1, 9),
(10, 'Angkutan', 'angkutan', 'fa-truck-pickup', 'Sewa mobil pickup pindahan rumah, angkut hasil panen sawit dan kirim barang lokal', 1, 10),
(11, 'Kebersihan', 'kebersihan', 'fa-broom', 'Potong rumput halaman, bersih-bersih rumah/kantor, kuras toren dan sedot WC', 1, 11),
(12, 'Acara & Hiburan', 'acara-hiburan', 'fa-music', 'Sewa sound system, pasang tenda tratak, kursi pesta, orgen tunggal & MC', 1, 12),
(13, 'Fotografi & Videografi', 'fotografi-videografi', 'fa-camera', 'Dokumentasi pernikahan, acara keluarga, foto wisuda, pembuatan video promosi', 1, 13),
(14, 'Teknologi', 'teknologi', 'fa-laptop-code', 'Servis komputer/laptop, servis HP, instal ulang Windows, jaringan WiFi & CCTV', 1, 14),
(15, 'Pendidikan', 'pendidikan', 'fa-graduation-cap', 'Guru les privat SD/SMP/SMA, belajar mengaji, kursus komputer dan bimbingan belajar', 1, 15),
(16, 'Jasa Rumah Tangga', 'jasa-rumah-tangga', 'fa-house-chimney-user', 'Jasa setrika, masak catering harian, perbaikan pipa air & bantuan rumah tangga', 1, 16),
(17, 'Jasa Lainnya', 'jasa-lainnya', 'fa-ellipsis', 'Berbagai kebutuhan jasa lokal spesifik lainnya di wilayah Indragiri Hulu', 1, 17);

-- 6. Demo Users (Password: password123)
-- Hash: $2y$10$bve1QmPfUJgkw947Ert/U.nqVzzA43uRlUpS.r0B2z12s.otmva96
INSERT INTO users (id, role_id, name, email, phone, password_hash, is_active, email_verified_at, created_at) VALUES
(1, 1, 'Admin Jasa Inhu', 'admin@jasainhu.id', '081268000001', '$2y$10$bve1QmPfUJgkw947Ert/U.nqVzzA43uRlUpS.r0B2z12s.otmva96', 1, NOW(), NOW()),
(2, 2, 'Budi Santoso', 'budi@gmail.com', '081268112233', '$2y$10$bve1QmPfUJgkw947Ert/U.nqVzzA43uRlUpS.r0B2z12s.otmva96', 1, NOW(), NOW()),
(3, 3, 'Ahmad Fauzi', 'ahmad.servis@jasainhu.id', '081268223344', '$2y$10$bve1QmPfUJgkw947Ert/U.nqVzzA43uRlUpS.r0B2z12s.otmva96', 1, NOW(), NOW()),
(4, 3, 'Hendra Wijaya', 'hendra.ac@jasainhu.id', '081268334455', '$2y$10$bve1QmPfUJgkw947Ert/U.nqVzzA43uRlUpS.r0B2z12s.otmva96', 1, NOW(), NOW()),
(5, 3, 'Pak Suardi Sawit', 'suardi.panen@jasainhu.id', '081268445566', '$2y$10$bve1QmPfUJgkw947Ert/U.nqVzzA43uRlUpS.r0B2z12s.otmva96', 1, NOW(), NOW());

-- 7. Profiles
INSERT INTO profiles (user_id, avatar, bio, address, district_id, village_id) VALUES
(1, NULL, 'Pengelola Pusat Layanan Jasa Inhu', 'Jl. Bupati Tulus No. 1, Rengat', 1, 1),
(2, NULL, 'Warga Pematang Reba, butuh bantuan servis rumah dan kendaraan.', 'Jl. Lintas Timur, Pematang Reba', 2, 7),
(3, NULL, 'Mekanik motor berpengalaman lebih dari 8 tahun, menerima servis motor injeksi & panggilan darurat di Rengat dan sekitarnya.', 'Jl. Veteran No. 45, Rengat', 1, 1),
(4, NULL, 'Teknisi pendingin ruangan (AC) bersertifikat, berpengalaman menangani AC Split rumah, kantor perbankan & ruko di Belilas.', 'Jl. Jend. Sudirman, Belilas', 4, 17),
(5, NULL, 'Tim jasa tenaga kerja perkebunan sawit siap borongan panen, tebas gawangan & pemupukan di Seberida dan Batang Cenaku.', 'Jl. Simpang Empat Belilas', 4, 16);

-- 8. Service Providers
INSERT INTO service_providers (id, user_id, primary_category_id, business_name, headline, description, experience_years, hourly_rate_min, hourly_rate_max, is_verified, rating_avg, reviews_count, completed_jobs, address, district_id, village_id) VALUES
(1, 3, 1, 'Bengkel Berkah Motor Rengat', 'Spesialis Servis Motor Injeksi & Panggilan Darurat Rengat', 'Kami melayani servis berkala, tune-up, ganti oli, kuras tangki, kelistrikan, reset ECU injeksi, dan panggilan mogok di jalan area Rengat dan sekitarnya.', 8, 35000, 150000, 1, 4.90, 18, 42, 'Jl. Veteran No. 45, Rengat', 1, 1),
(2, 4, 3, 'Sejuk Jaya Tehnik Belilas', 'Cuci AC, Bongkar Pasang & Perbaikan AC Rumah/Kantor Belilas', 'Melayani cuci AC bersih bergaransi, isi freon R32/R410/R22, bongkar pasang AC pindah rumah, dan perbaikan AC netes/tidak dingin wilayah Belilas dan Seberida.', 6, 75000, 250000, 1, 4.85, 12, 29, 'Jl. Jend. Sudirman, Belilas, Seberida', 4, 17),
(3, 5, 9, 'Mandiri Tani Jaya Sawit', 'Jasa Tenaga Panen, Tebas Semprot & Perawatan Kebun Sawit Inhu', 'Regu kerja kompak profesional untuk panen sawit rotasi tepat waktu, tebas tebang semak belukar, semprot gulma piringan, dan tabur pupuk borongan.', 10, 50000, 300000, 1, 4.95, 8, 35, 'Simpang 4 Belilas, Seberida', 4, 16);

-- 9. Service Areas (Wilayah jangkauan penyedia)
INSERT INTO service_areas (provider_id, district_id) VALUES
(1, 1), -- Bengkel Berkah melayani Rengat
(1, 2), -- melayani Rengat Barat
(1, 11), -- melayani Kuala Cenaku
(2, 4), -- Sejuk Jaya melayani Seberida
(2, 5), -- melayani Batang Cenaku
(2, 6), -- melayani Batang Gansal
(3, 4), -- Mandiri Tani melayani Seberida
(3, 5), -- melayani Batang Cenaku
(3, 3); -- melayani Pasir Penyu

-- 10. Sample Service Requests (Permintaan Jasa dari Masyarakat)
INSERT INTO service_requests (id, user_id, category_id, district_id, village_id, title, description, budget, address_detail, urgency, status, created_at) VALUES
(1, 2, 3, 2, 7, 'Cuci AC 2 Unit Rumah di Pematang Reba', 'Butuh teknisi AC untuk cuci 2 unit AC 1/2 PK merk Daikin dan Sharp. Sudah 4 bulan belum dicuci dan mulai terasa kurang dingin.', 160000, 'Komplek Perumahan Griya Reba Asri Blok B No. 4, Pematang Reba', 'normal', 'open', NOW()),
(2, 2, 1, 1, 1, 'Servis Motor Vario 125 Brebet & Ganti Oli di Rengat', 'Motor Vario 125 tahun 2019 kalau ditarik gas agak brebet, sekalian ganti oli mesin dan oli gardan. Bisa dikerjakan di rumah atau diantar ke bengkel sekitar Rengat.', 100000, 'Jl. Hang Lekir dekat Danau Raja, Rengat', 'urgent', 'open', NOW());

-- 11. Sample Response
INSERT INTO service_request_responses (request_id, provider_id, offer_price, estimated_duration, message, status) VALUES
(1, 2, 150000, '1-2 Jam', 'Halo Pak Budi, kami dari Sejuk Jaya Tehnik siap meluncur ke Pematang Reba besok pagi. Harga Rp 150.000 untuk 2 unit AC sudah termasuk cuci filter, blower indor-outdoor dan cek tekanan freon.', 'pending');

-- 12. Sample Reviews
INSERT INTO reviews (request_id, user_id, provider_id, rating, comment) VALUES
(1, 2, 1, 5, 'Pelayanan cepat dan mekanik ramah. Motor saya langsung enak tarikannya. Sangat direkomendasikan untuk warga Rengat!');

-- 13. Sample Notifications
INSERT INTO notifications (user_id, title, message, link, is_read) VALUES
(2, 'Penawaran Baru Masuk', 'Sejuk Jaya Tehnik memberikan penawaran untuk permintaan Cuci AC Anda.', '/user/requests.php', 0);

-- 14. Sample Banners (Promo & Pengumuman)
INSERT INTO banners (title, subtitle, badge_text, badge_color, image_url, link_url, button_text, sort_order, is_active) VALUES
('Gabung Jadi Mitra JASA INHU Gratis!', 'Punya keahlian bengkel, tukang, teknisi AC, atau jasa kebun di Indragiri Hulu? Dapatkan pelanggan lokal setiap hari.', 'PELUANG MITRA', '#0d9488', '', '/register.php', 'Daftar Sekarang', 1, 1),
('Tips Aman & Nyaman Bertransaksi Jasa', 'Pastikan pengerjaan dicek bersama sebelum pelunasan biaya. Gunakan chat WhatsApp terverifikasi untuk konfirmasi.', 'TIPS AMAN', '#2563eb', '', '/#kategori', 'Pelajari Layanan', 2, 1),
('Layanan Siaga Montir & Servis Panggilan se-Inhu', 'Motor mogok atau AC rusak di Rengat, Belilas, Air Molek, dan Peranap? Temukan teknisi terdekat dalam hitungan menit.', 'LAYANAN CEPAT', '#d97706', '', '/#kategori', 'Cari Teknisi', 3, 1);


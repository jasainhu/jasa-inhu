-- ========================================================
-- DATABASE DUMP STERIL: JASA INHU (SIAP DEPLOY / HOSTING)
-- Waktu Ekspor: 2026-10-09 07:39:53 WIB
-- Urutan Akun: #001 (Admin), #002-#012 (11 Mitra Inhu Asli)
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+07:00';

DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `id` int(11) NOT NULL auto_increment,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_group` varchar(50) default 'general',
  `description` varchar(255) default NULL,
  `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;

INSERT INTO `app_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `updated_at`) VALUES
('1', 'admin_wa', '6285126241679', 'contact', 'Nomor WhatsApp Resmi Layanan & Admin JASA INHU', '2026-10-08 21:44:52'),
('2', 'bank_name_1', 'Bank Riau Kepri Syariah', 'finance', 'Nama Bank Rekening Utama 1', '2026-10-06 01:32:39'),
('3', 'bank_acc_1', '8202167210', 'finance', 'Nomor Rekening Bank Utama 1', '2026-10-08 16:00:54'),
('4', 'bank_owner_1', 'ANGGA FAHRANDI', 'finance', 'Atas Nama Pemilik Rekening 1', '2026-10-08 16:00:54'),
('5', 'bank_name_2', 'Bank BNI', 'finance', 'Nama Bank Rekening Alternatif 2', '2026-10-08 16:00:54'),
('6', 'bank_acc_2', '1047488767', 'finance', 'Nomor Rekening Bank Alternatif 2', '2026-10-08 16:00:54'),
('7', 'bank_owner_2', 'ANGGA FAHRANDI', 'finance', 'Atas Nama Pemilik Rekening 2', '2026-10-08 16:00:54'),
('8', 'qris_info', 'Scan QRIS Langsung via WhatsApp Admin', 'finance', 'Instruksi Pembayaran QRIS', '2026-10-06 01:32:39'),
('9', 'lead_fee_amount', '2000', 'lead_fee', 'Biaya kontak per pesanan yang disepakati (Rp)', '2026-10-08 16:19:55'),
('10', 'welcome_bonus_amount', '50000', 'lead_fee', 'Bonus saldo awal gratis untuk mitra baru (Rp)', '2026-10-08 16:19:55'),
('11', 'ewallet_name', 'DANA', 'finance', 'Nama Penyedia E-Wallet / Dompet Digital', '2026-10-08 15:10:03'),
('12', 'ewallet_acc', '085378230761', 'finance', 'Nomor Akun / HP E-Wallet', '2026-10-08 16:00:54'),
('13', 'ewallet_owner', 'ANGGA FAHRANDI', 'finance', 'Atas Nama Pemilik Akun E-Wallet', '2026-10-08 16:00:54'),
('14', 'smtp_host', 'smtp.gmail.com', 'email', 'Host server SMTP Gmail', '2026-10-08 17:13:57'),
('15', 'smtp_port', '587', 'email', 'Port server SMTP (587 untuk TLS atau 465 untuk SSL)', '2026-10-08 17:51:40'),
('16', 'smtp_user', 'jasainhu@gmail.com', 'email', 'Alamat akun Gmail pengirim resmi (misal: jasainhu.official@gmail.com)', '2026-10-08 21:44:52'),
('17', 'smtp_pass', 'zwhm imrn qzud zzrk', 'email', 'Google App Password 16 karakter akun Gmail', '2026-10-08 21:56:48'),
('18', 'smtp_from_name', 'JASA INHU Resmi', 'email', 'Nama pengirim email yang tampil di inbox pengguna', '2026-10-08 17:13:57'),
('19', 'smtp_secure', 'tls', 'email', 'Tipe enkripsi SMTP (tls atau ssl)', '2026-10-08 17:13:57'),
('20', 'gmail_webhook_url', 'https://script.google.com/macros/s/AKfycbwtsDuJkQRnV6y3TI3payFkVb1g8E9jMziHKH3q2urM6uXyXUUdERR-NjB38L_K2TnQAQ/exec', 'general', NULL, '2026-10-08 22:13:17'),
('21', 'admin_email', 'jasainhu@gmail.com', 'general', NULL, '2026-10-08 21:44:52');

DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id` int(11) NOT NULL auto_increment,
  `title` varchar(150) NOT NULL,
  `subtitle` varchar(255) default NULL,
  `badge_text` varchar(50) default 'PENGUMUMAN',
  `badge_color` varchar(30) default '#0d9488',
  `image_url` varchar(255) default NULL,
  `link_url` varchar(255) default NULL,
  `button_text` varchar(50) default 'Lihat Selengkapnya',
  `show_overlay` tinyint(1) default '0',
  `position` varchar(30) NOT NULL default 'carousel',
  `sort_order` int(11) default '0',
  `is_active` tinyint(1) default '1',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `idx_banners_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8;

INSERT INTO `banners` (`id`, `title`, `subtitle`, `badge_text`, `badge_color`, `image_url`, `link_url`, `button_text`, `show_overlay`, `position`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
('1', 'Gabung Jadi Mitra JASA INHU', 'Dapatkan orderan jasa dan pelanggan lokal setiap hari se-Kabupaten Indragiri Hulu.', 'PELUANG MITRA', '#0d9488', 'uploads/banners/banner_1791129196_25b20a83.png', '', 'Daftar Sekarang', '0', 'carousel', '1', '1', '2026-10-03 14:51:27', '2026-10-04 22:53:16'),
('2', 'Tips Aman & Nyaman Bertransaksi Jasa', '', 'TIPS AMAN', '#2563eb', 'uploads/banners/banner_1791129127_e92df7e9.png', '', 'Pelajari Layanan', '0', 'carousel', '2', '1', '2026-10-03 14:51:27', '2026-10-04 22:52:07'),
('3', 'Layanan Siaga Montir & Servis Panggilan se-Inhu', 'Motor mogok atau AC rusak di Rengat, Belilas, Air Molek, dan Peranap? Temukan teknisi terdekat dalam hitungan menit.', 'LAYANAN CEPAT', '#d97706', 'uploads/banners/banner_1791129044_4a8fc59d.png', '', 'Cari Teknisi', '0', 'carousel', '3', '1', '2026-10-03 14:51:27', '2026-10-04 22:50:44'),
('5', 'Butuh Tukang Cepat & Urgent? (Siaga 24 Jam)', 'Kunci mogok, derek, tambal ban & listrik mati.', 'SIAGA 24 JAM', '#eb0000', 'uploads/banners/banner_1791485571_09468568.png', '/index.php?q=siaga#penyedia', 'Panggil Teknisi Sekarang', '0', 'side_top', '1', '1', '2026-10-04 22:40:06', '2026-10-09 01:53:22'),
('6', 'Punya Keahlian di Inhu? Buka Usaha Jasa', 'Dapatkan orderan jasa dari warga setiap hari tanpa biaya pendaftaran.', 'BUKA USAHA JASA', '#0d9488', 'uploads/banners/banner_1791485615_1f9e6e42.png', '/register.php?role=penyedia', 'Daftar Jadi Mitra Inhu', '0', 'side_bottom', '2', '1', '2026-10-04 22:40:06', '2026-10-09 01:53:35'),
('65', 'Pasang Kebutuhan Jasa Terbuka', 'Bingung memilih teknisi atau ingin membandingkan harga? Siarkan keluhan atau proyek pekerjaan Anda secara gratis. Teknisi & bengkel terverifikasi di sekitarmu akan mengajukan penawaran harga terbaik!', 'Tender Kilat & Siaran Warga Inhu', '#f59e0b', 'uploads/banners/banner_1791382158_2438c653.png', '/tender.php', 'Pasang Kebutuhan Sekarang', '0', 'tender', '1', '1', '2026-10-07 20:47:41', '2026-10-07 21:09:53');

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL auto_increment,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) default '0',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_msg_conv` (`conversation_id`),
  KEY `idx_msg_sender` (`sender_id`),
  KEY `idx_msg_receiver` (`receiver_id`),
  KEY `idx_msg_read` (`is_read`),
  KEY `idx_msg_time` (`created_at`),
  CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `conversations`;
CREATE TABLE `conversations` (
  `id` int(11) NOT NULL auto_increment,
  `user_one_id` int(11) NOT NULL,
  `user_two_id` int(11) NOT NULL,
  `provider_id` int(11) default NULL,
  `last_message` text,
  `last_message_at` datetime default NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `uq_conversation_users` (`user_one_id`,`user_two_id`),
  KEY `idx_conv_user_one` (`user_one_id`),
  KEY `idx_conv_user_two` (`user_two_id`),
  KEY `idx_conv_provider` (`provider_id`),
  KEY `idx_conv_last_time` (`last_message_at`),
  CONSTRAINT `fk_conv_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conv_user_one` FOREIGN KEY (`user_one_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conv_user_two` FOREIGN KEY (`user_two_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `districts`;
CREATE TABLE `districts` (
  `id` int(11) NOT NULL auto_increment,
  `regency_id` int(11) NOT NULL,
  `code` varchar(20) default NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_districts_regency` (`regency_id`),
  CONSTRAINT `fk_districts_regency` FOREIGN KEY (`regency_id`) REFERENCES `regencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8;

INSERT INTO `districts` (`id`, `regency_id`, `code`, `name`, `created_at`) VALUES
('1', '1', '140201', 'Rengat', '2026-10-03 11:21:59'),
('2', '1', '140202', 'Rengat Barat', '2026-10-03 11:21:59'),
('3', '1', '140203', 'Pasir Penyu', '2026-10-03 11:21:59'),
('4', '1', '140204', 'Seberida', '2026-10-03 11:21:59'),
('5', '1', '140205', 'Batang Cenaku', '2026-10-03 11:21:59'),
('6', '1', '140206', 'Batang Gansal', '2026-10-03 11:21:59'),
('7', '1', '140207', 'Kelayang', '2026-10-03 11:21:59'),
('8', '1', '140208', 'Rakit Kulim', '2026-10-03 11:21:59'),
('9', '1', '140209', 'Peranap', '2026-10-03 11:21:59'),
('10', '1', '140210', 'Batang Peranap', '2026-10-03 11:21:59'),
('11', '1', '140211', 'Kuala Cenaku', '2026-10-03 11:21:59'),
('12', '1', '140212', 'Sungai Lala', '2026-10-03 11:21:59'),
('13', '1', '140213', 'Lirik', '2026-10-03 11:21:59'),
('14', '1', '140214', 'Lubuk Batu Jaya', '2026-10-03 11:21:59');

DROP TABLE IF EXISTS `email_logs`;
CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL auto_increment,
  `recipient_email` varchar(150) NOT NULL,
  `recipient_name` varchar(150) default NULL,
  `subject` varchar(255) NOT NULL,
  `body_text` text NOT NULL,
  `status` enum('sent','failed','simulated') default 'sent',
  `error_message` text,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_recipient` (`recipient_email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8;

INSERT INTO `email_logs` (`id`, `recipient_email`, `recipient_name`, `subject`, `body_text`, `status`, `error_message`, `created_at`) VALUES
('1', 'testuser@example.com', 'Warga Uji Coba', 'Kode Verifikasi Akun JASA INHU: 789012', 'Halo Warga Uji Coba,\n\nKode verifikasi Anda: 789012\nTautan: http://127.0.0.1:8000/verify.php?token=abc123xyz\n', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:19:18'),
('2', 'warga1791454836@test.id', 'Warga Baru Rengat 1791454836', 'Kode Verifikasi Akun JASA INHU: 743538', 'Halo Warga Baru Rengat 1791454836,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 743538\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=4b30fd88bce0be7c405392187656a688\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:20:36'),
('3', 'montir1791454836@test.id', 'Montir Baru Inhu 1791454836', 'Kode Verifikasi Akun JASA INHU: 788362', 'Halo Montir Baru Inhu 1791454836,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 788362\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=4fc6dff40285a0efa4ed58c8c66fbf23\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:20:36'),
('4', 'verif1791454849@gmail.com', 'Test Verifikasi Inhu 1791454849', 'Kode Verifikasi Akun JASA INHU: 378646', 'Halo Test Verifikasi Inhu 1791454849,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 378646\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=348d2d228d1e08c2290b78d207f13321\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:20:49'),
('5', 'verif1791454849@gmail.com', 'Test Verifikasi Inhu 1791454849', 'Kode Verifikasi Akun JASA INHU: 741399', 'Halo Test Verifikasi Inhu 1791454849,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 741399\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=40a528f2c89b72e0bc615a6f50d158e3\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:20:49'),
('6', 'warga1791455181@test.id', 'Warga Baru Rengat 1791455181', 'Kode Verifikasi Akun JASA INHU: 936924', 'Halo Warga Baru Rengat 1791455181,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 936924\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=3d0b192a0d299a21480d5c23154504bd\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:26:21'),
('7', 'montir1791455181@test.id', 'Montir Baru Inhu 1791455181', 'Kode Verifikasi Akun JASA INHU: 928290', 'Halo Montir Baru Inhu 1791455181,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 928290\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=75a4a7a437b642ed2af4b93f3ef2fbcf\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:26:21'),
('8', 'verif1791455182@gmail.com', 'Test Verifikasi Inhu 1791455182', 'Kode Verifikasi Akun JASA INHU: 766324', 'Halo Test Verifikasi Inhu 1791455182,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 766324\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=35fd02a0d09036c0f2d97dd852421ba9\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:26:22'),
('9', 'verif1791455182@gmail.com', 'Test Verifikasi Inhu 1791455182', 'Kode Verifikasi Akun JASA INHU: 592432', 'Halo Test Verifikasi Inhu 1791455182,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 592432\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=2bed1facf618c83c564565a9eb01acb4\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:26:22'),
('10', 'testuser@example.com', 'Warga Uji Coba', 'Kode Verifikasi Akun JASA INHU: 789012', 'Halo Warga Uji Coba,\n\nKode verifikasi Anda: 789012\nTautan: http://127.0.0.1:8000/verify.php?token=abc123xyz\n', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:26:22'),
('11', 'warga1791455222@test.id', 'Warga Baru Rengat 1791455222', 'Kode Verifikasi Akun JASA INHU: 652844', 'Halo Warga Baru Rengat 1791455222,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 652844\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=ebf935cf267d189321aafb7c618e2904\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:27:02'),
('12', 'montir1791455222@test.id', 'Montir Baru Inhu 1791455222', 'Kode Verifikasi Akun JASA INHU: 645008', 'Halo Montir Baru Inhu 1791455222,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 645008\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=765eb98342203cdaaf32e418d8979848\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:27:03'),
('13', 'verif1791455224@gmail.com', 'Test Verifikasi Inhu 1791455224', 'Kode Verifikasi Akun JASA INHU: 471257', 'Halo Test Verifikasi Inhu 1791455224,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 471257\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=dc7da2f706a8cfa30e5e2016272d582e\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:27:04'),
('14', 'verif1791455224@gmail.com', 'Test Verifikasi Inhu 1791455224', 'Kode Verifikasi Akun JASA INHU: 674476', 'Halo Test Verifikasi Inhu 1791455224,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 674476\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=f8a279f3090effb7028b05cedcca4be3\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:27:04'),
('15', 'mufidasari16@gmail.com', 'wirsas', 'Kode Verifikasi Akun JASA INHU: 450162', 'Halo wirsas,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 450162\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://127.0.0.1:8000/verify.php?token=e65b63ffe00b25a44ba5b6b93e0e9057\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'simulated', 'SMTP Gmail belum dikonfigurasi di Pengaturan Admin. Email dicatat dalam mode simulasi.', '2026-10-08 17:30:06'),
('16', 'mufidasari16@gmail.com', 'wirsas', 'Kode Verifikasi Akun JASA INHU: 781690', 'Halo wirsas,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 781690\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://127.0.0.1:8000/verify.php?token=1d21b0abd3b618746949c9a8e64a4367\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'failed', 'Gagal terhubung ke server SMTP smtp.gmail.com:587 (A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond)', '2026-10-08 17:39:39'),
('17', 'mufidasari16@gmail.com', 'wirsas', 'Kode Verifikasi Akun JASA INHU: 411132', 'Halo wirsas,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 411132\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://127.0.0.1:8000/verify.php?token=ccfe4e0f9a400bff9aff6c5bf1898b3e\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'failed', 'Gagal terhubung ke server SMTP smtp.gmail.com:587 (A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond)', '2026-10-08 17:40:52'),
('18', 'anggababang@gmail.com', NULL, 'Test Manual Script', 'Test', 'failed', 'Gagal terhubung ke server SMTP smtp.gmail.com:587 (A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond)', '2026-10-08 17:48:10'),
('19', 'warga1791457243@test.id', 'Warga Baru Rengat 1791457243', 'Kode Verifikasi Akun JASA INHU: 485122', 'Halo Warga Baru Rengat 1791457243,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 485122\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=cd1f90edb3dea5b9759c7afa3b341be0\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'failed', 'Port SMTP smtp.gmail.com:587 diblokir oleh provider internet (ISP) lokal Anda (Error 10060 Timeout). Gunakan opsi Webhook HTTPS Google Apps Script untuk pengiriman bebas blokir.', '2026-10-08 18:00:46'),
('20', 'montir1791457243@test.id', 'Montir Baru Inhu 1791457243', 'Kode Verifikasi Akun JASA INHU: 781461', 'Halo Montir Baru Inhu 1791457243,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 781461\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=d273d52fc56ca4f5d69fe9441b3bec07\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'failed', 'Port SMTP smtp.gmail.com:587 diblokir oleh provider internet (ISP) lokal Anda (Error 10060 Timeout). Gunakan opsi Webhook HTTPS Google Apps Script untuk pengiriman bebas blokir.', '2026-10-08 18:00:49'),
('21', 'admin@jasainhu.id', 'Duplikat User', 'Kode Verifikasi Akun JASA INHU: 645084', 'Halo Duplikat User,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 645084\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=95feec8095b1758e0020408361988542\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'failed', 'Port SMTP smtp.gmail.com:587 diblokir oleh provider internet (ISP) lokal Anda (Error 10060 Timeout). Gunakan opsi Webhook HTTPS Google Apps Script untuk pengiriman bebas blokir.', '2026-10-08 18:00:52'),
('22', 'verif1791457252@gmail.com', 'Test Verifikasi Inhu 1791457252', 'Kode Verifikasi Akun JASA INHU: 624872', 'Halo Test Verifikasi Inhu 1791457252,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 624872\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=9559fa584f20fb794ab5e3d6617e53f5\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'failed', 'Port SMTP smtp.gmail.com:587 diblokir oleh provider internet (ISP) lokal Anda (Error 10060 Timeout). Gunakan opsi Webhook HTTPS Google Apps Script untuk pengiriman bebas blokir.', '2026-10-08 18:00:55'),
('23', 'verif1791457252@gmail.com', 'Test Verifikasi Inhu 1791457252', 'Kode Verifikasi Akun JASA INHU: 653465', 'Halo Test Verifikasi Inhu 1791457252,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 653465\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=ae2610f2ad04997a0911f4b2f08216fc\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'failed', 'Port SMTP smtp.gmail.com:587 diblokir oleh provider internet (ISP) lokal Anda (Error 10060 Timeout). Gunakan opsi Webhook HTTPS Google Apps Script untuk pengiriman bebas blokir.', '2026-10-08 18:00:58'),
('24', 'anggababang@gmail.com', 'Admin Jasa Inhu', 'Uji Coba Sistem JASA INHU - Integrasi Penuh', 'Pengiriman Berhasil! Sistem email JASA INHU kini terhubung sempurna.', 'failed', 'Webhook Google HTTP Error 404: <!DOCTYPE html><html lang=\"id\"><head><script nonce=\"_2FKbJ8jX92sU-xbwz1fgw\">window[\'ppConfig\'] = {productName: \'26981ed0d57bbad37e728ff58134270c\', del', '2026-10-08 18:21:29'),
('25', 'anggababang@gmail.com', NULL, 'Test System Email via Webhook', 'Testing send_system_email', 'sent', NULL, '2026-10-08 18:23:15'),
('26', 'anggababang@gmail.com', 'Admin Uji Coba', 'Uji Coba Pengiriman Email Otomatis JASA INHU', 'Halo Admin,\n\nIni adalah email uji coba dari sistem JASA INHU untuk memastikan koneksi pengiriman email bekerja dengan baik.\n\nKode sample: 123456\n', 'sent', NULL, '2026-10-08 18:28:24'),
('27', 'mufidasari16@gmail.com', 'wirsas', 'Kode Verifikasi Akun JASA INHU: 637776', 'Halo wirsas,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 637776\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://127.0.0.1:8000/verify.php?token=9b892039aa03697999a76a9726bf0d21\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-08 18:30:10'),
('28', 'mufidasari16@gmail.com', 'wirsas', 'Kode Verifikasi Akun JASA INHU: 237288', 'Halo wirsas,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 237288\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://127.0.0.1:8000/verify.php?token=b24ba7b1e5a5f5aaaec56a2671a259b9\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-08 18:32:35'),
('29', 'verif1791459596@gmail.com', 'Test Verifikasi Inhu 1791459596', 'Kode Verifikasi Akun JASA INHU: 699540', 'Halo Test Verifikasi Inhu 1791459596,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 699540\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=716d74a0487424ca20ca2f98b353a8a7\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-08 18:39:58'),
('30', 'verif1791459596@gmail.com', 'Test Verifikasi Inhu 1791459596', 'Kode Verifikasi Akun JASA INHU: 110112', 'Halo Test Verifikasi Inhu 1791459596,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 110112\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=8fc52c920d5c46d9ca6d22abe61aeb23\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'failed', 'Gagal terhubung ke Webhook Google: Operation timed out after 15876 milliseconds with 0 bytes received', '2026-10-08 18:40:23'),
('31', 'verif1791460508@gmail.com', 'Test Verifikasi Inhu 1791460508', 'Kode Verifikasi Akun JASA INHU: 933334', 'Halo Test Verifikasi Inhu 1791460508,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 933334\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=6a5b5279676d150e57f569474e51f1aa\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-08 18:55:12'),
('32', 'verif1791460508@gmail.com', 'Test Verifikasi Inhu 1791460508', 'Kode Verifikasi Akun JASA INHU: 424724', 'Halo Test Verifikasi Inhu 1791460508,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 424724\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=79789f56ac88ba8e0ac6a43add6f00fb\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-08 18:55:18'),
('33', 'jasainhu@gmail.com', 'Admin Uji Coba', 'Uji Coba Pengiriman Email Otomatis JASA INHU', 'Halo Admin,\n\nIni adalah email uji coba dari sistem JASA INHU untuk memastikan koneksi pengiriman email bekerja dengan baik.\n\nKode sample: 123456\n', 'sent', NULL, '2026-10-08 21:57:27'),
('34', 'jasainhu@gmail.com', 'Admin Uji Coba', 'Uji Coba Pengiriman Email Otomatis JASA INHU', 'Halo Admin,\n\nIni adalah email uji coba dari sistem JASA INHU untuk memastikan koneksi pengiriman email bekerja dengan baik.\n\nKode sample: 123456\n', 'sent', NULL, '2026-10-08 22:14:07'),
('35', 'warga1791484033@test.id', 'Warga Baru Rengat 1791484033', 'Kode Verifikasi Akun JASA INHU: 897853', 'Halo Warga Baru Rengat 1791484033,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 897853\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=00eeaa6c99d32bc37895b0e4fcca3e40\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-09 01:27:15'),
('36', 'montir1791484033@test.id', 'Montir Baru Inhu 1791484033', 'Kode Verifikasi Akun JASA INHU: 513787', 'Halo Montir Baru Inhu 1791484033,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 513787\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=5e81d1ceffdbcebda37bfd6089c88d87\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-09 01:27:17'),
('37', 'admin@jasainhu.id', 'Duplikat User', 'Kode Verifikasi Akun JASA INHU: 469868', 'Halo Duplikat User,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 469868\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=e3e8b729f5077c3bbb12c0336284097e\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-09 01:27:19'),
('38', 'warga1791484522@test.id', 'Warga Baru Rengat 1791484522', 'Kode Verifikasi Akun JASA INHU: 773122', 'Halo Warga Baru Rengat 1791484522,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 773122\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=64e8c1361abcbfd38c2fd945f2eec7a6\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-09 01:35:25'),
('39', 'montir1791484522@test.id', 'Montir Baru Inhu 1791484522', 'Kode Verifikasi Akun JASA INHU: 541988', 'Halo Montir Baru Inhu 1791484522,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 541988\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=3c36a03febe974220f9e1b06a05755c1\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-09 01:35:29'),
('40', 'warga1791484793@test.id', 'Warga Baru Rengat 1791484793', 'Kode Verifikasi Akun JASA INHU: 422886', 'Halo Warga Baru Rengat 1791484793,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 422886\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=6e6cf7636ac6806bc927f9988b676bd1\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-09 01:39:56'),
('41', 'montir1791484793@test.id', 'Montir Baru Inhu 1791484793', 'Kode Verifikasi Akun JASA INHU: 199943', 'Halo Montir Baru Inhu 1791484793,\n\nTerima kasih telah mendaftar di platform JASA INHU.\n\nKode verifikasi akun Anda adalah: 199943\n\nAtau Anda dapat langsung klik tautan aktivasi instan berikut:\nhttp://localhost/verify.php?token=8e16df69f5891555015e76ceeed34151\n\nKode dan tautan berlaku selama 15 menit.\n\nDemi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\nSalam hangat,\nTim JASA INHU', 'sent', NULL, '2026-10-09 01:40:05');

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) default NULL,
  `is_read` tinyint(1) default '0',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_notifications_user` (`user_id`,`is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `profiles`;
CREATE TABLE `profiles` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `avatar` varchar(255) default NULL,
  `bio` text,
  `gender` varchar(20) default NULL,
  `birth_date` date default NULL,
  `address` text,
  `district_id` int(11) default NULL,
  `village_id` int(11) default NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_profiles_district` (`district_id`),
  KEY `idx_profiles_village` (`village_id`),
  CONSTRAINT `fk_profiles_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profiles_village` FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=278 DEFAULT CHARSET=utf8;

INSERT INTO `profiles` (`id`, `user_id`, `avatar`, `bio`, `gender`, `birth_date`, `address`, `district_id`, `village_id`, `created_at`, `updated_at`) VALUES
('1', '1', NULL, 'Admin Utama', NULL, NULL, 'Simpang Bom', '3', '12', '2026-10-03 11:21:59', '2026-10-08 22:01:37'),
('267', '2', NULL, 'Spesialis Servis Injeksi & Panggilan Motor Mogok 24 Jam di Rengat', NULL, NULL, 'Jl. Raya Lintas Utama', '1', NULL, '2026-10-09 01:27:56', NULL),
('268', '3', NULL, 'Cuci AC Bersih, Bongkar Pasang & Tambah Freon Bergaransi di Belilas', NULL, NULL, 'Jl. Raya Lintas Utama', '4', NULL, '2026-10-09 01:27:56', NULL),
('269', '4', NULL, 'Pembuatan Kanopi Baja Ringan, Pagar Besi Minimalis & Teralis Pintu', NULL, NULL, 'Jl. Raya Lintas Utama', '3', NULL, '2026-10-09 01:27:56', NULL),
('270', '5', NULL, 'Pengeboran Sumur Air Bersih Bebas Karat & Servis Pompa Air', NULL, NULL, 'Jl. Raya Lintas Utama', '2', NULL, '2026-10-09 01:27:56', NULL),
('271', '6', NULL, 'Sewa Pickup Harian/Borongan, Angkut Buah Sawit & Pindahan Rumah', NULL, NULL, 'Jl. Raya Lintas Utama', '4', NULL, '2026-10-09 01:27:56', NULL),
('272', '7', NULL, 'Ahli Pasang Keramik, Plamir, Cat Rumah, Atap Bocor & Renovasi Total', NULL, NULL, 'Jl. Raya Utama Wilayah Inhu', '1', NULL, '2026-10-09 01:34:42', NULL),
('273', '8', NULL, 'Instalasi Listrik Rumah, Perbaikan Konsleting & MCB Anjlok 24 Jam', NULL, NULL, 'Jl. Raya Utama Wilayah Inhu', '2', NULL, '2026-10-09 01:34:42', NULL),
('274', '9', NULL, 'Tenaga Babat Rumput Kebun, Semprot Gulma, Dodos Panen & Pruning', NULL, NULL, 'Jl. Raya Utama Wilayah Inhu', '6', NULL, '2026-10-09 01:34:42', NULL),
('275', '10', NULL, 'Dokumentasi Pesta Pernikahan, Foto Adat Melayu, Wisuda & Video Drone', NULL, NULL, 'Jl. Raya Utama Wilayah Inhu', '2', NULL, '2026-10-09 01:34:42', NULL),
('276', '11', NULL, 'Make Up Artist Pengantin Melayu, Wisuda, Lamaran & Sewa Baju Adat', NULL, NULL, 'Jl. Raya Utama Wilayah Inhu', '3', NULL, '2026-10-09 01:34:42', NULL),
('277', '12', NULL, 'Rawat Luka Diabetes, Pasang Selang NGT/Kateter & Perawatan Lansia', NULL, NULL, 'Jl. Raya Utama Wilayah Inhu', '1', NULL, '2026-10-09 01:34:42', NULL);

DROP TABLE IF EXISTS `provider_discussions`;
CREATE TABLE `provider_discussions` (
  `id` int(11) NOT NULL auto_increment,
  `provider_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) default NULL,
  `message` text NOT NULL,
  `is_provider_reply` tinyint(1) default '0',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_disc_provider` (`provider_id`),
  KEY `idx_disc_user` (`user_id`),
  KEY `idx_disc_parent` (`parent_id`),
  CONSTRAINT `fk_disc_parent` FOREIGN KEY (`parent_id`) REFERENCES `provider_discussions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_disc_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_disc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `provider_portfolios`;
CREATE TABLE `provider_portfolios` (
  `id` int(11) NOT NULL auto_increment,
  `provider_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text,
  `image_before` varchar(255) default NULL,
  `image_after` varchar(255) NOT NULL,
  `service_date` date default NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `fk_portfolios_provider` (`provider_id`),
  CONSTRAINT `fk_portfolios_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8;

INSERT INTO `provider_portfolios` (`id`, `provider_id`, `title`, `description`, `image_before`, `image_after`, `service_date`, `created_at`) VALUES
('6', '1', 'Perbaikan Mesin Injeksi Vario 150 Brebet & Mati Total', NULL, 'uploads/portfolios/motor_before.jpg', 'uploads/portfolios/motor_after.jpg', NULL, '2026-10-09 01:34:41'),
('7', '2', 'Cuci Bersih Total & Perbaikan AC Netes Air di Perumahan Belilas', NULL, 'uploads/portfolios/ac_before.jpg', 'uploads/portfolios/ac_after.jpg', NULL, '2026-10-09 01:34:41'),
('8', '3', 'Pemasangan Kanopi Baja Ringan & Atap Spandek Rumah 6x5 Meter', NULL, 'uploads/portfolios/kanopi_before.jpg', 'uploads/portfolios/kanopi_after.jpg', NULL, '2026-10-09 01:34:41'),
('9', '4', 'Pengeboran Sumur Kedalaman 45 Meter & Pemasangan Jetpump Air Jernih', NULL, 'uploads/portfolios/sumur_before.jpg', 'uploads/portfolios/sumur_after.jpg', NULL, '2026-10-09 01:34:41'),
('10', '6', 'Pengecatan Ulang & Plamir Dinding Rumah Tipe 45 di Kampung Dagang', NULL, 'uploads/portfolios/cat_before.jpg', 'uploads/portfolios/cat_after.jpg', NULL, '2026-10-09 01:34:42'),
('11', '7', 'Perbaikan Jalur Konsleting & Penataan Ulang Panel MCB Rumah', NULL, 'uploads/portfolios/listrik_before.jpg', 'uploads/portfolios/listrik_after.jpg', NULL, '2026-10-09 01:34:42'),
('12', '8', 'Pembersihan Babat Rumput Liar Kebun Sawit 4 Hektar di Batang Gansal', NULL, 'uploads/portfolios/sawit_before.jpg', 'uploads/portfolios/sawit_after.jpg', NULL, '2026-10-09 01:34:42'),
('13', '9', 'Dokumentasi Resepsi Pernikahan Adat Melayu di Gedung Dang Purnama Rengat', NULL, 'uploads/portfolios/wedding_before.jpg', 'uploads/portfolios/wedding_after.jpg', NULL, '2026-10-09 01:34:42');

DROP TABLE IF EXISTS `provider_wallet_transactions`;
CREATE TABLE `provider_wallet_transactions` (
  `id` int(11) NOT NULL auto_increment,
  `provider_id` int(11) NOT NULL,
  `type` enum('topup','lead_fee','bonus','refund','adjustment') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `reference_id` int(11) default NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_pwt_provider` (`provider_id`),
  KEY `idx_pwt_type` (`type`),
  KEY `idx_pwt_created` (`created_at`),
  CONSTRAINT `fk_pwt_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `regencies`;
CREATE TABLE `regencies` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `province_name` varchar(100) NOT NULL default 'Riau',
  `is_active` tinyint(1) default '1',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO `regencies` (`id`, `code`, `name`, `province_name`, `is_active`, `created_at`) VALUES
('1', '1402', 'Kabupaten Indragiri Hulu', 'Riau', '1', '2026-10-03 11:21:59');

DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL auto_increment,
  `reporter_user_id` int(11) NOT NULL,
  `reported_user_id` int(11) NOT NULL,
  `request_id` int(11) default NULL,
  `reason` varchar(200) NOT NULL,
  `details` text,
  `status` varchar(30) default 'pending',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_reports_status` (`status`),
  KEY `fk_reports_reporter` (`reporter_user_id`),
  KEY `fk_reports_reported` (`reported_user_id`),
  KEY `fk_reports_request` (`request_id`),
  CONSTRAINT `fk_reports_reported` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_request` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL auto_increment,
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text,
  `photo_url` varchar(255) default NULL,
  `tags` text,
  `reply_text` text,
  `replied_at` timestamp NULL default NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_reviews_provider` (`provider_id`),
  KEY `idx_reviews_user` (`user_id`),
  KEY `fk_reviews_request` (`request_id`),
  CONSTRAINT `fk_reviews_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_request` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` varchar(255) default NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `created_at`) VALUES
('1', 'admin', 'Administrator', 'Pengelola penuh sistem dan moderasi platform JASA INHU', '2026-10-03 11:21:59'),
('2', 'pengguna', 'Masyarakat / Pengguna', 'Masyarakat pencari jasa dan pembuat permintaan pekerjaan', '2026-10-03 11:21:59'),
('3', 'penyedia', 'Penyedia Jasa', 'Penyedia jasa lokal yang menawarkan keahlian dan merespons pekerjaan', '2026-10-03 11:21:59');

DROP TABLE IF EXISTS `service_areas`;
CREATE TABLE `service_areas` (
  `id` int(11) NOT NULL auto_increment,
  `provider_id` int(11) NOT NULL,
  `district_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `uq_provider_district` (`provider_id`,`district_id`),
  KEY `idx_areas_district` (`district_id`),
  CONSTRAINT `fk_areas_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_areas_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8;

INSERT INTO `service_areas` (`id`, `provider_id`, `district_id`, `created_at`) VALUES
('2', '1', '1', '2026-10-09 01:34:41'),
('3', '1', '2', '2026-10-09 01:34:41'),
('4', '1', '11', '2026-10-09 01:34:41'),
('5', '2', '4', '2026-10-09 01:34:41'),
('6', '2', '5', '2026-10-09 01:34:41'),
('7', '2', '6', '2026-10-09 01:34:41'),
('8', '3', '3', '2026-10-09 01:34:41'),
('9', '3', '7', '2026-10-09 01:34:41'),
('10', '3', '12', '2026-10-09 01:34:41'),
('11', '3', '13', '2026-10-09 01:34:41'),
('12', '4', '2', '2026-10-09 01:34:41'),
('13', '4', '1', '2026-10-09 01:34:41'),
('14', '4', '4', '2026-10-09 01:34:41'),
('15', '5', '4', '2026-10-09 01:34:41'),
('16', '5', '5', '2026-10-09 01:34:41'),
('17', '5', '6', '2026-10-09 01:34:41'),
('18', '5', '2', '2026-10-09 01:34:41'),
('19', '6', '1', '2026-10-09 01:34:42'),
('20', '6', '2', '2026-10-09 01:34:42'),
('21', '6', '11', '2026-10-09 01:34:42'),
('22', '7', '2', '2026-10-09 01:34:42'),
('23', '7', '1', '2026-10-09 01:34:42'),
('24', '7', '13', '2026-10-09 01:34:42'),
('25', '7', '3', '2026-10-09 01:34:42'),
('26', '8', '6', '2026-10-09 01:34:42'),
('27', '8', '4', '2026-10-09 01:34:42'),
('28', '8', '5', '2026-10-09 01:34:42'),
('29', '9', '1', '2026-10-09 01:34:42'),
('30', '9', '2', '2026-10-09 01:34:42'),
('31', '9', '3', '2026-10-09 01:34:42'),
('32', '9', '4', '2026-10-09 01:34:42'),
('33', '9', '13', '2026-10-09 01:34:42'),
('34', '10', '3', '2026-10-09 01:34:42'),
('35', '10', '12', '2026-10-09 01:34:42'),
('36', '10', '7', '2026-10-09 01:34:42'),
('37', '10', '9', '2026-10-09 01:34:42'),
('38', '11', '1', '2026-10-09 01:34:42'),
('39', '11', '2', '2026-10-09 01:34:42'),
('40', '11', '11', '2026-10-09 01:34:42');

DROP TABLE IF EXISTS `service_categories`;
CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `icon` varchar(100) default 'fa-wrench',
  `description` varchar(255) default NULL,
  `is_active` tinyint(1) default '1',
  `sort_order` int(11) default '0',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_categories_active` (`is_active`),
  KEY `idx_categories_order` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

INSERT INTO `service_categories` (`id`, `name`, `slug`, `icon`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', 'Servis Motor', 'servis-motor', 'fa-motorcycle', 'Perbaikan mesin, servis berkala, ganti oli, servis ban & kelistrikan motor', '1', '1', '2026-10-03 11:21:59', NULL),
('2', 'Servis Mobil', 'servis-mobil', 'fa-car', 'Perbaikan mesin mobil, tune-up, ganti oli, bengkel kaki-kaki & panggilan darurat', '1', '2', '2026-10-03 11:21:59', NULL),
('3', 'Servis AC', 'servis-ac', 'fa-snowflake', 'Cuci AC berkala, tambah freon, perbaikan AC bocor atau tidak dingin', '1', '3', '2026-10-03 11:21:59', NULL),
('4', 'Elektronik', 'elektronik', 'fa-tv', 'Perbaikan TV, mesin cuci, kulkas, kipas angin dan alat elektronik rumah', '1', '4', '2026-10-03 11:21:59', NULL),
('5', 'Listrik', 'listrik', 'fa-bolt', 'Instalasi listrik baru, perbaikan korsleting, penambahan titik lampu & stop kontak', '1', '5', '2026-10-03 11:21:59', NULL),
('6', 'Bangunan', 'bangunan', 'fa-trowel-bricks', 'Tukang pasang bata, plester, renovasi rumah, pengecatan dan lantai keramik', '1', '6', '2026-10-03 11:21:59', NULL),
('7', 'Tukang Kayu', 'tukang-kayu', 'fa-hammer', 'Pembuatan kusen, pintu, lemari, kitchen set dan perbaikan mebel kayu', '1', '7', '2026-10-03 11:21:59', NULL),
('8', 'Tukang Las', 'tukang-las', 'fa-fire-burner', 'Pembuatan & servis pagar besi, kanopi, teralis, folding gate dan konstruksi baja', '1', '8', '2026-10-03 11:21:59', NULL),
('9', 'Pertanian & Perkebunan', 'pertanian-perkebunan', 'fa-seedling', 'Jasa panen sawit, semprot gulma, tebas tebang kebun, pupuk dan perawatan kebun sawit/karet', '1', '9', '2026-10-03 11:21:59', NULL),
('10', 'Angkutan', 'angkutan', 'fa-truck-pickup', 'Sewa mobil pickup pindahan rumah, angkut hasil panen sawit dan kirim barang lokal', '1', '10', '2026-10-03 11:21:59', NULL),
('11', 'Kebersihan', 'kebersihan', 'fa-broom', 'Potong rumput halaman, bersih-bersih rumah/kantor, kuras toren dan sedot WC', '1', '11', '2026-10-03 11:21:59', NULL),
('12', 'Acara & Hiburan', 'acara-hiburan', 'fa-music', 'Sewa sound system, pasang tenda tratak, kursi pesta, orgen tunggal & MC', '1', '12', '2026-10-03 11:21:59', NULL),
('13', 'Fotografi & Videografi', 'fotografi-videografi', 'fa-camera', 'Dokumentasi pernikahan, acara keluarga, foto wisuda, pembuatan video promosi', '1', '13', '2026-10-03 11:21:59', NULL),
('14', 'Teknologi', 'teknologi', 'fa-laptop-code', 'Servis komputer/laptop, servis HP, instal ulang Windows, jaringan WiFi & CCTV', '1', '14', '2026-10-03 11:21:59', NULL),
('15', 'Pendidikan', 'pendidikan', 'fa-graduation-cap', 'Guru les privat SD/SMP/SMA, belajar mengaji, kursus komputer dan bimbingan belajar', '1', '15', '2026-10-03 11:21:59', NULL),
('16', 'Jasa Rumah Tangga', 'jasa-rumah-tangga', 'fa-house-chimney-user', 'Jasa setrika, masak catering harian, perbaikan pipa air & bantuan rumah tangga', '1', '16', '2026-10-03 11:21:59', NULL),
('17', 'Jasa Lainnya', 'jasa-lainnya', 'fa-ellipsis', 'Berbagai kebutuhan jasa lokal spesifik lainnya di wilayah Indragiri Hulu', '1', '17', '2026-10-03 11:21:59', NULL),
('18', 'Medical', 'medical', 'fa-wrench', 'Jasa Kesehatan dan Perawatan', '1', '0', '2026-10-08 16:59:15', NULL);

DROP TABLE IF EXISTS `service_providers`;
CREATE TABLE `service_providers` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `primary_category_id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `image_url` varchar(255) default NULL,
  `headline` varchar(255) default NULL,
  `credential_title` varchar(150) default NULL,
  `certificate_url` varchar(255) default NULL,
  `description` text,
  `experience_years` int(11) default '1',
  `hourly_rate_min` decimal(12,2) default '0.00',
  `hourly_rate_max` decimal(12,2) default '0.00',
  `id_card_number` varchar(50) default NULL,
  `id_card_image` varchar(255) default NULL,
  `is_verified` tinyint(1) default '0',
  `rating_avg` decimal(3,2) default '5.00',
  `reviews_count` int(11) default '0',
  `completed_jobs` int(11) default '0',
  `wallet_balance` decimal(12,2) default '0.00',
  `address` text,
  `district_id` int(11) default NULL,
  `village_id` int(11) default NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_providers_category` (`primary_category_id`),
  KEY `idx_providers_district` (`district_id`),
  KEY `idx_providers_verified` (`is_verified`),
  KEY `fk_providers_village` (`village_id`),
  CONSTRAINT `fk_providers_category` FOREIGN KEY (`primary_category_id`) REFERENCES `service_categories` (`id`),
  CONSTRAINT `fk_providers_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_providers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_providers_village` FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;

INSERT INTO `service_providers` (`id`, `user_id`, `primary_category_id`, `business_name`, `image_url`, `headline`, `credential_title`, `certificate_url`, `description`, `experience_years`, `hourly_rate_min`, `hourly_rate_max`, `id_card_number`, `id_card_image`, `is_verified`, `rating_avg`, `reviews_count`, `completed_jobs`, `wallet_balance`, `address`, `district_id`, `village_id`, `created_at`, `updated_at`) VALUES
('1', '2', '1', 'Bengkel Berkah Motor Rengat', 'uploads/providers/bengkel_motor_rengat.jpg', 'Spesialis Servis Injeksi & Panggilan Motor Mogok 24 Jam di Rengat', NULL, NULL, 'Melayani servis motor Honda, Yamaha, Suzuki, ganti oli original, reset scanner injeksi, tambal ban darurat, dan jemput motor mogok di jalan se-Kecamatan Rengat.', '8', '35000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '1', NULL, '2026-10-09 01:27:56', NULL),
('2', '3', '3', 'Sejuk Jaya Tehnik Belilas', 'uploads/providers/sejuk_jaya_ac.jpg', 'Cuci AC Bersih, Bongkar Pasang & Tambah Freon Bergaransi di Belilas', NULL, NULL, 'Teknisi AC bersertifikat di Belilas (Seberida). Kami melayani cuci AC split rumah tangga, ruko, perkantoran, isi freon R32/R410A, dan atasi AC bocor air.', '8', '75000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '4', NULL, '2026-10-09 01:27:56', NULL),
('3', '4', '8', 'Bengkel Las Karya Mandiri Air Molek', 'uploads/providers/las_karya_mandiri.jpg', 'Pembuatan Kanopi Baja Ringan, Pagar Besi Minimalis & Teralis Pintu', NULL, NULL, 'Melayani pembuatan pagar dorong, teralis jendela pengaman, kanopi spandek/alderon, tangga putar, dan servis las panggilan di Air Molek & Pasir Penyu.', '8', '150000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '3', NULL, '2026-10-09 01:27:56', NULL),
('4', '5', '6', 'Jasa Sumur Bor & Pompa Air Tirta Pematang Reba', 'uploads/providers/sumur_bor_tirta.jpg', 'Pengeboran Sumur Air Bersih Bebas Karat & Servis Pompa Jetpump', NULL, NULL, 'Pengeboran sumur rumah dan kebun hingga kedalaman 40-80 meter untuk dapat air jernih tidak bau besi di area Rengat Barat (Pematang Reba) dan sekitarnya.', '8', '250000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '2', NULL, '2026-10-09 01:27:56', NULL),
('5', '6', '10', 'Angkutan Sawit & Sewa Pickup Seberida', 'uploads/providers/pickup_lintas_sawit.jpg', 'Sewa Pickup Harian/Borongan, Angkut Buah Sawit & Pindahan Rumah', NULL, NULL, 'Armada L300 dan Gran Max siap jalan untuk angsir buah kelapa sawit ke RAM/PKS, antar material toko bangunan, dan pindahan rumah se-Inhu.', '8', '120000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '4', NULL, '2026-10-09 01:27:56', NULL),
('6', '7', '6', 'Tukang Bangunan Pak Kumis Rengat', 'uploads/providers/renovasi_pak_kumis.jpg', 'Ahli Pasang Keramik, Plamir, Cat Rumah, Atap Bocor & Renovasi Total', NULL, NULL, 'Berpengalaman lebih dari 15 tahun di bidang konstruksi rumah di Rengat. Melayani pasang keramik lantai/dinding, plamir halus, pengecatan interior/eksterior, dan atap bocor.', '8', '125000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '1', NULL, '2026-10-09 01:34:42', NULL),
('7', '8', '5', 'Rian Elektrik Siaga Listrik 24 Jam', 'uploads/providers/rian_elektrik.jpg', 'Instalasi Listrik Rumah, Perbaikan Konsleting & MCB Anjlok 24 Jam', NULL, NULL, 'Layanan perbaikan listrik darurat di Rengat Barat & sekitarnya. Atasi mati lampu mendadak, MCB sering jepret, penambahan titik lampu/stop kontak, dan instalasi grounding aman.', '8', '50000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '2', NULL, '2026-10-09 01:34:42', NULL),
('8', '9', '9', 'Kelompok Rawat & Panen Sawit Barokah', 'uploads/providers/sawit_barokah.jpg', 'Tenaga Babat Rumput Kebun, Semprot Gulma, Dodos Panen & Pruning', NULL, NULL, 'Regu pekerja kebun sawit terpercaya di Batang Gansal & Seberida. Siap borongan babat piringan/pasar pikul, semprot herbisida gulma, dodos buah pasir/matang, dan pupuk.', '8', '100000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '6', NULL, '2026-10-09 01:34:42', NULL),
('9', '10', '13', 'Lensa Melayu Fotografi & Wedding Cinema', 'uploads/providers/lensa_melayu.jpg', 'Dokumentasi Pesta Pernikahan, Foto Adat Melayu, Wisuda & Video Drone', NULL, NULL, 'Fotografer & videografer profesional se-Kabupaten Indragiri Hulu. Melayani foto akad nikah, resepsi adat Melayu Inhu, video cinematic teaser 4K, foto wisuda, dan pre-wedding.', '8', '500000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '2', NULL, '2026-10-09 01:34:42', NULL),
('10', '11', '12', 'Griya Cantik MUA & Rias Pengantin Kak Nia', 'uploads/providers/griya_cantik_mua.jpg', 'Make Up Artist Pengantin Melayu, Wisuda, Lamaran & Sewa Baju Adat', NULL, NULL, 'Rias wajah flawless tahan lama tidak mudah luntur di Pasir Penyu & sekitarnya. Melayani rias pengantin, sunatan, kebaya wisuda, dan sewa gaun pengantin modern.', '8', '150000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '3', NULL, '2026-10-09 01:34:42', NULL),
('11', '12', '18', 'Homecare Medis & Fisioterapi Bunda Sehat', 'uploads/providers/homecare_bunda_sehat.jpg', 'Rawat Luka Diabetes, Pasang Selang NGT/Kateter & Perawatan Lansia', NULL, NULL, 'Tenaga medis perawat profesional dengan Surat Tanda Registrasi (STR) aktif di Rengat. Melayani perawatan luka modern pasca operasi, cek gula darah/tensi berkala, dan dampingan lansia ke rumah.', '8', '80000.00', '0.00', NULL, NULL, '1', '5.00', '0', '0', '50000.00', NULL, '1', NULL, '2026-10-09 01:34:42', NULL);

DROP TABLE IF EXISTS `service_request_responses`;
CREATE TABLE `service_request_responses` (
  `id` int(11) NOT NULL auto_increment,
  `request_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `offer_price` decimal(12,2) NOT NULL,
  `estimated_duration` varchar(100) default NULL,
  `message` text NOT NULL,
  `status` varchar(30) default 'pending',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `idx_responses_request` (`request_id`),
  KEY `idx_responses_provider` (`provider_id`),
  KEY `idx_responses_status` (`status`),
  CONSTRAINT `fk_responses_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_responses_request` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `service_request_timeline`;
CREATE TABLE `service_request_timeline` (
  `id` int(11) NOT NULL auto_increment,
  `request_id` int(11) NOT NULL,
  `status_key` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `note` text,
  `actor_role` varchar(50) default 'system',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_timeline_request` (`request_id`),
  KEY `idx_timeline_created` (`created_at`),
  CONSTRAINT `fk_timeline_request` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `service_requests`;
CREATE TABLE `service_requests` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `provider_id` int(11) default NULL,
  `district_id` int(11) NOT NULL,
  `village_id` int(11) default NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `budget` decimal(12,2) default NULL,
  `final_price` decimal(12,2) default NULL,
  `cost_breakdown` text,
  `address_detail` varchar(255) default NULL,
  `urgency` varchar(30) default 'normal',
  `status` varchar(30) default 'open',
  `progress_step` varchar(30) default 'created',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `idx_requests_user` (`user_id`),
  KEY `idx_requests_category` (`category_id`),
  KEY `idx_requests_district` (`district_id`),
  KEY `idx_requests_status` (`status`),
  KEY `fk_requests_village` (`village_id`),
  KEY `fk_requests_provider` (`provider_id`),
  CONSTRAINT `fk_requests_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`),
  CONSTRAINT `fk_requests_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`),
  CONSTRAINT `fk_requests_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_village` FOREIGN KEY (`village_id`) REFERENCES `villages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `user_verifications`;
CREATE TABLE `user_verifications` (
  `id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `channel` enum('whatsapp','email') NOT NULL default 'whatsapp',
  `target` varchar(150) NOT NULL,
  `code` varchar(10) NOT NULL,
  `token` varchar(64) default NULL,
  `expires_at` datetime NOT NULL,
  `is_verified` tinyint(1) default '0',
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_code` (`code`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8;

INSERT INTO `user_verifications` (`id`, `user_id`, `channel`, `target`, `code`, `token`, `expires_at`, `is_verified`, `created_at`) VALUES
('1', '234', 'whatsapp', '08127771791452033', '529518', '39e5de46d6740576c5efce91ad25257a', '2026-10-08 16:48:53', '0', '2026-10-08 16:33:53'),
('2', '1', 'whatsapp', '085378230761', '445397', '81c27ae67cde55029a5851b5f965d550', '2026-10-08 16:50:35', '0', '2026-10-08 16:35:35'),
('7', '241', 'whatsapp', '082283632352', '739776', 'ea2c86ccb129691528783b07d42f8bb4', '2026-10-08 17:16:38', '0', '2026-10-08 17:01:38'),
('8', '241', 'whatsapp', '082283632352', '928604', '51fe596a06fde1557c6db47643473700', '2026-10-08 17:20:41', '0', '2026-10-08 17:05:41'),
('11', '248', 'email', 'warga1791454836@test.id', '743538', '4b30fd88bce0be7c405392187656a688', '2026-10-08 17:35:36', '0', '2026-10-08 17:20:36'),
('12', '249', 'email', 'montir1791454836@test.id', '788362', '4fc6dff40285a0efa4ed58c8c66fbf23', '2026-10-08 17:35:36', '0', '2026-10-08 17:20:36'),
('16', '251', 'email', 'warga1791455181@test.id', '936924', '3d0b192a0d299a21480d5c23154504bd', '2026-10-08 17:41:21', '0', '2026-10-08 17:26:21'),
('17', '252', 'email', 'montir1791455181@test.id', '928290', '75a4a7a437b642ed2af4b93f3ef2fbcf', '2026-10-08 17:41:21', '0', '2026-10-08 17:26:21'),
('21', '254', 'email', 'warga1791455222@test.id', '652844', 'ebf935cf267d189321aafb7c618e2904', '2026-10-08 17:42:02', '0', '2026-10-08 17:27:02'),
('22', '255', 'email', 'montir1791455222@test.id', '645008', '765eb98342203cdaaf32e418d8979848', '2026-10-08 17:42:03', '0', '2026-10-08 17:27:03'),
('26', '257', 'email', 'mufidasari16@gmail.com', '450162', 'e65b63ffe00b25a44ba5b6b93e0e9057', '2026-10-08 17:45:06', '0', '2026-10-08 17:30:06'),
('27', '257', 'email', 'mufidasari16@gmail.com', '781690', '1d21b0abd3b618746949c9a8e64a4367', '2026-10-08 17:54:31', '0', '2026-10-08 17:39:31'),
('28', '257', 'email', 'mufidasari16@gmail.com', '411132', 'ccfe4e0f9a400bff9aff6c5bf1898b3e', '2026-10-08 17:55:44', '0', '2026-10-08 17:40:44'),
('29', '258', 'email', 'warga1791457243@test.id', '485122', 'cd1f90edb3dea5b9759c7afa3b341be0', '2026-10-08 18:15:43', '0', '2026-10-08 18:00:43'),
('30', '259', 'email', 'montir1791457243@test.id', '781461', 'd273d52fc56ca4f5d69fe9441b3bec07', '2026-10-08 18:15:46', '0', '2026-10-08 18:00:46'),
('31', '260', 'email', 'admin@jasainhu.id', '645084', '95feec8095b1758e0020408361988542', '2026-10-08 18:15:49', '0', '2026-10-08 18:00:49'),
('35', '262', 'email', 'mufidasari16@gmail.com', '637776', '9b892039aa03697999a76a9726bf0d21', '2026-10-08 18:45:06', '1', '2026-10-08 18:30:06'),
('36', '263', 'email', 'mufidasari16@gmail.com', '237288', 'b24ba7b1e5a5f5aaaec56a2671a259b9', '2026-10-08 18:47:32', '1', '2026-10-08 18:32:32'),
('37', '264', 'email', 'warga1791484033@test.id', '897853', '00eeaa6c99d32bc37895b0e4fcca3e40', '2026-10-09 01:42:13', '0', '2026-10-09 01:27:13'),
('38', '265', 'email', 'montir1791484033@test.id', '513787', '5e81d1ceffdbcebda37bfd6089c88d87', '2026-10-09 01:42:15', '0', '2026-10-09 01:27:15'),
('39', '266', 'email', 'admin@jasainhu.id', '469868', 'e3e8b729f5077c3bbb12c0336284097e', '2026-10-09 01:42:17', '0', '2026-10-09 01:27:17'),
('40', '278', 'email', 'warga1791484522@test.id', '773122', '64e8c1361abcbfd38c2fd945f2eec7a6', '2026-10-09 01:50:22', '0', '2026-10-09 01:35:22'),
('41', '279', 'email', 'montir1791484522@test.id', '541988', '3c36a03febe974220f9e1b06a05755c1', '2026-10-09 01:50:25', '0', '2026-10-09 01:35:25'),
('42', '280', 'email', 'warga1791484793@test.id', '422886', '6e6cf7636ac6806bc927f9988b676bd1', '2026-10-09 01:54:53', '0', '2026-10-09 01:39:53'),
('43', '281', 'email', 'montir1791484793@test.id', '199943', '8e16df69f5891555015e76ceeed34151', '2026-10-09 01:54:56', '0', '2026-10-09 01:39:56');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL auto_increment,
  `role_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) default '1',
  `email_verified_at` datetime default NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_phone` (`phone`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;

INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `phone`, `password_hash`, `is_active`, `email_verified_at`, `created_at`, `updated_at`) VALUES
('1', '1', 'Angga Fahrandi', 'jasainhu@gmail.com', '085126241679', '$2y$10$F7cYjAk79sGoTvW6fWYjZuxQbNIoV4g.iPivVS7hp4nLKOioP/0Ue', '1', '2026-10-03 11:21:59', '2026-10-03 11:21:59', '2026-10-08 22:01:37'),
('2', '3', 'Mas Doni (Berkah Motor)', 'berkah.motor@jasainhu.id', '081275990101', '$2y$10$JzPyEJjj0NPFu7mMVgTjSehTexINcfJ7WmfAG6nzd8xSzv.wj2uyO', '1', NULL, '2026-10-09 01:27:56', '2026-10-09 01:27:56'),
('3', '3', 'Pak Hendra (Sejuk Jaya)', 'sejuk.jaya@jasainhu.id', '081275990202', '$2y$10$JzPyEJjj0NPFu7mMVgTjSehTexINcfJ7WmfAG6nzd8xSzv.wj2uyO', '1', NULL, '2026-10-09 01:27:56', '2026-10-09 01:27:56'),
('4', '3', 'Cak Kuswanto (Las Mandiri)', 'karya.mandiri@jasainhu.id', '081275990303', '$2y$10$JzPyEJjj0NPFu7mMVgTjSehTexINcfJ7WmfAG6nzd8xSzv.wj2uyO', '1', NULL, '2026-10-09 01:27:56', '2026-10-09 01:27:56'),
('5', '3', 'Pak Joko (Tirta Bor)', 'tirta.sumur@jasainhu.id', '081275990404', '$2y$10$JzPyEJjj0NPFu7mMVgTjSehTexINcfJ7WmfAG6nzd8xSzv.wj2uyO', '1', NULL, '2026-10-09 01:27:56', '2026-10-09 01:27:56'),
('6', '3', 'Bang Ucok (Lintas Pickup)', 'lintas.sawit@jasainhu.id', '081275990505', '$2y$10$JzPyEJjj0NPFu7mMVgTjSehTexINcfJ7WmfAG6nzd8xSzv.wj2uyO', '1', NULL, '2026-10-09 01:27:56', '2026-10-09 01:27:56'),
('7', '3', 'Pak Kumis (Sutrisno)', 'pak.kumis@jasainhu.id', '081275990606', '$2y$10$67/TVAYQZY5VyZF9blqqC.iuy24S.qT5QdeyMg0oS5113trNLtdg2', '1', NULL, '2026-10-09 01:34:42', '2026-10-09 01:34:42'),
('8', '3', 'Mas Rian (Teknisi Listrik)', 'rian.elektrik@jasainhu.id', '081275990707', '$2y$10$67/TVAYQZY5VyZF9blqqC.iuy24S.qT5QdeyMg0oS5113trNLtdg2', '1', NULL, '2026-10-09 01:34:42', '2026-10-09 01:34:42'),
('9', '3', 'Bang Herman (Mandor Sawit)', 'sawit.barokah@jasainhu.id', '081275990808', '$2y$10$67/TVAYQZY5VyZF9blqqC.iuy24S.qT5QdeyMg0oS5113trNLtdg2', '1', NULL, '2026-10-09 01:34:42', '2026-10-09 01:34:42'),
('10', '3', 'Fajar Ramadhan (Lensa Melayu)', 'lensa.melayu@jasainhu.id', '081275990909', '$2y$10$67/TVAYQZY5VyZF9blqqC.iuy24S.qT5QdeyMg0oS5113trNLtdg2', '1', NULL, '2026-10-09 01:34:42', '2026-10-09 01:34:42'),
('11', '3', 'Nia Rahmawati (Kak Nia MUA)', 'griya.cantik@jasainhu.id', '081275991010', '$2y$10$67/TVAYQZY5VyZF9blqqC.iuy24S.qT5QdeyMg0oS5113trNLtdg2', '1', NULL, '2026-10-09 01:34:42', '2026-10-09 01:34:42'),
('12', '3', 'Ners Anisa S.Kep (STR Aktif)', 'bunda.sehat@jasainhu.id', '081275991111', '$2y$10$67/TVAYQZY5VyZF9blqqC.iuy24S.qT5QdeyMg0oS5113trNLtdg2', '1', NULL, '2026-10-09 01:34:42', '2026-10-09 01:34:42');

DROP TABLE IF EXISTS `villages`;
CREATE TABLE `villages` (
  `id` int(11) NOT NULL auto_increment,
  `district_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `postal_code` varchar(10) default NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_villages_district` (`district_id`),
  CONSTRAINT `fk_villages_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=207 DEFAULT CHARSET=utf8;

INSERT INTO `villages` (`id`, `district_id`, `name`, `postal_code`, `created_at`) VALUES
('2', '1', 'Sekip Hilir', '29312', '2026-10-03 11:21:59'),
('3', '1', 'Sekip Hulu', '29313', '2026-10-03 11:21:59'),
('4', '1', 'Kampung Dagang', '29314', '2026-10-03 11:21:59'),
('5', '1', 'Kampung Besar Kota', '29315', '2026-10-03 11:21:59'),
('6', '1', 'Kampung Pulau', '29316', '2026-10-03 11:21:59'),
('7', '2', 'Pematang Reba', '29351', '2026-10-03 11:21:59'),
('8', '2', 'Talang Jerinjing', '29351', '2026-10-03 11:21:59'),
('9', '2', 'Tanah Datar', '29351', '2026-10-03 11:21:59'),
('10', '2', 'Danau Baru', '29351', '2026-10-03 11:21:59'),
('11', '2', 'Redang', '29351', '2026-10-03 11:21:59'),
('12', '3', 'Air Molek I', '29352', '2026-10-03 11:21:59'),
('13', '3', 'Air Molek II', '29352', '2026-10-03 11:21:59'),
('15', '3', 'Candirejo', '29352', '2026-10-03 11:21:59'),
('16', '3', 'Tanah Merah', '29352', '2026-10-03 11:21:59'),
('17', '4', 'Pangkalan Kasai', '29371', '2026-10-03 11:21:59'),
('20', '4', 'Buluh Rampai', '29371', '2026-10-03 11:21:59'),
('21', '4', 'Titian Resak', '29371', '2026-10-03 11:21:59'),
('23', '13', 'Sukajadi', '29353', '2026-10-03 11:21:59'),
('24', '13', 'Banjar Balam', '29353', '2026-10-03 11:21:59'),
('27', '9', 'Baturijal Barat', '29354', '2026-10-03 11:21:59'),
('28', '9', 'Baturijal Hulu', '29354', '2026-10-03 11:21:59'),
('30', '5', 'Aur Cina', '29372', '2026-10-03 11:21:59'),
('31', '5', 'Batu Papan', '29372', '2026-10-03 11:21:59'),
('32', '5', 'Bukit Lipai', '29372', '2026-10-03 11:21:59'),
('34', '6', 'Rantau Langsat', '29373', '2026-10-03 11:21:59'),
('35', '7', 'Simpang Kelayang', '29355', '2026-10-03 11:21:59'),
('36', '7', 'Bongkal Malang', '29355', '2026-10-03 11:21:59'),
('39', '10', 'Selunak', '29357', '2026-10-03 11:21:59'),
('40', '10', 'Pematang Benteng', '29357', '2026-10-03 11:21:59'),
('41', '11', 'Kuala Cenaku', '29381', '2026-10-03 11:21:59'),
('42', '11', 'Teluk Sungkai', '29381', '2026-10-03 11:21:59'),
('43', '12', 'Kelawat', '29361', '2026-10-03 11:21:59'),
('44', '12', 'Sungai Lala', '29361', '2026-10-03 11:21:59'),
('45', '14', 'Lubuk Batu Tinggal', '29362', '2026-10-03 11:21:59'),
('47', '1', 'Kampung Besar Seberang', '29312', '2026-10-08 20:49:25'),
('48', '1', 'Pasar Kota', '29314', '2026-10-08 20:49:25'),
('49', '1', 'Kuantan Baru', '29319', '2026-10-08 20:49:25'),
('50', '1', 'Pasir Kemilu', '29319', '2026-10-08 20:49:25'),
('51', '1', 'Pulau Gajah', '29319', '2026-10-08 20:49:25'),
('52', '1', 'Rantau Mapesai', '29319', '2026-10-08 20:49:25'),
('53', '1', 'Rawa Bangun', '29319', '2026-10-08 20:49:25'),
('54', '1', 'Sungai Beringin', '29319', '2026-10-08 20:49:25'),
('55', '1', 'Sungai Guntung Hilir', '29319', '2026-10-08 20:49:25'),
('56', '1', 'Sungai Guntung Tengah', '29319', '2026-10-08 20:49:25'),
('57', '1', 'Sungai Raya', '29319', '2026-10-08 20:49:25'),
('58', '2', 'Air Jernih', '29351', '2026-10-08 20:49:25'),
('59', '2', 'Alang Kepayang', '29351', '2026-10-08 20:49:25'),
('60', '2', 'Barangan', '29351', '2026-10-08 20:49:25'),
('61', '2', 'Bukit Petaling', '29351', '2026-10-08 20:49:25'),
('62', '2', 'Danau Tiga', '29351', '2026-10-08 20:49:25'),
('63', '2', 'Kota Lama', '29351', '2026-10-08 20:49:25'),
('64', '2', 'Pekan Heran', '29351', '2026-10-08 20:49:25'),
('65', '2', 'Pematang Jaya', '29351', '2026-10-08 20:49:25'),
('66', '2', 'Rantau Bakung', '29351', '2026-10-08 20:49:25'),
('67', '2', 'Sialang Dua Dahan', '29351', '2026-10-08 20:49:25'),
('68', '2', 'Sungai Baung', '29351', '2026-10-08 20:49:25'),
('69', '2', 'Sungai Dawu', '29351', '2026-10-08 20:49:25'),
('70', '2', 'Tanah Makmur', '29351', '2026-10-08 20:49:25'),
('71', '3', 'Kembang Harum', '29352', '2026-10-08 20:49:25'),
('72', '3', 'Sekar Mawar', '29352', '2026-10-08 20:49:25'),
('73', '3', 'Tanjung Gading', '29352', '2026-10-08 20:49:25'),
('74', '3', 'Batu Gajah', '29352', '2026-10-08 20:49:25'),
('75', '3', 'Jatirejo', '29352', '2026-10-08 20:49:25'),
('76', '3', 'Lembah Dusun Gading', '29352', '2026-10-08 20:49:25'),
('77', '3', 'Pasir Keranji', '29352', '2026-10-08 20:49:25'),
('78', '3', 'Petalongan', '29352', '2026-10-08 20:49:25'),
('79', '3', 'Serumpun Jaya', '29352', '2026-10-08 20:49:25'),
('80', '4', 'Bandar Padang', '29371', '2026-10-08 20:49:25'),
('81', '4', 'Beligan', '29371', '2026-10-08 20:49:25'),
('82', '4', 'Bukit Meranti', '29371', '2026-10-08 20:49:25'),
('83', '4', 'Kelesa', '29371', '2026-10-08 20:49:25'),
('84', '4', 'Payarumbai', '29371', '2026-10-08 20:49:25'),
('85', '4', 'Petala Bumi', '29371', '2026-10-08 20:49:25'),
('86', '4', 'Serasam', '29371', '2026-10-08 20:49:25'),
('87', '4', 'Sibabat', '29371', '2026-10-08 20:49:25'),
('88', '5', 'Alim', '29372', '2026-10-08 20:49:25'),
('89', '5', 'Anak Talang', '29372', '2026-10-08 20:49:25'),
('90', '5', 'Bukit Lingkar', '29372', '2026-10-08 20:49:25'),
('91', '5', 'Cenaku Kecil', '29372', '2026-10-08 20:49:25'),
('92', '5', 'Kepayang Sari', '29372', '2026-10-08 20:49:25'),
('93', '5', 'Kerubung Jaya', '29372', '2026-10-08 20:49:25'),
('94', '5', 'Kuala Gading', '29372', '2026-10-08 20:49:25'),
('95', '5', 'Kuala Kilan', '29372', '2026-10-08 20:49:25'),
('96', '5', 'Lahai Kemuning', '29372', '2026-10-08 20:49:25'),
('97', '5', 'Pataling Jaya', '29372', '2026-10-08 20:49:25'),
('98', '5', 'Pejangki', '29372', '2026-10-08 20:49:25'),
('99', '5', 'Pematang Manggis', '29372', '2026-10-08 20:49:25'),
('100', '5', 'Punti Anai', '29372', '2026-10-08 20:49:25'),
('101', '5', 'Sanglap', '29372', '2026-10-08 20:49:25'),
('102', '5', 'Sipang', '29372', '2026-10-08 20:49:25'),
('103', '5', 'Talang Bersemi', '29372', '2026-10-08 20:49:25'),
('104', '5', 'Talang Mulya', '29372', '2026-10-08 20:49:25'),
('105', '6', 'Belimbing', '29373', '2026-10-08 20:49:25'),
('106', '6', 'Ringin', '29373', '2026-10-08 20:49:25'),
('107', '6', 'Seberida', '29373', '2026-10-08 20:49:25'),
('108', '6', 'Siambul', '29373', '2026-10-08 20:49:25'),
('109', '6', 'Sungai Akar', '29373', '2026-10-08 20:49:25'),
('110', '6', 'Talang Lakat', '29373', '2026-10-08 20:49:25'),
('111', '6', 'Usul', '29373', '2026-10-08 20:49:25'),
('112', '6', 'Penyaguan', '29373', '2026-10-08 20:49:25'),
('113', '6', 'Danau Rambai', '29373', '2026-10-08 20:49:25'),
('114', '7', 'Bukit Selanjut', '29355', '2026-10-08 20:49:25'),
('115', '7', 'Dusun Tua', '29355', '2026-10-08 20:49:25'),
('116', '7', 'Dusun Tua Pelang', '29355', '2026-10-08 20:49:25'),
('117', '7', 'Kota Medan', '29355', '2026-10-08 20:49:25'),
('118', '7', 'Pasir Beringin', '29355', '2026-10-08 20:49:25'),
('119', '7', 'Pelangko', '29355', '2026-10-08 20:49:25'),
('120', '7', 'Polak Pisang', '29355', '2026-10-08 20:49:25'),
('121', '7', 'Pulau Sengkilo', '29355', '2026-10-08 20:49:25'),
('122', '7', 'Simpang Kota Medan', '29355', '2026-10-08 20:49:25'),
('123', '7', 'Sungai Kuning Benio', '29355', '2026-10-08 20:49:25'),
('124', '7', 'Sungai Banyak Ikan', '29355', '2026-10-08 20:49:25'),
('125', '7', 'Sungai Golang', '29355', '2026-10-08 20:49:25'),
('126', '7', 'Sungai Pasir Putih', '29355', '2026-10-08 20:49:25'),
('127', '7', 'Tanjung Beludu', '29355', '2026-10-08 20:49:25'),
('128', '7', 'Teluk Sejuah', '29355', '2026-10-08 20:49:25'),
('129', '8', 'Batu Sawar', '29356', '2026-10-08 20:49:25'),
('130', '8', 'Bukit Indah', '29356', '2026-10-08 20:49:25'),
('131', '8', 'Kampung Bunga', '29356', '2026-10-08 20:49:25'),
('132', '8', 'Kelayang', '29356', '2026-10-08 20:49:25'),
('133', '8', 'Kota Baru', '29356', '2026-10-08 20:49:25'),
('134', '8', 'Kuantan Tenang', '29356', '2026-10-08 20:49:25'),
('135', '8', 'Lubuk Sitarak', '29356', '2026-10-08 20:49:25'),
('136', '8', 'Petonggan', '29356', '2026-10-08 20:49:25'),
('137', '8', 'Rimba Seminai', '29356', '2026-10-08 20:49:25'),
('138', '8', 'Sungai Ekok', '29356', '2026-10-08 20:49:25'),
('139', '8', 'Talang Durian Cacar', '29356', '2026-10-08 20:49:25'),
('140', '8', 'Talang Gedabu', '29356', '2026-10-08 20:49:25'),
('141', '8', 'Talang Parigi', '29356', '2026-10-08 20:49:25'),
('142', '8', 'Talang Pring Jaya', '29356', '2026-10-08 20:49:25'),
('143', '8', 'Talang Selantai', '29356', '2026-10-08 20:49:25'),
('144', '8', 'Talang Suka Maju', '29356', '2026-10-08 20:49:25'),
('145', '8', 'Talang Sungai Limau', '29356', '2026-10-08 20:49:25'),
('146', '8', 'Talang Sungai Parit', '29356', '2026-10-08 20:49:25'),
('147', '8', 'Talang Tujuh Buah Tangga', '29356', '2026-10-08 20:49:25'),
('148', '9', 'Baturijal Hilir', '29354', '2026-10-08 20:49:25'),
('149', '9', 'Peranap', '29354', '2026-10-08 20:49:25'),
('150', '9', 'Gumanti', '29354', '2026-10-08 20:49:25'),
('151', '9', 'Katipo Pura', '29354', '2026-10-08 20:49:25'),
('152', '9', 'Pandan Wangi', '29354', '2026-10-08 20:49:25'),
('153', '9', 'Pauh Ranap', '29354', '2026-10-08 20:49:25'),
('154', '9', 'Semelinang Tebing', '29354', '2026-10-08 20:49:25'),
('155', '9', 'Semelinang Darat', '29354', '2026-10-08 20:49:25'),
('156', '9', 'Serai Wangi', '29354', '2026-10-08 20:49:25'),
('157', '9', 'Setako Raya', '29354', '2026-10-08 20:49:25'),
('158', '10', 'Koto Tuo', '29357', '2026-10-08 20:49:25'),
('159', '10', 'Peladangan', '29357', '2026-10-08 20:49:25'),
('160', '10', 'Pematang', '29357', '2026-10-08 20:49:25'),
('161', '10', 'Pesajian', '29357', '2026-10-08 20:49:25'),
('162', '10', 'Puntikayu', '29357', '2026-10-08 20:49:25'),
('163', '10', 'Sencano Jaya', '29357', '2026-10-08 20:49:25'),
('164', '10', 'Suka Maju', '29357', '2026-10-08 20:49:25'),
('165', '10', 'Sungai Aur', '29357', '2026-10-08 20:49:25'),
('166', '11', 'Kuala Mulia', '29381', '2026-10-08 20:49:25'),
('167', '11', 'Pulau Gelang', '29381', '2026-10-08 20:49:25'),
('168', '11', 'Pulau Jum\'at', '29381', '2026-10-08 20:49:25'),
('169', '11', 'Rawa Asri', '29381', '2026-10-08 20:49:25'),
('170', '11', 'Rawa Sekip', '29381', '2026-10-08 20:49:25'),
('171', '11', 'Suka Jadi', '29381', '2026-10-08 20:49:25'),
('172', '11', 'Tambak', '29381', '2026-10-08 20:49:25'),
('173', '11', 'Tanjung Sari', '29381', '2026-10-08 20:49:25'),
('174', '12', 'Kuala Lala', '29361', '2026-10-08 20:49:25'),
('175', '12', 'Morong', '29361', '2026-10-08 20:49:25'),
('176', '12', 'Pasir Batu Mandi', '29361', '2026-10-08 20:49:25'),
('177', '12', 'Pasir Bongkal', '29361', '2026-10-08 20:49:25'),
('178', '12', 'Pasir Kelampaian', '29361', '2026-10-08 20:49:25'),
('179', '12', 'Pasir Selabau', '29361', '2026-10-08 20:49:25'),
('180', '12', 'Perkebunan Sungai Parit', '29361', '2026-10-08 20:49:25'),
('181', '12', 'Perkebunan Sungai Lala', '29361', '2026-10-08 20:49:25'),
('182', '12', 'Sungai Air Putih', '29361', '2026-10-08 20:49:25'),
('183', '12', 'Tanjung Danau', '29361', '2026-10-08 20:49:25'),
('184', '13', 'Gudang Batu', '29353', '2026-10-08 20:49:25'),
('185', '13', 'Japura', '29353', '2026-10-08 20:49:25'),
('186', '13', 'Lambang Sari I, II, III', '29353', '2026-10-08 20:49:25'),
('187', '13', 'Lambang Sari IV', '29353', '2026-10-08 20:49:25'),
('188', '13', 'Lambang Sari V', '29353', '2026-10-08 20:49:25'),
('189', '13', 'Mekarsari', '29353', '2026-10-08 20:49:25'),
('190', '13', 'Pasir Ringgit', '29353', '2026-10-08 20:49:25'),
('191', '13', 'Pasir Sialang Jaya', '29353', '2026-10-08 20:49:25'),
('192', '13', 'Redang Seko', '29353', '2026-10-08 20:49:25'),
('193', '13', 'Rejosari', '29353', '2026-10-08 20:49:25'),
('194', '13', 'Seko Lubuk Tigo', '29353', '2026-10-08 20:49:25'),
('195', '13', 'Sidomulyo', '29353', '2026-10-08 20:49:25'),
('196', '13', 'Sungai Sagu', '29353', '2026-10-08 20:49:25'),
('197', '13', 'Wonosari', '29353', '2026-10-08 20:49:25'),
('198', '14', 'Air Putih', '29362', '2026-10-08 20:49:25'),
('199', '14', 'Kulim Jaya', '29362', '2026-10-08 20:49:25'),
('200', '14', 'Pondok Gelugur', '29362', '2026-10-08 20:49:25'),
('201', '14', 'Pontian Mekar', '29362', '2026-10-08 20:49:25'),
('202', '14', 'Rimpian', '29362', '2026-10-08 20:49:25'),
('203', '14', 'Sei Beras-beras', '29362', '2026-10-08 20:49:25'),
('204', '14', 'Sei Seberas Hilir', '29362', '2026-10-08 20:49:25'),
('205', '14', 'Tasik Juang', '29362', '2026-10-08 20:49:25'),
('206', '13', 'Lirik Area', '29353', '2026-10-08 20:54:13');

DROP TABLE IF EXISTS `wallet_topup_requests`;
CREATE TABLE `wallet_topup_requests` (
  `id` int(11) NOT NULL auto_increment,
  `provider_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(100) default 'Transfer Bank / QRIS',
  `proof_image` varchar(255) default NULL,
  `status` enum('pending','approved','rejected') default 'pending',
  `admin_notes` text,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `approved_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `idx_wtr_provider` (`provider_id`),
  KEY `idx_wtr_status` (`status`),
  CONSTRAINT `fk_wtr_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `whatsapp_logs`;
CREATE TABLE `whatsapp_logs` (
  `id` int(11) NOT NULL auto_increment,
  `request_id` int(11) default NULL,
  `recipient_phone` varchar(30) NOT NULL,
  `recipient_name` varchar(100) default NULL,
  `event_type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `status` enum('queued','sent','simulated','failed') default 'simulated',
  `direct_url` text,
  `response_payload` text,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `idx_wa_request` (`request_id`),
  KEY `idx_wa_phone` (`recipient_phone`),
  KEY `idx_wa_event` (`event_type`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8;

INSERT INTO `whatsapp_logs` (`id`, `request_id`, `recipient_phone`, `recipient_name`, `event_type`, `message`, `status`, `direct_url`, `response_payload`, `created_at`) VALUES
('21', NULL, '08127771791452033', 'Test Verifikasi Inhu 1791452033', 'otp_verification', 'Halo Test Verifikasi Inhu 1791452033,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n', 'simulated', 'https://wa.me/628127771791452033?text=Halo+Test+Verifikasi+Inhu+1791452033%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%F0%9F%91%89+%2A529518%2A%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 16:33:53'),
('22', NULL, '085378230761', 'Administrator Jasa Inhu', 'otp_verification', 'Halo Administrator Jasa Inhu,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n', 'simulated', 'https://wa.me/6285378230761?text=Halo+Administrator+Jasa+Inhu%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%F0%9F%91%89+%2A445397%2A%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 16:35:35'),
('23', NULL, '08127771791452212', 'Test Verifikasi Inhu 1791452212', 'otp_verification', 'Halo Test Verifikasi Inhu 1791452212,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *484989* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/628127771791452212?text=Halo+Test+Verifikasi+Inhu+1791452212%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A484989%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 16:36:52'),
('26', NULL, '08127771791452279', 'Test Verifikasi Inhu 1791452279', 'otp_verification', 'Halo Test Verifikasi Inhu 1791452279,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *173087* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/628127771791452279?text=Halo+Test+Verifikasi+Inhu+1791452279%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A173087%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 16:37:59'),
('27', NULL, '082283632352', 'wirsas', 'otp_verification', 'Halo wirsas,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *739776* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/6282283632352?text=Halo+wirsas%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A739776%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 17:01:38'),
('28', NULL, '082283632352', 'wirsas', 'otp_verification', 'Halo wirsas,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *928604* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/6282283632352?text=Halo+wirsas%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A928604%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 17:05:41'),
('30', NULL, '08127771791454483', 'Test Verifikasi Inhu 1791454483', 'otp_verification', 'Halo Test Verifikasi Inhu 1791454483,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *396468* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/628127771791454483?text=Halo+Test+Verifikasi+Inhu+1791454483%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A396468%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 17:14:43'),
('33', NULL, '08127771791454849', 'Test Verifikasi Inhu 1791454849', 'otp_verification', 'Halo Test Verifikasi Inhu 1791454849,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *382984* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/628127771791454849?text=Halo+Test+Verifikasi+Inhu+1791454849%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A382984%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 17:20:49'),
('35', NULL, '08127771791455182', 'Test Verifikasi Inhu 1791455182', 'otp_verification', 'Halo Test Verifikasi Inhu 1791455182,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *506154* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/628127771791455182?text=Halo+Test+Verifikasi+Inhu+1791455182%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A506154%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 17:26:22'),
('37', NULL, '08127771791455224', 'Test Verifikasi Inhu 1791455224', 'otp_verification', 'Halo Test Verifikasi Inhu 1791455224,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *875309* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/628127771791455224?text=Halo+Test+Verifikasi+Inhu+1791455224%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A875309%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 17:27:04'),
('39', NULL, '08127771791457252', 'Test Verifikasi Inhu 1791457252', 'otp_verification', 'Halo Test Verifikasi Inhu 1791457252,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *582207* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/628127771791457252?text=Halo+Test+Verifikasi+Inhu+1791457252%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A582207%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 18:00:55'),
('40', NULL, '08127771791459596', 'Test Verifikasi Inhu 1791459596', 'otp_verification', 'Halo Test Verifikasi Inhu 1791459596,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *383570* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/628127771791459596?text=Halo+Test+Verifikasi+Inhu+1791459596%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A383570%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 18:39:58'),
('41', NULL, '08127771791460508', 'Test Verifikasi Inhu 1791460508', 'otp_verification', 'Halo Test Verifikasi Inhu 1791460508,\n\nKode verifikasi pendaftaran akun JASA INHU Anda adalah:\n>> *199869* <<\n\nMasukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\nAbaikan jika Anda tidak mendaftar di JASA INHU.', 'simulated', 'https://wa.me/628127771791460508?text=Halo+Test+Verifikasi+Inhu+1791460508%2C%0A%0AKode+verifikasi+pendaftaran+akun+JASA+INHU+Anda+adalah%3A%0A%3E%3E+%2A199869%2A+%3C%3C%0A%0AMasukkan+kode+ini+di+aplikasi+untuk+mengaktifkan+akun+Anda.+Kode+berlaku+15+menit.%0A%0AAbaikan+jika+Anda+tidak+mendaftar+di+JASA+INHU.', NULL, '2026-10-08 18:55:12');

SET FOREIGN_KEY_CHECKS = 1;

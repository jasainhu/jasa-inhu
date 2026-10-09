<?php
/**
 * Seeder Tampilan Visual Marketplace JASA INHU (Pilot Showcase)
 * Mengisi data etalase visual lengkap: 11 Mitra Unggulan Riil, Portofolio Before-After, Ulasan Lokal, dan Live Job Board
 */

require_once __DIR__ . '/../config/database.php';

$db = get_db();

echo "=== MEMULAI SEED DATA MARKETPLACE VISUAL & MITRA PERCONTOHAN JASA INHU ===\n";

// 1. Siapkan direktori upload jika belum ada
$portDir = __DIR__ . '/../uploads/portfolios';
if (!is_dir($portDir)) mkdir($portDir, 0777, true);

$provDir = __DIR__ . '/../uploads/providers';
if (!is_dir($provDir)) mkdir($provDir, 0777, true);

// Fungsi pembantu generate kartu visual yang elegan dan bersih
function generate_card_image($filename, $title, $tag, $location, $bgHex, $accentHex) {
    $w = 640;
    $h = 400;
    $im = imagecreatetruecolor($w, $h);

    list($r, $g, $b) = sscanf($bgHex, "#%02x%02x%02x");
    $bgColor = imagecolorallocate($im, $r, $g, $b);
    imagefilledrectangle($im, 0, 0, $w, $h, $bgColor);

    // Pola dekoratif modern
    list($ar, $ag, $ab) = sscanf($accentHex, "#%02x%02x%02x");
    $accentColor = imagecolorallocatealpha($im, $ar, $ag, $ab, 100);
    imagefilledellipse($im, $w - 50, 50, 240, 240, $accentColor);
    imagefilledellipse($im, 70, $h - 30, 200, 200, $accentColor);

    // Frame border halus
    $white = imagecolorallocate($im, 255, 255, 255);
    $whiteAlpha = imagecolorallocatealpha($im, 255, 255, 255, 85);
    imagerectangle($im, 10, 10, $w - 11, $h - 11, $whiteAlpha);

    // Tag badge di pojok kiri atas
    $tagBg = imagecolorallocate($im, $ar, $ag, $ab);
    $tagW = max(220, strlen($tag) * 9 + 30);
    imagefilledrectangle($im, 25, 25, 25 + $tagW, 60, $tagBg);
    imagestring($im, 4, 38, 35, $tag, $white);

    // Lokasi di kanan atas
    imagestring($im, 3, $w - 240, 35, $location, $whiteAlpha);

    // Judul Usaha di Tengah
    imagestring($im, 5, 30, $h / 2 - 20, $title, $white);
    imagestring($im, 3, 30, $h / 2 + 15, "KABUPATEN INDRAGIRI HULU, RIAU", $white);

    // Footer info
    imagestring($im, 2, 30, $h - 35, "JASA INHU - MITRA RESMI TERVERIFIKASI KTP", $white);

    imagejpeg($im, $filename, 90);
    imagedestroy($im);
}

// 2. Generate Gambar Cover Usaha Mitra
generate_card_image("$provDir/bengkel_motor_rengat.jpg", "BENGKEL BERKAH MOTOR INJEKSI", "SPESIALIS MOTOR & MOGOK", "KEC. RENGAT", "#0f172a", "#0d9488");
generate_card_image("$provDir/sejuk_jaya_ac.jpg", "SEJUK JAYA TEHNIK PENDINGIN", "CUCI & SERVIS AC RUMAH", "KEC. SEBERIDA", "#082f49", "#0284c7");
generate_card_image("$provDir/las_karya_mandiri.jpg", "BENGKEL LAS KARYA MANDIRI", "KANOPI & PAGAR BESI", "KEC. PASIR PENYU", "#1e1b4b", "#6366f1");
generate_card_image("$provDir/sumur_bor_tirta.jpg", "JASA SUMUR BOR AIR BERSIH", "SUMUR BOR & JETPUMP", "KEC. RENGAT BARAT", "#022c22", "#059669");
generate_card_image("$provDir/pickup_lintas_sawit.jpg", "ANGKUTAN SAWIT & SEWA PICKUP", "ARMADA ANGKUT L300", "KEC. SEBERIDA", "#451a03", "#d97706");
generate_card_image("$provDir/renovasi_pak_kumis.jpg", "TUKANG BANGUNAN PAK KUMIS", "RENOVASI & CAT RUMAH", "KEC. RENGAT", "#3b0764", "#9333ea");
generate_card_image("$provDir/rian_elektrik.jpg", "RIAN ELEKTRIK SIAGA 24 JAM", "INSTALASI & KONSLETING", "KEC. RENGAT BARAT", "#1c1917", "#eab308");
generate_card_image("$provDir/sawit_barokah.jpg", "RAWAT & PANEN SAWIT BAROKAH", "BABAT, D尋DOS & SEMPROT", "KEC. BATANG GANSAL", "#14532d", "#16a34a");
generate_card_image("$provDir/lensa_melayu.jpg", "LENSA MELAYU FOTOGRAFI & CINEMA", "WEDDING & DOKUMENTASI", "KEC. RENGAT BARAT", "#09090b", "#e11d48");
generate_card_image("$provDir/griya_cantik_mua.jpg", "GRIYA CANTIK MUA & RIAS PENGANTIN", "RIAS PENGANTIN MELAYU", "KEC. PASIR PENYU", "#831843", "#ec4899");
generate_card_image("$provDir/homecare_bunda_sehat.jpg", "HOMECARE MEDIS BUNDA SEHAT", "RAWAT LUKA & LANSIA", "KEC. RENGAT", "#064e3b", "#14b8a6");
generate_card_image("$provDir/bubut_belilas_jaya.jpg", "BENGKEL BUBUT BELILAS JAYA", "SPESIALIS BUBUT LOGAM", "KEC. SEBERIDA", "#1e293b", "#0d9488");

// 3. Generate Portofolio Before - After
generate_card_image("$portDir/motor_before.jpg", "Kondisi Motor Mogok & Injeksi Tersumbat", "[SEBELUM PENGERJAAN]", "RENGAT", "#7f1d1d", "#dc2626");
generate_card_image("$portDir/motor_after.jpg", "Mesin Halus Tarikan Enteng Siap Jalan", "[HASIL KERJA SELESAI]", "RENGAT", "#064e3b", "#10b981");

generate_card_image("$portDir/ac_before.jpg", "Filter AC Hitam Berdebu & Netes Air", "[SEBELUM DICUCI]", "BELILAS", "#334155", "#64748b");
generate_card_image("$portDir/ac_after.jpg", "Evaporator Bersih Kinclong Dingin Menggigil", "[HASIL KERJA SELESAI]", "BELILAS", "#075985", "#0ea5e9");

generate_card_image("$portDir/kanopi_before.jpg", "Halaman Terik Panas & Belum Ada Peneduh", "[SEBELUM PASANG KANOPI]", "AIR MOLEK", "#475569", "#94a3b8");
generate_card_image("$portDir/kanopi_after.jpg", "Kanopi Baja Ringan Minimalis Kokoh Rapi", "[HASIL KERJA SELESAI]", "AIR MOLEK", "#1e293b", "#0d9488");

generate_card_image("$portDir/sumur_before.jpg", "Air Keruh Kuning Berkarat & Debit Kecil", "[SEBELUM BOR BARU]", "PEMATANG REBA", "#78350f", "#b45309");
generate_card_image("$portDir/sumur_after.jpg", "Air Sumur Bor Jernih Mengalir Deras", "[HASIL KERJA SELESAI]", "PEMATANG REBA", "#022c22", "#10b981");

generate_card_image("$portDir/cat_before.jpg", "Dinding Kusam Berjamur & Cat Mengelupas", "[SEBELUM RENOVASI]", "RENGAT", "#4c0519", "#be123c");
generate_card_image("$portDir/cat_after.jpg", "Dinding Plamir Halus & Cat Mewah Cerah", "[HASIL KERJA SELESAI]", "RENGAT", "#1e1b4b", "#6366f1");

generate_card_image("$portDir/listrik_before.jpg", "Kabel Sekring Semrawut Rawan Konsleting", "[SEBELUM PERBAIKAN]", "PEMATANG REBA", "#78350f", "#d97706");
generate_card_image("$portDir/listrik_after.jpg", "Instalasi Panel MCB Rapi Standar PLN", "[HASIL KERJA SELESAI]", "PEMATANG REBA", "#0f172a", "#10b981");

generate_card_image("$portDir/sawit_before.jpg", "Kebun Sawit Rimbun Semak Belukar", "[SEBELUM DIBABAT]", "BATANG GANSAL", "#365314", "#65a30d");
generate_card_image("$portDir/sawit_after.jpg", "Piringan & Pasar Pikul Bersih Rapi", "[HASIL KERJA SELESAI]", "BATANG GANSAL", "#14532d", "#22c55e");

generate_card_image("$portDir/wedding_before.jpg", "Dokumentasi Raw Footage Lapangan", "[TAHAP PRODUKSI]", "INHU", "#27272a", "#71717a");
generate_card_image("$portDir/wedding_after.jpg", "Album Foto Mewah & Video Cinematic 4K", "[HASIL KERJA SELESAI]", "INHU", "#09090b", "#f43f5e");

// 4. Update Provider #1 jika ada sisa test
$db->exec("
    UPDATE service_providers 
    SET business_name = 'Bengkel Bubut Belilas Jaya',
        headline = 'Jasa Bubut Logam Presisi, As Roda Truk & Bikin Sparepart Mesin',
        description = 'Spesialis bubut logam presisi, perbaikan as roda pickup & truk sawit, ratakan piringan cakram, dan cor babet di Belilas.',
        image_url = 'uploads/providers/bubut_belilas_jaya.jpg',
        is_verified = 1
    WHERE id = 1 AND business_name LIKE '%1791484033%'
");

// 5. Data 11 Mitra Unggulan Riil
$showcase_providers = [
    [
        'email' => 'berkah.motor@jasainhu.id',
        'name' => 'Mas Doni (Berkah Motor)',
        'phone' => '081275990101',
        'business_name' => 'Bengkel Berkah Motor Rengat',
        'headline' => 'Spesialis Servis Injeksi & Panggilan Motor Mogok 24 Jam di Rengat',
        'description' => "Melayani servis motor Honda, Yamaha, Suzuki, ganti oli original, reset scanner injeksi, tambal ban darurat, dan jemput motor mogok di jalan se-Kecamatan Rengat.",
        'category_id' => 1, // Servis Motor
        'district_id' => 1, // Rengat
        'areas' => [1, 2, 11], // Rengat, Rengat Barat, Kuala Cenaku
        'hourly_rate_min' => 35000,
        'rating_avg' => 4.95,
        'reviews_count' => 38,
        'completed_jobs' => 64,
        'image_url' => 'uploads/providers/bengkel_motor_rengat.jpg',
        'portfolios' => [
            ['title' => 'Perbaikan Mesin Injeksi Vario 150 Brebet & Mati Total', 'before' => 'uploads/portfolios/motor_before.jpg', 'after' => 'uploads/portfolios/motor_after.jpg'],
        ],
        'reviews' => [
            ['name' => 'Fahmi Reza (Warga Rengat)', 'rating' => 5, 'comment' => 'Motor mogok jam 9 malam di jalan dekat Danau Raja, ditelepon mas Doni langsung datang bawa alat. 30 menit kelar. Luar biasa sangat menolong!'],
            ['name' => 'Siti Nurhaliza', 'rating' => 5, 'comment' => 'Tarif jujur tidak dimahal-mahalin, ganti oli sekalian dicek kelistrikan gratis. Mantap.'],
        ]
    ],
    [
        'email' => 'sejuk.jaya@jasainhu.id',
        'name' => 'Pak Hendra (Sejuk Jaya)',
        'phone' => '081275990202',
        'business_name' => 'Sejuk Jaya Tehnik Belilas',
        'headline' => 'Cuci AC Bersih, Bongkar Pasang & Tambah Freon Bergaransi di Belilas',
        'description' => "Teknisi AC bersertifikat di Belilas (Seberida). Kami melayani cuci AC split rumah tangga, ruko, perkantoran, isi freon R32/R410A, dan atasi AC bocor air.",
        'category_id' => 3, // Servis AC
        'district_id' => 4, // Seberida (Belilas)
        'areas' => [4, 5, 6], // Seberida, Batang Cenaku, Batang Gansal
        'hourly_rate_min' => 75000,
        'rating_avg' => 4.90,
        'reviews_count' => 45,
        'completed_jobs' => 82,
        'image_url' => 'uploads/providers/sejuk_jaya_ac.jpg',
        'portfolios' => [
            ['title' => 'Cuci Bersih Total & Perbaikan AC Netes Air di Perumahan Belilas', 'before' => 'uploads/portfolios/ac_before.jpg', 'after' => 'uploads/portfolios/ac_after.jpg'],
        ],
        'reviews' => [
            ['name' => 'H. Ridwan (Belilas)', 'rating' => 5, 'comment' => 'Pengerjaan bersih sekali, lantai dialasi terpal jadi tidak kotor kena air cucian AC. Langganan tetap!'],
            ['name' => 'Ibu Maya', 'rating' => 5, 'comment' => 'AC kamar yang awalnya cuma keluar angin doang sekarang dingin menggigil. Top markotop.'],
        ]
    ],
    [
        'email' => 'karya.mandiri@jasainhu.id',
        'name' => 'Cak Kuswanto (Las Mandiri)',
        'phone' => '081275990303',
        'business_name' => 'Bengkel Las Karya Mandiri Air Molek',
        'headline' => 'Pembuatan Kanopi Baja Ringan, Pagar Besi Minimalis & Teralis Pintu',
        'description' => "Melayani pembuatan pagar dorong, teralis jendela pengaman, kanopi spandek/alderon, tangga putar, dan servis las panggilan di Air Molek & Pasir Penyu.",
        'category_id' => 8, // Bengkel Las
        'district_id' => 3, // Pasir Penyu (Air Molek)
        'areas' => [3, 7, 12, 13], // Pasir Penyu, Kelayang, Sungai Lala, Lirik
        'hourly_rate_min' => 150000,
        'rating_avg' => 4.88,
        'reviews_count' => 29,
        'completed_jobs' => 47,
        'image_url' => 'uploads/providers/las_karya_mandiri.jpg',
        'portfolios' => [
            ['title' => 'Pemasangan Kanopi Baja Ringan & Atap Spandek Rumah 6x5 Meter', 'before' => 'uploads/portfolios/kanopi_before.jpg', 'after' => 'uploads/portfolios/kanopi_after.jpg'],
        ],
        'reviews' => [
            ['name' => 'dr. Anton (Air Molek)', 'rating' => 5, 'comment' => 'Besi tebal sesuai pesanan, las-lasan rapi dan cat dasarnya anti karat rapi. Sangat profesional.'],
        ]
    ],
    [
        'email' => 'tirta.sumur@jasainhu.id',
        'name' => 'Pak Joko (Tirta Bor)',
        'phone' => '081275990404',
        'business_name' => 'Jasa Sumur Bor & Pompa Air Tirta Pematang Reba',
        'headline' => 'Pengeboran Sumur Air Bersih Bebas Karat & Servis Pompa Jetpump',
        'description' => "Pengeboran sumur rumah dan kebun hingga kedalaman 40-80 meter untuk dapat air jernih tidak bau besi di area Rengat Barat (Pematang Reba) dan sekitarnya.",
        'category_id' => 6, // Bangunan (Tukang Sumur/Pipa)
        'district_id' => 2, // Rengat Barat
        'areas' => [2, 1, 4], // Rengat Barat, Rengat, Seberida
        'hourly_rate_min' => 250000,
        'rating_avg' => 4.92,
        'reviews_count' => 24,
        'completed_jobs' => 36,
        'image_url' => 'uploads/providers/sumur_bor_tirta.jpg',
        'portfolios' => [
            ['title' => 'Pengeboran Sumur Kedalaman 45 Meter & Pemasangan Jetpump Air Jernih', 'before' => 'uploads/portfolios/sumur_before.jpg', 'after' => 'uploads/portfolios/sumur_after.jpg'],
        ],
        'reviews' => [
            ['name' => 'Pak Suryo (Pematang Reba)', 'rating' => 5, 'comment' => 'Alhamdulillah airnya jernih sekali tidak bau besi lagi. Kerja cepat 2 hari beres.'],
        ]
    ],
    [
        'email' => 'lintas.sawit@jasainhu.id',
        'name' => 'Bang Ucok (Lintas Pickup)',
        'phone' => '081275990505',
        'business_name' => 'Angkutan Sawit & Sewa Pickup Seberida',
        'headline' => 'Sewa Pickup Harian/Borongan, Angkut Buah Sawit & Pindahan Rumah',
        'description' => "Armada L300 dan Gran Max siap jalan untuk angsir buah kelapa sawit ke RAM/PKS, antar material toko bangunan, dan pindahan rumah se-Inhu.",
        'category_id' => 10, // Angkutan
        'district_id' => 4, // Seberida
        'areas' => [4, 5, 6, 2], // Seberida, Batang Cenaku, Batang Gansal, Rengat Barat
        'hourly_rate_min' => 120000,
        'rating_avg' => 4.96,
        'reviews_count' => 52,
        'completed_jobs' => 110,
        'image_url' => 'uploads/providers/pickup_lintas_sawit.jpg',
        'portfolios' => [],
        'reviews' => [
            ['name' => 'Pak Gunawan Sawit', 'rating' => 5, 'comment' => 'Sopir hafal medan jalan tanah becek, muatan aman sampai tujuan. Sangat recommended.'],
        ]
    ],
    [
        'email' => 'pak.kumis@jasainhu.id',
        'name' => 'Pak Kumis (Sutrisno)',
        'phone' => '081275990606',
        'business_name' => 'Tukang Bangunan Pak Kumis Rengat',
        'headline' => 'Ahli Pasang Keramik, Plamir, Cat Rumah, Atap Bocor & Renovasi Total',
        'description' => "Berpengalaman lebih dari 15 tahun di bidang konstruksi rumah di Rengat. Melayani pasang keramik lantai/dinding, plamir halus, pengecatan interior/eksterior, dan atap bocor.",
        'category_id' => 6, // Bangunan
        'district_id' => 1, // Rengat
        'areas' => [1, 2, 11], // Rengat, Rengat Barat, Kuala Cenaku
        'hourly_rate_min' => 125000,
        'rating_avg' => 4.94,
        'reviews_count' => 31,
        'completed_jobs' => 58,
        'image_url' => 'uploads/providers/renovasi_pak_kumis.jpg',
        'portfolios' => [
            ['title' => 'Pengecatan Ulang & Plamir Dinding Rumah Tipe 45 di Kampung Dagang', 'before' => 'uploads/portfolios/cat_before.jpg', 'after' => 'uploads/portfolios/cat_after.jpg'],
        ],
        'reviews' => [
            ['name' => 'Ibu Rahmawati (Rengat)', 'rating' => 5, 'comment' => 'Kerjaan rapi sekali, nat keramik lurus dan cat dinding rata tanpa belang. Pak Kumis orangnya ramah dan jujur.'],
        ]
    ],
    [
        'email' => 'rian.elektrik@jasainhu.id',
        'name' => 'Mas Rian (Teknisi Listrik)',
        'phone' => '081275990707',
        'business_name' => 'Rian Elektrik Siaga Listrik 24 Jam',
        'headline' => 'Instalasi Listrik Rumah, Perbaikan Konsleting & MCB Anjlok 24 Jam',
        'description' => "Layanan perbaikan listrik darurat di Rengat Barat & sekitarnya. Atasi mati lampu mendadak, MCB sering jepret, penambahan titik lampu/stop kontak, dan instalasi grounding aman.",
        'category_id' => 5, // Listrik
        'district_id' => 2, // Rengat Barat
        'areas' => [2, 1, 13, 3], // Rengat Barat, Rengat, Lirik, Pasir Penyu
        'hourly_rate_min' => 50000,
        'rating_avg' => 4.98,
        'reviews_count' => 41,
        'completed_jobs' => 73,
        'image_url' => 'uploads/providers/rian_elektrik.jpg',
        'portfolios' => [
            ['title' => 'Perbaikan Jalur Konsleting & Penataan Ulang Panel MCB Rumah', 'before' => 'uploads/portfolios/listrik_before.jpg', 'after' => 'uploads/portfolios/listrik_after.jpg'],
        ],
        'reviews' => [
            ['name' => 'Bapak Irwan (Pematang Reba)', 'rating' => 5, 'comment' => 'Listrik rumah padam separuh tengah malam, Mas Rian datang cepat dan ketemu kabel yang meleleh di plafon. Sangat cekatan!'],
        ]
    ],
    [
        'email' => 'sawit.barokah@jasainhu.id',
        'name' => 'Bang Herman (Mandor Sawit)',
        'phone' => '081275990808',
        'business_name' => 'Kelompok Rawat & Panen Sawit Barokah',
        'headline' => 'Tenaga Babat Rumput Kebun, Semprot Gulma, Dodos Panen & Pruning',
        'description' => "Regu pekerja kebun sawit terpercaya di Batang Gansal & Seberida. Siap borongan babat piringan/pasar pikul, semprot herbisida gulma, dodos buah pasir/matang, dan pupuk.",
        'category_id' => 9, // Pertanian & Perkebunan
        'district_id' => 6, // Batang Gansal
        'areas' => [6, 4, 5], // Batang Gansal, Seberida, Batang Cenaku
        'hourly_rate_min' => 100000,
        'rating_avg' => 4.92,
        'reviews_count' => 35,
        'completed_jobs' => 89,
        'image_url' => 'uploads/providers/sawit_barokah.jpg',
        'portfolios' => [
            ['title' => 'Pembersihan Babat Rumput Liar Kebun Sawit 4 Hektar di Batang Gansal', 'before' => 'uploads/portfolios/sawit_before.jpg', 'after' => 'uploads/portfolios/sawit_after.jpg'],
        ],
        'reviews' => [
            ['name' => 'Haji Mansyur (Pengusaha Sawit)', 'rating' => 5, 'comment' => 'Kerja regu Bang Herman cepat dan bersih. Buah tidak ada yang tertinggal di pelepah. Sangat puas.'],
        ]
    ],
    [
        'email' => 'lensa.melayu@jasainhu.id',
        'name' => 'Fajar Ramadhan (Lensa Melayu)',
        'phone' => '081275990909',
        'business_name' => 'Lensa Melayu Fotografi & Wedding Cinema',
        'headline' => 'Dokumentasi Pesta Pernikahan, Foto Adat Melayu, Wisuda & Video Drone',
        'description' => "Fotografer & videografer profesional se-Kabupaten Indragiri Hulu. Melayani foto akad nikah, resepsi adat Melayu Inhu, video cinematic teaser 4K, foto wisuda, dan pre-wedding.",
        'category_id' => 13, // Fotografi & Videografi
        'district_id' => 2, // Rengat Barat
        'areas' => [1, 2, 3, 4, 13], // Multi kecamatan se-Inhu
        'hourly_rate_min' => 500000,
        'rating_avg' => 4.97,
        'reviews_count' => 28,
        'completed_jobs' => 42,
        'image_url' => 'uploads/providers/lensa_melayu.jpg',
        'portfolios' => [
            ['title' => 'Dokumentasi Resepsi Pernikahan Adat Melayu di Gedung Dang Purnama Rengat', 'before' => 'uploads/portfolios/wedding_before.jpg', 'after' => 'uploads/portfolios/wedding_after.jpg'],
        ],
        'reviews' => [
            ['name' => 'Rina & Dimas (Pengantin Baru)', 'rating' => 5, 'comment' => 'Hasil foto dan video wedding-nya keren banget! Tone warnanya elegan dan candid moment-nya dapet semua.'],
        ]
    ],
    [
        'email' => 'griya.cantik@jasainhu.id',
        'name' => 'Nia Rahmawati (Kak Nia MUA)',
        'phone' => '081275991010',
        'business_name' => 'Griya Cantik MUA & Rias Pengantin Kak Nia',
        'headline' => 'Make Up Artist Pengantin Melayu, Wisuda, Lamaran & Sewa Baju Adat',
        'description' => "Rias wajah flawless tahan lama tidak mudah luntur di Pasir Penyu & sekitarnya. Melayani rias pengantin, sunatan, kebaya wisuda, dan sewa gaun pengantin modern.",
        'category_id' => 12, // Acara & Hiburan
        'district_id' => 3, // Pasir Penyu (Air Molek)
        'areas' => [3, 12, 7, 9], // Pasir Penyu, Sungai Lala, Kelayang, Peranap
        'hourly_rate_min' => 150000,
        'rating_avg' => 4.95,
        'reviews_count' => 33,
        'completed_jobs' => 61,
        'image_url' => 'uploads/providers/griya_cantik_mua.jpg',
        'portfolios' => [],
        'reviews' => [
            ['name' => 'Dinda Puspita (Air Molek)', 'rating' => 5, 'comment' => 'Make up wisuda awet dari pagi sampai sore padahal sempat gerah. Banyak yang muji riasannya natural tapi manglingi.'],
        ]
    ],
    [
        'email' => 'bunda.sehat@jasainhu.id',
        'name' => 'Ners Anisa S.Kep (STR Aktif)',
        'phone' => '081275991111',
        'business_name' => 'Homecare Medis & Fisioterapi Bunda Sehat',
        'headline' => 'Rawat Luka Diabetes, Pasang Selang NGT/Kateter & Perawatan Lansia',
        'description' => "Tenaga medis perawat profesional dengan Surat Tanda Registrasi (STR) aktif di Rengat. Melayani perawatan luka modern pasca operasi, cek gula darah/tensi berkala, dan dampingan lansia ke rumah.",
        'category_id' => 18, // Medical
        'district_id' => 1, // Rengat
        'areas' => [1, 2, 11], // Rengat, Rengat Barat, Kuala Cenaku
        'hourly_rate_min' => 80000,
        'rating_avg' => 5.00,
        'reviews_count' => 19,
        'completed_jobs' => 39,
        'image_url' => 'uploads/providers/homecare_bunda_sehat.jpg',
        'portfolios' => [],
        'reviews' => [
            ['name' => 'Keluarga Bpk. Syamsudin (Rengat)', 'rating' => 5, 'comment' => 'Ners Anisa sangat sabar dan telaten merawat luka diabetes orang tua kami. Alat-alatnya steril dan penjelasannya jelas.'],
        ]
    ]
];

$passwordHash = password_hash('password123', PASSWORD_BCRYPT);

foreach ($showcase_providers as $spData) {
    // 1. Cek atau Buat User
    $stmtUser = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmtUser->execute([$spData['email']]);
    $userId = $stmtUser->fetchColumn();

    if (!$userId) {
        $insUser = $db->prepare("
            INSERT INTO users (role_id, name, email, phone, password_hash, is_active, created_at, updated_at)
            VALUES (3, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $insUser->execute([$spData['name'], $spData['email'], $spData['phone'], $passwordHash]);
        $userId = $db->lastInsertId();
    } else {
        $db->prepare("UPDATE users SET name = ?, phone = ?, is_active = 1 WHERE id = ?")
           ->execute([$spData['name'], $spData['phone'], $userId]);
    }

    // 2. Cek atau Buat Profile
    $stmtProf = $db->prepare("SELECT id FROM profiles WHERE user_id = ?");
    $stmtProf->execute([$userId]);
    if (!$stmtProf->fetchColumn()) {
        $db->prepare("
            INSERT INTO profiles (user_id, bio, address, district_id)
            VALUES (?, ?, 'Jl. Raya Utama Wilayah Inhu', ?)
        ")->execute([$userId, $spData['headline'], $spData['district_id']]);
    }

    // 3. Cek atau Buat Service Provider
    $stmtProv = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
    $stmtProv->execute([$userId]);
    $providerId = $stmtProv->fetchColumn();

    if (!$providerId) {
        $insProv = $db->prepare("
            INSERT INTO service_providers 
            (user_id, primary_category_id, business_name, headline, description, experience_years, hourly_rate_min, is_verified, rating_avg, reviews_count, completed_jobs, image_url, district_id, wallet_balance, created_at)
            VALUES (?, ?, ?, ?, ?, 8, ?, 1, ?, ?, ?, ?, ?, 50000, NOW())
        ");
        $insProv->execute([
            $userId,
            $spData['category_id'],
            $spData['business_name'],
            $spData['headline'],
            $spData['description'],
            $spData['hourly_rate_min'],
            $spData['rating_avg'],
            $spData['reviews_count'],
            $spData['completed_jobs'],
            $spData['image_url'],
            $spData['district_id']
        ]);
        $providerId = $db->lastInsertId();
    } else {
        $updProv = $db->prepare("
            UPDATE service_providers 
            SET business_name = ?, primary_category_id = ?, headline = ?, description = ?, hourly_rate_min = ?,
                is_verified = 1, rating_avg = ?, reviews_count = ?, completed_jobs = ?, image_url = ?, district_id = ?
            WHERE id = ?
        ");
        $updProv->execute([
            $spData['business_name'],
            $spData['category_id'],
            $spData['headline'],
            $spData['description'],
            $spData['hourly_rate_min'],
            $spData['rating_avg'],
            $spData['reviews_count'],
            $spData['completed_jobs'],
            $spData['image_url'],
            $spData['district_id'],
            $providerId
        ]);
    }

    // 4. Masukkan Wilayah Jangkauan (Service Areas)
    $db->prepare("DELETE FROM service_areas WHERE provider_id = ?")->execute([$providerId]);
    if (!empty($spData['areas'])) {
        $stmtArea = $db->prepare("INSERT IGNORE INTO service_areas (provider_id, district_id) VALUES (?, ?)");
        foreach ($spData['areas'] as $distId) {
            $stmtArea->execute([$providerId, $distId]);
        }
    }

    // 5. Masukkan Portofolio Before-After
    $db->prepare("DELETE FROM provider_portfolios WHERE provider_id = ?")->execute([$providerId]);
    if (!empty($spData['portfolios'])) {
        foreach ($spData['portfolios'] as $p) {
            $db->prepare("
                INSERT INTO provider_portfolios 
                (provider_id, title, image_before, image_after, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$providerId, $p['title'], $p['before'], $p['after']]);
        }
    }

    // 6. Masukkan Ulasan & Rating Bintang Nyata
    $stmtCustomer = $db->query("SELECT id FROM users WHERE role_id = 2 LIMIT 1");
    $customerId = (int)($stmtCustomer->fetchColumn() ?: 1);

    $db->prepare("DELETE FROM reviews WHERE provider_id = ?")->execute([$providerId]);
    if (!empty($spData['reviews'])) {
        foreach ($spData['reviews'] as $r) {
            $stmtReq = $db->prepare("
                INSERT INTO service_requests (user_id, category_id, district_id, title, description, budget, status, created_at)
                VALUES (?, ?, ?, ?, 'Layanan jasa diselesaikan dengan sangat baik dan memuaskan.', ?, 'completed', NOW())
            ");
            $stmtReq->execute([$customerId, $spData['category_id'], $spData['district_id'], 'Pekerjaan: ' . $spData['business_name'], $spData['hourly_rate_min']]);
            $sampleReqId = $db->lastInsertId();

            $db->prepare("
                INSERT INTO reviews (request_id, user_id, provider_id, rating, comment, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$sampleReqId, $customerId, $providerId, $r['rating'], $r['comment']]);
        }
    }

    echo " + Mitra unggulan seeded: {$spData['business_name']} [Kat: #{$spData['category_id']}] (ID: #{$providerId})\n";
}

// 7. Seed 4 Permintaan Jasa Terbuka (Live Job Board) di Beranda
echo "\n--- SEEDING PERMINTAAN JASA TERBUKA (LIVE JOB BOARD) ---\n";
// Hapus permintaan open dummy yang lama jika ada
$db->exec("DELETE FROM service_requests WHERE status = 'open' AND (title LIKE '%1791484033%' OR title LIKE '%Test%')");

$openRequests = [
    [
        'title' => 'Butuh Cuci & Tambah Freon 2 Unit AC Rumah di Pematang Reba',
        'category_id' => 3, // Servis AC
        'district_id' => 2, // Rengat Barat
        'budget' => 170000,
        'description' => 'AC kamar dan ruang tamu kurang dingin dan air menetes. Butuh teknisi datang besok pagi ke Perumahan Pematang Reba.',
        'urgency' => 'urgent'
    ],
    [
        'title' => 'Panggilan Montir Cek Karburator & Kelistrikan Motor Mogok',
        'category_id' => 1, // Servis Motor
        'district_id' => 1, // Rengat
        'budget' => 60000,
        'description' => 'Motor Beat mogok dekat Danau Raja Rengat. Tidak mau hidup saat distarter. Butuh montir bawa alat cek busi dan pengapian.',
        'urgency' => 'urgent'
    ],
    [
        'title' => 'Cari Tukang Cat Pagar Rumah & Perbaikan Teras Retak',
        'category_id' => 6, // Bangunan
        'district_id' => 1, // Rengat
        'budget' => 450000,
        'description' => 'Pagar besi depan rumah perlu dicat ulang anti karat dan plesteran lantai teras yang retak sekitar 3 meter.',
        'urgency' => 'normal'
    ],
    [
        'title' => 'Sewa Pickup Muat Lemari & Kasur Pindahan ke Simpang Belilas',
        'category_id' => 10, // Angkutan
        'district_id' => 4, // Seberida
        'budget' => 250000,
        'description' => 'Butuh pickup L300 atau Gran Max untuk angkut lemari 3 pintu, kasur springbed, dan beberapa kardus pakaian ke kontrakan baru.',
        'urgency' => 'scheduled'
    ]
];

$stmtCust = $db->query("SELECT id FROM users WHERE role_id = 2 LIMIT 1");
$custUserId = (int)($stmtCust->fetchColumn() ?: 1);

foreach ($openRequests as $req) {
    // Cek apakah judul serupa sudah ada
    $checkReq = $db->prepare("SELECT id FROM service_requests WHERE title = ? AND status = 'open'");
    $checkReq->execute([$req['title']]);
    if (!$checkReq->fetchColumn()) {
        $stmtInsReq = $db->prepare("
            INSERT INTO service_requests (user_id, category_id, district_id, title, description, budget, urgency, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())
        ");
        $stmtInsReq->execute([
            $custUserId,
            $req['category_id'],
            $req['district_id'],
            $req['title'],
            $req['description'],
            $req['budget'],
            $req['urgency']
        ]);
        echo " + Permintaan terbuka seeded: '{$req['title']}' (Budget: Rp " . number_format($req['budget'], 0, ',', '.') . ")\n";
    }
}

echo "\n=== SEED DATA MARKETPLACE VISUAL & MITRA PERCONTOHAN BERHASIL 100%! ===\n";

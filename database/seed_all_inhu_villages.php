<?php
/**
 * Migration Seeder: Lengkapi Seluruh 194 Desa & Kelurahan Se-Kabupaten Indragiri Hulu
 * Sumber Resmi: Kemendagri / BPS Kabupaten Indragiri Hulu
 * Terdiri dari 14 Kecamatan: 16 Kelurahan & 178 Desa (Total 194 Wilayah)
 */

require_once __DIR__ . '/../config/database.php';

$db = get_db();

$villagesData = [
    // 1. KECAMATAN RENGAT (ID: 1) - 6 Kelurahan, 10 Desa
    ['district_id' => 1, 'name' => 'Kampung Besar Kota', 'postal_code' => '29311'],
    ['district_id' => 1, 'name' => 'Kampung Besar Seberang', 'postal_code' => '29312'],
    ['district_id' => 1, 'name' => 'Kampung Dagang', 'postal_code' => '29313'],
    ['district_id' => 1, 'name' => 'Pasar Kota', 'postal_code' => '29314'],
    ['district_id' => 1, 'name' => 'Sekip Hilir', 'postal_code' => '29315'],
    ['district_id' => 1, 'name' => 'Sekip Hulu', 'postal_code' => '29316'],
    ['district_id' => 1, 'name' => 'Kampung Pulau', 'postal_code' => '29319'],
    ['district_id' => 1, 'name' => 'Kuantan Baru', 'postal_code' => '29319'],
    ['district_id' => 1, 'name' => 'Pasir Kemilu', 'postal_code' => '29319'],
    ['district_id' => 1, 'name' => 'Pulau Gajah', 'postal_code' => '29319'],
    ['district_id' => 1, 'name' => 'Rantau Mapesai', 'postal_code' => '29319'],
    ['district_id' => 1, 'name' => 'Rawa Bangun', 'postal_code' => '29319'],
    ['district_id' => 1, 'name' => 'Sungai Beringin', 'postal_code' => '29319'],
    ['district_id' => 1, 'name' => 'Sungai Guntung Hilir', 'postal_code' => '29319'],
    ['district_id' => 1, 'name' => 'Sungai Guntung Tengah', 'postal_code' => '29319'],
    ['district_id' => 1, 'name' => 'Sungai Raya', 'postal_code' => '29319'],

    // 2. KECAMATAN RENGAT BARAT (ID: 2) - 1 Kelurahan, 17 Desa
    ['district_id' => 2, 'name' => 'Pematang Reba', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Air Jernih', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Alang Kepayang', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Barangan', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Bukit Petaling', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Danau Baru', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Danau Tiga', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Kota Lama', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Pekan Heran', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Pematang Jaya', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Rantau Bakung', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Redang', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Sialang Dua Dahan', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Sungai Baung', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Sungai Dawu', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Talang Jerinjing', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Tanah Datar', 'postal_code' => '29351'],
    ['district_id' => 2, 'name' => 'Tanah Makmur', 'postal_code' => '29351'],

    // 3. KECAMATAN PASIR PENYU (ID: 3) - 5 Kelurahan, 8 Desa
    ['district_id' => 3, 'name' => 'Air Molek I', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Kembang Harum', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Sekar Mawar', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Tanah Merah', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Tanjung Gading', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Air Molek II', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Batu Gajah', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Candirejo', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Jatirejo', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Lembah Dusun Gading', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Pasir Keranji', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Petalongan', 'postal_code' => '29352'],
    ['district_id' => 3, 'name' => 'Serumpun Jaya', 'postal_code' => '29352'],

    // 4. KECAMATAN SEBERIDA (ID: 4) - 1 Kelurahan, 10 Desa
    ['district_id' => 4, 'name' => 'Pangkalan Kasai', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Bandar Padang', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Beligan', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Bukit Meranti', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Buluh Rampai', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Kelesa', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Payarumbai', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Petala Bumi', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Serasam', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Sibabat', 'postal_code' => '29371'],
    ['district_id' => 4, 'name' => 'Titian Resak', 'postal_code' => '29371'],

    // 5. KECAMATAN BATANG CENAKU (ID: 5) - 20 Desa
    ['district_id' => 5, 'name' => 'Alim', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Anak Talang', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Aur Cina', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Batu Papan', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Bukit Lingkar', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Bukit Lipai', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Cenaku Kecil', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Kepayang Sari', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Kerubung Jaya', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Kuala Gading', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Kuala Kilan', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Lahai Kemuning', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Pataling Jaya', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Pejangki', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Pematang Manggis', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Punti Anai', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Sanglap', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Sipang', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Talang Bersemi', 'postal_code' => '29372'],
    ['district_id' => 5, 'name' => 'Talang Mulya', 'postal_code' => '29372'],

    // 6. KECAMATAN BATANG GANSAL (ID: 6) - 10 Desa
    ['district_id' => 6, 'name' => 'Belimbing', 'postal_code' => '29373'],
    ['district_id' => 6, 'name' => 'Rantau Langsat', 'postal_code' => '29373'],
    ['district_id' => 6, 'name' => 'Ringin', 'postal_code' => '29373'],
    ['district_id' => 6, 'name' => 'Seberida', 'postal_code' => '29373'],
    ['district_id' => 6, 'name' => 'Siambul', 'postal_code' => '29373'],
    ['district_id' => 6, 'name' => 'Sungai Akar', 'postal_code' => '29373'],
    ['district_id' => 6, 'name' => 'Talang Lakat', 'postal_code' => '29373'],
    ['district_id' => 6, 'name' => 'Usul', 'postal_code' => '29373'],
    ['district_id' => 6, 'name' => 'Penyaguan', 'postal_code' => '29373'],
    ['district_id' => 6, 'name' => 'Danau Rambai', 'postal_code' => '29373'],

    // 7. KECAMATAN KELAYANG (ID: 7) - 1 Kelurahan, 16 Desa
    ['district_id' => 7, 'name' => 'Simpang Kelayang', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Bongkal Malang', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Bukit Selanjut', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Dusun Tua', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Dusun Tua Pelang', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Kota Medan', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Pasir Beringin', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Pelangko', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Polak Pisang', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Pulau Sengkilo', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Simpang Kota Medan', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Sungai Kuning Benio', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Sungai Banyak Ikan', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Sungai Golang', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Sungai Pasir Putih', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Tanjung Beludu', 'postal_code' => '29355'],
    ['district_id' => 7, 'name' => 'Teluk Sejuah', 'postal_code' => '29355'],

    // 8. KECAMATAN RAKIT KULIM (ID: 8) - 19 Desa
    ['district_id' => 8, 'name' => 'Batu Sawar', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Bukit Indah', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Kampung Bunga', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Kelayang', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Kota Baru', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Kuantan Tenang', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Lubuk Sitarak', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Petonggan', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Rimba Seminai', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Sungai Ekok', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Talang Durian Cacar', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Talang Gedabu', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Talang Parigi', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Talang Pring Jaya', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Talang Selantai', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Talang Suka Maju', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Talang Sungai Limau', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Talang Sungai Parit', 'postal_code' => '29356'],
    ['district_id' => 8, 'name' => 'Talang Tujuh Buah Tangga', 'postal_code' => '29356'],

    // 9. KECAMATAN PERANAP (ID: 9) - 2 Kelurahan, 10 Desa
    ['district_id' => 9, 'name' => 'Baturijal Hilir', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Peranap', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Baturijal Barat', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Baturijal Hulu', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Gumanti', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Katipo Pura', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Pandan Wangi', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Pauh Ranap', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Semelinang Tebing', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Semelinang Darat', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Serai Wangi', 'postal_code' => '29354'],
    ['district_id' => 9, 'name' => 'Setako Raya', 'postal_code' => '29354'],

    // 10. KECAMATAN BATANG PERANAP (ID: 10) - 10 Desa
    ['district_id' => 10, 'name' => 'Koto Tuo', 'postal_code' => '29357'],
    ['district_id' => 10, 'name' => 'Peladangan', 'postal_code' => '29357'],
    ['district_id' => 10, 'name' => 'Pematang', 'postal_code' => '29357'],
    ['district_id' => 10, 'name' => 'Pematang Benteng', 'postal_code' => '29357'],
    ['district_id' => 10, 'name' => 'Pesajian', 'postal_code' => '29357'],
    ['district_id' => 10, 'name' => 'Puntikayu', 'postal_code' => '29357'],
    ['district_id' => 10, 'name' => 'Selunak', 'postal_code' => '29357'],
    ['district_id' => 10, 'name' => 'Sencano Jaya', 'postal_code' => '29357'],
    ['district_id' => 10, 'name' => 'Suka Maju', 'postal_code' => '29357'],
    ['district_id' => 10, 'name' => 'Sungai Aur', 'postal_code' => '29357'],

    // 11. KECAMATAN KUALA CENAKU (ID: 11) - 10 Desa
    ['district_id' => 11, 'name' => 'Kuala Cenaku', 'postal_code' => '29381'],
    ['district_id' => 11, 'name' => 'Kuala Mulia', 'postal_code' => '29381'],
    ['district_id' => 11, 'name' => 'Pulau Gelang', 'postal_code' => '29381'],
    ['district_id' => 11, 'name' => 'Pulau Jum\'at', 'postal_code' => '29381'],
    ['district_id' => 11, 'name' => 'Rawa Asri', 'postal_code' => '29381'],
    ['district_id' => 11, 'name' => 'Rawa Sekip', 'postal_code' => '29381'],
    ['district_id' => 11, 'name' => 'Suka Jadi', 'postal_code' => '29381'],
    ['district_id' => 11, 'name' => 'Tambak', 'postal_code' => '29381'],
    ['district_id' => 11, 'name' => 'Tanjung Sari', 'postal_code' => '29381'],
    ['district_id' => 11, 'name' => 'Teluk Sungkai', 'postal_code' => '29381'],

    // 12. KECAMATAN SUNGAI LALA (ID: 12) - 12 Desa
    ['district_id' => 12, 'name' => 'Kelawat', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Kuala Lala', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Morong', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Pasir Batu Mandi', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Pasir Bongkal', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Pasir Kelampaian', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Pasir Selabau', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Perkebunan Sungai Parit', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Perkebunan Sungai Lala', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Sungai Lala', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Sungai Air Putih', 'postal_code' => '29361'],
    ['district_id' => 12, 'name' => 'Tanjung Danau', 'postal_code' => '29361'],

    // 13. KECAMATAN LIRIK (ID: 13) - 17 Desa
    ['district_id' => 13, 'name' => 'Banjar Balam', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Gudang Batu', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Japura', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Lambang Sari I, II, III', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Lambang Sari IV', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Lambang Sari V', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Lirik Area', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Mekarsari', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Pasir Ringgit', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Pasir Sialang Jaya', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Redang Seko', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Rejosari', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Seko Lubuk Tigo', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Sidomulyo', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Sukajadi', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Sungai Sagu', 'postal_code' => '29353'],
    ['district_id' => 13, 'name' => 'Wonosari', 'postal_code' => '29353'],

    // 14. KECAMATAN LUBUK BATU JAYA (ID: 14) - 9 Desa
    ['district_id' => 14, 'name' => 'Air Putih', 'postal_code' => '29362'],
    ['district_id' => 14, 'name' => 'Kulim Jaya', 'postal_code' => '29362'],
    ['district_id' => 14, 'name' => 'Lubuk Batu Tinggal', 'postal_code' => '29362'],
    ['district_id' => 14, 'name' => 'Pondok Gelugur', 'postal_code' => '29362'],
    ['district_id' => 14, 'name' => 'Pontian Mekar', 'postal_code' => '29362'],
    ['district_id' => 14, 'name' => 'Rimpian', 'postal_code' => '29362'],
    ['district_id' => 14, 'name' => 'Sei Beras-beras', 'postal_code' => '29362'],
    ['district_id' => 14, 'name' => 'Sei Seberas Hilir', 'postal_code' => '29362'],
    ['district_id' => 14, 'name' => 'Tasik Juang', 'postal_code' => '29362'],
];

echo "[START] Memulai penyisipan data 194 Desa & Kelurahan se-Inhu...\n";

$inserted = 0;
$skipped = 0;

$stmtCheck = $db->prepare("SELECT id FROM villages WHERE district_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
$stmtIns = $db->prepare("INSERT INTO villages (district_id, name, postal_code, created_at) VALUES (?, ?, ?, NOW())");

foreach ($villagesData as $v) {
    $stmtCheck->execute([$v['district_id'], $v['name']]);
    if ($stmtCheck->fetch()) {
        $skipped++;
    } else {
        $stmtIns->execute([$v['district_id'], $v['name'], $v['postal_code']]);
        $inserted++;
    }
}

$totalInDb = (int)$db->query("SELECT COUNT(*) FROM villages")->fetchColumn();

echo "[SUCCESS] Selesai!\n";
echo " - Ditambahkan baru: $inserted desa/kelurahan\n";
echo " - Sudah ada sebelumnya: $skipped desa/kelurahan\n";
echo " - Total desa/kelurahan di database saat ini: $totalInDb desa/kelurahan\n";

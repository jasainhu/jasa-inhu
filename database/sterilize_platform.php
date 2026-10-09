<?php
/**
 * Script Sterilisasi Database JASA INHU
 * Menata ulang ID pengguna dan mitra agar rapi & berurutan dari #001
 * Menghapus akun pengujian dummy dan pesanan uji coba
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    echo "=== MEMULAI STERILISASI DATABASE JASA INHU ===\n";

    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Bersihkan transaksi pengujian
    echo "1. Membersihkan data transaksi pengujian (pesanan, chat, notifikasi, log)...\n";
    $truncate_tables = [
        'chat_messages',
        'conversations',
        'notifications',
        'reports',
        'service_request_timeline',
        'service_request_responses',
        'reviews',
        'service_requests',
        'wallet_topup_requests',
        'provider_wallet_transactions',
        'provider_discussions'
    ];

    foreach ($truncate_tables as $table) {
        try {
            $db->exec("DELETE FROM `$table`");
            $db->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            echo "   - Tabel `$table` dikosongkan & auto-increment di-reset ke 1.\n";
        } catch (Exception $e) {
            echo "   - [Lewati] Tabel `$table`: " . $e->getMessage() . "\n";
        }
    }

    // 2. Hapus akun dummy
    echo "2. Menghapus akun pengguna uji coba (warga dummy, montir dummy, duplikat)...\n";
    $keep_old_user_ids = [1, 267, 268, 269, 270, 271, 272, 273, 274, 275, 276, 277];
    $in_clause = implode(',', $keep_old_user_ids);

    $db->exec("DELETE FROM profiles WHERE user_id NOT IN ($in_clause)");
    $db->exec("DELETE FROM service_providers WHERE user_id NOT IN ($in_clause)");
    $deleted_users = $db->exec("DELETE FROM users WHERE id NOT IN ($in_clause)");
    echo "   - $deleted_users akun uji coba berhasil dihapus.\n";

    // 3. Mapping Baru: 11 Penyedia Jasa Asli Inhu
    echo "3. Menata ulang ID Pengguna & Mitra menjadi urut #001 sampai #012...\n";
    $provider_mappings = [
        // old_user_id => [new_user_id, new_sp_id, old_sp_id]
        267 => [2, 1, 2],   // Mas Doni - Bengkel Berkah Motor
        268 => [3, 2, 3],   // Pak Hendra - Sejuk Jaya Tehnik
        269 => [4, 3, 4],   // Cak Kuswanto - Las Karya Mandiri
        270 => [5, 4, 5],   // Pak Joko - Sumur Bor Tirta
        271 => [6, 5, 6],   // Bang Ucok - Angkutan Sawit Pickup
        272 => [7, 6, 7],   // Pak Kumis - Tukang Bangunan
        273 => [8, 7, 8],   // Mas Rian - Rian Elektrik
        274 => [9, 8, 9],   // Bang Herman - Mandor Sawit Barokah
        275 => [10, 9, 10], // Fajar Ramadhan - Lensa Melayu
        276 => [11, 10, 11],// Nia Rahmawati - Griya Cantik MUA
        277 => [12, 11, 12] // Ners Anisa - Homecare Medis
    ];

    // Hapus service provider dummy yang lama (SP ID 1 yang dulunya milik montir dummy 265)
    $db->exec("DELETE FROM service_providers WHERE id = 1 AND user_id NOT IN ($in_clause)");
    $db->exec("DELETE FROM service_areas WHERE provider_id = 1");

    foreach ($provider_mappings as $old_user_id => $map) {
        $new_user_id = $map[0];
        $new_sp_id   = $map[1];
        $old_sp_id   = $map[2];

        // Update User ID
        $db->exec("UPDATE users SET id = $new_user_id WHERE id = $old_user_id");

        // Update Profile
        $db->exec("UPDATE profiles SET user_id = $new_user_id WHERE user_id = $old_user_id");

        // Update Service Provider (set id, user_id, reset stats & beri saldo deposit awal Rp 50.000)
        $db->exec("
            UPDATE service_providers 
            SET id = $new_sp_id, 
                user_id = $new_user_id, 
                completed_jobs = 0, 
                reviews_count = 0, 
                rating_avg = 5.00, 
                wallet_balance = 50000.00 
            WHERE id = $old_sp_id
        ");

        // Update Portofolios
        $db->exec("UPDATE provider_portfolios SET provider_id = $new_sp_id WHERE provider_id = $old_sp_id");

        // Update Service Areas
        $db->exec("UPDATE service_areas SET provider_id = $new_sp_id WHERE provider_id = $old_sp_id");

        echo "   - Mitra ID $new_sp_id (User ID #00$new_user_id) berhasil ditata ulang.\n";
    }

    // 4. Reset Auto Increment
    echo "4. Mengatur AUTO_INCREMENT agar pendaftar baru mulai dari #013...\n";
    $db->exec("ALTER TABLE users AUTO_INCREMENT = 13");
    $db->exec("ALTER TABLE service_providers AUTO_INCREMENT = 12");
    $db->exec("ALTER TABLE profiles AUTO_INCREMENT = 13");

    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n=== STERILISASI BERHASIL! ===\n";
    echo "Daftar Akun di Platform Saat Ini:\n";
    $stmtUsers = $db->query("
        SELECT u.id, u.name, u.email, u.phone, r.name as role_name, sp.business_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        LEFT JOIN service_providers sp ON u.id = sp.user_id 
        ORDER BY u.id ASC
    ");
    foreach ($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $formatted_id = sprintf('#%03d', $u['id']);
        $biz = $u['business_name'] ? " | Toko: {$u['business_name']}" : "";
        echo "{$formatted_id} | [{$u['role_name']}] {$u['name']} ({$u['email']}){$biz}\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

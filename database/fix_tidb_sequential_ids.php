<?php
/**
 * Script Perapihan ID Database TiDB Cloud (JASA INHU)
 * Mengaktifkan AUTO_ID_CACHE = 1 agar ID selalu berurutan (1, 2, 3...)
 * dan merapikan ID akun serta nomor order / invoice agar berurutan #001, #002, dst.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    echo "=== MEMULAI PERAPIHAN ID DATABASE JASA INHU ===\n";

    // 1. Terapkan AUTO_ID_CACHE = 1 ke seluruh tabel utama agar TiDB tidak melompat 30.000 lagi
    $tables = [
        'users',
        'profiles',
        'service_providers',
        'service_areas',
        'service_requests',
        'service_request_responses',
        'service_request_timeline',
        'reviews',
        'wallet_topup_requests',
        'provider_wallet_transactions',
        'chat_messages',
        'conversations',
        'notifications',
        'user_verifications',
        'provider_portfolios'
    ];

    echo "1. Mengatur AUTO_ID_CACHE = 1 pada tabel TiDB...\n";
    foreach ($tables as $t) {
        try {
            $db->exec("ALTER TABLE `$t` AUTO_ID_CACHE = 1");
            echo "   [OK] Tabel $t diatur berurutan (AUTO_ID_CACHE = 1)\n";
        } catch (Exception $e) {
            echo "   [Skip/Info] Tabel $t: " . $e->getMessage() . "\n";
        }
    }

    // 2. Pemetaan ID Baru yang Rapi
    // User Mapping:
    // 1 -> 1 (Angga Fahrandi - Admin)
    // 20014 -> 2 (Mufida sari - Mitra)
    // 28017 -> 3 (Febriani lestari - Pengguna)
    // 32013 -> 4 (Wirsas - Pengguna)
    $user_map = [
        20014 => 2,
        28017 => 3,
        32013 => 4
    ];

    // Provider Mapping:
    // 30012 -> 1
    $prov_map = [
        30012 => 1
    ];

    // Request Mapping:
    // 1 -> 1
    // 30001 -> 2
    // 60001 -> 3
    $req_map = [
        30001 => 2,
        60001 => 3
    ];

    echo "\n2. Menata ulang ID Pengguna (User IDs)...\n";
    foreach ($user_map as $old_uid => $new_uid) {
        $db->exec("UPDATE users SET id = $new_uid WHERE id = $old_uid");
        $db->exec("UPDATE profiles SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE service_providers SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE service_requests SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE reviews SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE notifications SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE user_verifications SET user_id = $new_uid WHERE user_id = $old_uid");
        echo "   [User] ID $old_uid -> $new_uid berhasil dirapikan.\n";
    }

    echo "\n3. Menata ulang ID Mitra (Service Provider IDs)...\n";
    foreach ($prov_map as $old_pid => $new_pid) {
        $db->exec("UPDATE service_providers SET id = $new_pid WHERE id = $old_pid");
        $db->exec("UPDATE service_areas SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE service_requests SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE service_request_responses SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE reviews SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE wallet_topup_requests SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE provider_wallet_transactions SET provider_id = $new_pid WHERE provider_id = $old_pid");
        echo "   [Provider] ID $old_pid -> $new_pid berhasil dirapikan.\n";
    }

    echo "\n4. Menata ulang ID Pesanan (Service Request IDs)...\n";
    foreach ($req_map as $old_rid => $new_rid) {
        $db->exec("UPDATE service_requests SET id = $new_rid WHERE id = $old_rid");
        $db->exec("UPDATE service_request_responses SET request_id = $new_rid WHERE request_id = $old_rid");
        $db->exec("UPDATE service_request_timeline SET request_id = $new_rid WHERE request_id = $old_rid");
        $db->exec("UPDATE reviews SET request_id = $new_rid WHERE request_id = $old_rid");
        $db->exec("UPDATE provider_wallet_transactions SET reference_id = $new_rid WHERE reference_id = $old_rid");
        echo "   [Request] ID $old_rid -> $new_rid berhasil dirapikan.\n";
    }

    // Rapikan Review ID
    $db->exec("UPDATE reviews SET id = 2 WHERE id = 30001");
    // Rapikan Topup ID
    $db->exec("UPDATE wallet_topup_requests SET id = 1 WHERE id > 1");

    echo "\n5. Mengatur ulang AUTO_INCREMENT ke nilai urut selanjutnya...\n";
    $db->exec("ALTER TABLE users AUTO_INCREMENT = 5");
    $db->exec("ALTER TABLE service_providers AUTO_INCREMENT = 2");
    $db->exec("ALTER TABLE service_requests AUTO_INCREMENT = 4");
    $db->exec("ALTER TABLE reviews AUTO_INCREMENT = 3");
    $db->exec("ALTER TABLE wallet_topup_requests AUTO_INCREMENT = 2");

    echo "\n=== SUKSES! SELURUH ID SEKARANG BERURUTAN & RAPI DARI #001 ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

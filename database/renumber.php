<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    echo "=== MEMULAI UPDATE ID DENGAN FOREIGN_KEY_CHECKS = 0 ===\n";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    $user_map = [
        20014 => 2,
        28017 => 3,
        32013 => 4
    ];

    $prov_map = [
        30012 => 1
    ];

    $req_map = [
        30001 => 2,
        60001 => 3
    ];

    echo "1. Update User IDs...\n";
    foreach ($user_map as $old_uid => $new_uid) {
        $db->exec("UPDATE users SET id = $new_uid WHERE id = $old_uid");
        $db->exec("UPDATE profiles SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE service_providers SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE service_requests SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE reviews SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE notifications SET user_id = $new_uid WHERE user_id = $old_uid");
        $db->exec("UPDATE user_verifications SET user_id = $new_uid WHERE user_id = $old_uid");
        echo "   [User] $old_uid -> $new_uid\n";
    }

    echo "2. Update Provider IDs...\n";
    foreach ($prov_map as $old_pid => $new_pid) {
        $db->exec("UPDATE service_providers SET id = $new_pid WHERE id = $old_pid");
        $db->exec("UPDATE service_areas SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE service_requests SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE service_request_responses SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE reviews SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE wallet_topup_requests SET provider_id = $new_pid WHERE provider_id = $old_pid");
        $db->exec("UPDATE provider_wallet_transactions SET provider_id = $new_pid WHERE provider_id = $old_pid");
        echo "   [Provider] $old_pid -> $new_pid\n";
    }

    echo "3. Update Request IDs...\n";
    foreach ($req_map as $old_rid => $new_rid) {
        $db->exec("UPDATE service_requests SET id = $new_rid WHERE id = $old_rid");
        $db->exec("UPDATE service_request_responses SET request_id = $new_rid WHERE request_id = $old_rid");
        $db->exec("UPDATE service_request_timeline SET request_id = $new_rid WHERE request_id = $old_rid");
        $db->exec("UPDATE reviews SET request_id = $new_rid WHERE request_id = $old_rid");
        $db->exec("UPDATE provider_wallet_transactions SET reference_id = $new_rid WHERE reference_id = $old_rid");
        echo "   [Request] $old_rid -> $new_rid\n";
    }

    $db->exec("UPDATE reviews SET id = 2 WHERE id = 30001");
    $db->exec("UPDATE wallet_topup_requests SET id = 1 WHERE id > 1");

    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "=== SUKSES DIUPDATE! ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    echo "Menjalankan migrasi perubahan tipe kolom gambar di database...\n";

    $queries = [
        "ALTER TABLE profiles MODIFY COLUMN avatar MEDIUMTEXT NULL",
        "ALTER TABLE service_providers MODIFY COLUMN image_url MEDIUMTEXT NULL",
        "ALTER TABLE service_providers MODIFY COLUMN id_card_image MEDIUMTEXT NULL",
        "ALTER TABLE service_providers MODIFY COLUMN certificate_url MEDIUMTEXT NULL",
        "ALTER TABLE provider_portfolios MODIFY COLUMN image_after MEDIUMTEXT NULL",
        "ALTER TABLE provider_portfolios MODIFY COLUMN image_before MEDIUMTEXT NULL",
        "ALTER TABLE wallet_topup_requests MODIFY COLUMN proof_image MEDIUMTEXT NULL",
    ];

    foreach ($queries as $q) {
        try {
            $db->exec($q);
            echo "[SUCCESS] $q\n";
        } catch (Exception $e) {
            echo "[WARN] $q : " . $e->getMessage() . "\n";
        }
    }

    echo "Selesai migrasi kolom database.\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

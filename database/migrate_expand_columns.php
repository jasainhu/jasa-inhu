<?php
// Load .env jika ada
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v, " \t\n\r\0\x0B\"'"));
        }
    }
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = get_db();
    echo "Terhubung ke database server: " . DB_HOST . ":" . DB_PORT . " (" . DB_NAME . ")\n";
    echo "Menjalankan migrasi perubahan tipe kolom gambar ke MEDIUMTEXT...\n";

    $queries = [
        "ALTER TABLE profiles MODIFY COLUMN avatar MEDIUMTEXT NULL",
        "ALTER TABLE service_providers MODIFY COLUMN image_url MEDIUMTEXT NULL",
        "ALTER TABLE service_providers MODIFY COLUMN id_card_image MEDIUMTEXT NULL",
        "ALTER TABLE service_providers MODIFY COLUMN certificate_url MEDIUMTEXT NULL",
        "ALTER TABLE provider_portfolios MODIFY COLUMN image_after MEDIUMTEXT NULL",
        "ALTER TABLE provider_portfolios MODIFY COLUMN image_before MEDIUMTEXT NULL",
        "ALTER TABLE wallet_topup_requests MODIFY COLUMN proof_image MEDIUMTEXT NULL",
        "ALTER TABLE banners MODIFY COLUMN image_url MEDIUMTEXT NULL",
    ];

    foreach ($queries as $q) {
        try {
            $db->exec($q);
            echo "[SUCCESS] $q\n";
        } catch (Exception $e) {
            echo "[WARN] $q : " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== Verifikasi Kolom TiDB Cloud ===\n";
    $cols = $db->query("DESCRIBE profiles")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if ($c['Field'] === 'avatar') {
            echo "profiles.avatar sekarang: " . $c['Type'] . "\n";
        }
    }

    $spCols = $db->query("DESCRIBE service_providers")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($spCols as $c) {
        if (in_array($c['Field'], ['id_card_image', 'image_url', 'certificate_url'])) {
            echo "service_providers." . $c['Field'] . " sekarang: " . $c['Type'] . "\n";
        }
    }

    echo "\nSelesai migrasi kolom database TiDB Cloud.\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

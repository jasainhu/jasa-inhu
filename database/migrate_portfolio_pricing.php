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
    echo "Memeriksa dan menambahkan kolom 'price' di tabel provider_portfolios...\n";

    $cols = $db->query("SHOW COLUMNS FROM provider_portfolios LIKE 'price'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE provider_portfolios ADD COLUMN price VARCHAR(100) NULL AFTER title");
        echo "[SUCCESS] Kolom 'price' berhasil ditambahkan ke provider_portfolios!\n";
    } else {
        echo "[INFO] Kolom 'price' sudah ada sebelumnya di provider_portfolios.\n";
    }

    echo "\n=== Verifikasi Struktur Kolom provider_portfolios ===\n";
    $columns = $db->query("DESCRIBE provider_portfolios")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $c) {
        echo "- {$c['Field']} ({$c['Type']}) " . ($c['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    echo "\nMigrasi selesai!\n";
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

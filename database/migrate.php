<?php
/**
 * Script Migrasi Database & Seeding Otomatis JASA INHU
 * Dapat dijalankan melalui CLI (php database/migrate.php) atau melalui browser.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$is_cli = (php_sapi_name() === 'cli');

function output_msg(string $message, string $type = 'info') {
    global $is_cli;
    if ($is_cli) {
        $prefix = match ($type) {
            'success' => '[SUCCESS] ',
            'error'   => '[ERROR] ',
            'warning' => '[WARNING] ',
            default   => '[INFO] '
        };
        echo $prefix . $message . PHP_EOL;
    } else {
        $class = match ($type) {
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            default   => 'alert-info'
        };
        echo "<div class='alert {$class}' style='margin: 10px 0; padding: 12px; border-radius: 6px; font-family: sans-serif;'>{$message}</div>";
    }
}

if (!$is_cli) {
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><title>Migrasi Database JASA INHU</title>";
    echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'></head><body class='p-4 bg-light'>";
    echo "<div class='container' style='max-width: 800px; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>";
    echo "<h2 class='mb-4 text-primary fw-bold'>Migrasi & Setup Database JASA INHU</h2>";
}

try {
    output_msg("Menghubungkan ke MySQL Server di " . DB_HOST . ":" . DB_PORT . "...");
    $rootPdo = get_db(false);

    output_msg("Membuat database '" . DB_NAME . "' jika belum ada...");
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8 COLLATE utf8_general_ci;");
    output_msg("Database '" . DB_NAME . "' siap digunakan.", "success");

    // Hubungkan kembali dengan memilih database
    $dbPdo = get_db(true);

    // 1. Eksekusi Schema
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("File schema.sql tidak ditemukan di: " . $schemaFile);
    }
    output_msg("Membaca dan menjalankan 'database/schema.sql'...");
    $schemaSql = file_get_contents($schemaFile);
    $dbPdo->exec($schemaSql);
    output_msg("Tabel database berhasil dibuat!", "success");

    // 2. Eksekusi Seeding
    $seedFile = __DIR__ . '/seed.sql';
    if (file_exists($seedFile)) {
        output_msg("Membaca dan menjalankan 'database/seed.sql'...");
        $seedSql = file_get_contents($seedFile);
        $dbPdo->exec($seedSql);
        output_msg("Data awal (Roles, 14 Kecamatan Inhu, 17 Kategori Jasa, Akun Demo) berhasil di-seed!", "success");
    }

    output_msg("=== SETUP DATABASE JASA INHU SELESAI DENGAN SUKSES ===", "success");

    if (!$is_cli) {
        echo "<hr class='my-4'>";
        echo "<h5>Akun Demo Siap Pakai (Password: <code>password123</code>):</h5>";
        echo "<ul>";
        echo "<li><strong>Admin:</strong> <code>admin@jasainhu.id</code></li>";
        echo "<li><strong>Pengguna:</strong> <code>budi@gmail.com</code></li>";
        echo "<li><strong>Penyedia Jasa 1:</strong> <code>ahmad.servis@jasainhu.id</code> (Bengkel Motor Rengat)</li>";
        echo "<li><strong>Penyedia Jasa 2:</strong> <code>hendra.ac@jasainhu.id</code> (Teknisi AC Belilas)</li>";
        echo "</ul>";
        echo "<a href='../index.php' class='btn btn-primary mt-3'>Buka Beranda JASA INHU</a>";
    }

} catch (Throwable $e) {
    output_msg("Terjadi kesalahan: " . $e->getMessage(), "error");
}

if (!$is_cli) {
    echo "</div></body></html>";
}

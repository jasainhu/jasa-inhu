<?php
/**
 * Script Pemulihan Database Jasa Inhu ke Cluster TiDB Cloud Baru
 */
ini_set('memory_limit', '1024M');
set_time_limit(600);

$host = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = 'dKU6oFZH1thEDwU.root';
$pass = '3v0aGp8h2H254cbh';
$dbname = 'jasa_inhu';

$backupFile = __DIR__ . '/../backups/backup_jasa_inhu_2026-10-19_000947.sql';

if (!file_exists($backupFile)) {
    die("File backup tidak ditemukan: {$backupFile}\n");
}

echo "========================================================\n";
echo "   PEMULIHAN DATABASE JASA INHU KE CLUSTER BARU        \n";
echo "========================================================\n";
echo "Host     : {$host}:{$port}\n";
echo "User     : {$user}\n";
echo "Database : {$dbname}\n";
echo "File     : " . basename($backupFile) . " (" . round(filesize($backupFile) / (1024*1024), 2) . " MB)\n\n";

try {
    echo "[1/4] Menghubungkan ke TiDB Cloud Serverless...\n";
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "      Berhasil terhubung!\n\n";

    echo "[2/4] Menyiapkan database `{$dbname}`...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    $pdo->exec("SET NAMES utf8mb4");
    echo "      Database `{$dbname}` siap.\n\n";

    echo "[3/4] Mengimpor struktur tabel dan data (termasuk 48 mitra & foto)...\n";
    $handle = fopen($backupFile, 'r');
    if (!$handle) throw new Exception("Gagal membaca file SQL.");

    $query = '';
    $inString = false;
    $stringChar = '';
    $escaped = false;
    $count = 0;
    $startTime = microtime(true);

    while (($line = fgets($handle, 2097152)) !== false) {
        $len = strlen($line);
        $trimmed = trim($line);

        // Abaikan komentar baris tunggal jika belum ada query berjalan
        if ($query === '' && (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#') || (str_starts_with($trimmed, '/*') && !str_starts_with($trimmed, '/*T!')))) {
            continue;
        }

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $stringChar) {
                    if ($i + 1 < $len && $line[$i + 1] === $stringChar) {
                        $query .= $char;
                        $i++;
                    } else {
                        $inString = false;
                    }
                }
            } else {
                if ($char === "'" || $char === '"') {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === ';') {
                    $query .= ';';
                    $stmt = trim($query);
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                        $count++;
                        if ($count % 50 === 0) {
                            echo "      > Dieksekusi {$count} pernyataan SQL...\n";
                            flush();
                        }
                    }
                    $query = '';
                    continue;
                }
            }
            $query .= $char;
        }
    }

    $remainder = trim($query);
    if (!empty($remainder)) {
        $pdo->exec($remainder);
        $count++;
    }
    fclose($handle);

    $duration = round(microtime(true) - $startTime, 2);
    echo "      Berhasil mengimpor {$count} pernyataan dalam {$duration} detik!\n\n";

    echo "[4/4] Memverifikasi data yang telah dipulihkan...\n";
    $spCount = $pdo->query("SELECT count(*) FROM service_providers")->fetchColumn();
    $uCount = $pdo->query("SELECT count(*) FROM users")->fetchColumn();
    $catCount = $pdo->query("SELECT count(*) FROM service_categories")->fetchColumn();
    $revCount = $pdo->query("SELECT count(*) FROM reviews")->fetchColumn();
    $portCount = $pdo->query("SELECT count(*) FROM provider_portfolios")->fetchColumn();

    echo "      - Mitra Penyedia Jasa : {$spCount} mitra\n";
    echo "      - Total Pengguna      : {$uCount} pengguna\n";
    echo "      - Kategori Jasa       : {$catCount} kategori\n";
    echo "      - Portofolio Foto     : {$portCount} portofolio\n";
    echo "      - Ulasan & Rating     : {$revCount} ulasan\n\n";

    echo "========================================================\n";
    echo "   SELURUH DATA BERHASIL DIPULIHKAN DENGAN SEMPURNA!   \n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}


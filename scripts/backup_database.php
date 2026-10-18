<?php
/**
 * CLI Script: Pencadangan Database Otomatis Mingguan JASA INHU
 * 
 * Penggunaan:
 *   php scripts/backup_database.php           (Simpan cadangan ke folder backups/)
 *   php scripts/backup_database.php --email   (Simpan cadangan dan kirim notifikasi ke email admin)
 */

if (php_sapi_name() !== 'cli') {
    die("Script ini hanya dapat dijalankan melalui CLI / Terminal.\n");
}

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

echo "========================================================\n";
echo "   JASA INHU - OTOMATISASI PENCADANGAN DATABASE (BACKUP) \n";
echo "========================================================\n";
echo "Waktu mulai : " . date('Y-m-d H:i:s') . " WIB\n";

$options = getopt('', ['email']);
$send_email = isset($options['email']);

// 1. Siapkan folder backups/
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// 2. Ekspor seluruh database
echo "Mengambil struktur dan seluruh isi data TiDB Cloud...\n";
$start = microtime(true);
$sql = generate_database_sql_dump();
$duration = round(microtime(true) - $start, 2);

$filename = 'backup_jasa_inhu_' . date('Y-m-d_His') . '.sql';
$filePath = $backupDir . '/' . $filename;

file_put_contents($filePath, $sql);
$sizeKb = round(strlen($sql) / 1024, 2);

echo "✅ File cadangan berhasil dibuat!\n";
echo "   - Nama File : $filename\n";
echo "   - Ukuran    : $sizeKb KB\n";
echo "   - Durasi    : $duration detik\n";
echo "   - Lokasi    : $filePath\n";

// 3. Bersihkan cadangan lama (> 30 hari)
echo "\nMemeriksa pembersihan arsip cadangan lama (> 30 hari)...\n";
$files = scandir($backupDir);
$now = time();
$deleted = 0;
foreach ($files as $f) {
    if ($f !== '.' && $f !== '..' && str_ends_with($f, '.sql')) {
        $p = $backupDir . '/' . $f;
        if (($now - filemtime($p)) > (30 * 86400)) {
            unlink($p);
            $deleted++;
        }
    }
}
echo "   - File lama dihapus: $deleted file\n";

// 4. Kirim email jika parameter --email aktif
if ($send_email) {
    $admin_email = get_setting('admin_email', 'jasainhu@gmail.com');
    echo "\nMengirimkan arsip ke email admin ($admin_email)...\n";
    $subject = "📁 [CADANGAN DATABASE MINGGUAN] JASA INHU - " . date('d M Y');
    $body = "<h2>Pencadangan Database Otomatis Mingguan</h2>"
          . "<p>Pencadangan database JASA INHU telah berhasil dieksekusi secara otomatis:</p>"
          . "<ul>"
          . "<li><strong>Waktu:</strong> " . date('d M Y, H:i:s') . " WIB</li>"
          . "<li><strong>Nama File:</strong> <code>$filename</code></li>"
          . "<li><strong>Ukuran:</strong> $sizeKb KB</li>"
          . "<li><strong>Server:</strong> TiDB Cloud Serverless (jasa_inhu)</li>"
          . "</ul>";
    send_mail_with_attachment($admin_email, $subject, $body, $sql, $filename);
    echo "✅ Email berhasil dikirimkan!\n";
}

echo "\n========================================================\n";
echo "   PROSES PENCADANGAN DATABASE SELESAI DENGAN SUKSES!   \n";
echo "========================================================\n";

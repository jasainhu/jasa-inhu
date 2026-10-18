<?php
/**
 * Admin: Pencadangan & Pemulihan Database JASA INHU
 */

$page_title = 'Pencadangan Database';
$admin_active = 'backup';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/backup_helper.php';
require_once __DIR__ . '/../includes/mailer.php';

require_role('admin');

$db = get_db();
$admin_email = get_setting('admin_email', 'jasainhu@gmail.com');

// 1. Aksi Unduh Langsung via Browser
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    if (isset($_GET['csrf']) && validate_csrf($_GET['csrf'])) {
        download_database_backup();
        exit;
    } else {
        set_flash('error', 'Token keamanan tidak valid. Silakan ulangi.');
        redirect('/admin/backup.php');
    }
}

// 2. Aksi Simpan ke Folder Server Lokal (backups/)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_local_backup') {
    if (validate_csrf()) {
        try {
            $backupDir = __DIR__ . '/../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $sql = generate_database_sql_dump();
            $filename = 'backup_jasa_inhu_' . date('Y-m-d_His') . '.sql';
            $filePath = $backupDir . '/' . $filename;

            file_put_contents($filePath, $sql);
            set_flash('success', "Cadangan database berhasil disimpan: <strong>$filename</strong> (" . round(strlen($sql) / 1024, 2) . " KB)");
        } catch (Exception $e) {
            set_flash('error', 'Gagal membuat file cadangan: ' . $e->getMessage());
        }
        redirect('/admin/backup.php');
    }
}

// 3. Aksi Kirim File Backup ke Email Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'email_backup') {
    if (validate_csrf()) {
        try {
            $sql = generate_database_sql_dump();
            $filename = 'backup_jasa_inhu_' . date('Y-m-d_His') . '.sql';

            $subject = "📁 [CADANGAN DATABASE] JASA INHU - " . date('d M Y');
            $body = "<h2>Cadangan Database Resmi JASA INHU</h2>"
                  . "<p>Terlampir file cadangan database resmi JASA INHU yang diekspor pada:</p>"
                  . "<ul>"
                  . "<li><strong>Waktu:</strong> " . date('d M Y, H:i:s') . " WIB</li>"
                  . "<li><strong>Ukuran:</strong> " . round(strlen($sql) / 1024, 2) . " KB</li>"
                  . "<li><strong>Database:</strong> TiDB Cloud Serverless (jasa_inhu)</li>"
                  . "</ul>"
                  . "<p>Simpan file lampiran <code>$filename</code> ini sebagai arsip aman data platform Anda.</p>"
                  . "<hr><p style='font-size: 11px; color: #666;'>Dikirim otomatis dari Sistem Manajemen JASA INHU</p>";

            // Mengirim email dengan attachment
            $sent = send_mail_with_attachment($admin_email, $subject, $body, $sql, $filename);

            if ($sent) {
                set_flash('success', "File cadangan database berhasil dikirimkan ke email <strong>$admin_email</strong>!");
            } else {
                set_flash('warning', "Pencadangan selesai, namun pengiriman email mengalami kendala. Pastikan konfigurasi SMTP Gmail aktif di menu Pengaturan.");
            }
        } catch (Exception $e) {
            set_flash('error', 'Terjadi kesalahan saat memproses email: ' . $e->getMessage());
        }
        redirect('/admin/backup.php');
    }
}

// 4. Aksi Hapus File Backup Tertentu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_backup') {
    if (validate_csrf()) {
        $fileToDelete = basename(trim($_POST['filename'] ?? ''));
        $target = __DIR__ . '/../backups/' . $fileToDelete;
        if (file_exists($target) && str_ends_with($fileToDelete, '.sql')) {
            unlink($target);
            set_flash('success', "File cadangan <strong>$fileToDelete</strong> berhasil dihapus.");
        } else {
            set_flash('error', 'File cadangan tidak ditemukan.');
        }
        redirect('/admin/backup.php');
    }
}

// Ambil Statistik Database
$total_tables = 0;
$total_size_mb = 0;
try {
    $stmtSize = $db->query("
        SELECT 
            COUNT(table_name) AS total_tables,
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 3) AS total_mb
        FROM information_schema.tables 
        WHERE table_schema = 'jasa_inhu'
    ");
    $sizeData = $stmtSize->fetch(PDO::FETCH_ASSOC);
    $total_tables = (int)($sizeData['total_tables'] ?? 0);
    $total_size_mb = (float)($sizeData['total_mb'] ?? 0);
} catch (Exception $e) {}

// Baca daftar file cadangan di folder backups/
$backupDir = __DIR__ . '/../backups';
$existing_backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..' && str_ends_with($f, '.sql')) {
            $filePath = $backupDir . '/' . $f;
            $existing_backups[] = [
                'name' => $f,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath)
            ];
        }
    }
    // Urutkan dari yang paling baru
    usort($existing_backups, fn($a, $b) => $b['modified'] <=> $a['modified']);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">
            <i class="fa-solid fa-database text-teal me-2"></i>Pencadangan Database Platform
        </h1>
        <p class="text-muted small mb-0">Amankan data seluruh mitra, pelanggan, pesanan, dan transaksi JASA INHU dalam format SQL</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/backup.php?action=download&csrf=<?= csrf_token() ?>" class="btn btn-teal btn-sm fw-bold shadow-xs">
            <i class="fa-solid fa-download me-1"></i> Unduh SQL Sekarang
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Status Database -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="p-3.5 rounded-4 border bg-white shadow-2xs h-100" style="border-left: 5px solid #0284c7 !important;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Kapasitas Database Terpakai</span>
                <span class="badge bg-info-subtle text-info-emphasis rounded-pill">TiDB Cloud</span>
            </div>
            <div class="h3 fw-bold text-dark mb-1"><?= $total_size_mb ?> MB</div>
            <div class="small text-muted">Dari kuota gratis <strong>25.000 MB (25 GB)</strong></div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="p-3.5 rounded-4 border bg-white shadow-2xs h-100" style="border-left: 5px solid #10b981 !important;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">Total Tabel & Struktur</span>
                <span class="badge bg-success-subtle text-success rounded-pill">Lengkap</span>
            </div>
            <div class="h3 fw-bold text-dark mb-1"><?= $total_tables ?> Tabel</div>
            <div class="small text-muted">Termasuk mitra, order, ulasan & dompet</div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="p-3.5 rounded-4 border bg-white shadow-2xs h-100" style="border-left: 5px solid #f59e0b !important;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-semibold text-muted">File Cadangan Tersimpan</span>
                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">Folder Lokal</span>
            </div>
            <div class="h3 fw-bold text-dark mb-1"><?= count($existing_backups) ?> File</div>
            <div class="small text-muted">Arsip di server lokal <code>/backups/</code></div>
        </div>
    </div>
</div>

<!-- Pusat Eksekusi Cadangan -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3.5 px-4 border-bottom">
        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="fa-solid fa-cloud-arrow-down text-teal"></i>
            <span>Pilihan Metode Pencadangan (Backup)</span>
        </h5>
    </div>
    <div class="card-body p-4">
        <div class="row g-4">
            <!-- Metode 1: Unduh Langsung -->
            <div class="col-lg-4 col-md-6">
                <div class="card border rounded-3 p-3 h-100 bg-light bg-opacity-50 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-teal text-white mb-3 shadow-xs" style="width: 50px; height: 50px;">
                            <i class="fa-solid fa-file-arrow-down fs-4"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Unduh Langsung (.sql)</h6>
                        <p class="text-muted small mb-3">
                            Unduh seluruh database saat ini ke memori laptop/HP Anda sebagai file berekstensi <code>.sql</code> dalam sekali klik.
                        </p>
                    </div>
                    <a href="<?= BASE_URL ?>/admin/backup.php?action=download&csrf=<?= csrf_token() ?>" class="btn btn-teal fw-bold w-100 shadow-xs">
                        <i class="fa-solid fa-download me-1"></i> Unduh File SQL Sekarang
                    </a>
                </div>
            </div>

            <!-- Metode 2: Simpan di Folder Server -->
            <div class="col-lg-4 col-md-6">
                <div class="card border rounded-3 p-3 h-100 bg-light bg-opacity-50 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white mb-3 shadow-xs" style="width: 50px; height: 50px;">
                            <i class="fa-solid fa-folder-plus fs-4"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Simpan di Folder Server</h6>
                        <p class="text-muted small mb-3">
                            Buat salinan arsip dan simpan ke dalam folder internal <code>backups/</code> pada sistem web.
                        </p>
                    </div>
                    <form action="<?= BASE_URL ?>/admin/backup.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_local_backup">
                        <button type="submit" class="btn btn-primary-custom fw-bold w-100 shadow-xs">
                            <i class="fa-solid fa-hard-drive me-1"></i> Buat Arsip di Server
                        </button>
                    </form>
                </div>
            </div>

            <!-- Metode 3: Kirim ke Email Admin -->
            <div class="col-lg-4 col-md-12">
                <div class="card border rounded-3 p-3 h-100 bg-light bg-opacity-50 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning text-dark mb-3 shadow-xs" style="width: 50px; height: 50px;">
                            <i class="fa-solid fa-envelope-circle-check fs-4"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Kirim ke Email Admin</h6>
                        <p class="text-muted small mb-3">
                            Kirimkan file cadangan <code>.sql</code> sebagai lampiran aman ke email resmi <strong><?= e($admin_email) ?></strong>.
                        </p>
                    </div>
                    <form action="<?= BASE_URL ?>/admin/backup.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="email_backup">
                        <button type="submit" class="btn btn-warning text-dark fw-bold w-100 shadow-xs">
                            <i class="fa-solid fa-paper-plane me-1"></i> Kirim ke <?= e($admin_email) ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Daftar File Cadangan yang Tersimpan di Server -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3.5 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-muted"></i>
                <span>Arsip File Cadangan di Folder Server (backups/)</span>
            </h5>
            <small class="text-muted">Daftar file cadangan SQL yang telah dibuat sebelumnya</small>
        </div>
        <span class="badge bg-light text-dark border"><?= count($existing_backups) ?> file ditemukan</span>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($existing_backups)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th class="ps-4">Nama File</th>
                            <th>Ukuran</th>
                            <th>Waktu Pembuatan</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($existing_backups as $b): ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-dark">
                                    <i class="fa-solid fa-file-code text-teal me-2"></i>
                                    <?= e($b['name']) ?>
                                </td>
                                <td><?= round($b['size'] / 1024, 2) ?> KB</td>
                                <td class="text-muted"><?= date('d M Y, H:i:s', $b['modified']) ?> WIB</td>
                                <td class="text-end pe-4">
                                    <form action="<?= BASE_URL ?>/admin/backup.php" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus arsip file backup ini?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_backup">
                                        <input type="hidden" name="filename" value="<?= e($b['name']) ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-xs" title="Hapus File">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-4 text-center text-muted">
                <i class="fa-solid fa-box-open fs-2 text-secondary mb-2 d-block"></i>
                Belum ada file cadangan yang disimpan di folder server lokal. Klik tombol <strong>"Buat Arsip di Server"</strong> di atas.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Petunjuk Pemulihan (Restore) Database -->
<div class="card border-0 shadow-sm rounded-4 bg-light">
    <div class="card-body p-4">
        <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-question text-teal"></i>
            <span>Panduan Memulihkan (Restore) Data</span>
        </h6>
        <div class="small text-muted lh-base">
            <p class="mb-2">Jika Anda berpindah server atau ingin memulihkan data dari file <code>.sql</code> cadangan:</p>
            <ol class="ps-3 mb-0">
                <li class="mb-1"><strong>Lewat TiDB Cloud Console:</strong> Buka <a href="https://tidbcloud.com" target="_blank" class="fw-semibold">tidbcloud.com</a> &rarr; Pilih Cluster <code>jasa_inhu</code> &rarr; Masuk ke menu <strong>SQL Editor / Import</strong> &rarr; Jalankan isi file <code>.sql</code>.</li>
                <li class="mb-1"><strong>Lewat Terminal / Command Prompt:</strong> Jalankan perintah: <code>mysql -h gateway01.ap-southeast-1.prod.aws.tidbcloud.com -P 4000 -u [USER] -p jasa_inhu &lt; nama_file_backup.sql</code></li>
                <li><strong>Lewat DBeaver / Navicat / TablePlus:</strong> Buat koneksi ke database, lalu klik kanan &rarr; <em>Execute SQL Script</em> &rarr; Pilih file <code>.sql</code>.</li>
            </ol>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Laporan Resmi & Ekspor PDF / Excel Data Mitra Penyedia Jasa JASA INHU
 * Compatible with Print to PDF (A4 Landscape) & Excel CSV Export
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();
$format = trim($_GET['format'] ?? 'pdf');

// Ambil seluruh data mitra
$stmt = $db->query("
    SELECT sp.id as provider_id, sp.business_name, sp.headline, sp.description,
           sp.hourly_rate_min, sp.hourly_rate_max, sp.experience_years,
           sp.is_verified, sp.id_card_type, sp.school_name, sp.credential_title,
           sp.rating_avg, sp.reviews_count, sp.completed_jobs, sp.created_at,
           u.id as user_id, u.name as owner_name, u.email, u.phone, u.is_active,
           sc.name as category_name,
           d.name as district_name, v.name as village_name,
           (SELECT COUNT(*) FROM provider_portfolios pp WHERE pp.provider_id = sp.id) as portfolio_count
    FROM service_providers sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    LEFT JOIN villages v ON sp.village_id = v.id
    WHERE u.email_verified_at IS NOT NULL
    ORDER BY sp.is_verified DESC, u.is_active DESC, sp.id ASC
");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Export Excel CSV
if ($format === 'excel' || $format === 'csv') {
    $filename = "DATA_MITRA_JASA_INHU_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    // BOM for UTF-8 Excel compatibility
    fputs($output, "\xEF\xBB\xBF");

    // Header Kolom
    fputcsv($output, [
        'No',
        'ID Mitra',
        'Nama Usaha / Toko',
        'Nama Pemilik',
        'Kategori Jasa',
        'Nomor WhatsApp',
        'Email',
        'Kecamatan',
        'Desa / Kelurahan',
        'Status Akun',
        'Status Verifikasi KTP',
        'Tipe Identitas',
        'Gelar / Sertifikat',
        'Pengalaman (Tahun)',
        'Tarif Mulai Dari (Rp)',
        'Tarif Hingga (Rp)',
        'Jumlah Portofolio',
        'Rating Rata-rata',
        'Pesanan Selesai',
        'Tanggal Terdaftar'
    ]);

    $no = 1;
    foreach ($providers as $p) {
        $statusAkun = ($p['is_active'] == 1) ? 'Aktif' : 'Diblokir / Nonaktif';
        $statusVerif = ($p['is_verified'] == 1) ? 'Terverifikasi' : 'Belum Verifikasi';
        
        fputcsv($output, [
            $no++,
            $p['provider_id'],
            $p['business_name'],
            $p['owner_name'],
            $p['category_name'] ?: 'Lainnya',
            $p['phone'],
            $p['email'],
            $p['district_name'] ?: 'Inhu',
            $p['village_name'] ?: '-',
            $statusAkun,
            $statusVerif,
            strtoupper($p['id_card_type'] ?: 'KTP'),
            $p['credential_title'] ?: '-',
            $p['experience_years'] ?: '0',
            $p['hourly_rate_min'],
            $p['hourly_rate_max'],
            $p['portfolio_count'],
            $p['rating_avg'],
            $p['completed_jobs'],
            $p['created_at']
        ]);
    }

    fclose($output);
    exit;
}

// Hitung statistik ringkas untuk header dokumen
$totalMitra = count($providers);
$totalAktif = count(array_filter($providers, fn($p) => $p['is_active'] == 1));
$totalVerif = count(array_filter($providers, fn($p) => $p['is_verified'] == 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Data Mitra - JASA INHU (<?= date('d/m/Y') ?>)</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11.5px;
            color: #1e293b;
            background: #f8fafc;
        }
        .page-sheet {
            background: #ffffff;
            max-width: 1200px;
            margin: 20px auto;
            padding: 30px 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-radius: 8px;
        }
        .kop-surat {
            border-bottom: 3px double #0d9488;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
        }
        .table-data th {
            background-color: #0d9488 !important;
            color: #ffffff !important;
            font-weight: 600;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #0f766e;
            padding: 8px 6px;
            font-size: 10.5px;
        }
        .table-data td {
            border: 1px solid #cbd5e1;
            padding: 7px 6px;
            vertical-align: middle;
            font-size: 10.5px;
        }
        .table-data tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge-verif {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: 700;
            border-radius: 4px;
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .badge-unverif {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: 600;
            border-radius: 4px;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .badge-blocked {
            display: inline-block;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: 700;
            border-radius: 4px;
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* Action Toolbar (Hidden during print) */
        .action-toolbar {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 1000;
            display: flex;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            padding: 10px 15px;
            background: white;
            border-radius: 50px;
            border: 1px solid #e2e8f0;
        }

        @media print {
            body {
                background: white !important;
                font-size: 10px;
            }
            .page-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 10px !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }
            .action-toolbar {
                display: none !important;
            }
            @page {
                size: A4 landscape;
                margin: 12mm 10mm;
            }
            .table-data th {
                background-color: #0d9488 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- Floating Action Bar -->
<div class="action-toolbar">
    <button type="button" class="btn btn-danger fw-bold rounded-pill px-3 shadow-xs d-flex align-items-center gap-1.5" onclick="window.print()">
        <i class="fa-solid fa-file-pdf"></i>
        <span>Download / Cetak PDF</span>
    </button>
    <a href="?format=excel" class="btn btn-success fw-bold rounded-pill px-3 shadow-xs d-flex align-items-center gap-1.5">
        <i class="fa-solid fa-file-excel"></i>
        <span>Export Excel (CSV)</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/users.php?role=penyedia" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="page-sheet">
    <!-- Kop Surat Resmi -->
    <div class="kop-surat">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo Jasa Inhu" style="height: 52px; width: auto;" onerror="this.style.display='none'">
                <div>
                    <h4 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.3px;">JASA INHU (PORTAL LAYANAN JASA TERPADU)</h4>
                    <div class="text-secondary small fw-semibold">Pusat Layanan Kebutuhan Harian & Tenaga Kerja Terampil Mandiri</div>
                    <div class="text-muted" style="font-size: 10px;">Kabupaten Indragiri Hulu, Riau &bull; Portal Resmi: https://jasainhu.gafahrandi.my.id</div>
                </div>
            </div>
            <div class="text-end">
                <div class="badge bg-teal text-white px-2.5 py-1 mb-1 fw-bold" style="background-color: #0d9488 !important;">DOKUMEN RESMI</div>
                <div class="text-muted" style="font-size: 10px;">Tanggal Cetak: <strong><?= date('d F Y, H:i') ?> WIB</strong></div>
                <div class="text-muted" style="font-size: 10px;">Admin Pengesah: <strong><?= e($_SESSION['user_name'] ?? 'Administrator') ?></strong></div>
            </div>
        </div>
    </div>

    <!-- Judul Dokumen & Ringkasan -->
    <div class="text-center mb-3">
        <h5 class="fw-bold text-dark mb-1 text-uppercase" style="letter-spacing: 0.5px;">BUKU DAFTAR MITRA PENYEDIA JASA KAB. INDRAGIRI HULU</h5>
        <div class="text-muted small">Status Operasional & Basis Data Rekanan Mitra Jasa Inhu</div>
    </div>

    <!-- Mini Ringkasan Statistik -->
    <div class="row g-2 mb-3 text-center">
        <div class="col-3">
            <div class="p-2 border rounded bg-light">
                <div class="text-muted" style="font-size: 9.5px;">TOTAL TERDAFTAR</div>
                <div class="fw-bold text-dark fs-6"><?= $totalMitra ?> Mitra</div>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border rounded bg-light">
                <div class="text-muted" style="font-size: 9.5px;">STATUS AKTIF</div>
                <div class="fw-bold text-success fs-6"><?= $totalAktif ?> Mitra</div>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border rounded bg-light">
                <div class="text-muted" style="font-size: 9.5px;">TERVERIFIKASI RESMI</div>
                <div class="fw-bold text-teal fs-6" style="color: #0d9488;"><?= $totalVerif ?> Mitra</div>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border rounded bg-light">
                <div class="text-muted" style="font-size: 9.5px;">CAKUPAN WILAYAH</div>
                <div class="fw-bold text-primary fs-6">14 Kecamatan se-Inhu</div>
            </div>
        </div>
    </div>

    <!-- Tabel Data Mitra -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th style="width: 160px;">Nama Usaha / Toko</th>
                <th style="width: 120px;">Pemilik</th>
                <th style="width: 100px;">WhatsApp</th>
                <th style="width: 110px;">Kategori Jasa</th>
                <th style="width: 120px;">Wilayah Operasional</th>
                <th style="width: 90px;">Verifikasi</th>
                <th style="width: 90px;">Estimasi Tarif</th>
                <th style="width: 65px;">Portofolio</th>
                <th style="width: 55px;">Rating</th>
                <th style="width: 70px;">Tgl Daftar</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($providers as $p): ?>
                <tr>
                    <td class="text-center fw-bold"><?= $no++ ?></td>
                    <td>
                        <strong class="text-dark"><?= e($p['business_name']) ?></strong>
                        <?php if ($p['is_active'] == 0): ?>
                            <br><span class="badge-blocked">DIBLOKIR</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($p['owner_name']) ?></td>
                    <td class="font-monospace fw-semibold text-dark"><?= e($p['phone']) ?></td>
                    <td>
                        <span class="badge bg-light text-dark border"><?= e($p['category_name'] ?: 'Umum') ?></span>
                    </td>
                    <td>
                        Kec. <?= e($p['district_name'] ?: 'Inhu') ?>
                        <?= !empty($p['village_name']) ? '<br><small class="text-muted">(' . e($p['village_name']) . ')</small>' : '' ?>
                    </td>
                    <td class="text-center">
                        <?php if ($p['is_verified'] == 1): ?>
                            <span class="badge-verif">
                                <?= (($p['id_card_type'] ?? '') === 'pelajar') ? '✓ Pelajar' : '✓ KTP Resmi' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge-unverif">Belum Verif</span>
                        <?php endif; ?>
                        <?php if (!empty($p['credential_title'])): ?>
                            <br><small class="text-muted" style="font-size: 8.5px;"><?= e($p['credential_title']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (!empty($p['hourly_rate_min']) && $p['hourly_rate_min'] > 0): ?>
                            Rp <?= number_format($p['hourly_rate_min'], 0, ',', '.') ?>
                        <?php else: ?>
                            <span class="text-muted fst-italic">Fleksibel</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center fw-semibold"><?= (int)$p['portfolio_count'] ?> Foto</td>
                    <td class="text-center">
                        <i class="fa-solid fa-star text-warning" style="font-size: 9px;"></i> <?= number_format($p['rating_avg'], 1) ?>
                    </td>
                    <td class="text-center text-muted" style="font-size: 9.5px;">
                        <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Tanda Tangan & Pengesahan Dokumen -->
    <div class="row mt-4 pt-3">
        <div class="col-8">
            <div class="text-muted" style="font-size: 9.5px; line-height: 1.5;">
                <strong>Catatan Legalitas & Keabsahan:</strong><br>
                1. Dokumen ini dicetak otomatis secara langsung dari server basis data aktif JASA INHU.<br>
                2. Data mitra di atas merupakan data pelaku jasa perorangan dan UMKM yang telah terdaftar resmi.<br>
                3. Digunakan sebagai arsip pembinaan, pengawasan kualitas layanan, dan rekapitulasi data daerah.
            </div>
        </div>
        <div class="col-4 text-center">
            <div class="text-muted" style="font-size: 10px;">Indragiri Hulu, <?= date('d F Y') ?></div>
            <div class="fw-bold text-dark mt-1" style="font-size: 10.5px;">PENGELOLA JASA INHU</div>
            <div style="height: 50px;"></div>
            <div class="fw-bold text-dark text-decoration-underline"><?= e($_SESSION['user_name'] ?? 'Angga Fahrandi') ?></div>
            <div class="text-muted" style="font-size: 9.5px;">Founder & Administrator Utama</div>
        </div>
    </div>
</div>

<script>
// Auto print if ?print=1 in URL
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', () => {
        window.print();
    });
}
</script>

</body>
</html>

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

// Filter Parameter
$filter_district = (int)($_GET['district_id'] ?? 0);
$filter_category = (int)($_GET['category_id'] ?? 0);
$filter_verif = isset($_GET['verified']) && $_GET['verified'] !== '' ? (int)$_GET['verified'] : -1;

$where_clauses = ["u.email_verified_at IS NOT NULL"];
$params = [];

if ($filter_district > 0) {
    $where_clauses[] = "sp.district_id = ?";
    $params[] = $filter_district;
}
if ($filter_category > 0) {
    $where_clauses[] = "sp.primary_category_id = ?";
    $params[] = $filter_category;
}
if ($filter_verif === 1) {
    $where_clauses[] = "sp.is_verified = 1";
} elseif ($filter_verif === 0) {
    $where_clauses[] = "(sp.is_verified = 0 OR sp.is_verified IS NULL)";
}

$where_sql = implode(' AND ', $where_clauses);

// Ambil seluruh data mitra sesuai filter
$stmt = $db->prepare("
    SELECT sp.id as provider_id, sp.business_name, sp.headline, sp.description,
           sp.hourly_rate_min, sp.hourly_rate_max, sp.experience_years,
           sp.is_verified, sp.id_card_type, sp.school_name, sp.credential_title,
           sp.serves_home_visit, sp.has_physical_store, sp.address,
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
    WHERE {$where_sql}
    ORDER BY sp.is_verified DESC, u.is_active DESC, sp.id ASC
");
$stmt->execute($params);
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil master data untuk filter
$districts = $db->query("SELECT id, name FROM districts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $db->query("SELECT id, name FROM service_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

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
        'Tipe Layanan',
        'Nomor WhatsApp',
        'Email',
        'Kecamatan',
        'Desa / Kelurahan',
        'Alamat Lengkap',
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
        
        $tipeLayanan = 'Panggilan ke Rumah';
        if (!empty($p['serves_home_visit']) && !empty($p['has_physical_store'])) {
            $tipeLayanan = 'Panggilan & Toko Fisik';
        } elseif (!empty($p['has_physical_store'])) {
            $tipeLayanan = 'Toko Fisik';
        }

        fputcsv($output, [
            $no++,
            $p['provider_id'],
            $p['business_name'],
            $p['owner_name'],
            $p['category_name'] ?: 'Lainnya',
            $tipeLayanan,
            $p['phone'],
            $p['email'],
            $p['district_name'] ?: 'Inhu',
            $p['village_name'] ?: '-',
            $p['address'] ?: '-',
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
$totalToko = count(array_filter($providers, fn($p) => !empty($p['has_physical_store'])));
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
            background: #f1f5f9;
        }
        .page-sheet {
            background: #ffffff;
            max-width: 1240px;
            margin: 20px auto;
            padding: 35px 45px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
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
        .table-data tr {
            page-break-inside: avoid;
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

        /* Filter Toolbar (Hidden during print) */
        .filter-panel {
            max-width: 1240px;
            margin: 20px auto 0 auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 16px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }

        /* Action Toolbar (Hidden during print) */
        .action-toolbar {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 1000;
            display: flex;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.18);
            padding: 10px 16px;
            background: white;
            border-radius: 50px;
            border: 1px solid #cbd5e1;
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
            .filter-panel, .action-toolbar, .no-print {
                display: none !important;
            }
            @page {
                size: A4 landscape;
                margin: 10mm 8mm;
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

<!-- Filter Panel (Hanya tampil di layar) -->
<div class="filter-panel no-print">
    <form method="GET" action="" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small fw-bold text-dark mb-1">Filter Kecamatan:</label>
            <select name="district_id" class="form-select form-select-sm">
                <option value="0">-- Seluruh Kecamatan se-Inhu --</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filter_district === (int)$d['id'] ? 'selected' : '' ?>>
                        Kec. <?= e($d['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold text-dark mb-1">Filter Kategori Jasa:</label>
            <select name="category_id" class="form-select form-select-sm">
                <option value="0">-- Seluruh Kategori Jasa --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $filter_category === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-bold text-dark mb-1">Verifikasi KTP:</label>
            <select name="verified" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="1" <?= $filter_verif === 1 ? 'selected' : '' ?>>Hanya Terverifikasi</option>
                <option value="0" <?= $filter_verif === 0 ? 'selected' : '' ?>>Belum Verifikasi</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-teal text-white fw-bold px-3" style="background-color: #0d9488;">
                <i class="fa-solid fa-filter me-1"></i> Terapkan
            </button>
            <?php if ($filter_district > 0 || $filter_category > 0 || $filter_verif !== -1): ?>
                <a href="<?= BASE_URL ?>/admin/export_providers.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-sm btn-outline-dark ms-auto">
                <i class="fa-solid fa-house me-1"></i> Dashboard
            </a>
        </div>
    </form>
</div>

<!-- Floating Action Bar -->
<div class="action-toolbar no-print">
    <button type="button" class="btn btn-danger fw-bold rounded-pill px-3 shadow-xs d-flex align-items-center gap-1.5" onclick="window.print()">
        <i class="fa-solid fa-file-pdf"></i>
        <span>Download / Cetak PDF</span>
    </button>
    <a href="?format=excel<?= $filter_district ? '&district_id='.$filter_district : '' ?><?= $filter_category ? '&category_id='.$filter_category : '' ?><?= $filter_verif !== -1 ? '&verified='.$filter_verif : '' ?>" class="btn btn-success fw-bold rounded-pill px-3 shadow-xs d-flex align-items-center gap-1.5">
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
        <div class="text-muted small">
            Status Operasional & Basis Data Rekanan Mitra Jasa Inhu
            <?php if ($filter_district > 0): ?>
                &bull; Wilayah: <strong>Kec. <?= e($districts[array_search($filter_district, array_column($districts, 'id'))]['name'] ?? 'Inhu') ?></strong>
            <?php endif; ?>
            <?php if ($filter_category > 0): ?>
                &bull; Kategori: <strong><?= e($categories[array_search($filter_category, array_column($categories, 'id'))]['name'] ?? 'Umum') ?></strong>
            <?php endif; ?>
        </div>
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
                <div class="text-muted" style="font-size: 9.5px;">MEMILIKI TOKO FISIK</div>
                <div class="fw-bold text-warning-emphasis fs-6"><?= $totalToko ?> Lokasi Toko</div>
            </div>
        </div>
    </div>

    <!-- Tabel Data Mitra -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 28px;">No</th>
                <th style="width: 170px;">Nama Usaha & Tipe</th>
                <th style="width: 120px;">Pemilik</th>
                <th style="width: 100px;">WhatsApp</th>
                <th style="width: 110px;">Kategori Jasa</th>
                <th style="width: 140px;">Wilayah & Alamat</th>
                <th style="width: 85px;">Verifikasi</th>
                <th style="width: 85px;">Estimasi Tarif</th>
                <th style="width: 60px;">Porto</th>
                <th style="width: 50px;">Rating</th>
                <th style="width: 70px;">Tgl Terdaftar</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($providers)): ?>
                <tr>
                    <td colspan="11" class="text-center py-4 text-muted fst-italic">
                        Tidak ada data mitra yang sesuai dengan filter yang dipilih.
                    </td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($providers as $p): ?>
                    <tr>
                        <td class="text-center fw-bold"><?= $no++ ?></td>
                        <td>
                            <strong class="text-dark d-block"><?= e($p['business_name']) ?></strong>
                            <div class="mt-0.5">
                                <?= render_provider_service_type_badge($p, 'xs') ?>
                            </div>
                            <?php if ($p['is_active'] == 0): ?>
                                <span class="badge-blocked mt-0.5">DIBLOKIR</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark"><?= e($p['owner_name']) ?></div>
                            <small class="text-muted" style="font-size: 8.5px;">ID: #<?= $p['provider_id'] ?></small>
                        </td>
                        <td class="font-monospace fw-semibold text-dark"><?= e($p['phone']) ?></td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= e($p['category_name'] ?: 'Umum') ?></span>
                        </td>
                        <td>
                            <strong>Kec. <?= e($p['district_name'] ?: 'Inhu') ?></strong>
                            <?php if (!empty($p['village_name'])): ?>
                                <div class="text-muted" style="font-size: 9px;">Desa <?= e($p['village_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($p['address'])): ?>
                                <div class="text-secondary fst-italic" style="font-size: 8.5px;"><?= e($p['address']) ?></div>
                            <?php endif; ?>
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
                                <div class="text-muted" style="font-size: 8px;"><?= e($p['credential_title']) ?></div>
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
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Tanda Tangan & Pengesahan Dokumen -->
    <div class="row mt-4 pt-3">
        <div class="col-8">
            <div class="text-muted" style="font-size: 9.5px; line-height: 1.5;">
                <strong>Catatan Legalitas & Keabsahan:</strong><br>
                1. Dokumen ini dicetak otomatis secara langsung dari server basis data aktif platform <strong>JASA INHU</strong>.<br>
                2. Data mitra di atas merupakan data pelaku jasa perorangan dan UMKM yang telah terdaftar resmi di Kabupaten Indragiri Hulu.<br>
                3. Digunakan sebagai arsip pembinaan, pengawasan kualitas layanan, dan data rekapitulasi daerah.
            </div>
        </div>
        <div class="col-4 text-center">
            <div class="text-muted" style="font-size: 10px;">Indragiri Hulu, <?= date('d F Y') ?></div>
            <div class="fw-bold text-dark mt-1" style="font-size: 10.5px;">PENGELOLA JASA INHU</div>
            <div style="height: 48px;"></div>
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

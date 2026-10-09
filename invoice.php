<?php
/**
 * Bukti Transaksi & Kwitansi Elektronik Resmi (E-Receipt)
 * JASA INHU - Pusat Layanan Terpadu Kabupaten Indragiri Hulu
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Pastikan sudah masuk akun
if (!is_logged_in()) {
    set_flash('info', 'Silakan masuk akun terlebih dahulu untuk melihat kwitansi resmi.');
    redirect('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$request_id = (int)($_GET['id'] ?? 0);
if ($request_id <= 0) {
    set_flash('danger', 'ID pesanan tidak valid.');
    redirect('/');
}

$user = current_user();
$db = get_db();

// 1. Ambil data pesanan lengkap
$stmt = $db->prepare("
    SELECT sr.*, 
           sc.name as category_name, sc.icon as category_icon,
           d.name as customer_district, v.name as customer_village,
           cust.name as customer_name, cust.phone as customer_phone, cust.email as customer_email,
           sp.id as resolved_provider_id, sp.user_id as provider_user_id, sp.business_name, sp.is_verified as provider_verified,
           sp.address as provider_address, prov_d.name as provider_district,
           prov_u.name as provider_owner_name, prov_u.phone as provider_phone,
           (SELECT offer_price FROM service_request_responses srr WHERE srr.request_id = sr.id AND (srr.status = 'accepted' OR srr.provider_id = sp.id) LIMIT 1) as response_price,
           r.rating as review_rating, r.comment as review_comment, r.created_at as review_date
    FROM service_requests sr
    JOIN users cust ON sr.user_id = cust.id
    JOIN service_categories sc ON sr.category_id = sc.id
    LEFT JOIN districts d ON sr.district_id = d.id
    LEFT JOIN villages v ON sr.village_id = v.id
    LEFT JOIN service_providers sp ON (sr.provider_id = sp.id OR sp.id = (SELECT srr2.provider_id FROM service_request_responses srr2 WHERE srr2.request_id = sr.id AND srr2.status = 'accepted' LIMIT 1))
    LEFT JOIN users prov_u ON sp.user_id = prov_u.id
    LEFT JOIN districts prov_d ON sp.district_id = prov_d.id
    LEFT JOIN reviews r ON r.request_id = sr.id
    WHERE sr.id = ?
    LIMIT 1
");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    set_flash('danger', 'Data transaksi atau kwitansi tidak ditemukan.');
    redirect('/');
}

// 2. Hak Akses (Otorisasi Keamanan): Hanya boleh diakses oleh pemesan, penyedia jasa yang bersangkutan, atau admin
$is_customer = ((int)$request['user_id'] === (int)$user['id']);
$is_provider = (!empty($request['provider_user_id']) && (int)$request['provider_user_id'] === (int)$user['id']);
$is_admin = ($user['role_name'] === 'admin');

if (!$is_customer && !$is_provider && !$is_admin) {
    set_flash('danger', 'Anda tidak memiliki hak akses untuk melihat bukti kwitansi ini.');
    redirect('/');
}

// 3. Data format kwitansi
$invoice_number = 'INV/INHU/' . date('Ymd', strtotime($request['created_at'])) . '/' . str_pad((string)$request['id'], 5, '0', STR_PAD_LEFT);
$total_amount = (!empty($request['final_price']) && (float)$request['final_price'] > 0) 
    ? (float)$request['final_price'] 
    : ($request['response_price'] ?: ($request['budget'] ?: 0));
$is_paid = ($request['status'] === 'completed');
$back_url = $is_provider ? BASE_URL . '/provider/index.php' : BASE_URL . '/user/requests.php?id=' . $request['id'];

$page_title = 'Kwitansi Resmi #' . $invoice_number;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | <?= APP_NAME ?></title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #0d9488;
            --primary-dark: #0f766e;
            --dark: #0f172a;
        }
        body {
            background-color: #f1f5f9;
            color: #334155;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 0.9rem;
        }
        .invoice-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            max-width: 820px;
            margin: 30px auto;
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .invoice-header-strip {
            height: 8px;
            background: linear-gradient(90deg, #0d9488 0%, #14b8a6 50%, #f59e0b 100%);
        }
        .stamp-paid {
            display: inline-block;
            padding: 6px 16px;
            border: 3px solid #16a34a;
            color: #16a34a;
            font-weight: 800;
            font-size: 1.1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            border-radius: 8px;
            transform: rotate(-5deg);
            opacity: 0.9;
        }
        .stamp-pending {
            display: inline-block;
            padding: 6px 16px;
            border: 3px solid #ea580c;
            color: #ea580c;
            font-weight: 800;
            font-size: 1.1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            border-radius: 8px;
            transform: rotate(-5deg);
            opacity: 0.9;
        }
        .watermark-bg {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 6.5rem;
            font-weight: 900;
            color: rgba(13, 148, 136, 0.035);
            text-transform: uppercase;
            letter-spacing: 12px;
            pointer-events: none;
            user-select: none;
            white-space: nowrap;
        }
        .receipt-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        .floating-toolbar {
            position: sticky;
            top: 20px;
            z-index: 999;
            margin-bottom: 20px;
        }
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .no-print {
                display: none !important;
            }
            .invoice-card {
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }
            .watermark-bg {
                color: rgba(0, 0, 0, 0.04) !important;
            }
        }
    </style>
</head>
<body>

<div class="container py-3">
    <!-- Floating Action Toolbar (Disembunyikan Saat Print) -->
    <div class="floating-toolbar no-print">
        <div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-3 rounded-4 shadow-sm border" style="max-width: 820px; margin: 0 auto;">
            <a href="<?= $back_url ?>" class="btn btn-outline-secondary btn-sm fw-semibold">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Riwayat
            </a>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-whatsapp btn-sm fw-semibold" onclick="shareInvoiceWa()">
                    <i class="fa-brands fa-whatsapp me-1"></i> Bagikan Kwitansi
                </button>
                <button type="button" class="btn btn-primary btn-sm fw-bold px-3" style="background-color: var(--primary); border-color: var(--primary);" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Cetak / Simpan PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Lembar Kwitansi Resmi -->
    <div class="invoice-card p-4 p-md-5">
        <div class="invoice-header-strip position-absolute top-0 start-0 w-100"></div>
        <div class="watermark-bg"><?= $is_paid ? 'LUNAS' : 'SELESAI' ?></div>

        <!-- 1. Kop Surat & Identitas Platform JASA INHU -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center pb-4 mb-4 border-bottom gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= asset_url('images/logo.png') ?>" alt="JASA INHU" style="height: 48px; object-fit: contain;">
                <div>
                    <h5 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;">JASA INHU</h5>
                    <div class="text-muted small" style="font-size: 0.78rem;">Pusat Layanan Jasa Terpadu Kab. Indragiri Hulu, Riau</div>
                    <div class="text-muted small" style="font-size: 0.72rem;">Portal Resmi: https://jasa-inhu.id &bull; Siaga 14 Kecamatan</div>
                </div>
            </div>
            <div class="text-md-end">
                <div class="small fw-semibold text-muted text-uppercase" style="font-size: 0.72rem;">Nomor Kwitansi Resmi:</div>
                <div class="fw-bold text-dark fs-6 font-monospace"><?= e($invoice_number) ?></div>
                <div class="text-muted small" style="font-size: 0.75rem;">
                    Diterbitkan: <?= format_date($request['created_at'], true) ?>
                </div>
            </div>
        </div>

        <!-- 2. Status Stamp & Judul Dokumen -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <span class="badge text-bg-light border text-primary small px-2.5 py-1 mb-1">
                    <i class="fa-solid fa-file-invoice text-teal me-1"></i> BUKTI KWITANSI ELEKTRONIK
                </span>
                <h4 class="fw-bold text-dark mb-0">Tanda Terima Pengerjaan Jasa</h4>
            </div>
            <div>
                <?php if ($is_paid): ?>
                    <div class="stamp-paid">
                        <i class="fa-solid fa-check-circle me-1"></i> LUNAS
                    </div>
                <?php else: ?>
                    <div class="stamp-pending">
                        <i class="fa-solid fa-clock me-1"></i> DIKERJAKAN
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. Rincian Pihak Pemesan & Penyedia Jasa (2 Kolom Bersih) -->
        <div class="row g-3 p-3 bg-light rounded-4 border mb-4">
            <!-- Kolom Pelanggan -->
            <div class="col-md-6 border-end-md">
                <div class="text-muted fw-bold text-uppercase small mb-2" style="font-size: 0.72rem;">
                    <i class="fa-solid fa-user text-teal me-1"></i> Diberikan Kepada (Pelanggan):
                </div>
                <h6 class="fw-bold text-dark mb-1"><?= e($request['customer_name']) ?></h6>
                <div class="small text-secondary mb-1">
                    <i class="fa-solid fa-phone me-1 text-muted"></i> <?= e($request['customer_phone']) ?>
                </div>
                <div class="small text-secondary mb-1">
                    <i class="fa-solid fa-location-dot me-1 text-danger"></i> Kec. <?= e($request['customer_district'] ?: 'Indragiri Hulu') ?><?= !empty($request['customer_village']) ? ', ' . e($request['customer_village']) : '' ?>
                </div>
                <?php if (!empty($request['address_detail'])): ?>
                    <div class="small text-muted fst-italic">
                        Patokan: "<?= e($request['address_detail']) ?>"
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kolom Mitra Penyedia Jasa -->
            <div class="col-md-6 ps-md-4">
                <div class="text-muted fw-bold text-uppercase small mb-2" style="font-size: 0.72rem;">
                    <i class="fa-solid fa-store text-teal me-1"></i> Dikerjakan Oleh (Mitra Jasa):
                </div>
                <h6 class="fw-bold text-dark mb-1 d-flex align-items-center gap-1.5">
                    <span><?= e($request['business_name'] ?: 'Mitra Jasa Terdaftar') ?></span>
                    <?php if ($request['provider_verified']): ?>
                        <i class="fa-solid fa-circle-check text-success small" title="Mitra Terverifikasi KTP"></i>
                    <?php endif; ?>
                </h6>
                <div class="small text-secondary mb-1">
                    Teknisi/Penanggung Jawab: <strong><?= e($request['provider_owner_name'] ?: '-') ?></strong>
                </div>
                <div class="small text-secondary mb-1">
                    <i class="fa-solid fa-phone me-1 text-muted"></i> <?= e($request['provider_phone'] ?: '-') ?>
                </div>
                <div class="small text-secondary">
                    <i class="fa-solid fa-map-pin me-1 text-muted"></i> Basis: Kec. <?= e($request['provider_district'] ?: 'Kab. Indragiri Hulu') ?>
                </div>
            </div>
        </div>

        <!-- 4. Tabel Rincian Pekerjaan & Biaya -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered receipt-table align-middle">
                <thead>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 55%;">Deskripsi Pekerjaan & Layanan Jasa</th>
                        <th style="width: 20%;" class="text-center">Kategori</th>
                        <th style="width: 20%;" class="text-end">Biaya Jasa (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-center fw-bold">1</td>
                        <td>
                            <div class="fw-bold text-dark mb-1 fs-6"><?= e($request['title']) ?></div>
                            <p class="text-muted small mb-0" style="white-space: pre-line; line-height: 1.45;">
                                <?= e($request['description']) ?>
                            </p>
                            <?php if (!empty($request['cost_breakdown'])): ?>
                                <div class="mt-2 p-2 bg-light rounded border small">
                                    <div class="fw-bold text-dark mb-0.5" style="font-size: 0.75rem;"><i class="fa-solid fa-receipt text-teal me-1"></i> Rincian Kesepakatan & Suku Cadang:</div>
                                    <div class="text-secondary" style="white-space: pre-line; font-size: 0.78rem;"><?= e($request['cost_breakdown']) ?></div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge text-bg-light border text-primary">
                                <i class="fa-solid <?= e($request['category_icon'] ?: 'fa-wrench') ?> me-1"></i>
                                <?= e($request['category_name']) ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-dark fs-6 font-monospace">
                            <?= format_rupiah($total_amount) ?>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-light">
                        <td colspan="3" class="text-end fw-bold text-dark py-3">Total Biaya Disepakati:</td>
                        <td class="text-end fw-bold text-teal py-3 fs-5 font-monospace">
                            <?= format_rupiah($total_amount) ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="p-3 bg-light border-0">
                            <div class="d-flex justify-content-between align-items-center small">
                                <span class="text-muted">Metode Pembayaran: <strong>Tunai di Lokasi / Pembayaran Langsung ke Mitra</strong></span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2.5">
                                    <i class="fa-solid fa-circle-check me-1"></i> Transaksi Terkonfirmasi Sah
                                </span>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- 5. Ulasan Bintang Pelanggan (Jika Sudah Diulas) -->
        <?php if (!empty($request['review_rating'])): ?>
            <div class="p-3 rounded-3 border bg-warning bg-opacity-10 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-1.5">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-dark small"><i class="fa-solid fa-star text-warning me-1"></i> Penilaian Kepuasan Pelanggan:</span>
                        <div class="text-warning small">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="fa-<?= $s <= (int)$request['review_rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                            <?php endfor; ?>
                            <span class="fw-bold text-dark ms-1"><?= (int)$request['review_rating'] ?>.0 / 5.0</span>
                        </div>
                    </div>
                    <span class="text-muted small" style="font-size: 0.72rem;"><?= format_date($request['review_date']) ?></span>
                </div>
                <?php if (!empty($request['review_comment'])): ?>
                    <p class="text-secondary small mb-0 fst-italic">"<?= e($request['review_comment']) ?>"</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 6. Footer Kwitansi & QR Code Verifikasi -->
        <div class="row align-items-center pt-3 border-top g-3">
            <div class="col-sm-8">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-1 rounded-2 border bg-white shadow-xs flex-shrink-0">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=72x72&data=<?= urlencode(BASE_URL . '/invoice.php?id=' . $request['id']) ?>" alt="QR Verifikasi Transaksi" style="width: 64px; height: 64px;">
                    </div>
                    <div>
                        <div class="fw-bold text-dark small mb-0.5">Kwitansi Sah Terverifikasi Sistem</div>
                        <div class="text-muted small" style="font-size: 0.72rem; line-height: 1.4;">
                            Scan QR Code ini untuk memverifikasi keaslian transaksi pada basis data platform JASA INHU.
                            Dokumen ini merupakan bukti sah pengerjaan jasa antara masyarakat dan mitra usaha lokal.
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4 text-sm-end">
                <div class="text-muted small mb-1" style="font-size: 0.72rem;">Kabupaten Indragiri Hulu, Riau</div>
                <div class="fw-bold text-dark small">Platform Resmi JASA INHU</div>
                <div class="text-muted small" style="font-size: 0.7rem;">Dikelola oleh Tim Pengembang Lokal Inhu</div>
            </div>
        </div>
    </div>
</div>

<script>
function shareInvoiceWa() {
    const invNo = "<?= e($invoice_number) ?>";
    const jobTitle = "<?= e(addslashes($request['title'])) ?>";
    const provName = "<?= e(addslashes($request['business_name'] ?: 'Mitra Jasa')) ?>";
    const amount = "<?= format_rupiah($total_amount) ?>";
    const url = window.location.href;

    const text = `Halo, berikut Bukti Kwitansi Transaksi Jasa Resmi dari platform *JASA INHU*:\n\n` +
                 `📄 *No. Kwitansi*: ${invNo}\n` +
                 `🔧 *Jasa*: ${jobTitle}\n` +
                 `🏪 *Mitra*: ${provName}\n` +
                 `💰 *Total*: ${amount}\n` +
                 `✅ *Status*: LUNAS / SELESAI\n\n` +
                 `Buka & Cetak Kwitansi Resmi: ${url}`;

    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}
</script>

</body>
</html>

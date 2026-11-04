<?php
/**
 * Bukti Transaksi & Kwitansi Elektronik Resmi (E-Receipt)
 * JASA INHU - Pusat Layanan Terpadu Kabupaten Indragiri Hulu
 * Terbuka secara aman dan instan untuk pelanggan tanpa hambatan login
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$request_id = (int)($_GET['id'] ?? 0);
$db = get_db();

// 1. Ambil data pesanan lengkap
$request = null;
if ($request_id > 0) {
    $stmt = $db->prepare("
        SELECT sr.*, 
               sc.name as category_name, sc.icon as category_icon,
               d.name as customer_district, v.name as customer_village,
               cust.name as customer_name, cust.phone as customer_phone, cust.email as customer_email,
               COALESCE(sp_direct.id, sp_bid.id) as resolved_provider_id,
               COALESCE(sp_direct.user_id, sp_bid.user_id) as provider_user_id,
               COALESCE(sp_direct.business_name, sp_bid.business_name) as business_name,
               COALESCE(sp_direct.is_verified, sp_bid.is_verified) as provider_verified,
               COALESCE(sp_direct.address, sp_bid.address) as provider_address,
               COALESCE(prov_d_direct.name, prov_d_bid.name) as provider_district,
               COALESCE(prov_u_direct.name, prov_u_bid.name) as provider_owner_name,
               COALESCE(prov_u_direct.phone, prov_u_bid.phone) as provider_phone,
               srr_accepted.offer_price as response_price,
               r.id as review_id, r.rating as review_rating, r.comment as review_comment, 
               r.photo_url as review_photo, r.tags as review_tags, 
               r.reply_text as review_reply, r.replied_at as review_replied_at,
               r.created_at as review_date
        FROM service_requests sr
        JOIN users cust ON sr.user_id = cust.id
        JOIN service_categories sc ON sr.category_id = sc.id
        LEFT JOIN districts d ON sr.district_id = d.id
        LEFT JOIN villages v ON sr.village_id = v.id
        -- 1. Direct provider
        LEFT JOIN service_providers sp_direct ON sr.provider_id = sp_direct.id
        LEFT JOIN users prov_u_direct ON sp_direct.user_id = prov_u_direct.id
        LEFT JOIN districts prov_d_direct ON sp_direct.district_id = prov_d_direct.id
        -- 2. Bidding accepted provider
        LEFT JOIN service_request_responses srr_accepted ON (srr_accepted.request_id = sr.id AND srr_accepted.status = 'accepted')
        LEFT JOIN service_providers sp_bid ON srr_accepted.provider_id = sp_bid.id
        LEFT JOIN users prov_u_bid ON sp_bid.user_id = prov_u_bid.id
        LEFT JOIN districts prov_d_bid ON sp_bid.district_id = prov_d_bid.id
        LEFT JOIN reviews r ON r.request_id = sr.id
        WHERE sr.id = ?
        LIMIT 1
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
}

// 2. Tampilkan halaman 404 ramah jika pesanan tidak ditemukan (Mencegah loop redirect)
if (!$request) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Kwitansi Tidak Ditemukan | <?= APP_NAME ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .btn-teal { background-color: #0d9488; color: #fff; }
            .btn-teal:hover { background-color: #0f766e; color: #fff; }
        </style>
    </head>
    <body class="d-flex align-items-center justify-content-center min-vh-100 p-3">
        <div class="card border-0 shadow-sm rounded-4 text-center p-4 p-md-5 bg-white" style="max-width: 460px; width: 100%;">
            <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px;">
                <i class="fa-solid fa-file-circle-xmark fs-2"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Kwitansi Tidak Ditemukan</h5>
            <p class="text-muted small mb-4">Bukti transaksi atau nomor kwitansi yang Anda cari tidak ditemukan. Pastikan tautan kwitansi dari WhatsApp sudah sesuai.</p>
            <a href="<?= BASE_URL ?>/" class="btn btn-teal rounded-pill px-4 py-2 fw-semibold">
                <i class="fa-solid fa-house me-1.5"></i> Kembali ke Beranda
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 3. Hak Akses & Privasi Keamanan
$is_logged = is_logged_in();
$current_u = $is_logged ? current_user() : null;
$is_customer = ($is_logged && (int)$request['user_id'] === (int)$current_u['id']);
$is_provider = ($is_logged && !empty($request['provider_user_id']) && (int)$request['provider_user_id'] === (int)$current_u['id']);
$is_admin = ($is_logged && ($current_u['role_name'] ?? '') === 'admin');
$is_authorized = ($is_customer || $is_provider || $is_admin);

// Sensor nomor handphone untuk pengunjung umum demi perlindungan privasi
$customer_phone_display = $is_authorized ? $request['customer_phone'] : mask_phone_number($request['customer_phone']);

// Tautan tombol kembali
if ($is_provider) {
    $back_url = BASE_URL . '/provider/index.php';
    $back_label = 'Dashboard Mitra';
} elseif ($is_customer) {
    $back_url = BASE_URL . '/user/requests.php?id=' . $request['id'];
    $back_label = 'Riwayat Pesanan';
} elseif ($is_admin) {
    $back_url = BASE_URL . '/admin/orders.php';
    $back_label = 'Manajemen Pesanan';
} else {
    $back_url = BASE_URL . '/';
    $back_label = 'Beranda JASA INHU';
}

// 4. Format Nomor Kwitansi & Biaya
$order_seq = (int)$request['id'];
if ($order_seq > 999) {
    try {
        $order_seq = (int)$db->query("SELECT COUNT(*) FROM service_requests WHERE id <= " . (int)$request['id'])->fetchColumn() ?: $order_seq;
    } catch (Exception $e) {}
}
$invoice_number = 'INV/INHU/' . date('Ymd', strtotime($request['created_at'])) . '/' . str_pad((string)$order_seq, 4, '0', STR_PAD_LEFT);
$total_amount = (!empty($request['final_price']) && (float)$request['final_price'] > 0) 
    ? (float)$request['final_price'] 
    : ($request['response_price'] ?: ($request['budget'] ?: 0));
$is_paid = ($request['status'] === 'completed');

// Token keamanan HMAC untuk ulasan langsung dari kwitansi (Tanpa login)
$review_token = hash_hmac('sha256', 'invoice_review_' . $request['id'] . '_' . $request['created_at'], 'JASA_INHU_SECRET_REVIEW_KEY_2026');

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
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            max-width: 820px;
            margin: 20px auto 40px;
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
            top: 15px;
            z-index: 999;
            margin-bottom: 15px;
        }
        .star-rating-box .fa-star {
            cursor: pointer;
            font-size: 1.85rem;
            color: #cbd5e1;
            transition: transform 0.15s ease, color 0.15s ease;
        }
        .star-rating-box .fa-star.active,
        .star-rating-box .fa-star.hovered {
            color: #f59e0b;
        }
        .star-rating-box .fa-star:hover {
            transform: scale(1.15);
        }
        .review-tag-chip {
            cursor: pointer;
            user-select: none;
            font-size: 0.76rem;
            padding: 6px 12px;
            border-radius: 50px;
            transition: all 0.2s ease;
        }
        .review-tag-chip.active {
            background-color: #0d9488 !important;
            color: #ffffff !important;
            border-color: #0d9488 !important;
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
    <!-- Floating Action Toolbar -->
    <div class="floating-toolbar no-print">
        <div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-2.5 p-md-3 rounded-4 shadow-sm border" style="max-width: 820px; margin: 0 auto;">
            <a href="<?= $back_url ?>" class="btn btn-outline-secondary btn-sm fw-semibold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> <?= e($back_label) ?>
            </a>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-success btn-sm fw-semibold rounded-pill px-3" onclick="shareInvoiceWa()">
                    <i class="fa-brands fa-whatsapp me-1"></i> Bagikan Kwitansi
                </button>
                <button type="button" class="btn btn-primary btn-sm fw-bold px-3 rounded-pill" style="background-color: var(--primary); border-color: var(--primary);" onclick="window.print()">
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
                    <div class="text-muted small" style="font-size: 0.72rem;">Portal Resmi: https://jasainhu.gafahrandi.my.id &bull; Siaga 14 Kecamatan</div>
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
                    <i class="fa-solid fa-file-invoice text-teal me-1"></i> BUKTI KWITANSI ELEKTRONIK RESMI
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

        <!-- 3. Rincian Pihak Pemesan & Penyedia Jasa (2 Kolom) -->
        <div class="row g-3 p-3 bg-light rounded-4 border mb-4">
            <!-- Kolom Pelanggan -->
            <div class="col-md-6 border-end-md">
                <div class="text-muted fw-bold text-uppercase small mb-2" style="font-size: 0.72rem;">
                    <i class="fa-solid fa-user text-teal me-1"></i> Diberikan Kepada (Pelanggan):
                </div>
                <h6 class="fw-bold text-dark mb-1"><?= e($request['customer_name']) ?></h6>
                <div class="small text-secondary mb-1">
                    <i class="fa-solid fa-phone me-1 text-muted"></i> <?= e($customer_phone_display) ?>
                </div>
                <div class="small text-secondary mb-1">
                    <i class="fa-solid fa-location-dot me-1 text-danger"></i> Kec. <?= e($request['customer_district'] ?: 'Indragiri Hulu') ?><?= !empty($request['customer_village']) ? ', ' . e($request['customer_village']) : '' ?>
                </div>
                <?php if ($is_authorized && !empty($request['address_detail'])): ?>
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
                        <i class="fa-solid fa-circle-check text-success small" title="Mitra Terverifikasi"></i>
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
                                    <div class="fw-bold text-dark mb-0.5" style="font-size: 0.75rem;"><i class="fa-solid fa-receipt text-teal me-1"></i> Rincian Suku Cadang & Biaya:</div>
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
                            <div class="d-flex justify-content-between align-items-center small flex-wrap gap-2">
                                <span class="text-muted">Metode Pembayaran: <strong>Pembayaran Langsung di Lokasi ke Mitra</strong></span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2.5">
                                    <i class="fa-solid fa-circle-check me-1"></i> Transaksi Sah Terverifikasi Sistem
                                </span>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- 5. Bagian Ulasan & Bintang Pelanggan -->
        <div id="reviewContainerSection">
            <?php if (!empty($request['review_rating'])): ?>
                <!-- Sudah Diulas -->
                <div class="p-3.5 rounded-4 border bg-warning bg-opacity-10 mb-4 shadow-xs">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark small"><i class="fa-solid fa-star text-warning me-1"></i> Ulasan & Penilaian Pelanggan:</span>
                            <div class="text-warning small fs-6">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <i class="fa-<?= $s <= (int)$request['review_rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                <?php endfor; ?>
                                <span class="fw-bold text-dark ms-1"><?= (int)$request['review_rating'] ?>.0 / 5.0</span>
                            </div>
                        </div>
                        <span class="text-muted small" style="font-size: 0.72rem;"><?= format_date($request['review_date']) ?></span>
                    </div>

                    <?php if (!empty($request['review_tags'])): ?>
                        <div class="d-flex flex-wrap gap-1.5 mb-2">
                            <?php foreach (explode(',', $request['review_tags']) as $t): ?>
                                <span class="badge bg-white text-teal border border-teal-subtle py-1 px-2.5" style="font-size: 0.72rem;">
                                    <i class="fa-solid fa-check me-1"></i> <?= e(trim($t)) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($request['review_comment'])): ?>
                        <p class="text-dark small mb-2 bg-white p-2.5 rounded-3 border border-light" style="white-space: pre-line;">
                            "<?= e($request['review_comment']) ?>"
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($request['review_photo'])): ?>
                        <div class="my-2 p-2 bg-white rounded-3 border d-inline-block">
                            <span class="text-muted small d-block mb-1 fw-semibold" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-camera text-teal me-1"></i> Foto Bukti Hasil Kerja:
                            </span>
                            <a href="<?= e(get_media_url($request['review_photo'])) ?>" target="_blank" class="d-inline-block position-relative border rounded-3 overflow-hidden shadow-xs">
                                <img src="<?= e(get_media_url($request['review_photo'])) ?>" alt="Hasil Kerja" class="img-fluid rounded-3" style="max-height: 140px; max-width: 200px; object-fit: cover;">
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($request['review_reply'])): ?>
                        <div class="mt-2.5 p-2.5 bg-white rounded-3 border-start border-4 border-teal shadow-xs">
                            <div class="fw-bold text-teal small mb-0.5" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-reply me-1"></i> Respon Mitra (<?= e($request['business_name'] ?: 'Penyedia Jasa') ?>):
                            </div>
                            <div class="text-secondary small fst-italic">"<?= e($request['review_reply']) ?>"</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($is_paid): ?>
                <!-- Belum Diulas: Form Beri Bintang Langsung di Lembar Kwitansi (no-print) -->
                <div class="p-3.5 p-md-4 rounded-4 border bg-teal-subtle bg-opacity-25 mb-4 no-print shadow-xs" id="invoiceReviewFormCard">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                            <i class="fa-solid fa-star"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Beri Bintang & Ulasan Pelayanan</h6>
                            <div class="text-muted small" style="font-size: 0.76rem;">Bagikan kepuasan pengerjaan oleh <?= e($request['business_name'] ?: 'Mitra Jasa') ?></div>
                        </div>
                    </div>

                    <div id="invoiceReviewAlert" class="alert alert-danger d-none py-2 px-3 small rounded-3 mb-3"></div>

                    <!-- Bintang Interaktif -->
                    <div class="my-3 text-center bg-white p-3 rounded-4 border">
                        <div class="star-rating-box d-flex justify-content-center gap-2 mb-1" id="invoiceStarRatingBox">
                            <i class="fa-solid fa-star active" data-rating="1"></i>
                            <i class="fa-solid fa-star active" data-rating="2"></i>
                            <i class="fa-solid fa-star active" data-rating="3"></i>
                            <i class="fa-solid fa-star active" data-rating="4"></i>
                            <i class="fa-solid fa-star active" data-rating="5"></i>
                        </div>
                        <input type="hidden" id="invoiceRatingVal" value="5">
                        <div class="fw-bold text-dark small" id="invoiceRatingLabel">Sangat Puas! Pelayanan Memuaskan ⭐⭐⭐⭐⭐</div>
                    </div>

                    <!-- Tag Kesan Cepat -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1.5">Apa yang paling Anda sukai?</label>
                        <div class="d-flex flex-wrap gap-1.5" id="invoiceTagsContainer">
                            <span class="badge bg-white text-secondary border review-tag-chip active" data-tag="Pengerjaan Cepat" onclick="toggleInvoiceTag(this)">⚡ Pengerjaan Cepat</span>
                            <span class="badge bg-white text-secondary border review-tag-chip active" data-tag="Hasil Rapi & Bersih" onclick="toggleInvoiceTag(this)">🧹 Hasil Rapi & Bersih</span>
                            <span class="badge bg-white text-secondary border review-tag-chip active" data-tag="Harga Transparan" onclick="toggleInvoiceTag(this)">💰 Harga Transparan</span>
                            <span class="badge bg-white text-secondary border review-tag-chip" data-tag="Teknisi Ramah & Sopan" onclick="toggleInvoiceTag(this)">😊 Teknisi Ramah & Sopan</span>
                            <span class="badge bg-white text-secondary border review-tag-chip" data-tag="Komunikasi Lancar" onclick="toggleInvoiceTag(this)">📱 Komunikasi Lancar</span>
                            <span class="badge bg-white text-secondary border review-tag-chip" data-tag="Sangat Direkomendasikan" onclick="toggleInvoiceTag(this)">👍 Sangat Direkomendasikan</span>
                        </div>
                    </div>

                    <!-- Komentar Ulasan -->
                    <div class="mb-3">
                        <label for="invoiceCommentInput" class="form-label small fw-semibold text-secondary mb-1">Tulis Ulasan Anda (Opsional):</label>
                        <textarea class="form-control rounded-3 shadow-none small" id="invoiceCommentInput" rows="2.5" placeholder="Ceritakan bagaimana hasil servis/pekerjaan mitra hari ini..."></textarea>
                    </div>

                    <!-- Upload Foto Hasil Kerja (Dengan Kompresi Otomatis di HP) -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">
                            <i class="fa-solid fa-camera text-teal me-1"></i> Lampirkan Foto Hasil Pekerjaan (Opsional):
                        </label>
                        <input type="file" id="invoicePhotoInput" class="form-control rounded-3 shadow-none small" accept="image/*" onchange="handleInvoicePhotoSelect(this)">
                        
                        <div id="invoicePhotoPreviewBox" class="mt-2 d-none position-relative d-inline-block">
                            <img id="invoicePhotoPreviewImg" src="" alt="Pratinjau Foto" class="img-fluid rounded-3 border shadow-xs" style="max-height: 120px; object-fit: cover;">
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle p-1" style="width: 24px; height: 24px; line-height: 1;" onclick="clearInvoicePhoto()" title="Hapus foto">
                                <i class="fa-solid fa-xmark text-white small"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Tombol Kirim Ulasan -->
                    <div class="text-end">
                        <button type="button" class="btn btn-primary btn-sm fw-bold px-4 py-2 rounded-pill shadow-xs" style="background-color: var(--primary); border-color: var(--primary);" id="btnSubmitInvoiceReview" onclick="submitInvoiceReview()">
                            <i class="fa-solid fa-paper-plane me-1.5" id="btnInvoiceIcon"></i>
                            <span class="spinner-border spinner-border-sm me-1.5 d-none" id="btnInvoiceSpinner" role="status"></span>
                            <span id="btnInvoiceText">Kirim Ulasan & Bintang ⭐</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 6. Footer Kwitansi & QR Code Verifikasi -->
        <div class="row align-items-center pt-3 border-top g-3">
            <div class="col-sm-8">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-1 rounded-2 border bg-white shadow-xs flex-shrink-0">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=72x72&data=<?= urlencode(BASE_URL . '/invoice.php?id=' . $request['id']) ?>" alt="QR Verifikasi Transaksi" style="width: 64px; height: 64px;">
                    </div>
                    <div>
                        <div class="fw-bold text-dark small mb-0.5">Kwitansi Elektronik Sah Terverifikasi Sistem</div>
                        <div class="text-muted small" style="font-size: 0.72rem; line-height: 1.4;">
                            Scan QR Code ini untuk memverifikasi keaslian transaksi pada basis data resmi platform JASA INHU.
                            Dokumen ini merupakan bukti sah pengerjaan jasa antara masyarakat dan mitra usaha lokal Indragiri Hulu.
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4 text-sm-end">
                <div class="text-muted small mb-1" style="font-size: 0.72rem;">Kabupaten Indragiri Hulu, Riau</div>
                <div class="fw-bold text-dark small">Platform Resmi JASA INHU</div>
                <div class="text-muted small" style="font-size: 0.7rem;">Siaga 14 Kecamatan &bull; Cepat & Terpercaya</div>
            </div>
        </div>
    </div>
</div>

<script>
// Fungsi Bagikan Kwitansi via WhatsApp
function shareInvoiceWa() {
    const invNo = <?= json_encode($invoice_number) ?>;
    const jobTitle = <?= json_encode($request['title']) ?>;
    const provName = <?= json_encode($request['business_name'] ?: 'Mitra Jasa') ?>;
    const amount = <?= json_encode(format_rupiah($total_amount)) ?>;
    const url = window.location.href;

    const text = `Halo, berikut Bukti Kwitansi Transaksi Jasa Resmi dari platform *JASA INHU*:\n\n` +
                 `📄 *No. Kwitansi*: ${invNo}\n` +
                 `🔧 *Jasa*: ${jobTitle}\n` +
                 `🏪 *Mitra*: ${provName}\n` +
                 `💰 *Total Biaya*: ${amount}\n` +
                 `✅ *Status*: LUNAS / SELESAI\n\n` +
                 `Lihat Kwitansi Lengkap: ${url}`;

    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}

// Bintang Rating Interaktif
const starLabels = {
    1: 'Kecewa ⭐',
    2: 'Kurang Puas ⭐⭐',
    3: 'Cukup Baik ⭐⭐⭐',
    4: 'Puas, Pengerjaan Bagus ⭐⭐⭐⭐',
    5: 'Sangat Puas! Pelayanan Memuaskan ⭐⭐⭐⭐⭐'
};

const starBox = document.getElementById('invoiceStarRatingBox');
if (starBox) {
    const stars = starBox.querySelectorAll('.fa-star');
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const val = parseInt(this.getAttribute('data-rating'));
            document.getElementById('invoiceRatingVal').value = val;
            document.getElementById('invoiceRatingLabel').textContent = starLabels[val];
            updateStarsUI(val);
        });

        star.addEventListener('mouseenter', function() {
            const val = parseInt(this.getAttribute('data-rating'));
            stars.forEach(s => {
                const sVal = parseInt(s.getAttribute('data-rating'));
                s.classList.toggle('hovered', sVal <= val);
            });
        });

        star.addEventListener('mouseleave', function() {
            stars.forEach(s => s.classList.remove('hovered'));
        });
    });
}

function updateStarsUI(val) {
    if (!starBox) return;
    const stars = starBox.querySelectorAll('.fa-star');
    stars.forEach(s => {
        const sVal = parseInt(s.getAttribute('data-rating'));
        s.classList.toggle('active', sVal <= val);
    });
}

function toggleInvoiceTag(badge) {
    badge.classList.toggle('active');
}

// Kompresi Gambar Client-Side via HTML5 Canvas
let compressedPhotoDataUrl = null;

function compressImageToDataUrl(file, maxWidth = 900, maxHeight = 900, quality = 0.78) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                let w = img.width;
                let h = img.height;
                if (w > maxWidth || h > maxHeight) {
                    if (w > h) {
                        h = Math.round((h * maxWidth) / w);
                        w = maxWidth;
                    } else {
                        w = Math.round((w * maxHeight) / h);
                        h = maxHeight;
                    }
                }
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                resolve(canvas.toDataURL('image/jpeg', quality));
            };
            img.onerror = reject;
            img.src = e.target.result;
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

async function handleInvoicePhotoSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        try {
            compressedPhotoDataUrl = await compressImageToDataUrl(file, 900, 900, 0.78);
            document.getElementById('invoicePhotoPreviewImg').src = compressedPhotoDataUrl;
            document.getElementById('invoicePhotoPreviewBox').classList.remove('d-none');
        } catch (err) {
            const reader = new FileReader();
            reader.onload = function(e) {
                compressedPhotoDataUrl = e.target.result;
                document.getElementById('invoicePhotoPreviewImg').src = e.target.result;
                document.getElementById('invoicePhotoPreviewBox').classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
    }
}

function clearInvoicePhoto() {
    compressedPhotoDataUrl = null;
    const input = document.getElementById('invoicePhotoInput');
    if (input) input.value = '';
    const box = document.getElementById('invoicePhotoPreviewBox');
    if (box) box.classList.add('d-none');
    const img = document.getElementById('invoicePhotoPreviewImg');
    if (img) img.src = '';
}

// Kirim Ulasan dari Kwitansi via AJAX
function submitInvoiceReview() {
    const rating = document.getElementById('invoiceRatingVal').value;
    const comment = document.getElementById('invoiceCommentInput').value.trim();
    const alertEl = document.getElementById('invoiceReviewAlert');
    alertEl.classList.add('d-none');

    const btn = document.getElementById('btnSubmitInvoiceReview');
    const btnText = document.getElementById('btnInvoiceText');
    const btnSpinner = document.getElementById('btnInvoiceSpinner');
    const btnIcon = document.getElementById('btnInvoiceIcon');

    if (btn) btn.disabled = true;
    if (btnText) btnText.textContent = 'Menyimpan Ulasan...';
    if (btnSpinner) btnSpinner.classList.remove('d-none');
    if (btnIcon) btnIcon.classList.add('d-none');

    const formData = new FormData();
    formData.append('request_id', '<?= (int)$request['id'] ?>');
    formData.append('invoice_token', '<?= e($review_token) ?>');
    formData.append('rating', rating);
    formData.append('comment', comment);

    // Ambil tag aktif
    const activeTags = [];
    document.querySelectorAll('#invoiceTagsContainer .review-tag-chip.active').forEach(b => {
        activeTags.push(b.getAttribute('data-tag'));
    });
    activeTags.forEach(t => formData.append('tags[]', t));

    // Kirim foto hasil kompresi Base64 atau file asli
    if (compressedPhotoDataUrl) {
        formData.append('photo_base64', compressedPhotoDataUrl);
    } else {
        const pInput = document.getElementById('invoicePhotoInput');
        if (pInput && pInput.files && pInput.files[0]) {
            formData.append('photo', pInput.files[0]);
        }
    }

    fetch('<?= BASE_URL ?>/api/submit_review.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Kirim Ulasan & Bintang ⭐';
        if (btnSpinner) btnSpinner.classList.add('d-none');
        if (btnIcon) btnIcon.classList.remove('d-none');

        if (data.success) {
            // Tampilkan card ulasan sukses seketika
            let tagsHtml = '';
            if (activeTags.length > 0) {
                tagsHtml = '<div class="d-flex flex-wrap gap-1.5 mb-2">' +
                    activeTags.map(t => `<span class="badge bg-white text-teal border border-teal-subtle py-1 px-2.5" style="font-size: 0.72rem;"><i class="fa-solid fa-check me-1"></i> ${t}</span>`).join('') +
                    '</div>';
            }

            let photoHtml = '';
            if (compressedPhotoDataUrl) {
                photoHtml = `<div class="my-2 p-2 bg-white rounded-3 border d-inline-block">
                    <span class="text-muted small d-block mb-1 fw-semibold" style="font-size: 0.72rem;"><i class="fa-solid fa-camera text-teal me-1"></i> Foto Bukti Hasil Kerja:</span>
                    <img src="${compressedPhotoDataUrl}" alt="Hasil Kerja" class="img-fluid rounded-3" style="max-height: 140px; max-width: 200px; object-fit: cover;">
                </div>`;
            }

            let commentHtml = '';
            if (comment) {
                commentHtml = `<p class="text-dark small mb-2 bg-white p-2.5 rounded-3 border border-light">"${comment}"</p>`;
            }

            let starsIcons = '';
            for (let s = 1; s <= 5; s++) {
                starsIcons += `<i class="fa-${s <= parseInt(rating) ? 'solid' : 'regular'} fa-star"></i>`;
            }

            const successReviewCard = `
                <div class="p-3.5 rounded-4 border bg-warning bg-opacity-10 mb-4 shadow-xs animate__animated animate__fadeIn">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark small"><i class="fa-solid fa-star text-warning me-1"></i> Ulasan & Penilaian Pelanggan:</span>
                            <div class="text-warning small fs-6">
                                ${starsIcons}
                                <span class="fw-bold text-dark ms-1">${rating}.0 / 5.0</span>
                            </div>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 small">Baru Saja</span>
                    </div>
                    ${tagsHtml}
                    ${commentHtml}
                    ${photoHtml}
                </div>
            `;

            document.getElementById('reviewContainerSection').innerHTML = successReviewCard;
            alert(data.message || 'Terima kasih atas ulasan bintang Anda!');
        } else {
            alertEl.textContent = data.message || 'Gagal menyimpan ulasan.';
            alertEl.classList.remove('d-none');
        }
    })
    .catch(err => {
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Kirim Ulasan & Bintang ⭐';
        if (btnSpinner) btnSpinner.classList.add('d-none');
        if (btnIcon) btnIcon.classList.remove('d-none');

        alertEl.textContent = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        alertEl.classList.remove('d-none');
    });
}
</script>

</body>
</html>

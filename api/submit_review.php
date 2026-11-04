<?php
/**
 * API: Pengiriman Ulasan & Penilaian Bintang (Review & Rating)
 * JASA INHU - Pusat Layanan Terpadu Kab. Indragiri Hulu
 * Mendukung autentikasi Akun Pelanggan & Token Kwitansi Resmi (Tanpa Hambatan Login)
 */
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

$db = get_db();

// 1. Cek parameter input
$request_id = (int)($_POST['request_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$invoice_token = trim($_POST['invoice_token'] ?? '');

if ($request_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID pesanan tidak valid.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Mohon pilih rating bintang antara 1 sampai 5.']);
    exit;
}

// 2. Ambil data pesanan jasa dari database
$stmtReq = $db->prepare("
    SELECT sr.*, 
           cust.id as cust_id, cust.name as customer_name, cust.phone as customer_phone,
           COALESCE(sp_direct.id, sp_bid.id) as resolved_provider_id,
           COALESCE(sp_direct.user_id, sp_bid.user_id) as provider_user_id,
           COALESCE(sp_direct.business_name, sp_bid.business_name) as business_name
    FROM service_requests sr
    JOIN users cust ON sr.user_id = cust.id
    LEFT JOIN service_providers sp_direct ON sr.provider_id = sp_direct.id
    LEFT JOIN service_request_responses srr_accepted ON (srr_accepted.request_id = sr.id AND srr_accepted.status = 'accepted')
    LEFT JOIN service_providers sp_bid ON srr_accepted.provider_id = sp_bid.id
    WHERE sr.id = ?
    LIMIT 1
");
$stmtReq->execute([$request_id]);
$request = $stmtReq->fetch();

if (!$request) {
    echo json_encode(['success' => false, 'message' => 'Pesanan jasa tidak ditemukan.']);
    exit;
}

// 3. Verifikasi Hak Akses: Sesi Login Pelanggan ATAU Invoice Token Resmi
$authorized_user_id = 0;
$authorized_user_name = $request['customer_name'] ?? 'Pelanggan';

if (is_logged_in()) {
    $cur = current_user();
    if ((int)$cur['id'] === (int)$request['user_id'] || ($cur['role_name'] ?? '') === 'admin') {
        $authorized_user_id = (int)$request['user_id'];
        $authorized_user_name = $cur['name'];
        if (!validate_csrf()) {
            echo json_encode(['success' => false, 'message' => 'Sesi keamanan berakhir. Silakan muat ulang halaman.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Pesanan ini bukan milik akun Anda yang sedang aktif.']);
        exit;
    }
} elseif (!empty($invoice_token)) {
    // Verifikasi Token Invoice Kwitansi
    $expected_token = hash_hmac('sha256', 'invoice_review_' . $request['id'] . '_' . $request['created_at'], 'JASA_INHU_SECRET_REVIEW_KEY_2026');
    if (hash_equals($expected_token, $invoice_token)) {
        $authorized_user_id = (int)$request['user_id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Tautan atau token kwitansi tidak valid.']);
        exit;
    }
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk ke akun Anda atau buka kwitansi melalui tautan resmi WhatsApp.']);
    exit;
}

// 4. Ambil Provider ID
$provider_id = (int)($request['resolved_provider_id'] ?? 0);
$provider_user_id = (int)($request['provider_user_id'] ?? 0);
$provider_name = $request['business_name'] ?? 'Mitra Jasa';

if ($provider_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mitra penyedia jasa untuk pesanan ini belum ditentukan.']);
    exit;
}

// 5. Ambil Tags Kesan Layanan
$raw_tags = $_POST['tags'] ?? '';
$tags = '';
if (is_array($raw_tags)) {
    $tags = implode(', ', array_filter(array_map('trim', $raw_tags)));
} elseif (is_string($raw_tags)) {
    $tags = trim($raw_tags);
}

// 6. Handle Upload Foto Bukti Hasil Pengerjaan (Mendukung Base64 Canvas Kompresi & Upload File)
$photo_url = null;
if (!empty($_POST['photo_base64']) && str_starts_with($_POST['photo_base64'], 'data:image/')) {
    // Foto base64 yang sudah dikompresi di sisi browser client
    $photo_url = $_POST['photo_base64'];
} elseif (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    try {
        $photo_url = save_uploaded_media($_FILES['photo'], 'reviews', 'review_' . $request_id, 900, 75);
    } catch (Exception $e) {
        // Foto bersifat opsional
    }
}

// 7. Auto-migrasi skema: Pastikan reviews.photo_url bertipe MEDIUMTEXT agar Base64 tersimpan aman
try {
    $colCheck = $db->query("SHOW COLUMNS FROM reviews LIKE 'photo_url'")->fetch(PDO::FETCH_ASSOC);
    if ($colCheck && stripos($colCheck['Type'] ?? '', 'varchar') !== false) {
        $db->exec("ALTER TABLE reviews MODIFY COLUMN photo_url MEDIUMTEXT NULL");
    }
} catch (Exception $e) {
    // Query diabaikan jika alter sudah selesai atau tidak ada izin
}

try {
    $db->beginTransaction();

    // Pastikan status pesanan ditandai 'completed'
    if ($request['status'] !== 'completed') {
        $stmtUpdStatus = $db->prepare("UPDATE service_requests SET status = 'completed', progress_step = 'completed' WHERE id = ?");
        $stmtUpdStatus->execute([$request_id]);
    }

    // Cek apakah sudah ada ulasan sebelumnya
    $stmtCheckReview = $db->prepare("SELECT id, photo_url FROM reviews WHERE request_id = ? LIMIT 1");
    $stmtCheckReview->execute([$request_id]);
    $existingReview = $stmtCheckReview->fetch();

    $review_id = 0;

    // Eksekusi Simpan dengan Self-Healing Fallback
    try {
        if ($existingReview) {
            if ($photo_url) {
                $stmtReview = $db->prepare("
                    UPDATE reviews 
                    SET rating = ?, comment = ?, photo_url = ?, tags = ?, created_at = NOW() 
                    WHERE id = ?
                ");
                $stmtReview->execute([$rating, $comment, $photo_url, $tags, $existingReview['id']]);
            } else {
                $stmtReview = $db->prepare("
                    UPDATE reviews 
                    SET rating = ?, comment = ?, tags = ?, created_at = NOW() 
                    WHERE id = ?
                ");
                $stmtReview->execute([$rating, $comment, $tags, $existingReview['id']]);
            }
            $review_id = (int)$existingReview['id'];
        } else {
            $stmtReview = $db->prepare("
                INSERT INTO reviews (request_id, user_id, provider_id, rating, comment, photo_url, tags, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtReview->execute([$request_id, $authorized_user_id, $provider_id, $rating, $comment, $photo_url, $tags]);
            $review_id = (int)$db->lastInsertId();
        }
    } catch (PDOException $pdoEx) {
        // Jika kolom masih berukuran kecil (SQLSTATE 22001: Data too long), alter tabel dan coba kembali
        if ($photo_url && (str_contains($pdoEx->getMessage(), '22001') || str_contains($pdoEx->getMessage(), 'too long'))) {
            try {
                $db->exec("ALTER TABLE reviews MODIFY COLUMN photo_url MEDIUMTEXT NULL");
                if ($existingReview) {
                    $stmtRetry = $db->prepare("UPDATE reviews SET rating = ?, comment = ?, photo_url = ?, tags = ?, created_at = NOW() WHERE id = ?");
                    $stmtRetry->execute([$rating, $comment, $photo_url, $tags, $existingReview['id']]);
                    $review_id = (int)$existingReview['id'];
                } else {
                    $stmtRetry = $db->prepare("INSERT INTO reviews (request_id, user_id, provider_id, rating, comment, photo_url, tags, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmtRetry->execute([$request_id, $authorized_user_id, $provider_id, $rating, $comment, $photo_url, $tags]);
                    $review_id = (int)$db->lastInsertId();
                }
            } catch (Exception $retryErr) {
                // Fallback terakhir: Simpan ulasan tanpa foto agar penilaian bintang pelanggan tidak hilang
                if ($existingReview) {
                    $stmtNoPhoto = $db->prepare("UPDATE reviews SET rating = ?, comment = ?, tags = ?, created_at = NOW() WHERE id = ?");
                    $stmtNoPhoto->execute([$rating, $comment, $tags, $existingReview['id']]);
                    $review_id = (int)$existingReview['id'];
                } else {
                    $stmtNoPhoto = $db->prepare("INSERT INTO reviews (request_id, user_id, provider_id, rating, comment, tags, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmtNoPhoto->execute([$request_id, $authorized_user_id, $provider_id, $rating, $comment, $tags]);
                    $review_id = (int)$db->lastInsertId();
                }
            }
        } else {
            throw $pdoEx;
        }
    }

    // Catat ke timeline aktivitas pesanan
    add_order_timeline_event(
        $request_id,
        'completed',
        '⭐ Ulasan Diberikan (' . $rating . ' Bintang)',
        $authorized_user_name . ' memberikan rating ' . $rating . ' bintang' . ($photo_url ? ' beserta foto hasil pengerjaan' : '') . '.',
        'customer'
    );

    // Hitung ulang rata-rata rating & ulasan mitra
    $stmtCalc = $db->prepare("
        SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating
        FROM reviews
        WHERE provider_id = ?
    ");
    $stmtCalc->execute([$provider_id]);
    $stats = $stmtCalc->fetch();

    $new_reviews_count = (int)($stats['total_reviews'] ?? 0);
    $new_rating_avg = round((float)($stats['avg_rating'] ?? 5.0), 2);

    // Hitung order selesai
    $stmtJobs = $db->prepare("
        SELECT COUNT(*) FROM service_requests 
        WHERE (provider_id = ? OR id IN (SELECT request_id FROM service_request_responses WHERE provider_id = ? AND status = 'accepted'))
        AND status = 'completed'
    ");
    $stmtJobs->execute([$provider_id, $provider_id]);
    $completed_jobs = (int)$stmtJobs->fetchColumn();

    // Update di tabel service_providers
    $stmtUpdProv = $db->prepare("
        UPDATE service_providers 
        SET rating_avg = ?, reviews_count = ?, completed_jobs = ?
        WHERE id = ?
    ");
    $stmtUpdProv->execute([$new_rating_avg, $new_reviews_count, $completed_jobs, $provider_id]);

    // Kirim notifikasi ke Mitra
    if ($provider_user_id > 0) {
        $notifTitle = "Ulasan Baru ({$rating} Bintang) dari Pelanggan!";
        $notifMsg = $authorized_user_name . " memberikan rating {$rating} bintang" . (!empty($comment) ? ": \"{$comment}\"" : ".");
        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmtNotif->execute([$provider_user_id, $notifTitle, $notifMsg, '/provider/index.php']);
    }

    $db->commit();

    echo json_encode([
        'success'        => true,
        'message'        => "Terima kasih! Ulasan bintang {$rating} Anda untuk {$provider_name} berhasil disimpan.",
        'rating'         => $rating,
        'comment'        => $comment,
        'photo_url'      => $photo_url,
        'tags'           => $tags,
        'provider_name'  => $provider_name,
        'new_rating_avg' => $new_rating_avg,
        'reviews_count'  => $new_reviews_count
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan ulasan: ' . $e->getMessage()
    ]);
}

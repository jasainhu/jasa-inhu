<?php
/**
 * API: Pengiriman Ulasan & Penilaian Bintang (Review & Rating)
 * JASA INHU
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

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk ke akun Anda terlebih dahulu.']);
    exit;
}

$user = current_user();
$db = get_db();

if (!validate_csrf()) {
    echo json_encode(['success' => false, 'message' => 'Sesi keamanan berakhir. Silakan muat ulang halaman.']);
    exit;
}

$request_id = (int)($_POST['request_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

// Ambil tags kesan layanan
$raw_tags = $_POST['tags'] ?? '';
$tags = '';
if (is_array($raw_tags)) {
    $tags = implode(', ', array_filter(array_map('trim', $raw_tags)));
} elseif (is_string($raw_tags)) {
    $tags = trim($raw_tags);
}

// Handle upload foto bukti hasil kerja (opsional)
$photo_url = null;
if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);

    if (in_array($mime, $allowed_types) && $_FILES['photo']['size'] <= 5 * 1024 * 1024) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'review_' . $request_id . '_' . time() . '.' . strtolower($ext);
        $uploadDir = __DIR__ . '/../uploads/reviews';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $dest = $uploadDir . '/' . $filename;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
            $photo_url = 'uploads/reviews/' . $filename;
        }
    }
}

if ($request_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID pesanan tidak valid.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Mohon pilih rating bintang antara 1 sampai 5.']);
    exit;
}

try {
    // 1. Ambil data pesanan jasa dan pastikan milik user ini
    $stmtReq = $db->prepare("
        SELECT sr.*, sp.id as resolved_provider_id, sp.user_id as provider_user_id, sp.business_name
        FROM service_requests sr
        LEFT JOIN service_providers sp ON sr.provider_id = sp.id
        WHERE sr.id = ? AND sr.user_id = ?
        LIMIT 1
    ");
    $stmtReq->execute([$request_id, $user['id']]);
    $request = $stmtReq->fetch();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Pesanan jasa tidak ditemukan atau bukan milik Anda.']);
        exit;
    }

    // Tentukan provider_id (jika dari lelang terbuka, ambil dari tawaran yang diterima)
    $provider_id = (int)($request['resolved_provider_id'] ?? 0);
    $provider_user_id = (int)($request['provider_user_id'] ?? 0);
    $provider_name = $request['business_name'] ?? 'Penyedia Jasa';

    if ($provider_id <= 0) {
        $stmtResp = $db->prepare("
            SELECT srr.provider_id, sp.user_id as provider_user_id, sp.business_name 
            FROM service_request_responses srr
            JOIN service_providers sp ON srr.provider_id = sp.id
            WHERE srr.request_id = ? AND srr.status = 'accepted'
            LIMIT 1
        ");
        $stmtResp->execute([$request_id]);
        $accepted = $stmtResp->fetch();
        if ($accepted) {
            $provider_id = (int)$accepted['provider_id'];
            $provider_user_id = (int)$accepted['provider_user_id'];
            $provider_name = $accepted['business_name'];
        }
    }

    if ($provider_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mitra penyedia jasa untuk pesanan ini belum ditentukan.']);
        exit;
    }

    $db->beginTransaction();

    // 2. Pastikan status pesanan ditandai 'completed'
    if ($request['status'] !== 'completed') {
        $stmtUpdStatus = $db->prepare("UPDATE service_requests SET status = 'completed', progress_step = 'completed' WHERE id = ?");
        $stmtUpdStatus->execute([$request_id]);
    }

    // 3. Cek apakah sudah pernah mengulas pesanan ini
    $stmtCheckReview = $db->prepare("SELECT id, photo_url FROM reviews WHERE request_id = ? LIMIT 1");
    $stmtCheckReview->execute([$request_id]);
    $existingReview = $stmtCheckReview->fetch();

    if ($existingReview) {
        // Update review yang sudah ada
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
        $review_id = $existingReview['id'];
    } else {
        // Insert review baru
        $stmtReview = $db->prepare("
            INSERT INTO reviews (request_id, user_id, provider_id, rating, comment, photo_url, tags, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtReview->execute([$request_id, $user['id'], $provider_id, $rating, $comment, $photo_url, $tags]);
        $review_id = (int)$db->lastInsertId();
    }

    // Catat ke timeline aktivitas pesanan
    add_order_timeline_event(
        $request_id,
        'completed',
        '⭐ Ulasan Diberikan (' . $rating . ' Bintang)',
        'Pelanggan memberikan rating ' . $rating . ' bintang' . ($photo_url ? ' dengan bukti foto hasil pengerjaan' : '') . '.',
        'customer'
    );

    // 4. Hitung ulang rata-rata rating & jumlah ulasan mitra
    $stmtCalc = $db->prepare("
        SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating
        FROM reviews
        WHERE provider_id = ?
    ");
    $stmtCalc->execute([$provider_id]);
    $stats = $stmtCalc->fetch();

    $new_reviews_count = (int)($stats['total_reviews'] ?? 0);
    $new_rating_avg = round((float)($stats['avg_rating'] ?? 5.0), 2);

    // Hitung order selesai dari service_requests
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

    // 5. Kirim Notifikasi ke Akun Mitra
    if ($provider_user_id > 0) {
        $notifTitle = "Ulasan Baru ({$rating} Bintang) dari Pelanggan!";
        $notifMsg = $user['name'] . " memberi Anda rating {$rating} bintang" . (!empty($comment) ? ": \"{$comment}\"" : ".");
        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmtNotif->execute([$provider_user_id, $notifTitle, $notifMsg, '/provider/index.php']);
    }

    $db->commit();

    echo json_encode([
        'success'       => true,
        'message'       => "Terima kasih! Ulasan bintang {$rating} Anda untuk {$provider_name} berhasil disimpan.",
        'rating'        => $rating,
        'comment'       => $comment,
        'provider_name' => $provider_name,
        'new_rating_avg'=> $new_rating_avg,
        'reviews_count' => $new_reviews_count
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

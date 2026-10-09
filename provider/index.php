<?php
/**
 * Dashboard Penyedia Jasa JASA INHU
 */

$page_title = 'Dashboard Penyedia Jasa';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('penyedia');

$user = current_user();
$db = get_db();
$error = '';

// Ambil data detail profil usaha penyedia
$stmtProv = $db->prepare("
    SELECT sp.*, sc.name as category_name, sc.icon as category_icon, d.name as district_name
    FROM service_providers sp
    JOIN service_categories sc ON sp.primary_category_id = sc.id
    LEFT JOIN districts d ON sp.district_id = d.id
    WHERE sp.user_id = ?
    LIMIT 1
");
$stmtProv->execute([$user['id']]);
$provider = $stmtProv->fetch();
$lead_fee_amount = (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE);

// Handle aksi-aksi pesanan oleh penyedia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan muat ulang.';
    } else {
        $action = $_POST['action'];

        if ($action === 'submit_offer') {
            $request_id = (int)$_POST['request_id'];
            $offer_price = (float)$_POST['offer_price'];
            $est_duration = trim($_POST['estimated_duration'] ?? '');
            $message = trim($_POST['message'] ?? '');

            if ($offer_price <= 0 || empty($message)) {
                $error = 'Nominal tawaran dan pesan penjelasan wajib diisi.';
            } else {
                try {
                    // Cek apakah sudah pernah kirim tawaran
                    $stmtCheck = $db->prepare("SELECT id FROM service_request_responses WHERE request_id = ? AND provider_id = ?");
                    $stmtCheck->execute([$request_id, $provider['id']]);
                    if ($stmtCheck->fetch()) {
                        $error = 'Anda sudah pernah mengirimkan penawaran untuk permintaan ini.';
                    } else {
                        $stmtIns = $db->prepare("
                            INSERT INTO service_request_responses 
                            (request_id, provider_id, offer_price, estimated_duration, message, status, created_at)
                            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                        ");
                        $stmtIns->execute([$request_id, $provider['id'], $offer_price, $est_duration, $message]);
                        set_flash('success', 'Penawaran Anda berhasil dikirim ke pelanggan!');
                        redirect('/provider/index.php');
                    }
                } catch (Exception $e) {
                    $error = 'Gagal mengirim penawaran: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'accept_direct_order') {
            $request_id = (int)$_POST['request_id'];
            try {
                // Pastikan pesanan langsung memang untuk mitra ini dan status masih 'open'
                $stmtReq = $db->prepare("
                    SELECT sr.*, u.name as customer_name, u.phone as customer_phone 
                    FROM service_requests sr 
                    JOIN users u ON sr.user_id = u.id 
                    WHERE sr.id = ? AND sr.provider_id = ? AND sr.status = 'open' 
                    LIMIT 1
                ");
                $stmtReq->execute([$request_id, $provider['id']]);
                $req = $stmtReq->fetch();

                if (!$req) {
                    $error = 'Pesanan tidak ditemukan atau sudah diproses.';
                } else {
                    // Cek dan potong saldo deposit (Biaya Kontak / Lead Fee)
                    $leadResult = deduct_provider_lead_fee(
                        (int)$provider['id'],
                        $request_id,
                        $lead_fee_amount,
                        "Biaya Kontak Pesanan Langsung #" . $request_id . " (" . $req['title'] . ")"
                    );

                    if (!$leadResult['success']) {
                        $error = $leadResult['message'];
                    } else {
                        $db->beginTransaction();

                        // Cek respon jika ada
                        $stmtResp = $db->prepare("SELECT id FROM service_request_responses WHERE request_id = ? AND provider_id = ? LIMIT 1");
                        $stmtResp->execute([$request_id, $provider['id']]);
                        $existingResp = $stmtResp->fetch();

                        if ($existingResp) {
                            $stmtUpdResp = $db->prepare("UPDATE service_request_responses SET status = 'accepted' WHERE id = ?");
                            $stmtUpdResp->execute([$existingResp['id']]);
                        } else {
                            $stmtInsResp = $db->prepare("
                                INSERT INTO service_request_responses 
                                (request_id, provider_id, offer_price, estimated_duration, message, status, created_at)
                                VALUES (?, ?, ?, 'Sesuai kesepakatan langsung', 'Pesanan langsung disetujui mitra', 'accepted', NOW())
                            ");
                            $stmtInsResp->execute([$request_id, $provider['id'], $req['budget'] ?: 0]);
                        }

                        // Update status pesanan jadi in_progress dan progress_step accepted
                        $stmtUpdReq = $db->prepare("UPDATE service_requests SET status = 'in_progress', progress_step = 'accepted' WHERE id = ?");
                        $stmtUpdReq->execute([$request_id]);

                        // Catat ke timeline pelacak aktivitas pesanan
                        add_order_timeline_event(
                            $request_id,
                            'accepted',
                            '🤝 Pesanan Diterima Mitra',
                            'Mitra ' . ($provider['business_name'] ?? 'Penyedia Jasa') . ' menyetujui pesanan Anda dan siap memulai.',
                            'provider'
                        );

                        // Kirim notifikasi ke pelanggan
                        $notifTitle = "Pesanan Langsung Diterima Mitra!";
                        $notifMsg = "Mitra " . ($provider['business_name'] ?? 'Penyedia Jasa') . " telah menyetujui pesanan Anda: \"" . $req['title'] . "\" dan mulai pengerjaan.";
                        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmtNotif->execute([$req['user_id'], $notifTitle, $notifMsg, '/user/requests.php?id=' . $request_id]);

                        // Kirim notifikasi WA otomatis
                        if (!empty($req['customer_phone'])) {
                            $waMsg = build_order_wa_template('order_accepted', [
                                'title'         => $req['title'],
                                'customer_name' => $req['customer_name'] ?? 'Pelanggan',
                                'provider_name' => $provider['business_name'] ?? 'Mitra Jasa',
                                'request_id'    => $request_id
                            ]);
                            send_wa_notification($req['customer_phone'], $waMsg, 'order_accepted', $request_id, $req['customer_name'] ?? null);
                        }

                        $db->commit();

                        $feeDeductMsg = ($lead_fee_amount > 0) ? 'Biaya kontak Rp ' . number_format($lead_fee_amount, 0, ',', '.') . ' telah dipotong dari saldo deposit Anda.' : 'Biaya kontak GRATIS (Promo Rp 0)!';
                        set_flash('success', 'Pesanan berhasil diterima! ' . $feeDeductMsg . ' Sisa saldo: Rp ' . number_format($leadResult['balance_after'], 0, ',', '.') . '. Silakan hubungi pelanggan.');
                        redirect('/provider/index.php');
                    }
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Gagal memproses pesanan: ' . $e->getMessage();
            }
        } elseif ($action === 'reject_direct_order') {
            $request_id = (int)$_POST['request_id'];
            $reason = trim($_POST['reject_reason'] ?? 'Mitra sedang berhalangan');

            try {
                $stmtReq = $db->prepare("SELECT * FROM service_requests WHERE id = ? AND provider_id = ? AND status = 'open' LIMIT 1");
                $stmtReq->execute([$request_id, $provider['id']]);
                $req = $stmtReq->fetch();

                if (!$req) {
                    $error = 'Pesanan tidak ditemukan atau sudah diproses.';
                } else {
                    $db->beginTransaction();

                    $stmtUpdReq = $db->prepare("UPDATE service_requests SET status = 'cancelled', progress_step = 'cancelled' WHERE id = ?");
                    $stmtUpdReq->execute([$request_id]);

                    add_order_timeline_event(
                        $request_id,
                        'cancelled',
                        '❌ Pesanan Ditolak Mitra',
                        'Mitra berhalangan atau tidak dapat melayani pesanan saat ini. Alasan: ' . $reason,
                        'provider'
                    );

                    $notifTitle = "Pesanan Ditolak Mitra";
                    $notifMsg = "Mitra " . ($provider['business_name'] ?? 'Penyedia Jasa') . " tidak dapat menerima pesanan \"" . $req['title'] . "\". Alasan: " . $reason;
                    $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmtNotif->execute([$req['user_id'], $notifTitle, $notifMsg, '/user/requests.php?id=' . $request_id]);

                    $db->commit();
                    set_flash('success', 'Pesanan langsung telah ditolak.');
                    redirect('/provider/index.php');
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Gagal menolak pesanan: ' . $e->getMessage();
            }
        } elseif ($action === 'update_order_step') {
            $request_id = (int)$_POST['request_id'];
            $next_step = trim($_POST['step'] ?? '');

            try {
                $stmtCheck = $db->prepare("
                    SELECT sr.*, u.name as customer_name, u.phone as customer_phone 
                    FROM service_requests sr
                    JOIN users u ON sr.user_id = u.id
                    WHERE sr.id = ? 
                      AND (sr.provider_id = ? OR sr.id IN (SELECT request_id FROM service_request_responses WHERE provider_id = ? AND status = 'accepted'))
                      AND sr.status = 'in_progress'
                    LIMIT 1
                ");
                $stmtCheck->execute([$request_id, $provider['id'], $provider['id']]);
                $req = $stmtCheck->fetch();

                if (!$req) {
                    $error = 'Pesanan tidak ditemukan atau belum aktif.';
                } else {
                    if ($next_step === 'on_the_way') {
                        $stmtUpd = $db->prepare("UPDATE service_requests SET progress_step = 'on_the_way' WHERE id = ?");
                        $stmtUpd->execute([$request_id]);

                        add_order_timeline_event(
                            $request_id,
                            'on_the_way',
                            '🛵 Mitra Sedang Menuju Lokasi',
                            'Mitra ' . ($provider['business_name'] ?? 'Penyedia Jasa') . ' sedang dalam perjalanan menuju lokasi Anda.',
                            'provider'
                        );

                        $notifTitle = "Mitra Sedang Menuju Lokasi Anda!";
                        $notifMsg = "Mitra " . ($provider['business_name'] ?? 'Penyedia Jasa') . " sedang menuju lokasi pengerjaan \"" . $req['title'] . "\". Harap bersiap di tempat.";
                        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmtNotif->execute([$req['user_id'], $notifTitle, $notifMsg, '/user/requests.php?id=' . $request_id]);

                        // Kirim notifikasi WhatsApp otomatis
                        if (!empty($req['customer_phone'])) {
                            $waMsg = build_order_wa_template('on_the_way', [
                                'title'         => $req['title'],
                                'customer_name' => $req['customer_name'] ?? 'Pelanggan',
                                'provider_name' => $provider['business_name'] ?? 'Mitra Jasa',
                                'request_id'    => $request_id
                            ]);
                            send_wa_notification($req['customer_phone'], $waMsg, 'on_the_way', $request_id, $req['customer_name'] ?? null);
                        }

                        set_flash('success', 'Status diperbarui: Anda sedang menuju ke lokasi pelanggan.');
                        redirect('/provider/index.php');
                    } elseif ($next_step === 'working') {
                        $stmtUpd = $db->prepare("UPDATE service_requests SET progress_step = 'working' WHERE id = ?");
                        $stmtUpd->execute([$request_id]);

                        add_order_timeline_event(
                            $request_id,
                            'working',
                            '🔧 Pengerjaan Jasa Dimulai',
                            'Mitra ' . ($provider['business_name'] ?? 'Penyedia Jasa') . ' telah tiba di lokasi dan mulai melakukan pengerjaan.',
                            'provider'
                        );

                        $notifTitle = "Pengerjaan Jasa Dimulai!";
                        $notifMsg = "Mitra " . ($provider['business_name'] ?? 'Penyedia Jasa') . " telah tiba dan mulai mengerjakan pesanan Anda: \"" . $req['title'] . "\".";
                        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmtNotif->execute([$req['user_id'], $notifTitle, $notifMsg, '/user/requests.php?id=' . $request_id]);

                        // Kirim notifikasi WhatsApp otomatis
                        if (!empty($req['customer_phone'])) {
                            $waMsg = build_order_wa_template('working', [
                                'title'         => $req['title'],
                                'customer_name' => $req['customer_name'] ?? 'Pelanggan',
                                'provider_name' => $provider['business_name'] ?? 'Mitra Jasa',
                                'request_id'    => $request_id
                            ]);
                            send_wa_notification($req['customer_phone'], $waMsg, 'working', $request_id, $req['customer_name'] ?? null);
                        }

                        set_flash('success', 'Status diperbarui: Pengerjaan jasa telah dimulai.');
                        redirect('/provider/index.php');
                    } else {
                        $error = 'Tahapan status tidak valid.';
                    }
                }
            } catch (Exception $e) {
                $error = 'Gagal memperbarui tahapan: ' . $e->getMessage();
            }
        } elseif ($action === 'complete_order') {
            $request_id = (int)$_POST['request_id'];
            $final_price = (!empty($_POST['final_price']) && (float)$_POST['final_price'] > 0) ? (float)$_POST['final_price'] : null;
            $cost_breakdown = trim($_POST['cost_breakdown'] ?? '');

            try {
                // Pastikan pesanan dikerjakan mitra ini dan status 'in_progress'
                $stmtCheck = $db->prepare("
                    SELECT sr.*, u.name as customer_name, u.phone as customer_phone 
                    FROM service_requests sr
                    JOIN users u ON sr.user_id = u.id
                    WHERE sr.id = ? 
                      AND (sr.provider_id = ? OR sr.id IN (SELECT request_id FROM service_request_responses WHERE provider_id = ? AND status = 'accepted'))
                      AND sr.status = 'in_progress'
                    LIMIT 1
                ");
                $stmtCheck->execute([$request_id, $provider['id'], $provider['id']]);
                $req = $stmtCheck->fetch();

                if (!$req) {
                    $error = 'Pesanan tidak ditemukan atau belum dalam status pengerjaan.';
                } else {
                    $db->beginTransaction();

                    // Update status request jadi completed dan simpan tagihan akhir jika ada
                    if ($final_price !== null) {
                        $stmtUpd = $db->prepare("UPDATE service_requests SET status = 'completed', progress_step = 'completed', final_price = ?, cost_breakdown = ? WHERE id = ?");
                        $stmtUpd->execute([$final_price, $cost_breakdown, $request_id]);
                    } else {
                        $stmtUpd = $db->prepare("UPDATE service_requests SET status = 'completed', progress_step = 'completed' WHERE id = ?");
                        $stmtUpd->execute([$request_id]);
                    }

                    // Catat timeline selesai
                    add_order_timeline_event(
                        $request_id,
                        'completed',
                        '✅ Pekerjaan Selesai & Kwitansi Diterbitkan',
                        'Mitra telah menyelesaikan pekerjaan ' . ($final_price ? 'dengan total biaya ' . format_rupiah($final_price) : 'sesuai kesepakatan') . '. Kwitansi resmi telah diterbitkan.',
                        'provider'
                    );

                    // Update completed_jobs provider
                    $stmtJobs = $db->prepare("UPDATE service_providers SET completed_jobs = completed_jobs + 1 WHERE id = ?");
                    $stmtJobs->execute([$provider['id']]);

                    // Kirim notifikasi ke pemesan
                    $notifTitle = "Pekerjaan Selesai! Beri Ulasan & Bintang";
                    $notifMsg = "Mitra " . ($provider['business_name'] ?? 'Penyedia Jasa') . " telah menyelesaikan pekerjaan \"" . $req['title'] . "\". Silakan beri penilaian dan ulasan kepuasan Anda.";
                    $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmtNotif->execute([$req['user_id'], $notifTitle, $notifMsg, '/user/requests.php?id=' . $request_id]);

                    // Kirim notifikasi WhatsApp otomatis
                    if (!empty($req['customer_phone'])) {
                        $waMsg = build_order_wa_template('completed', [
                            'title'         => $req['title'],
                            'customer_name' => $req['customer_name'] ?? 'Pelanggan',
                            'provider_name' => $provider['business_name'] ?? 'Mitra Jasa',
                            'request_id'    => $request_id,
                            'final_price'   => $final_price
                        ]);
                        send_wa_notification($req['customer_phone'], $waMsg, 'completed', $request_id, $req['customer_name'] ?? null);
                    }

                    $db->commit();

                    set_flash('success', 'Pekerjaan berhasil ditandai selesai! Kwitansi resmi dan rincian biaya telah diperbarui.');
                    redirect('/provider/index.php');
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Gagal menyelesaikan pesanan: ' . $e->getMessage();
            }
        } elseif ($action === 'reply_review') {
            $review_id = (int)$_POST['review_id'];
            $reply_text = trim($_POST['reply_text'] ?? '');

            if (empty($reply_text)) {
                $error = 'Tanggapan ulasan wajib diisi.';
            } else {
                try {
                    $stmtChkRev = $db->prepare("
                        SELECT r.*, sr.user_id as customer_id, sr.title as req_title 
                        FROM reviews r 
                        JOIN service_requests sr ON r.request_id = sr.id 
                        WHERE r.id = ? AND r.provider_id = ?
                        LIMIT 1
                    ");
                    $stmtChkRev->execute([$review_id, $provider['id']]);
                    $rev = $stmtChkRev->fetch();

                    if (!$rev) {
                        $error = 'Ulasan tidak ditemukan atau bukan pesanan Anda.';
                    } else {
                        $stmtUpdRev = $db->prepare("UPDATE reviews SET reply_text = ?, replied_at = NOW() WHERE id = ?");
                        $stmtUpdRev->execute([$reply_text, $review_id]);

                        // Kirim notifikasi ke pemesan
                        $notifTitle = "Mitra Menanggapi Ulasan Anda!";
                        $notifMsg = ($provider['business_name'] ?? 'Mitra') . " memberikan tanggapan resmi: \"" . mb_strimwidth($reply_text, 0, 75, '...') . "\"";
                        $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmtNotif->execute([$rev['customer_id'], $notifTitle, $notifMsg, '/user/requests.php?id=' . $rev['request_id']]);

                        set_flash('success', 'Tanggapan resmi Anda berhasil disimpan!');
                        redirect('/provider/index.php');
                    }
                } catch (Exception $e) {
                    $error = 'Gagal menyimpan tanggapan: ' . $e->getMessage();
                }
            }
        }
    }
}

// 1. Statistik Penyedia Jasa
$stmtStats = $db->prepare("
    SELECT 
        COUNT(*) as total_offers,
        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_offers
    FROM service_request_responses
    WHERE provider_id = ?
");
$stmtStats->execute([$provider['id'] ?? 0]);
$prov_stats = $stmtStats->fetch();

$stmtPort = $db->prepare("SELECT COUNT(*) FROM provider_portfolios WHERE provider_id = ?");
$stmtPort->execute([$provider['id'] ?? 0]);
$total_portfolios = (int)$stmtPort->fetchColumn();

// 2. Pekerjaan Sedang Berjalan (Active In-Progress Jobs)
$stmtActive = $db->prepare("
    SELECT sr.*, u.name as customer_name, u.phone as customer_phone, sc.name as category_name, sc.icon as category_icon, d.name as district_name,
           (CASE WHEN sr.provider_id = ? THEN 'direct' ELSE 'bidding' END) as order_origin,
           srr.offer_price as agreed_price
    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id
    JOIN service_categories sc ON sr.category_id = sc.id
    JOIN districts d ON sr.district_id = d.id
    LEFT JOIN service_request_responses srr ON (srr.request_id = sr.id AND srr.provider_id = ? AND srr.status = 'accepted')
    WHERE (sr.provider_id = ? OR sr.id IN (SELECT request_id FROM service_request_responses WHERE provider_id = ? AND status = 'accepted'))
      AND sr.status = 'in_progress'
    ORDER BY sr.created_at DESC
");
$stmtActive->execute([$provider['id'] ?? 0, $provider['id'] ?? 0, $provider['id'] ?? 0, $provider['id'] ?? 0]);
$active_jobs = $stmtActive->fetchAll();

// 3. Pesanan Masuk Langsung (Direct Orders Khusus untuk Penyedia Ini, status: open)
$stmtDirect = $db->prepare("
    SELECT sr.*, u.name as customer_name, u.phone as customer_phone, sc.name as category_name, sc.icon as category_icon, d.name as district_name,
           (SELECT id FROM service_request_responses srr WHERE srr.request_id = sr.id AND srr.provider_id = ?) as my_response_id
    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id
    JOIN service_categories sc ON sr.category_id = sc.id
    JOIN districts d ON sr.district_id = d.id
    WHERE sr.provider_id = ? AND sr.status = 'open'
    ORDER BY sr.created_at DESC
");
$stmtDirect->execute([$provider['id'] ?? 0, $provider['id'] ?? 0]);
$direct_orders = $stmtDirect->fetchAll();

// 4. Permintaan Jasa Terbuka Umum di Sekitar Inhu (Lelang)
$stmtLeads = $db->prepare("
    SELECT sr.*, u.name as customer_name, sc.name as category_name, sc.icon as category_icon, d.name as district_name,
           (SELECT COUNT(*) FROM service_request_responses srr WHERE srr.request_id = sr.id) as total_offers,
           (SELECT id FROM service_request_responses srr WHERE srr.request_id = sr.id AND srr.provider_id = ?) as my_response_id
    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id
    JOIN service_categories sc ON sr.category_id = sc.id
    JOIN districts d ON sr.district_id = d.id
    WHERE sr.status = 'open' AND sr.provider_id IS NULL
    ORDER BY (sr.category_id = ?) DESC, sr.created_at DESC
    LIMIT 6
");
$stmtLeads->execute([$provider['id'] ?? 0, $provider['primary_category_id'] ?? 0]);
$open_leads = $stmtLeads->fetchAll();

// 5. Riwayat Respon / Tawaran Saya
$stmtMyOffers = $db->prepare("
    SELECT srr.*, sr.title as request_title, sr.status as request_status, d.name as district_name
    FROM service_request_responses srr
    JOIN service_requests sr ON srr.request_id = sr.id
    JOIN districts d ON sr.district_id = d.id
    WHERE srr.provider_id = ?
    ORDER BY srr.created_at DESC
    LIMIT 5
");
$stmtMyOffers->execute([$provider['id'] ?? 0]);
$my_offers = $stmtMyOffers->fetchAll();

// 6. Ulasan & Testimoni Pelanggan Terbaru
$stmtReviews = $db->prepare("
    SELECT r.*, u.name as customer_name, sr.title as request_title
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN service_requests sr ON r.request_id = sr.id
    WHERE r.provider_id = ?
    ORDER BY r.created_at DESC
    LIMIT 6
");
$stmtReviews->execute([$provider['id'] ?? 0]);
$recent_reviews = $stmtReviews->fetchAll();

// 7. Obrolan & Pesan Pelanggan Terbaru (In-App Chat)
$stmtConvProvider = $db->prepare("
    SELECT c.*,
           CASE WHEN c.user_one_id = ? THEN u2.name ELSE u1.name END as customer_name,
           (SELECT COUNT(*) FROM chat_messages cm WHERE cm.conversation_id = c.id AND cm.receiver_id = ? AND cm.is_read = 0) as unread_count
    FROM conversations c
    JOIN users u1 ON c.user_one_id = u1.id
    JOIN users u2 ON c.user_two_id = u2.id
    WHERE c.user_one_id = ? OR c.user_two_id = ?
    ORDER BY c.last_message_at DESC
    LIMIT 5
");
$stmtConvProvider->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$recent_chat_conversations = $stmtConvProvider->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header Welcome Card -->
    <div class="card border-0 shadow-sm p-3 p-md-4 mb-4 text-white" style="background: linear-gradient(135deg, var(--dark) 0%, #115e59 100%); border-radius: 18px;">
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge text-bg-success fw-bold">Mitra Penyedia Jasa Inhu</span>
                    <?php if (!empty($provider['is_verified'])): ?>
                        <span class="badge bg-success-subtle text-white border border-light small">
                            <i class="fa-solid fa-circle-check"></i> Terverifikasi
                        </span>
                    <?php else: ?>
                        <span class="badge text-bg-warning text-dark small">Menunggu Verifikasi Admin</span>
                    <?php endif; ?>
                </div>
                <h2 class="fw-bold mb-1 fs-4 fs-md-2"><?= e($provider['business_name'] ?? $user['name']) ?></h2>
                <p class="text-light opacity-90 mb-0 small">
                    <i class="fa-solid <?= e($provider['category_icon'] ?? 'fa-wrench') ?> text-warning me-1"></i>
                    Bidang: <strong><?= e($provider['category_name'] ?? 'Jasa Umum') ?></strong> &bull;
                    <i class="fa-solid fa-map-pin text-danger ms-2 me-1"></i> Wilayah: <?= e($provider['district_name'] ?: 'Kab. Indragiri Hulu') ?>
                </p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex flex-wrap gap-2 justify-content-md-end">
                <a href="<?= BASE_URL ?>/provider/wallet.php" class="btn btn-warning text-dark fw-bold py-2 px-3 shadow-sm flex-fill flex-md-grow-0" style="border-radius: 10px;" title="Kelola Saldo Dompet & Biaya Kontak">
                    <i class="fa-solid fa-wallet me-1"></i> Saldo: Rp <?= number_format((float)($provider['wallet_balance'] ?? 0), 0, ',', '.') ?>
                </a>
                <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $provider['id'] ?>" target="_blank" class="btn btn-outline-light fw-bold py-2 px-3 shadow-sm flex-fill flex-md-grow-0" style="border-radius: 10px;" title="Lihat Tampilan Toko/Profil Publik Anda yang Dilihat Pelanggan">
                    <i class="fa-solid fa-store me-1"></i> Profil Publik ↗
                </a>
                <a href="<?= BASE_URL ?>/provider/portfolio.php" class="btn btn-outline-light fw-bold py-2 px-3 shadow-sm flex-fill flex-md-grow-0" style="border-radius: 10px;">
                    <i class="fa-solid fa-camera me-1"></i> Portofolio (<?= $total_portfolios ?>)
                </a>
                <a href="<?= BASE_URL ?>/provider/leads.php" class="btn btn-light fw-bold py-2 px-3 shadow-sm flex-fill flex-md-grow-0" style="color: var(--primary-dark); border-radius: 10px;">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Cari Pekerjaan
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger mb-4"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Stats Row (Grid 2x2 di Mobile, 4 Kolom di Desktop) -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card border-top border-4 border-warning h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold text-muted text-truncate">Saldo Dompet</span>
                    <a href="<?= BASE_URL ?>/provider/wallet.php" class="stat-icon text-decoration-none" style="background: #fef3c7; color: #d97706; width: 34px; height: 34px;" title="Isi Saldo">
                        <i class="fa-solid fa-wallet" style="font-size: 0.85rem;"></i>
                    </a>
                </div>
                <div class="h5 h4-md fw-bold mb-1 text-teal text-truncate">Rp <?= number_format((float)($provider['wallet_balance'] ?? 0), 0, ',', '.') ?></div>
                <div class="small text-muted d-flex justify-content-between align-items-center" style="font-size: 0.72rem;">
                    <span>~<?= $lead_fee_amount > 0 ? floor(((float)($provider['wallet_balance'] ?? 0)) / $lead_fee_amount) : '∞' ?> order</span>
                    <a href="<?= BASE_URL ?>/provider/wallet.php" class="text-teal fw-bold text-decoration-none">+ Top-Up</a>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold text-muted text-truncate">Disepakati</span>
                    <div class="stat-icon" style="background: #dcfce7; color: #16a34a; width: 34px; height: 34px;">
                        <i class="fa-solid fa-handshake" style="font-size: 0.85rem;"></i>
                    </div>
                </div>
                <div class="h5 h4-md fw-bold mb-1"><?= (int)($prov_stats['accepted_offers'] ?? 0) ?></div>
                <div class="small text-muted text-truncate" style="font-size: 0.72rem;">Pelanggan sepakat</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold text-muted text-truncate">Pekerjaan Selesai</span>
                    <div class="stat-icon" style="background: #e0f2fe; color: #0284c7; width: 34px; height: 34px;">
                        <i class="fa-solid fa-check-circle" style="font-size: 0.85rem;"></i>
                    </div>
                </div>
                <div class="h5 h4-md fw-bold mb-1"><?= (int)($provider['completed_jobs'] ?? 0) ?></div>
                <div class="small text-muted text-truncate" style="font-size: 0.72rem;">Proyek terselesaikan</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100 p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold text-muted text-truncate">Rating & Ulasan</span>
                    <div class="stat-icon" style="background: #fdf2f8; color: #db2777; width: 34px; height: 34px;">
                        <i class="fa-solid fa-star" style="font-size: 0.85rem;"></i>
                    </div>
                </div>
                <div class="h5 h4-md fw-bold mb-1 d-flex align-items-center gap-1">
                    <span><?= number_format((float)($provider['rating_avg'] ?? 5.0), 1) ?></span>
                    <i class="fa-solid fa-star text-warning" style="font-size: 0.8rem;"></i>
                </div>
                <div class="small text-muted text-truncate" style="font-size: 0.72rem;"><?= (int)($provider['reviews_count'] ?? 0) ?> ulasan</div>
            </div>
        </div>
    </div>

    <!-- Bagian Pekerjaan Sedang Berjalan (Active Jobs) -->
    <?php if (!empty($active_jobs)): ?>
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; overflow: hidden; border-left: 5px solid var(--primary) !important;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge text-bg-primary fw-bold px-2.5 py-1.5"><i class="fa-solid fa-spinner fa-spin me-1"></i> Sedang Dikerjakan</span>
                    <h5 class="fw-bold mb-0 text-dark">Pekerjaan Sedang Berjalan (<?= count($active_jobs) ?>)</h5>
                </div>
                <span class="small text-muted d-none d-md-inline">Selesaikan dan konfirmasi untuk mendapatkan ulasan kepuasan dari pelanggan</span>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($active_jobs as $job): 
                        $job_step = $job['progress_step'] ?? 'accepted';
                    ?>
                        <div class="p-3 rounded-3 border bg-white shadow-xs">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex flex-wrap align-items-center gap-1">
                                    <span class="badge text-bg-light border text-primary small">
                                        <i class="fa-solid <?= e($job['category_icon']) ?> me-1"></i> <?= e($job['category_name']) ?>
                                    </span>
                                    <span class="badge text-bg-light border text-muted small">
                                        <i class="fa-solid fa-location-dot text-danger me-1"></i> Kec. <?= e($job['district_name']) ?>
                                    </span>
                                    <?php if ($job['order_origin'] === 'direct'): ?>
                                        <span class="badge text-bg-warning text-dark small">
                                            <i class="fa-solid fa-handshake-angle me-1"></i> Pesanan Langsung
                                        </span>
                                    <?php else: ?>
                                        <span class="badge text-bg-info text-white small">
                                            <i class="fa-solid fa-gavel me-1"></i> Lelang Terbuka
                                        </span>
                                    <?php endif; ?>

                                    <!-- Status Progres Terkini -->
                                    <?php if ($job_step === 'on_the_way'): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                                            <i class="fa-solid fa-motorcycle me-1"></i> OTW Lokasi
                                        </span>
                                    <?php elseif ($job_step === 'working'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle small">
                                            <i class="fa-solid fa-screwdriver-wrench me-1"></i> Sedang Dikerjakan
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small">
                                            <i class="fa-solid fa-check me-1"></i> Pesanan Diterima
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="fw-bold text-teal fs-6">
                                    <?= format_rupiah($job['agreed_price'] ?: $job['budget']) ?>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-1"><?= e($job['title']) ?></h5>
                            <p class="small text-muted mb-3" style="white-space: pre-line;"><?= e($job['description']) ?></p>

                            <!-- Mini Step Bar -->
                            <div class="p-2 mb-3 rounded-2 bg-light border d-flex align-items-center justify-content-between small flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted fw-semibold">Tahap:</span>
                                    <?php if ($job_step === 'created' || $job_step === 'accepted'): ?>
                                        <span class="fw-bold text-primary"><i class="fa-solid fa-circle-dot me-1"></i>1. Diterima</span> &rarr; <span class="text-muted">2. OTW Lokasi</span> &rarr; <span class="text-muted">3. Pengerjaan</span> &rarr; <span class="text-muted">4. Selesai</span>
                                    <?php elseif ($job_step === 'on_the_way'): ?>
                                        <span class="text-success"><i class="fa-solid fa-check"></i> Diterima</span> &rarr; <span class="fw-bold text-primary"><i class="fa-solid fa-motorcycle me-1"></i>2. Menuju Lokasi</span> &rarr; <span class="text-muted">3. Pengerjaan</span> &rarr; <span class="text-muted">4. Selesai</span>
                                    <?php elseif ($job_step === 'working'): ?>
                                        <span class="text-success"><i class="fa-solid fa-check"></i> Diterima</span> &rarr; <span class="text-success"><i class="fa-solid fa-check"></i> Tiba di Lokasi</span> &rarr; <span class="fw-bold text-warning-emphasis"><i class="fa-solid fa-screwdriver-wrench me-1"></i>3. Dikerjakan</span> &rarr; <span class="text-muted">4. Selesai</span>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-muted" data-bs-toggle="collapse" data-bs-target="#timelineJob<?= $job['id'] ?>">
                                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat Status <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.7rem;"></i>
                                </button>
                            </div>

                            <!-- Collapse Riwayat Jejak Status -->
                            <div class="collapse mb-3" id="timelineJob<?= $job['id'] ?>">
                                <div class="p-3 bg-white border rounded-3 small">
                                    <div class="fw-bold text-dark mb-2"><i class="fa-solid fa-list-check me-1 text-teal"></i> Jejak Aktivitas Pesanan:</div>
                                    <?php 
                                        $job_timeline = get_order_timeline((int)$job['id']);
                                        if (empty($job_timeline)):
                                    ?>
                                        <span class="text-muted">Belum ada riwayat aktivitas tercatat.</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-column gap-2">
                                            <?php foreach ($job_timeline as $ev): ?>
                                                <div class="d-flex justify-content-between align-items-start border-bottom pb-1">
                                                    <div>
                                                        <span class="fw-semibold text-dark"><?= e($ev['title']) ?></span>
                                                        <?php if (!empty($ev['note'])): ?>
                                                            <div class="text-muted" style="font-size: 0.8rem;"><?= e($ev['note']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="text-muted" style="font-size: 0.75rem; white-space: nowrap;"><?= date('H:i, d M', strtotime($ev['created_at'])) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 border-top gap-2 small">
                                <span class="text-muted">
                                    Pelanggan: <strong><?= e($job['customer_name']) ?></strong> &bull; Dimulai: <?= format_date($job['created_at'], true) ?>
                                </span>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <a href="<?= BASE_URL ?>/chat.php?to_user=<?= $job['user_id'] ?>" class="btn btn-sm btn-outline-teal fw-semibold">
                                        <i class="fa-solid fa-comments me-1"></i> Chat
                                    </a>
                                    <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $job['customer_phone'])) ?>?text=Halo%20<?= urlencode($job['customer_name']) ?>,%20kami%20dari%20<?= urlencode($provider['business_name']) ?>%20sedang%20mengerjakan%20jasa:%20'<?= urlencode($job['title']) ?>'" target="_blank" class="btn btn-sm btn-success">
                                        <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp
                                    </a>

                                    <!-- Tombol Transisi Status Interaktif -->
                                    <?php if ($job_step === 'created' || $job_step === 'accepted'): ?>
                                        <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="d-inline"
                                              data-confirm="Pelanggan akan menerima notifikasi bahwa Anda sedang dalam perjalanan meluncur ke lokasi."
                                              data-confirm-title="Meluncur ke Lokasi Pelanggan?"
                                              data-confirm-btn="Ya, Sedang Meluncur"
                                              data-confirm-cancel="Batal"
                                              data-confirm-type="info">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_order_step">
                                            <input type="hidden" name="request_id" value="<?= $job['id'] ?>">
                                            <input type="hidden" name="step" value="on_the_way">
                                            <button type="submit" class="btn btn-sm btn-primary fw-semibold shadow-xs">
                                                <i class="fa-solid fa-motorcycle me-1"></i> Sedang Menuju Lokasi
                                            </button>
                                        </form>
                                    <?php elseif ($job_step === 'on_the_way'): ?>
                                        <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="d-inline"
                                              data-confirm="Status pesanan akan diubah menjadi 'Sedang Dikerjakan' di aplikasi pelanggan."
                                              data-confirm-title="Mulai Pengerjaan Jasa Sekarang?"
                                              data-confirm-btn="Ya, Mulai Kerjakan"
                                              data-confirm-cancel="Batal"
                                              data-confirm-type="warning">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_order_step">
                                            <input type="hidden" name="request_id" value="<?= $job['id'] ?>">
                                            <input type="hidden" name="step" value="working">
                                            <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs">
                                                <i class="fa-solid fa-screwdriver-wrench me-1"></i> Mulai Pengerjaan
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <button type="button" class="btn btn-sm <?= ($job_step === 'working') ? 'btn-success text-white' : 'btn-outline-success' ?> fw-bold" onclick="openCompleteOrderModal(<?= $job['id'] ?>, '<?= e(addslashes($job['title'])) ?>', '<?= e(addslashes($job['customer_name'])) ?>', <?= (float)($job['agreed_price'] ?: $job['budget']) ?>)">
                                        <i class="fa-solid fa-circle-check me-1"></i> Tandai Selesai & Terbitkan Tagihan
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bagian Pesanan Langsung Masuk (Jika Ada) -->
    <?php if (!empty($direct_orders)): ?>
        <div class="card border-warning shadow-sm mb-4" style="border-width: 2px;">
            <div class="card-header bg-warning bg-opacity-10 py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge text-bg-warning text-dark"><i class="fa-solid fa-star me-1"></i> Pesanan Langsung</span>
                    <h5 class="fw-bold mb-0 text-dark">Pesanan Khusus Masuk untuk Anda (<?= count($direct_orders) ?>)</h5>
                </div>
                <span class="small text-muted">Pelanggan menunjuk usaha Anda secara spesifik</span>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($direct_orders as $do): ?>
                        <div class="p-3 rounded-3 border bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge text-bg-light border text-primary small">
                                        <i class="fa-solid <?= e($do['category_icon']) ?> me-1"></i> <?= e($do['category_name']) ?>
                                    </span>
                                    <span class="badge text-bg-light border text-muted small ms-1">
                                        <i class="fa-solid fa-location-dot text-danger me-1"></i> Kec. <?= e($do['district_name']) ?>
                                    </span>
                                    <span class="badge text-bg-warning text-dark small ms-1">
                                        <i class="fa-solid fa-handshake-angle me-1"></i> Ditunjuk Langsung
                                    </span>
                                </div>
                                <div class="fw-bold text-teal fs-6">
                                    <?= format_rupiah($do['budget']) ?>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-1"><?= e($do['title']) ?></h5>
                            <p class="small text-muted mb-3" style="white-space: pre-line;"><?= e($do['description']) ?></p>

                            <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 border-top gap-2 small">
                                <span class="text-muted">
                                    Pemesan: <strong><?= e($do['customer_name']) ?></strong> &bull; Dibuat: <?= format_date($do['created_at'], true) ?>
                                </span>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="<?= BASE_URL ?>/chat.php?to_user=<?= $do['user_id'] ?>" class="btn btn-sm btn-outline-teal fw-semibold">
                                        <i class="fa-solid fa-comments me-1"></i> Chat
                                    </a>
                                    <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $do['customer_phone'])) ?>?text=Halo%20<?= urlencode($do['customer_name']) ?>,%20kami%20dari%20<?= urlencode($provider['business_name']) ?>%20telah%20menerima%20pesanan%20jasa%20Anda:%20'<?= urlencode($do['title']) ?>'" target="_blank" class="btn btn-sm btn-success">
                                        <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp
                                    </a>
                                    <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="d-inline"
                                          data-confirm="<?= $lead_fee_amount > 0 ? "Saldo deposit Anda akan dipotong Rp " . number_format($lead_fee_amount, 0, ',', '.') . " sebagai biaya kontak." : "Biaya kontak saat ini GRATIS (Promo Rp 0)!" ?>"
                                          data-confirm-title="Terima Pesanan Langsung Ini?"
                                          data-confirm-btn="Ya, Terima & Kerjakan"
                                          data-confirm-cancel="Batal"
                                          data-confirm-type="success">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="accept_direct_order">
                                        <input type="hidden" name="request_id" value="<?= $do['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary-custom fw-semibold">
                                            <i class="fa-solid fa-circle-check me-1"></i> Terima & Mulai Kerjakan
                                            <span class="badge bg-white text-dark ms-1" style="font-size: 0.65rem;">
                                                <?= $lead_fee_amount > 0 ? '-Rp ' . number_format($lead_fee_amount, 0, ',', '.') : 'GRATIS' ?>
                                            </span>
                                        </button>
                                    </form>
                                    <?php if ($do['my_response_id']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3">
                                            <i class="fa-solid fa-check"></i> Tawaran Terkirim
                                        </span>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#directOfferForm<?= $do['id'] ?>">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Ajukan Biaya Khusus
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rejectDirectForm<?= $do['id'] ?>">
                                        <i class="fa-solid fa-xmark me-1"></i> Tolak
                                    </button>
                                </div>
                            </div>

                            <!-- Form Tolak Pesanan Langsung -->
                            <div class="collapse mt-3 pt-3 border-top" id="rejectDirectForm<?= $do['id'] ?>">
                                <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="bg-danger-subtle p-3 rounded-3 border border-danger-subtle">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reject_direct_order">
                                    <input type="hidden" name="request_id" value="<?= $do['id'] ?>">
                                    <h6 class="fw-bold small mb-2 text-danger"><i class="fa-solid fa-circle-exclamation me-1"></i> Alasan Penolakan Pesanan</h6>
                                    <div class="mb-2">
                                        <input type="text" name="reject_reason" class="form-control form-control-sm" placeholder="Contoh: Jadwal kerja penuh hari ini / Lokasi di luar jangkauan" required>
                                    </div>
                                    <div class="text-end">
                                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#rejectDirectForm<?= $do['id'] ?>">Batal</button>
                                        <button type="submit" class="btn btn-sm btn-danger fw-semibold">Konfirmasi Tolak Pesanan</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Form Respon Pesanan Langsung -->
                            <?php if (!$do['my_response_id']): ?>
                                <div class="collapse mt-3 pt-3 border-top" id="directOfferForm<?= $do['id'] ?>">
                                    <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="bg-light p-3 rounded-3 border">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="submit_offer">
                                        <input type="hidden" name="request_id" value="<?= $do['id'] ?>">
                                        <h6 class="fw-bold small mb-2 text-dark">Kirimkan Konfirmasi Biaya & Kesiapan Anda</h6>
                                        <div class="row g-2 mb-2">
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Biaya / Harga Jasa yang Disepakati (Rp) <span class="text-danger">*</span></label>
                                                <input type="number" name="offer_price" class="form-control form-control-sm" placeholder="Contoh: 150000" value="<?= $do['budget'] ?: '' ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Estimasi Waktu Pengerjaan / Kedatangan</label>
                                                <input type="text" name="estimated_duration" class="form-control form-control-sm" placeholder="Contoh: Datang hari ini jam 14.00 WIB" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold">Pesan Konfirmasi untuk Pemesan <span class="text-danger">*</span></label>
                                                <textarea name="message" rows="2" class="form-control form-control-sm" placeholder="Contoh: Halo Pak, kami siap meluncur ke lokasi sesuai jadwal yang diminta..." required></textarea>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#directOfferForm<?= $do['id'] ?>">Batal</button>
                                            <button type="submit" class="btn btn-sm btn-primary-custom">Kirim Konfirmasi ke Pemesan</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Peluang Pekerjaan Terbaru di Inhu (Umum / Lelang) -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-bullhorn text-primary me-2"></i> Peluang Pekerjaan Terbuka di Inhu
                    </h5>
                    <a href="<?= BASE_URL ?>/provider/leads.php" class="small text-teal text-decoration-none fw-semibold">
                        Lihat Semua &rarr;
                    </a>
                </div>
                <div class="card-body p-3">
                    <?php if (!empty($open_leads)): ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($open_leads as $lead): ?>
                                <div class="p-3 rounded-3 border bg-white shadow-sm">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="badge text-bg-light border text-primary small">
                                                <i class="fa-solid <?= e($lead['category_icon']) ?> me-1"></i> <?= e($lead['category_name']) ?>
                                            </span>
                                            <span class="badge text-bg-light border text-muted small ms-1">
                                                <i class="fa-solid fa-location-dot text-danger me-1"></i> Kec. <?= e($lead['district_name']) ?>
                                            </span>
                                            <?php if ($lead['urgency'] === 'urgent'): ?>
                                                <span class="badge text-bg-danger small ms-1">Mendesak</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fw-bold text-teal">
                                            <?= format_rupiah($lead['budget']) ?>
                                        </div>
                                    </div>

                                    <h6 class="fw-bold text-dark mb-1"><?= e($lead['title']) ?></h6>
                                    <p class="small text-muted mb-2"><?= e($lead['description']) ?></p>

                                    <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 border-top small">
                                        <span class="text-muted">
                                            Pemohon: <strong><?= e($lead['customer_name']) ?></strong> &bull; <?= $lead['total_offers'] ?> Tawaran Masuk
                                        </span>
                                        <div>
                                            <?php if ($lead['my_response_id']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="fa-solid fa-check"></i> Sudah Menawar
                                                </span>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-primary-custom py-1 px-3" data-bs-toggle="collapse" data-bs-target="#offerForm<?= $lead['id'] ?>">
                                                    <i class="fa-solid fa-paper-plane me-1"></i> Kirim Tawaran
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Collapsible Form Kirim Tawaran -->
                                    <?php if (!$lead['my_response_id']): ?>
                                        <div class="collapse mt-3 pt-3 border-top" id="offerForm<?= $lead['id'] ?>">
                                            <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="bg-light p-3 rounded-3 border">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="submit_offer">
                                                <input type="hidden" name="request_id" value="<?= $lead['id'] ?>">
                                                
                                                <h6 class="fw-bold small mb-2 text-dark">Kirimkan Penawaran Anda ke Pelanggan</h6>
                                                <div class="row g-2 mb-2">
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-semibold">Harga Penawaran Anda (Rp) <span class="text-danger">*</span></label>
                                                        <input type="number" name="offer_price" class="form-control form-control-sm" placeholder="Contoh: 150000" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small fw-semibold">Estimasi Waktu Pengerjaan</label>
                                                        <input type="text" name="estimated_duration" class="form-control form-control-sm" placeholder="Contoh: 2 jam / 1 hari kerja">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small fw-semibold">Pesan / Penjelasan untuk Pelanggan <span class="text-danger">*</span></label>
                                                        <textarea name="message" rows="2" class="form-control form-control-sm" placeholder="Perkenalkan diri dan jelaskan kesiapan Anda mengerjakan..." required></textarea>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#offerForm<?= $lead['id'] ?>">Batal</button>
                                                    <button type="submit" class="btn btn-sm btn-primary-custom">Kirim Penawaran</button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-clipboard-check fs-2 text-muted mb-2 d-block"></i>
                            Saat ini belum ada permintaan jasa terbuka baru di Inhu.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar: Dompet Saldo, Obrolan, Ulasan & Tawaran Saya -->
        <div class="col-lg-4">
            <!-- Widget Dompet Deposit Mitra -->
            <div class="card border-0 shadow-sm mb-4 text-white" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%); border-radius: 14px;">
                <div class="card-body p-3.5">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-uppercase fw-bold text-white-50"><i class="fa-solid fa-wallet text-warning me-1"></i> Dompet Saldo Deposit</span>
                        <a href="<?= BASE_URL ?>/provider/wallet.php" class="badge bg-warning text-dark text-decoration-none py-1.5 px-2.5 fw-bold shadow-xs">
                            + Isi Saldo
                        </a>
                    </div>
                    <div class="h3 fw-bold text-white mb-0.5">
                        Rp <?= number_format((float)($provider['wallet_balance'] ?? 0), 0, ',', '.') ?>
                    </div>
                    <div class="text-white-50 mb-1" style="font-size: 0.7rem;">
                        <i class="fa-solid fa-lock text-warning me-1"></i> Kredit Kuota Pesanan (Non-Tunai)
                    </div>
                    <div class="small text-white-50 d-flex justify-content-between align-items-center border-top border-white border-opacity-15 pt-2 mt-2">
                        <span>Kuota: <strong class="text-white">~<?= $lead_fee_amount > 0 ? floor(((float)($provider['wallet_balance'] ?? 0)) / $lead_fee_amount) : '∞' ?> Order</strong></span>
                        <span>Biaya: <strong class="text-white"><?= $lead_fee_amount > 0 ? 'Rp ' . number_format($lead_fee_amount, 0, ',', '.') : 'GRATIS' ?>/order</strong></span>
                    </div>
                </div>
            </div>

            <!-- Card Obrolan & Pesan Pelanggan -->
            <div class="card border shadow-sm mb-4" style="border-radius: 14px; overflow: hidden;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="fw-bold small text-dark d-flex align-items-center gap-1.5">
                        <i class="fa-solid fa-comments text-teal"></i>
                        <span>OBROLAN PELANGGAN</span>
                    </div>
                    <a href="<?= BASE_URL ?>/chat.php" class="small text-teal text-decoration-none fw-semibold">
                        Buka Semua ↗
                    </a>
                </div>
                <div class="list-group list-group-flush small">
                    <?php if (!empty($recent_chat_conversations)): ?>
                        <?php foreach ($recent_chat_conversations as $conv): ?>
                            <?php $unread = (int)$conv['unread_count']; ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="fw-bold text-dark d-flex align-items-center gap-1">
                                        <i class="fa-solid fa-circle-user text-teal"></i> <?= e($conv['customer_name']) ?>
                                    </span>
                                    <?php if ($unread > 0): ?>
                                        <span class="badge bg-teal rounded-pill" style="font-size: 0.65rem;">
                                            <?= $unread ?> Pesan Baru
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small" style="font-size: 0.68rem;">
                                            <?= !empty($conv['last_message_at']) ? date('H:i', strtotime($conv['last_message_at'])) : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted mb-2 small text-truncate" style="line-height: 1.4; max-width: 250px;">
                                    <?= e($conv['last_message'] ?: 'Mulai obrolan baru...') ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center pt-1 border-top">
                                    <span class="text-muted" style="font-size: 0.7rem;">
                                        <i class="fa-regular fa-clock me-1"></i> <?= format_date($conv['last_message_at'], true) ?>
                                    </span>
                                    <a href="<?= BASE_URL ?>/chat.php?c=<?= $conv['id'] ?>" class="btn btn-sm btn-outline-teal py-0.5 px-2 fw-semibold" style="font-size: 0.72rem;">
                                        <i class="fa-solid fa-reply me-1"></i> Buka Chat ↗
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted small">
                            <i class="fa-regular fa-comments fs-3 text-muted opacity-50 mb-1 d-block"></i>
                            Belum ada pesan obrolan masuk dari pelanggan.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Ulasan & Testimoni Pelanggan -->
            <div class="card border shadow-sm mb-4" style="border-radius: 14px; overflow: hidden;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="fw-bold small text-dark d-flex align-items-center gap-1">
                        <i class="fa-solid fa-star text-warning"></i>
                        <span>ULASAN & TESTIMONI</span>
                    </div>
                    <span class="badge bg-warning bg-opacity-20 text-dark fw-bold">
                        <?= number_format((float)($provider['rating_avg'] ?? 5.0), 1) ?> / 5.0
                    </span>
                </div>
                <div class="card-body p-3 border-bottom bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-center p-2 rounded-3 bg-white border" style="min-width: 80px;">
                            <div class="h3 fw-bold text-dark mb-0"><?= number_format((float)($provider['rating_avg'] ?? 5.0), 1) ?></div>
                            <div class="text-warning small" style="font-size: 0.72rem;">
                                <?php
                                $fullStars = floor((float)($provider['rating_avg'] ?? 5.0));
                                for ($s = 1; $s <= 5; $s++) {
                                    if ($s <= $fullStars) echo '<i class="fa-solid fa-star"></i>';
                                    else echo '<i class="fa-regular fa-star text-muted"></i>';
                                }
                                ?>
                            </div>
                        </div>
                        <div>
                            <div class="fw-semibold text-dark small">Kepuasan Warga Inhu</div>
                            <div class="small text-muted" style="font-size: 0.8rem;">
                                Berdasarkan <strong><?= (int)($provider['reviews_count'] ?? 0) ?> ulasan</strong> & <?= (int)($provider['completed_jobs'] ?? 0) ?> pesanan selesai.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="list-group list-group-flush small">
                    <?php if (!empty($recent_reviews)): ?>
                        <?php foreach ($recent_reviews as $rev): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                        <i class="fa-solid fa-circle-user text-teal"></i> <?= e($rev['customer_name']) ?>
                                    </div>
                                    <span class="text-muted" style="font-size: 0.72rem;"><?= format_date($rev['created_at']) ?></span>
                                </div>
                                <div class="text-warning mb-1" style="font-size: 0.78rem;">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="fa-<?= $s <= (int)$rev['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                    <?php endfor; ?>
                                    <span class="text-dark fw-bold ms-1" style="font-size: 0.75rem;"><?= (int)$rev['rating'] ?>.0</span>
                                </div>

                                <!-- Tag Kesan Pelanggan -->
                                <?php if (!empty($rev['tags'])): 
                                    $pTags = array_filter(array_map('trim', explode(',', $rev['tags'])));
                                ?>
                                    <div class="d-flex flex-wrap gap-1 my-1.5">
                                        <?php foreach ($pTags as $tag): ?>
                                            <span class="badge bg-teal-subtle text-teal border border-teal-subtle py-0.5 px-2 rounded-pill" style="font-size: 0.65rem;">
                                                <?= e($tag) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Foto Bukti Hasil Kerja jika diunggah -->
                                <?php if (!empty($rev['photo_url'])): ?>
                                    <div class="my-1.5">
                                        <a href="<?= BASE_URL ?>/<?= e($rev['photo_url']) ?>" target="_blank" class="d-inline-block border rounded-2 overflow-hidden shadow-xs">
                                            <img src="<?= BASE_URL ?>/<?= e($rev['photo_url']) ?>" alt="Bukti Kerja" style="width: 100px; height: 65px; object-fit: cover;">
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($rev['comment'])): ?>
                                    <p class="text-secondary mb-1 fst-italic" style="font-size: 0.82rem; line-height: 1.4;">
                                        "<?= e($rev['comment']) ?>"
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($rev['request_title'])): ?>
                                    <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.72rem;">
                                        <span class="text-truncate"><i class="fa-solid fa-tag text-teal me-1"></i> Jasa: <?= e($rev['request_title']) ?></span>
                                        <?php if (!empty($rev['request_id'])): ?>
                                            <a href="<?= BASE_URL ?>/invoice.php?id=<?= $rev['request_id'] ?>" target="_blank" class="text-teal fw-semibold text-decoration-none text-nowrap ms-2">
                                                <i class="fa-solid fa-receipt me-1"></i> Kwitansi ↗
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Respon / Tanggapan Resmi Mitra -->
                                <?php if (!empty($rev['reply_text'])): ?>
                                    <div class="mt-2 p-2 bg-light rounded-2 border-start border-3 border-teal">
                                        <div class="d-flex justify-content-between align-items-center mb-0.5">
                                            <span class="fw-bold text-teal" style="font-size: 0.72rem;"><i class="fa-solid fa-reply me-1"></i> Respon Anda:</span>
                                            <span class="text-muted" style="font-size: 0.65rem;"><?= format_date($rev['replied_at'], true) ?></span>
                                        </div>
                                        <p class="mb-0 text-muted fst-italic" style="font-size: 0.76rem;">"<?= e($rev['reply_text']) ?>"</p>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-2 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-teal py-0.5 px-2 rounded-2" data-bs-toggle="collapse" data-bs-target="#replyReviewForm<?= $rev['id'] ?>" style="font-size: 0.72rem;">
                                            <i class="fa-solid fa-reply me-1"></i> Beri Tanggapan
                                        </button>
                                        <div class="collapse mt-2 text-start" id="replyReviewForm<?= $rev['id'] ?>">
                                            <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="bg-light p-2.5 rounded-2 border">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="reply_review">
                                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                <label class="form-label fw-semibold small mb-1 text-dark">Tulis Tanggapan Resmi Anda:</label>
                                                <textarea name="reply_text" rows="2" class="form-control form-control-sm mb-2" placeholder="Contoh: Terima kasih banyak atas kepercayaannya Pak/Bu..." required></textarea>
                                                <div class="d-flex justify-content-end gap-1.5">
                                                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#replyReviewForm<?= $rev['id'] ?>" style="font-size: 0.75rem;">Batal</button>
                                                    <button type="submit" class="btn btn-sm btn-teal text-white fw-semibold" style="font-size: 0.75rem;">Kirim Tanggapan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fa-regular fa-comment-dots fs-3 text-muted mb-2 d-block opacity-50"></i>
                            Belum ada ulasan dari pelanggan. Selesaikan pesanan jasa untuk mengumpulkan bintang pertama Anda!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar Tawaran Saya -->
            <div class="card border shadow-sm" style="border-radius: 14px; overflow: hidden;">
                <div class="card-header bg-white py-3 fw-bold small text-muted">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> TAWARAN TERAKHIR SAYA
                </div>
                <div class="list-group list-group-flush small">
                    <?php if (!empty($my_offers)): ?>
                        <?php foreach ($my_offers as $mo): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge text-bg-light border text-muted">Kec. <?= e($mo['district_name']) ?></span>
                                    <span class="badge <?= $mo['status'] === 'accepted' ? 'text-bg-success' : 'text-bg-warning' ?>">
                                        <?= strtoupper(e($mo['status'])) ?>
                                    </span>
                                </div>
                                <div class="fw-bold text-dark mb-1"><?= e($mo['request_title']) ?></div>
                                <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.75rem;">
                                    <span class="fw-bold text-teal"><?= format_rupiah($mo['offer_price']) ?></span>
                                    <span><?= format_date($mo['created_at']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">Belum ada penawaran dikirim.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Pekerjaan Selesai & Tagihan Akhir -->
<div class="modal fade" id="completeOrderModal" tabindex="-1" aria-labelledby="completeOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0d9488 0%, #042f2e 100%);">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-check fs-4 text-warning"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="completeOrderModalLabel">Selesaikan Pekerjaan & Tagihan</h5>
                        <span class="text-white-50 small" style="font-size: 0.78rem;">Kwitansi resmi otomatis terbit untuk pelanggan</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="<?= BASE_URL ?>/provider/index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="complete_order">
                <input type="hidden" name="request_id" id="completeRequestId" value="">

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 border mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Pelanggan:</span>
                            <strong class="text-dark" id="completeCustomerName">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Pekerjaan:</span>
                            <strong class="text-teal" id="completeJobTitle">-</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1" for="completeFinalPrice">
                            Total Biaya Akhir yang Dibayar Pelanggan (Rp) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">Rp</span>
                            <input type="number" name="final_price" id="completeFinalPrice" class="form-control fw-bold fs-5 text-teal" required>
                        </div>
                        <span class="text-muted" style="font-size: 0.73rem;">
                            Jika ada pergantian onderdil/suku cadang: ketik nominal total yang disepakati (contoh: <strong>200000</strong>).
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small mb-1" for="completeBreakdown">
                            Rincian Pekerjaan & Suku Cadang (Opsional):
                        </label>
                        <textarea name="cost_breakdown" id="completeBreakdown" rows="2" class="form-control form-control-sm" placeholder="Contoh: Ongkos jasa perbaikan Rp 50.000 + Ganti onderdil spul & busi Rp 150.000"></textarea>
                        <span class="text-muted" style="font-size: 0.72rem;">Rincian ini akan tercetak jelas di lembar kwitansi resmi sebagai bukti garansi.</span>
                    </div>

                    <div class="alert alert-info border-0 p-2.5 rounded-3 mb-0 small" style="font-size: 0.78rem;">
                        <i class="fa-solid fa-circle-info me-1"></i> <strong>Tanpa Potongan Tambahan:</strong> Seluruh uang pembayaran pelanggan adalah 100% hak Anda. Biaya kontak platform tetap flat <?= $lead_fee_amount > 0 ? 'Rp ' . number_format($lead_fee_amount, 0, ',', '.') : 'GRATIS (Rp 0)' ?>.
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 py-2 shadow-xs">
                        <i class="fa-solid fa-circle-check me-1"></i> Selesaikan & Terbitkan Kwitansi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCompleteOrderModal(requestId, title, customerName, defaultPrice) {
    document.getElementById('completeRequestId').value = requestId;
    document.getElementById('completeJobTitle').textContent = title;
    document.getElementById('completeCustomerName').textContent = customerName;
    document.getElementById('completeFinalPrice').value = defaultPrice || '';
    document.getElementById('completeBreakdown').value = '';

    const modalEl = document.getElementById('completeOrderModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

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

// 8. Riwayat Pekerjaan Selesai
$stmtCompleted = $db->prepare("
    SELECT sr.*, u.name as customer_name, u.phone as customer_phone, 
           sc.name as category_name, sc.icon as category_icon, d.name as district_name,
           (CASE WHEN sr.provider_id = ? THEN 'direct' ELSE 'bidding' END) as order_origin,
           srr.offer_price as agreed_price,
           (SELECT rating FROM reviews r WHERE r.request_id = sr.id LIMIT 1) as customer_rating,
           (SELECT comment FROM reviews r WHERE r.request_id = sr.id LIMIT 1) as customer_comment
    FROM service_requests sr
    JOIN users u ON sr.user_id = u.id
    JOIN service_categories sc ON sr.category_id = sc.id
    JOIN districts d ON sr.district_id = d.id
    LEFT JOIN service_request_responses srr ON (srr.request_id = sr.id AND srr.provider_id = ? AND srr.status = 'accepted')
    WHERE (sr.provider_id = ? OR sr.id IN (SELECT request_id FROM service_request_responses WHERE provider_id = ? AND status = 'accepted'))
      AND sr.status = 'completed'
    ORDER BY sr.updated_at DESC
    LIMIT 10
");
$stmtCompleted->execute([$provider['id'] ?? 0, $provider['id'] ?? 0, $provider['id'] ?? 0, $provider['id'] ?? 0]);
$completed_jobs = $stmtCompleted->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>


<style>
/* Modern Provider Dashboard Styling */
.provider-hero-card {
    background: linear-gradient(135deg, #092c28 0%, #0f766e 60%, #115e59 100%);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(15, 118, 110, 0.2);
}
.provider-hero-card::after {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(45, 212, 191, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.provider-stat-badge {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 10px 16px;
    color: #fff;
    transition: transform 0.2s ease, background 0.2s ease;
}
.provider-stat-badge:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}
.modern-nav-pills {
    background: #f1f5f9;
    padding: 6px;
    border-radius: 16px;
    gap: 6px;
}
.modern-nav-pills .nav-link {
    border-radius: 12px;
    padding: 10px 18px;
    font-weight: 600;
    font-size: 0.92rem;
    color: #475569;
    transition: all 0.2s ease;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.modern-nav-pills .nav-link:hover {
    color: #0f766e;
    background: rgba(255, 255, 255, 0.6);
}
.modern-nav-pills .nav-link.active {
    background: #ffffff;
    color: #0f766e;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}
.order-card {
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    background: #fff;
}
.order-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.07);
    border-color: #cbd5e1;
}
.order-card.priority-active {
    border-left: 5px solid #0d9488;
}
.order-card.priority-new {
    border-left: 5px solid #ef4444;
}
.btn-wa-call {
    background-color: #25D366;
    color: #fff;
    font-weight: 600;
    border: none;
    transition: all 0.2s ease;
}
.btn-wa-call:hover {
    background-color: #1eb956;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
}
.stepper-progress {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 15px 0 20px;
}
.stepper-progress::before {
    content: '';
    position: absolute;
    top: 14px;
    left: 20px;
    right: 20px;
    height: 3px;
    background: #e2e8f0;
    z-index: 1;
}
.stepper-step {
    position: relative;
    z-index: 2;
    text-align: center;
    flex: 1;
}
.stepper-dot {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #cbd5e1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
    color: #64748b;
    margin-bottom: 4px;
    transition: all 0.2s ease;
}
.stepper-step.completed .stepper-dot {
    background: #0d9488;
    border-color: #0d9488;
    color: #fff;
}
.stepper-step.active .stepper-dot {
    background: #f59e0b;
    border-color: #f59e0b;
    color: #fff;
    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
}
.stepper-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    display: block;
}
.stepper-step.completed .stepper-label,
.stepper-step.active .stepper-label {
    color: #0f172a;
}
.avatar-circle {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.1rem;
}
</style>

<div class="container py-4">

    <!-- Flash Error / Alert -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3">
            <i class="fa-solid fa-circle-exclamation fs-4"></i>
            <div><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- 1. Modern Hero Header Card -->
    <div class="provider-hero-card p-4 p-md-5 mb-4 text-white">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-emerald-500 text-white fw-bold px-3 py-1.5 rounded-pill" style="background-color: #10b981;">
                        <i class="fa-solid fa-id-badge me-1"></i> Mitra Resmi Inhu
                    </span>
                    <?php if (!empty($provider['is_verified'])): ?>
                        <span class="badge bg-white text-teal fw-bold px-3 py-1.5 rounded-pill shadow-xs" style="color: #0d9488;">
                            <i class="fa-solid fa-circle-check text-success"></i> Terverifikasi
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark fw-bold px-3 py-1.5 rounded-pill">
                            <i class="fa-solid fa-clock-rotate-left"></i> Menunggu Verifikasi
                        </span>
                    <?php endif; ?>
                </div>

                <h1 class="fw-bold fs-2 mb-2 text-white">
                    <?= e($provider['business_name'] ?? $user['name']) ?>
                </h1>

                <p class="text-white-50 mb-3 small">
                    <i class="fa-solid <?= e($provider['category_icon'] ?? 'fa-wrench') ?> text-warning me-1"></i>
                    Bidang: <strong class="text-white"><?= e($provider['category_name'] ?? 'Jasa Umum') ?></strong> &bull;
                    <i class="fa-solid fa-location-dot text-danger ms-2 me-1"></i>
                    Wilayah: <strong class="text-white"><?= e($provider['district_name'] ?: 'Kab. Indragiri Hulu') ?></strong>
                </p>

                <!-- Quick Performance Stats -->
                <div class="d-flex flex-wrap gap-2">
                    <div class="provider-stat-badge">
                        <div class="text-white-50" style="font-size: 0.72rem;">Rating Pelanggan</div>
                        <div class="fw-bold fs-6">
                            ⭐ <?= number_format((float)($provider['rating_avg'] ?? 0), 1) ?>
                            <span class="text-white-50 fw-normal small">(<?= (int)($provider['reviews_count'] ?? 0) ?> ulasan)</span>
                        </div>
                    </div>
                    <div class="provider-stat-badge">
                        <div class="text-white-50" style="font-size: 0.72rem;">Pekerjaan Selesai</div>
                        <div class="fw-bold fs-6">
                            <i class="fa-solid fa-circle-check text-success me-1"></i>
                            <?= (int)($provider['completed_jobs'] ?? 0) ?> Pekerjaan
                        </div>
                    </div>
                    <div class="provider-stat-badge">
                        <div class="text-white-50" style="font-size: 0.72rem;">Portofolio Hasil Kerja</div>
                        <div class="fw-bold fs-6">
                            <i class="fa-solid fa-images text-info me-1"></i>
                            <?= $total_portfolios ?> Foto
                        </div>
                    </div>
                </div>
            </div>

            <!-- Saldo & Quick Links -->
            <div class="col-lg-5 text-lg-end">
                <div class="p-3 bg-white bg-opacity-10 rounded-4 border border-white border-opacity-20 backdrop-blur d-inline-block text-start w-100" style="max-width: 380px;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-white-50 small"><i class="fa-solid fa-wallet me-1"></i> Saldo Dompet Mitra:</span>
                        <a href="<?= BASE_URL ?>/provider/wallet.php" class="badge bg-warning text-dark text-decoration-none fw-bold px-2 py-1 rounded-pill">
                            + Top Up Saldo
                        </a>
                    </div>
                    <div class="fs-3 fw-bold text-white mb-2">
                        Rp <?= number_format((float)($provider['wallet_balance'] ?? 0), 0, ',', '.') ?>
                    </div>
                    <div class="text-white-50 mb-3" style="font-size: 0.72rem;">
                        Biaya kontak flat <?= $lead_fee_amount > 0 ? 'Rp ' . number_format($lead_fee_amount, 0, ',', '.') : 'GRATIS (Rp 0)' ?> hanya dipotong saat Anda menyetujui pesanan baru.
                    </div>
                    <div class="d-flex gap-2 mb-2">
                        <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $provider['id'] ?>" target="_blank" class="btn btn-light btn-sm fw-bold flex-fill rounded-3 text-teal">
                            <i class="fa-solid fa-store me-1"></i> Lihat Toko Publik ↗
                        </a>
                        <a href="<?= BASE_URL ?>/provider/portfolios.php" class="btn btn-outline-light btn-sm fw-bold rounded-3" title="Unggah Hasil Kerja">
                            <i class="fa-solid fa-plus me-1"></i> Portofolio
                        </a>
                    </div>
                    <!-- Sound & Notification Controller Bar -->
                    <div class="pt-2 border-top border-white border-opacity-20 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light text-teal fw-bold flex-fill rounded-3 sound-toggle-btn shadow-2xs" onclick="ProviderNotification.toggleSound()" style="font-size: 0.72rem;">
                            <i class="fa-solid fa-volume-high me-1 text-success"></i> Suara: Aktif
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light fw-bold rounded-3" onclick="ProviderNotification.testSound()" style="font-size: 0.72rem;" title="Uji Coba Bunyi Nada Dering HP">
                            <i class="fa-solid fa-play me-1"></i> Tes Dering
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Banner Notifikasi Lowongan Baru Warga (Jika Ada) -->
    <?php if (!empty($open_leads) && count($open_leads) > 0): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3" style="background: linear-gradient(90deg, #fffbeb 0%, #fef3c7 100%);">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 44px; height: 44px; min-width: 44px;">
                    <i class="fa-solid fa-bullhorn fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Ada <?= count($open_leads) ?> Lowongan Jasa Terbuka dari Warga!</h6>
                    <span class="text-muted small">Pelanggan di Kab. Inhu sedang mencari jasa kategori Anda. Kirim penawaran sekarang untuk dapat orderan!</span>
                </div>
            </div>
            <a href="#pills-leads" onclick="document.getElementById('pills-leads-tab').click();" class="btn btn-dark btn-sm fw-bold px-3 py-2 rounded-pill shadow-xs text-nowrap">
                Lihat Lowongan Warga <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
    <?php endif; ?>

    <!-- 3. Modern Segmented Tab Navigation -->
    <div class="mb-4">
        <ul class="nav nav-pills modern-nav-pills shadow-xs" id="providerOrderTabs" role="tablist">
            <!-- Tab 1: Pesanan Baru -->
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 <?= (!empty($direct_orders) || empty($active_jobs)) ? 'active' : '' ?>" id="pills-new-tab" data-bs-toggle="pill" data-bs-target="#pills-new" type="button" role="tab" aria-controls="pills-new" aria-selected="<?= (!empty($direct_orders) || empty($active_jobs)) ? 'true' : 'false' ?>">
                    <i class="fa-solid fa-inbox text-danger"></i>
                    <span>Pesanan Baru</span>
                    <?php if (count($direct_orders) > 0): ?>
                        <span class="badge bg-danger rounded-pill px-2 py-1"><?= count($direct_orders) ?></span>
                    <?php else: ?>
                        <span class="badge bg-light text-muted rounded-pill px-2 py-1">0</span>
                    <?php endif; ?>
                </button>
            </li>

            <!-- Tab 2: Sedang Dikerjakan -->
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100 <?= (empty($direct_orders) && !empty($active_jobs)) ? 'active' : '' ?>" id="pills-active-tab" data-bs-toggle="pill" data-bs-target="#pills-active" type="button" role="tab" aria-controls="pills-active" aria-selected="<?= (empty($direct_orders) && !empty($active_jobs)) ? 'true' : 'false' ?>">
                    <i class="fa-solid fa-motorcycle text-primary"></i>
                    <span>Sedang Dikerjakan</span>
                    <?php if (count($active_jobs) > 0): ?>
                        <span class="badge bg-primary rounded-pill px-2 py-1"><?= count($active_jobs) ?></span>
                    <?php else: ?>
                        <span class="badge bg-light text-muted rounded-pill px-2 py-1">0</span>
                    <?php endif; ?>
                </button>
            </li>

            <!-- Tab 3: Bursa Lowongan Warga -->
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100" id="pills-leads-tab" data-bs-toggle="pill" data-bs-target="#pills-leads" type="button" role="tab" aria-controls="pills-leads" aria-selected="false">
                    <i class="fa-solid fa-briefcase text-warning"></i>
                    <span>Bursa Lowongan Warga</span>
                    <?php if (count($open_leads) > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill px-2 py-1"><?= count($open_leads) ?></span>
                    <?php endif; ?>
                </button>
            </li>

            <!-- Tab 4: Riwayat Selesai -->
            <li class="nav-item flex-fill" role="presentation">
                <button class="nav-link w-100" id="pills-completed-tab" data-bs-toggle="pill" data-bs-target="#pills-completed" type="button" role="tab" aria-controls="pills-completed" aria-selected="false">
                    <i class="fa-solid fa-circle-check text-success"></i>
                    <span>Riwayat Selesai</span>
                    <span class="badge bg-light text-muted rounded-pill px-2 py-1"><?= count($completed_jobs) ?></span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="providerOrderTabsContent">

        <!-- ========================================== -->
        <!-- TAB 1: PESANAN BARU (DIRECT ORDERS)       -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= (!empty($direct_orders) || empty($active_jobs)) ? 'show active' : '' ?>" id="pills-new" role="tabpanel" aria-labelledby="pills-new-tab">
            <?php if (empty($direct_orders)): ?>
                <!-- Empty State -->
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white my-3">
                    <div class="mx-auto mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center text-muted" style="width: 80px; height: 80px;">
                        <i class="fa-solid fa-inbox fs-1 text-teal opacity-50"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Belum Ada Pesanan Baru Masuk</h5>
                    <p class="text-muted small mx-auto mb-4" style="max-width: 480px;">
                        Pesanan langsung dari warga yang memilih profil toko Anda akan masuk di sini. Anda juga bisa mencari pekerjaan aktif di tab <strong>Bursa Lowongan Warga</strong>.
                    </p>
                    <div>
                        <button type="button" onclick="document.getElementById('pills-leads-tab').click();" class="btn btn-outline-teal fw-bold px-4 py-2 rounded-pill shadow-xs">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Cek Lowongan Terbuka Warga
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($direct_orders as $order): 
                        $custPhoneClean = preg_replace('/[^0-9]/', '', $order['customer_phone'] ?? '');
                        if (str_starts_with($custPhoneClean, '0')) {
                            $custPhoneClean = '62' . substr($custPhoneClean, 1);
                        }
                        $waTextNew = urlencode("Halo Bapak/Ibu " . ($order['customer_name'] ?? 'Pelanggan') . ", saya " . ($provider['business_name'] ?? 'Mitra Jasa') . " dari Aplikasi Jasa Inhu mengenai pesanan jasa: \"" . $order['title'] . "\". Boleh kami konfirmasi lokasi dan jadwalnya?");
                    ?>
                        <div class="col-12">
                            <div class="order-card priority-new shadow-sm p-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 pb-3 border-bottom mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle bg-danger-subtle text-danger">
                                            <?= strtoupper(substr($order['customer_name'] ?? 'P', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <h5 class="fw-bold text-dark mb-0"><?= e($order['customer_name']) ?></h5>
                                                <span class="badge bg-danger text-white rounded-pill small">Pesanan Baru Menunggu Respon</span>
                                            </div>
                                            <div class="text-muted small mt-1">
                                                <i class="fa-solid fa-map-pin text-danger me-1"></i> <?= e($order['district_name'] ?: 'Kab. Inhu') ?> &bull; 
                                                <i class="fa-solid fa-clock me-1 ms-1"></i> Masuk: <?= format_date($order['created_at']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-md-end">
                                        <div class="text-muted small">Anggaran Perkiraan Pelanggan:</div>
                                        <div class="fs-5 fw-bold text-success">
                                            <?= $order['budget'] ? format_rupiah($order['budget']) : '<span class="text-muted fs-6">Sesuai Kesepakatan</span>' ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Job Detail Description -->
                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark mb-1">
                                        <i class="fa-solid <?= e($order['category_icon'] ?? 'fa-wrench') ?> text-teal me-1"></i>
                                        <?= e($order['title']) ?>
                                    </h6>
                                    <p class="text-secondary small mb-2 bg-light p-3 rounded-3 border">
                                        <?= nl2br(e($order['description'])) ?>
                                    </p>
                                    <?php if (!empty($order['address'])): ?>
                                        <div class="small text-muted mb-2">
                                            <i class="fa-solid fa-location-dot text-danger me-1"></i> <strong>Alamat Pelanggan:</strong> <?= e($order['address']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Quick Chat & Actions Bar -->
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pt-2">
                                    <!-- Direct Chat Contacts -->
                                    <div class="d-flex gap-2">
                                        <?php if (!empty($custPhoneClean)): ?>
                                            <a href="https://wa.me/<?= $custPhoneClean ?>?text=<?= $waTextNew ?>" target="_blank" class="btn btn-wa-call btn-sm px-3 py-2 rounded-3" title="Chat WhatsApp Pelanggan">
                                                <i class="fa-brands fa-whatsapp fs-6 me-1"></i> Hubungi WA
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>/chat.php?user_id=<?= $order['user_id'] ?>" class="btn btn-outline-teal btn-sm px-3 py-2 rounded-3" title="Obrolan Pesan di Aplikasi">
                                            <i class="fa-solid fa-comments me-1"></i> Chat Aplikasi
                                        </a>
                                    </div>

                                    <!-- Main Accept / Reject Buttons -->
                                    <div class="d-flex gap-2">
                                        <!-- Reject Form -->
                                        <form method="POST" action="<?= BASE_URL ?>/provider/index.php" onsubmit="return confirm('Yakin ingin menolak pesanan ini?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reject_direct_order">
                                            <input type="hidden" name="request_id" value="<?= $order['id'] ?>">
                                            <input type="hidden" name="reject_reason" value="Mitra sedang ada jadwal pekerjaan lain">
                                            <button type="submit" class="btn btn-outline-danger btn-sm px-3 py-2 rounded-3 fw-bold">
                                                <i class="fa-solid fa-xmark me-1"></i> Tolak
                                            </button>
                                        </form>

                                        <!-- Accept Form -->
                                        <form method="POST" action="<?= BASE_URL ?>/provider/index.php" onsubmit="return confirm('Terima pesanan ini dan mulai pengerjaan? Biaya kontak <?= $lead_fee_amount > 0 ? 'Rp ' . number_format($lead_fee_amount, 0, ',', '.') : 'GRATIS' ?> akan dipotong dari saldo dompet Anda.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="accept_direct_order">
                                            <input type="hidden" name="request_id" value="<?= $order['id'] ?>">
                                            <button type="submit" class="btn btn-success btn-sm px-4 py-2 rounded-3 fw-bold shadow-xs">
                                                <i class="fa-solid fa-check me-1"></i> TERIMA PESANAN SEKARANG
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: SEDANG DIKERJAKAN (ACTIVE JOBS)    -->
        <!-- ========================================== -->
        <div class="tab-pane fade <?= (empty($direct_orders) && !empty($active_jobs)) ? 'show active' : '' ?>" id="pills-active" role="tabpanel" aria-labelledby="pills-active-tab">
            <?php if (empty($active_jobs)): ?>
                <!-- Empty State -->
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white my-3">
                    <div class="mx-auto mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center text-muted" style="width: 80px; height: 80px;">
                        <i class="fa-solid fa-motorcycle fs-1 text-primary opacity-50"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Tidak Ada Pekerjaan yang Sedang Dikerjakan</h5>
                    <p class="text-muted small mx-auto mb-4" style="max-width: 480px;">
                        Saat Anda menerima pesanan dari warga, Anda dapat memantau status perjalanan (OTW), mulai pengerjaan, dan menerbitkan kwitansi akhir di sini.
                    </p>
                    <div>
                        <button type="button" onclick="document.getElementById('pills-new-tab').click();" class="btn btn-teal fw-bold px-4 py-2 rounded-pill shadow-xs">
                            <i class="fa-solid fa-inbox me-1"></i> Lihat Pesanan Masuk
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($active_jobs as $job): 
                        $step = $job['progress_step'] ?? 'accepted';
                        $custPhoneClean = preg_replace('/[^0-9]/', '', $job['customer_phone'] ?? '');
                        if (str_starts_with($custPhoneClean, '0')) {
                            $custPhoneClean = '62' . substr($custPhoneClean, 1);
                        }
                        $waTextActive = urlencode("Halo Bapak/Ibu " . ($job['customer_name'] ?? 'Pelanggan') . ", saya " . ($provider['business_name'] ?? 'Mitra Jasa') . " dari Aplikasi Jasa Inhu yang sedang mengerjakan pesanan: \"" . $job['title'] . "\".");
                        $finalEstimate = (int)($job['agreed_price'] ?: $job['budget'] ?: 0);
                    ?>
                        <div class="col-12">
                            <div class="order-card priority-active shadow-sm p-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 pb-3 border-bottom mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-circle bg-primary-subtle text-primary">
                                            <?= strtoupper(substr($job['customer_name'] ?? 'P', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <h5 class="fw-bold text-dark mb-0"><?= e($job['customer_name']) ?></h5>
                                                <?php if ($step === 'accepted'): ?>
                                                    <span class="badge bg-primary text-white rounded-pill small">Pesanan Diterima - Siap Berangkat</span>
                                                <?php elseif ($step === 'on_the_way'): ?>
                                                    <span class="badge bg-info text-white rounded-pill small">🛵 Mitra Sedang Menuju Lokasi (OTW)</span>
                                                <?php elseif ($step === 'working'): ?>
                                                    <span class="badge bg-warning text-dark rounded-pill small">🔧 Sedang Melakukan Pengerjaan</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small mt-1">
                                                <i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($job['district_name'] ?: 'Kab. Inhu') ?> &bull; 
                                                <i class="fa-solid fa-clock me-1 ms-1"></i> Mulai: <?= format_date($job['created_at']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-md-end">
                                        <div class="text-muted small">Biaya Kesepakatan:</div>
                                        <div class="fs-5 fw-bold text-teal">
                                            <?= $finalEstimate > 0 ? format_rupiah($finalEstimate) : 'Sesuai Tagihan Akhir' ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Interactive Modern Stepper -->
                                <div class="stepper-progress">
                                    <!-- Step 1: Diterima -->
                                    <div class="stepper-step completed">
                                        <div class="stepper-dot"><i class="fa-solid fa-check"></i></div>
                                        <span class="stepper-label">1. Diterima</span>
                                    </div>
                                    <!-- Step 2: OTW -->
                                    <div class="stepper-step <?= in_array($step, ['on_the_way', 'working', 'completed']) ? 'completed' : ($step === 'accepted' ? 'active' : '') ?>">
                                        <div class="stepper-dot">
                                            <?= in_array($step, ['on_the_way', 'working', 'completed']) ? '<i class="fa-solid fa-check"></i>' : '2' ?>
                                        </div>
                                        <span class="stepper-label">2. Menuju Lokasi</span>
                                    </div>
                                    <!-- Step 3: Bekerja -->
                                    <div class="stepper-step <?= in_array($step, ['working', 'completed']) ? 'completed' : ($step === 'on_the_way' ? 'active' : '') ?>">
                                        <div class="stepper-dot">
                                            <?= in_array($step, ['working', 'completed']) ? '<i class="fa-solid fa-check"></i>' : '3' ?>
                                        </div>
                                        <span class="stepper-label">3. Pengerjaan</span>
                                    </div>
                                    <!-- Step 4: Selesai -->
                                    <div class="stepper-step <?= ($step === 'working') ? 'active' : '' ?>">
                                        <div class="stepper-dot">4</div>
                                        <span class="stepper-label">4. Selesai & Kwitansi</span>
                                    </div>
                                </div>

                                <!-- Job Detail Description -->
                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark mb-1">
                                        <i class="fa-solid <?= e($job['category_icon'] ?? 'fa-wrench') ?> text-teal me-1"></i>
                                        <?= e($job['title']) ?>
                                    </h6>
                                    <p class="text-secondary small mb-2 bg-light p-3 rounded-3 border">
                                        <?= nl2br(e($job['description'])) ?>
                                    </p>
                                    <?php if (!empty($job['address'])): ?>
                                        <div class="small text-muted mb-2">
                                            <i class="fa-solid fa-map-pin text-danger me-1"></i> <strong>Alamat Lokasi:</strong> <?= e($job['address']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Buttons Stepper Bar -->
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pt-2">
                                    <!-- Direct Chat Contacts -->
                                    <div class="d-flex gap-2">
                                        <?php if (!empty($custPhoneClean)): ?>
                                            <a href="https://wa.me/<?= $custPhoneClean ?>?text=<?= $waTextActive ?>" target="_blank" class="btn btn-wa-call btn-sm px-3 py-2 rounded-3" title="Chat WhatsApp Pelanggan">
                                                <i class="fa-brands fa-whatsapp fs-6 me-1"></i> Hubungi WA
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>/chat.php?user_id=<?= $job['user_id'] ?>" class="btn btn-outline-teal btn-sm px-3 py-2 rounded-3" title="Obrolan Pesan di Aplikasi">
                                            <i class="fa-solid fa-comments me-1"></i> Chat Aplikasi
                                        </a>
                                    </div>

                                    <!-- Next Step Button Based on Step -->
                                    <div class="d-flex flex-fill justify-content-md-end">
                                        <?php if ($step === 'accepted'): ?>
                                            <!-- Step 1 to Step 2: OTW -->
                                            <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="w-100" style="max-width: 320px;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_order_step">
                                                <input type="hidden" name="request_id" value="<?= $job['id'] ?>">
                                                <input type="hidden" name="step" value="on_the_way">
                                                <button type="submit" class="btn btn-primary fw-bold w-100 py-2.5 rounded-3 shadow-xs">
                                                    <i class="fa-solid fa-motorcycle me-2"></i> Saya Menuju Lokasi (OTW)
                                                </button>
                                            </form>

                                        <?php elseif ($step === 'on_the_way'): ?>
                                            <!-- Step 2 to Step 3: Working -->
                                            <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="w-100" style="max-width: 320px;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_order_step">
                                                <input type="hidden" name="request_id" value="<?= $job['id'] ?>">
                                                <input type="hidden" name="step" value="working">
                                                <button type="submit" class="btn btn-warning text-dark fw-bold w-100 py-2.5 rounded-3 shadow-xs">
                                                    <i class="fa-solid fa-wrench me-2"></i> Sudah Sampai & Mulai Kerja
                                                </button>
                                            </form>

                                        <?php elseif ($step === 'working'): ?>
                                            <!-- Step 3 to Step 4: Complete Job Modal -->
                                            <button type="button" onclick="openCompleteOrderModal(<?= $job['id'] ?>, '<?= addslashes(e($job['title'])) ?>', '<?= addslashes(e($job['customer_name'])) ?>', <?= $finalEstimate ?>)" class="btn btn-success fw-bold w-100 py-2.5 rounded-3 shadow-xs" style="max-width: 340px;">
                                                <i class="fa-solid fa-circle-check me-2"></i> Selesai & Buat Tagihan Kwitansi
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: BURSA LOWONGAN WARGA (OPEN LEADS)  -->
        <!-- ========================================== -->
        <div class="tab-pane fade" id="pills-leads" role="tabpanel" aria-labelledby="pills-leads-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">Permintaan Jasa Terbuka Warga Inhu</h5>
                <span class="badge bg-teal-subtle text-teal px-3 py-2 rounded-pill small">Sistem Penawaran Harga</span>
            </div>

            <?php if (empty($open_leads)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white my-3">
                    <div class="mx-auto mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center text-muted" style="width: 80px; height: 80px;">
                        <i class="fa-solid fa-bullhorn fs-1 text-warning opacity-50"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Belum Ada Lowongan Jasa Terbuka Saat Ini</h5>
                    <p class="text-muted small mx-auto mb-0" style="max-width: 450px;">
                        Saat ada warga Indragiri Hulu yang membuat permintaan jasa lelang untuk umum, daftarnya akan segera ditampilkan di sini.
                    </p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($open_leads as $lead): ?>
                        <div class="col-md-6">
                            <div class="order-card shadow-sm p-4 h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <span class="badge bg-teal-subtle text-teal rounded-pill small">
                                            <i class="fa-solid <?= e($lead['category_icon'] ?? 'fa-wrench') ?> me-1"></i> <?= e($lead['category_name']) ?>
                                        </span>
                                        <span class="badge bg-light text-muted small">
                                            <?= $lead['total_offers'] ?> Tawaran Masuk
                                        </span>
                                    </div>

                                    <h6 class="fw-bold text-dark fs-6 mb-1"><?= e($lead['title']) ?></h6>
                                    <p class="text-muted small mb-3">
                                        <?= e(mb_strimwidth($lead['description'], 0, 140, '...')) ?>
                                    </p>

                                    <div class="d-flex align-items-center justify-content-between small text-muted mb-3 bg-light p-2.5 rounded-3">
                                        <div><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($lead['district_name'] ?: 'Inhu') ?></div>
                                        <div><i class="fa-solid fa-wallet text-success me-1"></i> Anggaran: <strong class="text-dark"><?= $lead['budget'] ? format_rupiah($lead['budget']) : 'Nego' ?></strong></div>
                                    </div>
                                </div>

                                <div>
                                    <?php if ($lead['my_response_id']): ?>
                                        <button class="btn btn-outline-secondary btn-sm w-100 rounded-3" disabled>
                                            <i class="fa-solid fa-check text-success me-1"></i> Tawaran Anda Sudah Terkirim
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-warning text-dark fw-bold btn-sm w-100 rounded-3 shadow-xs" type="button" data-bs-toggle="collapse" data-bs-target="#offerForm<?= $lead['id'] ?>">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Ajukan Penawaran Harga
                                        </button>

                                        <!-- Collapsible Bidding Form -->
                                        <div class="collapse mt-3" id="offerForm<?= $lead['id'] ?>">
                                            <form method="POST" action="<?= BASE_URL ?>/provider/index.php" class="p-3 bg-light rounded-3 border small">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="submit_offer">
                                                <input type="hidden" name="request_id" value="<?= $lead['id'] ?>">

                                                <div class="mb-2">
                                                    <label class="form-label fw-bold mb-1">Harga Tawaran Anda (Rp):</label>
                                                    <input type="number" name="offer_price" class="form-control form-control-sm" placeholder="Contoh: 150000" required value="<?= $lead['budget'] ?: '' ?>">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label fw-bold mb-1">Estimasi Waktu Pengerjaan:</label>
                                                    <input type="text" name="estimated_duration" class="form-control form-control-sm" placeholder="Contoh: 2 Jam / 1 Hari" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label fw-bold mb-1">Pesan untuk Pelanggan:</label>
                                                    <textarea name="message" rows="2" class="form-control form-control-sm" placeholder="Jelaskan keahlian dan kesiapan Anda..." required></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-success btn-sm fw-bold w-100 rounded-3">
                                                    Kirim Tawaran Sekarang
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: RIWAYAT SELESAI (COMPLETED JOBS)   -->
        <!-- ========================================== -->
        <div class="tab-pane fade" id="pills-completed" role="tabpanel" aria-labelledby="pills-completed-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">Riwayat Pekerjaan Selesai</h5>
                <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill small">Arsip Kwitansi Resmi</span>
            </div>

            <?php if (empty($completed_jobs)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white my-3">
                    <div class="mx-auto mb-3 bg-light rounded-circle d-flex align-items-center justify-content-center text-muted" style="width: 80px; height: 80px;">
                        <i class="fa-solid fa-circle-check fs-1 text-success opacity-50"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Belum Ada Riwayat Pekerjaan Selesai</h5>
                    <p class="text-muted small mx-auto mb-0" style="max-width: 450px;">
                        Pekerjaan yang telah Anda selesaikan dan terbitkan kwitansinya akan tersimpan rapi beserta ulasan bintang pelanggan di sini.
                    </p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($completed_jobs as $comp): ?>
                        <div class="col-md-6">
                            <div class="order-card shadow-sm p-4 h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <span class="badge bg-success text-white rounded-pill small">
                                            <i class="fa-solid fa-check me-1"></i> Selesai
                                        </span>
                                        <span class="text-muted small">
                                            <?= format_date($comp['updated_at'] ?: $comp['created_at']) ?>
                                        </span>
                                    </div>

                                    <h6 class="fw-bold text-dark fs-6 mb-1"><?= e($comp['title']) ?></h6>
                                    <div class="text-muted small mb-2">
                                        Pelanggan: <strong><?= e($comp['customer_name']) ?></strong> &bull; <?= e($comp['district_name'] ?: 'Inhu') ?>
                                    </div>

                                    <div class="bg-light p-2.5 rounded-3 mb-3 d-flex justify-content-between align-items-center small">
                                        <span class="text-muted">Total Pembayaran:</span>
                                        <span class="fw-bold text-success fs-6"><?= format_rupiah($comp['agreed_price'] ?: $comp['budget']) ?></span>
                                    </div>

                                    <!-- Customer Review & Rating if available -->
                                    <?php if (!empty($comp['customer_rating'])): ?>
                                        <div class="p-2.5 bg-warning-subtle rounded-3 mb-3 small">
                                            <div class="d-flex align-items-center gap-1 text-warning mb-1">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa-solid fa-star <?= $i <= $comp['customer_rating'] ? 'text-warning' : 'text-secondary opacity-25' ?>"></i>
                                                <?php endfor; ?>
                                                <span class="fw-bold text-dark ms-1"><?= $comp['customer_rating'] ?> / 5</span>
                                            </div>
                                            <?php if (!empty($comp['customer_comment'])): ?>
                                                <div class="text-dark fst-italic">"<?= e($comp['customer_comment']) ?>"</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="pt-2 border-top">
                                    <a href="<?= BASE_URL ?>/receipt.php?request_id=<?= $comp['id'] ?>" target="_blank" class="btn btn-outline-teal btn-sm w-100 rounded-3">
                                        <i class="fa-solid fa-receipt me-1"></i> Lihat Kwitansi Resmi ↗
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

<script src="<?= BASE_URL ?>/assets/js/provider_sound.js"></script>
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

// Inisialisasi Deteksi Order Baru & Nada Dering Masuk
document.addEventListener('DOMContentLoaded', () => {
    if (window.ProviderNotification) {
        window.ProviderNotification.startOrderPolling(<?= !empty($direct_orders[0]['id']) ? (int)$direct_orders[0]['id'] : 0 ?>);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

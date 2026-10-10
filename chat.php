<?php
/**
 * Halaman Menu Obrolan Langsung (In-App Live Chat Ultra Modern)
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Wajib Login
if (!is_logged_in()) {
    set_flash('info', 'Silakan masuk ke akun Anda terlebih dahulu untuk menggunakan menu obrolan.');
    redirect('/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$page_title = 'Obrolan & Pesan Langsung - JASA INHU';
$current_user = current_user();
$db = get_db();

$active_conv_id = !empty($_GET['c']) ? (int)$_GET['c'] : 0;
$target_provider_id = !empty($_GET['provider_id']) ? (int)$_GET['provider_id'] : 0;
$target_user_id = !empty($_GET['to_user']) ? (int)$_GET['to_user'] : 0;
$is_explicit_chat = !empty($_GET['c']) || !empty($_GET['provider_id']) || !empty($_GET['to_user']);

// 1. Jika ada parameter provider_id, temukan user_id pemilik toko
if ($target_provider_id > 0) {
    $stmtP = $db->prepare("SELECT user_id, business_name FROM service_providers WHERE id = ? LIMIT 1");
    $stmtP->execute([$target_provider_id]);
    $pData = $stmtP->fetch();
    if ($pData) {
        $target_user_id = (int)$pData['user_id'];
    }
}

// 2. Jika ada target_user_id dan bukan diri sendiri, temukan atau buat percakapan
if ($target_user_id > 0 && $target_user_id !== (int)$current_user['id']) {
    $u1 = min((int)$current_user['id'], $target_user_id);
    $u2 = max((int)$current_user['id'], $target_user_id);

    $stmtFind = $db->prepare("SELECT id FROM conversations WHERE user_one_id = ? AND user_two_id = ? LIMIT 1");
    $stmtFind->execute([$u1, $u2]);
    $existingId = $stmtFind->fetchColumn();

    if ($existingId) {
        $active_conv_id = (int)$existingId;
    } else {
        $provIdParam = $target_provider_id > 0 ? $target_provider_id : null;
        $stmtCreate = $db->prepare("
            INSERT INTO conversations (user_one_id, user_two_id, provider_id, last_message, last_message_at, created_at)
            VALUES (?, ?, ?, 'Mulai percakapan', NOW(), NOW())
        ");
        $stmtCreate->execute([$u1, $u2, $provIdParam]);
        $active_conv_id = (int)$db->lastInsertId();
    }
}

// 3. Ambil Semua Daftar Percakapan Pengguna Ini
$stmtAllConv = $db->prepare("
    SELECT c.*,
           u1.name as u1_name, u1.id as u1_id,
           u2.name as u2_name, u2.id as u2_id,
           sp.id as sp_id, sp.business_name as sp_business_name, sp.rating_avg as sp_rating,
           cat.name as category_name,
           (SELECT COUNT(*) FROM chat_messages cm WHERE cm.conversation_id = c.id AND cm.receiver_id = ? AND cm.is_read = 0) as unread_count
    FROM conversations c
    JOIN users u1 ON c.user_one_id = u1.id
    JOIN users u2 ON c.user_two_id = u2.id
    LEFT JOIN service_providers sp ON c.provider_id = sp.id
    LEFT JOIN service_categories cat ON sp.primary_category_id = cat.id
    WHERE c.user_one_id = ? OR c.user_two_id = ?
    ORDER BY c.last_message_at DESC
");
$stmtAllConv->execute([$current_user['id'], $current_user['id'], $current_user['id']]);
$conversations = $stmtAllConv->fetchAll();

// Jika belum ada active_conv_id yang terpilih, pilih percakapan pertama secara otomatis jika ada
if ($active_conv_id <= 0 && !empty($conversations)) {
    $active_conv_id = (int)$conversations[0]['id'];
}

// 4. Ambil Detail Percakapan Aktif & Partner Bicara
$active_partner = null;
$active_messages = [];
$active_conversation = null;
$recent_order = null;
$partnerWaClean = '';

// Ambil provider_id diri sendiri jika user saat ini adalah mitra
$stmtMyProv = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
$stmtMyProv->execute([$current_user['id']]);
$my_provider_id = (int)$stmtMyProv->fetchColumn();

if ($active_conv_id > 0) {
    foreach ($conversations as $cv) {
        if ((int)$cv['id'] === $active_conv_id) {
            $active_conversation = $cv;
            break;
        }
    }

    if ($active_conversation) {
        $pId = ((int)$active_conversation['u1_id'] === (int)$current_user['id']) ? (int)$active_conversation['u2_id'] : (int)$active_conversation['u1_id'];
        $pName = ((int)$active_conversation['u1_id'] === (int)$current_user['id']) ? $active_conversation['u2_name'] : $active_conversation['u1_name'];

        // Cek apakah partner adalah penyedia jasa
        $stmtPSP = $db->prepare("
            SELECT sp.*, sc.name as category_name, sc.icon as category_icon, d.name as district_name 
            FROM service_providers sp 
            JOIN service_categories sc ON sp.primary_category_id = sc.id
            LEFT JOIN districts d ON sp.district_id = d.id
            WHERE sp.user_id = ? 
            LIMIT 1
        ");
        $stmtPSP->execute([$pId]);
        $provPartner = $stmtPSP->fetch();

        // Ambil nomor HP partner
        $stmtPartnerUser = $db->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
        $stmtPartnerUser->execute([$pId]);
        $partnerPhone = $stmtPartnerUser->fetchColumn() ?: '';

        if (!empty($partnerPhone)) {
            $partnerWaClean = preg_replace('/[^0-9]/', '', $partnerPhone);
            if (str_starts_with($partnerWaClean, '0')) {
                $partnerWaClean = '62' . substr($partnerWaClean, 1);
            }
        }

        $active_partner = [
            'id' => $pId,
            'name' => $provPartner ? $provPartner['business_name'] : $pName,
            'real_name' => $pName,
            'phone' => $partnerPhone,
            'wa_clean' => $partnerWaClean,
            'is_provider' => !empty($provPartner),
            'provider' => $provPartner ?: null
        ];

        // Cari pesanan aktif/terkait antara current user dan partner
        $partnerProvId = !empty($provPartner['id']) ? (int)$provPartner['id'] : 0;
        if ($partnerProvId > 0 || $my_provider_id > 0) {
            $stmtRecentOrder = $db->prepare("
                SELECT sr.*, sc.name as category_name, sc.icon as category_icon
                FROM service_requests sr
                JOIN service_categories sc ON sr.category_id = sc.id
                WHERE ((sr.user_id = ? AND (sr.provider_id = ? OR sr.id IN (SELECT request_id FROM service_request_responses WHERE provider_id = ?)))
                   OR (sr.user_id = ? AND (sr.provider_id = ? OR sr.id IN (SELECT request_id FROM service_request_responses WHERE provider_id = ?))))
                ORDER BY sr.created_at DESC
                LIMIT 1
            ");
            $stmtRecentOrder->execute([
                $current_user['id'], $partnerProvId, $partnerProvId,
                $pId, $my_provider_id, $my_provider_id
            ]);
            $recent_order = $stmtRecentOrder->fetch();
        }

        // Tandai pesan sudah dibaca
        $stmtMarkRead = $db->prepare("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmtMarkRead->execute([$active_conv_id, $current_user['id']]);

        // Ambil riwayat pesan
        $stmtM = $db->prepare("
            SELECT cm.*, u.name as sender_name
            FROM chat_messages cm
            JOIN users u ON cm.sender_id = u.id
            WHERE cm.conversation_id = ?
            ORDER BY cm.created_at ASC
            LIMIT 150
        ");
        $stmtM->execute([$active_conv_id]);
        $active_messages = $stmtM->fetchAll();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Modern Chat Ultra-Premium CSS */
.chat-app-wrapper {
    height: calc(100vh - 170px);
    min-height: 580px;
    max-height: 780px;
    border-radius: 20px;
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 16px 40px -12px rgba(15, 118, 110, 0.08);
    overflow: hidden;
}

/* Sidebar Contacts */
.chat-sidebar {
    background: #ffffff;
    border-right: 1px solid #f1f5f9;
}
.chat-sidebar-header {
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
}
.chat-search-pill {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 7px 12px;
    transition: all 0.2s ease;
}
.chat-search-pill:focus-within {
    background: #ffffff;
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}
.conversation-item {
    margin: 4px 8px;
    border-radius: 12px;
    padding: 10px 12px;
    transition: all 0.2s ease;
    cursor: pointer;
    border-left: 3px solid transparent;
}
.conversation-item:hover {
    background: #f8fafc;
}
.conversation-item.active {
    background: #f0fdfa;
    border-left: 3px solid #0d9488;
}

/* Avatar & Indicators */
.chat-avatar {
    width: 44px;
    height: 44px;
    min-width: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    position: relative;
    user-select: none;
}
.online-pulse {
    position: absolute;
    bottom: 1px;
    right: 1px;
    width: 12px;
    height: 12px;
    background: #10b981;
    border: 2px solid #ffffff;
    border-radius: 50%;
}

/* Chat Main Area Wallpaper */
.chat-messages-viewport {
    background: 
        radial-gradient(#e2e8f0 1.2px, transparent 1.2px) 0 0 / 22px 22px,
        #f8fafc;
    overflow-y: auto;
    scroll-behavior: smooth;
}

/* Chat Bubbles */
.bubble-me {
    background: linear-gradient(135deg, #0d9488 0%, #115e59 100%);
    color: #ffffff;
    border-radius: 18px 18px 4px 18px;
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.16);
    padding: 10px 14px;
    max-width: 78%;
    min-width: 90px;
    word-break: break-word;
}
.bubble-partner {
    background: #ffffff;
    color: #0f172a;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 18px 18px 18px 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    padding: 10px 14px;
    max-width: 78%;
    min-width: 90px;
    word-break: break-word;
}
.bubble-meta {
    font-size: 0.68rem;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 4px;
}

/* Pinned Order Context Banner */
.order-pinned-banner {
    background: #f0fdfa;
    border-bottom: 1px solid #ccfbf1;
    padding: 8px 16px;
    transition: all 0.2s ease;
}
.order-pinned-banner:hover {
    background: #e6fffa;
}

/* Smart Quick Reply Chips */
.quick-chips-bar {
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    padding: 8px 16px 4px;
    display: flex;
    gap: 6px;
    overflow-x: auto;
    scrollbar-width: none;
}
.quick-chips-bar::-webkit-scrollbar {
    display: none;
}
.quick-chip-btn {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 4px 12px;
    border-radius: 20px;
    white-space: nowrap;
    transition: all 0.2s ease;
}
.quick-chip-btn:hover {
    background: #0d9488;
    color: #ffffff;
    border-color: #0d9488;
    transform: translateY(-1px);
}

/* Floating Input Bar */
.chat-input-bar {
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    padding: 12px 16px;
}
.chat-input-pill {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 25px;
    padding: 4px 6px 4px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.chat-input-pill:focus-within {
    background: #ffffff;
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}
.chat-input-field {
    border: none;
    outline: none;
    background: transparent;
    width: 100%;
    font-size: 0.9rem;
    color: #1e293b;
    padding: 6px 0;
}
.btn-send-gradient {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0d9488 0%, #115e59 100%);
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
}
.btn-send-gradient:hover:not(:disabled) {
    transform: scale(1.06);
    box-shadow: 0 6px 16px rgba(13, 148, 136, 0.35);
}
.btn-send-gradient:active {
    transform: scale(0.96);
}
.btn-wa-header {
    background: #25D366;
    color: #ffffff;
    border: none;
    font-weight: 600;
    font-size: 0.78rem;
    border-radius: 10px;
    padding: 6px 12px;
    transition: all 0.2s ease;
}
.btn-wa-header:hover {
    background: #1eb956;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(37, 211, 102, 0.3);
}
</style>

<!-- Breadcrumb Nav -->
<div class="bg-light py-2 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i> Beranda</a></li>
                <li class="breadcrumb-item active fw-bold text-dark" aria-current="page"><i class="fa-solid fa-comments text-teal me-1"></i> Kotak Obrolan Live</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-3 mb-4">
    <div class="chat-app-wrapper">
        <div class="row g-0 h-100">
            
            <!-- ============================================== -->
            <!-- KOLOM KIRI: DAFTAR KONTAK OBROLAN (SIDEBAR)    -->
            <!-- ============================================== -->
            <div class="col-lg-4 col-md-5 h-100 d-flex flex-column chat-sidebar <?= $is_explicit_chat ? 'd-none d-md-flex' : 'd-flex' ?>" id="chatSidebarCol">
                <!-- Header Sidebar -->
                <div class="p-3 chat-sidebar-header">
                    <div class="d-flex justify-content-between align-items-center mb-2.5">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-teal text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="fa-solid fa-comments fs-6"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-0 fs-6">Kotak Obrolan</h5>
                        </div>
                        <span class="badge bg-teal-subtle text-teal rounded-pill px-2.5 py-1 small fw-bold">
                            <?= count($conversations) ?> Kontak
                        </span>
                    </div>

                    <!-- Search Box -->
                    <div class="chat-search-pill d-flex align-items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass text-muted small"></i>
                        <input type="text" id="searchChatInput" class="border-0 bg-transparent w-100 small text-dark outline-none" placeholder="Cari nama mitra atau pelanggan..." onkeyup="filterConversations()" style="outline: none;">
                    </div>
                </div>

                <!-- List Percakapan -->
                <div class="flex-grow-1 overflow-y-auto" id="conversationList">
                    <?php if (!empty($conversations)): ?>
                        <?php foreach ($conversations as $conv): ?>
                            <?php
                            $isMeU1 = ((int)$conv['u1_id'] === (int)$current_user['id']);
                            $partnerId = $isMeU1 ? (int)$conv['u2_id'] : (int)$conv['u1_id'];
                            $partnerDisplayName = !empty($conv['sp_business_name']) ? $conv['sp_business_name'] : ($isMeU1 ? $conv['u2_name'] : $conv['u1_name']);
                            $isPartnerMitra = !empty($conv['sp_business_name']);
                            $isActive = ((int)$conv['id'] === $active_conv_id);
                            $unread = (int)$conv['unread_count'];
                            ?>
                            <a href="<?= BASE_URL ?>/chat.php?c=<?= $conv['id'] ?>" class="d-flex align-items-center gap-2.5 text-decoration-none text-dark conversation-item <?= $isActive ? 'active' : '' ?>" data-name="<?= strtolower(e($partnerDisplayName)) ?>">
                                <div class="chat-avatar <?= $isPartnerMitra ? 'bg-teal text-white' : 'bg-secondary text-white' ?>">
                                    <?= strtoupper(substr($partnerDisplayName, 0, 1)) ?>
                                    <span class="online-pulse"></span>
                                </div>

                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                        <div class="fw-bold small text-truncate d-flex align-items-center gap-1" style="max-width: 170px;">
                                            <span class="text-truncate"><?= e($partnerDisplayName) ?></span>
                                            <?php if ($isPartnerMitra): ?>
                                                <i class="fa-solid fa-circle-check text-teal" style="font-size: 0.7rem;" title="Mitra Resmi Terverifikasi"></i>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-muted small" style="font-size: 0.68rem;">
                                            <?= !empty($conv['last_message_at']) ? date('H:i', strtotime($conv['last_message_at'])) : '' ?>
                                        </span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="small text-truncate pe-1 <?= $unread > 0 ? 'fw-bold text-dark' : 'text-muted' ?>" style="font-size: 0.78rem; max-width: 180px;">
                                            <?= e($conv['last_message'] ?: 'Mulai obrolan baru...') ?>
                                        </div>
                                        <?php if ($unread > 0): ?>
                                            <span class="badge bg-danger rounded-pill px-2 py-0.5 small" style="font-size: 0.68rem;">
                                                <?= $unread ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 px-3 text-muted">
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-2">
                                <i class="fa-regular fa-comment-dots fs-3 text-muted opacity-50"></i>
                            </div>
                            <h6 class="fw-bold text-dark small mb-1">Belum Ada Obrolan</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.78rem;">Mulai obrolan dengan mitra penyedia jasa langsung dari etalase profil mereka.</p>
                            <a href="<?= BASE_URL ?>/" class="btn btn-teal text-white btn-sm fw-semibold rounded-pill px-3">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Cari Jasa di Inhu
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- KOLOM KANAN: RUANG OBROLAN (LIVE CHAT ROOM)   -->
            <!-- ============================================== -->
            <div class="col-lg-8 col-md-7 h-100 d-flex flex-column bg-light <?= !$is_explicit_chat ? 'd-none d-md-flex' : 'd-flex' ?>" id="chatRoomCol">
                <?php if ($active_conversation && $active_partner): ?>
                    
                    <!-- 1. Header Chat Room -->
                    <div class="p-3 bg-white border-bottom d-flex align-items-center justify-content-between shadow-xs">
                        <div class="d-flex align-items-center gap-2.5">
                            <!-- Tombol Kembali Khusus Mobile -->
                            <a href="<?= BASE_URL ?>/chat.php" class="btn btn-sm btn-light border d-md-none me-1 px-2.5 rounded-3">
                                <i class="fa-solid fa-arrow-left"></i>
                            </a>

                            <div class="chat-avatar <?= $active_partner['is_provider'] ? 'bg-teal text-white' : 'bg-secondary text-white' ?>">
                                <?= strtoupper(substr($active_partner['name'], 0, 1)) ?>
                                <span class="online-pulse"></span>
                            </div>

                            <div>
                                <div class="fw-bold text-dark d-flex align-items-center gap-1.5 fs-6 lh-sm">
                                    <span><?= e($active_partner['name']) ?></span>
                                    <?php if ($active_partner['is_provider']): ?>
                                        <span class="badge bg-teal-subtle text-teal border border-teal-subtle py-0.5 px-2 rounded-pill small" style="font-size: 0.65rem;">
                                            <i class="fa-solid fa-shield-halved me-1"></i> Mitra Terverifikasi
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border py-0.5 px-2 rounded-pill small" style="font-size: 0.65rem;">
                                            Pelanggan
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.72rem;">
                                    <?php if ($active_partner['is_provider'] && !empty($active_partner['provider'])): ?>
                                        <span><i class="fa-solid <?= e($active_partner['provider']['category_icon'] ?? 'fa-wrench') ?> text-warning me-1"></i> <?= e($active_partner['provider']['category_name']) ?> &bull; Kec. <?= e($active_partner['provider']['district_name'] ?: 'Inhu') ?></span>
                                    <?php else: ?>
                                        <span class="text-success"><i class="fa-solid fa-circle text-success me-1" style="font-size: 0.5rem;"></i> Online di Platform Jasa Inhu</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Header Right Quick Actions -->
                        <div class="d-flex align-items-center gap-2">
                            <!-- WhatsApp Shortcut Button -->
                            <?php if (!empty($active_partner['wa_clean'])): ?>
                                <a href="https://wa.me/<?= $active_partner['wa_clean'] ?>?text=<?= urlencode('Halo ' . $active_partner['name'] . ', saya menghubungkan dari Kotak Obrolan Jasa Inhu.') ?>" target="_blank" class="btn btn-wa-header d-inline-flex align-items-center gap-1 shadow-xs" title="Lanjut Obrolan atau Telepon via WhatsApp">
                                    <i class="fa-brands fa-whatsapp fs-6"></i>
                                    <span class="d-none d-sm-inline">WhatsApp</span>
                                </a>
                            <?php endif; ?>

                            <!-- Provider Profile Link -->
                            <?php if ($active_partner['is_provider'] && !empty($active_partner['provider'])): ?>
                                <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $active_partner['provider']['id'] ?>" target="_blank" class="btn btn-outline-teal btn-sm fw-semibold d-none d-sm-inline-flex align-items-center gap-1 py-1.5 px-2.5 rounded-3" style="font-size: 0.78rem;" title="Lihat Toko Publik / Portofolio">
                                    <i class="fa-solid fa-store"></i>
                                    <span>Toko ↗</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. Pinned Order Context Banner (Jika ada order terkait) -->
                    <?php if (!empty($recent_order)): ?>
                        <div class="order-pinned-banner d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2 small text-truncate">
                                <span class="badge bg-teal text-white rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                    <i class="fa-solid fa-receipt me-1"></i> Order #<?= $recent_order['id'] ?>
                                </span>
                                <span class="fw-bold text-dark text-truncate">
                                    <?= e($recent_order['title']) ?>
                                </span>
                                <span class="text-muted d-none d-md-inline">&bull; Biaya: <strong class="text-success"><?= $recent_order['budget'] ? format_rupiah($recent_order['budget']) : 'Nego' ?></strong></span>
                            </div>
                            <div>
                                <span class="badge <?= $recent_order['status'] === 'completed' ? 'bg-success' : ($recent_order['status'] === 'in_progress' ? 'bg-primary' : 'bg-warning text-dark') ?> rounded-pill small" style="font-size: 0.68rem;">
                                    <?= strtoupper($recent_order['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 3. Pesan Chat Body (Scrollable with Modern Pattern) -->
                    <div class="flex-grow-1 p-3 chat-messages-viewport d-flex flex-column gap-2.5" id="chatMessagesArea">
                        <!-- Date & Privacy Pill -->
                        <div class="text-center my-1">
                            <span class="badge bg-white text-muted border shadow-xs px-3 py-1 fw-normal rounded-pill" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-shield-halved text-teal me-1"></i> Percakapan resmi dilindungi sistem JASA INHU
                            </span>
                        </div>

                        <?php if (!empty($active_messages)): ?>
                            <?php foreach ($active_messages as $msg): ?>
                                <?php $isMe = ((int)$msg['sender_id'] === (int)$current_user['id']); ?>
                                <div class="d-flex flex-column <?= $isMe ? 'align-items-end' : 'align-items-start' ?> msg-bubble-wrap" id="msg-<?= $msg['id'] ?>" data-id="<?= $msg['id'] ?>">
                                    <div class="<?= $isMe ? 'bubble-me' : 'bubble-partner' ?>">
                                        <p class="mb-0 small" style="white-space: pre-line; font-size: 0.88rem; line-height: 1.45;"><?= e($msg['message']) ?></p>
                                        <div class="bubble-meta <?= $isMe ? 'text-white-50' : 'text-muted' ?>">
                                            <span><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                                            <?php if ($isMe): ?>
                                                <i class="fa-solid <?= $msg['is_read'] ? 'fa-check-double' : 'fa-check' ?>" style="<?= $msg['is_read'] ? 'color: #fef08a;' : '' ?>" title="<?= $msg['is_read'] ? 'Sudah Dibaca' : 'Terkirim' ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted my-auto" id="noMessagesPlaceholder">
                                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center p-3 mb-2 shadow-xs border">
                                    <i class="fa-regular fa-comments text-teal fs-3"></i>
                                </div>
                                <h6 class="fw-bold text-dark small mb-1">Mulai Obrolan dengan <?= e($active_partner['name']) ?></h6>
                                <p class="small text-muted mb-0" style="font-size: 0.78rem;">Konsultasikan kendala teknis, perkiraan biaya, atau jadwal survei lokasi Anda.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Smart Quick Reply Chips -->
                    <div class="quick-chips-bar">
                        <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Halo, apakah layanan jasa ini bisa dipanggil hari ini?')">
                            ⚡ Bisa hari ini?
                        </button>
                        <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Berapa perkiraan total biaya untuk perbaikan ini?')">
                            💰 Perkiraan biaya?
                        </button>
                        <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Apakah bisa survei atau cek kondisi lokasi terlebih dahulu?')">
                            🔍 Survei lokasi dulu?
                        </button>
                        <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Lokasi saya di daerah: ')">
                            📍 Kirim alamat saya
                        </button>
                        <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Boleh minta nomor WhatsApp untuk kirim foto / video kondisi kerusakan?')">
                            📸 Minta WA untuk kirim foto
                        </button>
                    </div>

                    <!-- 5. Modern Floating Input Bar -->
                    <div class="chat-input-bar">
                        <form id="chatSendForm" onsubmit="sendChatMessage(event)" class="d-flex align-items-center gap-2">
                            <input type="hidden" id="chatConvId" value="<?= $active_conv_id ?>">
                            <input type="hidden" id="chatReceiverId" value="<?= $active_partner['id'] ?>">

                            <div class="flex-grow-1 chat-input-pill">
                                <i class="fa-regular fa-message text-muted small"></i>
                                <input type="text" id="chatMessageInput" class="chat-input-field" placeholder="Ketik pesan untuk <?= e($active_partner['name']) ?>..." autocomplete="off" required>
                            </div>

                            <button type="submit" id="btnSendChat" class="btn-send-gradient" title="Kirim Pesan (Tekan Enter)">
                                <i class="fa-solid fa-paper-plane" style="font-size: 0.95rem;"></i>
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <!-- State Belum Memilih Obrolan di Layar Besar -->
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 p-4 text-center text-muted my-auto">
                        <div class="rounded-circle bg-white border d-inline-flex align-items-center justify-content-center p-4 mb-3 shadow-xs" style="width: 80px; height: 80px;">
                            <i class="fa-solid fa-comments text-teal" style="font-size: 2.2rem;"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Pilih Kontak untuk Memulai Percakapan</h5>
                        <p class="small text-muted mb-3" style="max-width: 380px;">
                            Pilih salah satu mitra atau pelanggan di panel sebelah kiri untuk berdiskusi, bernegosiasi biaya, atau mengatur jadwal kedatangan.
                        </p>
                        <a href="<?= BASE_URL ?>/" class="btn btn-outline-teal btn-sm fw-semibold rounded-pill px-3 py-1.5 shadow-xs">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Temukan Mitra Jasa di Inhu
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
window.CSRF_TOKEN = '<?= csrf_token() ?>';
window.BASE_URL = '<?= BASE_URL ?>';
window.CURRENT_USER_ID = <?= (int)$current_user['id'] ?>;
window.ACTIVE_CONV_ID = <?= (int)$active_conv_id ?>;

// Web Audio API Soft Chime (Synthesizer halus saat pesan masuk)
function playMessageChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // Note D5
        osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.12); // Note A5

        gain.gain.setValueAtTime(0.08, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start();
        osc.stop(ctx.currentTime + 0.25);
    } catch (e) {
        // Abaikan jika browser memblokir audio autoplay sebelum interaksi
    }
}

function scrollChatToBottom() {
    const area = document.getElementById('chatMessagesArea');
    if (area) {
        area.scrollTop = area.scrollHeight;
    }
}

// Scroll otomatis saat pertama load & fokus ke input
document.addEventListener('DOMContentLoaded', () => {
    scrollChatToBottom();
    const input = document.getElementById('chatMessageInput');
    if (input && (window.innerWidth >= 768 || <?= $is_explicit_chat ? 'true' : 'false' ?>)) {
        input.focus();
    }
});

function insertQuickChat(text) {
    const input = document.getElementById('chatMessageInput');
    if (!input) return;
    input.value = text;
    input.focus();
}

function filterConversations() {
    const q = document.getElementById('searchChatInput').value.toLowerCase().trim();
    const items = document.querySelectorAll('.conversation-item');
    items.forEach(el => {
        const name = el.getAttribute('data-name') || '';
        if (name.includes(q)) {
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
        }
    });
}

function sendChatMessage(e) {
    e.preventDefault();
    const input = document.getElementById('chatMessageInput');
    const msg = input.value.trim();
    const convId = document.getElementById('chatConvId').value;
    const receiverId = document.getElementById('chatReceiverId').value;
    const btn = document.getElementById('btnSendChat');

    if (!msg) return;

    btn.disabled = true;

    const formData = new FormData();
    formData.append('csrf_token', window.CSRF_TOKEN);
    formData.append('conversation_id', convId);
    formData.append('receiver_id', receiverId);
    formData.append('message', msg);

    fetch(window.BASE_URL + '/api/chat_send.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        if (data.success) {
            input.value = '';
            input.focus();

            // Sembunyikan placeholder kosong jika ada
            const emptyEl = document.getElementById('noMessagesPlaceholder');
            if (emptyEl) emptyEl.classList.add('d-none');

            // Tambahkan bubble ke area chat
            const area = document.getElementById('chatMessagesArea');
            const d = data.data;

            const bubbleWrap = document.createElement('div');
            bubbleWrap.className = 'd-flex flex-column align-items-end msg-bubble-wrap';
            bubbleWrap.id = 'msg-' + d.id;
            bubbleWrap.setAttribute('data-id', d.id);
            bubbleWrap.innerHTML = `
                <div class="bubble-me">
                    <p class="mb-0 small" style="white-space: pre-line; font-size: 0.88rem; line-height: 1.45;">${escapeHtml(d.message)}</p>
                    <div class="bubble-meta text-white-50">
                        <span>${d.time}</span>
                        <i class="fa-solid fa-check"></i>
                    </div>
                </div>
            `;
            area.appendChild(bubbleWrap);
            scrollChatToBottom();
        } else {
            alert(data.message || 'Gagal mengirim pesan.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        alert('Terjadi kesalahan jaringan.');
    });
}

// Polling Pesan Baru Secara Real-Time Setiap 2.5 Detik
let lastMessageId = 0;
const existingBubbles = document.querySelectorAll('.msg-bubble-wrap');
if (existingBubbles.length > 0) {
    const lastEl = existingBubbles[existingBubbles.length - 1];
    lastMessageId = parseInt(lastEl.getAttribute('data-id')) || 0;
}

if (window.ACTIVE_CONV_ID > 0) {
    setInterval(() => {
        fetch(window.BASE_URL + '/api/chat_fetch.php?conversation_id=' + window.ACTIVE_CONV_ID + '&last_id=' + lastMessageId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.messages && res.messages.length > 0) {
                const area = document.getElementById('chatMessagesArea');
                const emptyEl = document.getElementById('noMessagesPlaceholder');
                if (emptyEl) emptyEl.classList.add('d-none');

                let hasNewIncoming = false;

                res.messages.forEach(m => {
                    if (m.id > lastMessageId) {
                        lastMessageId = m.id;
                    }

                    // Hindari duplikasi jika pesan sudah ada
                    if (document.getElementById('msg-' + m.id)) return;

                    if (!m.is_me) {
                        hasNewIncoming = true;
                    }

                    const bubbleWrap = document.createElement('div');
                    bubbleWrap.className = 'd-flex flex-column ' + (m.is_me ? 'align-items-end' : 'align-items-start') + ' msg-bubble-wrap';
                    bubbleWrap.id = 'msg-' + m.id;
                    bubbleWrap.setAttribute('data-id', m.id);

                    if (m.is_me) {
                        bubbleWrap.innerHTML = `
                            <div class="bubble-me">
                                <p class="mb-0 small" style="white-space: pre-line; font-size: 0.88rem; line-height: 1.45;">${escapeHtml(m.message)}</p>
                                <div class="bubble-meta text-white-50">
                                    <span>${m.time}</span>
                                    <i class="fa-solid fa-check"></i>
                                </div>
                            </div>
                        `;
                    } else {
                        bubbleWrap.innerHTML = `
                            <div class="bubble-partner">
                                <p class="mb-0 small" style="white-space: pre-line; font-size: 0.88rem; line-height: 1.45;">${escapeHtml(m.message)}</p>
                                <div class="bubble-meta text-muted">
                                    <span>${m.time}</span>
                                </div>
                            </div>
                        `;
                    }

                    area.appendChild(bubbleWrap);
                });

                if (hasNewIncoming) {
                    playMessageChime();
                }

                scrollChatToBottom();
            }
        })
        .catch(e => {});
    }, 2500);
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

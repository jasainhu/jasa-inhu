<?php
/**
 * Halaman Ruang Obrolan & Pesan Langsung (Ultra Modern Live Chat)
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
$target_user_id = !empty($_GET['to_user']) ? (int)$_GET['to_user'] : (!empty($_GET['user_id']) ? (int)$_GET['user_id'] : 0);
$is_explicit_chat = !empty($_GET['c']) || !empty($_GET['provider_id']) || !empty($_GET['to_user']) || !empty($_GET['user_id']);

// Konfigurasi tampilan mobile saat membuka ruang chat aktif
if ($is_explicit_chat) {
    $hide_footer = true;
    $hide_mobile_bottom_nav = true;
    $hide_floating_wa = true;
    $hide_ai_widget = true;
    $hide_mobile_header = true;
}

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

// Jika belum ada active_conv_id yang terpilih, pilih percakapan pertama secara otomatis (untuk desktop)
if ($active_conv_id <= 0 && !empty($conversations)) {
    $active_conv_id = (int)$conversations[0]['id'];
}

// 4. Ambil Detail Percakapan Aktif & Partner Bicara
$active_partner = null;
$active_messages = [];
$active_conversation = null;
$recent_order = null;
$partnerWaClean = '';
$partnerWaUrl = '';

// Ambil provider_id diri sendiri jika user saat ini adalah mitra
$stmtMyProv = $db->prepare("SELECT id FROM service_providers WHERE user_id = ? LIMIT 1");
$stmtMyProv->execute([$current_user['id']]);
$my_provider_id = (int)$stmtMyProv->fetchColumn();

// Ambil alamat profil tersimpan pengguna saat ini untuk kemudahan pemesanan langsung dari chat
$stmtUserProf = $db->prepare("SELECT address FROM profiles WHERE user_id = ? LIMIT 1");
$stmtUserProf->execute([$current_user['id']]);
$user_saved_address = $stmtUserProf->fetchColumn() ?: '';

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
            } elseif (str_starts_with($partnerWaClean, '8')) {
                $partnerWaClean = '62' . $partnerWaClean;
            }
            if (!empty($partnerWaClean)) {
                $waMsg = urlencode("Halo " . ($provPartner ? $provPartner['business_name'] : $pName) . ", saya menghubungi dari aplikasi JASA INHU.");
                $partnerWaUrl = "https://wa.me/{$partnerWaClean}?text={$waMsg}";
            }
        }

        $active_partner = [
            'id' => $pId,
            'name' => $provPartner ? $provPartner['business_name'] : $pName,
            'real_name' => $pName,
            'phone' => $partnerPhone,
            'wa_clean' => $partnerWaClean,
            'wa_url' => $partnerWaUrl,
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

// Hitung total kontak unread
$total_unread_conversations = 0;
foreach ($conversations as $cRow) {
    if ((int)$cRow['unread_count'] > 0) {
        $total_unread_conversations++;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* =================================================== */
/* ULTRA MODERN LIVE CHAT STYLING (WHATSAPP/TELEGRAM) */
/* =================================================== */

:root {
    --chat-teal-primary: #0d9488;
    --chat-teal-dark: #0f766e;
    --chat-teal-deep: #064e43;
    --chat-bubble-me: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    --chat-bg-pattern: #f0f2f5;
}

.chat-app-wrapper {
    background: #ffffff;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Sidebar Contacts */
.chat-sidebar {
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    transition: all 0.25s ease;
}
.chat-sidebar-header {
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
}
.chat-search-pill {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    padding: 7px 14px;
    transition: all 0.2s ease;
}
.chat-search-pill:focus-within {
    background: #ffffff;
    border-color: var(--chat-teal-primary);
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}

/* Tab filter pills on sidebar */
.chat-filter-tab {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}
.chat-filter-tab:hover {
    background: #f1f5f9;
    color: #1e293b;
}
.chat-filter-tab.active {
    background: var(--chat-teal-primary);
    color: #ffffff;
    border-color: var(--chat-teal-primary);
    box-shadow: 0 2px 6px rgba(13, 148, 136, 0.25);
}

.conversation-item {
    margin: 4px 8px;
    border-radius: 14px;
    padding: 11px 13px;
    transition: all 0.15s ease;
    cursor: pointer;
    border-left: 3.5px solid transparent;
}
.conversation-item:hover {
    background: #f8fafc;
}
.conversation-item.active {
    background: #f0fdfa;
    border-left-color: var(--chat-teal-primary);
}

/* Avatar with Online Glow Ring */
.chat-avatar {
    width: 44px;
    height: 44px;
    min-width: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.05rem;
    position: relative;
    user-select: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.online-pulse-ring {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 13px;
    height: 13px;
    background: #10b981;
    border: 2px solid #ffffff;
    border-radius: 50%;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.3);
}

/* Room Header */
.chat-room-header {
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    padding: 11px 16px;
    z-index: 10;
}

/* WhatsApp-Level Sleek Background Pattern */
.chat-messages-viewport {
    background-color: var(--chat-bg-pattern);
    background-image: 
        radial-gradient(circle at 50% 50%, rgba(13, 148, 136, 0.03) 0%, transparent 70%),
        url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%2364748b' fill-opacity='0.05' fill-rule='evenodd'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
    overflow-y: auto;
    scroll-behavior: smooth;
    display: flex;
    flex-direction: column;
    padding: 16px;
    gap: 10px;
}

/* Bubbles */
.msg-bubble-wrap {
    animation: chatBubblePop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes chatBubblePop {
    from {
        opacity: 0;
        transform: translateY(6px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.bubble-me {
    background: var(--chat-bubble-me);
    color: #ffffff;
    border-radius: 18px 18px 4px 18px;
    box-shadow: 0 3px 10px rgba(13, 148, 136, 0.2);
    padding: 9px 14px;
    max-width: 82%;
    min-width: 90px;
    word-break: break-word;
    position: relative;
}
.bubble-partner {
    background: #ffffff;
    color: #0f172a;
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: 18px 18px 18px 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    padding: 9px 14px;
    max-width: 82%;
    min-width: 90px;
    word-break: break-word;
    position: relative;
}
.bubble-meta {
    font-size: 0.67rem;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 3px;
    user-select: none;
}

/* Quick Action Chips */
.quick-chips-bar {
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    padding: 8px 14px;
    display: flex;
    gap: 7px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.quick-chips-bar::-webkit-scrollbar {
    display: none;
}
.quick-chip-btn {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #334155;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 5px 13px;
    border-radius: 20px;
    white-space: nowrap;
    transition: all 0.2s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.quick-chip-btn:hover {
    background: var(--chat-teal-primary);
    color: #ffffff;
    border-color: var(--chat-teal-primary);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(13, 148, 136, 0.25);
}
.quick-chip-btn.highlight {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    border-color: #a7f3d0;
    color: #065f46;
}
.quick-chip-btn.highlight:hover {
    background: #059669;
    color: #ffffff;
    border-color: #059669;
}

/* Chat Input Bar */
.chat-input-bar {
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    padding: 10px 14px;
    z-index: 5;
}
.chat-input-pill {
    background: #f1f5f9;
    border: 1.5px solid transparent;
    border-radius: 26px;
    padding: 5px 14px;
    display: flex;
    align-items: center;
    gap: 9px;
    transition: all 0.2s ease;
}
.chat-input-pill:focus-within {
    background: #ffffff;
    border-color: var(--chat-teal-primary);
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}
.chat-input-field {
    border: none;
    outline: none;
    background: transparent;
    width: 100%;
    font-size: 0.9rem;
    color: #0f172a;
    padding: 5px 0;
}
.chat-input-field::placeholder {
    color: #94a3b8;
}
.btn-send-gradient {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.28);
    cursor: pointer;
}
.btn-send-gradient:hover:not(:disabled) {
    transform: scale(1.08);
    box-shadow: 0 6px 16px rgba(13, 148, 136, 0.38);
}
.btn-send-gradient:active {
    transform: scale(0.92);
}
.btn-send-gradient:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* =================================================== */
/* RESPONSIVE LAYOUT & MOBILE FULLSCREEN EXPERIENCE   */
/* =================================================== */

@media (min-width: 768px) {
    .chat-app-wrapper {
        height: calc(100vh - 150px);
        min-height: 600px;
        max-height: 820px;
        border-radius: 20px;
        border: 1px solid rgba(226, 232, 240, 0.95);
        box-shadow: 0 16px 40px -12px rgba(15, 118, 110, 0.08);
    }
}

@media (max-width: 767.98px) {
    <?php if ($is_explicit_chat): ?>
    /* Ruang Chat Fullscreen Native Mobile (Ala WhatsApp / Shopee Chat) */
    body {
        overflow: hidden !important;
        position: fixed;
        width: 100%;
        height: 100%;
    }
    .gojek-mobile-header {
        display: none !important;
    }
    #kakInhuWidgetRoot,
    .floating-wa-btn,
    .mobile-bottom-nav {
        display: none !important;
    }
    .chat-breadcrumb-nav {
        display: none !important;
    }
    .chat-main-container {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
        height: 100dvh !important;
        overflow: hidden !important;
    }
    .chat-app-wrapper {
        height: 100dvh !important;
        min-height: 100dvh !important;
        max-height: 100dvh !important;
        border-radius: 0 !important;
        border: none !important;
        box-shadow: none !important;
        display: flex !important;
        flex-direction: column !important;
    }
    .chat-app-wrapper > .row {
        height: 100% !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
    }
    #chatRoomCol {
        height: 100% !important;
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        overflow: hidden !important;
    }
    .chat-room-header {
        position: sticky;
        top: 0;
        z-index: 20;
        padding: 9px 12px;
        flex-shrink: 0 !important;
    }
    .chat-messages-viewport {
        flex: 1 1 0 !important;
        min-height: 0 !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch !important;
    }
    .quick-chips-bar {
        flex-shrink: 0 !important;
    }
    .chat-input-bar {
        flex-shrink: 0 !important;
        padding: 8px 10px calc(8px + env(safe-area-inset-bottom, 0px)) !important;
        background: #ffffff !important;
        position: relative !important;
        z-index: 25 !important;
        border-top: 1px solid #e2e8f0 !important;
    }
    <?php else: ?>
    /* Tampilan Kotak Masuk Mobile (Inbox) */
    .chat-app-wrapper {
        height: calc(100dvh - 130px);
        min-height: 480px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
    }
    <?php endif; ?>
}
</style>

<!-- Breadcrumb Nav (Khusus Desktop) -->
<div class="bg-light py-2 border-bottom chat-breadcrumb-nav d-none d-md-block">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i> Beranda</a></li>
                <li class="breadcrumb-item active fw-bold text-dark" aria-current="page"><i class="fa-solid fa-comments text-teal me-1"></i> Kotak Obrolan Live</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-md-3 mb-md-4 chat-main-container">
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
                            <div class="rounded-circle text-white d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px; background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                                <i class="fa-solid fa-comments fs-6"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0 fs-6">Kotak Obrolan</h5>
                                <small class="text-muted" style="font-size: 0.68rem;">Pesan langsung mitra & pelanggan</small>
                            </div>
                        </div>
                        <span class="badge bg-teal-subtle text-teal rounded-pill px-2.5 py-1 small fw-bold">
                            <?= count($conversations) ?> Kontak
                        </span>
                    </div>

                    <!-- Search Box -->
                    <div class="chat-search-pill d-flex align-items-center gap-2 mb-2">
                        <i class="fa-solid fa-magnifying-glass text-muted small"></i>
                        <input type="text" id="searchChatInput" class="border-0 bg-transparent w-100 small text-dark outline-none" placeholder="Cari nama mitra atau jasa..." onkeyup="filterConversations()" style="outline: none;">
                        <button type="button" class="btn btn-link text-muted p-0 border-0 d-none" id="btnClearSearchChat" onclick="clearSearchChat()">
                            <i class="fa-solid fa-xmark small"></i>
                        </button>
                    </div>

                    <!-- Quick Filter Tabs -->
                    <div class="d-flex gap-1.5 overflow-x-auto pb-0.5">
                        <button type="button" class="chat-filter-tab active" onclick="setChatFilter('all', this)">Semua (<?= count($conversations) ?>)</button>
                        <button type="button" class="chat-filter-tab" onclick="setChatFilter('unread', this)">Belum Dibaca <?= $total_unread_conversations > 0 ? "($total_unread_conversations)" : '' ?></button>
                        <button type="button" class="chat-filter-tab" onclick="setChatFilter('provider', this)">Mitra Jasa</button>
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
                            <a href="<?= BASE_URL ?>/chat.php?c=<?= $conv['id'] ?>" 
                               class="d-flex align-items-center gap-2.5 text-decoration-none text-dark conversation-item <?= $isActive ? 'active' : '' ?>" 
                               data-name="<?= strtolower(e($partnerDisplayName)) ?>"
                               data-is-unread="<?= $unread > 0 ? '1' : '0' ?>"
                               data-is-provider="<?= $isPartnerMitra ? '1' : '0' ?>">
                                <div class="chat-avatar <?= $isPartnerMitra ? 'bg-teal text-white' : 'bg-secondary text-white' ?>">
                                    <?= strtoupper(substr($partnerDisplayName, 0, 1)) ?>
                                    <span class="online-pulse-ring"></span>
                                </div>

                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                        <div class="fw-bold small text-truncate d-flex align-items-center gap-1" style="max-width: 170px;">
                                            <span class="text-truncate"><?= e($partnerDisplayName) ?></span>
                                            <?php if ($isPartnerMitra): ?>
                                                <i class="fa-solid fa-circle-check text-teal" style="font-size: 0.72rem;" title="Mitra Resmi Terverifikasi"></i>
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
                                            <span class="badge bg-danger rounded-pill px-2 py-0.5 small shadow-2xs" style="font-size: 0.68rem;">
                                                <?= $unread ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 px-3 text-muted">
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-3 mb-2 shadow-xs">
                                <i class="fa-regular fa-comment-dots fs-3 text-teal opacity-75"></i>
                            </div>
                            <h6 class="fw-bold text-dark small mb-1">Belum Ada Obrolan</h6>
                            <p class="small text-muted mb-3" style="font-size: 0.78rem;">Mulai obrolan dengan mitra penyedia jasa langsung dari etalase profil mereka.</p>
                            <a href="<?= BASE_URL ?>/" class="btn btn-teal text-white btn-sm fw-semibold rounded-pill px-3 shadow-xs">
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
                    <div class="chat-room-header d-flex align-items-center justify-content-between shadow-2xs">
                        <div class="d-flex align-items-center gap-2">
                            <!-- Tombol Kembali Khusus Mobile -->
                            <a href="<?= BASE_URL ?>/chat.php" class="btn btn-sm btn-light border d-md-none px-2 rounded-circle shadow-2xs" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;" title="Kembali ke Daftar Obrolan">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>

                            <div class="chat-avatar <?= $active_partner['is_provider'] ? 'bg-teal text-white' : 'bg-secondary text-white' ?>" style="width: 40px; height: 40px; min-width: 40px; font-size: 0.95rem;">
                                <?= strtoupper(substr($active_partner['name'], 0, 1)) ?>
                                <span class="online-pulse-ring"></span>
                            </div>

                            <div class="overflow-hidden">
                                <div class="fw-bold text-dark d-flex align-items-center gap-1.5 fs-6 lh-sm text-truncate">
                                    <span class="text-truncate"><?= e($active_partner['name']) ?></span>
                                    <?php if ($active_partner['is_provider']): ?>
                                        <span class="badge bg-teal-subtle text-teal border border-teal-subtle py-0.5 px-2 rounded-pill small d-none d-sm-inline-flex align-items-center gap-1" style="font-size: 0.65rem;">
                                            <i class="fa-solid fa-shield-halved"></i> Mitra Resmi
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border py-0.5 px-2 rounded-pill small d-none d-sm-inline-flex" style="font-size: 0.65rem;">
                                            Pelanggan
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted text-truncate" style="font-size: 0.72rem;">
                                    <?php if ($active_partner['is_provider'] && !empty($active_partner['provider'])): ?>
                                        <span><i class="fa-solid <?= e($active_partner['provider']['category_icon'] ?? 'fa-wrench') ?> text-warning me-1"></i> <?= e($active_partner['provider']['category_name']) ?> &bull; Kec. <?= e($active_partner['provider']['district_name'] ?: 'Inhu') ?></span>
                                    <?php else: ?>
                                        <span class="text-success fw-medium"><i class="fa-solid fa-circle text-success me-1" style="font-size: 0.5rem;"></i> Aktif di Platform Jasa Inhu</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Header Right Quick Actions -->
                        <div class="d-flex align-items-center gap-1.5">
                            <!-- Direct Order / Deal Button -->
                            <?php if ($active_partner['is_provider'] && !empty($active_partner['provider'])): ?>
                                <button type="button" class="btn btn-primary-custom btn-sm fw-bold d-inline-flex align-items-center gap-1 shadow-2xs px-2.5 px-sm-3 py-1.5 rounded-pill" style="font-size: 0.75rem;" title="Kunci Kesepakatan & Pesan Resmi" onclick="openOrderModalFromChat()">
                                    <i class="fa-solid fa-handshake text-warning"></i>
                                    <span>Deal Jasa</span>
                                </button>
                            <?php endif; ?>

                            <!-- Provider Storefront Link -->
                            <?php if ($active_partner['is_provider'] && !empty($active_partner['provider'])): ?>
                                <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $active_partner['provider']['id'] ?>" target="_blank" class="btn btn-light border btn-sm fw-semibold d-inline-flex align-items-center gap-1 py-1.5 px-2.5 rounded-pill text-dark shadow-2xs" style="font-size: 0.75rem;" title="Lihat Profil & Portofolio Toko">
                                    <i class="fa-solid fa-store text-teal"></i>
                                    <span>Lihat Toko ↗</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. Pinned Order Context Banner (Jika ada order terkait) -->
                    <?php if (!empty($recent_order)): ?>
                        <div class="order-pinned-banner d-flex align-items-center justify-content-between px-3 py-2 bg-teal-subtle border-bottom border-teal-subtle">
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
                    <div class="flex-grow-1 chat-messages-viewport" id="chatMessagesArea">
                        <!-- Date & Privacy Pill -->
                        <div class="text-center my-1 d-flex flex-column align-items-center gap-1.5">
                            <span class="badge bg-white text-muted border shadow-2xs px-3 py-1.5 fw-medium rounded-pill" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-shield-halved text-teal me-1"></i> Percakapan Resmi Dilindungi Sistem JASA INHU
                            </span>
                            <span class="text-muted small px-3 py-1.5 bg-white border border-dashed rounded-3 shadow-2xs" style="font-size: 0.7rem; max-width: 90%;">
                                <i class="fa-solid fa-lock text-teal me-1"></i>
                                <span>Demi keamanan transaksi & komplain bergaransi, pastikan rincian pesanan resmi dikunci melalui tombol <strong>Deal Jasa</strong> di atas.</span>
                            </span>
                        </div>

                        <?php if (!empty($active_messages)): ?>
                            <?php foreach ($active_messages as $msg): ?>
                                <?php 
                                    $isMe = ((int)$msg['sender_id'] === (int)$current_user['id']); 
                                    $isOrderCard = str_contains($msg['message'], '[PESANAN RESMI DIBUAT]');
                                ?>
                                <div class="d-flex flex-column <?= $isMe ? 'align-items-end' : 'align-items-start' ?> msg-bubble-wrap" id="msg-<?= $msg['id'] ?>" data-id="<?= $msg['id'] ?>">
                                    <?php if ($isOrderCard): ?>
                                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-1 text-start" style="max-width: 380px; background: #ffffff; border: 1.5px solid #0d9488 !important;">
                                            <div class="px-3 py-2 text-white d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="fa-solid fa-receipt text-warning fs-6"></i>
                                                    <span class="fw-bold small" style="font-size: 0.78rem;">PESANAN RESMI DIBUAT</span>
                                                </div>
                                                <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.65rem;">Terverifikasi</span>
                                            </div>
                                            <div class="p-3 text-dark">
                                                <p class="small text-dark mb-2" style="white-space: pre-line; font-size: 0.82rem; line-height: 1.5;"><?= e($msg['message']) ?></p>
                                                <div class="pt-2 border-top">
                                                    <a href="<?= BASE_URL ?>/user/requests.php" class="btn btn-teal btn-sm fw-bold rounded-pill w-100" style="font-size: 0.75rem;">
                                                        <i class="fa-solid fa-file-lines me-1"></i> Buka Rincian Pesanan
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="bubble-meta text-muted px-3 pb-2 pt-0 d-flex justify-content-between">
                                                <span class="small text-muted" style="font-size: 0.65rem;">JASA INHU Verified Order</span>
                                                <span><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="<?= $isMe ? 'bubble-me' : 'bubble-partner' ?>">
                                            <p class="mb-0 small" style="white-space: pre-line; font-size: 0.88rem; line-height: 1.45;"><?= e($msg['message']) ?></p>
                                            <div class="bubble-meta <?= $isMe ? 'text-white-50' : 'text-muted' ?>">
                                                <span><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                                                <?php if ($isMe): ?>
                                                    <i class="fa-solid <?= $msg['is_read'] ? 'fa-check-double text-warning' : 'fa-check' ?>" title="<?= $msg['is_read'] ? 'Sudah Dibaca' : 'Terkirim' ?>"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted my-auto" id="noMessagesPlaceholder">
                                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center p-3 mb-2 shadow-xs border">
                                    <i class="fa-regular fa-comments text-teal fs-3"></i>
                                </div>
                                <h6 class="fw-bold text-dark small mb-1">Mulai Obrolan dengan <?= e($active_partner['name']) ?></h6>
                                <p class="small text-muted mb-0" style="font-size: 0.78rem;">Konsultasikan kendala teknis, perkiraan biaya, atau atur waktu kunjungan Anda.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Smart Quick Reply Chips -->
                    <div class="quick-chips-bar">
                        <?php if ($active_partner['is_provider']): ?>
                            <button type="button" class="quick-chip-btn highlight" onclick="openOrderModalFromChat()">
                                🤝 Deal Pesanan Resmi
                            </button>
                            <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Halo, apakah layanan jasa ini bisa dipanggil hari ini?')">
                                ⚡ Bisa hari ini?
                            </button>
                            <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Berapa perkiraan total biaya untuk pekerjaan ini?')">
                                💰 Perkiraan biaya?
                            </button>
                            <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Apakah bisa survei atau cek kondisi lokasi terlebih dahulu?')">
                                🔍 Survei lokasi dulu?
                            </button>
                            <button type="button" class="quick-chip-btn" onclick="insertSavedAddressChat()">
                                📍 Kirim alamat saya
                            </button>
                            <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Kira-kira butuh waktu berapa jam untuk pengerjaannya ya?')">
                                ⏱️ Estimasi waktu?
                            </button>
                        <?php else: ?>
                            <button type="button" class="quick-chip-btn highlight" onclick="insertQuickChat('Halo kak, bisa jelaskan detail keluhan atau kirim foto kondisinya?')">
                                🔍 Tanya detail kendala
                            </button>
                            <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Bisa kirimkan alamat lengkap atau patokan lokasi untuk survei?')">
                                📍 Minta alamat lokasi
                            </button>
                            <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Baik kak, untuk biaya jasa tersebut perkiraannya Rp ')">
                                💰 Berikan estimasi biaya
                            </button>
                            <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Tim kami siap meluncur ke lokasi sekarang kak.')">
                                ⚡ Siap meluncur sekarang
                            </button>
                            <button type="button" class="quick-chip-btn" onclick="insertQuickChat('Silakan klik tombol [Deal Jasa] di atas ya kak agar pesanan tercatat resmi.')">
                                📋 Arahkan deal resmi
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- 5. Modern Floating Input Bar -->
                    <div class="chat-input-bar">
                        <form id="chatSendForm" onsubmit="sendChatMessage(event)" class="d-flex align-items-center gap-2">
                            <input type="hidden" id="chatConvId" value="<?= $active_conv_id ?>">
                            <input type="hidden" id="chatReceiverId" value="<?= $active_partner['id'] ?>">

                            <div class="flex-grow-1 chat-input-pill">
                                <i class="fa-regular fa-comment-dots text-muted small"></i>
                                <input type="text" id="chatMessageInput" class="chat-input-field" placeholder="Ketik pesan untuk <?= e($active_partner['name']) ?>..." autocomplete="off" required>
                            </div>

                            <button type="submit" id="btnSendChat" class="btn-send-gradient" title="Kirim Pesan (Enter)">
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

<?php if (!empty($active_partner) && $active_partner['is_provider'] && !empty($active_partner['provider'])): ?>
<!-- Modal Pesan Jasa Langsung dari Chat (Deal Harga) -->
<div class="modal fade" id="modalInChatOrder" tabindex="-1" aria-labelledby="modalInChatOrderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-white bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="fa-solid fa-file-invoice-dollar fs-5 text-warning"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0" id="modalInChatOrderLabel">Buat Pesanan & Deal Biaya</h6>
                        <small class="text-white-50" style="font-size: 0.72rem;">Kunci kesepakatan dengan <?= e($active_partner['name']) ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="formInChatOrder" onsubmit="submitInChatOrder(event)">
                <input type="hidden" id="inChatProviderId" value="<?= (int)$active_partner['provider']['id'] ?>">
                <input type="hidden" id="inChatMapsUrl" value="">
                
                <div class="modal-body p-4">
                    <!-- Banner Info Aman -->
                    <div class="alert alert-teal-subtle border-0 rounded-3 p-3 mb-3 d-flex align-items-start gap-2.5">
                        <i class="fa-solid fa-shield-halved text-teal mt-0.5 fs-6"></i>
                        <div class="small text-dark" style="font-size: 0.78rem; line-height: 1.45;">
                            Pesanan ini otomatis tercatat resmi di sistem <strong>JASA INHU</strong>, rincian muncul di obrolan ini, dan notifikasi terkirim otomatis ke WhatsApp mitra.
                        </div>
                    </div>

                    <!-- Kebutuhan Jasa -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">
                            Layanan / Pekerjaan yang Disepakati <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                            <input type="text" class="form-control border-start-0 ps-1" id="inChatProblem" required 
                                   value="Layanan <?= e($active_partner['provider']['category_name'] ?? 'Jasa') ?>" 
                                   placeholder="Contoh: Servis AC 1 PK cuci + tambah freon, dll">
                        </div>
                    </div>

                    <!-- Kesepakatan Biaya (Budget) -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1 d-flex justify-content-between">
                            <span>Biaya yang Disepakati (Rp)</span>
                            <span class="text-muted fw-normal" style="font-size: 0.72rem;">Kosongkan jika masih nego/survei</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 fw-bold text-teal">Rp</span>
                            <input type="number" class="form-control border-start-0 ps-1 fw-bold text-dark" id="inChatBudget" 
                                   placeholder="Contoh: 150000" min="0" step="5000">
                        </div>
                        <small class="text-muted" style="font-size: 0.7rem;">Nominal yang sudah Anda sepakati bersama mitra dalam obrolan.</small>
                    </div>

                    <!-- Waktu Kunjungan / Pengerjaan -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">
                            Waktu Kunjungan / Pengerjaan <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="inChatTime" required>
                            <option value="Hari ini / Secepatnya" selected>⚡ Hari ini / Secepatnya</option>
                            <option value="Besok Pagi (08:00 - 12:00)">☀️ Besok Pagi (08:00 - 12:00)</option>
                            <option value="Besok Siang / Sore (13:00 - 17:00)">🌤️ Besok Siang / Sore (13:00 - 17:00)</option>
                            <option value="Akhir Pekan (Sabtu / Minggu)">🗓️ Akhir Pekan (Sabtu / Minggu)</option>
                            <option value="Sesuai Kesepakatan di Chat">💬 Sesuai Kesepakatan di Chat</option>
                        </select>
                    </div>

                    <!-- Alamat / Lokasi -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold text-dark mb-0">
                                Alamat / Lokasi Pengerjaan <span class="text-danger">*</span>
                            </label>
                            <button type="button" class="btn btn-link text-teal p-0 text-decoration-none fw-semibold" style="font-size: 0.72rem;" onclick="detectGPSInChatOrder()" id="btnGpsInChat">
                                <i class="fa-solid fa-crosshairs me-1"></i> Deteksi GPS
                            </button>
                        </div>
                        <textarea class="form-control" id="inChatAddress" rows="2" required placeholder="Contoh: Jl. Narasinga No. 12, Kel. Kampung Dagang, patokan dekat Masjid..."><?= e($user_saved_address) ?></textarea>
                        <div id="gpsInChatStatus" class="small text-success mt-1 d-none" style="font-size: 0.7rem;">
                            <i class="fa-solid fa-circle-check me-1"></i> Titik koordinat GPS berhasil dikunci!
                        </div>
                    </div>

                    <!-- Opsi Pesan untuk Orang Lain -->
                    <div class="border rounded-3 p-2.5 bg-light mb-1">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="switchOrderOther" onchange="toggleOrderOther(this.checked)">
                            <label class="form-check-label small fw-semibold text-dark" for="switchOrderOther" style="font-size: 0.78rem;">
                                Pesankan untuk Kerabat / Orang Lain
                            </label>
                        </div>
                        <div id="boxOrderOther" class="mt-2 pt-2 border-top d-none">
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm" id="inChatRecipientName" placeholder="Nama Penerima">
                                </div>
                                <div class="col-6">
                                    <input type="tel" class="form-control form-control-sm" id="inChatRecipientPhone" placeholder="No. HP Penerima">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-2.5 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-light border btn-sm fw-semibold rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="btnSubmitInChatOrder" class="btn btn-teal btn-sm fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1.5"></i> Kirim & Kunci Pesanan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
window.CSRF_TOKEN = '<?= csrf_token() ?>';
window.BASE_URL = '<?= BASE_URL ?>';
window.CURRENT_USER_ID = <?= (int)$current_user['id'] ?>;
window.ACTIVE_CONV_ID = <?= (int)$active_conv_id ?>;
window.USER_SAVED_ADDRESS = <?= json_encode($user_saved_address) ?>;

// Web Audio API Soft Chime (Synthesizer halus saat pesan masuk)
function playMessageChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.12); // A5

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

function scrollChatToBottom(smooth = false) {
    const area = document.getElementById('chatMessagesArea');
    if (area) {
        if (smooth) {
            area.scrollTo({ top: area.scrollHeight, behavior: 'smooth' });
        } else {
            area.scrollTop = area.scrollHeight;
        }
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

function insertSavedAddressChat() {
    const input = document.getElementById('chatMessageInput');
    if (!input) return;
    if (window.USER_SAVED_ADDRESS && window.USER_SAVED_ADDRESS.trim().length > 0) {
        input.value = 'Lokasi saya di: ' + window.USER_SAVED_ADDRESS;
    } else {
        input.value = 'Lokasi saya di daerah: ';
    }
    input.focus();
}

// Filter Kontak Sidebar (Tab & Text Search)
let currentFilterTab = 'all';

function setChatFilter(tab, el) {
    currentFilterTab = tab;
    document.querySelectorAll('.chat-filter-tab').forEach(b => b.classList.remove('active'));
    if (el) el.classList.add('active');
    applyConversationFilters();
}

function filterConversations() {
    const q = document.getElementById('searchChatInput').value.trim();
    const clearBtn = document.getElementById('btnClearSearchChat');
    if (clearBtn) {
        if (q.length > 0) clearBtn.classList.remove('d-none');
        else clearBtn.classList.add('d-none');
    }
    applyConversationFilters();
}

function clearSearchChat() {
    const input = document.getElementById('searchChatInput');
    if (input) {
        input.value = '';
        filterConversations();
        input.focus();
    }
}

function applyConversationFilters() {
    const q = document.getElementById('searchChatInput').value.toLowerCase().trim();
    const items = document.querySelectorAll('.conversation-item');
    items.forEach(el => {
        const name = el.getAttribute('data-name') || '';
        const isUnread = el.getAttribute('data-is-unread') === '1';
        const isProvider = el.getAttribute('data-is-provider') === '1';

        let matchesTab = true;
        if (currentFilterTab === 'unread') {
            matchesTab = isUnread;
        } else if (currentFilterTab === 'provider') {
            matchesTab = isProvider;
        }

        const matchesQuery = !q || name.includes(q);

        if (matchesTab && matchesQuery) {
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
        }
    });
}

function openOrderModalFromChat() {
    const modalEl = document.getElementById('modalInChatOrder');
    if (!modalEl) {
        alert('Fitur pemesanan langsung tersedia saat membuka obrolan dengan mitra penyedia jasa.');
        return;
    }
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function toggleOrderOther(checked) {
    const box = document.getElementById('boxOrderOther');
    if (!box) return;
    if (checked) {
        box.classList.remove('d-none');
    } else {
        box.classList.add('d-none');
    }
}

function detectGPSInChatOrder() {
    const btn = document.getElementById('btnGpsInChat');
    const status = document.getElementById('gpsInChatStatus');
    const hiddenMaps = document.getElementById('inChatMapsUrl');
    const addrArea = document.getElementById('inChatAddress');

    if (!navigator.geolocation) {
        alert('Fitur GPS tidak didukung di perangkat ini.');
        return;
    }

    const origText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Mendeteksi...';
    btn.disabled = true;

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}`;
            hiddenMaps.value = mapsUrl;
            
            if (status) {
                status.classList.remove('d-none');
                status.innerHTML = `<i class="fa-solid fa-circle-check me-1"></i> Koordinat GPS terkunci: (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
            }
            if (!addrArea.value.trim() || addrArea.value.startsWith('Lokasi GPS:')) {
                addrArea.value = `Lokasi GPS: ${mapsUrl}`;
            }
            btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> GPS Terkunci';
            btn.disabled = false;
        },
        (err) => {
            btn.innerHTML = origText;
            btn.disabled = false;
            alert('Gagal mendeteksi lokasi GPS. Pastikan izin lokasi diaktifkan di browser Anda.');
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function submitInChatOrder(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitInChatOrder');
    const providerId = document.getElementById('inChatProviderId').value;
    const problem = document.getElementById('inChatProblem').value.trim();
    const budget = document.getElementById('inChatBudget').value.trim();
    const preferredTime = document.getElementById('inChatTime').value;
    const address = document.getElementById('inChatAddress').value.trim();
    const mapsUrl = document.getElementById('inChatMapsUrl').value.trim();
    const isOther = document.getElementById('switchOrderOther').checked;
    const recipientName = isOther ? document.getElementById('inChatRecipientName').value.trim() : '';
    const recipientPhone = isOther ? document.getElementById('inChatRecipientPhone').value.trim() : '';

    if (!problem) {
        alert('Mohon sebutkan layanan atau keluhan yang disepakati.');
        return;
    }
    if (!address) {
        alert('Mohon isi alamat atau lokasi pengerjaan.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Memproses Pesanan...';

    const formData = new FormData();
    formData.append('csrf_token', window.CSRF_TOKEN);
    formData.append('provider_id', providerId);
    formData.append('problem', problem);
    if (budget) formData.append('budget', budget);
    formData.append('preferred_time', preferredTime);
    formData.append('address', address);
    if (mapsUrl) formData.append('maps_url', mapsUrl);
    formData.append('order_for', isOther ? 'other' : 'self');
    if (isOther) {
        formData.append('recipient_name', recipientName);
        formData.append('recipient_phone', recipientPhone);
    }

    fetch(window.BASE_URL + '/api/direct_order.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1.5"></i> Kirim & Kunci Pesanan';
        if (data.success) {
            const modalEl = document.getElementById('modalInChatOrder');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            alert('🎉 Pesanan Resmi Berhasil Dibuat!\nNo. Pesanan: #' + data.request_id + '\n\nNotifikasi telah dikirimkan ke WhatsApp mitra.');
            window.location.reload();
        } else {
            alert(data.message || 'Gagal membuat pesanan resmi.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1.5"></i> Kirim & Kunci Pesanan';
        alert('Terjadi kesalahan jaringan saat memproses pesanan.');
    });
}

function formatBubbleHtml(msgText, isMe, time, isRead) {
    if (msgText.includes('[PESANAN RESMI DIBUAT]')) {
        return `
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-1 text-start" style="max-width: 380px; background: #ffffff; border: 1.5px solid #0d9488 !important;">
                <div class="px-3 py-2 text-white d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-receipt text-warning fs-6"></i>
                        <span class="fw-bold small" style="font-size: 0.78rem;">PESANAN RESMI DIBUAT</span>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.65rem;">Terverifikasi</span>
                </div>
                <div class="p-3 text-dark">
                    <p class="small text-dark mb-2" style="white-space: pre-line; font-size: 0.82rem; line-height: 1.5;">${escapeHtml(msgText)}</p>
                    <div class="pt-2 border-top">
                        <a href="${window.BASE_URL}/user/requests.php" class="btn btn-teal btn-sm fw-bold rounded-pill w-100" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-file-lines me-1"></i> Buka Rincian Pesanan
                        </a>
                    </div>
                </div>
                <div class="bubble-meta text-muted px-3 pb-2 pt-0 d-flex justify-content-between">
                    <span class="small text-muted" style="font-size: 0.65rem;">JASA INHU Verified Order</span>
                    <span>${time}</span>
                </div>
            </div>
        `;
    }

    if (isMe) {
        return `
            <div class="bubble-me">
                <p class="mb-0 small" style="white-space: pre-line; font-size: 0.88rem; line-height: 1.45;">${escapeHtml(msgText)}</p>
                <div class="bubble-meta text-white-50">
                    <span>${time}</span>
                    <i class="fa-solid ${isRead ? 'fa-check-double text-warning' : 'fa-check'}"></i>
                </div>
            </div>
        `;
    } else {
        return `
            <div class="bubble-partner">
                <p class="mb-0 small" style="white-space: pre-line; font-size: 0.88rem; line-height: 1.45;">${escapeHtml(msgText)}</p>
                <div class="bubble-meta text-muted">
                    <span>${time}</span>
                </div>
            </div>
        `;
    }
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
            bubbleWrap.innerHTML = formatBubbleHtml(d.message, true, d.time, false);
            area.appendChild(bubbleWrap);
            scrollChatToBottom(true);
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

// Efek Judul Tab berkedip saat tab tidak aktif
let origPageTitle = document.title;
let titleInterval = null;

window.addEventListener('focus', () => {
    if (titleInterval) {
        clearInterval(titleInterval);
        titleInterval = null;
    }
    document.title = origPageTitle;
});

function flashTabTitle() {
    if (document.hidden && !titleInterval) {
        let isFlash = false;
        titleInterval = setInterval(() => {
            document.title = isFlash ? '(1) 💬 Pesan Baru!' : origPageTitle;
            isFlash = !isFlash;
        }, 1200);
    }
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
                    bubbleWrap.innerHTML = formatBubbleHtml(m.message, m.is_me, m.time, false);

                    area.appendChild(bubbleWrap);
                });

                if (hasNewIncoming) {
                    playMessageChime();
                    flashTabTitle();
                }

                scrollChatToBottom(true);
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

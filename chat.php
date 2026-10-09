<?php
/**
 * Halaman Menu Obrolan Langsung (In-App Live Chat)
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
            SELECT sp.*, sc.name as category_name, d.name as district_name 
            FROM service_providers sp 
            JOIN service_categories sc ON sp.primary_category_id = sc.id
            LEFT JOIN districts d ON sp.district_id = d.id
            WHERE sp.user_id = ? 
            LIMIT 1
        ");
        $stmtPSP->execute([$pId]);
        $provPartner = $stmtPSP->fetch();

        $active_partner = [
            'id' => $pId,
            'name' => $provPartner ? $provPartner['business_name'] : $pName,
            'real_name' => $pName,
            'is_provider' => !empty($provPartner),
            'provider' => $provPartner ?: null
        ];

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

<div class="bg-light py-2 border-bottom">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/" class="text-decoration-none text-muted"><i class="fa-solid fa-house me-1"></i> Beranda</a></li>
                <li class="breadcrumb-item active fw-bold text-dark" aria-current="page"><i class="fa-solid fa-comments text-teal me-1"></i> Kotak Obrolan</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-3 mb-5">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden chat-container-wrapper" style="height: 680px; min-height: 550px;">
        <div class="row g-0 h-100">
            
            <!-- KOLOM KIRI: DAFTAR PERCAKAPAN (SIDEBAR) -->
            <div class="col-lg-4 col-md-5 border-end h-100 d-flex flex-column bg-white <?= $is_explicit_chat ? 'd-none d-md-flex' : 'd-flex' ?>" id="chatSidebarCol">
                <!-- Header Sidebar Obrolan -->
                <div class="p-3 border-bottom bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-2.5">
                        <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-comments text-teal fs-5"></i>
                            <span>Kotak Obrolan</span>
                        </h5>
                        <span class="badge bg-teal-subtle text-teal rounded-pill px-2.5 py-1 small fw-bold">
                            <?= count($conversations) ?> Kontak
                        </span>
                    </div>

                    <!-- Input Cari Percakapan -->
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="searchChatInput" class="form-control bg-light border-start-0" placeholder="Cari nama mitra atau pelanggan..." onkeyup="filterConversations()">
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
                            <a href="<?= BASE_URL ?>/chat.php?c=<?= $conv['id'] ?>" class="d-flex align-items-center gap-3 p-3 border-bottom text-decoration-none text-dark hover-bg-light transition conversation-item <?= $isActive ? 'bg-teal-subtle border-start border-4 border-teal active' : '' ?>" data-name="<?= strtolower(e($partnerDisplayName)) ?>">
                                <div class="position-relative flex-shrink-0">
                                    <div class="rounded-circle <?= $isPartnerMitra ? 'bg-teal text-white' : 'bg-secondary text-white' ?> d-flex align-items-center justify-content-center fw-bold shadow-xs" style="width: 44px; height: 44px; font-size: 1.1rem;">
                                        <?= strtoupper(substr($partnerDisplayName, 0, 1)) ?>
                                    </div>
                                    <?php if ($isPartnerMitra): ?>
                                        <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width: 12px; height: 12px;" title="Mitra Jasa Inhu"></span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                        <div class="fw-bold small text-truncate d-flex align-items-center gap-1" style="max-width: 170px;">
                                            <span class="text-truncate"><?= e($partnerDisplayName) ?></span>
                                            <?php if ($isPartnerMitra): ?>
                                                <i class="fa-solid fa-circle-check text-teal" style="font-size: 0.72rem;" title="Mitra Terverifikasi"></i>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-muted small" style="font-size: 0.7rem;">
                                            <?= !empty($conv['last_message_at']) ? date('H:i', strtotime($conv['last_message_at'])) : '' ?>
                                        </span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="text-muted small text-truncate pe-1" style="font-size: 0.78rem; max-width: 180px;">
                                            <?= e($conv['last_message'] ?: 'Mulai obrolan baru...') ?>
                                        </div>
                                        <?php if ($unread > 0): ?>
                                            <span class="badge bg-teal rounded-pill px-2 py-0.5 small" style="font-size: 0.68rem;">
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

            <!-- KOLOM KANAN: RUANG OBROLAN (CHAT ROOM) -->
            <div class="col-lg-8 col-md-7 h-100 d-flex flex-column bg-light <?= !$is_explicit_chat ? 'd-none d-md-flex' : 'd-flex' ?>" id="chatRoomCol">
                <?php if ($active_conversation && $active_partner): ?>
                    <!-- 1. Header Chat Room -->
                    <div class="p-3 bg-white border-bottom d-flex align-items-center justify-content-between shadow-xs">
                        <div class="d-flex align-items-center gap-2.5">
                            <!-- Tombol Kembali Khusus Mobile -->
                            <a href="<?= BASE_URL ?>/chat.php" class="btn btn-sm btn-light border d-md-none me-1 px-2.5">
                                <i class="fa-solid fa-arrow-left"></i>
                            </a>

                            <div class="rounded-circle <?= $active_partner['is_provider'] ? 'bg-teal text-white' : 'bg-secondary text-white' ?> d-flex align-items-center justify-content-center fw-bold shadow-xs flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.1rem;">
                                <?= strtoupper(substr($active_partner['name'], 0, 1)) ?>
                            </div>

                            <div>
                                <div class="fw-bold text-dark d-flex align-items-center gap-1.5 fs-6 lh-sm">
                                    <span><?= e($active_partner['name']) ?></span>
                                    <?php if ($active_partner['is_provider']): ?>
                                        <span class="badge bg-teal-subtle text-teal border border-teal-subtle py-0.5 px-2 small" style="font-size: 0.65rem;">
                                            <i class="fa-solid fa-store me-1"></i> Mitra Jasa Inhu
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border py-0.5 px-2 small" style="font-size: 0.65rem;">
                                            Pelanggan
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted" style="font-size: 0.72rem;">
                                    <?php if ($active_partner['is_provider'] && !empty($active_partner['provider'])): ?>
                                        <span><?= e($active_partner['provider']['category_name']) ?> &bull; Kec. <?= e($active_partner['provider']['district_name'] ?: 'Inhu') ?></span>
                                    <?php else: ?>
                                        <span class="text-success"><i class="fa-solid fa-circle text-success me-1" style="font-size: 0.5rem;"></i> Aktif di JASA INHU</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Aksi Tambahan: Link Profil Mitra / WhatsApp -->
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($active_partner['is_provider'] && !empty($active_partner['provider'])): ?>
                                <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $active_partner['provider']['id'] ?>" target="_blank" class="btn btn-outline-teal btn-sm fw-semibold d-none d-sm-inline-flex align-items-center gap-1 py-1 px-2.5" style="font-size: 0.78rem;">
                                    <i class="fa-solid fa-store"></i>
                                    <span>Etalase Profil ↗</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. Pesan Chat Body (Scrollable) -->
                    <div class="flex-grow-1 p-3 overflow-y-auto d-flex flex-column gap-2.5" id="chatMessagesArea" style="background: #f8fafc;">
                        <!-- Date Divider Awal -->
                        <div class="text-center my-1">
                            <span class="badge bg-white text-muted border shadow-xs px-3 py-1 fw-normal" style="font-size: 0.72rem;">
                                <i class="fa-solid fa-lock text-teal me-1"></i> Percakapan dilindungi platform resmi JASA INHU
                            </span>
                        </div>

                        <?php if (!empty($active_messages)): ?>
                            <?php foreach ($active_messages as $msg): ?>
                                <?php $isMe = ((int)$msg['sender_id'] === (int)$current_user['id']); ?>
                                <div class="d-flex flex-column <?= $isMe ? 'align-items-end' : 'align-items-start' ?> msg-bubble-wrap" id="msg-<?= $msg['id'] ?>" data-id="<?= $msg['id'] ?>">
                                    <div class="p-2.5 rounded-3 shadow-xs <?= $isMe ? 'bg-teal text-white rounded-bottom-end-0' : 'bg-white text-dark border rounded-bottom-start-0' ?>" style="max-width: 78%; min-width: 100px; word-break: break-word; line-height: 1.45;">
                                        <p class="mb-1 small" style="white-space: pre-line; font-size: 0.85rem;"><?= e($msg['message']) ?></p>
                                        <div class="d-flex align-items-center justify-content-end gap-1 <?= $isMe ? 'text-white-50' : 'text-muted' ?>" style="font-size: 0.65rem;">
                                            <span><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                                            <?php if ($isMe): ?>
                                                <i class="fa-solid <?= $msg['is_read'] ? 'fa-check-double text-warning' : 'fa-check' ?>" title="<?= $msg['is_read'] ? 'Dibaca' : 'Terkirim' ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted my-auto" id="noMessagesPlaceholder">
                                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center p-3 mb-2 shadow-xs">
                                    <i class="fa-regular fa-comments text-teal fs-3"></i>
                                </div>
                                <h6 class="fw-bold text-dark small mb-1">Mulai Percakapan</h6>
                                <p class="small text-muted mb-0" style="font-size: 0.78rem;">Kirimkan pertanyaan atau konsultasikan kebutuhan jasa Anda di bawah ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Quick Prompt Chips -->
                    <div class="px-3 pt-2 bg-white border-top">
                        <div class="d-flex gap-1.5 overflow-x-auto pb-1" style="white-space: nowrap;">
                            <button type="button" class="btn btn-sm btn-light border rounded-pill py-0.5 px-2.5 text-muted small" style="font-size: 0.73rem;" onclick="insertQuickChat('Halo kak, apakah jasa ini bisa dipanggil hari ini?')">
                                Bisa dipanggil hari ini?
                            </button>
                            <button type="button" class="btn btn-sm btn-light border rounded-pill py-0.5 px-2.5 text-muted small" style="font-size: 0.73rem;" onclick="insertQuickChat('Berapa estimasi biaya untuk perbaikan ini?')">
                                Berapa estimasi biaya?
                            </button>
                            <button type="button" class="btn btn-sm btn-light border rounded-pill py-0.5 px-2.5 text-muted small" style="font-size: 0.73rem;" onclick="insertQuickChat('Apakah melayani survei / pengecekan lokasi dulu?')">
                                Bisa survei lokasi dulu?
                            </button>
                            <button type="button" class="btn btn-sm btn-light border rounded-pill py-0.5 px-2.5 text-muted small" style="font-size: 0.73rem;" onclick="insertQuickChat('Alamat pengerjaannya di daerah: ')">
                                Cantumkan Alamat
                            </button>
                        </div>
                    </div>

                    <!-- 4. Form Input Kirim Pesan -->
                    <div class="p-3 bg-white border-top">
                        <form id="chatSendForm" onsubmit="sendChatMessage(event)" class="d-flex align-items-center gap-2">
                            <input type="hidden" id="chatConvId" value="<?= $active_conv_id ?>">
                            <input type="hidden" id="chatReceiverId" value="<?= $active_partner['id'] ?>">

                            <div class="flex-grow-1 position-relative">
                                <input type="text" id="chatMessageInput" class="form-control rounded-pill py-2 px-3.5 shadow-xs border" placeholder="Ketik pesan obrolan di sini..." autocomplete="off" required>
                            </div>

                            <button type="submit" id="btnSendChat" class="btn btn-teal text-white rounded-circle shadow-xs d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;" title="Kirim Pesan (Enter)">
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
                        <h5 class="fw-bold text-dark mb-1">Pilih Percakapan untuk Memulai</h5>
                        <p class="small text-muted mb-3" style="max-width: 380px;">
                            Pilih salah satu kontak di sebelah kiri untuk berkonsultasi, bernegosiasi, atau melacak perkembangan pekerjaan jasa Anda.
                        </p>
                        <a href="<?= BASE_URL ?>/" class="btn btn-outline-teal btn-sm fw-semibold rounded-pill px-3 py-1.5">
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

function scrollChatToBottom() {
    const area = document.getElementById('chatMessagesArea');
    if (area) {
        area.scrollTop = area.scrollHeight;
    }
}

// Scroll otomatis saat pertama load
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
                <div class="p-2.5 rounded-3 shadow-xs bg-teal text-white rounded-bottom-end-0" style="max-width: 78%; min-width: 100px; word-break: break-word; line-height: 1.45;">
                    <p class="mb-1 small" style="white-space: pre-line; font-size: 0.85rem;">${escapeHtml(d.message)}</p>
                    <div class="d-flex align-items-center justify-content-end gap-1 text-white-50" style="font-size: 0.65rem;">
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
// Inisialisasi lastMessageId dari elemen yang ada di DOM
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

                res.messages.forEach(m => {
                    if (m.id > lastMessageId) {
                        lastMessageId = m.id;
                    }

                    // Hindari duplikasi jika pesan sudah ada
                    if (document.getElementById('msg-' + m.id)) return;

                    const bubbleWrap = document.createElement('div');
                    bubbleWrap.className = 'd-flex flex-column ' + (m.is_me ? 'align-items-end' : 'align-items-start') + ' msg-bubble-wrap';
                    bubbleWrap.id = 'msg-' + m.id;
                    bubbleWrap.setAttribute('data-id', m.id);

                    if (m.is_me) {
                        bubbleWrap.innerHTML = `
                            <div class="p-2.5 rounded-3 shadow-xs bg-teal text-white rounded-bottom-end-0" style="max-width: 78%; min-width: 100px; word-break: break-word; line-height: 1.45;">
                                <p class="mb-1 small" style="white-space: pre-line; font-size: 0.85rem;">${escapeHtml(m.message)}</p>
                                <div class="d-flex align-items-center justify-content-end gap-1 text-white-50" style="font-size: 0.65rem;">
                                    <span>${m.time}</span>
                                    <i class="fa-solid fa-check"></i>
                                </div>
                            </div>
                        `;
                    } else {
                        bubbleWrap.innerHTML = `
                            <div class="p-2.5 rounded-3 shadow-xs bg-white text-dark border rounded-bottom-start-0" style="max-width: 78%; min-width: 100px; word-break: break-word; line-height: 1.45;">
                                <p class="mb-1 small" style="white-space: pre-line; font-size: 0.85rem;">${escapeHtml(m.message)}</p>
                                <div class="d-flex align-items-center justify-content-end gap-1 text-muted" style="font-size: 0.65rem;">
                                    <span>${m.time}</span>
                                </div>
                            </div>
                        `;
                    }

                    area.appendChild(bubbleWrap);
                });
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

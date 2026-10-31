<?php
/**
 * Global Widget: "Kak Inhu" AI Agent & Virtual Concierge
 * JASA INHU - Marketplace Jasa Lokal Kabupaten Indragiri Hulu
 */
if (get_setting('ai_agent_enabled', '1') === '0') {
    return; // AI Agent dinonaktifkan di admin
}

$agentName = get_setting('ai_agent_name', 'Kak Inhu');
?>
<!-- KAK INHU - AI AGENT WIDGET -->
<div id="kakInhuWidgetRoot" class="kakinhu-root" data-agent-name="<?= e($agentName) ?>">
    
    <!-- 1. Floating Launcher Button & Tooltip (Pojok Kanan Bawah, Di Atas WhatsApp CS) -->
    <div id="kakInhuLauncher" class="kakinhu-launcher shadow-lg" role="button" aria-label="Tanya <?= e($agentName) ?> Asisten AI">
        <div class="kakinhu-avatar-wrapper">
            <div class="kakinhu-avatar">
                <img src="<?= asset_url('images/tanjak-icon.svg') ?>" alt="Tanjak Melayu">
            </div>
            <span class="kakinhu-online-dot" title="Online 24 Jam"></span>
        </div>
        <div class="kakinhu-launcher-text">
            <span class="kakinhu-btn-title">Tanya <?= e($agentName) ?></span>
            <span class="kakinhu-btn-sub d-none d-sm-inline"><i class="fa-solid fa-sparkles text-warning me-1"></i>Asisten AI 24 Jam</span>
        </div>
    </div>

    <!-- Tooltip Panggilan Pertama (Auto Pop-up Lembut) -->
    <div id="kakInhuTooltip" class="kakinhu-tooltip shadow-sm animate__animated animate__fadeInUp">
        <button type="button" class="kakinhu-tooltip-close" id="kakInhuTooltipClose" aria-label="Tutup tooltip">&times;</button>
        <div class="d-flex align-items-center gap-2">
            <img src="<?= asset_url('images/tanjak-icon.svg') ?>" alt="Tanjak Melayu" style="width: 28px; height: 28px; object-fit: contain; flex-shrink: 0;">
            <div class="small">
                <strong>Butuh jasa apa di Inhu?</strong><br>
                Tanya Kak Inhu, langsung dicarikan mitra terdekat!
            </div>
        </div>
    </div>

    <!-- 2. Chat Window (Slide Up Modal) -->
    <div id="kakInhuWindow" class="kakinhu-window shadow-2xl">
        <!-- Header -->
        <div class="kakinhu-header">
            <div class="kakinhu-header-info">
                <div class="kakinhu-header-avatar">
                    <img src="<?= asset_url('images/tanjak-icon.svg') ?>" alt="Kak Inhu">
                    <span class="kakinhu-header-ping"></span>
                </div>
                <div>
                    <h6 class="kakinhu-header-name mb-0"><?= e($agentName) ?> <span class="badge text-bg-warning text-dark px-1.5 py-0.5 ms-1" style="font-size: 0.65rem;">AI</span></h6>
                    <small class="kakinhu-header-status text-white-50 d-flex align-items-center gap-1">
                        <span class="kakinhu-status-indicator"></span> Asisten Jasa Inhu • Siaga 24 Jam
                    </small>
                </div>
            </div>
            <div class="kakinhu-header-actions">
                <button type="button" id="kakInhuResetBtn" class="kakinhu-btn-icon" title="Mulai Obrolan Baru" aria-label="Reset Chat">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button type="button" id="kakInhuCloseBtn" class="kakinhu-btn-icon" title="Tutup Chat" aria-label="Tutup Chat">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Chat Body -->
        <div id="kakInhuBody" class="kakinhu-body">
            <!-- Pesan Pembuka Default -->
            <div class="kakinhu-msg-row bot">
                <div class="kakinhu-msg-avatar">
                    <img src="<?= asset_url('images/tanjak-icon.svg') ?>" alt="Kak Inhu">
                </div>
                <div class="kakinhu-bubble bot">
                    <p class="mb-1 fw-semibold text-teal" style="font-size: 0.82rem;">
                        <i class="fa-solid fa-sparkles me-1 text-warning"></i> Halo! Saya <?= e($agentName) ?> 🙋‍♂️
                    </p>
                    <p class="mb-2 small">
                        Asisten pintar platform <strong>Jasa Inhu</strong>. Lagi butuh tukang, bengkel, servis AC, atau jasa apa hari ini di Indragiri Hulu? Ceritakan keluhan Anda, saya carikan mitra terdekat sekarang!
                    </p>
                    <div class="kakinhu-quick-chips">
                        <span class="kakinhu-chip" data-query="Butuh tukang cuci AC di Air Molek">❄️ Servis AC Air Molek</span>
                        <span class="kakinhu-chip" data-query="Motor saya mogok butuh montir di Rengat">🏍️ Montir Mogok Rengat</span>
                        <span class="kakinhu-chip" data-query="Cari regu panen sawit di Belilas">🌴 Regu Panen Sawit</span>
                        <span class="kakinhu-chip" data-query="Ada tukang las bikin kanopi di Pematang Reba?">🔥 Bengkel Las Inhu</span>
                        <span class="kakinhu-chip" data-query="Butuh tukang perbaikan listrik korslet">⚡ Servis Listrik</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Typing Indicator (Hidden by default) -->
        <div id="kakInhuTyping" class="kakinhu-typing-row d-none">
            <div class="kakinhu-msg-avatar">
                <img src="<?= asset_url('images/tanjak-icon.svg') ?>" alt="Kak Inhu">
            </div>
            <div class="kakinhu-bubble bot kakinhu-typing-bubble">
                <span class="kakinhu-dot"></span>
                <span class="kakinhu-dot"></span>
                <span class="kakinhu-dot"></span>
            </div>
        </div>

        <!-- Footer / Input Bar -->
        <div class="kakinhu-footer">
            <form id="kakInhuForm" class="kakinhu-input-wrapper">
                <button type="button" id="kakInhuMicBtn" class="kakinhu-mic-btn" title="Bicara lewat Suara (Voice)" aria-label="Gunakan Suara">
                    <i class="fa-solid fa-microphone"></i>
                </button>
                <input type="text" id="kakInhuInput" class="kakinhu-input" placeholder="Tanya apa saja... (cth: servis AC di Belilas)" autocomplete="off" maxlength="300" required>
                <button type="submit" id="kakInhuSendBtn" class="kakinhu-send-btn" aria-label="Kirim Pesan">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
            <div class="kakinhu-footer-note">
                <small>Didukung AI Cerdas JASA INHU • Data Mitra Nyata 14 Kecamatan</small>
            </div>
        </div>
    </div>
</div>

<!-- STYLES FOR KAK INHU WIDGET -->
<style>
/* Root container */
.kakinhu-root {
    font-family: inherit;
    position: relative;
    z-index: 999990;
}

/* 1. Floating Launcher Button (Bottom-Right) */
.kakinhu-launcher {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 999995;
    background: linear-gradient(135deg, #0d9488 0%, #1e40af 100%);
    color: #ffffff;
    border-radius: 50px;
    padding: 8px 18px 8px 10px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    box-shadow: 0 10px 28px rgba(13, 148, 136, 0.45);
    border: 2px solid rgba(255, 255, 255, 0.35);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    user-select: none;
}

.kakinhu-launcher:hover {
    transform: translateY(-4px) scale(1.03);
    box-shadow: 0 14px 34px rgba(13, 148, 136, 0.6);
    background: linear-gradient(135deg, #0f766e 0%, #1d4ed8 100%);
}

.kakinhu-avatar-wrapper {
    position: relative;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
}

.kakinhu-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255, 255, 255, 0.35);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    padding: 7px;
    box-sizing: border-box;
}

.kakinhu-avatar img,
.kakinhu-header-avatar img,
.kakinhu-msg-avatar img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.kakinhu-online-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 12px;
    height: 12px;
    background-color: #22c55e;
    border: 2px solid #ffffff;
    border-radius: 50%;
    animation: kakInhuPulse 2s infinite;
}

@keyframes kakInhuPulse {
    0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
    100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.kakinhu-launcher-text {
    display: flex;
    flex-direction: column;
    text-align: left;
    line-height: 1.2;
}

.kakinhu-btn-title {
    font-weight: 800;
    font-size: 0.94rem;
    letter-spacing: -0.2px;
}

.kakinhu-btn-sub {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.85);
    font-weight: 600;
}

/* Tooltip Popup */
.kakinhu-tooltip {
    position: fixed;
    bottom: 84px;
    right: 24px;
    z-index: 999994;
    background: #ffffff;
    color: #1e293b;
    border-radius: 16px;
    padding: 12px 14px;
    max-width: 280px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
    border: 1px solid rgba(13, 148, 136, 0.2);
    display: none;
    position: fixed;
    animation: fadeInTooltip 0.4s ease-out;
}

.kakinhu-tooltip::after {
    content: '';
    position: absolute;
    bottom: -8px;
    right: 32px;
    border-width: 8px 8px 0;
    border-style: solid;
    border-color: #ffffff transparent transparent;
}

.kakinhu-tooltip-close {
    position: absolute;
    top: 4px;
    right: 8px;
    background: none;
    border: none;
    font-size: 1.1rem;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
}

/* 2. Chat Window */
.kakinhu-window {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 380px;
    height: 560px;
    max-height: calc(100vh - 48px);
    background: #ffffff;
    border-radius: 24px;
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 999999;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(226, 232, 240, 0.9);
    animation: kakInhuSlideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes kakInhuSlideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Header */
.kakinhu-header {
    background: linear-gradient(135deg, #0d9488 0%, #1e40af 100%);
    color: #ffffff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.kakinhu-header-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.kakinhu-header-avatar {
    width: 38px;
    height: 38px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    border: 1.5px solid rgba(255, 255, 255, 0.4);
    position: relative;
    padding: 6px;
    box-sizing: border-box;
}

.kakinhu-header-ping {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 10px;
    height: 10px;
    background-color: #22c55e;
    border: 1.5px solid #ffffff;
    border-radius: 50%;
}

.kakinhu-header-name {
    font-weight: 700;
    font-size: 0.96rem;
    letter-spacing: -0.2px;
}

.kakinhu-status-indicator {
    width: 6px;
    height: 6px;
    background-color: #22c55e;
    border-radius: 50%;
    display: inline-block;
}

.kakinhu-header-actions {
    display: flex;
    gap: 6px;
}

.kakinhu-btn-icon {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #ffffff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.88rem;
    transition: background 0.2s;
}

.kakinhu-btn-icon:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Body / Message Stream */
.kakinhu-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 14px;
    scroll-behavior: smooth;
}

.kakinhu-msg-row {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    animation: fadeInMsg 0.3s ease-out;
}

.kakinhu-msg-row.user {
    flex-direction: row-reverse;
}

@keyframes fadeInMsg {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.kakinhu-msg-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #0d9488;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
    margin-top: 2px;
    padding: 4px;
    box-sizing: border-box;
}

.kakinhu-bubble {
    max-width: 82%;
    padding: 11px 14px;
    border-radius: 18px;
    font-size: 0.86rem;
    line-height: 1.45;
}

.kakinhu-bubble.bot {
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-top-left-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.kakinhu-bubble.user {
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    color: #ffffff;
    border-top-right-radius: 4px;
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);
}

/* Quick Chips */
.kakinhu-quick-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.kakinhu-chip {
    background: #f1f5f9;
    color: #0f766e;
    border: 1px solid #cbd5e1;
    border-radius: 20px;
    padding: 4px 10px;
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}

.kakinhu-chip:hover {
    background: #0d9488;
    color: #ffffff;
    border-color: #0d9488;
    transform: translateY(-1px);
}

/* Provider Mini Cards in Chat */
.kakinhu-provider-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px;
    margin-top: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    transition: transform 0.2s;
}

.kakinhu-provider-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
}

.kakinhu-provider-header {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 8px;
}

.kakinhu-provider-thumb {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
    background: #e2e8f0;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0d9488;
    font-size: 1.2rem;
}

.kakinhu-provider-name {
    font-weight: 700;
    font-size: 0.88rem;
    color: #0f172a;
    line-height: 1.2;
    margin-bottom: 2px;
}

.kakinhu-provider-meta {
    font-size: 0.74rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.kakinhu-card-actions {
    display: flex;
    gap: 6px;
    margin-top: 8px;
}

.kakinhu-card-btn {
    flex: 1;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 0.76rem;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    transition: all 0.2s;
}

.kakinhu-card-btn.order {
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(13, 148, 136, 0.35);
    border: none;
}

.kakinhu-card-btn.order:hover {
    background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
    color: #ffffff !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.5);
}

.kakinhu-card-btn.profile {
    background: #f1f5f9;
    color: #334155 !important;
    border: 1px solid #cbd5e1;
}

.kakinhu-card-btn.profile:hover {
    background: #e2e8f0;
    color: #0f172a !important;
}

/* Typing Indicator Animation */
.kakinhu-typing-row {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    padding: 0 16px;
    margin-bottom: 8px;
}

.kakinhu-typing-bubble {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 8px 14px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    width: auto;
}

.kakinhu-dot {
    width: 6px;
    height: 6px;
    background-color: #0d9488;
    border-radius: 50%;
    animation: kakInhuBlink 1.4s infinite both;
}

.kakinhu-dot:nth-child(2) { animation-delay: 0.2s; }
.kakinhu-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes kakInhuBlink {
    0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
    40% { transform: scale(1.1); opacity: 1; }
}

/* Footer / Input */
.kakinhu-footer {
    padding: 10px 14px 8px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.kakinhu-input-wrapper {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f1f5f9;
    border-radius: 28px;
    padding: 4px 6px 4px 12px;
    border: 1.5px solid transparent;
    transition: all 0.2s;
}

.kakinhu-input-wrapper:focus-within {
    background: #ffffff;
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
}

.kakinhu-input {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.86rem;
    color: #1e293b;
    padding: 6px 0;
}

.kakinhu-mic-btn {
    background: transparent;
    border: none;
    color: #64748b;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.kakinhu-mic-btn:hover {
    color: #0d9488;
    background: rgba(13, 148, 136, 0.1);
}

.kakinhu-mic-btn.listening {
    color: #ef4444;
    animation: kakInhuPulseRed 1.2s infinite;
}

@keyframes kakInhuPulseRed {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

.kakinhu-send-btn {
    background: #0d9488;
    border: none;
    color: #ffffff;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    transition: all 0.2s;
    flex-shrink: 0;
}

.kakinhu-send-btn:hover {
    background: #0f766e;
    transform: scale(1.05);
}

.kakinhu-footer-note {
    text-align: center;
    font-size: 0.68rem;
    color: #94a3b8;
    margin-top: 4px;
}

/* =========================================================
   RESPONSIVE OPTIMIZATION (SMARTPHONE & DESKTOP)
   ========================================================= */
@media (max-width: 991.98px) {
    .kakinhu-launcher {
        bottom: 78px; /* Rapi tepat di atas mobile bottom navigation bar */
        right: 14px;
        left: auto;
        padding: 6px 14px 6px 8px;
        gap: 8px;
    }
    .kakinhu-avatar-wrapper {
        width: 36px;
        height: 36px;
    }
    .kakinhu-avatar {
        width: 36px;
        height: 36px;
        padding: 5px;
        font-size: 1.1rem;
    }
    .kakinhu-btn-title {
        font-size: 0.84rem;
    }
    .kakinhu-tooltip {
        bottom: 134px;
        right: 14px;
        left: auto;
        max-width: 240px;
    }
    .kakinhu-tooltip::after {
        right: 24px;
        left: auto;
    }
}

@media (max-width: 576px) {
    .kakinhu-window {
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        height: 86vh;
        max-height: 86vh;
        border-radius: 24px 24px 0 0;
        border-bottom: none;
    }
}
</style>

<!-- JAVASCRIPT LOGIC FOR KAK INHU WIDGET -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const launcher = document.getElementById('kakInhuLauncher');
    const windowEl = document.getElementById('kakInhuWindow');
    const closeBtn = document.getElementById('kakInhuCloseBtn');
    const resetBtn = document.getElementById('kakInhuResetBtn');
    const bodyEl = document.getElementById('kakInhuBody');
    const form = document.getElementById('kakInhuForm');
    const input = document.getElementById('kakInhuInput');
    const micBtn = document.getElementById('kakInhuMicBtn');
    const typing = document.getElementById('kakInhuTyping');
    const tooltip = document.getElementById('kakInhuTooltip');
    const tooltipClose = document.getElementById('kakInhuTooltipClose');

    let chatHistory = [];
    let isWaitingResponse = false;
    const kakInhuAvatarUrl = '<?= asset_url('images/tanjak-icon.svg') ?>';

    // 1. Tooltip logic (tampil lembut jika belum pernah ditutup)
    if (tooltip && !sessionStorage.getItem('kakInhuTooltipDismissed')) {
        setTimeout(() => {
            if (windowEl.style.display !== 'flex') {
                tooltip.style.display = 'block';
            }
        }, 2500);

        if (tooltipClose) {
            tooltipClose.addEventListener('click', (e) => {
                e.stopPropagation();
                tooltip.style.display = 'none';
                sessionStorage.setItem('kakInhuTooltipDismissed', 'true');
            });
        }
    }

    // 2. Buka & Tutup Chat Window
    function toggleChat(open) {
        if (open) {
            windowEl.style.display = 'flex';
            launcher.style.display = 'none';
            if (tooltip) tooltip.style.display = 'none';
            setTimeout(() => input.focus(), 300);
            scrollToBottom();
        } else {
            windowEl.style.display = 'none';
            launcher.style.display = 'inline-flex';
        }
    }

    launcher.addEventListener('click', () => toggleChat(true));
    closeBtn.addEventListener('click', () => toggleChat(false));

    // 3. Reset / Clear Obrolan
    resetBtn.addEventListener('click', () => {
        if (confirm('Mulai obrolan baru dengan Kak Inhu?')) {
            chatHistory = [];
            // Kembalikan ke pesan sapaan awal
            const defaultGreeting = `
                <div class="kakinhu-msg-row bot">
                    <div class="kakinhu-msg-avatar">
                        <img src="${kakInhuAvatarUrl}" alt="Kak Inhu">
                    </div>
                    <div class="kakinhu-bubble bot">
                        <p class="mb-1 fw-semibold text-teal" style="font-size: 0.82rem;">
                            <i class="fa-solid fa-sparkles me-1 text-warning"></i> Halo kembali! Saya Kak Inhu 🙋‍♂️
                        </p>
                        <p class="mb-2 small">
                            Ada keluhan atau butuh bantuan tukang apa hari ini di Indragiri Hulu? Ceritakan saja, saya carikan mitranya!
                        </p>
                        <div class="kakinhu-quick-chips">
                            <span class="kakinhu-chip" data-query="Butuh tukang cuci AC di Air Molek">❄️ Servis AC Air Molek</span>
                            <span class="kakinhu-chip" data-query="Motor saya mogok butuh montir di Rengat">🏍️ Montir Mogok Rengat</span>
                            <span class="kakinhu-chip" data-query="Cari regu panen sawit di Belilas">🌴 Regu Panen Sawit</span>
                            <span class="kakinhu-chip" data-query="Ada tukang las bikin kanopi di Pematang Reba?">🔥 Bengkel Las Inhu</span>
                            <span class="kakinhu-chip" data-query="Butuh tukang perbaikan listrik korslet">⚡ Servis Listrik</span>
                        </div>
                    </div>
                </div>
            `;
            bodyEl.innerHTML = defaultGreeting;
            attachChipListeners();
            input.value = '';
            input.focus();
        }
    });

    // 4. Scroll ke bawah
    function scrollToBottom() {
        bodyEl.scrollTop = bodyEl.scrollHeight;
    }

    // 5. Tambah Pesan User ke Body
    function appendUserMessage(text) {
        const row = document.createElement('div');
        row.className = 'kakinhu-msg-row user';
        row.innerHTML = `<div class="kakinhu-bubble user">${escapeHtml(text)}</div>`;
        bodyEl.appendChild(row);
        scrollToBottom();
    }

    // 6. Format Markdown sederhana (bold, list, newline)
    function formatMarkdown(text) {
        let escaped = escapeHtml(text);
        // Bold: **teks**
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Bullet list: • teks
        escaped = escaped.replace(/^[•\-\*]\s+(.*)$/gm, '<li class="mb-1">$1</li>');
        escaped = escaped.replace(/(<li.*<\/li>)/s, '<ul class="ps-3 mb-2 small">$1</ul>');
        // Newlines
        escaped = escaped.replace(/\n\n/g, '<br><br>');
        escaped = escaped.replace(/\n/g, '<br>');
        return escaped;
    }

    function escapeHtml(string) {
        const div = document.createElement('div');
        div.textContent = string;
        return div.innerHTML;
    }

    // 7. Tambah Pesan Bot + Kartu Mitra ke Body
    function appendBotResponse(reply, providers = [], quickReplies = []) {
        const row = document.createElement('div');
        row.className = 'kakinhu-msg-row bot';

        let formattedText = formatMarkdown(reply);

        // Generate Kartu Mitra jika ada
        let providersHtml = '';
        if (providers && providers.length > 0) {
            providersHtml += '<div class="kakinhu-providers-list">';
            providers.forEach(p => {
                const verifiedBadge = p.is_verified ? '<span class="badge text-bg-success py-0 px-1 ms-1" style="font-size: 0.65rem;"><i class="fa-solid fa-check"></i> Terverifikasi</span>' : '';
                const nearbyBadge = p.is_nearby ? '<span class="badge text-bg-warning text-dark py-0 px-1 ms-1" style="font-size: 0.62rem;">Terdekat</span>' : '';
                
                let thumbHtml = '';
                if (p.image_url) {
                    thumbHtml = `<img src="${p.image_url}" class="kakinhu-provider-thumb" alt="${escapeHtml(p.business_name)}" onerror="this.outerHTML='<div class=\\'kakinhu-provider-thumb\\'><i class=\\'fa-solid ${p.category_icon || 'fa-wrench'}\\'></i></div>'">`;
                } else {
                    thumbHtml = `<div class="kakinhu-provider-thumb"><i class="fa-solid ${p.category_icon || 'fa-wrench'}"></i></div>`;
                }

                providersHtml += `
                    <div class="kakinhu-provider-card">
                        <div class="kakinhu-provider-header">
                            ${thumbHtml}
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="kakinhu-provider-name text-truncate">
                                    ${escapeHtml(p.business_name)} ${verifiedBadge} ${nearbyBadge}
                                </div>
                                <div class="kakinhu-provider-meta">
                                    <span><i class="fa-solid fa-map-pin text-danger"></i> ${escapeHtml(p.district_name)}</span>
                                    <span>•</span>
                                    <span><i class="fa-solid fa-star text-warning"></i> ${p.rating_avg}</span>
                                    <span>•</span>
                                    <span class="fw-semibold text-teal">${p.rate_display}</span>
                                </div>
                            </div>
                        </div>
                        <div class="kakinhu-card-actions">
                            <a href="${p.order_url || p.profile_url}" class="kakinhu-card-btn order"><i class="fa-solid fa-bolt text-warning"></i> Pesan Jasa</a>
                            <a href="${p.profile_url}" class="kakinhu-card-btn profile"><i class="fa-solid fa-store"></i> Lihat Toko</a>
                        </div>
                    </div>
                `;
            });
            providersHtml += '</div>';
        }

        // Generate Quick Chips jika ada
        let chipsHtml = '';
        if (quickReplies && quickReplies.length > 0) {
            chipsHtml += '<div class="kakinhu-quick-chips mt-2">';
            quickReplies.forEach(chip => {
                chipsHtml += `<span class="kakinhu-chip" data-query="${escapeHtml(chip)}">${escapeHtml(chip)}</span>`;
            });
            chipsHtml += '</div>';
        }

        row.innerHTML = `
            <div class="kakinhu-msg-avatar">
                <img src="${kakInhuAvatarUrl}" alt="Kak Inhu">
            </div>
            <div class="kakinhu-bubble bot">
                ${formattedText}
                ${providersHtml}
                ${chipsHtml}
            </div>
        `;

        bodyEl.appendChild(row);
        attachChipListeners();
        scrollToBottom();
    }

    // 8. Kirim Pesan ke API
    async function sendMessage(text) {
        if (!text || isWaitingResponse) return;

        isWaitingResponse = true;
        appendUserMessage(text);
        input.value = '';

        // Tampilkan indikator mengetik
        typing.classList.remove('d-none');
        scrollToBottom();

        try {
            const formData = new FormData();
            formData.append('message', text);
            formData.append('history', JSON.stringify(chatHistory));

            const resp = await fetch('<?= BASE_URL ?>/ai_chat.php', {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();
            typing.classList.add('d-none');
            isWaitingResponse = false;

            if (data.status === 'success') {
                appendBotResponse(data.reply, data.providers, data.quick_replies);
                // Simpan ke riwayat obrolan sesi
                chatHistory.push({ role: 'user', content: text });
                chatHistory.push({ role: 'model', content: data.reply });
            } else {
                appendBotResponse(data.message || 'Maaf, terjadi kesalahan saat memproses jawaban.');
            }
        } catch (err) {
            console.error('Kak Inhu Chat Error:', err);
            typing.classList.add('d-none');
            isWaitingResponse = false;
            appendBotResponse('Koneksi terputus sesaat. Silakan coba kirim kembali pesan Anda.');
        }
    }

    // Event Submit Form
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        sendMessage(input.value.trim());
    });

    // 9. Attach Event Listener ke Quick Chips
    function attachChipListeners() {
        document.querySelectorAll('.kakinhu-chip').forEach(chip => {
            chip.onclick = function() {
                const query = this.getAttribute('data-query') || this.textContent.trim();
                sendMessage(query);
            };
        });
    }
    attachChipListeners();

    // 10. Fitur Voice Input / Speech Recognition
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition && micBtn) {
        const recognition = new SpeechRecognition();
        recognition.lang = 'id-ID';
        recognition.continuous = false;
        recognition.interimResults = false;

        let isListening = false;

        micBtn.addEventListener('click', () => {
            if (isListening) {
                recognition.stop();
                return;
            }
            try {
                recognition.start();
                isListening = true;
                micBtn.classList.add('listening');
                input.placeholder = 'Mendengarkan suara Anda...';
            } catch (err) {
                console.warn('SpeechRecognition start error:', err);
            }
        });

        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            input.value = transcript;
            micBtn.classList.remove('listening');
            input.placeholder = 'Tanya apa saja... (cth: servis AC di Belilas)';
            isListening = false;
            sendMessage(transcript);
        };

        recognition.onerror = () => {
            micBtn.classList.remove('listening');
            input.placeholder = 'Tanya apa saja... (cth: servis AC di Belilas)';
            isListening = false;
        };

        recognition.onend = () => {
            micBtn.classList.remove('listening');
            input.placeholder = 'Tanya apa saja... (cth: servis AC di Belilas)';
            isListening = false;
        };
    } else if (micBtn) {
        micBtn.style.display = 'none'; // Sembunyikan jika browser tidak mendukung Web Speech
    }
});
</script>

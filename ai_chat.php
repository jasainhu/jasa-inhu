<?php
/**
 * API Endpoint: Chat dengan Kak Inhu (AI Agent)
 * JASA INHU - Marketplace Jasa Lokal Kabupaten Indragiri Hulu
 * (Placed at root to keep Vercel Hobby serverless functions <= 12)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ai_agent.php';

// Memastikan session aman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Cek apakah AI Agent diaktifkan oleh admin
if (get_setting('ai_agent_enabled', '1') === '0') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Layanan Asisten AI Kak Inhu sedang dalam pemeliharaan berkala. Silakan gunakan menu pencarian atau hubungi CS Admin via WhatsApp.'
    ]);
    exit;
}

// 2. Proteksi Anti-Flood / Rate Limiting (Maksimal 1 request per 0.8 detik per user session)
$now = microtime(true);
if (isset($_SESSION['ai_last_chat_time']) && ($now - $_SESSION['ai_last_chat_time']) < 0.8) {
    http_response_code(429);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Mohon tunggu sebentar sebelum mengirim pertanyaan berikutnya.'
    ]);
    exit;
}
$_SESSION['ai_last_chat_time'] = $now;

// 3. Baca Input JSON atau Form POST
$rawInput = file_get_contents('php://input');
$data = [];
if (!empty($rawInput)) {
    $data = json_decode($rawInput, true) ?: [];
}
if (empty($data)) {
    $data = $_POST;
}

$message = trim((string)($data['message'] ?? ''));
$history = is_array($data['history'] ?? null) ? $data['history'] : [];

if ($message === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Silakan ketik pertanyaan atau kebutuhan jasa Anda.'
    ]);
    exit;
}

// Batasi panjang pesan untuk keamanan
if (mb_strlen($message) > 500) {
    $message = mb_substr($message, 0, 500);
}

try {
    $result = JasaInhuAIAgent::handleMessage($message, $history);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log("API ai_chat error: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'message' => 'Maaf, terjadi kendala teknis saat memproses pesan Anda. Silakan coba kembali sesaat lagi.'
    ]);
}

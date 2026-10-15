<?php
/**
 * Secure Document Viewer - JASA INHU
 * Menyajikan berkas identitas KTP dan sertifikat / STR penyedia jasa
 * Mengatasi pemblokiran navigasi tab data: URI pada browser modern (Chrome, Edge, dll).
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Pastikan pengguna sudah login
$current_user = current_user();
if (!$current_user) {
    http_response_code(403);
    die('Akses ditolak: Silakan masuk ke akun Anda terlebih dahulu.');
}

$user_id = (int)($_GET['user_id'] ?? 0);
$topup_id = (int)($_GET['topup_id'] ?? 0);
$type = strtolower(trim($_GET['type'] ?? 'ktp'));
$download = isset($_GET['download']) && $_GET['download'] === '1';

$db = get_db();

// 1. Jika melihat Bukti Struk Top-Up Dompet
if ($topup_id > 0 || $type === 'topup' || $type === 'struk') {
    $stmtTopup = $db->prepare("
        SELECT wtr.id, wtr.proof_image, wtr.amount, wtr.payment_method, sp.user_id, u.name, sp.business_name
        FROM wallet_topup_requests wtr
        JOIN service_providers sp ON wtr.provider_id = sp.id
        JOIN users u ON sp.user_id = u.id
        WHERE wtr.id = ?
    ");
    $stmtTopup->execute([$topup_id]);
    $topup = $stmtTopup->fetch();

    if (!$topup) {
        http_response_code(404);
        die('Data pengajuan isi saldo tidak ditemukan.');
    }

    if ($current_user['role_name'] !== 'admin' && (int)$current_user['id'] !== (int)$topup['user_id']) {
        http_response_code(403);
        die('Akses ditolak: Anda tidak memiliki izin untuk melihat bukti transfer ini.');
    }

    $rawMedia = $topup['proof_image'] ?? '';
    if (empty($rawMedia)) {
        http_response_code(404);
        die('Bukti transfer belum diunggah untuk pengajuan ini.');
    }

    $doc = [
        'name' => 'struk_topup_' . $topup['id'] . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $topup['business_name'])
    ];
} else {
    // 2. Berkas KTP atau Sertifikat / STR
    if ($user_id <= 0) {
        $user_id = (int)$current_user['id'];
    }

    // Cek hak akses: hanya administrator atau pengguna pemilik yang boleh melihat
    if ($current_user['role_name'] !== 'admin' && (int)$current_user['id'] !== $user_id) {
        http_response_code(403);
        die('Akses ditolak: Anda tidak memiliki izin untuk melihat dokumen ini.');
    }

    $stmt = $db->prepare("
        SELECT sp.id_card_image, sp.id_card_number, sp.certificate_url, sp.credential_title, u.name, sp.business_name
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        http_response_code(404);
        die('Data dokumen penyedia jasa tidak ditemukan.');
    }

    $rawMedia = ($type === 'str' || $type === 'certificate') ? ($doc['certificate_url'] ?? '') : ($doc['id_card_image'] ?? '');

    if (empty($rawMedia)) {
        http_response_code(404);
        die('Dokumen ' . strtoupper($type) . ' belum diunggah.');
    }
}

// 1. Jika Base64 Data URI
if (str_starts_with($rawMedia, 'data:')) {
    $parts = explode(',', $rawMedia, 2);
    if (count($parts) !== 2 || empty($parts[1]) || strlen(trim($parts[1])) < 30) {
        http_response_code(400);
        die("
            <div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; text-align: center; padding: 60px 20px; color: #334155;'>
                <div style='font-size: 48px; margin-bottom: 16px;'>⚠️</div>
                <h2 style='font-weight: 700; color: #0f172a; margin-bottom: 8px;'>Dokumen Tidak Lengkap / Rusak</h2>
                <p style='max-width: 480px; margin: 0 auto 20px; line-height: 1.6; color: #64748b; font-size: 14px;'>
                    Dokumen identitas yang diunggah oleh <strong>" . htmlspecialchars($doc['name'] ?? 'Mitra', ENT_QUOTES) . "</strong> berukuran 0 bytes (tidak terkirim secara utuh saat pengguna mengunggah).
                </p>
                <div style='background: #f1f5f9; padding: 12px 20px; border-radius: 8px; display: inline-block; font-size: 13px; color: #475569;'>
                    💡 <strong>Solusi:</strong> Silakan minta mitra ini untuk mengunggah ulang foto dokumen KTP aslinya di menu profil.
                </div>
            </div>
        ");
    }

    $meta = $parts[0];
    $mime = 'image/jpeg';
    if (preg_match('/data:([^;]+)/', $meta, $matches)) {
        $mime = trim($matches[1]);
    }

    $binary = base64_decode($parts[1]);
    if ($binary === false || strlen($binary) === 0) {
        http_response_code(400);
        die("
            <div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; text-align: center; padding: 60px 20px; color: #334155;'>
                <div style='font-size: 48px; margin-bottom: 16px;'>⚠️</div>
                <h2 style='font-weight: 700; color: #0f172a; margin-bottom: 8px;'>Dokumen Gagal Diuraikan</h2>
                <p style='max-width: 480px; margin: 0 auto 20px; line-height: 1.6; color: #64748b; font-size: 14px;'>
                    Format foto dokumen rusak atau tidak terbaca. Silakan minta mitra mengunggah foto ulang.
                </p>
            </div>
        ");
    }

    $ext = 'jpg';
    if (str_contains($mime, 'png')) {
        $ext = 'png';
    } elseif (str_contains($mime, 'pdf')) {
        $ext = 'pdf';
    } elseif (str_contains($mime, 'webp')) {
        $ext = 'webp';
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $doc['name']);
    $filename = "{$type}_{$safeName}_{$user_id}.{$ext}";
    $disposition = $download ? 'attachment' : 'inline';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($binary));
    header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=86400');
    echo $binary;
    exit;
}

// 2. Jika File Lokal di server
$cleanPath = ltrim($rawMedia, '/');
$localPath = __DIR__ . '/' . $cleanPath;
if (file_exists($localPath)) {
    $mime = mime_content_type($localPath) ?: 'application/octet-stream';
    $ext = pathinfo($localPath, PATHINFO_EXTENSION);
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $doc['name']);
    $filename = "{$type}_{$safeName}_{$user_id}.{$ext}";
    $disposition = $download ? 'attachment' : 'inline';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($localPath));
    header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=86400');
    readfile($localPath);
    exit;
}

// 3. Jika URL Eksternal (http/https)
if (str_starts_with($rawMedia, 'http://') || str_starts_with($rawMedia, 'https://')) {
    header("Location: " . $rawMedia);
    exit;
}

http_response_code(404);
die('Berkas dokumen tidak ditemukan di penyimpanan server.');

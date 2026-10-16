<?php
/**
 * Media Streamer & Optimizer - JASA INHU
 * Menyajikan gambar (Portofolio, Profil Mitra, Avatar) sebagai berkas biner HTTP asli
 * dengan dukungan HTTP Caching kuat (Cache-Control & ETag).
 * Mencegah lonjakan payload HTML (FUNCTION_RESPONSE_PAYLOAD_TOO_LARGE di Vercel)
 * akibat penyisipan Base64 data URI secara inline.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$field = $_GET['field'] ?? 'after';

$default_banner = __DIR__ . '/assets/images/og-banner.jpg';
$default_logo = __DIR__ . '/assets/images/logo.png';

function stream_media_binary(?string $media, string $fallbackFile) {
    if (empty($media)) {
        stream_fallback_file($fallbackFile);
        exit;
    }

    // 1. Jika URL Eksternal (http / https), redirect
    if (str_starts_with($media, 'http://') || str_starts_with($media, 'https://')) {
        header('Location: ' . $media, true, 302);
        exit;
    }

    // 2. Jika Berkas Fisik Lokal di Server
    if (!str_starts_with($media, 'data:')) {
        $localPath = __DIR__ . '/' . ltrim($media, '/');
        if (file_exists($localPath) && is_file($localPath)) {
            $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
            $mime = match($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => 'image/jpeg'
            };
            $etag = '"' . md5_file($localPath) . '"';
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
                http_response_code(304);
                exit;
            }
            header('Content-Type: ' . $mime);
            header('ETag: ' . $etag);
            header('Cache-Control: public, max-age=604800, stale-while-revalidate=86400');
            header('Content-Length: ' . filesize($localPath));
            readfile($localPath);
            exit;
        }
        stream_fallback_file($fallbackFile);
        exit;
    }

    // 3. Jika Data URI Base64 (data:image/xxx;base64,...)
    $parts = explode(',', $media, 2);
    if (count($parts) !== 2) {
        stream_fallback_file($fallbackFile);
        exit;
    }

    $headerPart = $parts[0];
    $dataPart = $parts[1];

    $mime = 'image/jpeg';
    if (preg_match('/data:([^;]+);base64/', $headerPart, $matches)) {
        $mime = $matches[1];
    }

    $binary = base64_decode($dataPart);
    if ($binary === false || strlen($binary) === 0) {
        stream_fallback_file($fallbackFile);
        exit;
    }

    $etag = '"' . md5($binary) . '"';
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=604800, stale-while-revalidate=86400');
    header('Content-Length: ' . strlen($binary));
    echo $binary;
    exit;
}

function stream_fallback_file(string $file) {
    if (file_exists($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($file);
    } else {
        http_response_code(404);
    }
    exit;
}

if ($id <= 0) {
    stream_fallback_file($default_logo);
}

try {
    $db = get_db();

    // A. PORTFOLIO IMAGE
    if ($type === 'portfolio') {
        $stmt = $db->prepare("SELECT image_before, image_after FROM provider_portfolios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            stream_fallback_file($default_banner);
        }
        $img = ($field === 'before') ? ($row['image_before'] ?? '') : ($row['image_after'] ?? '');
        if (empty($img)) {
            // Jika field yang diminta kosong, coba fallback ke field lainnya
            $img = !empty($row['image_after']) ? $row['image_after'] : ($row['image_before'] ?? '');
        }
        stream_media_binary($img, $default_banner);
    }

    // B. PROVIDER PROFILE PICTURE / COVER
    if ($type === 'provider') {
        $stmt = $db->prepare("
            SELECT sp.image_url, p.avatar as owner_avatar
            FROM service_providers sp
            LEFT JOIN profiles p ON sp.user_id = p.user_id
            WHERE sp.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            stream_fallback_file($default_logo);
        }
        $img = !empty($row['image_url']) ? $row['image_url'] : ($row['owner_avatar'] ?? '');
        stream_media_binary($img, $default_logo);
    }

    // C. USER AVATAR
    if ($type === 'avatar' || $type === 'user') {
        $stmt = $db->prepare("SELECT avatar FROM profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $img = $row['avatar'] ?? '';
        stream_media_binary($img, $default_logo);
    }

    stream_fallback_file($default_logo);
} catch (Throwable $e) {
    stream_fallback_file($default_logo);
}

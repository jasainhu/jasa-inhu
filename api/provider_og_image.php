<?php
/**
 * Streamer Gambar Profil Toko Mitra untuk Open Graph (WhatsApp, FB, Telegram Preview)
 * JASA INHU - Marketplace Jasa Lokal Kab. Indragiri Hulu
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$provider_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
$default_banner = __DIR__ . '/../assets/images/og-banner.jpg';

if ($provider_id <= 0) {
    if (file_exists($default_banner)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=86400');
        readfile($default_banner);
    }
    exit;
}

try {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT sp.image_url, p.avatar as owner_avatar
        FROM service_providers sp
        LEFT JOIN profiles p ON sp.user_id = p.user_id
        WHERE sp.id = ?
        LIMIT 1
    ");
    $stmt->execute([$provider_id]);
    $row = $stmt->fetch();

    $photo = '';
    if ($row) {
        $photo = has_media($row['image_url'] ?? '') ? $row['image_url'] : ($row['owner_avatar'] ?? '');
    }

    if (empty($photo) || !has_media($photo)) {
        if (file_exists($default_banner)) {
            header('Content-Type: image/jpeg');
            header('Cache-Control: public, max-age=86400');
            readfile($default_banner);
        }
        exit;
    }

    // 1. Jika Base64 Data URI
    if (str_starts_with($photo, 'data:')) {
        $parts = explode(',', $photo, 2);
        if (count($parts) === 2) {
            $headerPart = $parts[0];
            $dataPart = $parts[1];
            
            $mime = 'image/jpeg';
            if (preg_match('/data:([^;]+);base64/', $headerPart, $matches)) {
                $mime = $matches[1];
            }

            $binary = base64_decode($dataPart);
            if ($binary !== false) {
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . strlen($binary));
                header('Cache-Control: public, max-age=86400');
                echo $binary;
                exit;
            }
        }
    }

    // 2. Jika External URL (http / https)
    if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
        header('Location: ' . $photo, true, 302);
        exit;
    }

    // 3. Jika File Lokal di server
    $filePath = __DIR__ . '/../' . ltrim($photo, '/');
    if (file_exists($filePath) && is_file($filePath)) {
        $mime = mime_content_type($filePath) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        exit;
    }

    // Fallback jika file tidak ditemukan
    if (file_exists($default_banner)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=86400');
        readfile($default_banner);
    }
} catch (Exception $e) {
    if (file_exists($default_banner)) {
        header('Content-Type: image/jpeg');
        readfile($default_banner);
    }
}
exit;

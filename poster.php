<?php
/**
 * Direct Poster Viewer / Downloader: JASA INHU
 */
$posterFile = __DIR__ . '/assets/images/poster-jasa-inhu-melayu.jpg';
if (!file_exists($posterFile)) {
    $posterFile = __DIR__ . '/assets/images/poster-mitra-inhu.jpg';
}

if (file_exists($posterFile)) {
    $isDownload = isset($_GET['download']) || isset($_GET['unduh']);
    $disposition = $isDownload ? 'attachment' : 'inline';
    
    header('Content-Type: image/jpeg');
    header('Content-Disposition: ' . $disposition . '; filename="poster-promo-jasa-inhu.jpg"');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . filesize($posterFile));
    readfile($posterFile);
    exit;
}

http_response_code(404);
echo "File poster belum ditemukan.";

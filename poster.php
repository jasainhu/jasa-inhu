<?php
/**
 * Direct Poster Viewer / Downloader: JASA INHU
 */
$posterFile = __DIR__ . '/assets/images/poster-mitra-inhu.jpg';
if (file_exists($posterFile)) {
    header('Content-Type: image/jpeg');
    header('Content-Disposition: inline; filename="poster-mitra-jasa-inhu.jpg"');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . filesize($posterFile));
    readfile($posterFile);
    exit;
}

http_response_code(404);
echo "File poster belum ditemukan.";

<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$db = get_db();

// Simulasikan eksekusi index.php dan ukur per bagian
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

ob_start();
require __DIR__ . '/../index.php';
$html = ob_get_clean();

echo "TOTAL HTML LENGTH: " . strlen($html) . " bytes\n\n";

// Cek tag <script>
preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $html, $scripts);
echo "SCRIPTS OVER 50KB:\n";
foreach ($scripts[0] as $i => $s) {
    if (strlen($s) > 50000) {
        echo "Script #$i: " . strlen($s) . " bytes\n";
        echo substr($s, 0, 200) . "...\n\n";
    }
}

// Cek tag <img> src data:
preg_match_all('/<img\b[^>]+src=["\'](data:[^"\']+)["\']/is', $html, $imgs);
echo "IMG DATA URIS OVER 50KB: " . count($imgs[1]) . " items\n";
foreach ($imgs[1] as $idx => $src) {
    echo "Img #$idx: " . strlen($src) . " bytes\n";
}

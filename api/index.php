<?php
/**
 * Vercel Serverless Gateway & Router: JASA INHU
 * Menghubungkan seluruh permintaan web ke file aplikasi PHP di root
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = urldecode($path);

// Set working directory ke root proyek agar require relatif berjalan normal
chdir(__DIR__ . '/..');

// 1. Root /
if ($path === '/' || $path === '') {
    require __DIR__ . '/../index.php';
    exit;
}

// 1.0 Favicon Handler
if ($path === '/favicon.ico') {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    $icoPath = __DIR__ . '/../assets/images/favicon-32x32.png';
    if (file_exists($icoPath)) {
        readfile($icoPath);
    }
    exit;
}

// 1.0.1 Direct APK Download Handler
if ($path === '/download/jasainhu.apk' || $path === '/jasainhu.apk') {
    $apkPath = __DIR__ . '/../download/jasainhu.apk';
    if (file_exists($apkPath)) {
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="JasaInhu.apk"');
        header('Content-Length: ' . filesize($apkPath));
        header('Cache-Control: public, max-age=86400');
        readfile($apkPath);
        exit;
    }
}

// 1.1 Service Worker PWA Handler
if ($path === '/sw.js') {
    header('Content-Type: application/javascript; charset=UTF-8');
    header('Service-Worker-Allowed: /');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo "// JASA INHU - Service Worker PWA\n";
    echo "const CACHE_NAME = 'jasainhu-pwa-v1';\n";
    echo "self.addEventListener('install', e => { self.skipWaiting(); });\n";
    echo "self.addEventListener('activate', e => { e.waitUntil(self.clients.claim()); });\n";
    echo "self.addEventListener('fetch', e => {\n";
    echo "  if (e.request.method !== 'GET') return;\n";
    echo "  e.respondWith(fetch(e.request).catch(() => caches.match(e.request)));\n";
    echo "});\n";
    exit;
}

// 1.2 Manifest PWA Handler
if ($path === '/manifest.json') {
    header('Content-Type: application/manifest+json; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    echo json_encode([
        'name' => 'JASA INHU - Layanan Jasa Indragiri Hulu',
        'short_name' => 'Jasa Inhu',
        'description' => 'Platform digital jasa & tukang terpercaya di Kabupaten Indragiri Hulu, Riau',
        'id' => '/',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#0d9488',
        'orientation' => 'portrait',
        'icons' => [
            ['src' => '/assets/images/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/assets/images/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/assets/images/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/assets/images/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable']
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$file = ltrim($path, '/');
$fullPath = __DIR__ . '/../' . $file;

// 2. Jika path mengarah ke file .php langsung (misal: /login.php, /user/requests.php, /admin/users.php)
if (is_file($fullPath) && str_ends_with($file, '.php')) {
    require $fullPath;
    exit;
}

// 3. Jika path adalah folder dengan index.php (misal: /user, /provider, /admin)
if (is_dir($fullPath) && is_file($fullPath . '/index.php')) {
    require $fullPath . '/index.php';
    exit;
}

// 4. Jika path tanpa ekstensi .php (misal: /login -> /login.php)
if (is_file($fullPath . '.php')) {
    require $fullPath . '.php';
    exit;
}

// 5. File statis fallback jika tidak ditangani Vercel static routing
if (is_file($fullPath)) {
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'json' => 'application/json',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf'
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    readfile($fullPath);
    exit;
}

http_response_code(404);
echo "404 - Halaman Tidak Ditemukan di JASA INHU";

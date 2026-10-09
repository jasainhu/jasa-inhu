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

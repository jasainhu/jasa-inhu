<?php
/**
 * Konfigurasi Aplikasi JASA INHU
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/session_handler.php';

// Pastikan session sudah aktif dengan database session handler agar login awet dan tidak logout sendiri
if (session_status() === PHP_SESSION_NONE) {
    session_set_save_handler(new DatabaseSessionHandler(), true);
    session_start();
}

// Set zona waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

// Identitas Aplikasi
define('APP_NAME', 'JASA INHU');
define('APP_TAGLINE', 'Temukan jasa yang kamu butuhkan di sekitar kamu');
define('APP_REGION', 'Kabupaten Indragiri Hulu, Riau');
define('APP_VERSION', '1.0.0');

// Deteksi Base URL secara dinamis
function get_base_url(): string {
    if (getenv('APP_URL')) {
        return rtrim(getenv('APP_URL'), '/');
    }
    if (isset($_SERVER['HTTP_HOST'])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (str_ends_with($_SERVER['HTTP_HOST'], '.vercel.app'));
        
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        
        // Deteksi subdirektori jika dijalankan di htdocs XAMPP (misal /JASA-INHU)
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        
        // Bersihkan path subdirektori dari folder admin, user, provider, api, database jika ada
        $cleanDir = preg_replace('/(\/(admin|user|provider|api|database|includes))(\/.*)?$/', '', $dir);
        if ($cleanDir === '/' || $cleanDir === '\\') {
            $cleanDir = '';
        }
        
        return rtrim($protocol . $host . $cleanDir, '/');
    }
    return 'http://localhost';
}

define('BASE_URL', get_base_url());

// Konfigurasi Biaya Kontak (Lead Fee) & Dompet Mitra
define('DEFAULT_LEAD_FEE', 3000);         // Rp 3.000 per order yang diterima mitra
define('WELCOME_BONUS_WALLET', 45000);    // Rp 45.000 (15 Pesanan Pertama GRATIS untuk mitra baru)
define('MIN_TOPUP_AMOUNT', 20000);        // Minimal isi saldo Rp 20.000
define('ADMIN_PHONE_WA', '6285126241679'); // Nomor WhatsApp Layanan Admin JASA INHU (+62 851-2624-1679)
define('ADMIN_EMAIL', 'jasainhu@gmail.com');   // Alamat Email Resmi Layanan JASA INHU

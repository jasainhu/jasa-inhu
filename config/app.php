<?php
/**
 * Konfigurasi Aplikasi JASA INHU
 */

// Pastikan session sudah aktif
if (session_status() === PHP_SESSION_NONE) {
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
    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        
        // Deteksi subdirektori jika dijalankan di htdocs XAMPP (misal /JASA-INHU)
        $script = $_SERVER['SCRIPT_NAME'];
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

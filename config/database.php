<?php
/**
 * Konfigurasi Database JASA INHU
 * Mendukung XAMPP Standar (Port 3306) & Port Kustom (Port 3391)
 */

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');
$db_name = getenv('DB_NAME') ?: 'jasa_inhu';
$env_port = getenv('DB_PORT');

// Deteksi port database
if ($env_port) {
    $db_port = (int)$env_port;
} else {
    // Port default XAMPP adalah 3306.
    // Jika tidak dapat dihubungi dan port 3391 aktif, gunakan 3391 secara otomatis.
    $db_port = 3306;
    $sock = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 0.2);
    if ($sock) {
        fclose($sock);
        $db_port = 3306;
    } else {
        $sockAlt = @fsockopen('127.0.0.1', 3391, $errno, $errstr, 0.2);
        if ($sockAlt) {
            fclose($sockAlt);
            $db_port = 3391;
        }
    }
}

define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);

/**
 * Mendapatkan koneksi PDO ke database
 *
 * @param bool $select_db Apakah langsung memilih database jasa_inhu
 * @return PDO
 */
function get_db(bool $select_db = true): PDO {
    static $pdo = null;

    if ($pdo !== null && $select_db) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8";
    if ($select_db) {
        $dsn .= ";dbname=" . DB_NAME;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Dukungan otomatis SSL untuk database cloud (TiDB Cloud, Aiven, Railway, dll)
    if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
        $caFile = __DIR__ . '/cacert.pem';
        if (file_exists($caFile) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
        }
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }

    try {
        $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        if ($select_db) {
            $pdo = $conn;
        }
        return $conn;
    } catch (PDOException $e) {
        // Tampilkan pesan kesalahan yang informatif untuk mempermudah setup
        $msg = "Koneksi database gagal (Host: " . DB_HOST . ":" . DB_PORT . ", DB: " . DB_NAME . "): " . $e->getMessage();
        error_log($msg);
        throw new Exception($msg);
    }
}

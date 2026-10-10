<?php
/**
 * Pengalihan Mode Profil: JASA INHU
 * Memungkinkan Mitra Jasa beralih antara Mode Mitra dan Mode Pengguna Biasa secara konsisten
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$user = current_user();
$to = strtolower(trim((string)($_GET['to'] ?? '')));
$current_role = strtolower((string)($_SESSION['user_role'] ?? ''));

// Hanya akun dengan role penyedia yang memiliki dua sisi peran
if ($current_role === 'penyedia') {
    if ($to === 'pengguna') {
        $_SESSION['active_profile_mode'] = 'pengguna';
        set_flash('info', 'Anda beralih ke Profil Pengguna Biasa. Anda dapat memesan jasa dari mitra lain di Inhu.');
        $redirect = !empty($_GET['redirect']) ? $_GET['redirect'] : '/user/profile.php';
        redirect($redirect);
    } elseif ($to === 'penyedia') {
        $_SESSION['active_profile_mode'] = 'penyedia';
        set_flash('success', 'Selamat datang kembali di Profil Mitra Jasa.');
        $redirect = !empty($_GET['redirect']) ? $_GET['redirect'] : '/provider/profile.php';
        redirect($redirect);
    }
}

redirect('/');

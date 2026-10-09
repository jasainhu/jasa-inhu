<?php
/**
 * Handler Logout JASA INHU
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();

// Mulai session baru hanya untuk flash message
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

set_flash('info', 'Anda telah berhasil keluar dari akun JASA INHU.');
redirect('/login.php');

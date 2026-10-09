<?php
/**
 * Dashboard Pengguna: Dialihkan ke Profil Saya & Alamat Domisili
 * Pengguna biasa menggunakan antarmuka modern di /user/profile.php dan /user/requests.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('pengguna');

// Alihkan pengguna biasa langsung ke Profil Saya
redirect('/user/profile.php');

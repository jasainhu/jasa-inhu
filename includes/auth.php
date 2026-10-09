<?php
/**
 * Authentication & Authorization Handler JASA INHU
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

/**
 * Cek apakah user sedang login
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Ambil data user yang sedang login
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    
    // Cache di static variable untuk efisiensi
    static $user_cache = null;
    if ($user_cache !== null) {
        return $user_cache;
    }

    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT u.id, u.role_id, u.name, u.email, u.phone, u.is_active, u.email_verified_at,
                   r.name as role_name, r.display_name as role_display,
                   p.avatar, p.bio, p.gender, p.birth_date, p.address, p.district_id, p.village_id,
                   d.name as district_name, v.name as village_name,
                   sp.id as provider_id, sp.business_name, sp.is_verified, sp.rating_avg
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN profiles p ON u.id = p.user_id
            LEFT JOIN districts d ON p.district_id = d.id
            LEFT JOIN villages v ON p.village_id = v.id
            LEFT JOIN service_providers sp ON u.id = sp.user_id
            WHERE u.id = ? AND u.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user_cache = $stmt->fetch() ?: null;
        return $user_cache;
    } catch (Exception $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Cek apakah user memiliki role tertentu
 */
function has_role(string $role_name): bool {
    if (!is_logged_in()) {
        return false;
    }
    return strtolower((string)($_SESSION['user_role'] ?? '')) === strtolower($role_name);
}

/**
 * Wajibkan login
 */
function require_login(string $redirect_to = '/login.php'): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan masuk terlebih dahulu untuk mengakses halaman ini.');
        redirect($redirect_to);
    }
}

/**
 * Wajibkan role tertentu (bisa string atau array of roles)
 */
function require_role($roles, string $fallback = '/'): void {
    require_login();
    $roles_array = is_array($roles) ? $roles : [$roles];
    $current_role = strtolower((string)($_SESSION['user_role'] ?? ''));

    if (!in_array($current_role, array_map('strtolower', $roles_array))) {
        set_flash('danger', 'Anda tidak memiliki hak akses ke halaman tersebut.');
        redirect(get_dashboard_url_for_role($current_role));
    }
}

/**
 * URL dashboard berdasarkan role (untuk menu profil/navigasi eksplisit)
 */
function get_dashboard_url_for_role(string $role): string {
    return match (strtolower($role)) {
        'admin'    => '/admin/index.php',
        'penyedia' => '/provider/index.php',
        default    => '/user/profile.php'
    };
}

/**
 * URL tujuan pengalihan setelah masuk (login / register)
 * - Admin     => /admin/index.php (Dashboard Admin)
 * - Mitra     => /provider/index.php (Dashboard Mitra / Manajemen Order)
 * - Pengguna  => Beranda (/index.php) atau halaman yang sedang ingin diakses ($redirect_target)
 */
function get_post_login_url(string $role, ?string $redirect_target = null): string {
    if (!empty($redirect_target)) {
        $clean = trim($redirect_target);
        // Validasi keamanan: hanya izinkan relative path internal (dimulai dengan / dan bukan // atau /\\)
        if (str_starts_with($clean, '/') && !str_starts_with($clean, '//') && !str_starts_with($clean, '/\\')) {
            return $clean;
        }
    }

    return match (strtolower($role)) {
        'admin'    => '/admin/index.php',
        'penyedia' => '/provider/index.php',
        'pengguna' => '/index.php',
        default    => '/index.php'
    };
}

/**
 * Proses Login
 */
function login_user(string $login, string $password, ?string $redirect_target = null): array {
    $login = trim($login);
    if (empty($login) || empty($password)) {
        return ['success' => false, 'message' => 'Email/No. HP dan kata sandi wajib diisi.'];
    }

    try {
        $db = get_db();
        // Pencarian bisa via Email atau Nomor HP (khas penggunaan lokal)
        $stmt = $db->prepare("
            SELECT u.id, u.role_id, u.name, u.email, u.phone, u.password_hash, u.is_active, u.email_verified_at,
                   r.name as role_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = ? OR u.phone = ?
            LIMIT 1
        ");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Akun tidak ditemukan. Periksa kembali email atau nomor HP Anda.'];
        }

        if ((int)$user['is_active'] !== 1) {
            return ['success' => false, 'message' => 'Akun Anda sedang dinonaktifkan. Hubungi pengelola platform.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Kata sandi yang Anda masukkan salah.'];
        }

        // Cek apakah akun sudah diverifikasi OTP (kecuali admin)
        if (empty($user['email_verified_at']) && strtolower($user['role_name']) !== 'admin') {
            if (!headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION['pending_verification_user_id'] = (int)$user['id'];
            $_SESSION['pending_verification_name'] = $user['name'];
            $_SESSION['pending_verification_phone'] = $user['phone'];
            $_SESSION['pending_verification_email'] = $user['email'];
            $_SESSION['pending_verification_role'] = $user['role_name'];

            generate_verification_otp((int)$user['id'], 'whatsapp');

            return [
                'success'  => true,
                'message'  => 'Akun Anda belum diverifikasi. Masukkan kode OTP untuk mengaktifkan akun.',
                'role'     => $user['role_name'],
                'redirect' => '/verify.php?channel=whatsapp' . (!empty($redirect_target) ? '&redirect=' . urlencode($redirect_target) : '')
            ];
        }

        // Regenerasi session ID untuk mencegah session fixation jika header belum terkirim
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role_name'];

        return [
            'success'  => true,
            'message'  => 'Selamat datang kembali, ' . $user['name'] . '!',
            'role'     => $user['role_name'],
            'redirect' => get_post_login_url($user['role_name'], $redirect_target)
        ];
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan sistem saat mencoba masuk. Silakan coba lagi.'];
    }
}

/**
 * Proses Pendaftaran User Baru
 */
function register_user(array $data): array {
    $role_type = trim($data['role_type'] ?? 'pengguna'); // 'pengguna' atau 'penyedia'
    $name = trim($data['name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $phone = trim($data['phone'] ?? '');
    $password = $data['password'] ?? '';
    $password_confirm = $data['password_confirm'] ?? '';
    $district_id = !empty($data['district_id']) ? (int)$data['district_id'] : null;
    $village_id = !empty($data['village_id']) ? (int)$data['village_id'] : null;
    $address = trim($data['address'] ?? '');

    // Khusus Penyedia Jasa
    $business_name = trim($data['business_name'] ?? '');
    $category_id = !empty($data['category_id']) ? (int)$data['category_id'] : null;

    // Validasi
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        return ['success' => false, 'message' => 'Nama lengkap, email, nomor HP, dan kata sandi wajib diisi.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Format alamat email tidak valid.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Kata sandi minimal harus 6 karakter.'];
    }

    if ($password !== $password_confirm) {
        return ['success' => false, 'message' => 'Konfirmasi kata sandi tidak cocok.'];
    }

    if ($role_type === 'penyedia') {
        if (empty($business_name)) {
            $business_name = $name; // Fallback ke nama penyedia jika kosong
        }
        if (empty($category_id)) {
            return ['success' => false, 'message' => 'Pilih kategori utama keahlian/jasa Anda.'];
        }
    }

    $db = get_db();

    // Cek duplikasi email atau telepon
    $stmt = $db->prepare("SELECT id, email, phone FROM users WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$email, $phone]);
    $existing = $stmt->fetch();
    if ($existing) {
        if ($existing['email'] === $email) {
            return ['success' => false, 'message' => 'Alamat email ini sudah terdaftar. Silakan masuk.'];
        }
        return ['success' => false, 'message' => 'Nomor HP ini sudah terdaftar. Gunakan nomor lain.'];
    }

    // Ambil ID role dari database
    $role_name = ($role_type === 'penyedia') ? 'penyedia' : 'pengguna';
    $stmtRole = $db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
    $stmtRole->execute([$role_name]);
    $role_row = $stmtRole->fetch();
    if (!$role_row) {
        return ['success' => false, 'message' => 'Role pendaftaran tidak valid.'];
    }
    $role_id = (int)$role_row['id'];

    // Hashing kata sandi dengan bcrypt
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $db->beginTransaction();

        // 1. Simpan ke users
        $stmtInsertUser = $db->prepare("
            INSERT INTO users (role_id, name, email, phone, password_hash, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmtInsertUser->execute([$role_id, $name, $email, $phone, $password_hash]);
        $user_id = (int)$db->lastInsertId();

        // 2. Simpan ke profiles
        $stmtInsertProfile = $db->prepare("
            INSERT INTO profiles (user_id, address, district_id, village_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmtInsertProfile->execute([$user_id, $address, $district_id, $village_id]);

        // 3. Jika penyedia jasa, simpan ke service_providers & service_areas dengan Bonus Sambutan Gratis
        if ($role_name === 'penyedia') {
            $starter_bonus = (float)get_setting('welcome_bonus_amount', defined('WELCOME_BONUS_WALLET') ? (float)WELCOME_BONUS_WALLET : 45000.00);
            $stmtInsertProvider = $db->prepare("
                INSERT INTO service_providers 
                (user_id, primary_category_id, business_name, description, address, district_id, village_id, is_verified, wallet_balance, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())
            ");
            $default_desc = "Penyedia jasa " . $business_name . " di wilayah Indragiri Hulu.";
            $stmtInsertProvider->execute([$user_id, $category_id, $business_name, $default_desc, $address, $district_id, $village_id, $starter_bonus]);
            $provider_id = (int)$db->lastInsertId();

            // Default service area ke kecamatan tempat tinggal jika ada
            if ($district_id) {
                $stmtArea = $db->prepare("INSERT IGNORE INTO service_areas (provider_id, district_id) VALUES (?, ?)");
                $stmtArea->execute([$provider_id, $district_id]);
            }

            // Catat mutasi bonus sambutan (Strategi Cicipi Madunya Dulu)
            if ($starter_bonus > 0) {
                $cur_fee = (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE);
                $free_quota_txt = ($cur_fee > 0) ? floor($starter_bonus / $cur_fee) . " Pesanan Pertama Gratis" : "Saldo Awal Kuota";
                $stmtBonus = $db->prepare("
                    INSERT INTO provider_wallet_transactions 
                    (provider_id, type, amount, balance_after, description, created_at)
                    VALUES (?, 'bonus', ?, ?, ?, NOW())
                ");
                $stmtBonus->execute([$provider_id, $starter_bonus, $starter_bonus, "Bonus Sambutan Mitra Baru JASA INHU (" . $free_quota_txt . ")"]);
            }
        }

        // 4. Notifikasi selamat datang
        $stmtNotif = $db->prepare("
            INSERT INTO notifications (user_id, title, message, link, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $notif_title = "Selamat Datang di JASA INHU";
        $notif_msg = "Akun Anda berhasil dibuat. Selamat menggunakan platform marketplace jasa lokal Indragiri Hulu!";
        $stmtNotif->execute([$user_id, $notif_title, $notif_msg, get_dashboard_url_for_role($role_name)]);

        $db->commit();

        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        // JANGAN langsung berikan session login resmi ($_SESSION['user_id']).
        // Simpan pending verification session agar wajib menyelesaikan OTP terlebih dahulu.
        $_SESSION['pending_verification_user_id'] = $user_id;
        $_SESSION['pending_verification_name'] = $name;
        $_SESSION['pending_verification_phone'] = $phone;
        $_SESSION['pending_verification_email'] = $email;
        $_SESSION['pending_verification_role'] = $role_name;

        // Otomatis kirim kode verifikasi ke WhatsApp pengguna yang baru mendaftar
        $otpRes = generate_verification_otp($user_id, 'whatsapp');

        return [
            'success'  => true,
            'message'  => 'Pendaftaran berhasil! Kode verifikasi telah dikirim ke WhatsApp Anda.',
            'role'     => $role_name,
            'otp_sent' => true,
            'redirect' => '/verify.php?channel=whatsapp' . (!empty($data['redirect']) ? '&redirect=' . urlencode($data['redirect']) : '')
        ];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Register error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal mendaftar: ' . $e->getMessage()];
    }
}

/**
 * Logout
 */
function logout_user(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}

/**
 * Buat dan kirim kode verifikasi (OTP) via WhatsApp atau Gmail
 */
function generate_verification_otp(int $user_id, string $channel = 'email'): array {
    $db = get_db();
    
    $stmt = $db->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['success' => false, 'message' => 'Pengguna tidak ditemukan.'];
    }

    $channel = ($channel === 'whatsapp') ? 'whatsapp' : 'email';
    $target = ($channel === 'whatsapp') ? $user['phone'] : $user['email'];
    $code = sprintf('%06d', mt_rand(100000, 999999));
    $token = bin2hex(random_bytes(16));
    $expires_at = date('Y-m-d H:i:s', time() + 900); // 15 menit

    $stmtIns = $db->prepare("
        INSERT INTO user_verifications 
        (user_id, channel, target, code, token, expires_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmtIns->execute([$user_id, $channel, $target, $code, $token, $expires_at]);

    $direct_url = null;
    $is_simulated = false;

    if ($channel === 'whatsapp') {
        $waMsg = "Halo " . $user['name'] . ",\n\n"
               . "Kode verifikasi pendaftaran akun JASA INHU Anda adalah:\n"
               . ">> *" . $code . "* <<\n\n"
               . "Masukkan kode ini di aplikasi untuk mengaktifkan akun Anda. Kode berlaku 15 menit.\n\n"
               . "Abaikan jika Anda tidak mendaftar di JASA INHU.";
        
        $waRes = send_wa_notification($user['phone'], $waMsg, 'otp_verification', null, $user['name']);
        $direct_url = $waRes['direct_url'] ?? null;
    } else {
        // Jalur Otomatis Gmail / SMTP
        $verifyLink = BASE_URL . "/verify.php?token=" . $token;
        $subject = "Kode Verifikasi Akun JASA INHU: " . $code;
        $emailHtml = render_verification_email_html($user['name'], $code, $verifyLink);
        $emailPlain = "Halo " . $user['name'] . ",\n\n"
                    . "Terima kasih telah mendaftar di platform JASA INHU.\n\n"
                    . "Kode verifikasi akun Anda adalah: " . $code . "\n\n"
                    . "Atau Anda dapat langsung klik tautan aktivasi instan berikut:\n"
                    . $verifyLink . "\n\n"
                    . "Kode dan tautan berlaku selama 15 menit.\n\n"
                    . "Demi keamanan platform dan mencegah akun/pesanan fiktif, mohon segera selesaikan verifikasi akun Anda.\n\n"
                    . "Salam hangat,\nTim JASA INHU";
        
        $mailRes = send_system_email($user['email'], $subject, $emailHtml, $emailPlain, $user['name']);
        $direct_url = $verifyLink;
        $is_simulated = !empty($mailRes['simulated']);
    }

    return [
        'success'    => true,
        'code'       => $code,
        'token'      => $token,
        'channel'    => $channel,
        'target'     => $target,
        'direct_url' => $direct_url,
        'simulated'  => $is_simulated,
        'message'    => ($channel === 'whatsapp') 
            ? 'Kode OTP 6-digit berhasil dikirim ke nomor WhatsApp Anda (' . $user['phone'] . ').' 
            : 'Kode OTP 6-digit dan link aktivasi telah dikirim otomatis ke Gmail Anda (' . $user['email'] . ').'
    ];
}

/**
 * Validasi kode OTP yang diinput pengguna
 */
function verify_user_otp(int $user_id, string $code): array {
    $db = get_db();
    $code = trim($code);

    if (empty($code)) {
        return ['success' => false, 'message' => 'Masukkan 6 digit kode verifikasi.'];
    }

    $stmt = $db->prepare("
        SELECT id, token FROM user_verifications 
        WHERE user_id = ? AND code = ? AND is_verified = 0 AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$user_id, $code]);
    $ver = $stmt->fetch();

    if (!$ver) {
        return ['success' => false, 'message' => 'Kode verifikasi salah atau sudah kadaluarsa. Silakan minta kode baru.'];
    }

    $db->prepare("UPDATE user_verifications SET is_verified = 1 WHERE id = ?")->execute([$ver['id']]);
    $db->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?")->execute([$user_id]);

    return ['success' => true, 'message' => 'Selamat, akun Anda berhasil diverifikasi!'];
}

/**
 * Validasi token aktivasi tautan email
 */
function verify_user_token(string $token): array {
    $db = get_db();
    $token = trim($token);

    if (empty($token)) {
        return ['success' => false, 'message' => 'Tautan verifikasi tidak valid.'];
    }

    $stmt = $db->prepare("
        SELECT id, user_id FROM user_verifications 
        WHERE token = ? AND is_verified = 0 AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$token]);
    $ver = $stmt->fetch();

    if (!$ver) {
        return ['success' => false, 'message' => 'Tautan verifikasi tidak valid atau sudah kadaluarsa.'];
    }

    $db->prepare("UPDATE user_verifications SET is_verified = 1 WHERE id = ?")->execute([$ver['id']]);
    $db->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?")->execute([$ver['user_id']]);

    // Otomatis login jika belum
    if (!is_logged_in()) {
        $stmtUser = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmtUser->execute([$ver['user_id']]);
        $u = $stmtUser->fetch();
        if ($u) {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['user_name'] = $u['name'];
            $_SESSION['user_email'] = $u['email'];
            $_SESSION['user_role'] = $u['role_name'];
        }
    }

    return ['success' => true, 'user_id' => (int)$ver['user_id'], 'message' => 'Selamat, akun Anda berhasil diverifikasi melalui Gmail!'];
}

<?php
/**
 * Helper Functions JASA INHU
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Escape string untuk mencegah XSS
 */
function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate atau ambil CSRF Token (Double-Submit Cookie Pattern untuk keandalan multi-server / Vercel)
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $token = !empty($_COOKIE['csrf_token']) ? $_COOKIE['csrf_token'] : bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }
    if (empty($_COOKIE['csrf_token']) || $_COOKIE['csrf_token'] !== $_SESSION['csrf_token']) {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        @setcookie('csrf_token', $_SESSION['csrf_token'], [
            'expires'  => time() + 86400 * 7,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $isSecure
        ]);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate input hidden untuk CSRF Token
 */
function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Validasi CSRF Token dari request POST (Mendukung Session dan Cookie ganda)
 */
function validate_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token)) {
        return false;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $cookieToken = $_COOKIE['csrf_token'] ?? '';

    if (!empty($sessionToken) && hash_equals($sessionToken, $token)) {
        return true;
    }
    if (!empty($cookieToken) && hash_equals($cookieToken, $token)) {
        $_SESSION['csrf_token'] = $cookieToken;
        return true;
    }
    return false;
}

/**
 * Format angka ke mata uang Rupiah
 */
function format_rupiah($amount): string {
    if ($amount === null || $amount === '') {
        return 'Sesuai Kesepakatan';
    }
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

/**
 * Format tanggal Indonesia
 */
function format_date(?string $datetime, bool $show_time = false): string {
    if (empty($datetime)) {
        return '-';
    }
    $timestamp = strtotime($datetime);
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    $result = "{$day} {$month} {$year}";
    if ($show_time) {
        $result .= ' ' . date('H:i', $timestamp) . ' WIB';
    }
    return $result;
}

/**
 * Format ID Pengguna / Mitra menjadi 3 digit (misal: #001, #002, dst)
 */
function format_user_id($id): string {
    if (!$id) return '-';
    return '#' . str_pad((string)$id, 3, '0', STR_PAD_LEFT);
}

/**
 * Set flash message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Ambil dan bersihkan flash message (Mendukung tipe spesifik atau seluruh array)
 */
function get_flash(?string $filterType = null): mixed {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        if ($filterType !== null) {
            if (($flash['type'] ?? '') === $filterType) {
                unset($_SESSION['flash']);
                return $flash['message'] ?? '';
            }
            return null;
        }
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Tampilkan banner flash message jika ada
 */
function render_flash(): void {
    $flash = get_flash();
    if ($flash) {
        $type = e($flash['type']);
        $msg = e($flash['message']);
        $icon = match($flash['type']) {
            'success' => 'fa-check-circle',
            'danger'  => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            default   => 'fa-info-circle'
        };
        echo "<div class='alert alert-{$type} alert-dismissible fade show d-flex align-items-center mb-4' role='alert'>
                <i class='fa-solid {$icon} me-2 fs-5'></i>
                <div>{$msg}</div>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}

/**
 * Redirect ke path tertentu
 */
function redirect(string $path): void {
    $target = str_starts_with($path, 'http') ? $path : BASE_URL . '/' . ltrim($path, '/');
    header("Location: {$target}");
    exit;
}

/**
 * Generate asset URL
 */
function asset_url(string $path): string {
    if (str_starts_with(BASE_URL, 'https://') || (isset($_SERVER['HTTP_HOST']) && str_ends_with($_SERVER['HTTP_HOST'], '.vercel.app'))) {
        return '/assets/' . ltrim($path, '/');
    }
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

/**
 * Mendapatkan URL Avatar Pengguna (Mendukung Data URI Base64 & File Lokal)
 */
function get_avatar_url(?string $avatar): ?string {
    if (empty($avatar)) return null;
    if (str_starts_with($avatar, 'data:image/') || str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
        return $avatar;
    }
    return BASE_URL . '/uploads/avatars/' . ltrim($avatar, '/');
}

/**
 * Ambil daftar semua kecamatan di Inhu
 */
function get_all_districts(): array {
    try {
        $db = get_db();
        $stmt = $db->query("SELECT id, name, code FROM districts ORDER BY name ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Ambil kategori jasa aktif
 */
function get_active_categories(int $limit = 0): array {
    try {
        $db = get_db();
        $sql = "SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Format nomor telepon ke link WhatsApp resmi (628xxx)
 */
function format_wa_url(?string $phone, string $message = ''): string {
    if (empty($phone)) return '#';
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (empty($clean)) return '#';
    if (str_starts_with($clean, '0')) {
        $clean = '62' . substr($clean, 1);
    } elseif (!str_starts_with($clean, '62')) {
        $clean = '62' . $clean;
    }
    $url = "https://wa.me/{$clean}";
    if (!empty($message)) {
        $url .= '?text=' . urlencode($message);
    }
    return $url;
}

/**
 * Buat template pesan WhatsApp resmi untuk setiap peristiwa pesanan
 */
function build_order_wa_template(string $event, array $data): string {
    $title = $data['title'] ?? 'Layanan Jasa';
    $customer_name = $data['customer_name'] ?? 'Pelanggan';
    $provider_name = $data['provider_name'] ?? 'Mitra Jasa';
    $request_id = (int)($data['request_id'] ?? 0);
    $track_url = BASE_URL . '/user/requests.php?id=' . $request_id;
    $invoice_url = BASE_URL . '/invoice.php?id=' . $request_id;

    switch ($event) {
        case 'order_created':
            return "Halo {$provider_name},\n\nAda pesanan jasa baru dari *{$customer_name}* di *JASA INHU*!\n\n📋 *Layanan:* {$title}\n📍 *Kecamatan:* " . ($data['district_name'] ?? 'Inhu') . "\n💰 *Budget:* " . (!empty($data['budget']) ? format_rupiah($data['budget']) : 'Negosiasi') . "\n\nSilakan buka aplikasi untuk merespon:\n" . BASE_URL . "/provider/index.php";

        case 'order_accepted':
            return "Halo {$customer_name},\n\nPesanan Anda *\"{$title}\"* telah *DITERIMA* oleh mitra kami *{$provider_name}*.\n\nMitra bersiap menuju lokasi Anda. Lacak status pesanan secara langsung di:\n{$track_url}\n\nTerima kasih telah menggunakan *JASA INHU*!";

        case 'on_the_way':
            return "Halo {$customer_name},\n\nMitra *{$provider_name}* sedang *DALAM PERJALANAN (OTW)* menuju lokasi Anda untuk pengerjaan *\"{$title}\"*.\n\nHarap pastikan ada orang di lokasi pengerjaan.\nLacak status: {$track_url}";

        case 'working':
            return "Halo {$customer_name},\n\nMitra *{$provider_name}* telah tiba di lokasi dan *PENGERJAAN DIMULAI* untuk layanan *\"{$title}\"*.\n\nLacak status: {$track_url}";

        case 'completed':
            $total_str = !empty($data['final_price']) ? format_rupiah($data['final_price']) : 'Sesuai kesepakatan';
            return "Halo {$customer_name},\n\nPekerjaan jasa *\"{$title}\"* telah *SELESAI* dikerjakan oleh mitra *{$provider_name}*!\n\n💵 *Total Biaya:* {$total_str}\n📄 *Kwitansi Resmi:* {$invoice_url}\n⭐ *Beri Rating & Ulasan:* {$track_url}\n\nTerima kasih telah mempercayai *JASA INHU*!";

        case 'cancelled':
            return "Halo {$customer_name},\n\nMohon maaf, pesanan Anda *\"{$title}\"* telah dibatalkan atau ditolak oleh mitra.\nAlasan: " . ($data['reason'] ?? 'Mitra berhalangan') . "\n\nAnda dapat memasang permintaan terbuka di: " . BASE_URL . "/tender.php";

        default:
            return "Halo {$customer_name}, ada pembaruan status untuk pesanan Anda: {$title}.\nCek di: {$track_url}";
    }
}

/**
 * Catat dan kirim notifikasi WhatsApp otomatis ke whatsapp_logs
 */
function send_wa_notification(string $phone, string $message, string $event_type = 'general', ?int $request_id = null, ?string $recipient_name = null): array {
    $wa_url = format_wa_url($phone, $message);
    $status = 'simulated';

    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO whatsapp_logs 
            (request_id, recipient_phone, recipient_name, event_type, message, status, direct_url, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $request_id, $phone, $recipient_name, $event_type, $message, $status, $wa_url
        ]);
        $log_id = (int)$db->lastInsertId();

        return [
            'success'    => true,
            'log_id'     => $log_id,
            'direct_url' => $wa_url,
            'status'     => $status
        ];
    } catch (Exception $e) {
        return [
            'success'    => false,
            'message'    => $e->getMessage(),
            'direct_url' => $wa_url
        ];
    }
}

/**
 * Ambil saldo dompet deposit mitra
 */
function get_provider_wallet_balance(int $provider_id): float {
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT wallet_balance FROM service_providers WHERE id = ?");
        $stmt->execute([$provider_id]);
        return (float)($stmt->fetchColumn() ?: 0.00);
    } catch (Exception $e) {
        return 0.00;
    }
}

/**
 * Potong biaya kontak (lead fee) saat mitra menerima atau memenangkan pesanan
 */
function deduct_provider_lead_fee(int $provider_id, ?int $request_id, ?float $amount = null, string $description = ''): array {
    $db = get_db();
    try {
        // Ambil nilai lead fee dinamis dari pengaturan jika tidak ditentukan khusus
        if ($amount === null) {
            $amount = (float)get_setting('lead_fee_amount', DEFAULT_LEAD_FEE);
        }

        // Ambil saldo saat ini
        $stmt = $db->prepare("SELECT wallet_balance, business_name FROM service_providers WHERE id = ?");
        $stmt->execute([$provider_id]);
        $prov = $stmt->fetch();

        if (!$prov) {
            return ['success' => false, 'message' => 'Penyedia jasa tidak ditemukan.'];
        }

        $current_balance = (float)$prov['wallet_balance'];

        // Jika mode promo gratis (biaya kontak Rp 0 atau minus), langsung izinkan tanpa memotong saldo
        if ($amount <= 0) {
            return [
                'success' => true,
                'message' => 'Biaya kontak gratis (Promo Rp 0)!',
                'balance_after' => $current_balance,
                'amount_deducted' => 0
            ];
        }

        if ($current_balance < $amount) {
            return [
                'success' => false,
                'message' => 'Saldo deposit Anda (Rp ' . number_format($current_balance, 0, ',', '.') . ') tidak mencukupi untuk menerima pesanan ini. Biaya kontak adalah Rp ' . number_format($amount, 0, ',', '.') . '. Silakan isi saldo deposit terlebih dahulu.',
                'current_balance' => $current_balance,
                'required_amount' => $amount
            ];
        }

        $balance_after = $current_balance - $amount;
        if (empty($description)) {
            $description = "Biaya Kontak / Lead Pesanan #" . ($request_id ?: '-');
        }

        // Update saldo mitra
        $upd = $db->prepare("UPDATE service_providers SET wallet_balance = ? WHERE id = ?");
        $upd->execute([$balance_after, $provider_id]);

        // Catat transaksi mutasi
        $ins = $db->prepare("
            INSERT INTO provider_wallet_transactions 
            (provider_id, type, amount, balance_after, description, reference_id, created_at)
            VALUES (?, 'lead_fee', ?, ?, ?, ?, NOW())
        ");
        $ins->execute([$provider_id, -$amount, $balance_after, $description, $request_id]);

        return [
            'success' => true,
            'message' => 'Biaya kontak sebesar Rp ' . number_format($amount, 0, ',', '.') . ' berhasil dipotong.',
            'balance_after' => $balance_after,
            'amount_deducted' => $amount
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Gagal memproses biaya kontak: ' . $e->getMessage()];
    }
}

/**
 * Tambah saldo dompet mitra (Top-Up / Bonus / Refund)
 */
function add_provider_wallet_balance(int $provider_id, float $amount, string $type = 'topup', string $description = '', ?int $reference_id = null): array {
    $db = get_db();
    try {
        $stmt = $db->prepare("SELECT wallet_balance FROM service_providers WHERE id = ?");
        $stmt->execute([$provider_id]);
        $current_balance = (float)($stmt->fetchColumn() ?: 0.00);

        $balance_after = $current_balance + $amount;
        if (empty($description)) {
            $description = ($type === 'topup' ? 'Top-Up Saldo Dompet' : 'Penambahan Saldo');
        }

        $upd = $db->prepare("UPDATE service_providers SET wallet_balance = ? WHERE id = ?");
        $upd->execute([$balance_after, $provider_id]);

        $ins = $db->prepare("
            INSERT INTO provider_wallet_transactions 
            (provider_id, type, amount, balance_after, description, reference_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $ins->execute([$provider_id, $type, $amount, $balance_after, $description, $reference_id]);

        return [
            'success' => true,
            'message' => 'Saldo berhasil ditambahkan sebesar Rp ' . number_format($amount, 0, ',', '.'),
            'balance_after' => $balance_after
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Gagal menambah saldo: ' . $e->getMessage()];
    }
}

/**
 * Mengambil nilai pengaturan dinamis dari tabel app_settings
 */
function get_setting(string $key, $default = null, bool $force_refresh = false) {
    static $settings_cache = null;

    if ($settings_cache === null || $force_refresh) {
        $settings_cache = [];
        try {
            $db = get_db();
            $stmt = $db->query("SELECT setting_key, setting_value FROM app_settings");
            while ($row = $stmt->fetch()) {
                $settings_cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Fallback jika tabel belum ada
        }
    }

    if (isset($settings_cache[$key])) {
        return $settings_cache[$key];
    }

    // Fallback konstanta bawaan config/app.php jika ada
    if ($key === 'admin_wa' && defined('ADMIN_PHONE_WA')) {
        return ADMIN_PHONE_WA;
    }
    if ($key === 'admin_email' && defined('ADMIN_EMAIL')) {
        return ADMIN_EMAIL;
    }
    if ($key === 'lead_fee_amount' && defined('DEFAULT_LEAD_FEE')) {
        return DEFAULT_LEAD_FEE;
    }
    if ($key === 'welcome_bonus_amount' && defined('WELCOME_BONUS_WALLET')) {
        return WELCOME_BONUS_WALLET;
    }

    return $default;
}

/**
 * Menyimpan atau memperbarui pengaturan aplikasi
 */
function update_setting(string $key, string $value): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO app_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $res = $stmt->execute([$key, $value]);
        if ($res) {
            // Segera sinkronkan cache pengaturan
            get_setting($key, null, true);
        }
        return $res;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mencatat log kejadian pada timeline pesanan (Order Tracker)
 */
function add_order_timeline_event(int $request_id, string $status_key, string $title, string $note = '', string $actor_role = 'system'): bool {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO service_request_timeline (request_id, status_key, title, note, actor_role, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$request_id, $status_key, $title, $note, $actor_role]);

        // Perbarui progress_step di service_requests
        $stmtUpd = $db->prepare("UPDATE service_requests SET progress_step = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpd->execute([$status_key, $request_id]);

        return true;
    } catch (Exception $e) {
        error_log("Failed to add timeline event: " . $e->getMessage());
        return false;
    }
}

/**
 * Mengambil daftar seluruh riwayat kejadian timeline sebuah pesanan
 */
function get_order_timeline(int $request_id): array {
    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT * FROM service_request_timeline 
            WHERE request_id = ? 
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$request_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}




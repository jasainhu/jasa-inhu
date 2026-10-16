<?php
/**
 * Admin: Kelola & Edit Pengguna Platform
 */

$page_title = 'Kelola Pengguna';
$admin_active = 'users';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$db = get_db();
$error = '';

// Handle aksi form (Edit User atau Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'];

        // 1. Toggle status aktif / blokir
        if ($action === 'toggle_active') {
            $user_id = (int)$_POST['user_id'];
            $new_status = (int)$_POST['new_status'];
            $block_reason = trim($_POST['block_reason'] ?? '');

            if ($user_id === (int)$_SESSION['user_id']) {
                set_flash('danger', 'Anda tidak dapat menonaktifkan akun sendiri.');
            } else {
                if ($new_status === 0) {
                    if (empty($block_reason)) {
                        $block_reason = 'Dinonaktifkan oleh administrator platform';
                    }
                    $stmt = $db->prepare("UPDATE users SET is_active = 0, block_reason = ?, blocked_at = NOW(), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$block_reason, $user_id]);
                    set_flash('warning', 'Akun berhasil diblokir. Alasan: "' . htmlspecialchars($block_reason, ENT_QUOTES) . '"');
                } else {
                    $stmt = $db->prepare("UPDATE users SET is_active = 1, block_reason = NULL, blocked_at = NULL, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$user_id]);
                    set_flash('success', 'Akun berhasil diaktifkan kembali (status blokir dicabut).');
                }
            }
            redirect('/admin/users.php');
        }

        // 1.b. Toggle Verifikasi KTP / Pelajar Mitra
        if ($action === 'toggle_ktp_verified') {
            $user_id = (int)$_POST['user_id'];
            $new_status = (int)$_POST['new_status'];
            $stmt = $db->prepare("UPDATE service_providers SET is_verified = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$new_status, $user_id]);

            // Cek tipe identitas
            $stmtSp = $db->prepare("SELECT id_card_type FROM service_providers WHERE user_id = ?");
            $stmtSp->execute([$user_id]);
            $spData = $stmtSp->fetch();
            $isPelajar = ($spData && ($spData['id_card_type'] ?? '') === 'pelajar');
            $docName = $isPelajar ? 'Kartu Pelajar' : 'KTP';

            $msg = ($new_status === 1) ? "Status identitas {$docName} mitra berhasil diverifikasi resmi." : "Status verifikasi {$docName} mitra telah dinonaktifkan.";
            set_flash('success', $msg);
            redirect('/admin/users.php');
        }

        // 1.c. Verifikasi Akun Manual oleh Admin (Bypass OTP)
        if ($action === 'manual_verify_user') {
            $user_id = (int)$_POST['user_id'];
            $stmt = $db->prepare("UPDATE users SET email_verified_at = NOW(), is_active = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
            $db->prepare("UPDATE user_verifications SET is_verified = 1 WHERE user_id = ?")->execute([$user_id]);
            set_flash('success', 'Akun berhasil diverifikasi manual oleh admin.');
            redirect('/admin/users.php');
        }

        // 1.d. Reset Kata Sandi Akun oleh Admin
        if ($action === 'reset_password') {
            $user_id = (int)$_POST['user_id'];
            $new_password = trim($_POST['new_password'] ?? '');

            if (empty($new_password) || strlen($new_password) < 6) {
                set_flash('danger', 'Kata sandi baru minimal 6 karakter.');
            } else {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hash, $user_id]);

                $stmtU = $db->prepare("SELECT name, phone FROM users WHERE id = ?");
                $stmtU->execute([$user_id]);
                $uData = $stmtU->fetch();
                $uName = $uData['name'] ?? 'Pengguna';

                set_flash('success', "Kata sandi akun '{$uName}' berhasil direset menjadi: <strong class='text-dark bg-warning px-1.5 py-0.5 rounded'>" . htmlspecialchars($new_password, ENT_QUOTES) . "</strong>. Anda dapat menginfokannya langsung ke pengguna via WhatsApp.");
            }
            redirect('/admin/users.php');
        }

        // 2. Edit data akun pengguna (Nama, HP, Email, Wilayah, dll)
        if ($action === 'edit_user') {
            $user_id = (int)$_POST['user_id'];
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
            $gender = !empty($_POST['gender']) && in_array($_POST['gender'], ['male', 'female', 'other']) ? $_POST['gender'] : null;
            $birth_date = !empty($_POST['birth_date']) ? trim($_POST['birth_date']) : null;
            if ($birth_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
                $birth_date = null;
            }
            $is_active = (int)($_POST['is_active'] ?? 1);
            $business_name = trim($_POST['business_name'] ?? '');

            if (empty($name) || empty($phone) || empty($email)) {
                $error = 'Nama, nomor handphone, dan email wajib diisi.';
            } else {
                // Cek duplikasi email / phone pada user lain
                $stmtCheck = $db->prepare("SELECT id FROM users WHERE (email = ? OR phone = ?) AND id != ? LIMIT 1");
                $stmtCheck->execute([$email, $phone, $user_id]);
                if ($stmtCheck->fetch()) {
                    $error = 'Email atau nomor handphone tersebut sudah digunakan oleh akun lain.';
                } else {
                    try {
                        $db->beginTransaction();

                        // Update tabel users
                        $stmtU = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmtU->execute([$name, $email, $phone, $is_active, $user_id]);

                        // Jika ada input kata sandi baru di modal edit
                        $new_pass = trim($_POST['new_password'] ?? '');
                        if (!empty($new_pass)) {
                            if (strlen($new_pass) < 6) {
                                throw new Exception('Kata sandi baru minimal 6 karakter.');
                            }
                            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                            $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")->execute([$hash, $user_id]);
                        }

                        // Update tabel profiles
                        $stmtPCheck = $db->prepare("SELECT id FROM profiles WHERE user_id = ?");
                        $stmtPCheck->execute([$user_id]);
                        if ($stmtPCheck->fetch()) {
                            $stmtP = $db->prepare("UPDATE profiles SET district_id = ?, gender = ?, birth_date = ?, updated_at = NOW() WHERE user_id = ?");
                            $stmtP->execute([$district_id, $gender, $birth_date, $user_id]);
                        } else {
                            $stmtP = $db->prepare("INSERT INTO profiles (user_id, district_id, gender, birth_date, created_at) VALUES (?, ?, ?, ?, NOW())");
                            $stmtP->execute([$user_id, $district_id, $gender, $birth_date]);
                        }

                        // Jika akun adalah penyedia jasa dan ada perubahan nama usaha
                        if (!empty($business_name)) {
                            $stmtSp = $db->prepare("UPDATE service_providers SET business_name = ?, district_id = ?, updated_at = NOW() WHERE user_id = ?");
                            $stmtSp->execute([$business_name, $district_id, $user_id]);
                        }

                        $db->commit();

                        // Jika mengedit akun sendiri, update sesi
                        if ($user_id === (int)$_SESSION['user_id']) {
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_email'] = $email;
                        }

                        set_flash('success', "Data akun '{$name}' (No. HP: {$phone}) berhasil diperbarui.");
                        redirect('/admin/users.php');
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = 'Gagal menyimpan data: ' . $e->getMessage();
                    }
                }
            }
        }

        // 3. Hapus akun pengguna / mitra (Clean Cascading Delete)
        if ($action === 'delete_user') {
            $user_id = (int)$_POST['user_id'];

            if ($user_id === (int)$_SESSION['user_id']) {
                set_flash('danger', 'Anda tidak dapat menghapus akun administrator yang sedang Anda gunakan.');
            } else {
                try {
                    $db->beginTransaction();

                    // Cek data user
                    $stmtUser = $db->prepare("SELECT u.id, u.name, u.role_id, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                    $stmtUser->execute([$user_id]);
                    $targetUser = $stmtUser->fetch();

                    if (!$targetUser) {
                        throw new Exception('Akun pengguna tidak ditemukan.');
                    }

                    // Jika user adalah penyedia jasa (mitra)
                    $stmtProvider = $db->prepare("SELECT id, business_name FROM service_providers WHERE user_id = ?");
                    $stmtProvider->execute([$user_id]);
                    $provider = $stmtProvider->fetch();

                    if ($provider) {
                        $provider_id = (int)$provider['id'];

                        // 1. Hapus service_areas
                        $db->prepare("DELETE FROM service_areas WHERE provider_id = ?")->execute([$provider_id]);

                        // 2. Hapus service_request_responses
                        $db->prepare("DELETE FROM service_request_responses WHERE provider_id = ?")->execute([$provider_id]);

                        // 3. Hapus reviews terkait provider
                        $db->prepare("DELETE FROM reviews WHERE provider_id = ?")->execute([$provider_id]);

                        // 4. Update service_requests jika provider_id mengarah ke provider ini
                        $db->prepare("UPDATE service_requests SET provider_id = NULL WHERE provider_id = ?")->execute([$provider_id]);

                        // 5. Hapus record service_providers
                        $db->prepare("DELETE FROM service_providers WHERE id = ?")->execute([$provider_id]);
                    }

                    // Hapus relasi umum user:
                    // 1. Reviews yang dibuat oleh user
                    $db->prepare("DELETE FROM reviews WHERE user_id = ?")->execute([$user_id]);

                    // 2. Notifications user
                    $db->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id]);

                    // 3. Service requests yang dibuat oleh user (dan responses-nya)
                    $stmtReqs = $db->prepare("SELECT id FROM service_requests WHERE user_id = ?");
                    $stmtReqs->execute([$user_id]);
                    $reqIds = $stmtReqs->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($reqIds)) {
                        $inClause = implode(',', array_fill(0, count($reqIds), '?'));
                        $db->prepare("DELETE FROM service_request_responses WHERE request_id IN ($inClause)")->execute($reqIds);
                        $db->prepare("DELETE FROM reviews WHERE request_id IN ($inClause)")->execute($reqIds);
                        $db->prepare("DELETE FROM service_requests WHERE user_id = ?")->execute([$user_id]);
                    }

                    // 4. Profiles
                    $db->prepare("DELETE FROM profiles WHERE user_id = ?")->execute([$user_id]);

                    // 5. Users
                    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

                    $db->commit();

                    $roleLabel = ($targetUser['role_name'] === 'penyedia') ? 'Mitra penyedia jasa' : 'Pengguna';
                    set_flash('success', "{$roleLabel} '{$targetUser['name']}' berhasil dihapus permanen dari sistem.");
                } catch (Exception $e) {
                    $db->rollBack();
                    set_flash('danger', 'Gagal menghapus akun: ' . $e->getMessage());
                }
            }
            redirect('/admin/users.php');
        }

        // 4. Hapus Massal Akun Pengguna / Mitra (Bulk Delete with Clean Cascading)
        if ($action === 'bulk_delete_users') {
            $raw_ids = $_POST['user_ids'] ?? [];
            if (!is_array($raw_ids) || empty($raw_ids)) {
                set_flash('warning', 'Tidak ada akun yang dipilih untuk dihapus.');
                redirect('/admin/users.php');
            }

            $current_admin_id = (int)$_SESSION['user_id'];
            $user_ids = array_filter(array_map('intval', $raw_ids), function($id) use ($current_admin_id) {
                return $id > 0 && $id !== $current_admin_id;
            });

            if (empty($user_ids)) {
                set_flash('warning', 'Tidak ada akun valid yang dapat dihapus.');
                redirect('/admin/users.php');
            }

            try {
                $db->beginTransaction();
                $deleted_count = 0;

                foreach ($user_ids as $uid) {
                    $stmtUser = $db->prepare("SELECT id FROM users WHERE id = ?");
                    $stmtUser->execute([$uid]);
                    if (!$stmtUser->fetch()) {
                        continue;
                    }

                    // Jika mitra / provider
                    $stmtProvider = $db->prepare("SELECT id FROM service_providers WHERE user_id = ?");
                    $stmtProvider->execute([$uid]);
                    $prov = $stmtProvider->fetch();

                    if ($prov) {
                        $pid = (int)$prov['id'];
                        $db->prepare("DELETE FROM service_areas WHERE provider_id = ?")->execute([$pid]);
                        $db->prepare("DELETE FROM service_request_responses WHERE provider_id = ?")->execute([$pid]);
                        $db->prepare("DELETE FROM reviews WHERE provider_id = ?")->execute([$pid]);
                        $db->prepare("UPDATE service_requests SET provider_id = NULL WHERE provider_id = ?")->execute([$pid]);
                        $db->prepare("DELETE FROM service_providers WHERE id = ?")->execute([$pid]);
                    }

                    // Relasi user
                    $db->prepare("DELETE FROM reviews WHERE user_id = ?")->execute([$uid]);
                    $db->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$uid]);

                    $stmtReqs = $db->prepare("SELECT id FROM service_requests WHERE user_id = ?");
                    $stmtReqs->execute([$uid]);
                    $reqIds = $stmtReqs->fetchAll(PDO::FETCH_COLUMN);
                    if (!empty($reqIds)) {
                        $inClause = implode(',', array_fill(0, count($reqIds), '?'));
                        $db->prepare("DELETE FROM service_request_responses WHERE request_id IN ($inClause)")->execute($reqIds);
                        $db->prepare("DELETE FROM reviews WHERE request_id IN ($inClause)")->execute($reqIds);
                        $db->prepare("DELETE FROM service_requests WHERE user_id = ?")->execute([$uid]);
                    }

                    $db->prepare("DELETE FROM profiles WHERE user_id = ?")->execute([$uid]);
                    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
                    $deleted_count++;
                }

                $db->commit();
                set_flash('success', "Berhasil menghapus {$deleted_count} akun secara permanen.");
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', 'Gagal melakukan penghapusan massal: ' . $e->getMessage());
            }

            redirect('/admin/users.php');
        }
    }
}

$role_filter = $_GET['role'] ?? '';
$verif_filter = $_GET['verif'] ?? '';
$where_clauses = [];
$params = [];

// Saring: Jangan tampilkan akun yang belum menyelesaikan verifikasi OTP di daftar utama
if ($role_filter === 'unverified') {
    $where_clauses[] = "u.email_verified_at IS NULL";
} else {
    $where_clauses[] = "u.email_verified_at IS NOT NULL";
    if (!empty($role_filter)) {
        $where_clauses[] = "r.name = ?";
        $params[] = $role_filter;
    }
}

// Saring Status Verifikasi & Blokir
if ($verif_filter === 'verified') {
    $where_clauses[] = "sp.is_verified = 1";
} elseif ($verif_filter === 'pending') {
    $where_clauses[] = "(sp.is_verified = 0 OR sp.is_verified IS NULL) AND (sp.id_card_image IS NOT NULL AND LENGTH(sp.id_card_image) > 50)";
} elseif ($verif_filter === 'unuploaded') {
    $where_clauses[] = "(sp.is_verified = 0 OR sp.is_verified IS NULL) AND (sp.id_card_image IS NULL OR LENGTH(sp.id_card_image) <= 50) AND r.name = 'penyedia'";
} elseif ($verif_filter === 'blocked') {
    $where_clauses[] = "u.is_active = 0";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "";

$stmt = $db->prepare("
    SELECT u.id, u.role_id, u.name, u.email, u.phone, u.is_active, u.block_reason, u.blocked_at, u.email_verified_at, u.created_at,
           r.name as role_name, r.display_name as role_display,
           p.district_id, p.address, p.gender, p.birth_date,
           d.name as district_name, sp.business_name, sp.id as provider_id,
           sp.is_verified, sp.id_card_number, sp.id_card_type, sp.school_name,
           IF(sp.id_card_image IS NOT NULL AND LENGTH(sp.id_card_image) > 50, 1, 0) as has_ktp,
           sp.credential_title,
           IF(sp.certificate_url IS NOT NULL AND LENGTH(sp.certificate_url) > 50, 1, 0) as has_cert
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN profiles p ON u.id = p.user_id
    LEFT JOIN districts d ON p.district_id = d.id
    LEFT JOIN service_providers sp ON u.id = sp.user_id
    {$where_sql}
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Hitung jumlah statistik untuk tab navigasi Peran
$count_all = (int)$db->query("SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL")->fetchColumn();
$count_pengguna = (int)$db->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'pengguna' AND u.email_verified_at IS NOT NULL")->fetchColumn();
$count_penyedia = (int)$db->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'penyedia' AND u.email_verified_at IS NOT NULL")->fetchColumn();
$count_admin = (int)$db->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin' AND u.email_verified_at IS NOT NULL")->fetchColumn();
$count_unverified = (int)$db->query("SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL")->fetchColumn();

// Hitung jumlah statistik untuk tab Status Verifikasi & Blokir
$count_verif_approved = (int)$db->query("SELECT COUNT(*) FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE sp.is_verified = 1 AND u.email_verified_at IS NOT NULL")->fetchColumn();
$count_verif_pending = (int)$db->query("SELECT COUNT(*) FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE (sp.is_verified = 0 OR sp.is_verified IS NULL) AND (sp.id_card_image IS NOT NULL AND LENGTH(sp.id_card_image) > 50) AND u.email_verified_at IS NOT NULL")->fetchColumn();
$count_verif_unuploaded = (int)$db->query("SELECT COUNT(*) FROM service_providers sp JOIN users u ON sp.user_id = u.id WHERE (sp.is_verified = 0 OR sp.is_verified IS NULL) AND (sp.id_card_image IS NULL OR LENGTH(sp.id_card_image) <= 50) AND u.email_verified_at IS NOT NULL")->fetchColumn();
$count_blocked = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 0 AND email_verified_at IS NOT NULL")->fetchColumn();

$districts = get_all_districts();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">Daftar & Edit Pengguna Platform</h1>
        <p class="text-muted small mb-0">Kelola akun administrator, masyarakat (pengguna), dan penyedia jasa lokal di Inhu</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/admin/export_providers.php" target="_blank" class="btn btn-sm btn-danger shadow-xs fw-bold">
            <i class="fa-solid fa-file-pdf me-1"></i> Cetak / Unduh PDF Data Mitra
        </a>
        <a href="<?= BASE_URL ?>/admin/export_providers.php?format=excel" class="btn btn-sm btn-success shadow-xs fw-bold">
            <i class="fa-solid fa-file-excel me-1"></i> Unduh Excel (CSV)
        </a>
        <a href="<?= BASE_URL ?>/admin/profile.php" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-user-gear me-1"></i> Edit Akun Admin Saya
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-4">
        <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<!-- Filter Role & Status Verifikasi -->
<div class="mb-3">
    <!-- Filter Role Tabs -->
    <div class="d-flex justify-content-between align-items-center mb-2.5 flex-wrap gap-2">
        <div class="btn-group shadow-xs">
            <a href="<?= BASE_URL ?>/admin/users.php<?= !empty($verif_filter) ? '?verif=' . urlencode($verif_filter) : '' ?>" class="btn btn-sm <?= empty($role_filter) ? 'btn-primary fw-semibold' : 'btn-outline-secondary bg-white' ?>">Semua (<?= $count_all ?>)</a>
            <a href="<?= BASE_URL ?>/admin/users.php?role=pengguna<?= !empty($verif_filter) ? '&verif=' . urlencode($verif_filter) : '' ?>" class="btn btn-sm <?= $role_filter === 'pengguna' ? 'btn-primary fw-semibold' : 'btn-outline-secondary bg-white' ?>">Masyarakat (<?= $count_pengguna ?>)</a>
            <a href="<?= BASE_URL ?>/admin/users.php?role=penyedia<?= !empty($verif_filter) ? '&verif=' . urlencode($verif_filter) : '' ?>" class="btn btn-sm <?= $role_filter === 'penyedia' ? 'btn-primary fw-semibold' : 'btn-outline-secondary bg-white' ?>">Penyedia Jasa (<?= $count_penyedia ?>)</a>
            <a href="<?= BASE_URL ?>/admin/users.php?role=admin<?= !empty($verif_filter) ? '&verif=' . urlencode($verif_filter) : '' ?>" class="btn btn-sm <?= $role_filter === 'admin' ? 'btn-primary fw-semibold' : 'btn-outline-secondary bg-white' ?>">Admin (<?= $count_admin ?>)</a>
        </div>
        <?php if ($count_unverified > 0): ?>
            <a href="<?= BASE_URL ?>/admin/users.php?role=unverified" class="btn btn-sm <?= $role_filter === 'unverified' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark bg-white' ?> shadow-xs">
                <i class="fa-solid fa-clock me-1"></i> Belum Selesai OTP (<?= $count_unverified ?>)
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Status Verifikasi & Blokir (Penyaring Khusus) -->
    <div class="card border-0 shadow-xs bg-light rounded-3 p-2.5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                <span class="text-muted small fw-semibold me-1"><i class="fa-solid fa-filter text-teal me-1"></i> Status Verifikasi:</span>
                <a href="<?= BASE_URL ?>/admin/users.php<?= !empty($role_filter) ? '?role=' . urlencode($role_filter) : '' ?>" 
                   class="btn btn-sm py-1 px-2.5 rounded-pill <?= empty($verif_filter) ? 'btn-dark text-white fw-semibold' : 'btn-white bg-white border text-secondary' ?>" style="font-size: 0.78rem;">
                    Semua
                </a>
                <a href="<?= BASE_URL ?>/admin/users.php?<?= http_build_query(array_filter(array_merge($_GET, ['verif' => 'verified']))) ?>" 
                   class="btn btn-sm py-1 px-2.5 rounded-pill <?= $verif_filter === 'verified' ? 'btn-success text-white fw-semibold shadow-xs' : 'btn-white bg-white border text-success' ?>" style="font-size: 0.78rem;">
                    <i class="fa-solid fa-circle-check me-1"></i> Terverifikasi KTP (<?= $count_verif_approved ?>)
                </a>
                <a href="<?= BASE_URL ?>/admin/users.php?<?= http_build_query(array_filter(array_merge($_GET, ['verif' => 'pending']))) ?>" 
                   class="btn btn-sm py-1 px-2.5 rounded-pill <?= $verif_filter === 'pending' ? 'btn-warning text-dark fw-bold shadow-xs' : 'btn-white bg-white border text-warning-emphasis' ?>" style="font-size: 0.78rem;">
                    <i class="fa-solid fa-clock me-1"></i> Menunggu Review (<?= $count_verif_pending ?>)
                </a>
                <a href="<?= BASE_URL ?>/admin/users.php?<?= http_build_query(array_filter(array_merge($_GET, ['verif' => 'unuploaded']))) ?>" 
                   class="btn btn-sm py-1 px-2.5 rounded-pill <?= $verif_filter === 'unuploaded' ? 'btn-secondary text-white fw-semibold shadow-xs' : 'btn-white bg-white border text-muted' ?>" style="font-size: 0.78rem;">
                    <i class="fa-solid fa-id-card-clip me-1"></i> Belum Unggah KTP (<?= $count_verif_unuploaded ?>)
                </a>
                <a href="<?= BASE_URL ?>/admin/users.php?<?= http_build_query(array_filter(array_merge($_GET, ['verif' => 'blocked']))) ?>" 
                   class="btn btn-sm py-1 px-2.5 rounded-pill <?= $verif_filter === 'blocked' ? 'btn-danger text-white fw-semibold shadow-xs' : 'btn-white bg-white border text-danger' ?>" style="font-size: 0.78rem;">
                    <i class="fa-solid fa-ban me-1"></i> Diblokir (<?= $count_blocked ?>)
                </a>
            </div>
            <?php if (!empty($verif_filter) || !empty($role_filter)): ?>
                <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm btn-link text-decoration-none text-muted py-0 small">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset Filter
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($role_filter === 'unverified'): ?>
    <div class="alert alert-warning border-0 shadow-xs mb-3 d-flex align-items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation text-warning fs-5"></i>
        <div>
            <strong class="text-dark">Data Pendaftaran Belum Selesai OTP:</strong>
            <div class="small text-muted">Akun-akun berikut baru mengisi formulir registrasi tetapi belum menyelesaikan verifikasi OTP. Akun ini tidak tampil di daftar pengguna aktif. Anda dapat menghapus akun ini jika pengguna salah input data.</div>
        </div>
    </div>
<?php endif; ?>

<!-- Bar Aksi Massal (Muncul Otomatis Saat Ada Akun yang Diceklis) -->
<div id="bulkActionBar" class="card border-danger bg-danger-subtle shadow-sm mb-3 d-none">
    <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-circle-check text-danger fs-5"></i>
            <span class="fw-bold text-danger"><span id="selectedCount">0</span> akun dipilih</span>
            <span class="text-muted small d-none d-md-inline">&bull; Hapus massal akun mitra/pengguna terpilih secara cepat</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary bg-white py-1 px-3 shadow-sm" onclick="deselectAllUsers()">
                <i class="fa-solid fa-xmark me-1"></i> Batal Pilih
            </button>
            <button type="button" class="btn btn-sm btn-danger py-1 px-3 fw-bold shadow-sm" onclick="openBulkDeleteModal()">
                <i class="fa-solid fa-trash-can me-1"></i> Hapus yang Dipilih (<span id="selectedCountBtn">0</span>)
            </button>
        </div>
    </div>
</div>

<div class="card border shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" id="usersTable">
            <thead class="table-light">
                <tr>
                    <th style="width: 42px;" class="text-center">
                        <input type="checkbox" class="form-check-input" id="checkAllUsers" title="Pilih Semua di Halaman Ini" onchange="toggleSelectAll(this)">
                    </th>
                    <th style="width: 45px;" class="text-center">No.</th>
                    <th>Nama & Profil Usaha</th>
                    <th style="width: 105px;">Peran</th>
                    <th>Nomor WhatsApp / HP</th>
                    <th>Kecamatan</th>
                    <th>Terdaftar</th>
                    <th class="text-center" style="width: 125px;">Status</th>
                    <th class="text-end" style="width: 135px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($users as $u): ?>
                    <tr id="userRow_<?= $u['id'] ?>">
                        <td class="text-center">
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                <input type="checkbox" class="form-check-input user-row-check" value="<?= $u['id'] ?>" data-name="<?= e($u['name']) ?>" onchange="updateSelectedCount()">
                            <?php else: ?>
                                <span class="text-muted" title="Akun Admin Anda Sendiri (Terkunci)"><i class="fa-solid fa-lock text-muted" style="font-size: 0.8rem;"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold text-secondary"><?= $no++ ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                <span class="fw-bold text-dark"><?= e($u['name']) ?></span>
                                <span class="badge bg-light text-teal border py-0.5 px-1.5" style="font-size: 0.68rem;" title="ID Database: <?= $u['id'] ?>">
                                    <?= format_user_id($u['id']) ?>
                                </span>
                            </div>
                            <?php if ($u['business_name']): ?>
                                <div class="text-teal fw-semibold" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-store me-1"></i> <?= e($u['business_name']) ?>
                                </div>
                                <div class="mt-1 d-flex align-items-center gap-1.5 flex-wrap">
                                    <?php 
                                    $isPelajar = (($u['id_card_type'] ?? '') === 'pelajar');
                                    $docTypeLabel = $isPelajar ? 'Kartu Pelajar' : 'KTP';
                                    ?>
                                    <?php if (!empty($u['is_verified'])): ?>
                                        <?php if ($isPelajar): ?>
                                            <span class="badge bg-purple-subtle text-purple border border-purple-subtle py-0.5 px-1.5" style="font-size: 0.68rem;" title="NISN: <?= e($u['id_card_number'] ?: '-') ?> • <?= e($u['school_name'] ?: 'Pelajar') ?>">
                                                <i class="fa-solid fa-graduation-cap me-0.5"></i> Pelajar Terverifikasi <?= !empty($u['school_name']) ? '(' . e($u['school_name']) . ')' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-1.5" style="font-size: 0.68rem;" title="NIK: <?= e($u['id_card_number'] ?: 'Belum ada NIK') ?>">
                                                <i class="fa-solid fa-circle-check me-0.5"></i> KTP Terverifikasi
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif (!empty($u['has_ktp'])): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0.5 px-1.5" style="font-size: 0.68rem;">
                                            <i class="fa-solid fa-clock me-0.5"></i> Menunggu Verifikasi <?= $docTypeLabel ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border py-0.5 px-1.5" style="font-size: 0.68rem;">
                                            Belum Unggah Identitas
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($u['has_ktp'])): ?>
                                        <a href="<?= BASE_URL ?>/admin/view_doc.php?user_id=<?= $u['id'] ?>&type=ktp" 
                                           onclick="openAdminDocPreview(event, <?= $u['id'] ?>, '<?= $isPelajar ? 'pelajar' : 'ktp' ?>', '<?= addslashes(htmlspecialchars($u['name'], ENT_QUOTES)) ?>', '<?= addslashes(htmlspecialchars($u['business_name'] ?? '', ENT_QUOTES)) ?>', '<?= addslashes(htmlspecialchars(($isPelajar && !empty($u['school_name']) ? ($u['school_name'] . ' • ') : '') . ($u['id_card_number'] ?? ''), ENT_QUOTES)) ?>', <?= !empty($u['is_verified']) ? 'true' : 'false' ?>)"
                                           target="_blank" 
                                           class="badge <?= $isPelajar ? 'bg-purple-subtle text-purple border border-purple-subtle' : 'bg-info-subtle text-info-emphasis border border-info-subtle' ?> text-decoration-none py-1 px-2 cursor-pointer shadow-2xs d-inline-flex align-items-center" 
                                           style="font-size: 0.68rem;"
                                           title="Klik untuk pratinjau <?= $docTypeLabel ?> dan verifikasi">
                                            <i class="fa-solid <?= $isPelajar ? 'fa-graduation-cap me-1' : 'fa-id-card me-1 text-info' ?>"></i> Foto <?= $docTypeLabel ?> <i class="fa-solid fa-magnifying-glass-plus ms-1 opacity-75"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($u['has_cert'])): ?>
                                        <a href="<?= BASE_URL ?>/admin/view_doc.php?user_id=<?= $u['id'] ?>&type=str" 
                                           onclick="openAdminDocPreview(event, <?= $u['id'] ?>, 'str', '<?= addslashes(htmlspecialchars($u['name'], ENT_QUOTES)) ?>', '<?= addslashes(htmlspecialchars($u['business_name'] ?? '', ENT_QUOTES)) ?>', '<?= addslashes(htmlspecialchars($u['credential_title'] ?? '', ENT_QUOTES)) ?>', <?= !empty($u['is_verified']) ? 'true' : 'false' ?>)"
                                           target="_blank" 
                                           class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none py-1 px-2 cursor-pointer shadow-2xs d-inline-flex align-items-center" 
                                           style="font-size: 0.68rem;"
                                           title="Klik untuk pratinjau berkas STR / Sertifikat">
                                            <i class="fa-solid fa-file-shield me-1 text-primary"></i> Berkas STR <i class="fa-solid fa-magnifying-glass-plus ms-1 opacity-75"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="text-muted" style="font-size: 0.75rem;"><?= e($u['email']) ?></div>
                            <?php if (!empty($u['gender']) || !empty($u['birth_date'])): ?>
                                <div class="text-secondary mt-0.5" style="font-size: 0.7rem;">
                                    <?php if (!empty($u['gender'])): ?>
                                        <span><?= $u['gender'] === 'male' ? '<i class="fa-solid fa-person text-primary"></i> Laki-laki' : ($u['gender'] === 'female' ? '<i class="fa-solid fa-person-dress text-danger"></i> Perempuan' : 'Lainnya') ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($u['birth_date'])): ?>
                                        <span class="ms-1 text-muted"><i class="fa-regular fa-calendar ms-1 me-0.5"></i> <?= date('d M Y', strtotime($u['birth_date'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $role_badge = match($u['role_name']) {
                                'admin'    => 'text-bg-danger',
                                'penyedia' => 'text-bg-success',
                                default    => 'text-bg-primary'
                            };
                            ?>
                            <span class="badge <?= $role_badge ?>"><?= e($u['role_display']) ?></span>
                        </td>
                        <td>
                            <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $u['phone'])) ?>" target="_blank" class="text-decoration-none fw-semibold">
                                <i class="fa-brands fa-whatsapp text-success me-1"></i> <?= e($u['phone']) ?>
                            </a>
                        </td>
                        <td><?= e($u['district_name'] ?: 'Kab. Inhu') ?></td>
                        <td><?= format_date($u['created_at']) ?></td>
                        <td class="text-center">
                            <?php if (empty($u['email_verified_at'])): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" title="Pendaftar belum menyelesaikan verifikasi kode OTP">
                                    <i class="fa-solid fa-clock me-1"></i> Menunggu OTP
                                </span>
                            <?php elseif ($u['is_active']): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="fa-solid fa-circle-check me-0.5"></i> Aktif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-inline-flex align-items-center gap-1">
                                    <i class="fa-solid fa-ban"></i> Diblokir
                                </span>
                                <?php if (!empty($u['block_reason'])): ?>
                                    <div class="text-danger small mt-1 text-center fw-medium" title="Alasan: <?= e($u['block_reason']) ?>" style="font-size: 0.68rem; line-height: 1.25;">
                                        <i class="fa-solid fa-circle-exclamation me-0.5"></i> <?= e(mb_strimwidth($u['block_reason'], 0, 24, '...')) ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                <?php if (empty($u['email_verified_at'])): ?>
                                    <!-- Tombol Verifikasi Akun Manual oleh Admin -->
                                    <form method="POST" action="<?= BASE_URL ?>/admin/users.php" class="d-inline" onsubmit="return confirm('Verifikasi akun ini sekarang secara manual?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="manual_verify_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success py-1 px-2" style="font-size: 0.75rem;" title="Verifikasi Akun Manual (Bypass OTP)">
                                            <i class="fa-solid fa-check-double me-1"></i> Verif OTP
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($u['role_name'] === 'penyedia'): ?>
                                    <!-- Tombol Cepat Verifikasi / Batal Verifikasi KTP Mitra -->
                                    <form method="POST" action="<?= BASE_URL ?>/admin/users.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_ktp_verified">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= empty($u['is_verified']) ? 1 : 0 ?>">
                                        <button type="submit" class="btn btn-sm <?= empty($u['is_verified']) ? 'btn-outline-success' : 'btn-outline-secondary' ?> py-1 px-2" style="font-size: 0.75rem;" title="<?= empty($u['is_verified']) ? 'Setujui Verifikasi KTP Mitra' : 'Batalkan Status Verifikasi KTP' ?>">
                                            <i class="fa-solid <?= empty($u['is_verified']) ? 'fa-id-card' : 'fa-xmark' ?>"></i> <?= empty($u['is_verified']) ? 'Verif' : 'Batal' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Tombol Edit Akun -->
                                <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['id'] ?>" style="font-size: 0.75rem;" title="Edit Data Akun">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>

                                <!-- Dropdown Aksi Lanjutan (Titik Tiga) -->
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-outline-secondary py-1 px-2 dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Aksi Lainnya">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 py-1.5" style="font-size: 0.8rem; min-width: 175px;">
                                        <li>
                                            <button type="button" class="dropdown-item py-1.5 px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#resetPassModal<?= $u['id'] ?>">
                                                <i class="fa-solid fa-key text-warning"></i> Reset Kata Sandi
                                            </button>
                                        </li>
                                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <?php if ($u['is_active']): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item py-1.5 px-3 d-flex align-items-center gap-2 text-warning" data-bs-toggle="modal" data-bs-target="#blockModal<?= $u['id'] ?>">
                                                        <i class="fa-solid fa-ban"></i> Blokir Akun...
                                                    </button>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <form method="POST" action="<?= BASE_URL ?>/admin/users.php" class="m-0">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                        <input type="hidden" name="new_status" value="1">
                                                        <button type="submit" class="dropdown-item py-1.5 px-3 d-flex align-items-center gap-2 text-success">
                                                            <i class="fa-solid fa-circle-check"></i> Buka Blokir
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <button type="button" class="dropdown-item py-1.5 px-3 d-flex align-items-center gap-2 text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $u['id'] ?>">
                                                    <i class="fa-solid fa-trash-can"></i> Hapus Permanen
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <!-- Modal Edit Pengguna -->
                            <div class="modal fade text-start" id="editModal<?= $u['id'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= $u['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content" style="border-radius: 14px;">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold" id="modalLabel<?= $u['id'] ?>">
                                                <i class="fa-solid fa-user-pen text-primary me-2"></i> Edit Akun: <?= e($u['name']) ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="<?= BASE_URL ?>/admin/users.php">
                                            <div class="modal-body p-4">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="edit_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">

                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                                                    <input type="text" name="name" class="form-control form-control-sm" value="<?= e($u['name']) ?>" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Nomor WhatsApp / Handphone <span class="text-danger">*</span></label>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-light text-success"><i class="fa-brands fa-whatsapp"></i></span>
                                                        <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx" value="<?= e($u['phone']) ?>" required>
                                                    </div>
                                                    <div class="form-text" style="font-size: 0.72rem;">Masukkan nomor handphone aktif yang sesuai.</div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                                                    <input type="email" name="email" class="form-control form-control-sm" value="<?= e($u['email']) ?>" required>
                                                </div>

                                                <?php if ($u['role_name'] === 'penyedia'): ?>
                                                    <div class="mb-3 p-2 bg-light rounded border">
                                                        <label class="form-label small fw-semibold text-teal mb-1">
                                                            <i class="fa-solid fa-store me-1"></i> Nama Usaha / Bengkel
                                                        </label>
                                                        <input type="text" name="business_name" class="form-control form-control-sm" value="<?= e($u['business_name'] ?? '') ?>" placeholder="Nama usaha jasa">
                                                    </div>
                                                <?php endif; ?>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Kecamatan di Inhu</label>
                                                    <select name="district_id" class="form-select form-select-sm">
                                                        <option value="">-- Pilih Kecamatan --</option>
                                                        <?php foreach ($districts as $d): ?>
                                                            <option value="<?= $d['id'] ?>" <?= ($u['district_id'] == $d['id']) ? 'selected' : '' ?>>
                                                                Kec. <?= e($d['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label small fw-semibold">Jenis Kelamin</label>
                                                        <select name="gender" class="form-select form-select-sm">
                                                            <option value="">-- Belum Diatur --</option>
                                                            <option value="male" <?= ($u['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Laki-laki</option>
                                                            <option value="female" <?= ($u['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Perempuan</option>
                                                            <option value="other" <?= ($u['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Lainnya</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label small fw-semibold">Tanggal Lahir</label>
                                                        <input type="date" name="birth_date" class="form-control form-control-sm" value="<?= e($u['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                                                    </div>
                                                </div>

                                                <div class="mb-3 p-2.5 bg-light rounded-3 border">
                                                    <label class="form-label small fw-semibold text-dark mb-1">
                                                        <i class="fa-solid fa-key text-warning me-1"></i> Ganti Kata Sandi (Opsional)
                                                    </label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="new_password" id="editPassInput<?= $u['id'] ?>" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password" minlength="6">
                                                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('editPassInput<?= $u['id'] ?>').value='inhu' + Math.floor(1000 + Math.random() * 9000)" title="Buat password acak">
                                                            <i class="fa-solid fa-dice me-1"></i> Acak
                                                        </button>
                                                    </div>
                                                    <div class="form-text" style="font-size: 0.72rem;">Isi jika pengguna lupa password dan meminta bantuan admin (min. 6 karakter).</div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Status Akun</label>
                                                    <select name="is_active" class="form-select form-select-sm">
                                                        <option value="1" <?= $u['is_active'] ? 'selected' : '' ?>>Aktif (Dapat Login)</option>
                                                        <option value="0" <?= !$u['is_active'] ? 'selected' : '' ?>>Nonaktif / Diblokir</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-sm btn-primary-custom">
                                                    <i class="fa-solid fa-save me-1"></i> Simpan Perubahan
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Reset Password -->
                            <div class="modal fade text-start" id="resetPassModal<?= $u['id'] ?>" tabindex="-1" aria-labelledby="resetPassModalLabel<?= $u['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                                    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="resetPassModalLabel<?= $u['id'] ?>">
                                                <i class="fa-solid fa-key text-warning"></i> Reset Kata Sandi
                                            </h5>
                                            <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                        </div>
                                        <form method="POST" action="<?= BASE_URL ?>/admin/users.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">

                                            <div class="modal-body py-3">
                                                <p class="small text-muted mb-3">
                                                    Atur kata sandi baru untuk akun <strong><?= e($u['name']) ?></strong> (<?= e($u['email']) ?>).
                                                </p>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Kata Sandi Baru <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="text" name="new_password" id="inputResetPass<?= $u['id'] ?>" class="form-control" value="inhu<?= rand(1000, 9999) ?>" required minlength="6">
                                                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('inputResetPass<?= $u['id'] ?>').value='inhu' + Math.floor(1000 + Math.random() * 9000)" title="Buat password acak baru">
                                                            <i class="fa-solid fa-dice me-1"></i> Acak
                                                        </button>
                                                    </div>
                                                    <div class="form-text" style="font-size: 0.72rem;">Minimal 6 karakter. Langsung aktif seketika setelah disimpan.</div>
                                                </div>

                                                <div class="p-2.5 rounded-3 bg-light border small text-muted">
                                                    <i class="fa-brands fa-whatsapp text-success me-1"></i>
                                                    Setelah disimpan, Anda dapat langsung menginfokan kata sandi baru ini ke pengguna via WhatsApp: <strong><?= e($u['phone']) ?></strong>.
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-sm btn-primary-custom fw-semibold px-3">
                                                    <i class="fa-solid fa-check me-1"></i> Simpan Password Baru
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                <!-- Modal Konfirmasi Hapus Pengguna / Mitra -->
                                <div class="modal fade text-start" id="deleteModal<?= $u['id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $u['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                                        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteModalLabel<?= $u['id'] ?>">
                                                    <i class="fa-solid fa-triangle-exclamation"></i> Konfirmasi Hapus Akun
                                                </h5>
                                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                            </div>
                                            <div class="modal-body py-3">
                                                <p class="mb-2 text-dark">
                                                    Apakah Anda yakin ingin menghapus akun <strong><?= e($u['name']) ?></strong>
                                                    <?php if ($u['business_name']): ?>
                                                        (Usaha: <strong class="text-teal"><?= e($u['business_name']) ?></strong>)
                                                    <?php endif; ?>?
                                                </p>
                                                <div class="alert alert-danger py-2 px-3 small mb-0 rounded-3 border-0 bg-danger-subtle text-danger">
                                                    <i class="fa-solid fa-circle-exclamation me-1"></i>
                                                    <strong>Peringatan:</strong> Seluruh data profil, layanan, wilayah jangkauan, dan hak akses login akan dihapus secara permanen dari sistem. Tindakan ini tidak dapat dibatalkan.
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0">
                                                <button type="button" class="btn btn-sm btn-light px-3" data-bs-dismiss="modal">Batal</button>
                                                <form method="POST" action="<?= BASE_URL ?>/admin/users.php" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger px-3 fw-bold shadow-sm">
                                                        <i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus Permanen
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Blokir Akun dengan Alasan -->
                                <div class="modal fade text-start" id="blockModal<?= $u['id'] ?>" tabindex="-1" aria-labelledby="blockModalLabel<?= $u['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
                                        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                                            <div class="modal-header border-0 pb-0">
                                                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="blockModalLabel<?= $u['id'] ?>">
                                                    <i class="fa-solid fa-ban"></i> Blokir Akun Pengguna
                                                </h5>
                                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                            </div>
                                            <form method="POST" action="<?= BASE_URL ?>/admin/users.php">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="new_status" value="0">
                                                <div class="modal-body py-3">
                                                    <p class="mb-2 text-dark small">
                                                        Anda akan menonaktifkan akun <strong><?= e($u['name']) ?></strong> 
                                                        <?php if ($u['business_name']): ?>(<strong class="text-teal"><?= e($u['business_name']) ?></strong>)<?php endif; ?>. Pengguna tidak akan dapat masuk ke sistem.
                                                    </p>

                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold text-secondary">Pilih Alasan Cepat (Template):</label>
                                                        <select class="form-select form-select-sm" onchange="fillBlockReason(<?= $u['id'] ?>, this.value)">
                                                            <option value="">-- Pilih Template Alasan --</option>
                                                            <option value="Pelanggaran syarat & ketentuan layanan platform">Pelanggaran syarat & ketentuan layanan platform</option>
                                                            <option value="Data identitas / KTP tidak valid atau palsu">Data identitas / KTP tidak valid atau palsu</option>
                                                            <option value="Terdapat laporan penipuan atau keluhan berulang dari pelanggan">Laporan penipuan atau keluhan berulang dari pelanggan</option>
                                                            <option value="Akun terindikasi spam, penyalahgunaan, atau tidak aktif">Akun terindikasi spam / tidak aktif</option>
                                                            <option value="Permintaan penonaktifan langsung oleh pemilik akun">Permintaan penonaktifan oleh pemilik akun</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-2">
                                                        <label class="form-label small fw-semibold text-dark">Alasan Pemblokiran <span class="text-danger">*</span></label>
                                                        <textarea name="block_reason" id="blockReasonInput_<?= $u['id'] ?>" class="form-control form-control-sm" rows="3" placeholder="Tulis alasan pemblokiran akun ini..." required></textarea>
                                                        <div class="form-text" style="font-size: 0.72rem;">Alasan ini akan tercatat di sistem dan ditampilkan kepada pengguna saat mencoba login.</div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-sm btn-light px-3" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-sm btn-danger px-3 fw-bold shadow-sm">
                                                        <i class="fa-solid fa-ban me-1"></i> Ya, Blokir Akun
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Massal -->
<div class="modal fade text-start" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="bulkDeleteModalLabel">
                    <i class="fa-solid fa-triangle-exclamation"></i> Konfirmasi Hapus Massal
                </h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/admin/users.php" id="bulkDeleteForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_delete_users">
                <div id="bulkDeleteHiddenInputs"></div>

                <div class="modal-body py-3">
                    <p class="mb-2 text-dark">
                        Apakah Anda yakin ingin menghapus <strong id="modalBulkCountText" class="text-danger">0</strong> akun yang dipilih secara permanen?
                    </p>
                    <div class="alert alert-danger py-2 px-3 small mb-0 rounded-3 border-0 bg-danger-subtle text-danger">
                        <i class="fa-solid fa-circle-exclamation me-1"></i>
                        <strong>Peringatan Serius:</strong> Tindakan ini akan menghapus tuntas seluruh profil usaha mitra, wilayah layanan, ulasan, penawaran kerja, dan akses login dari akun yang dipilih. Data yang sudah dihapus tidak dapat dipulihkan.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-light px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3 fw-bold shadow-sm">
                        <i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus Semua Terpilih
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSelectAll(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.user-row-check');
    checkboxes.forEach(cb => {
        cb.checked = masterCheckbox.checked;
        const row = document.getElementById('userRow_' + cb.value);
        if (row) {
            if (cb.checked) {
                row.classList.add('table-danger');
            } else {
                row.classList.remove('table-danger');
            }
        }
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.user-row-check:checked');
    const allCheckboxes = document.querySelectorAll('.user-row-check');
    const count = checkboxes.length;
    
    // Update counter text
    const countEl = document.getElementById('selectedCount');
    const countBtnEl = document.getElementById('selectedCountBtn');
    if (countEl) countEl.textContent = count;
    if (countBtnEl) countBtnEl.textContent = count;
    
    // Show/hide bar
    const bar = document.getElementById('bulkActionBar');
    if (bar) {
        if (count > 0) {
            bar.classList.remove('d-none');
        } else {
            bar.classList.add('d-none');
        }
    }
    
    // Master checkbox state
    const master = document.getElementById('checkAllUsers');
    if (master) {
        master.checked = (allCheckboxes.length > 0 && count === allCheckboxes.length);
        master.indeterminate = (count > 0 && count < allCheckboxes.length);
    }

    // Highlight rows
    allCheckboxes.forEach(cb => {
        const row = document.getElementById('userRow_' + cb.value);
        if (row) {
            if (cb.checked) {
                row.classList.add('table-danger');
            } else {
                row.classList.remove('table-danger');
            }
        }
    });
}

function deselectAllUsers() {
    const checkboxes = document.querySelectorAll('.user-row-check');
    checkboxes.forEach(cb => {
        cb.checked = false;
        const row = document.getElementById('userRow_' + cb.value);
        if (row) row.classList.remove('table-danger');
    });
    const master = document.getElementById('checkAllUsers');
    if (master) {
        master.checked = false;
        master.indeterminate = false;
    }
    updateSelectedCount();
}

function openBulkDeleteModal() {
    const checked = document.querySelectorAll('.user-row-check:checked');
    if (checked.length === 0) {
        alert('Silakan pilih minimal satu akun yang ingin dihapus.');
        return;
    }
    
    const container = document.getElementById('bulkDeleteHiddenInputs');
    container.innerHTML = '';
    
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'user_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    
    document.getElementById('modalBulkCountText').textContent = checked.length;
    
    const modalEl = document.getElementById('bulkDeleteModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function openAdminDocPreview(e, userId, type, userName, businessName, metaInfo, isVerified) {
    if (e) e.preventDefault();
    
    const docUrl = `<?= BASE_URL ?>/admin/view_doc.php?user_id=${userId}&type=${type}`;
    const downloadUrl = `<?= BASE_URL ?>/admin/view_doc.php?user_id=${userId}&type=${type}&download=1`;
    const isPelajar = (type === 'pelajar');
    const isKtp = (type === 'ktp' || isPelajar);
    const titleText = isPelajar ? `Foto Kartu Pelajar: ${userName}` : (isKtp ? `Foto KTP: ${userName}` : `Berkas STR / Sertifikat: ${userName}`);
    const metaLabel = isPelajar 
        ? (metaInfo ? `<strong>Sekolah / NISN:</strong> <code>${metaInfo}</code>` : '<span class="text-danger fw-semibold">Belum mencantumkan nomor Kartu Pelajar</span>')
        : (isKtp ? (metaInfo ? `<strong>NIK:</strong> <code>${metaInfo}</code>` : '<span class="text-danger fw-semibold">Belum mencantumkan nomor NIK</span>') : (metaInfo ? `<strong>Kualifikasi:</strong> ${metaInfo}` : 'STR Medis / Sertifikat Keahlian'));

    let verifyActionBtnHtml = '';
    if (isKtp) {
        const verifyBtnText = isPelajar ? 'Setujui Verifikasi Pelajar' : 'Setujui Verifikasi KTP';
        const cancelBtnText = isPelajar ? 'Batalkan Verifikasi Pelajar' : 'Batalkan Verifikasi KTP';
        if (!isVerified) {
            verifyActionBtnHtml = `
                <button type="button" class="btn ${isPelajar ? 'btn-primary' : 'btn-success'} fw-semibold px-3 py-2 rounded-pill shadow-xs d-inline-flex align-items-center gap-1.5" onclick="submitVerifyFromModal(${userId}, 1)">
                    <i class="fa-solid fa-circle-check"></i> <span>${verifyBtnText}</span>
                </button>
            `;
        } else {
            verifyActionBtnHtml = `
                <button type="button" class="btn btn-outline-secondary fw-semibold px-3 py-2 rounded-pill shadow-xs d-inline-flex align-items-center gap-1.5" onclick="submitVerifyFromModal(${userId}, 0)">
                    <i class="fa-solid fa-xmark"></i> <span>${cancelBtnText}</span>
                </button>
            `;
        }
    }

    if (typeof Swal !== 'undefined') {
        const badgeText = isVerified ? (isPelajar ? '🎓 Pelajar Terverifikasi' : 'KTP Terverifikasi') : (isPelajar ? 'Menunggu Verifikasi Pelajar' : 'Menunggu Verifikasi KTP');
        const badgeClass = isVerified ? (isPelajar ? 'bg-purple-subtle text-purple border border-purple-subtle' : 'bg-success-subtle text-success border border-success-subtle') : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        Swal.fire({
            title: `<div class="d-flex align-items-center gap-2 justify-content-center text-dark fs-5 fw-bold"><i class="fa-solid ${isPelajar ? 'fa-graduation-cap text-purple' : (isKtp ? 'fa-id-card text-info' : 'fa-file-shield text-teal')}"></i> <span>${titleText}</span></div>`,
            html: `
                <div class="mb-2.5 text-muted small d-flex align-items-center justify-content-center gap-2 flex-wrap">
                    <span class="badge bg-light text-dark border px-2 py-1">${businessName || userName}</span>
                    <span class="badge ${badgeClass} px-2 py-1">
                        <i class="fa-solid ${isVerified ? 'fa-check-circle' : 'fa-clock'} me-1"></i> ${badgeText}
                    </span>
                    <span class="small">${metaLabel}</span>
                </div>
                <div class="position-relative rounded-3 border bg-dark bg-opacity-10 p-1 mb-3 overflow-hidden d-flex align-items-center justify-content-center" style="min-height: 250px; max-height: 480px;">
                    <img src="${docUrl}" alt="Pratinjau Dokumen" class="img-fluid rounded-2 shadow-xs" style="max-height: 460px; object-fit: contain; width: auto;" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'p-4 text-center\\'><i class=\\'fa-solid fa-file-pdf text-danger fs-1 mb-2\\'></i><div class=\\'fw-bold text-dark small mb-2\\'>Dokumen Berformat PDF / Non-Gambar</div><a href=\\'${docUrl}\\' target=\\'_blank\\' class=\\'btn btn-sm btn-primary rounded-pill px-3 py-1.5\\'><i class=\\'fa-solid fa-arrow-up-right-from-square me-1\\'></i> Buka Berkas PDF di Tab Baru</a></div>';">
                </div>
                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                    ${verifyActionBtnHtml}
                    <a href="${docUrl}" target="_blank" class="btn btn-primary fw-semibold px-3 py-2 rounded-pill shadow-xs d-inline-flex align-items-center gap-1.5">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> <span>Buka Tab Penuh</span>
                    </a>
                    <a href="${downloadUrl}" class="btn btn-outline-secondary fw-semibold px-3 py-2 rounded-pill shadow-xs d-inline-flex align-items-center gap-1.5">
                        <i class="fa-solid fa-download"></i> <span>Unduh</span>
                    </a>
                </div>
            `,
            width: '680px',
            showConfirmButton: false,
            showCloseButton: true,
            customClass: {
                popup: 'rounded-4 shadow-xl border-0 p-3 p-md-4'
            }
        });
    } else {
        window.open(docUrl, '_blank');
    }
}

function submitVerifyFromModal(userId, newStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= BASE_URL ?>/admin/users.php';
    
    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = '<?= csrf_token() ?>';
    form.appendChild(csrf);

    const act = document.createElement('input');
    act.type = 'hidden';
    act.name = 'action';
    act.value = 'toggle_ktp_verified';
    form.appendChild(act);

    const uid = document.createElement('input');
    uid.type = 'hidden';
    uid.name = 'user_id';
    uid.value = userId;
    form.appendChild(uid);

    const st = document.createElement('input');
    st.type = 'hidden';
    st.name = 'new_status';
    st.value = newStatus;
    form.appendChild(st);

    document.body.appendChild(form);
    form.submit();
}

function fillBlockReason(userId, val) {
    const el = document.getElementById('blockReasonInput_' + userId);
    if (el && val) {
        el.value = val;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


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

            if ($user_id === (int)$_SESSION['user_id']) {
                set_flash('danger', 'Anda tidak dapat menonaktifkan akun sendiri.');
            } else {
                $stmt = $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                set_flash('success', 'Status akun pengguna berhasil diperbarui.');
            }
            redirect('/admin/users.php');
        }

        // 1.b. Toggle Verifikasi KTP Mitra
        if ($action === 'toggle_ktp_verified') {
            $user_id = (int)$_POST['user_id'];
            $new_status = (int)$_POST['new_status'];
            $stmt = $db->prepare("UPDATE service_providers SET is_verified = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$new_status, $user_id]);
            $msg = ($new_status === 1) ? 'Status identitas KTP mitra berhasil diverifikasi resmi.' : 'Status verifikasi KTP mitra telah dinonaktifkan.';
            set_flash('success', $msg);
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
$where_sql = '';
$params = [];

if (!empty($role_filter)) {
    $where_sql = "WHERE r.name = ?";
    $params[] = $role_filter;
}

$stmt = $db->prepare("
    SELECT u.id, u.role_id, u.name, u.email, u.phone, u.is_active, u.created_at,
           r.name as role_name, r.display_name as role_display,
           p.district_id, p.address, p.gender, p.birth_date,
           d.name as district_name, sp.business_name, sp.id as provider_id,
           sp.is_verified, sp.id_card_number, sp.id_card_image, sp.credential_title, sp.certificate_url
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

$districts = get_all_districts();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-dark mb-1">Daftar & Edit Pengguna Platform</h1>
        <p class="text-muted small mb-0">Kelola akun administrator, masyarakat (pengguna), dan penyedia jasa lokal di Inhu</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/admin/profile.php" class="btn btn-sm btn-primary-custom">
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

<!-- Filter Role Tabs -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm <?= empty($role_filter) ? 'btn-primary' : 'btn-outline-secondary' ?>">Semua (<?= count($users) ?>)</a>
        <a href="<?= BASE_URL ?>/admin/users.php?role=pengguna" class="btn btn-sm <?= $role_filter === 'pengguna' ? 'btn-primary' : 'btn-outline-secondary' ?>">Masyarakat</a>
        <a href="<?= BASE_URL ?>/admin/users.php?role=penyedia" class="btn btn-sm <?= $role_filter === 'penyedia' ? 'btn-primary' : 'btn-outline-secondary' ?>">Penyedia Jasa</a>
        <a href="<?= BASE_URL ?>/admin/users.php?role=admin" class="btn btn-sm <?= $role_filter === 'admin' ? 'btn-primary' : 'btn-outline-secondary' ?>">Admin</a>
    </div>
</div>

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

<div class="card border shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" id="usersTable">
            <thead class="table-light">
                <tr>
                    <th style="width: 42px;" class="text-center">
                        <input type="checkbox" class="form-check-input" id="checkAllUsers" title="Pilih Semua di Halaman Ini" onchange="toggleSelectAll(this)">
                    </th>
                    <th>ID</th>
                    <th>Nama & Email</th>
                    <th>Peran</th>
                    <th>Nomor WhatsApp / HP</th>
                    <th>Kecamatan</th>
                    <th>Terdaftar</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr id="userRow_<?= $u['id'] ?>">
                        <td class="text-center">
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                <input type="checkbox" class="form-check-input user-row-check" value="<?= $u['id'] ?>" data-name="<?= e($u['name']) ?>" onchange="updateSelectedCount()">
                            <?php else: ?>
                                <span class="text-muted" title="Akun Admin Anda Sendiri (Terkunci)"><i class="fa-solid fa-lock text-muted" style="font-size: 0.8rem;"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-teal"><?= format_user_id($u['id']) ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($u['name']) ?></div>
                            <?php if ($u['business_name']): ?>
                                <div class="text-teal fw-semibold" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-store me-1"></i> <?= e($u['business_name']) ?>
                                </div>
                                <div class="mt-1 d-flex align-items-center gap-1.5 flex-wrap">
                                    <?php if (!empty($u['is_verified'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-0.5 px-1.5" style="font-size: 0.68rem;" title="NIK: <?= e($u['id_card_number'] ?: 'Belum ada NIK') ?>">
                                            <i class="fa-solid fa-circle-check me-0.5"></i> KTP Terverifikasi
                                        </span>
                                    <?php elseif (!empty($u['id_card_image'])): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0.5 px-1.5" style="font-size: 0.68rem;">
                                            <i class="fa-solid fa-clock me-0.5"></i> Menunggu Verifikasi KTP
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border py-0.5 px-1.5" style="font-size: 0.68rem;">
                                            Belum Unggah KTP
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($u['id_card_image']) && file_exists(__DIR__ . '/../' . $u['id_card_image'])): ?>
                                        <a href="<?= BASE_URL ?>/<?= e($u['id_card_image']) ?>" target="_blank" class="badge bg-info-subtle text-info-emphasis border border-info-subtle text-decoration-none py-0.5 px-1.5" style="font-size: 0.68rem;">
                                            <i class="fa-solid fa-id-card me-0.5"></i> Foto KTP ↗
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
                            <?php if ($u['is_active']): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <?php if ($u['role_name'] === 'penyedia'): ?>
                                    <!-- Tombol Verifikasi / Batal Verifikasi KTP Mitra -->
                                    <form method="POST" action="<?= BASE_URL ?>/admin/users.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_ktp_verified">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= empty($u['is_verified']) ? 1 : 0 ?>">
                                        <button type="submit" class="btn <?= empty($u['is_verified']) ? 'btn-outline-success' : 'btn-outline-secondary' ?> py-1 px-2" style="font-size: 0.75rem;" title="<?= empty($u['is_verified']) ? 'Setujui Verifikasi KTP Mitra' : 'Batalkan Status Verifikasi KTP' ?>">
                                            <i class="fa-solid <?= empty($u['is_verified']) ? 'fa-id-card' : 'fa-xmark' ?>"></i> <?= empty($u['is_verified']) ? 'Verif KTP' : 'Batal KTP' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Tombol Edit Akun -->
                                <button type="button" class="btn btn-outline-primary py-1 px-2" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['id'] ?>" style="font-size: 0.75rem;" title="Edit Data & Nomor HP">
                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                </button>

                                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/admin/users.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $u['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> py-1 px-2" style="font-size: 0.75rem;" title="<?= $u['is_active'] ? 'Blokir / Nonaktifkan Sementara' : 'Aktifkan Akun' ?>">
                                            <?= $u['is_active'] ? 'Blokir' : 'Aktifkan' ?>
                                        </button>
                                    </form>

                                    <!-- Tombol Hapus Akun -->
                                    <button type="button" class="btn btn-outline-danger py-1 px-2" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $u['id'] ?>" style="font-size: 0.75rem;" title="Hapus Akun Permanen">
                                        <i class="fa-solid fa-trash-can"></i> Hapus
                                    </button>
                                <?php endif; ?>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


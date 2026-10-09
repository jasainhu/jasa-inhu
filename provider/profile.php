<?php
/**
 * Profil Usaha Penyedia Jasa JASA INHU
 */

$page_title = 'Profil Usaha Jasa';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('penyedia');

$user = current_user();
$db = get_db();
$error = '';

$stmtProv = $db->prepare("SELECT * FROM service_providers WHERE user_id = ? LIMIT 1");
$stmtProv->execute([$user['id']]);
$provider = $stmtProv->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Sesi keamanan berakhir.';
    } else {
        $business_name = trim($_POST['business_name'] ?? '');
        $headline = trim($_POST['headline'] ?? '');
        $credential_title = trim($_POST['credential_title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $experience = (int)($_POST['experience_years'] ?? 1);
        $rate_min = (float)($_POST['hourly_rate_min'] ?? 0);
        $rate_max = (float)($_POST['hourly_rate_max'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
        $village_id = !empty($_POST['village_id']) ? (int)$_POST['village_id'] : null;
        $address = trim($_POST['address'] ?? '');
        $id_card_number = trim($_POST['id_card_number'] ?? ($provider['id_card_number'] ?? ''));

        $image_url = $provider['image_url'] ?? '';
        $certificate_url = $provider['certificate_url'] ?? '';
        $id_card_image = $provider['id_card_image'] ?? '';

        // Handle image upload jika ada file foto baru
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_exts)) {
                $error = 'Format file tidak didukung. Gunakan JPG, PNG, atau WebP.';
            } elseif ($file['size'] > 3 * 1024 * 1024) {
                $error = 'Ukuran file foto terlalu besar. Maksimal 3MB.';
            } else {
                $filename = 'provider_' . $provider['id'] . '_' . time() . '.' . $ext;
                $targetPath = __DIR__ . '/../uploads/providers/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    if (!empty($provider['image_url']) && file_exists(__DIR__ . '/../' . $provider['image_url'])) {
                        @unlink(__DIR__ . '/../' . $provider['image_url']);
                    }
                    $image_url = 'uploads/providers/' . $filename;
                }
            }
        }

        // Handle certificate/STR upload (Opsional khusus tenaga medis/homecare)
        if (empty($error) && isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
            $fileCert = $_FILES['certificate'];
            $allowed_cert_exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $extCert = strtolower(pathinfo($fileCert['name'], PATHINFO_EXTENSION));

            if (!in_array($extCert, $allowed_cert_exts)) {
                $error = 'Format sertifikat/STR tidak didukung. Gunakan JPG, PNG, WebP, atau PDF.';
            } elseif ($fileCert['size'] > 4 * 1024 * 1024) {
                $error = 'Ukuran berkas sertifikat/STR maksimal 4MB.';
            } else {
                $filenameCert = 'cert_' . $provider['id'] . '_' . time() . '.' . $extCert;
                $targetPathCert = __DIR__ . '/../uploads/certificates/' . $filenameCert;
                if (move_uploaded_file($fileCert['tmp_name'], $targetPathCert)) {
                    if (!empty($provider['certificate_url']) && file_exists(__DIR__ . '/../' . $provider['certificate_url'])) {
                        @unlink(__DIR__ . '/../' . $provider['certificate_url']);
                    }
                    $certificate_url = 'uploads/certificates/' . $filenameCert;
                }
            }
        }

        // Handle Foto KTP Upload (Untuk Verifikasi Identitas)
        if (empty($error) && isset($_FILES['id_card_image']) && $_FILES['id_card_image']['error'] === UPLOAD_ERR_OK) {
            $fileKtp = $_FILES['id_card_image'];
            $allowed_ktp_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $extKtp = strtolower(pathinfo($fileKtp['name'], PATHINFO_EXTENSION));

            if (!in_array($extKtp, $allowed_ktp_exts)) {
                $error = 'Format foto KTP tidak didukung. Gunakan file JPG, PNG, atau WebP.';
            } elseif ($fileKtp['size'] > 4 * 1024 * 1024) {
                $error = 'Ukuran berkas foto KTP maksimal 4MB.';
            } else {
                $filenameKtp = 'ktp_' . $provider['id'] . '_' . time() . '.' . $extKtp;
                $targetPathKtp = __DIR__ . '/../uploads/ktp/' . $filenameKtp;
                if (move_uploaded_file($fileKtp['tmp_name'], $targetPathKtp)) {
                    if (!empty($provider['id_card_image']) && file_exists(__DIR__ . '/../' . $provider['id_card_image'])) {
                        @unlink(__DIR__ . '/../' . $provider['id_card_image']);
                    }
                    $id_card_image = 'uploads/ktp/' . $filenameKtp;
                }
            }
        }

        if (empty($error)) {
            if (empty($business_name) || empty($category_id)) {
                $error = 'Nama usaha dan kategori utama wajib diisi.';
            } else {
                try {
                    $db->beginTransaction();
                    $stmtU = $db->prepare("UPDATE users SET phone = ?, updated_at = NOW() WHERE id = ?");
                    $stmtU->execute([$phone, $user['id']]);

                    $stmtP = $db->prepare("
                        UPDATE service_providers 
                        SET business_name = ?, image_url = ?, headline = ?, credential_title = ?, certificate_url = ?,
                            id_card_number = ?, id_card_image = ?,
                            description = ?, primary_category_id = ?,
                            experience_years = ?, hourly_rate_min = ?, hourly_rate_max = ?,
                            district_id = ?, village_id = ?, address = ?, updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $stmtP->execute([$business_name, $image_url, $headline, $credential_title, $certificate_url, $id_card_number, $id_card_image, $description, $category_id, $experience, $rate_min, $rate_max, $district_id, $village_id, $address, $user['id']]);

                    // Sinkronkan juga ke tabel profiles pengguna
                    $stmtProf = $db->prepare("UPDATE profiles SET district_id = ?, village_id = ?, address = ?, updated_at = NOW() WHERE user_id = ?");
                    $stmtProf->execute([$district_id, $village_id, $address, $user['id']]);

                    // Daftarkan ke basis wilayah operasional jika ada kecamatan
                    if ($district_id) {
                        $stmtArea = $db->prepare("INSERT IGNORE INTO service_areas (provider_id, district_id) VALUES (?, ?)");
                        $stmtArea->execute([$provider['id'], $district_id]);
                    }

                    $db->commit();

                    set_flash('success', 'Profil usaha jasa, dokumen KTP, dan informasi operasional Anda berhasil diperbarui!');
                    redirect('/provider/profile.php');
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Gagal menyimpan profil: ' . $e->getMessage();
                }
            }
        }
    }
}

$categories = get_active_categories();
$districts = get_all_districts();
$current_villages = [];
if (!empty($provider['district_id'])) {
    $stmtV = $db->prepare("SELECT id, name, postal_code FROM villages WHERE district_id = ? ORDER BY name ASC");
    $stmtV->execute([$provider['district_id']]);
    $current_villages = $stmtV->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4" style="max-width: 750px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Profil Usaha Jasa</h3>
            <p class="text-muted small mb-0">Informasi ini akan ditampilkan kepada calon pelanggan di Indragiri Hulu</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/provider_detail.php?id=<?= $provider['id'] ?>" target="_blank" class="btn btn-outline-teal btn-sm fw-semibold">
                <i class="fa-solid fa-eye me-1"></i> Lihat Tampilan Publik ↗
            </a>
            <a href="<?= BASE_URL ?>/provider/portfolio.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-camera me-1"></i> Portofolio
            </a>
            <a href="<?= BASE_URL ?>/provider/index.php" class="btn btn-outline-secondary btn-sm">
                &larr; Dashboard
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger mb-4"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card border shadow-sm p-4 bg-white" style="border-radius: 16px;">
        <form method="POST" action="<?= BASE_URL ?>/provider/profile.php" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <!-- Bagian Upload Foto Usaha / Tempat Kerja -->
            <div class="p-3 mb-3 rounded-4 border bg-light">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                    <label class="form-label fw-bold text-dark mb-0">
                        <i class="fa-solid fa-camera text-teal me-1"></i> Foto Profil / Hasil Kerja / Tempat Usaha
                    </label>
                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.72rem;">
                        <i class="fa-solid fa-circle-check me-1"></i> Tidak Wajib Punya Toko Fisik
                    </span>
                </div>
                <div class="text-muted small mb-3" style="font-size: 0.78rem;">
                    Boleh gunakan foto diri rapi Anda, hasil pekerjaan sebelumnya, atau foto tempat kerja (jika ada). Foto ini akan tampil di kartu pencarian jasa di Inhu.
                </div>
                
                <div class="d-flex flex-column flex-sm-row align-items-center gap-3">
                    <div id="imagePreviewContainer" class="position-relative rounded-3 overflow-hidden border shadow-xs" style="width: 140px; height: 100px; background: #e2e8f0; flex-shrink: 0;">
                        <?php if (!empty($provider['image_url']) && file_exists(__DIR__ . '/../' . $provider['image_url'])): ?>
                            <img id="imagePreview" src="<?= BASE_URL ?>/<?= e($provider['image_url']) ?>" alt="Foto Usaha" class="w-100 h-100 object-fit-cover">
                        <?php else: ?>
                            <div id="imagePlaceholder" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                                <i class="fa-solid fa-image fs-3 mb-1 opacity-50"></i>
                                <span style="font-size: 0.7rem;">Belum ada foto</span>
                            </div>
                            <img id="imagePreview" src="" alt="Preview Foto" class="w-100 h-100 object-fit-cover d-none">
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 w-100">
                        <input type="file" name="image" id="providerImageInput" class="form-control form-control-sm mb-1" accept="image/jpeg,image/png,image/webp" onchange="previewProviderPhoto(this)">
                        <div class="form-text small" style="font-size: 0.75rem;">
                            Format yang didukung: <strong>JPG, PNG, WebP</strong>. Maksimal ukuran 3MB. Disarankan foto horizontal/landscape.
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold">Nama Usaha / Merek Jasa Mandiri <span class="text-danger">*</span></label>
                    <input type="text" name="business_name" class="form-control" value="<?= e($provider['business_name'] ?? '') ?>" placeholder="Contoh: Pak Herman Tukang Bangunan / Bengkel Berkah" required>
                    <div class="form-text small" style="font-size: 0.72rem;">
                        Pekerja mandiri tanpa toko dapat menggunakan nama panggilan dan bidang keahlian Anda.
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Nomor WhatsApp Usaha <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Slogan / Headline Promosi Singkat</label>
                    <input type="text" name="headline" class="form-control" value="<?= e($provider['headline'] ?? '') ?>" placeholder="Contoh: Ahli Bangunan Rapih & Berpengalaman di Belilas">
                </div>

                <!-- Lokasi Usaha & Titik Operasional (Kab. Inhu) -->
                <div class="col-12">
                    <div class="p-3.5 rounded-3 bg-light border">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="fa-solid fa-location-dot text-danger fs-5"></i>
                            <h6 class="fw-bold text-dark mb-0">Lokasi & Alamat Operasional / Tempat Tinggal (Kab. Inhu)</h6>
                        </div>
                        <p class="text-muted small mb-3" style="font-size: 0.78rem;">
                            Nama <strong>Desa/Kelurahan</strong> dan <strong>Kecamatan</strong> akan tampil di katalog. Bagi pekerja mandiri tanpa toko fisik, cukup tuliskan patokan domisili/tempat tinggal Anda agar pelanggan mengetahui area layanan Anda.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Kecamatan (Kab. Inhu) <span class="text-danger">*</span></label>
                                <select name="district_id" id="district_select" class="form-select" required>
                                    <option value="">-- Pilih Kecamatan --</option>
                                    <?php foreach ($districts as $dist): ?>
                                        <option value="<?= $dist['id'] ?>" <?= (($provider['district_id'] ?? 0) == $dist['id']) ? 'selected' : '' ?>>
                                            Kec. <?= e($dist['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Desa / Kelurahan <span class="text-danger">*</span></label>
                                <select name="village_id" id="village_select" class="form-select" <?= empty($current_villages) ? 'disabled' : '' ?> required>
                                    <?php if (empty($current_villages)): ?>
                                        <option value="">-- Pilih Kecamatan Dahulu --</option>
                                    <?php else: ?>
                                        <option value="">-- Pilih Desa / Kelurahan --</option>
                                        <?php foreach ($current_villages as $vil): ?>
                                            <option value="<?= $vil['id'] ?>" <?= (($provider['village_id'] ?? 0) == $vil['id']) ? 'selected' : '' ?>>
                                                <?= e($vil['name']) ?> <?= !empty($vil['postal_code']) ? '(' . e($vil['postal_code']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-semibold">Alamat Lengkap / Patokan Tempat Tinggal atau Workshop</label>
                                <input type="text" name="address" class="form-control" placeholder="Contoh: Jl. Lintas Timur Simpang 4 Belilas (Dekat Masjid / Pasar)" value="<?= e($provider['address'] ?? '') ?>">
                                <div class="form-text small" style="font-size: 0.73rem;">
                                    Tuliskan nama jalan, nomor rumah, atau patokan tempat tinggal agar pelanggan mudah mengetahui lokasi Anda.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Kategori Utama Keahlian <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (($provider['primary_category_id'] ?? 0) == $cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Pengalaman Kerja (Tahun)</label>
                    <input type="number" name="experience_years" class="form-control" value="<?= (int)($provider['experience_years'] ?? 1) ?>" min="0">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Perkiraan Biaya Mulai Dari (Rp)</label>
                    <input type="number" name="hourly_rate_min" class="form-control" value="<?= (float)($provider['hourly_rate_min'] ?? 0) ?>" placeholder="Contoh: 35000">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Hingga Sekitar (Rp)</label>
                    <input type="number" name="hourly_rate_max" class="form-control" value="<?= (float)($provider['hourly_rate_max'] ?? 0) ?>" placeholder="Contoh: 150000">
                </div>

                <!-- Verifikasi Identitas KTP Mitra -->
                <div class="col-12">
                    <div class="p-3.5 rounded-3 border bg-light shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-id-card text-teal fs-5"></i>
                                <h6 class="fw-bold text-dark mb-0">Verifikasi Identitas KTP Mitra</h6>
                            </div>
                            <?php if (!empty($provider['is_verified'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-semibold">
                                    <i class="fa-solid fa-circle-check me-1"></i> Terverifikasi KTP Resmi
                                </span>
                            <?php elseif (!empty($provider['id_card_image'])): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1.5 rounded-pill fw-semibold">
                                    <i class="fa-solid fa-clock me-1"></i> KTP Terkirim &bull; Menunggu Validasi Admin
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border px-3 py-1.5 rounded-pill fw-semibold">
                                    <i class="fa-solid fa-circle-exclamation me-1"></i> Belum Verifikasi KTP
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small mb-3" style="font-size: 0.77rem; line-height: 1.6;">
                            Unggah foto KTP Anda agar mendapatkan lencana <strong><i class="fa-solid fa-circle-check text-success"></i> Terverifikasi KTP</strong>. Profil yang terverifikasi akan diprioritaskan tampil di pencarian dan 3x lebih dipercaya oleh warga Indragiri Hulu.
                        </p>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Nomor Induk Kependudukan (NIK KTP)</label>
                                <input type="text" name="id_card_number" class="form-control" maxlength="20" placeholder="16 digit NIK KTP Anda" value="<?= e($provider['id_card_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Unggah Foto KTP Asli (JPG, PNG, WebP)</label>
                                <input type="file" name="id_card_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <?php if (!empty($provider['id_card_image']) && file_exists(__DIR__ . '/../' . $provider['id_card_image'])): ?>
                                    <div class="mt-1.5 small d-flex align-items-center justify-content-between">
                                        <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i> Foto KTP tersimpan</span>
                                        <a href="<?= BASE_URL ?>/<?= e($provider['id_card_image']) ?>" target="_blank" class="fw-semibold text-teal text-decoration-none">
                                            <i class="fa-solid fa-eye me-1"></i> Lihat Foto KTP ↗
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-3 p-2.5 rounded-2 border bg-white small text-muted d-flex align-items-start gap-2" style="font-size: 0.74rem;">
                            <i class="fa-solid fa-shield-halved text-success mt-0.5 fs-6"></i>
                            <div>
                                <strong>Privasi Dijamin Aman:</strong> Foto KTP Anda hanya disimpan secara rahasia untuk validasi identitas internal Tim Pengelola <?= APP_NAME ?> guna mencegah penipuan. Foto KTP <strong>tidak akan pernah ditampilkan kepada publik atau pengguna lain</strong>.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Khusus Tenaga Medis / Homecare: Form STR Opsional (Collapsed by default agar tidak menakuti tukang umum) -->
                <div class="col-12">
                    <div class="border rounded-3 p-3 bg-light">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="fw-bold text-dark small">
                                    <i class="fa-solid fa-user-doctor text-teal me-1"></i> Khusus Tenaga Medis / Perawat Homecare (Opsional)
                                </div>
                                <div class="text-muted" style="font-size: 0.74rem;">
                                    Bagi tukang bangunan, montir, teknisi AC, ART, & pekerja umum, bagian ini <strong>TIDAK PERLU</strong> diisi.
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-teal rounded-pill px-3 py-1 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCredentials" aria-expanded="<?= !empty($provider['credential_title']) ? 'true' : 'false' ?>">
                                <i class="fa-solid fa-award me-1"></i> <?= !empty($provider['credential_title']) ? 'Ubah Dokumen STR' : '+ Punya STR / Ijazah Medis? (Opsional)' ?>
                            </button>
                        </div>

                        <div class="collapse <?= !empty($provider['credential_title']) ? 'show' : '' ?> mt-3 pt-3 border-top" id="collapseCredentials">
                            <div class="alert alert-info border-0 py-2 px-3 small mb-3" style="font-size: 0.76rem;">
                                <i class="fa-solid fa-circle-info me-1"></i> Cantumkan STR atau Ijazah Keperawatan/Kebidanan untuk menampilkan Lencana Keahlian Resmi bagi layanan infus dan perawatan luka homecare.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Gelar / Kualifikasi Medis</label>
                                    <input type="text" name="credential_title" class="form-control" placeholder="Contoh: STR Perawat Aktif / D3 Keperawatan" value="<?= e($provider['credential_title'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Foto / Scan STR atau Ijazah (JPG, PNG, PDF)</label>
                                    <input type="file" name="certificate" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                                    <?php if (!empty($provider['certificate_url']) && file_exists(__DIR__ . '/../' . $provider['certificate_url'])): ?>
                                        <div class="mt-1.5 small">
                                            <span class="text-success"><i class="fa-solid fa-check-circle me-1"></i> Dokumen tersimpan:</span>
                                            <a href="<?= BASE_URL ?>/<?= e($provider['certificate_url']) ?>" target="_blank" class="fw-semibold text-teal text-decoration-none">Lihat Dokumen ↗</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Deskripsi Layanan & Keunggulan Anda</label>
                    <textarea name="description" rows="4" class="form-control" placeholder="Jelaskan jenis pekerjaan yang bisa Anda tangani, garansi layanan, jangkauan servis, dll..."><?= e($provider['description'] ?? '') ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom w-100 py-2.5 mt-4 fw-bold">
                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan & Perbarui Profil Usaha
            </button>
        </form>
    </div>
</div>

<script>
function previewProviderPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('imagePlaceholder');
            if (preview) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            }
            if (placeholder) {
                placeholder.classList.add('d-none');
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

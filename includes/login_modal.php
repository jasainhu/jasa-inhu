<?php
/**
 * Global Login Modal - Tokopedia Style Popup
 * JASA INHU
 */
if (function_exists('is_logged_in') && is_logged_in()) {
    return; // Tidak perlu render jika sudah login
}
?>
<!-- Login Modal Tokopedia Style -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow-lg position-relative" style="border-radius: 20px; padding: 26px 30px;">
            <!-- Tombol Close (X) -->
            <button type="button" class="btn-close position-absolute top-0 end-0 m-3 shadow-none" data-bs-dismiss="modal" aria-label="Close" style="z-index: 10;"></button>

            <!-- Header Facebook Style: Logo & Judul Tengah -->
            <div class="text-center pt-2 pb-1 mb-3">
                <div class="d-inline-block position-relative mb-2">
                    <img src="<?= asset_url('images/icon-192.png') ?>" alt="JASA INHU" width="58" height="58" class="rounded-circle shadow-xs border p-1 bg-white">
                </div>
                <h4 class="fw-bold text-dark mb-1" id="loginModalLabel" style="letter-spacing: -0.3px;">Masuk ke JASA INHU</h4>
                <p class="text-muted small mb-0">Layanan Jasa & Tukang Kab. Indragiri Hulu</p>
            </div>

            <!-- Alert Error AJAX -->
            <div id="modalLoginAlert" class="alert alert-danger d-none py-2 px-3 small align-items-center mb-3 rounded-3" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <span id="modalLoginAlertText"></span>
            </div>

            <!-- Form Login -->
            <form id="modalLoginForm" action="<?= BASE_URL ?>/login.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="is_ajax" value="1">
                <input type="hidden" name="redirect" id="modal_login_redirect" value="">

                <!-- Floating Label 1: Email atau nomor ponsel -->
                <div class="form-floating mb-2.5 fb-floating-group">
                    <input type="text" name="login" id="modal_login_input" class="form-control rounded-3" placeholder="Email atau nomor ponsel" required autofocus>
                    <label for="modal_login_input" class="text-secondary">Email atau nomor ponsel</label>
                </div>

                <!-- Floating Label 2: Kata sandi dengan tombol mata -->
                <div class="form-floating mb-3 position-relative fb-floating-group">
                    <input type="password" name="password" id="modal_password_input" class="form-control rounded-3 pe-5" placeholder="Kata sandi" required>
                    <label for="modal_password_input" class="text-secondary">Kata sandi</label>
                    <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" onclick="toggleModalPassword()" style="font-size: 0.95rem; z-index: 5;" tabindex="-1" aria-label="Lihat kata sandi">
                        <i class="fa-solid fa-eye" id="modalEyeIcon"></i>
                    </button>
                </div>

                <!-- Tombol Masuk Utama (Primary) -->
                <button type="submit" id="btnModalLoginSubmit" class="btn w-100 text-white fw-bold shadow-sm py-2.5 rounded-pill" style="background: #0d9488; border: none; font-size: 1rem;">
                    <span id="btnModalLoginText">Masuk</span>
                    <span id="btnModalLoginSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                </button>
            </form>

            <!-- Lupa Kata Sandi (Centered Link gaya Facebook) -->
            <div class="text-center my-3">
                <a href="https://wa.me/6281234567890?text=Halo%20Admin%20JASA%20INHU,%20saya%20lupa%20kata%20sandi%20akun%20saya" target="_blank" class="small text-decoration-none text-dark fw-semibold" style="font-size: 0.88rem;">
                    Lupa kata sandi?
                </a>
            </div>

            <!-- Tombol Buat Akun Baru (Daftar) Gaya Facebook -->
            <div class="pt-1">
                <a href="<?= BASE_URL ?>/register.php" class="btn w-100 py-2 rounded-pill fw-bold text-decoration-none d-flex align-items-center justify-content-center" style="border: 1.5px solid #0d9488; color: #0d9488; font-size: 0.92rem; background: transparent; transition: all 0.2s ease;">
                    Buat akun baru
                </a>
            </div>

            <!-- Footer Branding (Gaya Meta) -->
            <div class="text-center mt-3 pt-1 text-muted d-flex align-items-center justify-content-center gap-1.5" style="font-size: 0.76rem;">
                <i class="fa-solid fa-shield-halved" style="color: #0d9488;"></i>
                <span>JASA INHU &bull; Terpercaya & Aman</span>
            </div>
        </div>
    </div>
</div>

<script>

function toggleModalPassword() {
    const passInput = document.getElementById('modal_password_input');
    const eyeIcon = document.getElementById('modalEyeIcon');
    if (passInput) {
        if (passInput.type === 'password') {
            passInput.type = 'text';
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            passInput.type = 'password';
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('modalLoginForm');
    if (!form) return;

    // Set redirect default ke halaman saat ini (selama bukan login atau register)
    const redirInput = document.getElementById('modal_login_redirect');
    if (redirInput) {
        const currentLoc = window.location.pathname + window.location.search;
        if (currentLoc && !currentLoc.includes('login.php') && !currentLoc.includes('register.php')) {
            redirInput.value = currentLoc;
        }
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const alertBox = document.getElementById('modalLoginAlert');
        const alertText = document.getElementById('modalLoginAlertText');
        const btnSubmit = document.getElementById('btnModalLoginSubmit');
        const btnText = document.getElementById('btnModalLoginText');
        const btnSpinner = document.getElementById('btnModalLoginSpinner');

        alertBox.classList.add('d-none');
        btnSubmit.disabled = true;
        btnSpinner.classList.remove('d-none');
        btnText.textContent = 'Memverifikasi...';

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                btnText.textContent = 'Berhasil! Mengalihkan...';
                window.location.href = data.redirect || '<?= BASE_URL ?>/';
            } else {
                alertText.textContent = data.message || 'Login gagal. Silakan periksa kembali data Anda.';
                alertBox.classList.remove('d-none');
                btnSubmit.disabled = false;
                btnSpinner.classList.add('d-none');
                btnText.textContent = 'Masuk Sekarang';
            }
        })
        .catch(err => {
            // Fallback: standard submit jika JSON error
            form.submit();
        });
    });
});
</script>

<style>
.fb-floating-group.form-floating > .form-control {
    height: 54px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    padding-top: 1.5rem;
    padding-bottom: 0.5rem;
    font-size: 0.95rem;
    color: #1e293b;
    background-color: #ffffff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.fb-floating-group.form-floating > .form-control:focus {
    border-color: #0d9488;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
}
.fb-floating-group.form-floating > label {
    padding: 0.95rem 1rem;
    color: #64748b;
    font-size: 0.92rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.fb-floating-group.form-floating > .form-control:focus ~ label {
    color: #0d9488;
    font-weight: 500;
}
</style>

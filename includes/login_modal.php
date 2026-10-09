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

            <!-- Header Tokopedia: Masuk di Kiri, Daftar di Kanan -->
            <div class="d-flex align-items-center justify-content-between pt-1 pb-1 mb-3 pe-4">
                <h3 class="fw-bold mb-0 text-dark" id="loginModalLabel" style="font-size: 1.85rem; letter-spacing: -0.5px;">Masuk</h3>
                <a href="<?= BASE_URL ?>/register.php" class="fw-bold text-decoration-none" style="font-size: 0.95rem; color: #00AA5B;">
                    Daftar
                </a>
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

                <div class="mb-3">
                    <label for="modal_login_input" class="form-label fw-semibold small text-muted mb-1">Nomor HP atau Email</label>
                    <input type="text" name="login" id="modal_login_input" class="form-control py-2 px-3 rounded-3 border" placeholder="nama@email.com atau 0812..." required autofocus>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="modal_password_input" class="form-label fw-semibold small text-muted mb-0">Kata Sandi</label>
                        <a href="https://wa.me/6281234567890?text=Halo%20Admin%20JASA%20INHU,%20saya%20lupa%20kata%20sandi%20akun%20saya" target="_blank" class="small text-decoration-none fw-semibold" style="color: #00AA5B;">
                            Lupa kata sandi?
                        </a>
                    </div>
                    <div class="position-relative">
                        <input type="password" name="password" id="modal_password_input" class="form-control py-2 px-3 rounded-3 border pe-5" placeholder="Masukkan kata sandi..." required>
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-muted text-decoration-none pe-3" onclick="toggleModalPassword()" style="font-size: 0.9rem;" tabindex="-1">
                            <i class="fa-solid fa-eye" id="modalEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" id="btnModalLoginSubmit" class="btn w-100 text-white fw-bold shadow-sm" style="background: #00AA5B; border: none; border-radius: 10px; padding: 11px 16px; font-size: 0.95rem;">
                    <span id="btnModalLoginText">Masuk</span>
                    <span id="btnModalLoginSpinner" class="spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
                </button>
            </form>

            <!-- Bantuan Layanan (Tokopedia Care Style) -->
            <div class="text-center mt-3 pt-2 text-muted" style="font-size: 0.82rem;">
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

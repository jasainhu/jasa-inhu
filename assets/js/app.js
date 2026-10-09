/**
 * JASA INHU - JavaScript Application Helpers
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Dynamic District -> Village Loader
    const districtSelect = document.getElementById('district_select');
    const villageSelect = document.getElementById('village_select');

    if (districtSelect && villageSelect) {
        districtSelect.addEventListener('change', async function() {
            const districtId = this.value;
            villageSelect.innerHTML = '<option value="">Memuat desa / kelurahan...</option>';
            villageSelect.disabled = true;

            if (!districtId) {
                villageSelect.innerHTML = '<option value="">-- Pilih Kecamatan Terlebih Dahulu --</option>';
                villageSelect.disabled = true;
                return;
            }

            try {
                // Gunakan URL absolut atau relatif
                const baseUrl = window.APP_BASE_URL || '';
                const response = await fetch(`${baseUrl}/api/villages.php?district_id=${districtId}`);
                const result = await response.json();

                if (result.status === 'success' && Array.isArray(result.data)) {
                    if (result.data.length === 0) {
                        villageSelect.innerHTML = '<option value="">-- Tidak ada data desa --</option>';
                    } else {
                        let options = '<option value="">-- Pilih Desa / Kelurahan --</option>';
                        result.data.forEach(village => {
                            options += `<option value="${village.id}">${village.name} ${village.postal_code ? `(${village.postal_code})` : ''}</option>`;
                        });
                        villageSelect.innerHTML = options;
                        villageSelect.disabled = false;
                    }
                } else {
                    villageSelect.innerHTML = '<option value="">-- Gagal memuat data --</option>';
                }
            } catch (err) {
                console.error('Error fetching villages:', err);
                villageSelect.innerHTML = '<option value="">-- Gagal menghubungi server --</option>';
            }
        });
    }

    // 2. Role Selector di Register Form
    const rolePillBtns = document.querySelectorAll('.role-pill-btn');
    const roleInput = document.getElementById('role_type_input');
    const providerFields = document.getElementById('provider_fields');

    if (rolePillBtns.length > 0 && roleInput) {
        rolePillBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                rolePillBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const selectedRole = this.getAttribute('data-role');
                roleInput.value = selectedRole;

                if (providerFields) {
                    if (selectedRole === 'penyedia') {
                        providerFields.style.display = 'block';
                        const reqInputs = providerFields.querySelectorAll('[data-required]');
                        reqInputs.forEach(i => i.setAttribute('required', 'required'));
                    } else {
                        providerFields.style.display = 'none';
                        const reqInputs = providerFields.querySelectorAll('[data-required]');
                        reqInputs.forEach(i => i.removeAttribute('required'));
                    }
                }
            });
        });
    }

    // 3. Demo Account Auto-Fill Helper (pada login.php)
    window.fillDemoAccount = function(email, password) {
        const emailInput = document.getElementById('login_input');
        const passInput = document.getElementById('password_input');
        if (emailInput && passInput) {
            emailInput.value = email;
            passInput.value = password;
        }
    };

    // 4. Auto dismiss flash alerts setelah 6 detik
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 6000);
    });

    // 5. Global Modern Pop-up & Confirmation System (SweetAlert2)
    // Convert native confirm() and custom data-confirm forms to modern animated modals
    initModernPopups();
});

/**
 * Inisialisasi Popup Modern SweetAlert2 & Auto-Interseptor
 */
function initModernPopups() {
    if (typeof Swal === 'undefined') return;

    // Helper Global untuk Alert Modern
    window.appAlert = function(text, title = 'Pemberitahuan', icon = 'info') {
        let options = {
            title: title,
            html: text,
            icon: icon,
            confirmButtonText: '<i class="fa-solid fa-check me-1"></i> Mengerti',
            confirmButtonColor: '#0d9488'
        };
        if (typeof text === 'object') {
            options = Object.assign(options, text);
        }
        return Swal.fire(options);
    };

    // Helper Global untuk Konfirmasi Modern
    window.appConfirm = function(options = {}) {
        const defaults = {
            title: 'Konfirmasi Tindakan',
            text: 'Apakah Anda yakin ingin melanjutkan?',
            icon: 'warning',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0d9488',
            confirmButtonClass: '',
            reverseButtons: true
        };

        const config = Object.assign({}, defaults, options);
        const isDanger = config.confirmButtonClass.includes('danger') || 
                         config.icon === 'warning' || 
                         config.icon === 'error' ||
                         config.type === 'danger';

        return Swal.fire({
            title: config.title,
            html: config.text,
            icon: config.icon || (isDanger ? 'warning' : 'question'),
            showCancelButton: true,
            confirmButtonText: config.confirmButtonText,
            cancelButtonText: config.cancelButtonText,
            confirmButtonColor: isDanger ? '#ef4444' : '#0d9488',
            cancelButtonColor: '#94a3b8',
            reverseButtons: config.reverseButtons,
            customClass: {
                confirmButton: isDanger ? 'swal2-btn-danger' : ''
            }
        }).then(result => result.isConfirmed);
    };

    // Override window.alert native agar selalu tampil modern
    window.alert = function(msg) {
        window.appAlert(msg);
    };

    // Auto-intercept semua form dengan onsubmit="return confirm(...)" atau data-confirm
    document.querySelectorAll('form').forEach(form => {
        const onsubmitAttr = form.getAttribute('onsubmit');
        const hasDataConfirm = form.hasAttribute('data-confirm');

        // Jika form memiliki onsubmit native confirm('...')
        if (onsubmitAttr && onsubmitAttr.includes('confirm(')) {
            // Ambil teks pesan di dalam confirm('...')
            const match = onsubmitAttr.match(/confirm\(\s*['"](.*?)['"]\s*\)/i);
            const msg = match ? match[1] : 'Apakah Anda yakin ingin melanjutkan?';
            
            // Hapus onsubmit native dan gantikan dengan data attribute
            form.removeAttribute('onsubmit');
            form.setAttribute('data-confirm', msg);
            
            // Tebak judul dan tipe berdasarkan isi pesan
            const lowerMsg = msg.toLowerCase();
            if (lowerMsg.includes('batal')) {
                form.setAttribute('data-confirm-title', 'Batalkan Pesanan?');
                form.setAttribute('data-confirm-btn', 'Ya, Batalkan');
                form.setAttribute('data-confirm-type', 'danger');
            } else if (lowerMsg.includes('hapus')) {
                form.setAttribute('data-confirm-title', 'Hapus Data?');
                form.setAttribute('data-confirm-btn', 'Ya, Hapus');
                form.setAttribute('data-confirm-type', 'danger');
            } else if (lowerMsg.includes('tolak')) {
                form.setAttribute('data-confirm-title', 'Tolak Permintaan?');
                form.setAttribute('data-confirm-btn', 'Ya, Tolak');
                form.setAttribute('data-confirm-type', 'danger');
            } else if (lowerMsg.includes('terima') || lowerMsg.includes('setujui')) {
                form.setAttribute('data-confirm-title', 'Konfirmasi Penerimaan');
                form.setAttribute('data-confirm-btn', 'Ya, Lanjutkan');
                form.setAttribute('data-confirm-type', 'success');
            }
        }
    });

    // Event Listener Delegasi Form Submission untuk Pop-up Modern
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || !form.hasAttribute('data-confirm')) return;

        // Cegah submit jika belum dikonfirmasi lewat modal
        if (form.getAttribute('data-confirmed') === 'true') {
            form.removeAttribute('data-confirmed');
            return; // Lanjut submit asli
        }

        e.preventDefault();

        const text = form.getAttribute('data-confirm') || 'Apakah Anda yakin?';
        const title = form.getAttribute('data-confirm-title') || 'Konfirmasi Tindakan';
        const confirmBtn = form.getAttribute('data-confirm-btn') || 'Ya, Lanjutkan';
        const cancelBtn = form.getAttribute('data-confirm-cancel') || 'Batal';
        const type = form.getAttribute('data-confirm-type') || 'warning';

        const isDanger = type === 'danger' || title.toLowerCase().includes('batal') || title.toLowerCase().includes('hapus');

        Swal.fire({
            title: title,
            html: text,
            icon: type === 'danger' ? 'warning' : type,
            showCancelButton: true,
            confirmButtonText: confirmBtn,
            cancelButtonText: cancelBtn,
            confirmButtonColor: isDanger ? '#ef4444' : '#0d9488',
            cancelButtonColor: '#94a3b8',
            reverseButtons: true,
            customClass: {
                confirmButton: isDanger ? 'swal2-btn-danger' : ''
            }
        }).then(result => {
            if (result.isConfirmed) {
                form.setAttribute('data-confirmed', 'true');
                form.submit();
            }
        });
    }, true);
}

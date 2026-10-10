/**
 * JASA INHU - Universal Sound & Push Notification Engine
 * Mendukung 3 Peran: Mitra (Penyedia Jasa), Pengguna (Pelanggan), dan Admin
 */

const AppNotification = (function() {
    let audioCtx = null;
    let lastSeenOrderId = 0;
    let pollInterval = null;
    let isSoundMuted = localStorage.getItem('jasa_inhu_sound_enabled') === '0';

    // Inisialisasi AudioContext dengan aman
    function getAudioContext() {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioCtx = new AudioContextClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    // Mainkan nada tunggal sintetis
    function playTone(freq, startTime, duration, type = 'sine', gainLevel = 0.15) {
        const ctx = getAudioContext();
        if (!ctx) return;

        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(freq, ctx.currentTime + startTime);

        gain.gain.setValueAtTime(gainLevel, ctx.currentTime + startTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + startTime + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(ctx.currentTime + startTime);
        osc.stop(ctx.currentTime + startTime + duration);
    }

    // 1. Melodi Nada Dering Pesanan Baru Masuk (Khusus Mitra - Energetik Ala Gojek/Grab)
    function playOrderIncomingMelody() {
        if (isSoundMuted) return;

        try {
            const notes = [
                { f: 659.25, t: 0.00, d: 0.14 },
                { f: 783.99, t: 0.15, d: 0.14 },
                { f: 987.77, t: 0.30, d: 0.14 },
                { f: 1318.51, t: 0.45, d: 0.35 },
                { f: 659.25, t: 0.75, d: 0.14 },
                { f: 783.99, t: 0.90, d: 0.14 },
                { f: 987.77, t: 1.05, d: 0.14 },
                { f: 1318.51, t: 1.20, d: 0.45 }
            ];

            notes.forEach(n => {
                playTone(n.f, n.t, n.d, 'triangle', 0.22);
            });

            if (navigator.vibrate) {
                navigator.vibrate([300, 150, 300, 150, 450]);
            }
        } catch (e) {
            console.warn('Audio failed:', e);
        }
    }

    // 2. Melodi Denting Ramah Pesanan & Chat (Khusus Pelanggan / Pengguna)
    function playCustomerChime() {
        if (isSoundMuted) return;

        try {
            // Nada C5 (523Hz) -> G5 (784Hz) -> C6 (1046Hz) halus & elegan
            const notes = [
                { f: 523.25, t: 0.00, d: 0.18 },
                { f: 783.99, t: 0.12, d: 0.22 },
                { f: 1046.50, t: 0.26, d: 0.38 }
            ];

            notes.forEach(n => {
                playTone(n.f, n.t, n.d, 'sine', 0.20);
            });

            if (navigator.vibrate) {
                navigator.vibrate([200, 100, 200]);
            }
        } catch (e) {
            console.warn('Audio failed:', e);
        }
    }

    // 3. Melodi Alert Operasional & Topup (Khusus Admin)
    function playAdminCashChime() {
        if (isSoundMuted) return;

        try {
            // Dua nada bel ding-dong tajam ala register kasir & notif sistem
            const notes = [
                { f: 987.77, t: 0.00, d: 0.12 },
                { f: 1318.51, t: 0.10, d: 0.30 }
            ];

            notes.forEach(n => {
                playTone(n.f, n.t, n.d, 'triangle', 0.24);
            });

            if (navigator.vibrate) {
                navigator.vibrate([250, 100, 250]);
            }
        } catch (e) {
            console.warn('Audio failed:', e);
        }
    }

    // Uji coba suara notifikasi secara manual sesuai peran
    function testSound(role = 'provider') {
        getAudioContext();

        let title = '🔊 Nada Dering Berfungsi!';
        let message = 'Suara notifikasi di perangkat Anda aktif dan siap digunakan.';

        if (role === 'admin') {
            playAdminCashChime();
            title = '🔊 Nada Dering Admin Berfungsi!';
            message = 'Suara alert operasional aktif saat ada mitra baru mendaftar atau pengajuan top-up saldo deposit masuk.';
        } else if (role === 'customer') {
            playCustomerChime();
            title = '🔊 Nada Dering Pelanggan Berfungsi!';
            message = 'Suara notifikasi aktif saat mitra menerima orderan Anda, mulai OTW ke rumah, atau mengirim chat.';
        } else {
            playOrderIncomingMelody();
            title = '🔊 Nada Dering Mitra Berfungsi!';
            message = 'Suara notifikasi aktif dan siap membunyikan orderan masuk dari warga Inhu!';
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: title,
                text: message,
                confirmButtonColor: '#0d9488',
                confirmButtonText: 'Sip, Mantap!'
            });
        } else {
            alert(title + ' ' + message);
        }
    }

    // Toggle mute/unmute suara
    function toggleSound(role = 'provider') {
        isSoundMuted = !isSoundMuted;
        localStorage.setItem('jasa_inhu_sound_enabled', isSoundMuted ? '0' : '1');
        updateSoundUI();

        if (!isSoundMuted) {
            testSound(role);
        }
    }

    // Perbarui status tampilan tombol di halaman
    function updateSoundUI() {
        const soundToggles = document.querySelectorAll('.sound-toggle-btn');
        soundToggles.forEach(btn => {
            if (isSoundMuted) {
                btn.innerHTML = '<i class="fa-solid fa-volume-xmark me-1 text-danger"></i> Suara: Bisu (Muted)';
                btn.classList.remove('btn-teal', 'btn-light', 'text-teal');
                btn.classList.add('btn-outline-danger');
            } else {
                btn.innerHTML = '<i class="fa-solid fa-volume-high me-1 text-success"></i> Suara: Aktif (On)';
                btn.classList.remove('btn-outline-danger');
                btn.classList.add('btn-light', 'text-teal');
            }
        });
    }

    // Izin Notifikasi Pop-up HP (Browser Push Notification)
    function requestBrowserNotification(role = 'provider') {
        if (!('Notification' in window)) {
            alert('Browser perangkat Anda tidak mendukung Web Notification.');
            return;
        }

        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                new Notification('JASA INHU - Notifikasi Aktif!', {
                    body: 'Notifikasi sistem Jasa Inhu kini akan langsung muncul di layar HP Anda.',
                    icon: '/assets/images/logo.png'
                });
                testSound(role);
            } else {
                alert('Izin notifikasi belum diaktifkan. Anda bisa mengaktifkannya melalui Izin Situs di browser.');
            }
        });
    }

    // Inisialisasi Polling Real-time untuk Mitra
    function startOrderPolling(initialLatestId = 0) {
        lastSeenOrderId = initialLatestId;

        const unlockAudio = () => {
            getAudioContext();
            document.removeEventListener('click', unlockAudio);
            document.removeEventListener('touchstart', unlockAudio);
        };
        document.addEventListener('click', unlockAudio, { once: true });
        document.addEventListener('touchstart', unlockAudio, { once: true });

        updateSoundUI();

        if (pollInterval) clearInterval(pollInterval);

        pollInterval = setInterval(() => {
            const baseUrl = window.APP_BASE_URL || window.BASE_URL || '';
            fetch(baseUrl + '/api/provider_check_orders.php', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.is_provider) {
                    if (data.latest_order && data.latest_order.id > lastSeenOrderId) {
                        const isFirstLoad = (lastSeenOrderId === 0);
                        lastSeenOrderId = data.latest_order.id;

                        if (!isFirstLoad) {
                            playOrderIncomingMelody();

                            if ('Notification' in window && Notification.permission === 'granted') {
                                new Notification('🔔 Ada Pesanan Jasa Baru!', {
                                    body: `Dari: ${data.latest_order.customer_name} - "${data.latest_order.title}"`,
                                    icon: '/assets/images/logo.png'
                                });
                            }

                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: '🔔 PESANAN BARU MASUK!',
                                    html: `<strong>Pelanggan:</strong> ${data.latest_order.customer_name}<br>` +
                                          `<strong>Permintaan:</strong> ${data.latest_order.title}<br>` +
                                          `<span class="badge bg-success mt-2">Segera Tanggapi Pesanan</span>`,
                                    icon: 'info',
                                    showCancelButton: true,
                                    confirmButtonColor: '#0d9488',
                                    cancelButtonColor: '#6c757d',
                                    confirmButtonText: 'Lihat & Terima Pesanan',
                                    cancelButtonText: 'Tutup'
                                }).then(result => {
                                    if (result.isConfirmed) {
                                        window.location.reload();
                                    }
                                });
                            }
                        }
                    }
                }
            })
            .catch(() => {});
        }, 7000);
    }

    return {
        testSound,
        toggleSound,
        requestBrowserNotification,
        startOrderPolling,
        playOrderIncomingMelody,
        playCustomerChime,
        playAdminCashChime
    };
})();

// Alias untuk kompatibilitas
window.ProviderNotification = AppNotification;
window.AppNotification = AppNotification;

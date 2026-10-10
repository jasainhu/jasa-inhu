/**
 * JASA INHU - Provider Order Sound & Push Notification Engine
 * Sintesis Audio Web & Notifikasi HP Real-time
 */

const ProviderNotification = (function() {
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

    // Mainkan nada tunggal
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

    // Melodi Nada Dering Pesanan Baru Masuk (Ala Gojek / Grab Mitra)
    function playOrderIncomingMelody() {
        if (isSoundMuted) return;

        try {
            // Melodi 2x siklus: Nada E5 (659Hz) -> G5 (784Hz) -> B5 (987Hz) -> E6 (1318Hz)
            const notes = [
                { f: 659.25, t: 0.00, d: 0.14 },
                { f: 783.99, t: 0.15, d: 0.14 },
                { f: 987.77, t: 0.30, d: 0.14 },
                { f: 1318.51, t: 0.45, d: 0.35 },

                // Pengulangan nada jeda 0.7 detik
                { f: 659.25, t: 0.75, d: 0.14 },
                { f: 783.99, t: 0.90, d: 0.14 },
                { f: 987.77, t: 1.05, d: 0.14 },
                { f: 1318.51, t: 1.20, d: 0.45 }
            ];

            notes.forEach(n => {
                playTone(n.f, n.t, n.d, 'triangle', 0.22);
            });

            // Getarkan HP jika didukung browser
            if (navigator.vibrate) {
                navigator.vibrate([300, 150, 300, 150, 450]);
            }
        } catch (e) {
            console.warn('Gagal memutar audio:', e);
        }
    }

    // Uji coba suara notifikasi secara manual
    function testSound() {
        // Buka kunci AudioContext dengan interaksi pengguna
        getAudioContext();
        playOrderIncomingMelody();

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: '🔊 Nada Dering Berfungsi!',
                text: 'Suara notifikasi pesanan di perangkat HP Anda aktif dan siap menerima orderan baru.',
                confirmButtonColor: '#0d9488',
                confirmButtonText: 'Bagus, Saya Siap!'
            });
        } else {
            alert('🔊 Nada dering berhasil dibunyikan!');
        }
    }

    // Toggle mute/unmute suara
    function toggleSound() {
        isSoundMuted = !isSoundMuted;
        localStorage.setItem('jasa_inhu_sound_enabled', isSoundMuted ? '0' : '1');
        updateSoundUI();

        if (!isSoundMuted) {
            testSound();
        }
    }

    // Perbarui status tampilan tombol di halaman
    function updateSoundUI() {
        const soundToggles = document.querySelectorAll('.sound-toggle-btn');
        soundToggles.forEach(btn => {
            if (isSoundMuted) {
                btn.innerHTML = '<i class="fa-solid fa-volume-xmark me-1 text-danger"></i> Suara: Bisu';
                btn.classList.remove('btn-light', 'text-teal');
                btn.classList.add('btn-outline-danger');
            } else {
                btn.innerHTML = '<i class="fa-solid fa-volume-high me-1 text-success"></i> Suara: Aktif';
                btn.classList.remove('btn-outline-danger');
                btn.classList.add('btn-light', 'text-teal');
            }
        });
    }

    // Izin Notifikasi Pop-up HP (Browser Push Notification)
    function requestBrowserNotification() {
        if (!('Notification' in window)) {
            alert('Browser HP Anda tidak mendukung Web Notification.');
            return;
        }

        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                new Notification('JASA INHU - Notifikasi Aktif!', {
                    body: 'Notifikasi pesanan jasa baru kini akan langsung berdering di layar HP Anda.',
                    icon: '/assets/images/logo.png'
                });
                testSound();
            } else {
                alert('Izin notifikasi belum diaktifkan. Anda bisa mengaktifkannya melalui Izin Situs di browser.');
            }
        });
    }

    // Inisialisasi Polling Real-time
    function startOrderPolling(initialLatestId = 0) {
        lastSeenOrderId = initialLatestId;

        // Buka kunci AudioContext saat ada sentuhan pertama di layar
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

                        // Jika bukan saat baru buka halaman pertama kali dan ada pesanan baru
                        if (!isFirstLoad) {
                            playOrderIncomingMelody();

                            // Tampilkan Notifikasi Pop-up Layar HP jika diizinkan
                            if ('Notification' in window && Notification.permission === 'granted') {
                                new Notification('🔔 Ada Pesanan Jasa Baru!', {
                                    body: `Dari: ${data.latest_order.customer_name} - "${data.latest_order.title}"`,
                                    icon: '/assets/images/logo.png'
                                });
                            }

                            // Tampilkan Peringatan Layar
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
        }, 7000); // Cek setiap 7 detik
    }

    return {
        testSound,
        toggleSound,
        requestBrowserNotification,
        startOrderPolling,
        playOrderIncomingMelody
    };
})();

window.ProviderNotification = ProviderNotification;

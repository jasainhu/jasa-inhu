/**
 * JASA INHU - Client-Side Image Compressor & Helper
 * Mengompres foto resolusi tinggi kamera smartphone (5MB-15MB) menjadi < 600KB
 * secara instan di browser sebelum diunggah ke server PHP.
 * Mencegah error "upload_max_filesize", "post_max_size", dan "Sesi keamanan berakhir".
 */

(function(window) {
    'use strict';

    /**
     * Kompres objek File gambar menjadi File baru yang lebih ringan
     * @param {File} file File gambar asli
     * @param {Object} options { maxWidth: 1600, maxHeight: 1600, quality: 0.82 }
     * @returns {Promise<File>}
     */
    async function compressImageFile(file, options = {}) {
        if (!file || !(file instanceof File)) {
            return file;
        }

        // Hanya kompres berkas tipe gambar raster standar
        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp', 'image/jpg'];
        if (!validTypes.includes(file.type.toLowerCase()) && !file.type.startsWith('image/')) {
            return file;
        }

        // Abaikan SVG atau GIF animasi
        if (file.type === 'image/svg+xml' || file.type === 'image/gif') {
            return file;
        }

        const maxWidth = options.maxWidth || 1600;
        const maxHeight = options.maxHeight || 1600;
        const quality = options.quality !== undefined ? options.quality : 0.82;

        // Jika ukuran file sudah sangat kecil (< 350KB), tidak perlu dikompresi lagi
        if (file.size < 350 * 1024) {
            return file;
        }

        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    try {
                        let width = img.width;
                        let height = img.height;

                        if (width > height) {
                            if (width > maxWidth) {
                                height = Math.round((height * maxWidth) / width);
                                width = maxWidth;
                            }
                        } else {
                            if (height > maxHeight) {
                                width = Math.round((width * maxHeight) / height);
                                height = maxHeight;
                            }
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);

                        canvas.toBlob((blob) => {
                            if (!blob || blob.size >= file.size) {
                                // Jika kompresi gagal atau ukuran malah naik, pakai berkas asli
                                resolve(file);
                                return;
                            }

                            // Pastikan nama berkas berekstensi .jpg
                            const baseName = file.name.replace(/\.[^.]+$/, '');
                            const compressedFileName = baseName + '.jpg';

                            const compressedFile = new File([blob], compressedFileName, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });

                            resolve(compressedFile);
                        }, 'image/jpeg', quality);
                    } catch (err) {
                        console.warn('Gagal kompresi canvas:', err);
                        resolve(file);
                    }
                };

                img.onerror = function() {
                    resolve(file);
                };

                img.src = e.target.result;
            };

            reader.onerror = function() {
                resolve(file);
            };

            reader.readAsDataURL(file);
        });
    }

    /**
     * Memasang kompresor otomatis pada input file
     * @param {HTMLInputElement} inputEl Elemen input file
     * @param {Object} opts { previewEl, statusEl, maxWidth, maxHeight, quality, onComplete }
     */
    function attachAutoCompressor(inputEl, opts = {}) {
        if (!inputEl) return;

        inputEl.addEventListener('change', async function() {
            const file = this.files && this.files[0];
            if (!file) return;

            // Jangan proses ulang jika sudah terkompresi
            if (inputEl.dataset.isCompressed === 'true' && inputEl.dataset.compressedName === file.name) {
                return;
            }

            const origSizeKB = Math.round(file.size / 1024);
            const statusEl = typeof opts.statusEl === 'string' ? document.getElementById(opts.statusEl) : opts.statusEl;
            const previewEl = typeof opts.previewEl === 'string' ? document.getElementById(opts.previewEl) : opts.previewEl;
            const previewContainer = typeof opts.previewContainer === 'string' ? document.getElementById(opts.previewContainer) : opts.previewContainer;

            if (statusEl) {
                statusEl.innerHTML = `<span class="text-primary"><i class="fa-solid fa-spinner fa-spin me-1"></i> Mengompresi foto (${origSizeKB > 1024 ? (origSizeKB/1024).toFixed(1) + ' MB' : origSizeKB + ' KB'})...</span>`;
            }

            try {
                const compressed = await compressImageFile(file, {
                    maxWidth: opts.maxWidth || 1600,
                    maxHeight: opts.maxHeight || 1600,
                    quality: opts.quality !== undefined ? opts.quality : 0.82
                });

                // Update input file menggunakan DataTransfer API
                if (window.DataTransfer) {
                    const dt = new DataTransfer();
                    dt.items.add(compressed);
                    inputEl.files = dt.files;
                }

                inputEl.dataset.isCompressed = 'true';
                inputEl.dataset.compressedName = compressed.name;

                const newSizeKB = Math.round(compressed.size / 1024);

                // Tampilkan preview
                if (previewEl) {
                    const previewUrl = URL.createObjectURL(compressed);
                    previewEl.src = previewUrl;
                    previewEl.classList.remove('d-none');
                    if (previewContainer) {
                        previewContainer.classList.remove('d-none');
                    }
                }

                if (statusEl) {
                    if (compressed.size < file.size) {
                        statusEl.innerHTML = `<span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 mt-1"><i class="fa-solid fa-check-circle me-1"></i> Foto dioptimasi: <strong>${newSizeKB} KB</strong> (hemat ${Math.round((1 - compressed.size/file.size)*100)}%). Siap disimpan!</span>`;
                    } else {
                        statusEl.innerHTML = `<span class="badge bg-info-subtle text-info border border-info-subtle py-1 px-2 mt-1"><i class="fa-solid fa-check me-1"></i> Foto siap diunggah (${newSizeKB} KB)</span>`;
                    }
                }

                if (typeof opts.onComplete === 'function') {
                    opts.onComplete(compressed);
                }
            } catch (err) {
                console.error('Error saat kompresi file:', err);
                if (statusEl) {
                    statusEl.innerHTML = `<span class="text-muted small">File siap diunggah.</span>`;
                }
            }
        });
    }

    // Auto-attach untuk semua input gambar yang belum dikonfigurasi secara manual
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[type="file"]').forEach(input => {
            const accept = (input.getAttribute('accept') || '').toLowerCase();
            if (accept.includes('image') && !input.dataset.noAutoCompress && !input.dataset.hasCompressor) {
                input.dataset.hasCompressor = 'true';
                attachAutoCompressor(input, {
                    maxWidth: 1600,
                    maxHeight: 1600,
                    quality: 0.85
                });
            }
        });
    });

    window.compressImageFile = compressImageFile;
    window.attachAutoCompressor = attachAutoCompressor;

})(window);

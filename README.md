# JASA INHU - Marketplace Jasa Lokal Kabupaten Indragiri Hulu

Platform digital penghubung masyarakat yang membutuhkan jasa dengan penyedia jasa lokal (tukang, mekanik, teknisi, tenaga ahli perkebunan, dll.) di wilayah **Kabupaten Indragiri Hulu (Inhu), Riau, Indonesia**.

---

## 1. Konsep & Tujuan

- **Masyarakat Butuh Sesuatu** &rarr; Membuat permintaan jasa dengan lokasi kecamatan di Inhu.
- **Penyedia Jasa Merespons** &rarr; Memberikan penawaran harga & estimasi waktu pengerjaan.
- **Masyarakat Memilih** &rarr; Memilih penyedia terbaik dan berkoordinasi via WhatsApp/Telepon.

### Karakteristik Utama:
1. **Fokus Lokal Indragiri Hulu**: Menjangkau seluruh 14 kecamatan (Rengat, Rengat Barat, Pasir Penyu, Seberida, Peranap, Lirik, Batang Cenaku, Batang Gansal, dll.).
2. **Struktur Wilayah Berjenjang**: Kabupaten &rarr; Kecamatan &rarr; Desa/Kelurahan tersimpan di database.
3. **Kategori Fleksibel**: 17 kategori awal dari database yang dapat dikelola langsung oleh Admin.
4. **Keamanan Handal**: Perlindungan SQL Injection (PDO Prepared Statements), XSS (`htmlspecialchars`), CSRF Token, dan enkripsi kata sandi menggunakan `password_hash()` (Bcrypt).
5. **Mobile-First & Ringan**: Tampilan modern, responsif untuk layar smartphone, dibangun dengan Bootstrap 5, Font Awesome, dan Vanilla CSS tanpa framework berat.

---

## 2. Teknologi yang Digunakan

- **Bahasa Pemrograman**: PHP 8+ (Dukungan PHP 8.0, 8.1, 8.2+)
- **Basis Data**: MySQL / MariaDB (Kompatibel dengan MySQL 5.0+ hingga 8.0+ dan MariaDB)
- **Frontend**: HTML5, CSS3 kustom, JavaScript ES6
- **Framework UI**: Bootstrap 5.3 & Font Awesome 6
- **Server Kompatibel**: Apache (XAMPP / Laragon / Linux Apache) atau PHP Built-in Server

---

## 3. Struktur Direktori Project

```
d:/JASA-INHU/
├── admin/                     # Modul Administrator
│   ├── includes/
│   │   ├── header.php         # Layout header admin
│   │   └── footer.php         # Layout footer admin
│   ├── categories.php         # Manajemen kategori jasa
│   ├── index.php              # Dashboard admin (metrik & verifikasi)
│   ├── profile.php            # Edit akun admin & nomor handphone/WhatsApp
│   └── users.php              # Manajemen & edit pengguna/mitra (No. HP, Wilayah, dll)
├── api/                       # Endpoint AJAX / REST API
│   ├── districts.php          # JSON data 14 kecamatan Inhu
│   └── villages.php           # JSON data desa/kelurahan per kecamatan
├── assets/                    # Aset statis aplikasi
│   ├── css/
│   │   └── style.css          # Desain visual, tema warna, & layout responsif
│   └── js/
│       └── app.js             # Skrip interaksi, AJAX wilayah, & auto-dismiss
├── config/                    # Konfigurasi sistem
│   ├── app.php                # Identitas platform, timezone WIB, dynamic base URL
│   └── database.php           # Koneksi PDO (auto-detect port 3306 XAMPP / 3391)
├── database/                  # Skrip skema & data awal
│   ├── migrate.php            # Web/CLI runner otomatis untuk membuat DB & seeding
│   ├── schema.sql             # DDL struktur 14 tabel relasional
│   └── seed.sql               # Data master 14 kecamatan, 17 kategori, & akun demo
├── includes/                  # Komponen logika & template bersama
│   ├── auth.php               # Handler login, register, role middleware, session
│   ├── footer.php             # Footer publik dengan direktori wilayah Inhu
│   ├── functions.php          # Helper keamanan XSS, CSRF, format rupiah, flash
│   └── header.php             # Navigasi utama & session dropdown
├── provider/                  # Modul Penyedia Jasa (Mitra)
│   ├── index.php              # Dashboard mitra & kirim penawaran cepat
│   ├── leads.php              # Pencarian pekerjaan terbuka se-Inhu
│   └── profile.php            # Pengaturan profil usaha & tarif jasa
├── user/                      # Modul Masyarakat / Pengguna
│   ├── index.php              # Dashboard pengguna & buat permintaan jasa
│   ├── profile.php            # Profil & domisili pengguna
│   └── requests.php           # Detail permintaan & perbandingan tawaran masuk
├── tests/                     # Pengujian otomatis
│   └── test_flow.php          # Test suite autentikasi, database, & alur role
├── index.php                  # Halaman utama (Homepage) JASA INHU
├── login.php                  # Halaman masuk sistem
├── logout.php                 # Handler keluar sesi
├── order.php                  # Pemesanan langsung ke penyedia jasa tertentu (Direct Order)
├── register.php               # Pendaftaran (Pengguna & Penyedia Jasa)
└── README.md                  # Dokumentasi proyek
```

---

## 4. Struktur Tabel Database (`jasa_inhu`)

Tersedia 14 tabel yang saling berelasi secara logis:

1. `roles`: Master peran pengguna (`admin`, `pengguna`, `penyedia`).
2. `regencies`: Data kabupaten (default: Kabupaten Indragiri Hulu, Riau).
3. `districts`: 14 Kecamatan resmi di Kabupaten Indragiri Hulu.
4. `villages`: Master desa / kelurahan per kecamatan beserta kode pos.
5. `users`: Autentikasi akun, hash kata sandi bcrypt, email, nomor HP (WhatsApp).
6. `profiles`: Informasi domisili, alamat lengkap, dan bio pengguna.
7. `service_categories`: 17 kategori bidang keahlian dan ikon Font Awesome.
8. `service_providers`: Profil usaha, tarif minimum/maksimum, status verifikasi, dan rating.
9. `service_areas`: Wilayah jangkauan kerja penyedia jasa per kecamatan.
10. `service_requests`: Permintaan pekerjaan dari masyarakat (judul, lokasi, budget, urgensi).
11. `service_request_responses`: Tawaran harga dan pesan dari penyedia jasa ke pemohon.
12. `reviews`: Ulasan dan rating kepuasan (bintang 1 - 5).
13. `notifications`: Notifikasi aktivitas penting pengguna.
14. `reports`: Laporan pengaduan dan moderasi transaksi.

---

## 5. Cara Instalasi & Menjalankan di XAMPP

### Langkah 1: Persiapan Folder di XAMPP
1. Pastikan aplikasi **XAMPP** sudah terinstal di komputer Anda.
2. Salin atau letakkan folder `JASA-INHU` ke dalam direktori `htdocs`:
   - Misalnya: `C:\xampp\htdocs\JASA-INHU`

### Langkah 2: Menjalankan Apache dan MySQL
1. Buka **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache** dan **MySQL**.

### Langkah 3: Setup Database Otomatis
Anda dapat melakukan instalasi database dengan salah satu dari dua cara berikut:

**Opsi A (Paling Mudah via Browser):**
- Buka browser Anda dan akses tautan:
  `http://localhost/JASA-INHU/database/migrate.php`
- Skrip akan otomatis membuat database `jasa_inhu`, mengeksekusi tabel (`schema.sql`), dan mengisi data awal (`seed.sql`).

**Opsi B (Manual via phpMyAdmin):**
1. Buka `http://localhost/phpmyadmin/`.
2. Buat database baru bernama `jasa_inhu`.
3. Pilih database `jasa_inhu`, klik tab **Import**.
4. Import file `database/schema.sql`.
5. Setelah berhasil, import file `database/seed.sql`.

### Langkah 4: Membuka Aplikasi
Buka peramban dan kunjungi:
`http://localhost/JASA-INHU/`

*(Catatan: Jika Anda menjalankan aplikasi menggunakan built-in server PHP CLI, jalankan `php -S 127.0.0.1:8000` lalu akses `http://127.0.0.1:8000/`)*.

---

## 6. Akun Demo untuk Pengujian

Semua akun demo di bawah ini menggunakan kata sandi: **`password123`**

| Role / Peran | Nama | Email / Login | Wilayah / Usaha |
|---|---|---|---|
| **Admin** | Administrator Jasa Inhu | `admin@jasainhu.id` | Pengelola Pusat |
| **Pengguna** | Budi Santoso | `budi@gmail.com` | Warga Pematang Reba (Rengat Barat) |
| **Penyedia Jasa 1** | Ahmad Fauzi | `ahmad.servis@jasainhu.id` | Bengkel Berkah Motor (Rengat) |
| **Penyedia Jasa 2** | Hendra Wijaya | `hendra.ac@jasainhu.id` | Sejuk Jaya Tehnik AC (Belilas / Seberida) |

*(Di halaman `login.php` telah disediakan tombol cepat "Uji Coba Cepat" untuk mengisi kredensial dengan satu klik)*.

---

## 7. Hasil Pengujian Tahap Pertama

Semua alur sistem Tahap 1 telah diuji dan diverifikasi secara langsung:
- [x] Syntax PHP 8 linting (`php -l`) pada 25 file PHP: **100% Bebas Error**.
- [x] Test Suite Otomatis (`tests/test_flow.php`): **11 Test PASSED (0 Failed)**.
- [x] Koneksi database MySQL dengan fallback port adaptif (Port 3306 XAMPP / 3391).
- [x] Halaman Beranda JASA INHU memuat 17 kategori jasa dan 14 kecamatan secara dinamis dari database.
- [x] Pendaftaran Akun Pengguna & Penyedia Jasa dengan form dinamis.
- [x] Autentikasi Login, Proteksi Kata Sandi Bcrypt, dan Regenerasi ID Sesi.
- [x] Pembatasan Akses Halaman (Role Middleware) untuk Admin, Pengguna, dan Penyedia Jasa.
- [x] Dashboard interaktif untuk ketiga role.

---

## 8. Roadmap Pengembangan Selanjutnya (Tahap 2 & 3)

Setelah fondasi Tahap 1 terbukti kokoh dan stabil, tahap berikutnya dapat mencakup:
1. **Sistem Pesan Langsung (In-App Chat)**: Fitur perpesanan instan antara pemohon dan penyedia di dalam aplikasi.
2. **Notifikasi WhatsApp API Otomatis**: Pengiriman notifikasi penawaran baru langsung ke WhatsApp mitra dan pemohon.
3. **Upload Foto/Galeri Pekerjaan**: Bukti portofolio hasil kerja penyedia jasa dan foto kerusakan dari pemohon.
4. **Sistem Rekening Bersama / Pembayaran Digital**: Integrasi gateway pembayaran lokal / QRIS setelah legalitas usaha siap.
5. **Ekspansi Multi-Kabupaten**: Membuka wilayah kabupaten tetangga (seperti Kuantan Singingi, Pelalawan, dan Indragiri Hilir) memanfaatkan arsitektur wilayah database yang telah disiapkan.

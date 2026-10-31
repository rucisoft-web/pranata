# Panduan Instalasi PRANATA di cPanel

Panduan ini untuk hosting cPanel biasa (shared hosting atau VPS dengan cPanel). Ada dua jalur: **dengan Terminal/SSH** (lebih mudah) dan **tanpa Terminal** (memakai File Manager dan Cron Jobs).

Contoh di panduan memakai akun cPanel `userku`, folder program `/home/userku/pranata`, dan alamat `https://pranata.domainanda.com`. Sesuaikan dengan milik Anda.

## 1. Prasyarat hosting

| Komponen | Ketentuan | Tempat di cPanel |
|---|---|---|
| PHP | **8.3 atau lebih baru** (8.4 juga bisa) | *MultiPHP Manager* (pilih domain › PHP 8.3) atau *Select PHP Version* (CloudLinux) |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`, `dom`, `xmlwriter`, `ctype`, `tokenizer` | *Select PHP Version › Extensions* |
| Database | MySQL 8 atau MariaDB 10.6+ | *MySQL Databases* |
| SSL | Wajib (login dan cookie memakai HTTPS) | *SSL/TLS Status* › Run AutoSSL |

PRANATA tidak membutuhkan cron permanen; Cron Jobs hanya dipakai untuk menjalankan perintah sekali bila hosting tidak punya Terminal.

## 2. Buat database

1. Buka **MySQL Databases**.
2. *Create New Database*: misalnya `pranata` (cPanel menjadikannya `userku_pranata`).
3. *Add New User*: misalnya `grc` (menjadi `userku_grc`), dengan kata sandi kuat. Catat kata sandinya.
4. *Add User To Database*: pilih user dan database tadi › centang **ALL PRIVILEGES** › Make Changes.

## 3. Taruh program di server

Program diletakkan **di luar `public_html`** (misalnya `/home/userku/pranata`), supaya file `.env` dan kode tidak bisa dibuka dari browser.

### Jalur A: dengan Terminal (cPanel › Terminal, atau SSH)

```bash
cd ~
git clone https://github.com/rucisoft-web/pranata.git
cd pranata
composer install --no-dev --optimize-autoloader
```

Repo bersifat privat, jadi `git clone` meminta username GitHub dan *personal access token* (bukan kata sandi). Alternatif: *Git Version Control* di cPanel › Create › isi Clone URL.

Bila perintah `composer` tidak ada:

```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
```

Bila `php -v` di Terminal bukan 8.3 atau lebih baru, panggil PHP 8.3 langsung, misalnya `/opt/cpanel/ea-php83/root/usr/bin/php` (cPanel EasyApache) atau `/opt/alt/php83/usr/bin/php` (CloudLinux).

### Jalur B: tanpa Terminal (File Manager)

1. Pakai paket **`pranata-cpanel.zip`** (sudah berisi folder `vendor` versi produksi, tidak perlu Composer).
2. *File Manager* › buka `/home/userku` › **Upload** zip tersebut.
3. Klik kanan zip › **Extract** ke `/home/userku`. Hasilnya folder `/home/userku/pranata`.
4. Hapus file zip setelah diekstrak.

## 4. Arahkan domain ke folder `public`

### Pilihan 1 (disarankan): subdomain atau addon domain

1. *Domains* (atau *Subdomains*) › **Create A New Domain**, misalnya `pranata.domainanda.com`.
2. Matikan centang *Share document root* bila ada, lalu isi **Document Root**: `pranata/public`.
3. Simpan. Aktifkan SSL untuk domain itu di *SSL/TLS Status*.

Untuk domain yang sudah ada: *Domains* › Manage › ubah Document Root menjadi `pranata/public`.

### Pilihan 2: domain utama yang document root-nya terkunci di `public_html`

1. Pindahkan (atau salin) **seluruh isi** `pranata/public` ke `public_html`, termasuk file tersembunyi `.htaccess` (di File Manager: *Settings* › centang *Show Hidden Files*).
2. Edit `public_html/index.php`, ganti tiga baris yang berisi `__DIR__.'/../` menjadi:

```php
if (file_exists($maintenance = __DIR__.'/../pranata/storage/framework/maintenance.php')) {
require __DIR__.'/../pranata/vendor/autoload.php';
$app = require_once __DIR__.'/../pranata/bootstrap/app.php';
```

3. Setiap kali memperbarui program, salin ulang isi `pranata/public` ke `public_html` (kecuali `index.php` yang sudah diedit).

> Jangan mengekstrak seluruh program langsung ke `public_html`, karena `.env` (berisi kata sandi database) bisa terbuka dari browser. Paket sudah menyertakan `.htaccess` pengaman di folder utama untuk berjaga-jaga, tetapi tetap ikuti Pilihan 1 atau 2.

## 5. Isi file `.env`

Di folder `pranata`, salin `.env.example` menjadi `.env` (File Manager: klik kanan › Copy, beri nama `.env`), lalu edit:

```dotenv
APP_NAME=PRANATA
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://pranata.domainanda.com
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=userku_pranata
DB_USERNAME=userku_grc
DB_PASSWORD="kata-sandi-database"

SESSION_SECURE_COOKIE=true

# MFA (aplikasi authenticator seperti Google Authenticator) wajib untuk semua akun.
# Ubah ke false bila MFA cukup menjadi pilihan masing-masing pengguna.
PRANATA_MFA_REQUIRED=true
# Data contoh (tiga perusahaan DEMO): biarkan false untuk pemakaian sungguhan
PRANATA_SEED_DEMO=false
# Situs demo/promosi: tombol "Masuk sebagai Tamu" (hanya lihat), lihat bagian 8
PRANATA_GUEST_LOGIN=false
```

Biarkan `APP_KEY=` kosong; nilainya diisi otomatis oleh `php artisan key:generate` di langkah 6 (baris ini harus ada, kalau tidak muncul error *No APP_KEY variable was found*).

Catatan: di cPanel `DB_HOST` hampir selalu `localhost`. Kata sandi yang mengandung `#` atau spasi harus diberi tanda kutip.

## 6. Kunci aplikasi, tabel, dan administrator

### Dengan Terminal

```bash
cd ~/pranata
php artisan key:generate --force
php artisan migrate --seed --force
php artisan pranata:create-admin --name="Administrator" --email=admin@domainanda.com --entity-code=UTAMA --entity-name="PT Nama Perusahaan Tbk" --bumn --listed
php artisan optimize
php artisan pranata:doctor
```

`pranata:create-admin` meminta kata sandi bila `--password` tidak diisi. Hilangkan `--bumn` atau `--listed` bila tidak sesuai dengan status perusahaan. `migrate --seed` di produksi tidak memuat data apa pun kecuali `PRANATA_SEED_DEMO=true`.

`pranata:doctor` memeriksa versi PHP, ekstensi, koneksi database, tabel, folder yang harus bisa ditulis, dan pengaturan `.env`. Semua baris wajib harus `[OK]`.

### Tanpa Terminal (memakai Cron Jobs sekali jalan)

Buka **Cron Jobs**, tambahkan perintah berikut satu per satu dengan jadwal *Once Per Minute* (`* * * * *`). Tunggu 1–2 menit sampai berjalan, lalu **hapus** cron tersebut sebelum menambah yang berikutnya.

1. `cd /home/userku/pranata && php artisan key:generate --force > storage/logs/setup-1.txt 2>&1`
2. `cd /home/userku/pranata && php artisan migrate --seed --force > storage/logs/setup-2.txt 2>&1`
3. `cd /home/userku/pranata && php artisan pranata:create-admin --name="Administrator" --email=admin@domainanda.com --password='Sandi#Sementara2026' --entity-code=UTAMA --entity-name="PT Nama Perusahaan Tbk" --no-interaction > storage/logs/setup-3.txt 2>&1`
4. `cd /home/userku/pranata && php artisan optimize > storage/logs/setup-4.txt 2>&1`
5. `cd /home/userku/pranata && php artisan pranata:doctor > storage/logs/setup-5.txt 2>&1`

Periksa hasil tiap langkah di File Manager: `pranata/storage/logs/setup-*.txt`. Kata sandi minimal 12 karakter dengan huruf besar, huruf kecil, angka, dan simbol. Setelah login pertama, **ganti kata sandi** di menu **Keamanan** karena kata sandi sementara tadi sempat tersimpan di daftar cron.

Bila `php` di cron bukan versi 8.3 atau lebih baru, ganti `php` dengan path lengkap PHP 8.3 (lihat bagian 3).

## 7. Mulai memakai

1. Buka `https://pranata.domainanda.com/login`, masuk sebagai administrator.
2. Saat login pertama, pindai QR dengan aplikasi authenticator (Google Authenticator, Microsoft Authenticator, dsb.), masukkan kata sandi dan kode 6 digit, lalu **simpan sepuluh kode pemulihan** di tempat aman.
3. **Pengguna & akses** › *Tambah pengguna*: isi nama, email, peran, dan kata sandi sementara. Sampaikan kata sandi secara langsung; pengguna menggantinya di menu **Keamanan**.
4. Untuk anak perusahaan/entitas grup: **Pengguna & akses** › *Tambah perusahaan*. Pindah perusahaan lewat kotak **Perusahaan** di bagian atas.
5. Alur kerja: **Manajemen risiko** (catat › ajukan › ditinjau orang lain), **Register kewajiban** (sumber › kewajiban › periode), **Kalender kepatuhan** (PIC unggah bukti › ajukan › petugas kepatuhan verifikasi), **Audit internal** (rencana › lapangan › kertas kerja › temuan › final › tindak lanjut PIC › tutup).

| Peran | Hak utama |
|---|---|
| Administrator | Semua modul dan menu Pengguna & akses |
| Manajer risiko / Pemilik risiko | Mencatat risiko; manajer meninjau risiko milik orang lain |
| Petugas kepatuhan / PIC kepatuhan | Petugas mengelola kewajiban dan memverifikasi; PIC mengunggah bukti |
| Manajer audit / Auditor | Manajer merencanakan dan mereview; auditor hanya melihat penugasannya |
| Pembaca | Hanya melihat register risiko dan kepatuhan |

**Ponsel hilang / kode MFA tidak bisa dipakai:** administrator membuka **Pengguna & akses** › *Kelola* › **Reset MFA** (isi alasan). Pengguna mendaftarkan authenticator lagi saat login berikutnya. Untuk akun administrator sendiri, pakai kode pemulihan, atau jalankan `php artisan pranata:mfa-reset email@domain --reason="alasan reset"` (Terminal).

**Lupa kata sandi:** administrator memakai **Reset kata sandi** di menu yang sama. Bila administrator sendiri yang lupa, jalankan lagi perintah `pranata:create-admin` dengan email yang sama dan `--password` baru (lewat Terminal atau cron sekali jalan).

## 8. Situs demo untuk pengunjung (akses Tamu)

Untuk situs promosi, isi `.env`:

```
PRANATA_SEED_DEMO=true
PRANATA_GUEST_LOGIN=true
```

lalu jalankan berurutan `php artisan optimize:clear`, `php artisan db:seed --force`, dan `php artisan optimize` (Terminal atau cron sekali jalan seperti bagian 6). Data contoh berisi tiga perusahaan berlabel DEMO (profil farmasi, rumah sakit, dan properti dari laporan tahunan 2025) lengkap dengan 15 risiko, 9 kewajiban, 18 periode pemenuhan, 6 penugasan audit, 12 temuan, dan 24 bukti PDF sintetis. Semua catatan operasionalnya simulasi, bukan temuan atau penilaian aktual, dan peringatan ini tampil di setiap halaman.

Halaman depan lalu menampilkan tombol **Lihat demo sebagai tamu**, dan halaman login menampilkan **Masuk sebagai Tamu · Lihat Data Contoh**. Pengunjung masuk tanpa kata sandi ke `/demo`, bisa berganti di antara tiga perusahaan contoh, membuka semua modul, dan mengunduh bukti contoh. Akses tamu hanya membaca data berlabel DEMO: tidak ada tombol simpan, dan data perusahaan sungguhan tidak pernah terlihat. Untuk mematikan, ubah menjadi `PRANATA_GUEST_LOGIN=false` lalu `php artisan optimize`.

Data contoh dan data sungguhan boleh berada di instalasi yang sama, tetapi untuk produksi lebih rapi memakai instalasi terpisah.

## 9. Memperbarui program

Dengan Terminal:

```bash
cd ~/pranata
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan up
```

Tanpa Terminal: upload zip versi baru, ekstrak menimpa folder `pranata` (zip tidak berisi `.env`, dan isi folder `storage` seperti bukti unggahan dan log tidak terhapus), lalu jalankan `php artisan migrate --force` dan `php artisan optimize` lewat cron sekali jalan seperti bagian 6. Bila memakai Pilihan 2 di bagian 4, salin ulang isi `public` ke `public_html`.

Selalu **backup database** dulu (*phpMyAdmin* › Export, atau *Backup* di cPanel), dan simpan juga salinan folder `pranata/storage/app/private` (berisi berkas bukti) serta nilai `APP_KEY` di `.env`. Tanpa `APP_KEY` yang sama, MFA semua pengguna tidak bisa dibaca.

## 10. Masalah umum

| Gejala | Solusi |
|---|---|
| Error 500 tanpa keterangan | Lihat `pranata/storage/logs/laravel.log`. Untuk sementara boleh `APP_DEBUG=true`, lalu kembalikan ke `false`. |
| `Your PHP version … does not satisfy` | Ubah PHP domain ke 8.3 atau lebih baru di *MultiPHP Manager*, dan pastikan cron/Terminal juga memakai PHP 8.3+. |
| `could not find driver` | Aktifkan ekstensi **pdo_mysql**. |
| `SQLSTATE[HY000] [1045] Access denied` | Periksa `DB_DATABASE`/`DB_USERNAME` memakai awalan `userku_` dan user sudah ditambahkan ke database dengan ALL PRIVILEGES. |
| `Specified key was too long` | Sudah ditangani program (panjang indeks 191). Bila tetap muncul, minta hosting memakai MariaDB 10.6+/MySQL 8. |
| `No application encryption key` | Jalankan `php artisan key:generate --force` (bagian 6). |
| Halaman tampil tanpa gaya / 404 untuk `/css/...` | Document root belum ke `pranata/public`, atau isi `public` belum disalin lengkap ke `public_html`. |
| Semua halaman selain beranda 404 | File `.htaccess` dari `public` belum ikut tersalin (aktifkan *Show Hidden Files*). |
| `Permission denied` di `storage` | Atur izin folder `storage` dan `bootstrap/cache` menjadi 755 (File Manager › Change Permissions). |
| `419 Sesi kedaluwarsa` saat login | `APP_URL` harus sama persis dengan alamat yang dibuka (https dan subdomainnya). Bila belum memakai SSL, ubah `SESSION_SECURE_COOKIE=false` sementara. |
| Kode MFA selalu ditolak | Jam ponsel harus otomatis (sinkron jaringan). Kode yang sudah dipakai tidak bisa dipakai ulang; tunggu kode berikutnya. |
| Tombol "Masuk sebagai Tamu" tidak muncul | `PRANATA_GUEST_LOGIN=true` dan data contoh harus sudah dimuat (bagian 8), lalu `php artisan optimize`. |
| Unggah bukti gagal | Berkas PDF/JPG/PNG maksimal 10 MB. Naikkan `upload_max_filesize` dan `post_max_size` (misalnya 12M) di *MultiPHP INI Editor*. |
| Perubahan `.env` tidak berlaku | Jalankan `php artisan optimize:clear` lalu `php artisan optimize`. |

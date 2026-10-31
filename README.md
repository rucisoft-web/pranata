# PRANATA

**Governance, Risk & Compliance untuk BUMN dan perusahaan terbuka.**

Tata Kelola Terarah. Risiko Terkendali. — Pranata by Rucisoft.

## Status versi awal

Fondasi aplikasi Laravel 13 + MySQL, berisi autentikasi berbasis sesi, akses per perusahaan, dashboard, register risiko inheren, serta workflow draf → tinjauan → persetujuan/pengembalian. Antarmuka berbahasa Indonesia menggunakan Blade dan CSS lokal tanpa CDN atau proses build JavaScript.

Ini adalah fondasi pengembangan, belum aplikasi GRC lengkap dan belum siap menyimpan data produksi sensitif. Modul kepatuhan, audit internal, MFA, demo tamu, landing page, serta pengelolaan pengguna oleh administrator telah tersedia. Kontrol internal mendalam, dokumen kebijakan, KRI, risiko residual, lupa kata sandi lewat email, ekspor laporan, dan integrasi WBS belum tersedia. Lihat [roadmap](docs/ROADMAP.md).

## Persyaratan

- PHP **8.3 atau lebih baru** (8.3 dan 8.4 diuji) dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `xml`, `dom`, `xmlwriter`.
- Composer 2 (tidak perlu bila memakai paket cPanel yang sudah berisi `vendor`).
- MySQL 8 atau MariaDB 10.6+ (diuji juga dengan MariaDB 10.11).

## Instalasi lokal

```bash
git clone https://github.com/rucisoft-web/pranata.git
cd pranata
cp .env.example .env        # lalu isi DB_*, APP_URL, dan untuk lokal: APP_ENV=local, SESSION_SECURE_COOKIE=false
composer install
php artisan key:generate
php artisan migrate --seed
php artisan pranata:create-admin --name="Administrator" --email=admin@contoh.co.id --entity-code=UTAMA --entity-name="PT Contoh Tbk" --bumn --listed
php artisan pranata:doctor
php artisan serve
```

`pranata:create-admin` meminta kata sandi bila `--password` tidak diisi (untuk cron tanpa Terminal, isi `--password`). Setelah login, administrator menambah pengguna lain dan mengatur perannya di menu **Pengguna & akses** (juga reset kata sandi, reset MFA, cabut akses, dan tambah perusahaan grup). Setiap pengguna mengganti kata sandinya sendiri di menu **Keamanan**. Perintah `pranata:provision` (operator server) tetap tersedia.

Buka `http://localhost:8000` untuk landing page, `/login` untuk akun, dan `/demo` untuk contoh publik (bila `PRANATA_GUEST_LOGIN=true` dan data contoh dimuat dengan `PRANATA_SEED_DEMO=true` atau `php artisan db:seed --class=DemoSeeder`). MFA authenticator wajib secara bawaan; set `PRANATA_MFA_REQUIRED=false` bila ingin menjadikannya pilihan per pengguna. Pemilik tidak boleh menyetujui risikonya sendiri, termasuk ketika berperan administrator.

## Akses dan model penggunaan

Satu instalasi melayani satu grup perusahaan. Ini belum platform SaaS multi-pelanggan. Tabel perusahaan memiliki induk opsional, tetapi hubungan holding tidak otomatis memberi hak baca ke anak perusahaan. Akses selalu berasal dari keanggotaan eksplisit pada setiap perusahaan.

| Peran | Hak |
|---|---|
| `viewer` | Melihat register dan riwayat risiko perusahaan yang diberikan |
| `risk_owner` | Membuat, mengubah draf sendiri, mengajukan, dan membaca register perusahaan |
| `risk_manager` | Hak pemilik serta meninjau risiko milik pengguna lain |
| `admin` | Hak operasional risiko seperti risk manager; mengelola pengguna, peran, reset kata sandi/MFA, dan perusahaan grup di menu Pengguna & akses |

Pembatasan per unit dan klasifikasi kerahasiaan belum tersedia. Penugasan objek audit telah diterapkan: auditor hanya melihat penugasannya; PIC tindak lanjut melihat temuan terbit yang ditugaskan kepadanya. Dalam perusahaan yang sama, seluruh anggota dapat membaca register risiko dan kepatuhan; laporan/kertas kerja audit mengikuti akses khusus. Jangan gunakan untuk materi kasus WBS.

## Metodologi awal

`PRN-INITIAL-1`: kemungkinan 1–5 × dampak 1–5. Rendah 1–5, sedang 6–11, tinggi 12–19, kritis 20–25. Hanya penilaian **inheren**. Label kemungkinan/dampak bersifat awal dan perlu dilengkapi definisi kuantitatif serta disahkan perusahaan sebelum operasional. Jangan mengubah interpretasi versi ini setelah digunakan; metodologi berversi penuh adalah tahap berikutnya.

Pilihan respons `accept` adalah usulan respons; persetujuan catatan bukan persetujuan formal penerimaan risiko residual. Risiko disetujui tidak dapat diedit. Siklus penilaian ulang dan snapshot historis akan dikembangkan terpisah.

## Pengujian

Buat database **terpisah** bernama `pranata_test`. Sesuaikan kredensial pada `.env.testing` atau environment shell. Jangan arahkan pengujian ke database operasional: `RefreshDatabase` membuat ulang tabel.

```bash
php artisan test
```

GitHub Actions menjalankan validasi Composer, lint PHP, kompilasi Blade, pemeriksaan route, dan feature tests terhadap MySQL. Tes memeriksa isolasi perusahaan, pencabutan akses, pembatasan peran, spoofing field, maker-checker, status terkunci, konflik revisi, validasi, escaping, dan autentikasi.

`composer.lock` disusun pada target platform PHP 8.3.0 agar instalasi dapat direproduksi dan tetap berjalan di PHP 8.4. Gunakan `composer install`; pembaruan dependensi harus ditinjau dan diuji sebelum rilis.

## Deployment

Panduan lengkap hosting cPanel (dengan atau tanpa Terminal, termasuk paket zip berisi `vendor`) ada di [PANDUAN-CPANEL.md](PANDUAN-CPANEL.md). Intinya: document root hanya ke `public/`, `storage/` dan `bootstrap/cache/` dapat ditulis, HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` yang benar, `SESSION_SECURE_COOKIE=true`, lalu `php artisan pranata:doctor`. Backup database dan `APP_KEY` secara terpisah serta uji pemulihannya.

Sebelum produksi, selesaikan peninjauan keamanan, pemindaian malware pada unggahan, backup teruji, dan kebutuhan akses detail. Lihat [arsitektur dan batas keamanan](docs/ARCHITECTURE.md).

## Modul lanjutan dan demo

Lihat [panduan modul](docs/MODULES.md) untuk alur kepatuhan, audit, MFA dan pengaturan peran.

```bash
php artisan migrate
php artisan db:seed --class=DemoSeeder
```

Seeder membuat tiga perusahaan berlabel DEMO, 36 risiko (semua level dan status, lengkap dengan riwayat), 24 kewajiban dengan 120 periode pemenuhan (8 bulan terakhir hingga bulan depan), 15 penugasan audit di lima tahap, 33 kertas kerja, 24 temuan, dan 140 bukti PDF sintetis. Seeder idempoten, tidak menghapus data, dan menolak benturan kode dengan entitas/akun non-demo. Tidak ada kredensial demo publik: akun sintetis tidak dapat login. Akses tamu `/demo` hanya membaca entitas `is_demo=true`, memakai guard request terpisah, dan tidak melewati MFA akun internal. Akses tamu aktif bila `PRANATA_GUEST_LOGIN=true` (nama lama `DEMO_ENABLED` masih dibaca) dan data contoh sudah dimuat.

Perusahaan contoh: profil Kimia Farma, Medikaloka Hermina, dan Pakuwon Jati berdasarkan laporan tahunan 2025 yang diberikan. Semua catatan operasional adalah simulasi, bukan temuan atau penilaian aktual. Dokumen asli tidak didistribusikan dalam repository. Lihat [asal dan batas data demo](docs/DEMO_DATA.md).

Landing page menampilkan matriks ilustrasi SVG dan tangkapan layar aplikasi yang dihasilkan oleh `scripts/capture.mjs`. CI menjalankan browser desktop 1440×1080 dan mobile 390×844, memeriksa halaman demo, serta menghasilkan aset review. Untuk regenerasi: siapkan aplikasi dan DemoSeeder, instal Playwright 1.56.1, jalankan server, lalu `node scripts/capture.mjs`.

## Sumber fondasi

Entrypoint dan konfigurasi dasar diadaptasi dari `laravel/laravel` branch `13.x`. Laravel framework memakai lisensi MIT. Lihat [pemberitahuan pihak ketiga](THIRD_PARTY_NOTICES.md). Kode produk PRANATA belum diberikan lisensi distribusi publik.

## Versi PHP pada hosting

Jalankan `php -v` atau `php artisan pranata:doctor` memakai PHP CLI yang sama dengan situs. Versi PHP situs dan terminal/cron hosting dapat berbeda; pada cPanel EasyApache, PHP 8.3 biasanya di `/opt/cpanel/ea-php83/root/usr/bin/php` dan pada CloudLinux di `/opt/alt/php83/usr/bin/php`. `composer.lock` disusun untuk platform PHP 8.3.0 sehingga paket juga berjalan di PHP 8.4. Jangan memakai `--ignore-platform-reqs`.

# PRANATA

**Governance, Risk & Compliance untuk BUMN dan perusahaan terbuka.**

Tata Kelola Terarah. Risiko Terkendali. — Pranata by Rucisoft.

## Status versi awal

Fondasi aplikasi Laravel 13 + MySQL, berisi autentikasi berbasis sesi, akses per perusahaan, dashboard, register risiko inheren, serta workflow draf → tinjauan → persetujuan/pengembalian. Antarmuka berbahasa Indonesia menggunakan Blade dan CSS lokal tanpa CDN atau proses build JavaScript.

Ini adalah fondasi pengembangan, belum aplikasi GRC lengkap dan belum siap menyimpan data produksi sensitif. Modul kepatuhan, kontrol, audit, dokumen, KRI, risiko residual, MFA, reset kata sandi, ekspor, dan integrasi WBS belum diimplementasikan. Lihat [roadmap](docs/ROADMAP.md).

## Persyaratan

- PHP 8.3 atau lebih baru dengan ekstensi Laravel serta `pdo_mysql`; CI menggunakan PHP 8.4.
- Composer 2.
- MySQL 8.x; CI menggunakan MySQL 8.4.
- Database kosong dengan pengguna aplikasi khusus. Jangan memakai akun root untuk produksi.

## Instalasi lokal

```bash
git clone https://github.com/rucisoft-web/pranata.git
cd pranata
git switch feat/grc-foundation
cp .env.example .env
composer install
php artisan key:generate
```

Buat database `pranata`, kemudian isi `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` pada `.env` sesuai lingkungan sendiri. Jangan commit `.env`, kredensial database, atau `APP_KEY`.

```bash
php artisan migrate
php artisan pranata:provision admin@example.com --name="Administrator" --entity-code=DEMO --entity-name="PT Pranata Demo Tbk" --role=admin --bumn --public-company --listed
php artisan pranata:provision owner@example.com --name="Pemilik Risiko" --entity-code=DEMO --entity-name="PT Pranata Demo Tbk" --role=risk_owner
php artisan serve
```

Perintah provision meminta kata sandi secara tersembunyi; tidak ada akun atau kata sandi bawaan. Gunakan alamat dan nama perusahaan sendiri. Nama perusahaan demo bersifat fiktif. Untuk perusahaan non-BUMN atau tidak terbuka, hilangkan flag yang tidak sesuai. Flag status perusahaan tidak menghasilkan kesimpulan keberlakuan regulasi otomatis.

Buka `http://localhost:8000`. Masuk sebagai pemilik risiko untuk membuat dan mengajukan risiko; masuk sebagai administrator berbeda untuk meninjau. Pemilik tidak boleh menyetujui risikonya sendiri, termasuk ketika berperan administrator.

Provision adalah perintah khusus operator server tepercaya; menjalankannya kembali untuk pengguna dan perusahaan yang sama dapat mengganti peran. Pengelolaan pengguna melalui UI belum tersedia. Perintah ini mempertahankan profil perusahaan yang telah ada, tidak memperbaruinya diam-diam.

## Akses dan model penggunaan

Satu instalasi melayani satu grup perusahaan. Ini belum platform SaaS multi-pelanggan. Tabel perusahaan memiliki induk opsional, tetapi hubungan holding tidak otomatis memberi hak baca ke anak perusahaan. Akses selalu berasal dari keanggotaan eksplisit pada setiap perusahaan.

| Peran | Hak |
|---|---|
| `viewer` | Melihat register dan riwayat risiko perusahaan yang diberikan |
| `risk_owner` | Membuat, mengubah draf sendiri, mengajukan, dan membaca register perusahaan |
| `risk_manager` | Hak pemilik serta meninjau risiko milik pengguna lain |
| `admin` | Hak operasional risiko seperti risk manager; provision pengguna melalui CLI terpisah |

Pembatasan per unit, klasifikasi kerahasiaan, dan penugasan objek belum tersedia. Dalam perusahaan yang sama, seluruh anggota dapat membaca seluruh register dan riwayat perubahan risiko. Jangan gunakan untuk materi kasus WBS.

## Metodologi awal

`PRN-INITIAL-1`: kemungkinan 1–5 × dampak 1–5. Rendah 1–5, sedang 6–11, tinggi 12–19, kritis 20–25. Hanya penilaian **inheren**. Label kemungkinan/dampak bersifat awal dan perlu dilengkapi definisi kuantitatif serta disahkan perusahaan sebelum operasional. Jangan mengubah interpretasi versi ini setelah digunakan; metodologi berversi penuh adalah tahap berikutnya.

Pilihan respons `accept` adalah usulan respons; persetujuan catatan bukan persetujuan formal penerimaan risiko residual. Risiko disetujui tidak dapat diedit. Siklus penilaian ulang dan snapshot historis akan dikembangkan terpisah. Waktu sistem disimpan dan ditampilkan dalam UTC pada versi awal.

## Pengujian

Buat database **terpisah** bernama `pranata_test`. Sesuaikan kredensial pada `.env.testing` atau environment shell. Jangan arahkan pengujian ke database operasional: `RefreshDatabase` membuat ulang tabel.

```bash
php artisan test
```

GitHub Actions menjalankan validasi Composer, lint PHP, kompilasi Blade, pemeriksaan route, dan feature tests terhadap MySQL. Tes memeriksa isolasi perusahaan, pencabutan akses, pembatasan peran, spoofing field, maker-checker, status terkunci, konflik revisi, validasi, escaping, dan autentikasi.

Versi awal belum menyertakan `composer.lock`: resolusi dependensi pertama harus ditinjau, diuji, dan lockfile di-commit sebelum rilis yang dapat direproduksi. Jangan menjalankan pembaruan dependensi tanpa pengujian di produksi.

## Deployment

Arahkan document root web server hanya ke `public/`. Pastikan `storage/` dan `bootstrap/cache/` dapat ditulis oleh proses aplikasi. Gunakan HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` yang benar, dan `SESSION_SECURE_COOKIE=true`. Backup database dan `APP_KEY` secara terpisah serta uji pemulihannya.

Jangan men-deploy versi ini sebagai sistem produksi sebelum MFA, peninjauan keamanan, audit operasional, backup teruji, dan kebutuhan akses detail diselesaikan. Lihat [arsitektur dan batas keamanan](docs/ARCHITECTURE.md).

## Sumber fondasi

Entrypoint dan konfigurasi dasar diadaptasi dari `laravel/laravel` branch `13.x`. Laravel framework memakai lisensi MIT. Lihat [pemberitahuan pihak ketiga](THIRD_PARTY_NOTICES.md). Kode produk PRANATA belum diberikan lisensi distribusi publik.

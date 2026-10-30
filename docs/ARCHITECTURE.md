# Arsitektur fondasi PRANATA

## Batas instalasi

Satu instalasi untuk satu grup perusahaan; tidak ada klaim isolasi SaaS lintas pelanggan. `entities.parent_id` hanya hubungan struktural. Pengguna memperoleh satu peran per perusahaan melalui `entity_user`.

## Alur request

1. Middleware web Laravel menangani sesi terenkripsi, cookie, dan CSRF pada mutasi browser.
2. `auth` memverifikasi sesi pengguna.
3. `ResolveEntity` memeriksa ulang keanggotaan perusahaan pada setiap request dan membuat `EntityContext` request tersebut.
4. Controller membatasi query menggunakan perusahaan dari konteks, bukan field input pengguna.
5. `RiskWorkflow` memeriksa hak, kepemilikan, status, dan revisi sebelum mutasi.
6. Transaksi database mencatat perubahan risiko dan audit secara atomik.

## Autentikasi

Email disimpan huruf kecil oleh perintah provision. Password di-hash melalui cast Laravel `hashed`; plaintext tidak disimpan atau ditulis ke log. Login menggunakan pesan kesalahan umum, batas 5 kegagalan per kombinasi email/IP per menit, serta pembatas route per IP. Sesi diregenerasi saat login dan diinvalidasi saat logout. `entity_id` di sesi selalu dicocokkan kembali ke keanggotaan database. Tidak ada token API, registrasi publik, atau kredensial bawaan.

Belum tersedia MFA, SSO, reset password, verifikasi email, pemutusan seluruh sesi dari panel admin, dan audit login. Reverse proxy tepercaya perlu dikonfigurasi sesuai deployment sebelum mengandalkan alamat IP untuk pembatasan.

## Risiko dan persetujuan

Pemilik adalah pembuat catatan pada versi ini; penugasan ulang belum tersedia. Admin dan risk manager dapat meninjau risiko orang lain, tidak mengambil alih edit draf. `revision` adalah optimistic concurrency token; `lockForUpdate` dan transaksi mencegah dua perubahan workflow saling menimpa pada MySQL. Draf yang disetujui dikunci; penilaian ulang berperiode belum tersedia.

## Audit

Audit menyimpan sebelum/sesudah, pelaku, perusahaan, waktu, dan aksi. Hook model menolak update/delete melalui instance Eloquent. Ini **bukan** log yang kebal manipulasi: query builder, SQL langsung, dan operator database masih dapat mengubahnya. Penyimpanan append-only eksternal, retensi, pencatatan akses baca/ekspor, dan akses darurat harus ditambahkan sebelum klaim audit tahan manipulasi. Tidak ada endpoint edit/hapus audit.

## Integrasi WBS

Belum ada endpoint integrasi aktif. Kontrak mendatang hanya membawa referensi eksternal, ringkasan tersamarkan, kategori, kelemahan kontrol, dan tindakan yang disetujui. Identitas pelapor, komunikasi dan bukti asli tetap di WBS. Rancangan mencakup kredensial terpisah, scope per perusahaan, idempotency key, pencatatan transfer, dan antrean kegagalan. Hak GRC tidak memberi akses otomatis ke WBS.

## Model data

- `users`: akun internal.
- `entities`: perusahaan dan status BUMN/terbuka/tercatat yang terpisah.
- `entity_user`: keanggotaan dan peran.
- `risks`: konteks, uraian, skor inheren, respons, jadwal, status, persetujuan dan revisi.
- `audit_events`: snapshot perubahan risiko.

Sasaran dan proses masih berupa teks pada risiko; master sasaran/proses adalah tahap berikutnya. Dashboard menghitung seluruh status, bukan hanya risiko disahkan, dan label menjelaskannya. Tidak ada rata-rata skor ordinal atau agregasi risiko grup.

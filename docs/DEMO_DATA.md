# Asal dan batas data contoh

## Acuan profil

Laporan yang diberikan pengguna dibaca sebagai acuan nama dan konteks sektor, bukan sebagai sumber untuk menyimpulkan adanya pelanggaran atau temuan:

| Profil | Acuan lampiran | Bagian yang digunakan |
|---|---|---|
| Kimia Farma | Laporan_Tahunan_Tahun_Buku Kimia Farma 2025.pdf | Sampul PDF halaman 1 dan pengantar halaman 4: identitas serta konteks farmasi/layanan kesehatan |
| Medikaloka Hermina | hermina2025(1).pdf | Sampul PDF halaman 1 dan pengantar halaman 3: identitas serta jaringan layanan rumah sakit |
| Pakuwon Jati | pakuwon2025(1).pdf | PDF halaman 3 dan pengantar halaman 5: identitas serta konteks properti, pusat perbelanjaan, perkantoran dan hospitality |

Status dan nama pada contoh mengikuti profil acuan 2025; bukan verifikasi struktur kepemilikan atau status perusahaan terkini. Dokumen lampiran asli tidak disalin ke repository atau demo publik.

## Data sintetis

Semua risiko, penilaian, kejadian, kewajiban, tanggal, PIC, kertas kerja, temuan, rekomendasi, dan bukti dibuat untuk demonstrasi. Tidak ada data pribadi pejabat nyata, data pasien, atau transaksi nyata. Kewajiban memakai sumber kebijakan simulasi, bukan klaim kewajiban hukum perusahaan.

Penyebutan nama perusahaan bukan klaim bahwa mereka pelanggan, pengguna, sponsor, atau pendukung PRANATA.

## Isolasi publik

- Tiga entitas memiliki `is_demo=true` dan kode `DEMO-*`.
- Mode tamu hanya memilih dan membaca entitas demo.
- Pergantian perusahaan demo memakai parameter `entity` dan sesi `demo_entity_id`, terpisah dari perusahaan aktif akun.
- Guard `demo` diisi untuk request saja, tanpa login sesi akun internal.
- Semua akun sintetis `is_demo_guest=true` dan ditolak oleh login password.
- Route tamu seluruhnya GET/HEAD; tidak ada route mutasi demo.
- ID risiko, audit, kewajiban dan lampiran tetap diperiksa terhadap perusahaan demo aktif.
- Akun internal dan verifikasi MFA tidak berubah ketika pengguna berkunjung ke demo.
- `PRANATA_GUEST_LOGIN=false` (bawaan) menonaktifkan seluruh route demo dan menyembunyikan tombol tamu.

Seeder idempoten, tidak menghapus data, dan menolak benturan dengan entitas/akun non-demo. Jangan menandai entitas produksi sebagai demo; flag ini adalah keputusan publikasi data, bukan label tampilan biasa.

# Kepatuhan, audit internal, MFA, dan landing page

## Peran

Tambahan peran pada `pranata:provision --role=...`:

| Peran | Tanggung jawab |
|---|---|
| `compliance_officer` | Mencatat sumber/kewajiban, menilai keberlakuan, membuat periode, memverifikasi bukti orang lain |
| `compliance_owner` | Mengunggah bukti dan mengajukan pemenuhan untuk periode yang ditugaskan |
| `audit_manager` | Merencanakan audit, menjadi reviewer laporan bila ditugaskan, melihat audit perusahaan |
| `auditor` | Melihat penugasan sendiri, mencatat kertas kerja/temuan, mengajukan laporan, memverifikasi tindak lanjut |

Admin dapat mengelola kewajiban serta rencana audit tetapi tidak otomatis menjadi auditor penanggung jawab. PIC tindakan audit harus anggota perusahaan, bukan viewer/auditor, dan berbeda dari lead/reviewer penugasan. Delegasi dan penugasan ulang melalui UI belum tersedia.

## Kepatuhan

1. Officer mencatat versi sumber: judul, referensi, penerbit, versi, tanggal berlaku, URL opsional.
2. Officer menetapkan kewajiban, klausul, frekuensi, kritikalitas, keberlakuan beserta alasan, dan PIC.
3. Officer membuat periode/kejadian dengan label unik dan tenggat. Pembentukan periode masih eksplisit, belum terjadwal otomatis.
4. Sistem menyimpan snapshot definisi, sumber, dan PIC agar perubahan master tidak menimpa periode lama.
5. PIC mengunggah bukti privat dan mengajukan catatan pemenuhan.
6. Officer lain memverifikasi atau mengembalikan; PIC tidak dapat memverifikasi sendiri.

Status: `open → submitted → fulfilled`, atau `submitted → returned → submitted`. Lampiran dikunci setelah pengajuan, terbuka lagi saat dikembalikan. Tanggal tenggat, pengajuan dan verifikasi tetap terpisah. Waktu verifikasi setelah tenggat ditandai, tidak mengubah tanggal tenggat. Status dan keterlambatan tidak disamakan. Tidak ada klaim perhitungan kepatuhan hukum otomatis.

## Audit internal

1. Audit manager membuat penugasan, lingkup, tujuan, jadwal, auditor lead, dan reviewer berbeda.
2. Lead memulai `fieldwork`, menambahkan kertas kerja berisi kriteria, prosedur/sampel, hasil, dan kesimpulan; lampiran opsional.
3. Lead mencatat temuan: kriteria, kondisi, sebab, dampak, rekomendasi, tingkat, PIC, dan tenggat.
4. Lead mengajukan ringkasan laporan. Sedikitnya satu kertas kerja diperlukan.
5. Reviewer yang ditugaskan mengembalikan atau memfinalisasi laporan. Finalisasi menerbitkan seluruh draf temuan.
6. PIC melihat temuan terbit melalui Tindak Lanjut Saya, mengunggah bukti, lalu mengajukan tindakan/penyelesaian.
7. Lead atau reviewer memverifikasi penutupan atau mengembalikan. PIC tidak menutup sendiri.
8. Reviewer menutup penugasan setelah seluruh temuan ditutup.

Auditor yang tidak ditugaskan tidak memperoleh akses. PIC temuan tidak memperoleh akses ke seluruh laporan/kertas kerja. Kertas kerja dan temuan bersifat append-only pada versi ini: koreksi lapangan dicatat sebagai tambahan sebelum finalisasi, bukan menghapus bukti. Audit universe, program tahunan, format laporan PDF, pengelolaan tim lebih dari satu auditor, serta penugasan ulang belum tersedia.

## Bukti

PDF, JPG/JPEG, PNG maksimum 10 MB, validasi MIME dan ekstensi. Penyimpanan lokal privat dengan nama acak; unduhan memeriksa perusahaan dan hak pada objek asal, menggunakan disposition attachment, serta memeriksa SHA-256. Audit akses unduhan internal dicatat. Tidak ada URL publik untuk bukti operasional.

Validasi format bukan pemindaian malware. Integrasi pemindai/quarantine, object storage, legal hold, dan retensi perlu diselesaikan sebelum produksi sensitif. Bukti demo merupakan berkas sintetis tersendiri dan sengaja dapat dibaca tamu.

## MFA wajib

Seluruh akun internal melewati password → pendaftaran/verifikasi MFA → akses data. Tidak ada opsi menonaktifkan MFA melalui browser. Tamu menggunakan lingkungan demo read-only terpisah dan tidak mendapat sesi akun internal.

- TOTP 6 digit/30 detik dengan toleransi satu interval.
- Secret disimpan terenkripsi menggunakan `APP_KEY`.
- Counter terakhir disimpan dengan lock transaksi untuk mencegah replay.
- Sepuluh recovery code acak, hanya hash yang disimpan di database.
- Kode ditampilkan sekali; konsumsi kode pemulihan atomik dan sekali pakai.
- Enrollment dan rotasi kode meminta password; rotasi juga meminta TOTP yang belum digunakan.
- QR dibuat lokal, tidak mengirim secret ke penyedia gambar.
- Rotasi/reset mengganti epoch MFA sehingga sesi lama harus diverifikasi lagi.
- Endpoint MFA dibatasi percobaannya, halaman tidak di-cache, dan kode/password tidak diflash ke old input.

Backup `APP_KEY` dengan aman: tanpa kunci tersebut secret MFA terenkripsi tidak dapat dibaca. Sinkronkan waktu server dengan NTP.

Reset darurat hanya oleh operator server tepercaya setelah verifikasi identitas di luar aplikasi:

```bash
php artisan pranata:mfa-reset user@example.com --reason="Identitas diverifikasi dan reset telah disetujui"
```

Perintah meminta konfirmasi dan mencatat alasan di `security_events`. Identitas operator sistem perlu ditelusuri melalui akses server organisasi; belum ada integrasi identitas operator ke perintah CLI.

## Landing page dan tema

Route `/` adalah landing page publik; `/login` untuk akun, `/demo` untuk tamu. Tema terang menggunakan garis tipis teal, biru, emas, dan warna status yang terbatas. Layout responsif tanpa font/CDN eksternal. Grafik matriks pada landing berupa SVG ilustratif; gambar galeri adalah screenshot dari browser yang menjalankan aplikasi demo. Screenshot publik hanya boleh berasal dari entitas demo.

CI menangkap dashboard, kalender kepatuhan, dan audit, serta memeriksa overflow desktop/mobile. Audit visual manusia masih diperlukan untuk kebutuhan aksesibilitas dan perangkat yang lebih luas.

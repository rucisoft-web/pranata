<?php

/*
 * Pedoman Tata Kelola Perusahaan Terbuka: 5 aspek, 8 prinsip, 25 rekomendasi
 * (POJK 21/POJK.04/2015 dan SEOJK 32/SEOJK.04/2015). Pengungkapan dilakukan dengan
 * pendekatan "terapkan atau jelaskan" di laporan tahunan.
 */
return [
    'framework' => 'Pedoman Tata Kelola Perusahaan Terbuka',
    'reference' => 'POJK 21/POJK.04/2015 · SEOJK 32/SEOJK.04/2015',

    'statuses' => [
        'not_assessed' => 'Belum dinilai',
        'applied' => 'Diterapkan',
        'partial' => 'Diterapkan sebagian',
        'not_applied' => 'Belum diterapkan',
    ],
    // Istilah yang lazim dipakai pada tabel pengungkapan laporan tahunan.
    'disclosure_labels' => [
        'not_assessed' => 'Belum dinilai',
        'applied' => 'Dipenuhi',
        'partial' => 'Dipenuhi sebagian',
        'not_applied' => 'Belum dipenuhi',
    ],
    'review_statuses' => [
        'draft' => 'Penyusunan',
        'submitted' => 'Menunggu verifikasi',
        'returned' => 'Perlu perbaikan',
        'verified' => 'Terverifikasi',
    ],
    'assessment_statuses' => [
        'open' => 'Penyusunan',
        'approved' => 'Disetujui untuk pengungkapan',
    ],
    'action_statuses' => [
        'open' => 'Berjalan',
        'done' => 'Selesai',
    ],

    'aspects' => [
        1 => 'Hubungan Perusahaan Terbuka dengan Pemegang Saham dalam Menjamin Hak-Hak Pemegang Saham',
        2 => 'Fungsi dan Peran Dewan Komisaris',
        3 => 'Fungsi dan Peran Direksi',
        4 => 'Partisipasi Pemangku Kepentingan',
        5 => 'Keterbukaan Informasi',
    ],
    'short_aspects' => [1 => 'Pemegang saham', 2 => 'Dewan Komisaris', 3 => 'Direksi', 4 => 'Pemangku kepentingan', 5 => 'Keterbukaan informasi'],

    'principles' => [
        1 => [1, 'Meningkatkan Nilai Penyelenggaraan Rapat Umum Pemegang Saham (RUPS)'],
        2 => [1, 'Meningkatkan Kualitas Komunikasi Perusahaan Terbuka dengan Pemegang Saham atau Investor'],
        3 => [2, 'Memperkuat Keanggotaan dan Komposisi Dewan Komisaris'],
        4 => [2, 'Meningkatkan Kualitas Pelaksanaan Tugas dan Tanggung Jawab Dewan Komisaris'],
        5 => [3, 'Memperkuat Keanggotaan dan Komposisi Direksi'],
        6 => [3, 'Meningkatkan Kualitas Pelaksanaan Tugas dan Tanggung Jawab Direksi'],
        7 => [4, 'Meningkatkan Aspek Tata Kelola Perusahaan melalui Partisipasi Pemangku Kepentingan'],
        8 => [5, 'Meningkatkan Pelaksanaan Keterbukaan Informasi'],
    ],

    /* kode => [prinsip, rekomendasi, contoh bukti, fungsi PIC yang lazim] */
    'recommendations' => [
        '1.1' => [1, 'Perusahaan Terbuka memiliki cara atau prosedur teknis pengumpulan suara (voting) baik secara terbuka maupun tertutup yang mengedepankan independensi dan kepentingan pemegang saham.',
            'Tata tertib RUPS yang memuat mekanisme pemungutan suara terbuka/tertutup, penggunaan e-RUPS/eASY.KSEI, berita acara notaris, dan penghitung suara independen.', 'Sekretaris Perusahaan'],
        '1.2' => [1, 'Seluruh anggota Direksi dan anggota Dewan Komisaris Perusahaan Terbuka hadir dalam RUPS Tahunan.',
            'Daftar hadir RUPS Tahunan, risalah RUPS, dan penjelasan bila ada anggota yang berhalangan.', 'Sekretaris Perusahaan'],
        '1.3' => [1, 'Ringkasan risalah RUPS tersedia dalam Situs Web Perusahaan Terbuka paling sedikit selama 1 (satu) tahun.',
            'Tautan dan tangkapan layar halaman ringkasan risalah RUPS di situs web, dengan tanggal unggah.', 'Sekretaris Perusahaan'],
        '2.1' => [2, 'Perusahaan Terbuka memiliki suatu kebijakan komunikasi dengan pemegang saham atau investor.',
            'Kebijakan komunikasi dengan pemegang saham/investor yang disahkan Direksi, kalender kegiatan investor relations.', 'Investor Relations'],
        '2.2' => [2, 'Perusahaan Terbuka mengungkapkan kebijakan komunikasi Perusahaan Terbuka dengan pemegang saham atau investor dalam Situs Web.',
            'Tautan halaman kebijakan komunikasi di situs web dan bagian laporan tahunan yang merujuknya.', 'Investor Relations'],
        '3.1' => [3, 'Penentuan jumlah anggota Dewan Komisaris mempertimbangkan kondisi Perusahaan Terbuka.',
            'Keputusan RUPS pengangkatan, kajian jumlah dan komposisi Dewan Komisaris dengan mempertimbangkan ukuran dan kompleksitas usaha.', 'Komite Nominasi dan Remunerasi'],
        '3.2' => [3, 'Penentuan komposisi anggota Dewan Komisaris memperhatikan keberagaman keahlian, pengetahuan, dan pengalaman yang dibutuhkan.',
            'Matriks keahlian dan keberagaman Dewan Komisaris, kebijakan keberagaman, profil anggota.', 'Komite Nominasi dan Remunerasi'],
        '4.1' => [4, 'Dewan Komisaris mempunyai kebijakan penilaian sendiri (self assessment) untuk menilai kinerja Dewan Komisaris.',
            'Kebijakan dan formulir self assessment Dewan Komisaris, hasil penilaian tahun berjalan.', 'Sekretariat Dewan Komisaris'],
        '4.2' => [4, 'Kebijakan penilaian sendiri (self assessment) untuk menilai kinerja Dewan Komisaris diungkapkan melalui laporan tahunan Perusahaan Terbuka.',
            'Bagian laporan tahunan yang memuat kriteria, prosedur, dan pihak yang melakukan penilaian kinerja Dewan Komisaris.', 'Sekretaris Perusahaan'],
        '4.3' => [4, 'Dewan Komisaris mempunyai kebijakan terkait pengunduran diri anggota Dewan Komisaris apabila terlibat dalam kejahatan keuangan.',
            'Piagam/pedoman kerja Dewan Komisaris yang memuat ketentuan pengunduran diri bila terlibat kejahatan keuangan.', 'Sekretariat Dewan Komisaris'],
        '4.4' => [4, 'Dewan Komisaris atau Komite yang menjalankan fungsi Nominasi dan Remunerasi menyusun kebijakan suksesi dalam proses Nominasi anggota Direksi.',
            'Kebijakan suksesi Direksi, talent pool, dan risalah rapat Komite Nominasi dan Remunerasi.', 'Komite Nominasi dan Remunerasi'],
        '5.1' => [5, 'Penentuan jumlah anggota Direksi mempertimbangkan kondisi Perusahaan Terbuka serta efektivitas dalam pengambilan keputusan.',
            'Kajian struktur organisasi dan jumlah anggota Direksi, keputusan RUPS pengangkatan.', 'Human Capital'],
        '5.2' => [5, 'Penentuan komposisi anggota Direksi memperhatikan keberagaman keahlian, pengetahuan, dan pengalaman yang dibutuhkan.',
            'Matriks keahlian Direksi, kebijakan keberagaman, profil dan riwayat pendidikan anggota Direksi.', 'Human Capital'],
        '5.3' => [5, 'Anggota Direksi yang membawahi bidang akuntansi atau keuangan memiliki keahlian dan/atau pengetahuan di bidang akuntansi.',
            'Profil Direktur Keuangan, ijazah/sertifikasi akuntansi (mis. CA, CPA, CFA).', 'Human Capital'],
        '6.1' => [6, 'Direksi mempunyai kebijakan penilaian sendiri (self assessment) untuk menilai kinerja Direksi.',
            'Kebijakan self assessment Direksi, KPI kolegial dan individu, hasil penilaian.', 'Sekretaris Perusahaan'],
        '6.2' => [6, 'Kebijakan penilaian sendiri (self assessment) untuk menilai kinerja Direksi diungkapkan melalui laporan tahunan Perusahaan Terbuka.',
            'Bagian laporan tahunan yang memuat kriteria dan prosedur penilaian kinerja Direksi.', 'Sekretaris Perusahaan'],
        '6.3' => [6, 'Direksi mempunyai kebijakan terkait pengunduran diri anggota Direksi apabila terlibat dalam kejahatan keuangan.',
            'Piagam/pedoman kerja Direksi yang memuat ketentuan pengunduran diri bila terlibat kejahatan keuangan.', 'Sekretaris Perusahaan'],
        '7.1' => [7, 'Perusahaan Terbuka memiliki kebijakan untuk mencegah terjadinya insider trading.',
            'Kebijakan perdagangan saham orang dalam, periode blackout, daftar orang dalam, sosialisasi.', 'Sekretaris Perusahaan'],
        '7.2' => [7, 'Perusahaan Terbuka memiliki kebijakan anti korupsi dan anti fraud.',
            'Kebijakan anti korupsi/anti fraud, pengendalian gratifikasi, sertifikasi SMAP (ISO 37001) bila ada, laporan pelaksanaan.', 'Kepatuhan'],
        '7.3' => [7, 'Perusahaan Terbuka memiliki kebijakan tentang seleksi dan peningkatan kemampuan pemasok atau vendor.',
            'Kebijakan pengadaan, kriteria seleksi dan evaluasi vendor, program pengembangan pemasok.', 'Pengadaan'],
        '7.4' => [7, 'Perusahaan Terbuka memiliki kebijakan tentang pemenuhan hak-hak kreditur.',
            'Kebijakan pengelolaan pinjaman dan pemenuhan hak kreditur, pemantauan covenant.', 'Keuangan dan Treasury'],
        '7.5' => [7, 'Perusahaan Terbuka memiliki kebijakan sistem whistleblowing.',
            'Kebijakan whistleblowing system, saluran pelaporan, perlindungan pelapor, statistik laporan.', 'Kepatuhan'],
        '7.6' => [7, 'Perusahaan Terbuka memiliki kebijakan pemberian insentif jangka panjang kepada Direksi dan karyawan.',
            'Kebijakan remunerasi dan program insentif jangka panjang (LTI, MESOP/ESOP), keputusan RUPS terkait.', 'Human Capital'],
        '8.1' => [8, 'Perusahaan Terbuka memanfaatkan penggunaan teknologi informasi secara lebih luas selain Situs Web sebagai media keterbukaan informasi.',
            'Kanal informasi lain (media sosial resmi, aplikasi, webinar paparan publik), kebijakan pengelolaan kanal digital.', 'Komunikasi Korporat'],
        '8.2' => [8, 'Laporan Tahunan Perusahaan Terbuka mengungkapkan pemilik manfaat akhir dalam kepemilikan saham Perusahaan Terbuka paling sedikit 5% (lima persen), selain pengungkapan pemilik manfaat akhir dalam kepemilikan saham Perusahaan Terbuka melalui pemegang saham utama dan pengendali.',
            'Daftar pemegang saham dari Biro Administrasi Efek, bagan kepemilikan sampai pemilik manfaat akhir, bagian laporan tahunan terkait.', 'Sekretaris Perusahaan'],
    ],
];

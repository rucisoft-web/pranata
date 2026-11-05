<?php

/*
 * Templat kuesioner tata kelola. Jenis pertanyaan:
 *  scale  = skala 1–5, yesno = ya/tidak, text = isian bebas.
 * Pertanyaan yesno dengan 'flag' => 'yes' atau 'no' menandai jawaban yang menjadi
 * pengecualian dan wajib dijelaskan (mis. ada benturan kepentingan).
 * 'governance' memetakan hasil kuesioner sebagai bukti rekomendasi Pedoman Tata Kelola.
 */
return [
    'scale' => [1 => 'Sangat kurang', 2 => 'Kurang', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat baik'],
    'min_anonymous_responses' => 3,
    'roles' => ['admin', 'risk_manager', 'risk_owner', 'compliance_officer', 'compliance_owner', 'audit_manager', 'auditor', 'board_member'],

    'templates' => [
        'board_eval' => [
            'title' => 'Self assessment Dewan Komisaris',
            'kind' => 'Evaluasi kinerja', 'organ' => 'Dewan Komisaris', 'anonymous' => true, 'governance' => ['4.1', '4.2'],
            'description' => 'Penilaian kolegial atas pelaksanaan tugas pengawasan dan pemberian nasihat Dewan Komisaris selama tahun buku.',
            'sections' => [
                'Komposisi dan kompetensi' => [
                    ['bc1', 'scale', 'Jumlah dan komposisi anggota sesuai dengan ukuran dan kompleksitas usaha Perseroan.'],
                    ['bc2', 'scale', 'Keahlian, pengetahuan, dan pengalaman anggota cukup beragam untuk mengawasi strategi dan risiko utama.'],
                    ['bc3', 'scale', 'Komisaris Independen mampu menyampaikan pandangan secara independen.'],
                ],
                'Pengawasan strategi dan kinerja' => [
                    ['bs1', 'scale', 'Dewan Komisaris menelaah dan memberi masukan atas RJPP dan RKAP secara memadai.'],
                    ['bs2', 'scale', 'Pemantauan kinerja Direksi dilakukan berkala berdasarkan indikator yang disepakati.'],
                    ['bs3', 'scale', 'Dewan Komisaris memberikan arahan yang tepat waktu atas isu strategis dan risiko utama.'],
                ],
                'Rapat dan pengambilan keputusan' => [
                    ['bm1', 'scale', 'Rapat diselenggarakan sesuai jadwal dan frekuensi yang ditetapkan.'],
                    ['bm2', 'scale', 'Bahan rapat diterima cukup waktu dan berkualitas untuk pengambilan keputusan.'],
                    ['bm3', 'scale', 'Risalah rapat mencerminkan pembahasan, perbedaan pendapat, dan keputusan secara akurat.'],
                ],
                'Komite dan hubungan dengan organ lain' => [
                    ['bk1', 'scale', 'Komite penunjang (Audit, Nominasi dan Remunerasi, Pemantau Risiko) berfungsi efektif.'],
                    ['bk2', 'scale', 'Komunikasi dengan Direksi, auditor internal, dan auditor eksternal berjalan terbuka.'],
                    ['bk3', 'text', 'Hal yang perlu ditingkatkan oleh Dewan Komisaris pada tahun berikutnya.'],
                ],
            ],
        ],
        'director_eval' => [
            'title' => 'Self assessment Direksi',
            'kind' => 'Evaluasi kinerja', 'organ' => 'Direksi', 'anonymous' => true, 'governance' => ['6.1', '6.2'],
            'description' => 'Penilaian kolegial atas pelaksanaan tugas pengurusan Direksi, termasuk pencapaian KPI, manajemen risiko, dan tata kelola.',
            'sections' => [
                'Strategi dan kinerja' => [
                    ['ds1', 'scale', 'Direksi menetapkan dan mengomunikasikan strategi serta target usaha secara jelas.'],
                    ['ds2', 'scale', 'KPI kolegial tahun buku tercapai atau penyimpangannya ditindaklanjuti dengan rencana yang memadai.'],
                    ['ds3', 'scale', 'Pembagian tugas antaranggota Direksi jelas dan efektif.'],
                ],
                'Manajemen risiko dan pengendalian' => [
                    ['dr1', 'scale', 'Profil risiko utama dipantau dan dibahas berkala dalam rapat Direksi.'],
                    ['dr2', 'scale', 'Tindak lanjut temuan audit internal dan eksternal diselesaikan tepat waktu.'],
                    ['dr3', 'scale', 'Pengendalian internal atas pelaporan keuangan berjalan efektif.'],
                ],
                'Tata kelola dan kepatuhan' => [
                    ['dg1', 'scale', 'Direksi memastikan pemenuhan kewajiban regulator dan keterbukaan informasi tepat waktu.'],
                    ['dg2', 'scale', 'Direksi menjadi teladan penerapan kode etik dan budaya anti fraud.'],
                    ['dg3', 'scale', 'Hubungan kerja dengan Dewan Komisaris dan pemangku kepentingan berjalan baik.'],
                ],
                'Pengembangan organisasi' => [
                    ['do1', 'scale', 'Program suksesi dan pengembangan talenta kepemimpinan berjalan.'],
                    ['do2', 'text', 'Prioritas perbaikan kinerja Direksi untuk tahun berikutnya.'],
                ],
            ],
        ],
        'committee_eval' => [
            'title' => 'Evaluasi Komite Audit',
            'kind' => 'Evaluasi kinerja', 'organ' => 'Komite Audit', 'anonymous' => true, 'governance' => ['4.1'],
            'description' => 'Penilaian efektivitas Komite Audit oleh anggota komite dan Dewan Komisaris.',
            'sections' => [
                'Piagam dan komposisi' => [
                    ['ca1', 'scale', 'Piagam Komite Audit mutakhir dan dipahami anggota.'],
                    ['ca2', 'scale', 'Anggota memiliki keahlian akuntansi, keuangan, dan industri yang memadai.'],
                ],
                'Pelaksanaan tugas' => [
                    ['ct1', 'scale', 'Komite menelaah laporan keuangan sebelum diterbitkan secara memadai.'],
                    ['ct2', 'scale', 'Komite mengevaluasi independensi dan kinerja auditor eksternal.'],
                    ['ct3', 'scale', 'Komite memantau rencana kerja dan temuan audit internal.'],
                    ['ct4', 'scale', 'Komite menelaah pengaduan dan laporan pelanggaran yang relevan.'],
                    ['ct5', 'text', 'Saran untuk meningkatkan efektivitas Komite Audit.'],
                ],
            ],
        ],
        'dno' => [
            'title' => 'Kuesioner Direksi dan Dewan Komisaris (D&O)',
            'kind' => 'Pengungkapan', 'organ' => 'Direksi dan Dewan Komisaris', 'anonymous' => false, 'governance' => ['7.1', '4.3', '6.3', '8.2'],
            'description' => 'Pernyataan tahunan untuk laporan tahunan dan pengawasan benturan kepentingan: hubungan afiliasi, kepemilikan saham, rangkap jabatan, independensi, dan kepatuhan.',
            'sections' => [
                'Hubungan afiliasi' => [
                    ['na1', 'yesno', 'Apakah Anda memiliki hubungan keluarga sampai derajat kedua dengan anggota Direksi, Dewan Komisaris, atau pemegang saham utama Perseroan?', 'yes'],
                    ['na2', 'yesno', 'Apakah Anda memiliki hubungan keuangan atau kepengurusan dengan pemegang saham pengendali?', 'yes'],
                ],
                'Kepemilikan saham' => [
                    ['ns1', 'yesno', 'Apakah Anda memiliki saham Perseroan secara langsung atau tidak langsung?', 'yes'],
                    ['ns2', 'yesno', 'Apakah Anda melakukan transaksi saham Perseroan selama periode blackout?', 'yes'],
                    ['ns3', 'yesno', 'Apakah Anda telah melaporkan setiap perubahan kepemilikan saham kepada Sekretaris Perusahaan dan OJK dalam waktu yang ditentukan?', 'no'],
                ],
                'Rangkap jabatan' => [
                    ['nr1', 'yesno', 'Apakah Anda menjabat sebagai direktur, komisaris, atau anggota komite di perusahaan lain?', 'yes'],
                    ['nr2', 'yesno', 'Apakah jumlah rangkap jabatan Anda melampaui batas yang diatur dalam ketentuan OJK atau kebijakan Perseroan?', 'yes'],
                ],
                'Benturan kepentingan dan independensi' => [
                    ['nb1', 'yesno', 'Apakah Anda atau pihak terafiliasi memiliki kepentingan dalam transaksi dengan Perseroan selama tahun buku?', 'yes'],
                    ['nb2', 'yesno', 'Khusus Komisaris Independen: apakah Anda pernah bekerja atau memiliki kewenangan di Perseroan dalam 6 bulan terakhir?', 'yes'],
                ],
                'Kepatuhan dan integritas' => [
                    ['ni1', 'yesno', 'Apakah Anda pernah dinyatakan pailit, dihukum karena tindak pidana keuangan, atau sedang dalam proses hukum terkait kejahatan keuangan?', 'yes'],
                    ['ni2', 'yesno', 'Apakah Anda telah menerima, memahami, dan mematuhi kebijakan perdagangan saham orang dalam?', 'no'],
                    ['ni3', 'text', 'Keterangan tambahan yang perlu diketahui Sekretaris Perusahaan.'],
                ],
            ],
        ],
        'coc' => [
            'title' => 'Pernyataan kepatuhan Kode Etik dan gratifikasi',
            'kind' => 'Pernyataan kepatuhan', 'organ' => 'Insan Perseroan', 'anonymous' => false, 'governance' => ['7.2', '7.5'],
            'description' => 'Pernyataan tahunan atas pemahaman Kode Etik, pengendalian gratifikasi, benturan kepentingan, dan saluran whistleblowing.',
            'sections' => [
                'Kode Etik' => [
                    ['ce1', 'yesno', 'Saya telah membaca dan memahami Kode Etik Perseroan yang berlaku.', 'no'],
                    ['ce2', 'yesno', 'Saya mengetahui saluran whistleblowing dan perlindungan bagi pelapor.', 'no'],
                ],
                'Gratifikasi dan benturan kepentingan' => [
                    ['cg1', 'yesno', 'Selama tahun berjalan, apakah Anda menerima hadiah atau fasilitas terkait jabatan yang belum dilaporkan?', 'yes'],
                    ['cg2', 'yesno', 'Apakah Anda memiliki benturan kepentingan yang belum diungkapkan kepada atasan atau fungsi kepatuhan?', 'yes'],
                    ['cg3', 'text', 'Keterangan atas jawaban di atas, bila ada.'],
                ],
            ],
        ],
    ],
];

<?php
namespace Database\Seeders;

use App\Models\AuditEngagement;
use App\Models\AuditEvent;
use App\Models\AuditFinding;
use App\Models\AuditProcedure;
use App\Models\Entity;
use App\Models\EvidenceFile;
use App\Models\GovernanceAction;
use App\Models\GovernanceAssessment;
use App\Models\GovernanceItem;
use App\Models\Obligation;
use App\Models\ObligationOccurrence;
use App\Models\QuestionnaireAssignment;
use App\Models\QuestionnaireCampaign;
use App\Models\QuestionnaireResponse;
use App\Models\RegulatorySource;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSeeder extends Seeder
{
    private const PEOPLE = [
        'owner' => ['risk_owner', 'Rina Pratiwi'], 'owner-2' => ['risk_owner', 'Bayu Santoso'],
        'risk-reviewer' => ['risk_manager', 'Dewi Kartika'], 'compliance' => ['compliance_officer', 'Hendra Wijaya'],
        'pic' => ['compliance_owner', 'Sari Melati'], 'pic-2' => ['compliance_owner', 'Agus Firmansyah'],
        'auditor' => ['auditor', 'Yusuf Hakim'], 'auditor-2' => ['auditor', 'Maya Anggraini'],
        'audit-reviewer' => ['audit_manager', 'Indra Kusuma'],
        'komut' => ['board_member', 'Ratna Hadiwijaya'], 'komisaris' => ['board_member', 'Bambang Sutrisno'],
        'komisaris-ind' => ['board_member', 'Lestari Wulandari'], 'dirut' => ['board_member', 'Arif Nugroho'],
        'dirkeu' => ['board_member', 'Fitri Handayani'],
    ];

    /** Kewajiban contoh: [judul, frekuensi, tingkat kritis, PIC, sumber]. */
    private const OBLIGATIONS = [
        ['Laporan keuangan triwulanan kepada regulator dan bursa', 'quarterly', 'critical', 'pic', 'PASAR'],
        ['Laporan tahunan dan laporan keberlanjutan', 'annual', 'critical', 'pic', 'PASAR'],
        ['Keterbukaan informasi atau fakta material', 'monthly', 'high', 'pic', 'PASAR'],
        ['Penyelenggaraan RUPS tahunan dan pengumuman hasilnya', 'annual', 'high', 'pic-2', 'GCG'],
        ['Laporan bulanan pelaksanaan manajemen risiko', 'monthly', 'medium', 'pic-2', 'RISIKO'],
        ['Rapat Direksi dan Dewan Komisaris berkala beserta risalah', 'monthly', 'medium', 'pic-2', 'GCG'],
        ['Laporan pelaksanaan pengendalian gratifikasi dan benturan kepentingan', 'quarterly', 'high', 'pic', 'GCG'],
        ['Pembaruan register perizinan usaha dan sertifikasi operasional', 'quarterly', 'medium', 'pic-2', 'SEKTOR'],
    ];

    private const SOURCES = [
        'PASAR' => ['Pedoman pelaporan dan keterbukaan emiten (simulasi)', 'Divisi Kepatuhan — skenario demo', 'SIM-PASAR-2025', 'Rev. 2025'],
        'GCG' => ['Pedoman tata kelola perusahaan dan organ (simulasi)', 'Sekretaris Perusahaan — skenario demo', 'SIM-GCG-2025', 'Rev. 2025'],
        'RISIKO' => ['Kebijakan manajemen risiko korporasi (simulasi)', 'Unit Manajemen Risiko — skenario demo', 'SIM-ERM-2024', 'Rev. 2024'],
        'SEKTOR' => ['Ketentuan perizinan sektor usaha (simulasi)', 'Legal — skenario demo', 'SIM-IZIN-2025', 'Rev. 2025'],
    ];

    private const AUDITS = [
        ['draft', 'Audit pengelolaan persediaan dan gudang', 'auditor'],
        ['fieldwork', 'Audit pengadaan barang dan jasa', 'auditor-2'],
        ['review', 'Audit keamanan informasi dan hak akses aplikasi', 'auditor'],
        ['finalized', 'Audit siklus pendapatan dan piutang', 'auditor-2'],
        ['closed', 'Audit kepatuhan pelaporan korporasi', 'auditor'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $guest = $this->account('guest@demo.pranata.example', 'Tamu PRANATA');
            foreach ($this->profiles() as [$code, $name, $bumn, $sector, $risks]) {
                $entity = Entity::firstOrNew(['code' => 'DEMO-'.$code]);
                if ($entity->exists && ! $entity->is_demo) { throw new RuntimeException('Kode perusahaan demo berbenturan dengan data non-demo.'); }
                if (! $entity->exists) { $entity->fill(['name' => $name.' — DEMO', 'is_bumn' => $bumn, 'is_public_company' => true, 'is_listed' => true, 'is_demo' => true])->save(); }
                $people = [];
                foreach (self::PEOPLE as $suffix => [$role, $person]) {
                    $people[$suffix] = $this->account(strtolower($code).'.'.$suffix.'@demo.pranata.example', $person.' · '.$code.' (Simulasi)');
                    $people[$suffix]->entities()->syncWithoutDetaching([$entity->id => ['role' => $role]]);
                }
                $guest->entities()->syncWithoutDetaching([$entity->id => ['role' => 'viewer']]);
                $this->risks($entity, $code, $sector, $risks, $people);
                $this->compliance($entity, $code, $sector, $people);
                $this->audits($entity, $code, $sector, $people);
                $this->governance($entity, $code, $people);
                $this->questionnaires($entity, $code, $people);
            }
        });
    }

    private function profiles(): array
    {
        return [
            ['KAEF', 'PT Kimia Farma (Persero) Tbk', true, 'Farmasi dan layanan kesehatan', [
                ['Ketergantungan pasokan bahan baku impor', 'Operasional', 'Rantai pasok farmasi', 5, 4],
                ['Keterlambatan penerimaan piutang institusi', 'Keuangan', 'Pengelolaan piutang', 3, 4],
                ['Ketidakkonsistenan dokumentasi mutu produksi', 'Hukum dan Kepatuhan', 'Penjaminan mutu', 3, 5],
                ['Gangguan aplikasi layanan apotek', 'Teknologi dan Siber', 'Operasional teknologi', 2, 4],
                ['Keterlambatan pelaporan internal', 'Reputasi', 'Pelaporan korporasi', 2, 2],
                ['Fluktuasi kurs atas pembelian bahan baku', 'Keuangan', 'Treasuri', 4, 3],
                ['Produk kedaluwarsa di jaringan distribusi', 'Operasional', 'Distribusi dan gudang', 3, 3],
                ['Serangan ransomware pada sistem ERP', 'Teknologi dan Siber', 'Keamanan informasi', 4, 5],
                ['Penyalahgunaan diskon dan retur oleh pihak terkait', 'Fraud', 'Penjualan', 2, 4],
                ['Keterlambatan pengembangan produk baru', 'Strategis', 'Riset dan pengembangan', 3, 3],
                ['Pengelolaan limbah B3 yang tidak sesuai', 'ESG', 'Lingkungan pabrik', 2, 5],
                ['Penarikan produk akibat keluhan mutu', 'Reputasi', 'Layanan pelanggan', 2, 5],
            ]],
            ['HEAL', 'PT Medikaloka Hermina Tbk', false, 'Jaringan rumah sakit', [
                ['Gangguan kesinambungan layanan rumah sakit', 'Operasional', 'Pelayanan rumah sakit', 3, 5],
                ['Keterlambatan penagihan klaim layanan', 'Keuangan', 'Siklus pendapatan', 4, 4],
                ['Akses tidak sesuai pada data pasien', 'Teknologi dan Siber', 'Keamanan informasi', 4, 5],
                ['Ketidaklengkapan bukti pemeliharaan alat', 'Hukum dan Kepatuhan', 'Pemeliharaan peralatan', 2, 4],
                ['Keterlambatan penanganan umpan balik pasien', 'Reputasi', 'Pengalaman pasien', 2, 2],
                ['Kekurangan tenaga medis spesialis', 'Strategis', 'Sumber daya manusia', 4, 4],
                ['Kenaikan biaya obat dan bahan medis habis pakai', 'Keuangan', 'Pengadaan medis', 3, 3],
                ['Infeksi terkait layanan kesehatan', 'Operasional', 'Pencegahan infeksi', 2, 5],
                ['Manipulasi tagihan layanan', 'Fraud', 'Penagihan', 2, 4],
                ['Keterlambatan izin operasional rumah sakit baru', 'Hukum dan Kepatuhan', 'Ekspansi jaringan', 3, 4],
                ['Pengelolaan limbah medis', 'ESG', 'Lingkungan', 2, 4],
                ['Gangguan pasokan listrik dan oksigen', 'Operasional', 'Fasilitas', 2, 5],
            ]],
            ['PWON', 'PT Pakuwon Jati Tbk', false, 'Properti, pusat perbelanjaan, dan hospitality', [
                ['Penurunan tingkat hunian area sewa', 'Strategis', 'Pengelolaan tenant', 3, 4],
                ['Keterlambatan proyek pengembangan', 'Operasional', 'Manajemen proyek', 4, 4],
                ['Gangguan sistem keselamatan gedung', 'Operasional', 'Manajemen fasilitas', 4, 5],
                ['Keterlambatan penyelesaian dokumen proyek', 'Hukum dan Kepatuhan', 'Administrasi proyek', 2, 4],
                ['Ketidakakuratan jadwal pelaporan internal', 'Keuangan', 'Pelaporan pengelolaan aset', 2, 2],
                ['Kenaikan suku bunga pinjaman proyek', 'Keuangan', 'Pendanaan', 3, 4],
                ['Sengketa lahan dan sertifikat', 'Hukum dan Kepatuhan', 'Pertanahan', 2, 5],
                ['Penurunan okupansi hotel', 'Strategis', 'Hospitality', 3, 3],
                ['Kolusi dalam pemilihan kontraktor', 'Fraud', 'Pengadaan proyek', 2, 4],
                ['Serangan siber pada sistem parkir dan tenant', 'Teknologi dan Siber', 'Operasional teknologi', 3, 3],
                ['Konsumsi energi dan emisi gedung', 'ESG', 'Efisiensi energi', 3, 3],
                ['Keluhan publik atas kemacetan kawasan', 'Reputasi', 'Hubungan masyarakat', 3, 2],
            ]],
        ];
    }

    private function risks(Entity $entity, string $code, string $sector, array $risks, array $people): void
    {
        $statuses = ['approved', 'submitted', 'approved', 'approved', 'draft', 'approved', 'submitted', 'approved', 'approved', 'draft', 'approved', 'submitted'];
        $responses = ['reduce', 'reduce', 'reduce', 'share', 'accept', 'share', 'reduce', 'reduce', 'avoid', 'reduce', 'reduce', 'accept'];
        foreach ($risks as $i => [$title, $category, $process, $likelihood, $impact]) {
            $status = $statuses[$i];
            $owner = $people[$i % 3 === 2 ? 'owner-2' : 'owner'];
            $created = now()->subDays(120 - $i * 7);
            $risk = Risk::firstOrNew(['entity_id' => $entity->id, 'code' => $code.'-R'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)]);
            if ($risk->exists) { continue; }
            $risk->forceFill([
                'owner_id' => $owner->id, 'title' => $title, 'category' => $category,
                'objective' => 'Menjaga kinerja dan kesinambungan usaha '.strtolower($sector).' sesuai RKAP.',
                'process' => $process,
                'cause' => 'Skenario: ketergantungan pada proses '.strtolower($process).', pengendalian yang belum terdokumentasi seragam, dan perubahan lingkungan usaha.',
                'event' => 'Skenario: '.strtolower($title).'.',
                'impact_description' => 'Potensi penurunan pendapatan, tambahan biaya, keterlambatan layanan, atau sorotan regulator dan publik (ilustrasi demo).',
                'likelihood' => $likelihood, 'impact' => $impact,
                'assessment_reason' => 'Nilai ilustratif berdasarkan frekuensi kejadian simulasi tiga tahun terakhir dan estimasi dampak terhadap laba. Bukan penilaian atas perusahaan yang disebut.',
                'response' => $responses[$i],
                'treatment_plan' => match ($responses[$i]) {
                    'share' => 'Alihkan sebagian risiko melalui asuransi dan klausul kontrak; pantau kinerja mitra triwulanan.',
                    'accept' => 'Risiko diterima dalam batas selera risiko; dipantau lewat indikator bulanan dan ditinjau ulang semesteran.',
                    'avoid' => 'Hentikan praktik berisiko dan wajibkan persetujuan berjenjang untuk transaksi serupa.',
                    default => 'Perkuat kontrol kunci, tetapkan indikator peringatan dini, dan uji rencana kontinjensi sebelum akhir triwulan.',
                },
                'review_date' => in_array($i, [1, 6], true) ? today()->subDays(4 + $i) : today()->addDays(20 + $i * 9),
                'methodology_version' => config('grc.methodology_version'), 'status' => $status,
                'approved_by' => $status === 'approved' ? $people['risk-reviewer']->id : null,
                'approved_at' => $status === 'approved' ? $created->copy()->addDays(9) : null,
                'review_note' => $status === 'approved' ? 'Penilaian dan rencana penanganan memadai.' : null,
                'revision' => $status === 'draft' ? 1 : ($status === 'submitted' ? 2 : 3),
            ]);
            $risk->created_at = $created; $risk->updated_at = $created->copy()->addDays(9);
            $risk->save();
            $this->event($entity, 'risk', $risk->id, 'risk.created', $owner, $created);
            if ($i === 4) {
                $this->event($entity, 'risk', $risk->id, 'risk.submit', $owner, $created->copy()->addDays(3));
                $this->event($entity, 'risk', $risk->id, 'risk.return', $people['risk-reviewer'], $created->copy()->addDays(5));
                $risk->forceFill(['review_note' => 'Lengkapi dasar penilaian dampak dengan angka kerugian.'])->save();
            }
            if ($status !== 'draft') { $this->event($entity, 'risk', $risk->id, 'risk.submit', $owner, $created->copy()->addDays(6)); }
            if ($status === 'approved') { $this->event($entity, 'risk', $risk->id, 'risk.approve', $people['risk-reviewer'], $created->copy()->addDays(9)); }
        }
    }

    private function compliance(Entity $entity, string $code, string $sector, array $people): void
    {
        $sources = [];
        foreach (self::SOURCES as $key => [$title, $issuer, $reference, $version]) {
            $sources[$key] = RegulatorySource::firstOrCreate(['entity_id' => $entity->id, 'code' => $code.'-SRC-'.$key], [
                'title' => $title, 'issuer' => $issuer, 'reference' => $reference, 'version' => $version,
                'effective_date' => today()->subYear()->startOfYear(), 'created_by' => $people['compliance']->id,
            ]);
        }
        foreach (self::OBLIGATIONS as $i => [$title, $frequency, $criticality, $pic, $source]) {
            $obligation = Obligation::firstOrCreate(['entity_id' => $entity->id, 'code' => $code.'-K'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)], [
                'regulatory_source_id' => $sources[$source]->id, 'title' => $title,
                'description' => 'Contoh kewajiban untuk demonstrasi: siapkan dokumen, lakukan penelaahan internal, peroleh persetujuan pejabat berwenang, lalu sampaikan sebelum tenggat. Bukan kutipan atau penetapan kewajiban hukum aktual.',
                'clause' => 'Bagian '.($i + 2).' pedoman simulasi', 'frequency' => $frequency, 'criticality' => $criticality, 'applicability' => 'applies',
                'applicability_reason' => 'Perusahaan tercatat di bursa'.($entity->is_bumn ? ' dan berstatus BUMN' : '').' pada skenario demonstrasi; keberlakuan nyata perlu ditelaah Legal/Compliance.',
                'responsible_id' => $people[$pic]->id, 'created_by' => $people['compliance']->id,
            ]);
            foreach ($this->periods($frequency) as $k => [$period, $due]) {
                $key = crc32($code.$i.$period) % 10;
                $past = $due->lt(today());
                $status = match (true) {
                    ! $past && $due->lte(today()->addDays(10)) && $key < 4 => 'submitted',
                    ! $past => 'open',
                    $due->gte(today()->subDays(20)) && $key < 3 => 'open',
                    $due->gte(today()->subDays(45)) && $key < 2 => 'returned',
                    $due->gte(today()->subDays(30)) && $key < 5 => 'submitted',
                    default => 'fulfilled',
                };
                $submitted = $status === 'open' ? null : $due->copy()->subDays(1 + $key % 4)->setTime(10, 0);
                $occurrence = ObligationOccurrence::firstOrCreate(['obligation_id' => $obligation->id, 'period' => $period], [
                    'entity_id' => $entity->id, 'obligation_snapshot' => $obligation->load('source')->toArray(),
                    'responsible_id' => $obligation->responsible_id, 'due_date' => $due, 'status' => $status,
                    'submission_note' => $submitted ? 'Dokumen telah disiapkan, ditelaah, dan disetujui Direksi (simulasi).' : null,
                    'submitted_at' => $submitted, 'submitted_by' => $submitted ? $obligation->responsible_id : null,
                    'reviewed_by' => in_array($status, ['fulfilled', 'returned'], true) ? $people['compliance']->id : null,
                    'review_note' => $status === 'fulfilled' ? 'Bukti lengkap dan disampaikan tepat waktu.' : ($status === 'returned' ? 'Lampiran persetujuan Direksi belum ada; lengkapi dan ajukan ulang.' : null),
                    'fulfilled_at' => $status === 'fulfilled' ? $submitted->copy()->addDays(2) : null,
                ]);
                if ($submitted) { $this->evidence($entity, 'compliance', $occurrence->id, $people[$pic]); }
            }
        }
    }

    /** Periode contoh relatif terhadap hari ini: [label, tenggat]. */
    private function periods(string $frequency): array
    {
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $list = [];
        if ($frequency === 'monthly') {
            for ($m = -6; $m <= 1; $m++) {
                $month = today()->startOfMonth()->addMonths($m);
                $list[] = [$months[$month->month].' '.$month->year, $month->copy()->addMonth()->day(10)];
            }
        } elseif ($frequency === 'quarterly') {
            for ($q = -3; $q <= 0; $q++) {
                $start = today()->firstOfQuarter()->addMonths($q * 3);
                $list[] = ['Triwulan '.$start->quarter.' '.$start->year, $start->copy()->addMonths(3)->addDays(29)];
            }
        } else {
            foreach ([-1, 0] as $y) {
                $year = today()->year + $y - 1;
                $list[] = ['Tahun buku '.$year, today()->startOfYear()->addYears($y)->month(4)->day(30)];
            }
        }

        return $list;
    }

    private function audits(Entity $entity, string $code, string $sector, array $people): void
    {
        $procedures = [
            ['Uji rancangan kontrol otorisasi', 'Matriks kewenangan dan SOP terkait', 'Telaah SOP, wawancara pemilik proses, dan walkthrough satu transaksi.', 'Rancangan kontrol memadai dan terdokumentasi.', 'effective'],
            ['Uji efektivitas operasional atas 25 sampel', 'Setiap transaksi disetujui pejabat berwenang sebelum diproses', 'Pemilihan 25 sampel acak dan penelusuran bukti persetujuan.', 'Tiga sampel tanpa bukti persetujuan tepat waktu.', 'ineffective'],
            ['Analitik data pengecualian', 'Batas nilai dan pemisahan tugas pada sistem', 'Analisis data transaksi setahun untuk mendeteksi pengecualian dan duplikasi.', 'Ditemukan pola pengecualian yang perlu ditindaklanjuti.', 'observation'],
        ];
        $findings = [
            ['Persetujuan transaksi melewati batas kewenangan', 'high', 'Matriks kewenangan mensyaratkan persetujuan Direktur untuk nilai di atas batas.', 'Tiga dari 25 sampel disetujui pejabat satu tingkat di bawahnya.', 'Matriks kewenangan di sistem belum diperbarui.', 'Transaksi tidak terotorisasi dan potensi kerugian.', 'Perbarui matriks kewenangan di sistem dan lakukan rekonsiliasi bulanan.'],
            ['Dokumentasi pendukung tidak lengkap', 'medium', 'SOP mewajibkan dokumen pendukung lengkap sebelum pembayaran.', 'Sebagian dokumen diunggah setelah pembayaran.', 'Belum ada daftar periksa baku.', 'Menyulitkan penelusuran dan pemeriksaan eksternal.', 'Terapkan daftar periksa wajib di aplikasi sebelum pembayaran.'],
            ['Hak akses pengguna nonaktif belum dicabut', 'critical', 'Akses pegawai yang mutasi atau berhenti dicabut maksimal 3 hari kerja.', 'Ditemukan akun pegawai nonaktif masih aktif di sistem.', 'Belum ada integrasi data kepegawaian dengan sistem.', 'Risiko akses tidak sah atas data sensitif.', 'Lakukan review hak akses triwulanan dan otomatisasi pencabutan akses.'],
        ];
        foreach (self::AUDITS as $i => [$status, $title, $lead]) {
            $start = today()->subDays(150 - $i * 25);
            $audit = AuditEngagement::firstOrNew(['entity_id' => $entity->id, 'code' => $code.'-A'.($i + 1)]);
            if ($audit->exists) { continue; }
            $audit->forceFill([
                'title' => $title, 'scope' => 'Proses, kebijakan, sistem aplikasi, dan transaksi periode 12 bulan terakhir pada unit terkait ('.strtolower($sector).').',
                'objectives' => 'Menilai kecukupan rancangan dan efektivitas pengendalian internal serta kepatuhan terhadap kebijakan perusahaan (skenario demo).',
                'start_date' => $start, 'end_date' => $start->copy()->addDays(45),
                'lead_id' => $people[$lead]->id, 'reviewer_id' => $people['audit-reviewer']->id, 'created_by' => $people['audit-reviewer']->id,
                'status' => $status,
                'report_summary' => in_array($status, ['review', 'finalized', 'closed'], true) ? 'Pengendalian utama telah dirancang memadai; terdapat kelemahan pada pelaksanaan otorisasi dan pengelolaan dokumen yang perlu ditindaklanjuti (simulasi).' : null,
                'review_note' => in_array($status, ['finalized', 'closed'], true) ? 'Laporan disetujui untuk diterbitkan.' : null,
                'finalized_at' => in_array($status, ['finalized', 'closed'], true) ? $start->copy()->addDays(50) : null,
                'closed_at' => $status === 'closed' ? $start->copy()->addDays(100) : null,
                'revision' => $i + 1,
            ])->save();
            if ($status === 'draft') { continue; }
            foreach (array_slice($procedures, 0, $status === 'fieldwork' ? 2 : 3) as [$ptitle, $criteria, $procedure, $result, $conclusion]) {
                $record = AuditProcedure::firstOrCreate(['audit_engagement_id' => $audit->id, 'title' => $ptitle], [
                    'entity_id' => $entity->id, 'criteria' => $criteria, 'procedure' => $procedure, 'result' => $result,
                    'conclusion' => $conclusion, 'created_by' => $people[$lead]->id,
                ]);
                $this->evidence($entity, 'procedure', $record->id, $people[$lead]);
            }
            $plan = match ($status) {
                'fieldwork' => ['draft'], 'review' => ['draft', 'draft'],
                'finalized' => ['issued', 'submitted', 'returned'], default => ['closed', 'closed'],
            };
            foreach ($plan as $j => $findingStatus) {
                [$ftitle, $severity, $criteria, $condition, $cause, $impact, $recommendation] = $findings[($i + $j) % 3];
                $owner = $people[$j % 2 === 0 ? 'owner' : 'owner-2'];
                $acted = in_array($findingStatus, ['submitted', 'returned', 'closed'], true);
                $finding = AuditFinding::firstOrCreate(['audit_engagement_id' => $audit->id, 'title' => $ftitle], [
                    'entity_id' => $entity->id, 'criteria' => $criteria, 'condition' => $condition, 'cause' => $cause, 'impact' => $impact,
                    'recommendation' => $recommendation, 'severity' => $severity, 'responsible_id' => $owner->id,
                    'due_date' => $status === 'finalized' ? today()->addDays($j === 0 ? -6 : 10 + $j * 15) : $start->copy()->addDays(90),
                    'status' => $findingStatus,
                    'action_plan' => $acted ? 'Memperbarui prosedur, melatih pegawai terkait, dan memantau kepatuhan bulanan.' : null,
                    'completion_note' => $acted ? 'Prosedur baru telah berlaku dan bukti pelaksanaan dilampirkan.' : null,
                    'submitted_at' => $acted ? now()->subDays(5 + $j) : null, 'submitted_by' => $acted ? $owner->id : null,
                    'review_note' => $findingStatus === 'returned' ? 'Bukti belum menunjukkan pelaksanaan selama satu bulan penuh.' : ($findingStatus === 'closed' ? 'Tindak lanjut memadai; temuan ditutup.' : null),
                    'closed_by' => $findingStatus === 'closed' ? $people[$lead]->id : null,
                    'closed_at' => $findingStatus === 'closed' ? $start->copy()->addDays(95) : null,
                ]);
                if ($acted) { $this->evidence($entity, 'finding', $finding->id, $owner); }
            }
        }
    }

    /** Uraian penerapan contoh per rekomendasi (simulasi, bukan praktik aktual perusahaan). */
    private const GOVERNANCE_PRACTICE = [
        '1.1' => 'Tata tertib RUPS mengatur pemungutan suara terbuka dengan kartu suara dan pemungutan tertutup untuk agenda yang menyangkut orang. Perhitungan suara dilakukan Notaris dan Biro Administrasi Efek secara independen, termasuk suara melalui eASY.KSEI.',
        '1.2' => 'Seluruh anggota Direksi dan Dewan Komisaris hadir dalam RUPS Tahunan sebagaimana tercatat dalam daftar hadir dan risalah RUPS.',
        '1.3' => 'Ringkasan risalah RUPS diunggah ke situs web pada hari kerja kedua setelah RUPS dan tetap tersedia di arsip Hubungan Investor.',
        '2.1' => 'Kebijakan Komunikasi dengan Pemegang Saham dan Investor ditetapkan Direksi, mencakup paparan publik, analyst meeting, kunjungan investor, dan kanal pertanyaan.',
        '2.2' => 'Kebijakan komunikasi dimuat di menu Hubungan Investor situs web dan dirujuk dalam bab Tata Kelola laporan tahunan.',
        '3.1' => 'Jumlah anggota Dewan Komisaris ditetapkan RUPS dengan mempertimbangkan ukuran, kompleksitas usaha, dan kebutuhan pengawasan anak perusahaan.',
        '3.2' => 'Komposisi Dewan Komisaris disusun berdasarkan matriks keahlian (keuangan, hukum, industri, teknologi) yang dikaji Komite Nominasi dan Remunerasi.',
        '4.1' => 'Dewan Komisaris memiliki kebijakan self assessment tahunan dengan kriteria pelaksanaan tugas pengawasan, kehadiran rapat, dan kontribusi komite.',
        '4.2' => 'Kriteria dan prosedur self assessment Dewan Komisaris diungkapkan dalam laporan tahunan bab Tata Kelola.',
        '4.3' => 'Pedoman Kerja Dewan Komisaris memuat kewajiban mengundurkan diri bagi anggota yang terlibat kejahatan keuangan.',
        '4.4' => 'Komite Nominasi dan Remunerasi menyusun kebijakan suksesi Direksi yang mencakup talent pool, kriteria calon, dan jadwal kajian tahunan.',
        '5.1' => 'Jumlah anggota Direksi ditetapkan dengan mempertimbangkan struktur organisasi, lini usaha, dan efektivitas pengambilan keputusan.',
        '5.2' => 'Komposisi Direksi memperhatikan keberagaman keahlian operasional, keuangan, pemasaran, dan teknologi berdasarkan matriks kompetensi.',
        '5.3' => 'Direktur Keuangan berlatar belakang akuntansi dan memegang sertifikasi profesi akuntan.',
        '6.1' => 'Direksi memiliki kebijakan self assessment berbasis KPI kolegial dan individu yang dievaluasi setiap akhir tahun buku.',
        '6.2' => 'Kriteria dan prosedur penilaian kinerja Direksi diungkapkan dalam laporan tahunan.',
        '6.3' => 'Pedoman Kerja Direksi memuat kewajiban mengundurkan diri bagi anggota yang terlibat kejahatan keuangan.',
        '7.1' => 'Kebijakan Perdagangan Saham oleh Orang Dalam mengatur periode blackout, daftar orang dalam, dan kewajiban pelaporan transaksi.',
        '7.2' => 'Kebijakan Anti Korupsi dan Anti Fraud ditetapkan, didukung program pengendalian gratifikasi dan pernyataan kepatuhan tahunan.',
        '7.3' => 'Kebijakan pengadaan mengatur seleksi, evaluasi kinerja, dan program pengembangan kemampuan vendor.',
        '7.4' => 'Kebijakan pengelolaan pendanaan mengatur pemenuhan kewajiban dan hak kreditur, termasuk pemantauan covenant setiap triwulan.',
        '7.5' => 'Kebijakan Whistleblowing System menyediakan saluran pelaporan independen dengan perlindungan kerahasiaan pelapor.',
        '7.6' => 'Program insentif jangka panjang bagi Direksi dan karyawan kunci ditetapkan dalam kebijakan remunerasi dan disetujui RUPS.',
        '8.1' => 'Selain situs web, informasi disampaikan melalui media sosial resmi, siaran pers digital, dan paparan publik daring.',
        '8.2' => 'Laporan tahunan mengungkapkan pemilik manfaat akhir atas kepemilikan saham paling sedikit 5% berdasarkan data Biro Administrasi Efek.',
    ];

    /** Rekomendasi yang belum penuh diterapkan: [status 2025, status 2026, alasan, praktik alternatif, tindak lanjut]. */
    private const GOVERNANCE_GAPS = [
        'KAEF' => [
            '4.4' => ['partial', 'partial', 'Kebijakan suksesi Direksi masih mengikuti mekanisme penilaian calon oleh Pemegang Saham Seri A Dwiwarna, sehingga Komite Nominasi dan Remunerasi baru menyusun usulan talent pool internal.', 'Komite Nominasi dan Remunerasi menyampaikan daftar kandidat internal kepada pemegang saham setiap tahun.', 'Menyusun kebijakan suksesi Direksi tertulis'],
            '7.6' => ['not_applied', 'partial', 'Program insentif jangka panjang masih dalam kajian bersama pemegang saham pengendali dan menunggu kebijakan remunerasi grup.', 'Remunerasi variabel tahunan dikaitkan dengan KPI jangka menengah.', 'Mengajukan program LTI kepada RUPS Tahunan'],
            '8.2' => ['partial', 'applied', 'Pengungkapan pemilik manfaat akhir baru mencakup pemegang saham pengendali; data pemegang saham institusi di atas 5% masih ditelusuri.', 'Perseroan mengungkapkan bagan kepemilikan sampai pemegang saham pengendali.', null],
            '1.2' => ['applied', 'partial', 'Satu anggota Dewan Komisaris berhalangan hadir pada RUPS Tahunan karena penugasan negara yang tidak dapat diwakilkan.', 'Anggota yang berhalangan memberikan tanggapan tertulis atas agenda RUPS yang dibacakan dalam rapat.', null],
        ],
        'HEAL' => [
            '3.2' => ['partial', 'applied', 'Komposisi Dewan Komisaris belum memiliki anggota berlatar belakang teknologi informasi kesehatan.', 'Dewan Komisaris memanfaatkan tenaga ahli independen untuk kajian teknologi.', null],
            '4.4' => ['not_applied', 'partial', 'Fungsi nominasi baru dijalankan secara terpisah pada tahun berjalan sehingga kebijakan suksesi Direksi belum ditetapkan.', 'Direksi menyiapkan program pengembangan pimpinan rumah sakit sebagai talent pool.', 'Menetapkan kebijakan suksesi Direksi'],
            '7.6' => ['not_applied', 'not_applied', 'Perseroan belum memberikan insentif jangka panjang karena struktur remunerasi masih berbasis kinerja tahunan dan program kepemilikan saham karyawan belum disetujui.', 'Bonus kinerja tahunan dengan sebagian pembayaran ditangguhkan.', 'Kajian program MESOP bersama konsultan remunerasi'],
            '8.1' => ['partial', 'applied', 'Kanal keterbukaan informasi selain situs web baru berupa media sosial korporat dan belum mencakup paparan publik daring.', 'Paparan publik tahunan direkam dan diunggah ke situs web.', null],
        ],
        'PWON' => [
            '5.2' => ['partial', 'partial', 'Komposisi Direksi didominasi latar belakang properti dan keuangan; keahlian teknologi digital sedang dipenuhi melalui rekrutmen.', 'Direksi dibantu Chief Digital Officer non-direksi untuk inisiatif teknologi.', 'Usulan penambahan direktur berlatar teknologi'],
            '7.6' => ['not_applied', 'not_applied', 'Perseroan belum menerapkan program insentif jangka panjang dan memilih skema bonus tahunan yang disesuaikan dengan siklus proyek properti.', 'Bonus proyek dibayarkan bertahap sesuai serah terima unit.', 'Kajian skema LTI berbasis saham'],
            '1.2' => ['partial', 'applied', 'Dua anggota Dewan Komisaris berhalangan hadir dalam RUPS Tahunan karena alasan kesehatan dan perjalanan dinas.', 'Ketidakhadiran diumumkan dalam rapat beserta alasannya.', null],
            '4.3' => ['partial', 'applied', 'Ketentuan pengunduran diri anggota Dewan Komisaris bila terlibat kejahatan keuangan belum dimuat secara eksplisit dalam pedoman kerja.', 'Ketentuan anggaran dasar dan peraturan perundang-undangan tetap berlaku.', null],
        ],
    ];

    private function governance(Entity $entity, string $code, array $people): void
    {
        $functionPic = ['Sekretaris Perusahaan' => 'pic', 'Investor Relations' => 'pic-2', 'Komunikasi Korporat' => 'pic-2', 'Sekretariat Dewan Komisaris' => 'pic-2',
            'Komite Nominasi dan Remunerasi' => 'owner-2', 'Human Capital' => 'owner-2', 'Kepatuhan' => 'pic', 'Pengadaan' => 'owner', 'Keuangan dan Treasury' => 'owner'];
        $officer = $people['compliance'];
        $year = (int) now()->format('Y');
        $gaps = self::GOVERNANCE_GAPS[$code];
        // Tahun berjalan: 13 terverifikasi, 5 menunggu, 2 dikembalikan, sisanya masih disusun (2 belum dinilai, 1 tanpa PIC).
        $currentReview = ['1.1' => 'verified', '1.2' => 'submitted', '1.3' => 'verified', '2.1' => 'verified', '2.2' => 'verified', '3.1' => 'verified', '3.2' => 'submitted',
            '4.1' => 'verified', '4.2' => 'verified', '4.3' => 'returned', '4.4' => 'submitted', '5.1' => 'verified', '5.2' => 'submitted', '5.3' => 'verified',
            '6.1' => 'verified', '6.2' => 'verified', '6.3' => 'draft', '7.1' => 'verified', '7.2' => 'verified', '7.3' => 'draft', '7.4' => 'draft',
            '7.5' => 'verified', '7.6' => 'returned', '8.1' => 'submitted', '8.2' => 'draft'];
        foreach ([$year - 1 => 'approved', $year => 'open'] as $assessmentYear => $status) {
            $assessment = GovernanceAssessment::firstOrCreate(['entity_id' => $entity->id, 'year' => $assessmentYear], [
                'status' => $status, 'coordinator_id' => $officer->id, 'created_by' => $officer->id,
                'approval_note' => $status === 'approved' ? 'Disetujui dalam Rapat Direksi '.($assessmentYear + 1).' untuk dimuat dalam Laporan Tahunan '.$assessmentYear.' (simulasi).' : null,
                'approved_by' => $status === 'approved' ? $officer->id : null, 'approved_at' => $status === 'approved' ? now()->setDate($assessmentYear + 1, 3, 12) : null,
            ]);
            if ($assessment->wasRecentlyCreated) { $this->event($entity, 'governance_assessments', $assessment->id, 'governance.assessment_created', $officer, now()->setDate($assessmentYear, 11, 3)); }
            foreach (config('governance.recommendations') as $rec => $definition) {
                $gap = $gaps[$rec] ?? null;
                $itemStatus = $gap ? $gap[$status === 'approved' ? 0 : 1] : 'applied';
                $review = $status === 'approved' ? 'verified' : $currentReview[$rec];
                $submittedAt = $status === 'approved' ? now()->setDate($assessmentYear, 12, 2) : now()->subDays(24 - (int) str_replace('.', '', $rec) % 9);
                $reviewedAt = $submittedAt->copy()->addDays(5);
                if ($status === 'open' && in_array($rec, ['7.4', '8.2'], true)) { $itemStatus = 'not_assessed'; }
                $pic = $status === 'open' && $rec === '7.3' ? null : $people[$functionPic[$definition[3]]];
                $explained = in_array($itemStatus, ['partial', 'not_applied'], true);
                $item = GovernanceItem::firstOrCreate(['governance_assessment_id' => $assessment->id, 'code' => $rec], [
                    'entity_id' => $entity->id, 'status' => $itemStatus, 'pic_id' => $pic?->id,
                    'implementation' => $itemStatus === 'not_assessed' ? null : ($itemStatus === 'not_applied' ? null : self::GOVERNANCE_PRACTICE[$rec]),
                    'evidence_note' => $itemStatus === 'applied' ? 'Dokumen '.$definition[3].' '.$code.'-GCG-'.str_replace('.', '', $rec).'/'.$assessmentYear.' (simulasi).' : null,
                    'explanation' => $explained ? $gap[2] : null, 'alternative_practice' => $explained ? $gap[3] : null,
                    'target_date' => $explained && $gap[4] ? now()->setDate($assessmentYear + 1, 12, 31)->toDateString() : null,
                    'review_status' => $review,
                    'submitted_at' => in_array($review, ['submitted', 'verified', 'returned'], true) ? $submittedAt : null,
                    'submitted_by' => in_array($review, ['submitted', 'verified', 'returned'], true) ? $pic?->id : null,
                    'review_note' => match ($review) { 'verified' => 'Bukti dan uraian sesuai dengan dokumen yang berlaku.', 'returned' => 'Lengkapi nomor dan tanggal pengesahan kebijakan serta perbarui alasan sesuai kondisi tahun berjalan.', default => null },
                    'reviewed_by' => in_array($review, ['verified', 'returned'], true) ? $officer->id : null,
                    'reviewed_at' => in_array($review, ['verified', 'returned'], true) ? $reviewedAt : null,
                ]);
                if (! $item->wasRecentlyCreated) { continue; }
                if ($pic && $itemStatus === 'applied' && (int) str_replace('.', '', $rec) % 3 !== 0) { $this->evidence($entity, 'governance', $item->id, $pic); }
                if ($pic && $review !== 'draft') { $this->event($entity, 'governance_items', $item->id, 'governance.item_submit', $pic, $item->submitted_at); }
                if (in_array($review, ['verified', 'returned'], true)) { $this->event($entity, 'governance_items', $item->id, 'governance.item_'.($review === 'verified' ? 'verify' : 'return'), $officer, $item->reviewed_at); }
                if ($explained && $gap[4]) {
                    $done = $status === 'approved';
                    GovernanceAction::firstOrCreate(['governance_item_id' => $item->id, 'title' => $gap[4]], [
                        'entity_id' => $entity->id, 'pic_id' => $pic->id, 'created_by' => $officer->id,
                        'due_date' => $done ? now()->setDate($assessmentYear, 10, 31) : today()->subDays(12),
                        'status' => $done ? 'done' : 'open', 'completed_at' => $done ? now()->setDate($assessmentYear, 10, 20) : null,
                        'completion_note' => $done ? 'Draf kebijakan disampaikan kepada Direksi; pengesahan dijadwalkan tahun berikutnya.' : null,
                    ]);
                    if (! $done) {
                        GovernanceAction::firstOrCreate(['governance_item_id' => $item->id, 'title' => 'Sosialisasi rencana penerapan kepada Dewan Komisaris'], [
                            'entity_id' => $entity->id, 'pic_id' => $pic->id, 'created_by' => $officer->id, 'due_date' => today()->addDays(45), 'status' => 'open',
                        ]);
                    }
                }
            }
        }
    }

    /** Kuesioner contoh: [templat, tahun relatif, status, responden, jumlah yang mengisi, pengecualian D&O/kode etik]. */
    private function questionnaires(Entity $entity, string $code, array $people): void
    {
        $board = ['komut', 'komisaris', 'komisaris-ind', 'dirut', 'dirkeu'];
        $staff = ['pic', 'pic-2', 'owner', 'owner-2', 'auditor', 'risk-reviewer'];
        $year = (int) now()->format('Y');
        $plan = [
            ['board_eval', -1, 'closed', $board, 5], ['board_eval', 0, 'closed', $board, 5],
            ['director_eval', -1, 'closed', $board, 5], ['director_eval', 0, 'open', $board, 3],
            ['committee_eval', 0, 'closed', ['komisaris-ind', 'komisaris', 'audit-reviewer', 'risk-reviewer'], 4],
            ['dno', 0, 'open', $board, 4], ['coc', -1, 'closed', $staff, 6], ['coc', 0, 'open', $staff, 2],
        ];
        $notes = [
            'ns1' => 'Memiliki 125.000 saham Perseroan (0,002%) secara langsung, telah dilaporkan kepada OJK.',
            'nr1' => 'Komisaris pada satu anak perusahaan dan anggota komite pada asosiasi industri; masih dalam batas ketentuan.',
            'na1' => 'Saudara kandung bekerja sebagai manajer di anak perusahaan, tidak dalam garis pelaporan langsung.',
            'nb1' => 'Pihak terafiliasi menjadi pemasok jasa kebersihan dengan nilai kontrak di bawah ambang transaksi material; telah diungkapkan kepada Komite Audit.',
            'cg1' => 'Menerima bingkisan hari raya dari vendor senilai di bawah batas wajar; dilaporkan susulan kepada fungsi kepatuhan.',
        ];
        foreach ($plan as $i => [$template, $offset, $status, $respondents, $filled]) {
            $definition = config('questionnaires.templates')[$template];
            $campaignYear = $year + $offset;
            $campaign = QuestionnaireCampaign::firstOrNew(['entity_id' => $entity->id, 'template' => $template, 'year' => $campaignYear]);
            if ($campaign->exists) { continue; }
            $due = $offset < 0 ? now()->setDate($campaignYear, 12, 15) : ($status === 'open' ? today()->addDays(10 + $i) : today()->subDays(20));
            $campaign->fill(['title' => $definition['title'], 'due_date' => $due, 'status' => $status, 'anonymous' => $definition['anonymous'],
                'instructions' => 'Mohon isi sesuai kondisi tahun buku '.$campaignYear.'. Data simulasi untuk demonstrasi.', 'created_by' => $people['compliance']->id,
                'closed_by' => $status === 'closed' ? $people['compliance']->id : null, 'closed_at' => $status === 'closed' ? $due->copy()->addDays(3) : null])->save();
            $this->event($entity, 'questionnaire_campaigns', $campaign->id, 'questionnaire.opened', $people['compliance'], $due->copy()->subDays(21));
            foreach ($respondents as $n => $person) {
                $done = $n < $filled;
                $assignment = QuestionnaireAssignment::create(['entity_id' => $entity->id, 'questionnaire_campaign_id' => $campaign->id, 'user_id' => $people[$person]->id,
                    'status' => $done ? 'submitted' : 'pending', 'submitted_at' => $done ? $due->copy()->subDays(5)->startOfDay() : null]);
                if (! $done) { continue; }
                $answers = []; $exceptions = 0;
                foreach ($campaign->questions() as $key => [, $type, , $flag]) {
                    $seed = crc32($code.$template.$campaignYear.$key.$n);
                    if ($type === 'scale') {
                        // Tahun berjalan sedikit lebih baik; rapat dan komite lebih rendah agar ada area perbaikan.
                        $base = (str_starts_with($key, 'bm') || str_starts_with($key, 'do')) ? 3 : 4;
                        $answers[$key] = ['value' => max(2, min(5, $base + ($seed % 3) - 1 + ($offset === 0 && $seed % 4 === 0 ? 1 : 0)))];
                    } elseif ($type === 'yesno') {
                        $exception = isset($notes[$key]) && ($seed % 11 === 0 || ($key === 'ns1' && $n === 1) || ($key === 'nr1' && $n === 3) || ($key === 'cg1' && $n === 2 && $offset < 0));
                        $value = $exception ? $flag : ($flag === 'yes' ? 'no' : 'yes');
                        $answers[$key] = ['value' => $value] + ($exception ? ['note' => $notes[$key]] : []);
                        if ($exception) { $exceptions++; }
                    } else {
                        $texts = ['Bahan rapat perlu diterima lebih awal, paling lambat lima hari kerja sebelum rapat.', 'Perlu sesi khusus pembahasan risiko teknologi dan keamanan siber.', 'Tindak lanjut keputusan rapat sebaiknya dipantau dalam satu daftar yang dapat diakses semua anggota.', 'Program orientasi anggota baru perlu diperkuat.', ''];
                        $answers[$key] = ['value' => $texts[$seed % count($texts)]];
                    }
                }
                QuestionnaireResponse::create(['entity_id' => $entity->id, 'questionnaire_campaign_id' => $campaign->id,
                    'questionnaire_assignment_id' => $definition['anonymous'] ? null : $assignment->id, 'answers' => $answers, 'exceptions' => $exceptions]);
            }
            if ($status === 'closed') { $this->event($entity, 'questionnaire_campaigns', $campaign->id, 'questionnaire.closed', $people['compliance'], $campaign->closed_at); }
        }
    }

    private function event(Entity $entity, string $type, int $id, string $action, User $actor, $at): void
    {
        AuditEvent::firstOrCreate(['entity_id' => $entity->id, 'subject_type' => $type, 'subject_id' => $id, 'action' => $action, 'actor_id' => $actor->id],
            ['before' => null, 'after' => ['demo' => true], 'created_at' => $at]);
    }

    private function account(string $email, string $name): User
    {
        $user = User::firstOrNew(['email' => $email]);
        if ($user->exists && ! $user->is_demo_guest) { throw new RuntimeException('Akun demo berbenturan dengan akun non-demo.'); }
        if (! $user->exists) { $user->forceFill(['name' => $name, 'password' => Str::random(64), 'is_demo_guest' => true])->save(); }

        return $user;
    }

    private function evidence(Entity $entity, string $type, int $id, User $actor): void
    {
        $path = 'demo/'.$entity->id.'/'.$type.'-'.$id.'.pdf';
        $bytes = $this->pdf();
        if (! Storage::disk('local')->exists($path)) { Storage::disk('local')->put($path, $bytes); }
        EvidenceFile::firstOrCreate(['entity_id' => $entity->id, 'subject_type' => $type, 'subject_id' => $id], [
            'path' => $path, 'original_name' => 'SIMULASI-bukti-'.$type.'-'.$id.'.pdf',
            'mime' => 'application/pdf', 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'uploaded_by' => $actor->id,
        ]);
    }

    private function pdf(): string
    {
        $stream = "BT /F1 18 Tf 50 780 Td (PRANATA - DATA SIMULASI) Tj 0 -35 Td /F1 11 Tf (Bukti sintetis untuk demonstrasi. Bukan dokumen perusahaan.) Tj ET";
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream"];
        $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $i => $object) { $offsets[] = strlen($pdf); $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) { $pdf .= sprintf("%010d 00000 n \n", $offset); }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}

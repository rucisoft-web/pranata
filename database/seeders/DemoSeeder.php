<?php
namespace Database\Seeders;

use App\Models\AuditEngagement;
use App\Models\AuditEvent;
use App\Models\AuditFinding;
use App\Models\AuditProcedure;
use App\Models\Entity;
use App\Models\EvidenceFile;
use App\Models\Obligation;
use App\Models\ObligationOccurrence;
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

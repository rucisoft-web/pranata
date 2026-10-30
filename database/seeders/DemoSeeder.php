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
    public function run(): void
    {
        DB::transaction(function () {
            $guest = $this->account('guest@demo.pranata.example', 'Tamu PRANATA');
            $profiles = [
                ['KAEF', 'PT Kimia Farma (Persero) Tbk', true, 'Farmasi dan layanan kesehatan', [
                    ['Ketergantungan pasokan bahan baku', 'Operasional', 'Rantai pasok farmasi', 4, 4],
                    ['Keterlambatan penerimaan piutang', 'Keuangan', 'Pengelolaan piutang', 3, 4],
                    ['Ketidakkonsistenan dokumentasi mutu', 'Hukum dan Kepatuhan', 'Penjaminan mutu', 3, 5],
                    ['Gangguan aplikasi layanan apotek', 'Teknologi dan Siber', 'Operasional teknologi', 2, 4],
                    ['Keterlambatan pelaporan internal', 'Reputasi', 'Pelaporan korporasi', 2, 2],
                ]],
                ['HEAL', 'PT Medikaloka Hermina Tbk', false, 'Jaringan rumah sakit', [
                    ['Gangguan kesinambungan layanan rumah sakit', 'Operasional', 'Pelayanan rumah sakit', 3, 5],
                    ['Keterlambatan penagihan layanan', 'Keuangan', 'Siklus pendapatan', 3, 4],
                    ['Akses tidak sesuai pada data pasien', 'Teknologi dan Siber', 'Keamanan informasi', 4, 5],
                    ['Ketidaklengkapan bukti pemeliharaan alat', 'Hukum dan Kepatuhan', 'Pemeliharaan peralatan', 2, 4],
                    ['Keterlambatan penanganan umpan balik', 'Reputasi', 'Pengalaman pasien', 2, 2],
                ]],
                ['PWON', 'PT Pakuwon Jati Tbk', false, 'Properti, pusat perbelanjaan, dan hospitality', [
                    ['Penurunan tingkat hunian area sewa', 'Strategis', 'Pengelolaan tenant', 3, 4],
                    ['Keterlambatan proyek pengembangan', 'Operasional', 'Manajemen proyek', 4, 4],
                    ['Gangguan sistem keselamatan gedung', 'Operasional', 'Manajemen fasilitas', 4, 5],
                    ['Keterlambatan penyelesaian dokumen proyek', 'Hukum dan Kepatuhan', 'Administrasi proyek', 2, 4],
                    ['Ketidakakuratan jadwal pelaporan internal', 'Keuangan', 'Pelaporan pengelolaan aset', 2, 2],
                ]],
            ];
            foreach ($profiles as [$code, $name, $bumn, $sector, $risks]) {
                $entity = Entity::firstOrNew(['code' => 'DEMO-'.$code]);
                if ($entity->exists && ! $entity->is_demo) { throw new RuntimeException('Kode perusahaan demo berbenturan dengan data non-demo.'); }
                if (! $entity->exists) { $entity->fill(['name' => $name.' — DEMO', 'is_bumn' => $bumn, 'is_public_company' => true, 'is_listed' => true, 'is_demo' => true])->save(); }
                $people = [];
                foreach (['owner' => 'risk_owner', 'risk-reviewer' => 'risk_manager', 'compliance' => 'compliance_officer', 'pic' => 'compliance_owner', 'auditor' => 'auditor', 'audit-reviewer' => 'audit_manager'] as $suffix => $role) {
                    $people[$suffix] = $this->account(strtolower($code).'.'.$suffix.'@demo.pranata.example', strtoupper($code).' · '.str_replace('-', ' ', $suffix).' (Simulasi)');
                    $people[$suffix]->entities()->syncWithoutDetaching([$entity->id => ['role' => $role]]);
                }
                $guest->entities()->syncWithoutDetaching([$entity->id => ['role' => 'viewer']]);
                foreach ($risks as $i => [$title, $category, $process, $likelihood, $impact]) {
                    $status = ['draft', 'submitted', 'approved', 'approved', 'draft'][$i];
                    $risk = Risk::firstOrCreate(['entity_id' => $entity->id, 'code' => $code.'-SIM-R'.($i + 1)], [
                        'owner_id' => $people['owner']->id, 'title' => '[SIMULASI] '.$title, 'category' => $category,
                        'objective' => 'Skenario menjaga kesinambungan dan kualitas usaha: '.$sector,
                        'process' => $process, 'cause' => 'Skenario ketergantungan proses dan pengendalian yang perlu diperkuat.',
                        'event' => 'Skenario gangguan pada proses '.$process.'.', 'impact_description' => 'Potensi keterlambatan layanan, biaya tambahan, atau ketidakpatuhan dalam skenario demo.',
                        'likelihood' => $likelihood, 'impact' => $impact, 'assessment_reason' => 'Nilai ilustratif untuk demonstrasi matriks; bukan penilaian terhadap perusahaan yang disebut.',
                        'response' => 'reduce', 'treatment_plan' => 'Simulasi penguatan kontrol, pemantauan indikator, dan penyiapan prosedur alternatif.',
                        'review_date' => $i === 1 ? today()->subDays(5) : today()->addDays(15 + $i * 7),
                        'status' => $status, 'approved_by' => $status === 'approved' ? $people['risk-reviewer']->id : null,
                        'approved_at' => $status === 'approved' ? now()->subDays(2) : null,
                    ]);
                    AuditEvent::firstOrCreate(['entity_id' => $entity->id, 'subject_type' => 'risk', 'subject_id' => $risk->id, 'action' => 'risk.demo_seeded'], ['actor_id' => $people['owner']->id, 'after' => $risk->toArray(), 'created_at' => now()]);
                }
                $source = RegulatorySource::firstOrCreate(['entity_id' => $entity->id, 'code' => $code.'-SIM-SRC'], [
                    'title' => 'Kebijakan tata kelola dan kepatuhan — simulasi', 'issuer' => 'Tim Demo PRANATA',
                    'reference' => 'DEMO-KEP-001', 'version' => 'SIM-1', 'effective_date' => today()->startOfYear(), 'created_by' => $people['compliance']->id,
                ]);
                foreach (['Penelaahan kelengkapan pelaporan korporasi', 'Peninjauan konflik kepentingan', 'Evaluasi dokumen kepatuhan operasional'] as $i => $title) {
                    $obligation = Obligation::firstOrCreate(['entity_id' => $entity->id, 'code' => $code.'-SIM-K'.($i + 1)], [
                        'regulatory_source_id' => $source->id, 'title' => '[SIMULASI] '.$title,
                        'description' => 'Skenario internal: lengkapi daftar periksa, hasil penelaahan, dan dokumen persetujuan. Bukan kutipan atau penetapan kewajiban hukum aktual.',
                        'clause' => 'Klausul simulasi '.($i + 1), 'frequency' => $i === 1 ? 'annual' : 'monthly',
                        'criticality' => $i === 0 ? 'critical' : 'high', 'applicability' => 'applies',
                        'applicability_reason' => 'Berlaku hanya pada skenario demonstrasi; perlu penelaahan Legal/Compliance untuk operasional nyata.',
                        'responsible_id' => $people['pic']->id, 'created_by' => $people['compliance']->id,
                    ]);
                    foreach (['A', 'B'] as $j => $period) {
                        $status = ['open', 'submitted', 'fulfilled', 'returned', 'fulfilled', 'open'][$i * 2 + $j];
                        $occurrence = ObligationOccurrence::firstOrCreate(['obligation_id' => $obligation->id, 'period' => 'SIM-'.$period], [
                            'entity_id' => $entity->id, 'obligation_snapshot' => $obligation->load('source')->toArray(),
                            'responsible_id' => $people['pic']->id, 'due_date' => $j === 0 ? today()->subDays(7) : today()->addDays(20),
                            'status' => $status, 'submission_note' => $status !== 'open' ? 'Dokumen ilustrasi telah disiapkan untuk penelaahan.' : null,
                            'submitted_at' => $status !== 'open' ? now()->subDays(3) : null, 'submitted_by' => $status !== 'open' ? $people['pic']->id : null,
                            'reviewed_by' => in_array($status, ['fulfilled', 'returned']) ? $people['compliance']->id : null,
                            'review_note' => $status === 'fulfilled' ? 'Simulasi bukti diperiksa dan dinilai memadai.' : ($status === 'returned' ? 'Simulasi: lengkapi lampiran persetujuan.' : null),
                            'fulfilled_at' => $status === 'fulfilled' ? now()->subDay() : null,
                        ]);
                        if ($status !== 'open') { $this->evidence($entity, 'compliance', $occurrence->id, $people['pic']); }
                    }
                }
                foreach (['fieldwork', 'finalized'] as $i => $status) {
                    $audit = AuditEngagement::firstOrCreate(['entity_id' => $entity->id, 'code' => $code.'-SIM-A'.($i + 1)], [
                        'title' => '[SIMULASI] Audit '.($i === 0 ? 'pengendalian proses operasional' : 'tata kelola dokumen dan tindak lanjut'),
                        'scope' => 'Ruang lingkup ilustratif: dokumentasi persetujuan, pelaksanaan kontrol, dan bukti tindak lanjut.',
                        'objectives' => 'Mendemonstrasikan alur assurance pada sektor '.$sector.'. Bukan hasil audit aktual.',
                        'start_date' => today()->subDays(20), 'end_date' => today()->addDays(10),
                        'lead_id' => $people['auditor']->id, 'reviewer_id' => $people['audit-reviewer']->id, 'created_by' => $people['audit-reviewer']->id,
                        'status' => $status, 'report_summary' => $status === 'finalized' ? 'Ringkasan simulasi: terdapat peluang peningkatan dokumentasi dan pemantauan tindakan.' : null,
                        'finalized_at' => $status === 'finalized' ? now()->subDays(3) : null,
                    ]);
                    $procedure = AuditProcedure::firstOrCreate(['audit_engagement_id' => $audit->id, 'title' => '[SIMULASI] Pemeriksaan sampel dokumentasi'], [
                        'entity_id' => $entity->id, 'criteria' => 'Prosedur internal simulasi mengharuskan dokumentasi penelaahan.',
                        'procedure' => 'Skenario memilih 10 dokumen contoh dan menelusuri jejak persetujuan.',
                        'result' => 'Hasil sintetis: dua dokumen memerlukan penguatan bukti persetujuan.',
                        'conclusion' => 'observation', 'created_by' => $people['auditor']->id,
                    ]);
                    $this->evidence($entity, 'procedure', $procedure->id, $people['auditor']);
                    foreach (['Dokumentasi penelaahan belum seragam', 'Pemantauan tindakan perlu diperkuat'] as $j => $title) {
                        $findingStatus = $status === 'fieldwork' ? 'draft' : ($j === 0 ? 'submitted' : 'closed');
                        $finding = AuditFinding::firstOrCreate(['audit_engagement_id' => $audit->id, 'title' => '[SIMULASI] '.$title], [
                            'entity_id' => $entity->id, 'criteria' => 'Persyaratan internal skenario demo.',
                            'condition' => 'Kondisi sintetis yang dibuat khusus untuk demonstrasi aplikasi.',
                            'cause' => 'Skenario pembagian tanggung jawab yang belum rinci.', 'impact' => 'Potensi keterlambatan dan berkurangnya keterlacakan.',
                            'recommendation' => 'Seragamkan daftar periksa dan tetapkan verifikasi berjenjang.', 'severity' => $j === 0 ? 'high' : 'medium',
                            'responsible_id' => $people['owner']->id, 'due_date' => $j === 0 ? today()->subDays(2) : today()->addDays(14),
                            'status' => $findingStatus, 'action_plan' => $status === 'finalized' ? 'Simulasi standardisasi daftar periksa dan sosialisasi PIC.' : null,
                            'completion_note' => $status === 'finalized' ? 'Bukti contoh telah dilampirkan.' : null,
                            'submitted_at' => $status === 'finalized' ? now()->subDay() : null,
                            'submitted_by' => $status === 'finalized' ? $people['owner']->id : null,
                            'closed_by' => $findingStatus === 'closed' ? $people['auditor']->id : null,
                            'closed_at' => $findingStatus === 'closed' ? now() : null,
                            'review_note' => $findingStatus === 'closed' ? 'Simulasi verifikasi tindakan selesai.' : null,
                        ]);
                        if ($status === 'finalized') { $this->evidence($entity, 'finding', $finding->id, $people['owner']); }
                    }
                }
            }
        });
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

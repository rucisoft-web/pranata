<?php
namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditProcedure;
use App\Models\Entity;
use App\Models\EvidenceFile;
use App\Models\Obligation;
use App\Models\ObligationOccurrence;
use App\Models\RegulatorySource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    use RefreshDatabase;
    private $entity; private $officer; private $pic; private $lead; private $reviewer; private $source; private $obligation; private $period; private $audit;

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local');
        $this->entity = Entity::create(['code' => 'CORP', 'name' => 'PT Contoh Tbk']);
        $this->officer = $this->member($this->entity, 'compliance_officer');
        $this->pic = $this->member($this->entity, 'compliance_owner');
        $this->lead = $this->member($this->entity, 'auditor');
        $this->reviewer = $this->member($this->entity, 'audit_manager');
        $this->source = RegulatorySource::create(['entity_id' => $this->entity->id, 'code' => 'SRC-1', 'title' => 'Kebijakan pengendalian', 'issuer' => 'Perusahaan', 'reference' => 'SK-1', 'version' => '1', 'effective_date' => '2026-01-01', 'created_by' => $this->officer->id]);
        $this->obligation = Obligation::create($this->obligationData() + ['entity_id' => $this->entity->id, 'created_by' => $this->officer->id]);
        $this->period = ObligationOccurrence::create(['entity_id' => $this->entity->id, 'obligation_id' => $this->obligation->id, 'obligation_snapshot' => $this->obligation->load('source')->toArray(), 'period' => '2026-11', 'due_date' => today()->subDays(10), 'responsible_id' => $this->pic->id]);
        $this->audit = AuditEngagement::create(['entity_id' => $this->entity->id, 'code' => 'AUD-1', 'title' => 'Audit pengendalian', 'scope' => 'Proses operasional', 'objectives' => 'Menilai efektivitas kontrol', 'start_date' => today(), 'end_date' => today()->addDays(10), 'lead_id' => $this->lead->id, 'reviewer_id' => $this->reviewer->id, 'created_by' => $this->reviewer->id]);
    }

    private function obligationData(): array
    {
        return ['code' => 'OBL-1', 'regulatory_source_id' => $this->source->id, 'title' => 'Penelaahan berkala', 'description' => 'Lengkapi bukti penelaahan dan persetujuan', 'clause' => 'Pasal 1', 'frequency' => 'monthly', 'criticality' => 'high', 'applicability' => 'applies', 'applicability_reason' => 'Berlaku untuk proses perusahaan ini', 'responsible_id' => $this->pic->id];
    }

    private function upload(string $type, int $id): void
    {
        $this->post('/evidence/'.$type.'/'.$id, ['file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')])->assertRedirect();
    }

    private function procedure(): void
    {
        AuditProcedure::create(['entity_id' => $this->entity->id, 'audit_engagement_id' => $this->audit->id, 'title' => 'Uji sampel', 'criteria' => 'Kebijakan berlaku', 'procedure' => 'Pemeriksaan sepuluh sampel', 'result' => 'Bukti belum lengkap', 'conclusion' => 'observation', 'created_by' => $this->lead->id]);
    }

    private function finding(string $status = 'issued'): AuditFinding
    {
        return AuditFinding::create(['entity_id' => $this->entity->id, 'audit_engagement_id' => $this->audit->id, 'title' => 'Bukti penelaahan belum lengkap', 'criteria' => 'Kebijakan pengendalian', 'condition' => 'Bukti tidak lengkap', 'cause' => 'PIC belum ditentukan', 'impact' => 'Sulit menelusuri keputusan', 'recommendation' => 'Tetapkan PIC dan daftar periksa', 'severity' => 'high', 'responsible_id' => $this->pic->id, 'due_date' => today(), 'status' => $status]);
    }

    public function test_compliance_crud_and_snapshot_do_not_rewrite_old_period(): void
    {
        $this->signIn($this->officer)->get('/obligations')->assertOk();
        $this->get('/obligations/create')->assertOk();
        $this->get('/obligations/'.$this->obligation->id.'/edit')->assertOk();
        $this->put('/obligations/'.$this->obligation->id, array_merge($this->obligationData(), ['title' => 'Definisi baru', 'revision' => 1]))->assertRedirect();
        $this->assertSame('Penelaahan berkala', $this->period->fresh()->obligation_snapshot['title']);
        $this->post('/obligations/'.$this->obligation->id.'/periods', ['period' => '2026-12', 'due_date' => '2026-12-31', 'revision' => 2])->assertRedirect();
        $this->assertDatabaseHas('obligation_occurrences', ['period' => '2026-12']);
        $this->get('/compliance')->assertOk()->assertSee('Lewat tenggat');
        $this->get('/compliance/'.$this->period->id)->assertOk();
    }

    public function test_submission_needs_evidence_and_verification_is_independent(): void
    {
        $url = '/compliance/'.$this->period->id;
        $this->signIn($this->pic)->post($url.'/submit', ['revision' => 1, 'note' => 'Bukti telah diperiksa'])->assertUnprocessable();
        $this->upload('compliance', $this->period->id);
        $this->post($url.'/submit', ['revision' => 1, 'note' => 'Bukti pemenuhan terlampir'])->assertRedirect();
        $this->post($url.'/verify', ['revision' => 2, 'note' => 'Setujui catatan sendiri'])->assertForbidden();
        $this->post('/evidence/compliance/'.$this->period->id, ['file' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf')])->assertConflict();
        $this->signIn($this->officer)->post($url.'/verify', ['revision' => 2, 'note' => 'Bukti cukup dan telah diverifikasi'])->assertRedirect();
        $this->assertDatabaseHas('obligation_occurrences', ['id' => $this->period->id, 'status' => 'fulfilled', 'reviewed_by' => $this->officer->id]);
        $this->assertTrue($this->period->fresh()->fulfilled_at->gt($this->period->due_date));
        $this->assertDatabaseHas('audit_events', ['action' => 'compliance.verify']);
    }

    public function test_return_and_stale_revision_are_enforced(): void
    {
        $this->period->update(['status' => 'submitted', 'submitted_by' => $this->pic->id]);
        $this->signIn($this->officer)->post('/compliance/'.$this->period->id.'/return', ['revision' => 1, 'note' => 'Lengkapi bukti persetujuan'])->assertRedirect();
        $this->assertSame('returned', $this->period->fresh()->status);
        $this->post('/compliance/'.$this->period->id.'/verify', ['revision' => 1, 'note' => 'Permintaan dengan revisi lama'])->assertConflict();
    }

    public function test_outsider_and_viewer_cannot_write_compliance_or_download_evidence(): void
    {
        $this->signIn($this->pic); $this->upload('compliance', $this->period->id); $file = EvidenceFile::firstOrFail();
        $viewer = $this->member($this->entity, 'viewer');
        $this->signIn($viewer)->post('/obligations', $this->obligationData())->assertForbidden();
        $this->post('/evidence/compliance/'.$this->period->id, ['file' => UploadedFile::fake()->create('x.pdf', 1, 'application/pdf')])->assertForbidden();
        $other = Entity::create(['code' => 'OTHER', 'name' => 'Other']); $outsider = $this->member($other, 'admin');
        $this->signIn($outsider)->withSession(['entity_id' => $other->id])->get('/compliance/'.$this->period->id)->assertNotFound();
        $this->get('/evidence/'.$file->id)->assertNotFound();
        $this->get('/obligations/'.$this->obligation->id)->assertNotFound();
    }

    public function test_source_and_pic_must_belong_to_current_entity(): void
    {
        $other = Entity::create(['code' => 'OTHER', 'name' => 'Other']); $outsider = $this->member($other, 'compliance_owner');
        $this->signIn($this->officer)->post('/obligations', array_merge($this->obligationData(), ['code' => 'NEW', 'responsible_id' => $outsider->id]))->assertSessionHasErrors('responsible_id');
        $this->obligation->update(['applicability' => 'not_applies']);
        $this->post('/obligations/'.$this->obligation->id.'/periods', ['period' => 'NEW', 'due_date' => '2026-12-31', 'revision' => 1])->assertConflict();
    }

    public function test_evidence_download_is_private_and_checks_integrity(): void
    {
        $this->signIn($this->pic); $this->upload('compliance', $this->period->id); $file = EvidenceFile::firstOrFail();
        $this->get('/evidence/'.$file->id)->assertOk()->assertDownload('bukti.pdf');
        Storage::disk('local')->put($file->path, 'tampered bytes');
        $this->get('/evidence/'.$file->id)->assertConflict();
        $this->post('/evidence/compliance/'.$this->period->id, ['file' => UploadedFile::fake()->create('payload.html', 1, 'text/html')])->assertSessionHasErrors('file');
    }

    public function test_audit_report_maker_checker_and_finding_publication(): void
    {
        $this->signIn($this->lead)->get('/audits')->assertOk();
        $this->post('/audits/'.$this->audit->id.'/start', ['revision' => 1])->assertRedirect();
        $this->post('/audits/'.$this->audit->id.'/submit', ['revision' => 2, 'note' => 'Ringkasan audit operasional'])->assertUnprocessable();
        $this->post('/audits/'.$this->audit->id.'/procedures', ['title' => 'Uji sampel', 'criteria' => 'Kriteria penelaahan', 'procedure' => 'Pilih sampel dan telusuri', 'result' => 'Bukti perlu diperkuat', 'conclusion' => 'observation'])->assertRedirect();
        $finding = $this->finding('draft');
        $this->post('/audits/'.$this->audit->id.'/submit', ['revision' => 2, 'note' => 'Ringkasan audit operasional'])->assertRedirect();
        $this->post('/audits/'.$this->audit->id.'/finalize', ['revision' => 3, 'note' => 'Persetujuan oleh auditor sendiri'])->assertForbidden();
        $this->signIn($this->reviewer)->post('/audits/'.$this->audit->id.'/finalize', ['revision' => 3, 'note' => 'Laporan telah ditelaah dan disetujui'])->assertRedirect();
        $this->assertSame('issued', $finding->fresh()->status);
        $this->get('/audits/'.$this->audit->id)->assertOk()->assertSee('Kertas kerja audit');
    }

    public function test_auditor_sees_only_assigned_audits_and_auditee_only_issued_finding(): void
    {
        $finding = $this->finding('draft'); $colleague = $this->member($this->entity, 'auditor');
        $this->signIn($colleague)->get('/audits/'.$this->audit->id)->assertNotFound();
        $this->signIn($this->pic)->get('/audits/'.$this->audit->id)->assertNotFound();
        $this->get('/audit-findings/'.$finding->id)->assertNotFound();
        $finding->update(['status' => 'issued']);
        $this->get('/audit-findings/'.$finding->id)->assertOk();
        $this->get('/audit-actions')->assertOk()->assertSee($finding->title);
    }

    public function test_finding_submission_verification_and_engagement_closure(): void
    {
        $this->audit->update(['status' => 'finalized']); $finding = $this->finding();
        $this->signIn($this->reviewer)->post('/audits/'.$this->audit->id.'/close', ['revision' => 1, 'note' => 'Semua tindakan telah selesai'])->assertConflict();
        $this->signIn($this->pic); $this->upload('finding', $finding->id);
        $this->post('/audit-findings/'.$finding->id.'/submit', ['revision' => 1, 'action_plan' => 'Menetapkan daftar periksa dan PIC', 'note' => 'Pelaksanaan tindakan telah selesai'])->assertRedirect();
        $this->post('/audit-findings/'.$finding->id.'/close', ['revision' => 2, 'note' => 'Ditutup sendiri oleh PIC'])->assertForbidden();
        $this->signIn($this->lead)->post('/audit-findings/'.$finding->id.'/close', ['revision' => 2, 'note' => 'Bukti diuji dan tindakan dinilai memadai'])->assertRedirect();
        $this->assertSame('closed', $finding->fresh()->status);
        $this->signIn($this->reviewer)->post('/audits/'.$this->audit->id.'/close', ['revision' => 1, 'note' => 'Semua temuan telah diverifikasi selesai'])->assertRedirect();
        $this->assertSame('closed', $this->audit->fresh()->status);
    }

    public function test_audit_planning_validates_roles_and_independence(): void
    {
        $this->signIn($this->reviewer)->get('/audits/create')->assertOk();
        $data = ['code' => 'AUD-2', 'title' => 'Audit kedua', 'scope' => 'Proses operasional', 'objectives' => 'Menilai pengendalian', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'lead_id' => $this->lead->id, 'reviewer_id' => $this->reviewer->id];
        $this->post('/audits', array_merge($data, ['lead_id' => $this->reviewer->id]))->assertSessionHasErrors('reviewer_id');
        $this->post('/audits', $data)->assertRedirect();
        $this->signIn($this->officer)->post('/audits', $data)->assertForbidden();
    }
}

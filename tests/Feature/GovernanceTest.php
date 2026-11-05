<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\GovernanceAction;
use App\Models\GovernanceAssessment;
use App\Models\GovernanceItem;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GovernanceTest extends TestCase
{
    use RefreshDatabase;

    private Entity $entity;
    private User $officer;
    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->entity = Entity::create(['code' => 'UTAMA', 'name' => 'PT Utama Tbk']);
        $this->officer = $this->member($this->entity, 'compliance_officer');
        $this->pic = $this->member($this->entity, 'compliance_owner');
    }

    private function assessment(int $year = 2026, bool $copy = false): GovernanceAssessment
    {
        $this->signIn($this->officer)->post('/governance', ['year' => $year, 'coordinator_id' => $this->officer->id, 'copy_previous' => $copy ? '1' : null])->assertRedirect();

        return GovernanceAssessment::where('year', $year)->firstOrFail();
    }

    private function item(GovernanceAssessment $assessment, string $code): GovernanceItem
    {
        return GovernanceItem::where('governance_assessment_id', $assessment->id)->where('code', $code)->firstOrFail();
    }

    private function fill(GovernanceItem $item, array $data, ?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->signIn($as ?? $this->pic)->put('/governance/items/'.$item->id, $data + ['revision' => $item->fresh()->revision, 'status' => 'applied']);
    }

    public function test_assessment_creates_25_recommendations_across_5_aspects_and_8_principles(): void
    {
        $assessment = $this->assessment();
        $items = $assessment->items()->get();
        $this->assertCount(25, $items);
        $this->assertSame(5, $items->map->aspect()->unique()->count());
        $this->assertSame(8, $items->map->principle()->unique()->count());
        $this->get('/governance/'.$assessment->id)->assertOk()->assertSee('Matriks 25 rekomendasi')->assertSee('Prinsip 8: Meningkatkan Pelaksanaan Keterbukaan Informasi');
        $this->post('/governance', ['year' => 2026, 'coordinator_id' => $this->officer->id])->assertSessionHasErrors('year');
        $this->signIn($this->pic)->post('/governance', ['year' => 2027, 'coordinator_id' => $this->officer->id])->assertForbidden();
    }

    public function test_pic_fills_evidence_and_explanation_before_verification_by_another_officer(): void
    {
        $assessment = $this->assessment();
        $item = $this->item($assessment, '4.4');
        $this->signIn($this->officer)->post('/governance/'.$assessment->id.'/assign', ['pic' => ['4.4' => $this->pic->id]])->assertRedirect();
        $this->assertSame($this->pic->id, $item->fresh()->pic_id);

        $this->fill($item, ['status' => 'not_applied', 'explanation' => 'Belum'])->assertRedirect();
        $this->post('/governance/items/'.$item->id.'/submit', ['revision' => $item->fresh()->revision])->assertStatus(422);
        $this->fill($item, ['status' => 'not_applied', 'explanation' => 'Fungsi nominasi baru dibentuk sehingga kebijakan suksesi masih disusun.', 'alternative_practice' => 'Talent pool internal', 'target_date' => '2027-12-31'])->assertRedirect();
        $this->post('/governance/items/'.$item->id.'/submit', ['revision' => $item->fresh()->revision])->assertRedirect();
        $this->assertSame('submitted', $item->fresh()->review_status);
        $this->fill($item, ['status' => 'applied'])->assertForbidden();

        $other = $this->member($this->entity, 'risk_owner');
        $this->signIn($other)->post('/governance/items/'.$item->id.'/verify', ['revision' => $item->fresh()->revision, 'note' => 'Penjelasan memadai.'])->assertForbidden();
        $this->signIn($this->officer)->post('/governance/items/'.$item->id.'/verify', ['revision' => $item->fresh()->revision, 'note' => 'Penjelasan memadai untuk diungkapkan.'])->assertRedirect();
        $this->assertSame('verified', $item->fresh()->review_status);
    }

    public function test_applied_recommendation_needs_description_and_evidence(): void
    {
        $assessment = $this->assessment();
        $item = $this->item($assessment, '7.5');
        $item->update(['pic_id' => $this->pic->id]);
        $this->fill($item, ['implementation' => 'Kebijakan Whistleblowing System ditetapkan Direksi pada 2024.'])->assertRedirect();
        $this->post('/governance/items/'.$item->id.'/submit', ['revision' => $item->fresh()->revision])->assertStatus(422);
        $this->post('/evidence/governance/'.$item->id, ['file' => UploadedFile::fake()->create('wbs.pdf', 20, 'application/pdf')])->assertRedirect('/governance/items/'.$item->id);
        $this->post('/governance/items/'.$item->id.'/submit', ['revision' => $item->fresh()->revision])->assertRedirect();
        $this->assertSame('submitted', $item->fresh()->review_status);
        $this->post('/evidence/governance/'.$item->id, ['file' => UploadedFile::fake()->create('lain.pdf', 20, 'application/pdf')])->assertStatus(409);

        $stranger = $this->member($this->entity, 'risk_owner');
        $this->signIn($stranger)->put('/governance/items/'.$this->item($assessment, '7.1')->id, ['revision' => 1, 'status' => 'applied'])->assertForbidden();
    }

    public function test_approval_requires_all_verified_then_locks_and_exports_disclosure(): void
    {
        $assessment = $this->assessment();
        $this->post('/governance/'.$assessment->id.'/approve', ['revision' => 1, 'note' => 'Rapat Direksi 10 Maret.'])->assertStatus(422);
        GovernanceItem::where('governance_assessment_id', $assessment->id)->update(['status' => 'applied', 'review_status' => 'verified', 'implementation' => 'Penerapan sudah berjalan sesuai kebijakan.', 'pic_id' => $this->pic->id]);
        $this->item($assessment, '7.6')->update(['status' => 'not_applied', 'implementation' => null, 'explanation' => 'Program insentif jangka panjang masih dikaji.']);
        $this->post('/governance/'.$assessment->id.'/approve', ['revision' => 1, 'note' => 'Rapat Direksi 10 Maret 2027.'])->assertRedirect();
        $this->assertTrue($assessment->fresh()->isLocked());

        $item = $this->item($assessment, '1.1');
        $item->update(['review_status' => 'returned']);
        $this->fill($item, ['implementation' => 'Diubah setelah disetujui.'], $this->officer)->assertForbidden();

        $this->get('/governance/'.$assessment->id.'/disclosure')->assertOk()->assertSee('memenuhi 24 rekomendasi')->assertSee('Belum dipenuhi')->assertSee('Program insentif jangka panjang masih dikaji.')->assertDontSee('DRAF');
        $csv = $this->get('/governance/'.$assessment->id.'/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringContainsString('7.6;', $csv);
        $this->assertSame(26, substr_count(trim($csv), "\n") + 1);
        $this->get('/governance/'.$assessment->id.'/disclosure?format=doc')->assertOk()->assertHeader('content-type', 'application/msword; charset=UTF-8');
    }

    public function test_csv_neutralises_formula_injection(): void
    {
        $assessment = $this->assessment();
        $this->item($assessment, '2.1')->update(['implementation' => '=HYPERLINK("http://contoh")']);
        $csv = $this->get('/governance/'.$assessment->id.'/export')->getContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_next_year_copies_previous_answers_and_follow_up_is_closed_by_its_pic(): void
    {
        $first = $this->assessment(2025);
        $this->item($first, '7.6')->update(['status' => 'partial', 'explanation' => 'Masih dikaji bersama pemegang saham.', 'pic_id' => $this->pic->id]);
        $second = $this->assessment(2026, true);
        $copied = $this->item($second, '7.6');
        $this->assertSame('partial', $copied->status);
        $this->assertSame($this->pic->id, $copied->pic_id);
        $this->assertSame('draft', $copied->review_status);
        $this->get('/governance/'.$second->id)->assertSee('2025: Diterapkan sebagian');

        $this->signIn($this->pic)->post('/governance/items/'.$copied->id.'/actions', ['title' => 'Mengajukan program LTI kepada RUPS', 'pic_id' => $this->pic->id, 'due_date' => today()->addMonth()->toDateString()])->assertRedirect();
        $action = GovernanceAction::firstOrFail();
        $this->get('/audit-actions')->assertSee('Mengajukan program LTI kepada RUPS');
        $other = $this->member($this->entity, 'risk_owner');
        $this->signIn($other)->post('/governance/actions/'.$action->id.'/done', ['note' => 'Sudah selesai dikerjakan.'])->assertForbidden();
        $this->signIn($this->pic)->post('/governance/actions/'.$action->id.'/done', ['note' => 'Program diajukan dalam RUPS Tahunan.'])->assertRedirect();
        $this->assertSame('done', $action->fresh()->status);
    }

    public function test_other_company_cannot_read_assessment(): void
    {
        $assessment = $this->assessment();
        $other = Entity::create(['code' => 'LAIN', 'name' => 'PT Lain']);
        $outsider = $this->member($other, 'admin');
        $this->flushSession();
        $this->signIn($outsider)->get('/governance/'.$assessment->id)->assertNotFound();
        $this->get('/governance/items/'.$this->item($assessment, '1.1')->id)->assertNotFound();
        $this->get('/governance/'.$assessment->id.'/export')->assertNotFound();
    }

    public function test_guest_reads_demo_governance_without_edit_controls(): void
    {
        $this->seed(DemoSeeder::class);
        $this->assertSame(150, GovernanceItem::count());
        $assessment = GovernanceAssessment::where('status', 'open')->orderBy('id')->firstOrFail();
        $this->get('/demo/governance')->assertOk()->assertSee('Penerapan Pedoman Tata Kelola');
        $this->get('/demo/governance/'.$assessment->id)->assertOk()->assertSee('Rencana perbaikan yang masih berjalan')->assertDontSee('Simpan penugasan PIC');
        $item = $this->item($assessment, '4.4');
        $this->get('/demo/governance/items/'.$item->id)->assertOk()->assertSee('Alasan belum diterapkan')->assertDontSee('Simpan penilaian');
        $this->get('/demo/governance/'.$assessment->id.'/disclosure')->assertOk()->assertSee('DRAF');
        $this->get('/demo/ringkasan')->assertSee('Penerapan 25 rekomendasi Pedoman Tata Kelola');
    }
}

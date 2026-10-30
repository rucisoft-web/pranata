<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Entity;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Entity $entity;
    private User $owner;
    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entity = Entity::create(['code' => 'DEMO', 'name' => 'PT Pranata Demo Tbk', 'is_bumn' => true, 'is_public_company' => true, 'is_listed' => true]);
        $this->owner = $this->riskMember('owner@example.test', 'risk_owner');
        $this->reviewer = $this->riskMember('reviewer@example.test', 'risk_manager');
    }

    private function riskMember(string $email, string $role): User
    {
        $user = User::create(['name' => $email, 'email' => $email, 'password' => 'Testing-Only123!']);
        $user->entities()->attach($this->entity->id, ['role' => $role]);

        return $user;
    }

    private function payload(): array
    {
        return [
            'code' => 'RSK-001', 'title' => 'Gangguan layanan strategis', 'category' => 'Operasional',
            'objective' => 'Menjaga kesinambungan usaha', 'process' => 'Operasi',
            'cause' => 'Ketergantungan pada pemasok tunggal', 'event' => 'Layanan pemasok terhenti',
            'impact_description' => 'Operasi tertunda', 'likelihood' => 3, 'impact' => 4,
            'assessment_reason' => 'Hasil peninjauan operasional', 'response' => 'reduce',
            'treatment_plan' => 'Menyiapkan pemasok alternatif', 'review_date' => '2027-01-31',
        ];
    }

    private function risk(): Risk
    {
        return Risk::create(array_merge($this->payload(), ['entity_id' => $this->entity->id, 'owner_id' => $this->owner->id]));
    }

    public function test_guest_cannot_access_risk_register(): void
    {
        $this->get('/risks')->assertRedirect('/login');
    }

    public function test_owner_can_create_risk_and_workflow_is_audited(): void
    {
        $this->signIn($this->owner)->post('/risks', $this->payload())->assertRedirect();
        $risk = Risk::firstOrFail();
        $this->assertSame(12, $risk->score);
        $this->assertSame('Tinggi', $risk->level);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $risk->id, 'action' => 'risk.created', 'actor_id' => $this->owner->id]);
        $this->get('/dashboard')->assertOk()->assertSee('Dashboard eksekutif');
        $this->get('/risks/'.$risk->id)->assertOk()->assertSee('Gangguan layanan strategis');
        $this->get('/risks/'.$risk->id.'/edit')->assertOk();
    }

    public function test_client_cannot_override_owner_entity_status_or_methodology(): void
    {
        $other = Entity::create(['code' => 'OTHER', 'name' => 'Perusahaan lain']);
        $this->signIn($this->owner)->post('/risks', array_merge($this->payload(), [
            'entity_id' => $other->id, 'owner_id' => $this->reviewer->id, 'status' => 'approved',
            'methodology_version' => 'FORGED', 'approved_by' => $this->reviewer->id,
        ]))->assertRedirect();
        $this->assertDatabaseHas('risks', [
            'entity_id' => $this->entity->id, 'owner_id' => $this->owner->id,
            'status' => 'draft', 'methodology_version' => 'PRN-INITIAL-1', 'approved_by' => null,
        ]);
    }

    public function test_viewer_can_read_but_cannot_create_edit_or_submit(): void
    {
        $viewer = $this->riskMember('viewer@example.test', 'viewer');
        $risk = $this->risk();
        $this->signIn($viewer)->get('/risks')->assertOk();
        $this->get('/risks/create')->assertForbidden();
        $this->post('/risks', $this->payload())->assertForbidden();
        $this->put('/risks/'.$risk->id, $this->payload() + ['revision' => 1])->assertForbidden();
        $this->post('/risks/'.$risk->id.'/submit', ['revision' => 1])->assertForbidden();
    }

    public function test_other_owner_cannot_modify_a_colleagues_risk(): void
    {
        $risk = $this->risk();
        $colleague = $this->riskMember('colleague@example.test', 'risk_owner');
        $this->signIn($colleague)->put('/risks/'.$risk->id, $this->payload() + ['revision' => 1])->assertForbidden();
    }

    public function test_risk_is_invisible_and_immutable_from_another_entity(): void
    {
        $risk = $this->risk();
        $other = Entity::create(['code' => 'OTHER', 'name' => 'Other Entity']);
        $outsider = User::create(['name' => 'Outsider', 'email' => 'outsider@example.test', 'password' => 'Testing-Only123!']);
        $outsider->entities()->attach($other->id, ['role' => 'admin']);
        $this->signIn($outsider)->get('/risks')->assertOk()->assertDontSee($risk->title);
        $this->get('/dashboard')->assertOk()->assertDontSee($risk->title);
        $this->get('/risks/'.$risk->id)->assertNotFound();
        $this->get('/risks/'.$risk->id.'/edit')->assertNotFound();
        $this->put('/risks/'.$risk->id, $this->payload() + ['revision' => 1])->assertNotFound();
        $this->post('/risks/'.$risk->id.'/approve', ['revision' => 1, 'review_note' => 'Tidak sah'])->assertNotFound();
        $this->post('/entity', ['entity_id' => $this->entity->id])->assertForbidden();
    }

    public function test_revoked_membership_is_checked_on_every_request(): void
    {
        $this->signIn($this->owner)->withSession(['entity_id' => $this->entity->id]);
        $this->owner->entities()->detach($this->entity->id);
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_authorized_entity_switch_changes_visible_data(): void
    {
        $risk = $this->risk();
        $other = Entity::create(['code' => 'OTHER', 'name' => 'Other Entity']);
        $this->owner->entities()->attach($other->id, ['role' => 'viewer']);
        $this->signIn($this->owner)->post('/entity', ['entity_id' => $other->id])->assertRedirect('/dashboard');
        $this->get('/risks')->assertOk()->assertDontSee($risk->title);
        $this->get('/risks/create')->assertForbidden();
    }

    public function test_submit_approve_and_approved_record_cannot_be_edited(): void
    {
        $risk = $this->risk();
        $this->signIn($this->owner)->post('/risks/'.$risk->id.'/submit', ['revision' => 1])->assertRedirect();
        $this->signIn($this->reviewer)->post('/risks/'.$risk->id.'/approve', ['revision' => 2, 'review_note' => 'Dasar penilaian memadai'])->assertRedirect();
        $this->assertDatabaseHas('risks', ['id' => $risk->id, 'status' => 'approved', 'approved_by' => $this->reviewer->id, 'revision' => 3]);
        $this->assertSame(2, AuditEvent::count());
        $this->signIn($this->owner)->put('/risks/'.$risk->id, $this->payload() + ['revision' => 3])->assertConflict();
    }

    public function test_even_admin_cannot_approve_own_risk(): void
    {
        $risk = $this->risk();
        $this->owner->entities()->updateExistingPivot($this->entity->id, ['role' => 'admin']);
        $risk->update(['status' => 'submitted']);
        $this->signIn($this->owner)->post('/risks/'.$risk->id.'/approve', ['revision' => 1, 'review_note' => 'Setujui sendiri'])->assertForbidden();
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_return_requires_reason_and_reopens_draft(): void
    {
        $risk = $this->risk();
        $risk->update(['status' => 'submitted']);
        $this->signIn($this->reviewer)->post('/risks/'.$risk->id.'/return', ['revision' => 1])->assertSessionHasErrors('review_note');
        $this->post('/risks/'.$risk->id.'/return', ['revision' => 1, 'review_note' => 'Lengkapi dasar penilaian'])->assertRedirect();
        $this->assertDatabaseHas('risks', ['id' => $risk->id, 'status' => 'draft', 'review_note' => 'Lengkapi dasar penilaian']);
    }

    public function test_stale_revision_cannot_overwrite_newer_changes(): void
    {
        $risk = $this->risk();
        $this->signIn($this->owner)->put('/risks/'.$risk->id, array_merge($this->payload(), ['title' => 'Versi baru', 'revision' => 1]))->assertRedirect();
        $this->put('/risks/'.$risk->id, $this->payload() + ['revision' => 1])->assertConflict();
        $this->assertDatabaseHas('risks', ['id' => $risk->id, 'title' => 'Versi baru', 'revision' => 2]);
        $this->assertSame(1, AuditEvent::count());
    }

    public function test_invalid_scores_and_duplicate_codes_are_rejected(): void
    {
        $this->risk();
        $this->signIn($this->owner)->post('/risks', array_merge($this->payload(), ['likelihood' => 6, 'impact' => 0]))->assertSessionHasErrors(['code', 'likelihood', 'impact']);
    }

    public function test_user_content_is_escaped(): void
    {
        $risk = $this->risk();
        $risk->update(['title' => '<script>alert(1)</script>']);
        $this->signIn($this->owner)->get('/risks/'.$risk->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_register_pagination_preserves_filters(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            Risk::create(array_merge($this->payload(), ['code' => 'RSK-'.$i, 'entity_id' => $this->entity->id, 'owner_id' => $this->owner->id]));
        }
        $this->signIn($this->owner)->get('/risks?status=draft')->assertOk()->assertSee('Berikutnya')->assertSee('status=draft', false);
        $this->get('/risks?status=draft&page=2')->assertOk()->assertSee('Sebelumnya')->assertSee('Halaman 2');
    }
}

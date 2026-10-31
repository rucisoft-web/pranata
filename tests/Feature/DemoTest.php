<?php
namespace Tests\Feature;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\Entity;
use App\Models\EvidenceFile;
use App\Models\Obligation;
use App\Models\ObligationOccurrence;
use App\Models\Risk;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); Storage::fake('local'); $this->seed(DemoSeeder::class); }

    public function test_guest_can_switch_three_companies_and_read_every_demo_module(): void
    {
        $this->get('/')->assertOk()->assertSee('Lihat demo sebagai tamu')->assertSee('images/screenshots/dashboard.png');
        $this->get('/login')->assertOk()->assertSee('Masuk sebagai Tamu');
        $this->assertSame(3, Entity::where('is_demo', true)->count());
        foreach (Entity::where('is_demo', true)->get() as $entity) {
            $this->get('/demo?entity='.$entity->id)->assertOk()->assertSee($entity->name)->assertSee('DATA CONTOH');
            $this->get('/demo/ringkasan?entity='.$entity->id)->assertOk()->assertSee('Ringkasan eksekutif')->assertSee($entity->name)->assertSee('Perbandingan perusahaan dalam grup');
            foreach (['risks', 'obligations', 'compliance', 'audits', 'audit-actions'] as $path) { $this->get('/demo/'.$path)->assertOk(); }
            $risk = Risk::where('entity_id', $entity->id)->firstOrFail();
            $this->get('/demo/risks/'.$risk->id)->assertOk()->assertSee($risk->title)->assertDontSee('Ajukan tinjauan');
            $this->get('/demo/obligations/'.Obligation::where('entity_id', $entity->id)->firstOrFail()->id)->assertOk();
            $this->get('/demo/compliance/'.ObligationOccurrence::where('entity_id', $entity->id)->firstOrFail()->id)->assertOk()->assertDontSee('Ajukan verifikasi');
            $this->get('/demo/audits/'.AuditEngagement::where('entity_id', $entity->id)->firstOrFail()->id)->assertOk()->assertDontSee('Simpan temuan');
            $this->get('/demo/audit-findings/'.AuditFinding::where('entity_id', $entity->id)->firstOrFail()->id)->assertOk();
        }
        $this->assertGuest('web');
    }

    public function test_demo_cannot_select_real_company_or_cross_selected_demo_company(): void
    {
        $private = Entity::create(['code' => 'REAL', 'name' => 'Private Corp']);
        $this->get('/demo?entity='.$private->id)->assertNotFound();
        $entities = Entity::where('is_demo', true)->get();
        $this->get('/demo?entity='.$entities[0]->id)->assertOk();
        $foreignRisk = Risk::where('entity_id', $entities[1]->id)->firstOrFail();
        $this->get('/demo/risks/'.$foreignRisk->id)->assertNotFound();
        $foreignFile = EvidenceFile::where('entity_id', $entities[1]->id)->firstOrFail();
        $this->get('/demo/evidence/'.$foreignFile->id)->assertNotFound();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_guest_has_no_mutation_routes_and_downloads_only_demo_evidence(): void
    {
        $this->get('/demo')->assertOk();
        $this->post('/demo/risks', ['title' => 'Forged'])->assertStatus(405);
        $this->post('/obligations', [])->assertRedirect('/login');
        $this->get('/demo/risks/create')->assertNotFound();
        $file = EvidenceFile::where('entity_id', Entity::where('is_demo', true)->firstOrFail()->id)->firstOrFail();
        $this->get('/demo/evidence/'.$file->id)->assertOk()->assertDownload($file->original_name);
        $this->assertDatabaseMissing('risks', ['title' => 'Forged']);
    }

    public function test_demo_accounts_cannot_login_and_seeding_is_idempotent(): void
    {
        $user = User::where('is_demo_guest', true)->firstOrFail(); $user->password = 'Known-demo-password123!'; $user->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'Known-demo-password123!'])->assertSessionHasErrors('email');
        $count = Risk::count(); $files = EvidenceFile::count();
        $this->seed(DemoSeeder::class);
        $this->assertSame($count, Risk::count()); $this->assertSame($files, EvidenceFile::count());
        $this->assertGuest('web');
    }

    public function test_visiting_demo_preserves_authenticated_users_identity_and_mfa(): void
    {
        $entity = Entity::create(['code' => 'REAL', 'name' => 'Private Corp']); $user = $this->member($entity, 'viewer');
        $this->signIn($user)->withSession(['entity_id' => $entity->id])->get('/demo')->assertOk()->assertSee('Tamu');
        $this->get('/dashboard')->assertOk()->assertSee('Private Corp')->assertDontSee('DATA CONTOH');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_demo_can_be_disabled(): void
    {
        config(['demo.enabled' => false]);
        $this->get('/demo')->assertNotFound();
        $this->get('/login')->assertOk()->assertDontSee('Masuk sebagai Tamu');
    }
}

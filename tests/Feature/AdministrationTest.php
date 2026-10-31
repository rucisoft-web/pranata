<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Entity;
use App\Models\Obligation;
use App\Models\Risk;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private Entity $entity;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entity = Entity::create(['code' => 'UTAMA', 'name' => 'PT Utama Tbk']);
        $this->admin = $this->member($this->entity, 'admin');
    }

    public function test_validation_messages_and_error_pages_are_in_indonesian(): void
    {
        $owner = $this->member($this->entity, 'risk_owner');
        $this->signIn($owner)->from('/risks/create')->post('/risks', [])->assertSessionHasErrors(['code' => 'kode wajib diisi.', 'review_date' => 'tanggal review wajib diisi.']);
        $this->get('/risks/999')->assertNotFound()->assertSee('Halaman tidak ditemukan')->assertDontSee('No query results');
        $this->get('/users')->assertForbidden()->assertSee('Akses ditolak');
    }

    public function test_admin_adds_user_who_can_login_with_temporary_password(): void
    {
        $this->signIn($this->admin)->get('/users')->assertOk()->assertSee('Pengguna & akses')->assertSee($this->admin->email);
        $this->post('/users', ['name' => 'Rina', 'email' => 'Rina@Contoh.co.id', 'role' => 'compliance_owner', 'password' => 'lemah'])->assertSessionHasErrors('password');
        $this->post('/users', ['name' => 'Rina', 'email' => 'Rina@Contoh.co.id', 'role' => 'compliance_owner', 'password' => 'Sementara#2026x'])->assertRedirect('/users');
        $rina = User::where('email', 'rina@contoh.co.id')->firstOrFail();
        $this->assertSame('compliance_owner', $rina->roleIn($this->entity->id));
        $this->assertTrue(Hash::check('Sementara#2026x', $rina->password));
        $this->assertTrue(AuditEvent::where('action', 'user.added')->where('subject_id', $rina->id)->exists());
        $this->post('/users', ['name' => 'Rina', 'email' => 'rina@contoh.co.id', 'role' => 'viewer'])->assertSessionHasErrors('user');

        $this->put('/users/'.$rina->id, ['role' => 'compliance_officer'])->assertRedirect('/users');
        $this->assertSame('compliance_officer', $rina->roleIn($this->entity->id));
        $this->delete('/users/'.$rina->id)->assertRedirect('/users');
        $this->assertNull($rina->roleIn($this->entity->id));
    }

    public function test_admin_cannot_lock_themselves_out_and_non_admin_cannot_manage(): void
    {
        $this->signIn($this->admin)->put('/users/'.$this->admin->id, ['role' => 'viewer'])->assertSessionHasErrors('user');
        $this->delete('/users/'.$this->admin->id)->assertSessionHasErrors('user');
        $this->assertSame('admin', $this->admin->roleIn($this->entity->id));

        $manager = $this->member($this->entity, 'risk_manager');
        $this->signIn($manager)->get('/users')->assertForbidden();
        $this->post('/users', ['name' => 'X', 'email' => 'x@contoh.co.id', 'role' => 'admin', 'password' => 'Sementara#2026x'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@contoh.co.id']);
    }

    public function test_password_and_mfa_reset_stay_within_admins_companies(): void
    {
        $user = $this->member($this->entity, 'risk_owner');
        $this->signIn($user);
        $this->signIn($this->admin)->post('/users/'.$user->id.'/password', ['password' => 'BaruSekali#2026'])->assertRedirect('/users');
        $this->assertTrue(Hash::check('BaruSekali#2026', $user->fresh()->password));
        $this->post('/users/'.$user->id.'/mfa-reset', ['reason' => 'Ponsel pengguna hilang'])->assertRedirect('/users');
        $this->assertNull($user->fresh()->mfa_confirmed_at);

        $other = Entity::create(['code' => 'LAIN', 'name' => 'PT Lain']);
        $user->entities()->attach($other->id, ['role' => 'viewer']);
        $this->post('/users/'.$user->id.'/password', ['password' => 'Ambil#Alih2026x'])->assertForbidden();
        $this->assertFalse(Hash::check('Ambil#Alih2026x', $user->fresh()->password));
    }

    public function test_admin_can_create_company_and_becomes_its_admin(): void
    {
        $this->signIn($this->admin)->post('/entities', ['entity_code' => 'ANAK-01', 'entity_name' => 'PT Anak Usaha', 'is_bumn' => '1'])->assertRedirect('/users');
        $entity = Entity::where('code', 'ANAK-01')->firstOrFail();
        $this->assertTrue($entity->is_bumn);
        $this->assertSame('admin', $this->admin->roleIn($entity->id));
        $this->get('/dashboard')->assertOk()->assertSee('PT Anak Usaha');
    }

    public function test_user_changes_own_password(): void
    {
        $this->signIn($this->admin)->post('/security/password', ['current_password' => 'salah', 'password' => 'BaruSekali#2026', 'password_confirmation' => 'BaruSekali#2026'])->assertSessionHasErrors('current_password');
        $this->post('/security/password', ['current_password' => 'Testing-Only123!', 'password' => 'BaruSekali#2026', 'password_confirmation' => 'BaruSekali#2026'])->assertRedirect('/security');
        $this->assertTrue(Hash::check('BaruSekali#2026', $this->admin->fresh()->password));
    }

    public function test_mfa_can_be_made_optional(): void
    {
        config(['pranata.mfa_required' => false]);
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'Testing-Only123!'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
        $this->get('/security')->assertOk()->assertSee('Aktifkan MFA');

        config(['pranata.mfa_required' => true]);
        $this->get('/dashboard')->assertRedirect('/mfa/setup');
    }

    public function test_create_admin_command_works_without_terminal_prompts(): void
    {
        $this->artisan('pranata:create-admin', ['--email' => 'boss@contoh.co.id', '--name' => 'Direksi', '--password' => 'Sementara#2026x', '--entity-code' => 'induk', '--entity-name' => 'PT Induk Tbk', '--listed' => true, '--no-interaction' => true])->assertExitCode(0);
        $boss = User::where('email', 'boss@contoh.co.id')->firstOrFail();
        $entity = Entity::where('code', 'INDUK')->firstOrFail();
        $this->assertTrue($entity->is_listed);
        $this->assertSame('admin', $boss->roleIn($entity->id));
        $this->artisan('pranata:create-admin', ['--email' => 'baru@contoh.co.id', '--password' => 'lemah', '--entity-code' => 'INDUK', '--no-interaction' => true])->assertExitCode(1);
        $this->artisan('pranata:doctor')->assertExitCode(0);
    }

    public function test_database_seeder_loads_demo_only_when_enabled(): void
    {
        Storage::fake('local');
        config(['demo.seed' => false]);
        $this->seed(DatabaseSeeder::class);
        $this->assertFalse(Entity::where('is_demo', true)->exists());
        $this->get('/login')->assertDontSee('Masuk sebagai Tamu');
        $this->get('/')->assertDontSee('Lihat demo sebagai tamu');

        config(['demo.seed' => true]);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(3, Entity::where('is_demo', true)->count());
        $this->get('/login')->assertSee('Masuk sebagai Tamu');
    }

    public function test_relation_ids_compare_as_integers_even_when_driver_returns_strings(): void
    {
        $risk = new Risk;
        $risk->setRawAttributes(['owner_id' => (string) $this->admin->id, 'entity_id' => (string) $this->entity->id, 'revision' => '3']);
        $this->assertSame($this->admin->id, $risk->owner_id);
        $this->assertSame(3, $risk->revision);
        $obligation = new Obligation;
        $obligation->setRawAttributes(['responsible_id' => '7', 'revision' => '2']);
        $this->assertSame(7, $obligation->responsible_id);
        $this->assertSame(2, $obligation->revision);
    }

    public function test_risk_history_shows_readable_action_and_actor_name(): void
    {
        $owner = $this->member($this->entity, 'risk_owner');
        $this->signIn($owner)->post('/risks', [
            'code' => 'RSK-1', 'title' => 'Risiko uji', 'category' => 'Operasional', 'objective' => 'Sasaran', 'process' => 'Proses',
            'cause' => 'Sebab', 'event' => 'Peristiwa', 'impact_description' => 'Dampak', 'likelihood' => 3, 'impact' => 4,
            'assessment_reason' => 'Alasan', 'response' => 'reduce', 'treatment_plan' => 'Rencana', 'review_date' => '2027-01-31',
        ]);
        $risk = Risk::where('code', 'RSK-1')->firstOrFail();
        $this->get('/risks/'.$risk->id)->assertOk()->assertSee('Risiko dicatat sebagai draf')->assertSee($owner->name)->assertDontSee('risk.created')->assertDontSee('UTC');
    }

    public function test_executive_summary_charts_for_management_roles_only(): void
    {
        Storage::fake('local');
        $this->seed(\Database\Seeders\DemoSeeder::class);
        $this->assertSame(36, Risk::count());
        $this->get('/login')->assertSee('Ingin melihat-lihat dulu?')->assertSee('/demo/ringkasan');
        $this->get('/')->assertSee('Masuk sebagai Tamu');
        $this->get('/demo/ringkasan')->assertOk()->assertSee('Sebaran level risiko inheren')->assertSee('Tren pemenuhan kewajiban')->assertSee('<svg', false)->assertSee('Kepatuhan tepat waktu');

        $this->signIn($this->admin)->get('/ringkasan')->assertOk()->assertSee('Register risiko belum berisi catatan.')->assertDontSee('DEMO');
        $owner = $this->member($this->entity, 'risk_owner');
        $this->signIn($owner)->get('/ringkasan')->assertForbidden();
    }
}

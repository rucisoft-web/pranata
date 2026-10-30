<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User { return User::create(['name' => 'MFA User', 'email' => 'mfa@example.test', 'password' => 'Testing-Only123!']); }

    private function enabled(): User
    {
        $user = $this->account();
        $user->forceFill(['mfa_secret' => (new Google2FA)->generateSecretKey(32), 'mfa_confirmed_at' => now(), 'mfa_epoch' => (string) Str::uuid(), 'mfa_last_counter' => 0])->save();

        return $user;
    }

    public function test_password_session_cannot_access_any_business_route_without_mfa(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/mfa/setup');
        $this->get('/compliance')->assertRedirect('/mfa/setup');
        $this->get('/audits')->assertRedirect('/mfa/setup');
        $this->post('/entity', ['entity_id' => 1])->assertRedirect('/mfa/setup');
        $this->post('/obligations', [])->assertRedirect('/mfa/setup');
    }

    public function test_enrollment_encrypts_secret_and_displays_recovery_codes_once(): void
    {
        $user = $this->account();
        $response = $this->actingAs($user)->get('/mfa/setup')->assertOk()->assertSee('QR untuk mengatur aplikasi authenticator');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dom->getElementsByTagName('img')->item(0)->getAttribute('src'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $secret = session('mfa_setup.secret');
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post('/mfa/setup', ['current_password' => 'Testing-Only123!', 'code' => $code])->assertRedirect('/mfa/recovery');
        $plain = session('mfa_recovery_plain');
        $this->assertCount(10, $plain);
        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('mfa_secret'));
        $this->assertSame($secret, $user->fresh()->mfa_secret);
        $this->assertSame((new Google2FA)->getTimestamp(), $user->fresh()->mfa_last_counter);
        $this->assertArrayNotHasKey('mfa_secret', $user->fresh()->toArray());
        $this->get('/mfa/recovery')->assertOk()->assertSee($plain[0]);
        $this->get('/mfa/recovery')->assertOk()->assertDontSee($plain[0]);
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'action' => 'mfa.enrolled']);
    }

    public function test_totp_is_single_use_even_across_new_sessions(): void
    {
        $user = $this->enabled(); $code = (new Google2FA)->getCurrentOtp($user->mfa_secret);
        $this->actingAs($user)->get('/mfa/challenge')->assertOk();
        $this->post('/mfa/challenge', ['code' => $code])->assertRedirect('/dashboard');
        $this->withSession(['mfa' => null])->post('/mfa/challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/security')->assertRedirect('/mfa/challenge');
    }

    public function test_recovery_code_is_hashed_and_cannot_be_reused(): void
    {
        $user = $this->enabled();
        $user->forceFill(['mfa_recovery_hashes' => [hash('sha256', 'DEMO-RECOVERY-SECRET')]])->save();
        $this->actingAs($user)->post('/mfa/challenge', ['recovery_code' => 'DEMO-RECOVERY-SECRET'])->assertRedirect('/dashboard');
        $this->assertSame([], $user->fresh()->mfa_recovery_hashes);
        $this->withSession(['mfa' => null])->post('/mfa/challenge', ['recovery_code' => 'DEMO-RECOVERY-SECRET'])->assertSessionHasErrors('code');
    }

    public function test_rotation_requires_password_and_new_totp_and_invalidates_old_session_epoch(): void
    {
        $user = $this->enabled(); $oldEpoch = $user->mfa_epoch;
        $user->forceFill(['mfa_recovery_hashes' => [hash('sha256', 'OLD-CODE')]])->save();
        $code = (new Google2FA)->getCurrentOtp($user->mfa_secret);
        $this->signIn($user)->post('/mfa/recovery', ['current_password' => 'wrong', 'code' => $code])->assertSessionHasErrors('code');
        $this->post('/mfa/recovery', ['current_password' => 'Testing-Only123!', 'code' => $code])->assertRedirect('/mfa/recovery');
        $this->assertNotSame($oldEpoch, $user->fresh()->mfa_epoch);
        $this->assertNotContains(hash('sha256', 'OLD-CODE'), $user->fresh()->mfa_recovery_hashes);
        $this->withSession(['mfa' => ['user_id' => $user->id, 'epoch' => $oldEpoch]])->get('/security')->assertRedirect('/mfa/challenge');
    }

    public function test_setup_expires_and_codes_are_not_flashed_on_failure(): void
    {
        $user = $this->account();
        $this->actingAs($user)->withSession(['mfa_setup' => ['secret' => (new Google2FA)->generateSecretKey(32), 'expires' => time() - 1]])->post('/mfa/setup', ['current_password' => 'Testing-Only123!', 'code' => '123456'])->assertStatus(419);
        $this->get('/mfa/setup')->assertOk();
        $this->post('/mfa/setup', ['current_password' => 'wrong-password', 'code' => '123456'])->assertSessionHasErrors('code');
        $this->assertNull(session('_old_input.current_password'));
        $this->assertNull(session('_old_input.code'));
        $this->assertNull($user->fresh()->mfa_confirmed_at);
    }

    public function test_mfa_attempts_are_limited_per_account(): void
    {
        $user = $this->enabled();
        for ($i=0; $i<5; $i++) { $this->actingAs($user)->post('/mfa/challenge', ['recovery_code' => 'INVALID'])->assertSessionHasErrors('code'); }
        $this->post('/mfa/challenge', ['recovery_code' => 'INVALID'])->assertSessionHasErrors(['code' => 'Terlalu banyak percobaan MFA. Tunggu satu menit.']);
    }
}

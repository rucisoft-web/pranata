<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_logout(): void
    {
        $user = User::create(['name' => 'Pengguna', 'email' => 'user@example.test', 'password' => 'Testing-Only123!']);
        $entity = Entity::create(['code' => 'TEST', 'name' => 'PT Demo']);
        $user->entities()->attach($entity->id, ['role' => 'viewer']);
        $this->get('/login')->assertOk()->assertSee('PRANATA');
        $this->post('/login', ['email' => 'USER@example.test', 'password' => 'Testing-Only123!'])->assertRedirect('/mfa/setup');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertRedirect('/mfa/setup');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_invalid_login_uses_generic_message_and_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan. Coba kembali setelah satu menit.']);
        $this->assertGuest();
    }

    public function test_authenticated_user_without_membership_cannot_access_dashboard(): void
    {
        $user = User::create(['name' => 'No Access', 'email' => 'no@example.test', 'password' => 'Testing-Only123!']);
        $this->signIn($user)->get('/dashboard')->assertForbidden();
    }
}

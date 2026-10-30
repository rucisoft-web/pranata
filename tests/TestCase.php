<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function signIn(\App\Models\User $user): static
    {
        if (! $user->mfa_confirmed_at) {
            $user->forceFill(['mfa_secret' => (new \PragmaRX\Google2FA\Google2FA)->generateSecretKey(32), 'mfa_confirmed_at' => now(), 'mfa_epoch' => (string) \Illuminate\Support\Str::uuid()])->save();
        }

        return $this->actingAs($user)->withSession(['mfa' => ['user_id' => $user->id, 'epoch' => $user->mfa_epoch]]);
    }

    protected function member(\App\Models\Entity $entity, string $role): \App\Models\User
    {
        $user = \App\Models\User::create(['name' => 'Petugas '.$role, 'email' => \Illuminate\Support\Str::uuid().'@example.test', 'password' => 'Testing-Only123!']);
        $user->entities()->attach($entity->id, ['role' => $role]);

        return $user;
    }
}

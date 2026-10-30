<?php

use App\Models\Entity;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

Artisan::command('pranata:provision {email} {--name=} {--entity-code=} {--entity-name=} {--role=admin} {--bumn} {--public-company} {--listed}', function () {
    $email = Str::lower(trim($this->argument('email')));
    $code = $this->option('entity-code');
    $name = $this->option('entity-name');
    $userName = $this->option('name');
    $role = $this->option('role');
    Validator::make(compact('email', 'code', 'name', 'userName', 'role'), [
        'email' => ['required', 'email', 'max:254'],
        'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
        'name' => ['required', 'string', 'max:255'],
        'userName' => ['required', 'string', 'max:255'],
        'role' => [Rule::in(['admin', 'risk_manager', 'risk_owner', 'viewer', 'compliance_officer', 'compliance_owner', 'audit_manager', 'auditor'])],
    ])->validate();
    $existing = User::where('email', $email)->first();
    $password = null;
    if (! $existing) {
        $password = $this->secret('Kata sandi pengguna baru (minimum 12 karakter)');
        Validator::make(['password' => $password, 'password_confirmation' => $this->secret('Ulangi kata sandi')], [
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ])->validate();
    }
    DB::transaction(function () use ($existing, $email, $password, $code, $name, $userName, $role) {
        $entity = Entity::firstOrCreate(['code' => $code], [
            'name' => $name, 'is_bumn' => $this->option('bumn'),
            'is_public_company' => $this->option('public-company') || $this->option('listed'),
            'is_listed' => $this->option('listed'),
        ]);
        $user = $existing ?? User::create(['name' => $userName, 'email' => $email, 'password' => $password]);
        $user->entities()->syncWithoutDetaching([$entity->id => ['role' => $role]]);
    });
    $this->info('Pengguna dan akses perusahaan berhasil disiapkan.');
})->purpose('Membuat pengguna dan memberikan akses perusahaan melalui operator server tepercaya.');

Artisan::command('pranata:mfa-reset {email} {--reason=}', function () {
    $reason = $this->option('reason');
    Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:15', 'max:1000']])->validate();
    $user = User::where('email', Str::lower(trim($this->argument('email'))))->firstOrFail();
    if (! $this->confirm('Identitas pengguna telah diverifikasi di luar aplikasi dan reset disetujui?')) { return; }
    DB::transaction(function () use ($user, $reason) {
        $user = User::lockForUpdate()->findOrFail($user->id);
        $user->forceFill(['mfa_secret' => null, 'mfa_recovery_hashes' => null, 'mfa_confirmed_at' => null, 'mfa_last_counter' => null, 'mfa_epoch' => (string) Str::uuid()])->save();
        \App\Models\SecurityEvent::create(['user_id' => $user->id, 'action' => 'mfa.reset_by_operator', 'metadata' => ['reason' => $reason], 'created_at' => now()]);
    });
    $this->info('MFA direset. Sesi lama kehilangan akses data sampai MFA didaftarkan dan diverifikasi kembali.');
})->purpose('Reset MFA terkontrol oleh operator tepercaya setelah verifikasi identitas.');

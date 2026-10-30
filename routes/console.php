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
        'role' => [Rule::in(['admin', 'risk_manager', 'risk_owner', 'viewer'])],
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

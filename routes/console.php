<?php

use App\Models\Entity;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

Artisan::command('pranata:provision {email} {--name=} {--entity-code=} {--entity-name=} {--role=admin} {--bumn} {--public-company} {--listed} {--password= : Kata sandi akun baru (untuk cron tanpa Terminal)}', function () {
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
        $password = $this->option('password') ?: $this->secret('Kata sandi pengguna baru (minimum 12 karakter)');
        Validator::make(['password' => $password, 'password_confirmation' => $this->option('password') ?: $this->secret('Ulangi kata sandi')], [
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

Artisan::command('pranata:create-admin {--name= : Nama administrator} {--email= : Email login} {--password= : Kata sandi (min. 12, huruf besar/kecil, angka, simbol)} {--entity-code=UTAMA : Kode perusahaan} {--entity-name= : Nama perusahaan} {--bumn} {--listed}', function () {
    $email = Str::lower(trim((string) ($this->option('email') ?: $this->ask('Email administrator'))));
    $name = $this->option('name') ?: ($this->input->isInteractive() ? $this->ask('Nama administrator', 'Administrator') : 'Administrator');
    $code = Str::upper((string) $this->option('entity-code'));
    $entityName = $this->option('entity-name') ?: (Entity::where('code', $code)->value('name') ?? ($this->input->isInteractive() ? $this->ask('Nama perusahaan') : null));
    $password = $this->option('password');
    $existing = User::where('email', $email)->first();
    if (! $existing && ! $password) {
        $password = $this->input->isInteractive() ? $this->secret('Kata sandi (min. 12 karakter, huruf besar/kecil, angka, simbol)') : null;
    }
    $validator = Validator::make(compact('email', 'name', 'code', 'entityName', 'password'), [
        'email' => ['required', 'email', 'max:254'], 'name' => ['required', 'string', 'max:255'],
        'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'], 'entityName' => ['required', 'string', 'max:255'],
        'password' => [$existing ? 'nullable' : 'required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
    ], [], ['entityName' => 'nama perusahaan (--entity-name)', 'password' => 'kata sandi (--password)', 'code' => 'kode perusahaan']);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) { $this->error($error); }

        return 1;
    }
    if ($existing?->is_demo_guest) { $this->error('Email tersebut dipakai akun tamu demo.'); return 1; }
    DB::transaction(function () use ($existing, $email, $name, $code, $entityName, $password) {
        $entity = Entity::firstOrCreate(['code' => $code], ['name' => $entityName, 'is_bumn' => (bool) $this->option('bumn'),
            'is_public_company' => (bool) $this->option('listed'), 'is_listed' => (bool) $this->option('listed')]);
        $user = $existing ?? User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        if ($existing && $password) { $user->forceFill(['password' => $password, 'mfa_epoch' => (string) Str::uuid()])->save(); }
        $user->entities()->syncWithoutDetaching([$entity->id => ['role' => 'admin']]);
    });
    $this->info("Administrator {$email} siap untuk perusahaan {$code}.".($existing && ! $password ? ' Kata sandi lama tidak diubah.' : ''));
    $this->line('Login di '.rtrim((string) config('app.url'), '/').'/login'.(config('pranata.mfa_required') ? ' lalu daftarkan aplikasi authenticator (MFA) saat login pertama.' : '.'));

    return 0;
})->purpose('Membuat (atau memulihkan) akun administrator perusahaan; bisa dijalankan lewat cron tanpa Terminal.');

Artisan::command('pranata:doctor', function () {
    $ok = true;
    $row = function (string $label, bool $pass, string $detail = '', bool $warnOnly = false) use (&$ok) {
        if (! $pass && ! $warnOnly) { $ok = false; }
        $this->line(($pass ? '<info>[OK]</info>   ' : ($warnOnly ? '<comment>[CEK]</comment>  ' : '<error>[GAGAL]</error> ')).$label.(! $pass && $detail !== '' ? ' — '.$detail : ''));
    };
    $row('PHP '.PHP_VERSION, version_compare(PHP_VERSION, '8.3.0', '>='), 'butuh 8.3 atau lebih baru');
    foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'tokenizer', 'xml', 'dom', 'ctype', 'xmlwriter'] as $ext) {
        $row('Ekstensi '.$ext, extension_loaded($ext), 'aktifkan di Select PHP Version › Extensions');
    }
    $row('APP_KEY', (string) config('app.key') !== '', 'jalankan php artisan key:generate --force');
    try {
        DB::connection()->getPdo();
        $row('Koneksi database ('.config('database.default').')', true);
        $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()->diff(DB::table('migrations')->pluck('migration'));
        $row('Tabel/migrasi', $pending->isEmpty(), $pending->isEmpty() ? '' : $pending->count().' migrasi belum dijalankan: php artisan migrate --force');
        $admins = DB::table('entity_user')->where('role', 'admin')->count();
        $row('Administrator ('.$admins.' akses)', $admins > 0, 'buat dengan php artisan pranata:create-admin', true);
        $row('Akses tamu '.(config('demo.enabled') ? 'aktif' : 'nonaktif'), ! config('demo.enabled') || Entity::where('is_demo', true)->exists(),
            'data contoh belum dimuat: php artisan db:seed --class=DemoSeeder --force', true);
    } catch (\Throwable $e) {
        $row('Koneksi database', false, Str::limit($e->getMessage(), 160));
    }
    foreach ([storage_path('app'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache')] as $dir) {
        $row('Folder dapat ditulis: '.str_replace(base_path().'/', '', $dir), is_dir($dir) && is_writable($dir), 'atur izin 755');
    }
    $row('APP_DEBUG=false', ! config('app.debug'), 'matikan di produksi', true);
    $row('APP_URL '.config('app.url'), str_starts_with((string) config('app.url'), 'https://'), 'sebaiknya https:// dan sama persis dengan alamat situs', true);
    $row('MFA wajib', (bool) config('pranata.mfa_required'), 'PRANATA_MFA_REQUIRED=false; MFA hanya pilihan per pengguna', true);
    $this->newLine();
    $ok ? $this->info('Semua pemeriksaan wajib lulus.') : $this->error('Ada pemeriksaan yang gagal. Perbaiki lalu jalankan ulang.');

    return $ok ? 0 : 1;
})->purpose('Memeriksa kesiapan server (PHP, ekstensi, database, folder, konfigurasi).');

<?php

namespace App\Services;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class MfaService
{
    public function throttle(User $user): void
    {
        $key = 'mfa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Terlalu banyak percobaan MFA. Tunggu satu menit.']);
        }
        RateLimiter::hit($key, 60);
    }

    public function enroll(User $actor, string $secret, string $code, string $password): array
    {
        return DB::transaction(function () use ($actor, $secret, $code, $password) {
            $user = User::lockForUpdate()->findOrFail($actor->id);
            abort_if($user->mfa_confirmed_at, 409, 'MFA sudah aktif.');
            $counter = (new Google2FA)->verifyKeyNewer($secret, $code, 0, 1);
            if (! Hash::check($password, $user->password) || $counter === false) {
                $this->invalid();
            }
            [$plain, $hashes] = $this->codes();
            $user->forceFill(['mfa_secret' => $secret, 'mfa_recovery_hashes' => $hashes, 'mfa_last_counter' => $counter, 'mfa_confirmed_at' => now(), 'mfa_epoch' => (string) Str::uuid()])->save();
            $this->event($user, 'mfa.enrolled');

            return [$user, $plain];
        });
    }

    public function challenge(User $actor, ?string $code, ?string $recovery): User
    {
        return DB::transaction(function () use ($actor, $code, $recovery) {
            $user = User::lockForUpdate()->findOrFail($actor->id);
            abort_unless($user->mfa_confirmed_at, 409, 'MFA belum aktif.');
            if ($code !== null) {
                $this->consumeTotp($user, $code);
                $method = 'totp';
            } else {
                $needle = hash('sha256', strtoupper(trim((string) $recovery)));
                $hashes = $user->mfa_recovery_hashes ?? [];
                $index = null;
                foreach ($hashes as $i => $hash) {
                    if (hash_equals($hash, $needle)) { $index = $i; }
                }
                if ($index === null) { $this->invalid(); }
                unset($hashes[$index]);
                $user->forceFill(['mfa_recovery_hashes' => array_values($hashes)]);
                $method = 'recovery';
            }
            $user->save();
            $this->event($user, 'mfa.verified', ['method' => $method]);

            return $user;
        });
    }

    public function rotate(User $actor, string $password, string $code): array
    {
        return DB::transaction(function () use ($actor, $password, $code) {
            $user = User::lockForUpdate()->findOrFail($actor->id);
            abort_unless($user->mfa_confirmed_at, 409);
            if (! Hash::check($password, $user->password)) { $this->invalid(); }
            $this->consumeTotp($user, $code);
            [$plain, $hashes] = $this->codes();
            $user->forceFill(['mfa_recovery_hashes' => $hashes, 'mfa_epoch' => (string) Str::uuid()])->save();
            $this->event($user, 'mfa.recovery_rotated');

            return [$user, $plain];
        });
    }

    public function verified(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->mfa_confirmed_at && $user->mfa_epoch
            && $request->session()->get('mfa.user_id') === $user->id
            && hash_equals($user->mfa_epoch, (string) $request->session()->get('mfa.epoch', ''));
    }

    public function markVerified(Request $request, User $user): void
    {
        \Illuminate\Support\Facades\Auth::setUser($user);
        $request->session()->regenerate();
        $request->session()->put('mfa', ['user_id' => $user->id, 'epoch' => $user->mfa_epoch]);
        RateLimiter::clear('mfa:'.$user->id);
    }

    private function consumeTotp(User $user, string $code): void
    {
        $counter = (new Google2FA)->verifyKeyNewer($user->mfa_secret, $code, $user->mfa_last_counter ?? 0, 1);
        if ($counter === false) { $this->invalid(); }
        $user->forceFill(['mfa_last_counter' => $counter]);
    }

    private function codes(): array
    {
        $plain = array_map(fn () => strtoupper(bin2hex(random_bytes(10))), range(1, 10));

        return [$plain, array_map(fn ($code) => hash('sha256', $code), $plain)];
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['code' => 'Verifikasi tidak berhasil. Periksa kode, kata sandi, dan waktu perangkat. Kode yang telah digunakan tidak dapat dipakai lagi.']);
    }

    private function event(User $user, string $action, array $metadata = []): void
    {
        SecurityEvent::create(['user_id' => $user->id, 'action' => $action, 'metadata' => $metadata, 'created_at' => now()]);
    }
}

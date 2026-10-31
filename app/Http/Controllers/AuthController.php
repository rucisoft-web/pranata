<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string', 'max:1024']]);
        $data['email'] = Str::lower(trim($data['email']));
        $key = 'login:'.hash('sha256', $data['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba kembali setelah satu menit.']);
        }
        if (! Auth::attempt($data + ['is_demo_guest' => false])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->forget('entity_id');
        $request->session()->forget(['mfa', 'mfa_setup', 'mfa_recovery_plain']);

        if ($request->user()->mfa_confirmed_at) { return redirect()->route('mfa.challenge'); }

        return redirect()->route(config('pranata.mfa_required') ? 'mfa.setup' : 'dashboard');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        $user = $request->user();
        $user->forceFill(['password' => $data['password']])->save();
        \App\Models\SecurityEvent::create(['user_id' => $user->id, 'action' => 'password.changed', 'metadata' => [], 'created_at' => now()]);
        $request->session()->regenerate();

        return redirect()->route('mfa.security')->with('success', 'Kata sandi berhasil diganti.');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

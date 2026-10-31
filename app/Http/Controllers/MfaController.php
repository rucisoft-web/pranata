<?php

namespace App\Http\Controllers;

use App\Services\MfaService;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class MfaController extends Controller
{
    public function setup(Request $request)
    {
        if ($request->user()->mfa_confirmed_at) { return redirect()->route('mfa.challenge'); }
        if (! $request->session()->has('mfa_setup.secret') || $request->session()->get('mfa_setup.expires', 0) < time()) {
            $request->session()->put('mfa_setup', ['secret' => (new Google2FA)->generateSecretKey(32), 'expires' => time() + 600]);
        }
        $secret = $request->session()->get('mfa_setup.secret');
        $uri = (new Google2FA)->getQRCodeUrl('PRANATA', $request->user()->email, $secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(240), new SvgImageBackEnd)))->writeString($uri);
        $qr = 'data:image/svg+xml;base64,'.base64_encode($svg);

        return view('auth.mfa-setup', compact('secret', 'qr'));
    }

    public function enroll(Request $request, MfaService $mfa)
    {
        $mfa->throttle($request->user());
        $data = $request->validate(['code' => ['required', 'regex:/^[0-9]{6}$/'], 'current_password' => ['required', 'string', 'max:1024']]);
        abort_unless($request->session()->get('mfa_setup.expires', 0) >= time() && $request->session()->has('mfa_setup.secret'), 419, 'Pengaturan MFA kedaluwarsa. Muat ulang halaman.');
        [$user, $codes] = $mfa->enroll($request->user(), $request->session()->get('mfa_setup.secret'), $data['code'], $data['current_password']);
        $request->session()->forget('mfa_setup');
        $mfa->markVerified($request, $user);
        $request->session()->put('mfa_recovery_plain', $codes);

        return redirect()->route('mfa.recovery');
    }

    public function challenge(Request $request, MfaService $mfa)
    {
        if (! $request->user()->mfa_confirmed_at) { return redirect()->route(config('pranata.mfa_required') ? 'mfa.setup' : 'dashboard'); }
        if ($mfa->verified($request)) { return redirect()->route('dashboard'); }

        return view('auth.mfa-challenge');
    }

    public function verify(Request $request, MfaService $mfa)
    {
        $mfa->throttle($request->user());
        $data = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'prohibits:recovery_code', 'regex:/^[0-9]{6}$/'],
            'recovery_code' => ['nullable', 'required_without:code', 'prohibits:code', 'string', 'max:40'],
        ]);
        $user = $mfa->challenge($request->user(), $data['code'] ?? null, $data['recovery_code'] ?? null);
        $mfa->markVerified($request, $user);

        return redirect()->route('dashboard');
    }

    public function security(Request $request)
    {
        return view('auth.mfa-security', ['remaining' => count($request->user()->mfa_recovery_hashes ?? [])]);
    }

    public function recovery(Request $request)
    {
        return view('auth.mfa-recovery', ['codes' => $request->session()->pull('mfa_recovery_plain', [])]);
    }

    public function rotate(Request $request, MfaService $mfa)
    {
        $mfa->throttle($request->user());
        $data = $request->validate(['current_password' => ['required', 'string', 'max:1024'], 'code' => ['required', 'regex:/^[0-9]{6}$/']]);
        [$user, $codes] = $mfa->rotate($request->user(), $data['current_password'], $data['code']);
        $mfa->markVerified($request, $user);
        $request->session()->put('mfa_recovery_plain', $codes);

        return redirect()->route('mfa.recovery');
    }
}

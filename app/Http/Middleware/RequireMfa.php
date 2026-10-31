<?php

namespace App\Http\Middleware;

use App\Services\MfaService;
use Closure;
use Illuminate\Http\Request;

class RequireMfa
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->user()->mfa_confirmed_at) {
            return config('pranata.mfa_required') ? redirect()->route('mfa.setup') : $next($request);
        }
        if (! app(MfaService::class)->verified($request)) { return redirect()->route('mfa.challenge'); }

        return $next($request);
    }
}

<?php
namespace App\Http\Middleware;

use App\Models\Entity;
use App\Models\User;
use App\Support\EntityContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless(config('demo.enabled') && in_array($request->method(), ['GET', 'HEAD'], true), 404);
        $selection = $request->validate(['entity' => ['nullable', 'integer', 'min:1']]);
        $id = $selection['entity'] ?? $request->session()->get('demo_entity_id');
        $q = Entity::where('is_demo', true)->orderBy('id');
        $entity = $id ? $q->findOrFail($id) : $q->first();
        abort_unless($entity, 503, 'Data contoh belum disiapkan. Jalankan DemoSeeder.');
        $guest = User::where('email', 'guest@demo.pranata.example')->where('is_demo_guest', true)->first();
        abort_unless($guest && $guest->entities()->where('entities.id', $entity->id)->exists(), 503, 'Akses data contoh belum disiapkan.');
        $request->session()->put('demo_entity_id', $entity->id);
        $request->attributes->set('demo_mode', true);
        app()->instance(EntityContext::class, new EntityContext($entity, 'demo_viewer'));
        $previous = Auth::getDefaultDriver();
        Auth::shouldUse('demo');
        Auth::guard('demo')->setUser($guest);
        try { return $next($request); }
        finally { Auth::guard('demo')->forgetUser(); Auth::shouldUse($previous); }
    }
}

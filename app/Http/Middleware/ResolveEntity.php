<?php

namespace App\Http\Middleware;

use App\Support\EntityContext;
use Closure;
use Illuminate\Http\Request;

class ResolveEntity
{
    public function handle(Request $request, Closure $next): mixed
    {
        $query = $request->user()->entities()->orderBy('entities.id');
        $id = $request->session()->get('entity_id');
        $entity = $id ? $query->where('entities.id', $id)->first() : $query->first();
        abort_unless($entity, 403, 'Akses perusahaan belum diberikan atau telah dicabut.');
        $request->session()->put('entity_id', $entity->id);
        app()->instance(EntityContext::class, new EntityContext($entity, $entity->pivot->role));

        return $next($request);
    }
}

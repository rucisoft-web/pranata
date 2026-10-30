<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EntityController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate(['entity_id' => ['required', 'integer']]);
        abort_unless($request->user()->entities()->where('entities.id', $data['entity_id'])->exists(), 403);
        $request->session()->put('entity_id', (int) $data['entity_id']);

        return redirect()->route('dashboard');
    }
}

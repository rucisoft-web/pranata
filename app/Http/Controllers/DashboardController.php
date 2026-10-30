<?php

namespace App\Http\Controllers;

use App\Models\Risk;
use App\Support\EntityContext;

class DashboardController extends Controller
{
    public function __invoke(EntityContext $context)
    {
        $query = Risk::where('entity_id', $context->entity->id);
        $stats = [
            'total' => (clone $query)->count(),
            'high' => (clone $query)->whereRaw('likelihood * impact >= ?', [12])->count(),
            'submitted' => (clone $query)->where('status', 'submitted')->count(),
            'overdue' => (clone $query)->whereDate('review_date', '<', today())->count(),
        ];
        $latest = (clone $query)->latest('updated_at')->limit(8)->get();

        return view('dashboard', compact('context', 'stats', 'latest'));
    }
}

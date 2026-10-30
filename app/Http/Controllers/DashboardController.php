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
        $heatmap = (clone $query)->selectRaw('likelihood, impact, COUNT(*) as total')->groupBy('likelihood', 'impact')->get()->keyBy(fn ($r) => $r->likelihood.'-'.$r->impact);
        $compliance = \App\Models\ObligationOccurrence::where('entity_id', $context->entity->id);
        $complianceStats = ['total' => (clone $compliance)->count(), 'pending' => (clone $compliance)->where('status', 'submitted')->count(), 'late' => (clone $compliance)->where('status', '!=', 'fulfilled')->whereDate('due_date', '<', today())->count()];
        $findings = \App\Models\AuditFinding::where('entity_id', $context->entity->id);
        if (! $context->canManageAudit() && $context->role !== 'demo_viewer') {
            $findings->where(function ($q) use ($context) {
                $q->where('responsible_id', auth()->id());
                if ($context->role === 'auditor') { $q->orWhereHas('engagement', fn ($a) => $a->where('lead_id', auth()->id())); }
            });
        }
        $findingStats = ['open' => (clone $findings)->whereNotIn('status', ['draft', 'closed'])->count(), 'late' => (clone $findings)->whereNotIn('status', ['draft', 'closed'])->whereDate('due_date', '<', today())->count()];

        return view('dashboard', compact('context', 'stats', 'latest', 'heatmap', 'complianceStats', 'findingStats'));
    }
}

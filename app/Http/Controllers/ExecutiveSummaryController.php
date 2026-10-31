<?php

namespace App\Http\Controllers;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\Entity;
use App\Models\ObligationOccurrence;
use App\Models\Risk;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExecutiveSummaryController extends Controller
{
    public const LEVELS = ['Kritis' => 20, 'Tinggi' => 12, 'Sedang' => 6, 'Rendah' => 1];

    public function __invoke(Request $request, EntityContext $context)
    {
        abort_unless($context->canReadExecutive(), 403);
        $entity = $context->entity;
        $summary = $this->summarize($entity);

        $groupEntities = $request->attributes->get('demo_mode')
            ? Entity::where('is_demo', true)->orderBy('name')->get()
            : $request->user()->entities()->wherePivotIn('role', ['admin', 'risk_manager', 'compliance_officer', 'audit_manager'])->orderBy('name')->get();
        $group = $groupEntities->count() > 1
            ? $groupEntities->map(fn (Entity $e) => ['entity' => $e] + $this->summarize($e)['headline'])
            : collect();

        return view('executive.summary', compact('context', 'group') + $summary + ['narrative' => $this->narrative($summary)]);
    }

    public function summarize(Entity $entity): array
    {
        $risks = Risk::where('entity_id', $entity->id)->get();
        $level = fn (Risk $r) => $r->level;
        $byLevel = collect(array_keys(self::LEVELS))->mapWithKeys(fn ($l) => [$l => $risks->filter(fn ($r) => $level($r) === $l)->count()]);
        $byCategory = $risks->groupBy('category')->map(fn (Collection $items) => collect(array_keys(self::LEVELS))->mapWithKeys(fn ($l) => [$l => $items->filter(fn ($r) => $level($r) === $l)->count()]))
            ->sortByDesc(fn ($levels) => $levels->sum())->take(8);
        $topRisks = $risks->sortByDesc(fn (Risk $r) => $r->score * 100 + $r->impact)->take(6)->values();

        $occurrences = ObligationOccurrence::with('obligation')->where('entity_id', $entity->id)->get();
        $due = $occurrences->filter(fn ($o) => $o->due_date->lt(today()));
        $onTime = $due->filter(fn ($o) => $o->status === 'fulfilled' && $o->fulfilled_at && $o->fulfilled_at->lte($o->due_date->copy()->endOfDay()));
        $complianceStatus = collect(config('modules.compliance_statuses'))->map(fn ($label, $key) => $occurrences->where('status', $key)->count());
        $trend = collect(range(5, 0))->map(function ($ago) use ($occurrences) {
            $month = today()->startOfMonth()->subMonths($ago);
            $items = $occurrences->filter(fn ($o) => $o->due_date->isSameMonth($month));
            $fulfilled = $items->filter(fn ($o) => $o->status === 'fulfilled')->count();

            return ['label' => $this->monthLabel($month), 'total' => $items->count(), 'fulfilled' => $fulfilled,
                'rate' => $items->count() ? (int) round($fulfilled / $items->count() * 100) : null];
        });
        $overdueObligations = $occurrences->filter(fn ($o) => $o->status !== 'fulfilled' && $o->due_date->lt(today()))->sortBy('due_date')->take(5)->values();

        $audits = AuditEngagement::where('entity_id', $entity->id)->get();
        $findings = AuditFinding::where('entity_id', $entity->id)->where('status', '!=', 'draft')->get();
        $auditStatus = collect(config('modules.audit_statuses'))->map(fn ($label, $key) => $audits->where('status', $key)->count());
        $findingSeverity = collect(config('modules.criticalities'))->reverse()->map(fn ($label, $key) => [
            'open' => $findings->where('severity', $key)->where('status', '!=', 'closed')->count(),
            'closed' => $findings->where('severity', $key)->where('status', 'closed')->count(),
        ]);
        $openFindings = $findings->where('status', '!=', 'closed');

        $headline = [
            'risk_total' => $risks->count(),
            'risk_high' => $byLevel['Kritis'] + $byLevel['Tinggi'],
            'risk_approved' => $risks->where('status', 'approved')->count(),
            'risk_review_overdue' => $risks->filter(fn ($r) => $r->review_date->lt(today()))->count(),
            'compliance_rate' => $due->count() ? (int) round($onTime->count() / $due->count() * 100) : null,
            'compliance_overdue' => $occurrences->filter(fn ($o) => $o->status !== 'fulfilled' && $o->due_date->lt(today()))->count(),
            'compliance_upcoming' => $occurrences->filter(fn ($o) => $o->status !== 'fulfilled' && $o->due_date->between(today(), today()->addDays(30)))->count(),
            'audit_active' => $audits->whereIn('status', ['fieldwork', 'review'])->count(),
            'finding_open' => $openFindings->count(),
            'finding_overdue' => $openFindings->filter(fn ($f) => $f->due_date->lt(today()))->count(),
            'finding_closure' => $findings->count() ? (int) round($findings->where('status', 'closed')->count() / $findings->count() * 100) : null,
        ];

        return compact('headline', 'byLevel', 'byCategory', 'topRisks', 'complianceStatus', 'trend', 'overdueObligations', 'auditStatus', 'findingSeverity');
    }

    private function narrative(array $s): array
    {
        $h = $s['headline'];
        $topCategory = $s['byCategory']->sortByDesc(fn ($l) => $l['Kritis'] + $l['Tinggi'])->keys()->first();
        $lines = [];
        $lines[] = $h['risk_total']
            ? "Register memuat {$h['risk_total']} risiko; {$h['risk_high']} di antaranya berlevel tinggi atau kritis".($topCategory ? ", paling banyak pada kategori {$topCategory}." : '.')
            : 'Register risiko belum berisi catatan.';
        if ($s['topRisks']->isNotEmpty()) {
            $top = $s['topRisks']->first();
            $lines[] = "Risiko dengan skor tertinggi adalah \"{$top->title}\" (skor {$top->score}, {$top->level}); pastikan rencana penanganannya dipantau Direksi.";
        }
        if ($h['compliance_rate'] !== null) {
            $lines[] = "Tingkat pemenuhan kewajiban tepat waktu {$h['compliance_rate']}%; {$h['compliance_overdue']} kewajiban lewat tenggat dan {$h['compliance_upcoming']} jatuh tempo dalam 30 hari.";
        }
        $lines[] = "Audit internal: {$h['audit_active']} penugasan berjalan, {$h['finding_open']} temuan terbuka"
            .($h['finding_overdue'] ? " ({$h['finding_overdue']} melewati tenggat tindak lanjut)" : '')
            .($h['finding_closure'] !== null ? ", tingkat penyelesaian temuan {$h['finding_closure']}%." : '.');
        if ($h['risk_review_overdue']) { $lines[] = "{$h['risk_review_overdue']} risiko melewati jadwal review dan perlu dinilai ulang oleh pemiliknya."; }

        return $lines;
    }

    private function monthLabel(Carbon $month): string
    {
        return ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$month->month].' '.$month->format('y');
    }
}

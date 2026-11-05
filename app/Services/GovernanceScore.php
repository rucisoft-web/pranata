<?php
namespace App\Services;

use App\Models\GovernanceAssessment;
use Illuminate\Support\Collection;

class GovernanceScore
{
    /** Ringkasan penerapan: jumlah per status, indeks, verifikasi, dan sebaran per aspek. */
    public function summarize(GovernanceAssessment $assessment, ?Collection $items = null): array
    {
        $items ??= $assessment->items()->get();
        $count = fn (Collection $set, string $status) => $set->where('status', $status)->count();
        $index = fn (Collection $set) => $set->count() ? (int) round(($count($set, 'applied') + 0.5 * $count($set, 'partial')) / $set->count() * 100) : 0;
        $byAspect = collect(config('governance.aspects'))->map(function ($label, $aspect) use ($items, $count, $index) {
            $set = $items->filter(fn ($i) => $i->aspect() === $aspect);

            return ['label' => config('governance.short_aspects')[$aspect], 'total' => $set->count(), 'index' => $index($set)]
                + collect(array_keys(config('governance.statuses')))->mapWithKeys(fn ($s) => [$s => $count($set, $s)])->all();
        });

        return [
            'total' => $items->count(),
            'applied' => $count($items, 'applied'), 'partial' => $count($items, 'partial'),
            'not_applied' => $count($items, 'not_applied'), 'not_assessed' => $count($items, 'not_assessed'),
            'verified' => $items->where('review_status', 'verified')->count(),
            'submitted' => $items->where('review_status', 'submitted')->count(),
            'unassigned' => $items->whereNull('pic_id')->count(),
            'index' => $index($items),
            'by_aspect' => $byAspect,
        ];
    }
}

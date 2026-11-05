<?php
namespace App\Services;

use App\Models\QuestionnaireCampaign;
use Illuminate\Support\Collection;

class QuestionnaireResults
{
    /** Rekap hasil: skor per pertanyaan dan bagian, jawaban ya/tidak, isian, dan pengecualian bernama (non-anonim). */
    public function summarize(QuestionnaireCampaign $campaign, ?Collection $responses = null): array
    {
        $responses ??= $campaign->responses()->with('assignment.user')->get();
        $count = $responses->count();
        $hidden = $campaign->anonymous && $count < config('questionnaires.min_anonymous_responses');
        $questions = []; $sections = []; $exceptions = [];
        foreach ($campaign->questions() as $key => [$section, $type, $text, $flag]) {
            $values = $responses->map(fn ($r) => $r->answers[$key]['value'] ?? null)->filter(fn ($v) => $v !== null && $v !== '');
            $row = ['section' => $section, 'type' => $type, 'text' => $text, 'flag' => $flag, 'answered' => $values->count()];
            if ($type === 'scale') {
                $row['average'] = $values->count() ? round($values->avg(), 2) : null;
                $row['distribution'] = collect(range(1, 5))->mapWithKeys(fn ($s) => [$s => $values->filter(fn ($v) => (int) $v === $s)->count()])->all();
                $sections[$section][] = $row['average'];
            } elseif ($type === 'yesno') {
                $row['yes'] = $values->filter(fn ($v) => $v === 'yes')->count();
                $row['no'] = $values->filter(fn ($v) => $v === 'no')->count();
                $row['exceptions'] = $flag ? $values->filter(fn ($v) => $v === $flag)->count() : 0;
            } else {
                // Isian bebas diacak agar tidak dapat dicocokkan dengan urutan pengisian.
                $row['texts'] = $values->values()->shuffle()->all();
            }
            $questions[$key] = $row;
            if ($flag && ! $campaign->anonymous) {
                foreach ($responses as $response) {
                    if (($response->answers[$key]['value'] ?? null) === $flag) {
                        $exceptions[] = ['user' => $response->assignment?->user?->name ?? 'Responden', 'question' => $text, 'section' => $section, 'note' => $response->answers[$key]['note'] ?? ''];
                    }
                }
            }
        }
        $sectionAverages = collect($sections)->map(fn ($avgs) => collect($avgs)->filter()->count() ? round(collect($avgs)->filter()->avg(), 2) : null);
        $scaleAverages = collect($questions)->where('type', 'scale')->pluck('average')->filter();

        return [
            'responses' => $count, 'hidden' => $hidden,
            'overall' => $scaleAverages->count() ? round($scaleAverages->avg(), 2) : null,
            'sections' => $sectionAverages->all(), 'questions' => $questions,
            'exceptions' => $exceptions, 'exception_total' => (int) $responses->sum('exceptions'),
        ];
    }
}

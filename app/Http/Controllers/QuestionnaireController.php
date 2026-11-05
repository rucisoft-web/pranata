<?php
namespace App\Http\Controllers;

use App\Models\QuestionnaireAssignment;
use App\Models\QuestionnaireCampaign;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\ChangeLog;
use App\Services\QuestionnaireResults;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuestionnaireController extends Controller
{
    public function index(Request $request, EntityContext $context, QuestionnaireResults $results)
    {
        $readAll = $this->canReadResults($context);
        $campaigns = QuestionnaireCampaign::withCount(['assignments', 'assignments as submitted_count' => fn ($q) => $q->where('status', 'submitted')])
            ->where('entity_id', $context->entity->id)
            ->when(! $readAll, fn ($q) => $q->whereHas('assignments', fn ($q) => $q->where('user_id', $request->user()->id)))
            ->orderByDesc('year')->orderByDesc('id')->get();
        $scores = $readAll ? $campaigns->where('status', 'closed')->mapWithKeys(fn ($c) => [$c->id => $results->summarize($c)]) : collect();
        $mine = $this->demo($request) ? collect() : QuestionnaireAssignment::with('campaign')->where('entity_id', $context->entity->id)
            ->where('user_id', $request->user()->id)->where('status', 'pending')->whereHas('campaign', fn ($q) => $q->where('status', 'open'))->get();

        return view('questionnaires.index', compact('context', 'campaigns', 'scores', 'mine', 'readAll'));
    }

    public function create(Request $request, EntityContext $context)
    {
        abort_unless($this->canManage($request, $context), 403);
        $template = $request->validate(['template' => ['required', Rule::in(array_keys(config('questionnaires.templates')))]])['template'];
        $definition = config('questionnaires.templates')[$template];
        $members = $this->members($context);

        return view('questionnaires.create', compact('context', 'template', 'definition', 'members'));
    }

    public function store(Request $request, EntityContext $context, ChangeLog $log)
    {
        abort_unless($this->canManage($request, $context), 403);
        $data = $request->validate([
            'template' => ['required', Rule::in(array_keys(config('questionnaires.templates')))],
            'title' => ['required', 'string', 'min:5', 'max:255'], 'year' => ['required', 'integer', 'min:2015', 'max:2100'],
            'due_date' => ['required', 'date', 'after_or_equal:today'], 'instructions' => ['nullable', 'string', 'max:5000'],
            'respondents' => ['required', 'array', 'min:1'],
            'respondents.*' => ['integer', Rule::exists('entity_user', 'user_id')->where(fn ($q) => $q->where('entity_id', $context->entity->id)->whereIn('role', config('questionnaires.roles')))],
        ]);
        $anonymous = config('questionnaires.templates')[$data['template']]['anonymous'];
        $minimum = config('questionnaires.min_anonymous_responses');
        if ($anonymous && count(array_unique($data['respondents'])) < $minimum) {
            throw ValidationException::withMessages(['respondents' => "Kuesioner anonim memerlukan paling sedikit {$minimum} responden agar jawaban tidak dapat dikenali."]);
        }
        $campaign = DB::transaction(function () use ($data, $anonymous, $request, $context, $log) {
            $campaign = QuestionnaireCampaign::create(collect($data)->only(['template', 'title', 'year', 'due_date', 'instructions'])->all()
                + ['entity_id' => $context->entity->id, 'anonymous' => $anonymous, 'created_by' => $request->user()->id]);
            foreach (array_unique($data['respondents']) as $userId) {
                QuestionnaireAssignment::create(['entity_id' => $context->entity->id, 'questionnaire_campaign_id' => $campaign->id, 'user_id' => $userId]);
            }
            $log->record('questionnaire.opened', $campaign, $request->user());

            return $campaign;
        });

        return redirect()->route('questionnaires.show', $campaign)->with('success', 'Kuesioner dibuka untuk '.$campaign->assignments()->count().' responden.');
    }

    public function show(Request $request, int $campaign, EntityContext $context, QuestionnaireResults $results)
    {
        $campaign = QuestionnaireCampaign::with('assignments.user')->where('entity_id', $context->entity->id)->findOrFail($campaign);
        $readAll = $this->canReadResults($context);
        $mine = $this->demo($request) ? null : $campaign->assignments->firstWhere('user_id', $request->user()->id);
        abort_unless($readAll || $mine, 404);
        $summary = $readAll ? $results->summarize($campaign) : null;
        $previous = $readAll ? QuestionnaireCampaign::where('entity_id', $context->entity->id)->where('template', $campaign->template)
            ->where('status', 'closed')->where('year', '<', $campaign->year)->orderByDesc('year')->first() : null;
        $previousSummary = $previous ? $results->summarize($previous) : null;
        $governance = $this->governanceItems($campaign);
        $manage = $this->canManage($request, $context);

        return view('questionnaires.show', compact('context', 'campaign', 'readAll', 'mine', 'summary', 'previous', 'previousSummary', 'governance', 'manage'));
    }

    public function respond(Request $request, int $campaign, EntityContext $context)
    {
        [$campaign, $assignment] = $this->assignment($request, $campaign, $context);

        return view('questionnaires.respond', compact('context', 'campaign', 'assignment'));
    }

    public function submit(Request $request, int $campaign, EntityContext $context, ChangeLog $log)
    {
        $stub = QuestionnaireCampaign::where('entity_id', $context->entity->id)->findOrFail($campaign);
        $rules = []; $messages = [];
        foreach ($stub->questions() as $key => [, $type, $text, $flag]) {
            $rules["answers.$key.value"] = match ($type) { 'scale' => ['required', 'integer', 'between:1,5'], 'yesno' => ['required', Rule::in(['yes', 'no'])], default => ['nullable', 'string', 'max:5000'] };
            if ($flag) {
                $rules["answers.$key.note"] = ['nullable', 'string', 'max:5000', "required_if:answers.$key.value,$flag", 'min:5'];
                $messages["answers.$key.note.required_if"] = 'Jelaskan jawaban ini: '.\Illuminate\Support\Str::limit($text, 80);
            }
            $messages["answers.$key.value.required"] = 'Pertanyaan wajib dijawab: '.\Illuminate\Support\Str::limit($text, 80);
        }
        $data = $request->validate($rules, $messages);
        DB::transaction(function () use ($request, $campaign, $context, $data, $log) {
            [$record, $assignment] = $this->assignment($request, $campaign, $context, true);
            $answers = []; $exceptions = 0;
            foreach ($record->questions() as $key => [, $type, , $flag]) {
                $value = $data['answers'][$key]['value'] ?? null;
                $answers[$key] = ['value' => $type === 'scale' ? (int) $value : $value] + (isset($data['answers'][$key]['note']) && $data['answers'][$key]['note'] !== '' ? ['note' => $data['answers'][$key]['note']] : []);
                if ($flag && $value === $flag) { $exceptions++; }
            }
            QuestionnaireResponse::create(['entity_id' => $context->entity->id, 'questionnaire_campaign_id' => $record->id,
                'questionnaire_assignment_id' => $record->anonymous ? null : $assignment->id, 'answers' => $answers, 'exceptions' => $exceptions]);
            $assignment->fill(['status' => 'submitted', 'submitted_at' => $record->anonymous ? today() : now()])->save();
            $log->record('questionnaire.submitted', $assignment, $request->user());
        });

        return redirect()->route('questionnaires.index')->with('success', 'Terima kasih, jawaban Anda telah dikirim.'.($stub->anonymous ? ' Jawaban disimpan tanpa identitas.' : ''));
    }

    public function close(Request $request, int $campaign, EntityContext $context, ChangeLog $log)
    {
        abort_unless($this->canManage($request, $context), 403);
        DB::transaction(function () use ($request, $campaign, $context, $log) {
            $record = QuestionnaireCampaign::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($campaign);
            abort_unless($record->isOpen(), 409);
            abort_if($record->responses()->count() === 0, 422, 'Belum ada jawaban yang masuk.');
            $before = $record->toArray();
            $record->fill(['status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => now()])->save();
            $log->record('questionnaire.closed', $record, $request->user(), $before);
        });

        return redirect()->route('questionnaires.show', $campaign)->with('success', 'Kuesioner ditutup. Hasilnya kini menjadi bukti pada rekomendasi Governance terkait.');
    }

    public function export(int $campaign, EntityContext $context, QuestionnaireResults $results)
    {
        abort_unless($this->canReadResults($context), 403);
        $campaign = QuestionnaireCampaign::where('entity_id', $context->entity->id)->findOrFail($campaign);
        $s = $results->summarize($campaign);
        abort_if($s['hidden'], 409, 'Hasil kuesioner anonim baru dapat diekspor setelah jumlah tanggapan mencukupi.');
        $rows = [['Bagian', 'Pertanyaan', 'Jenis', 'Dijawab', 'Rata-rata', 'Ya', 'Tidak', 'Pengecualian']];
        foreach ($s['questions'] as $q) {
            $rows[] = [$q['section'], $q['text'], ['scale' => 'Skala 1-5', 'yesno' => 'Ya/Tidak', 'text' => 'Isian'][$q['type']], $q['answered'],
                isset($q['average']) ? number_format($q['average'], 2, ',', '') : '', $q['yes'] ?? '', $q['no'] ?? '', $q['exceptions'] ?? ''];
        }
        if ($s['exceptions']) {
            $rows[] = []; $rows[] = ['Pengecualian', 'Responden', 'Pertanyaan', 'Keterangan'];
            foreach ($s['exceptions'] as $e) { $rows[] = [$e['section'], $e['user'], $e['question'], $e['note']]; }
        }
        $out = fopen('php://temp', 'r+'); fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) { fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : (string) $v, $row), ';'); }
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="hasil-'.\Illuminate\Support\Str::slug($campaign->title).'-'.$campaign->year.'.csv"']);
    }

    /** Rekomendasi Governance tahun yang sama yang memakai hasil kuesioner ini sebagai bukti. */
    private function governanceItems(QuestionnaireCampaign $campaign): Collection
    {
        return \App\Models\GovernanceItem::where('entity_id', $campaign->entity_id)->whereIn('code', $campaign->definition()['governance'])
            ->whereHas('assessment', fn ($q) => $q->where('year', $campaign->year))->get();
    }

    private function assignment(Request $request, int $campaign, EntityContext $context, bool $lock = false): array
    {
        abort_if($this->demo($request), 403);
        $q = QuestionnaireCampaign::where('entity_id', $context->entity->id);
        $record = ($lock ? $q->lockForUpdate() : $q)->findOrFail($campaign);
        $q = QuestionnaireAssignment::where('questionnaire_campaign_id', $record->id)->where('user_id', $request->user()->id);
        $assignment = ($lock ? $q->lockForUpdate() : $q)->first();
        abort_unless($assignment, 404);
        abort_unless($record->isOpen(), 409, 'Kuesioner sudah ditutup.');
        abort_unless($assignment->status === 'pending', 409, 'Anda sudah mengirim jawaban untuk kuesioner ini.');

        return [$record, $assignment];
    }

    private function canManage(Request $request, EntityContext $context): bool
    {
        return $context->canManageGovernance() && ! $this->demo($request);
    }

    private function canReadResults(EntityContext $context): bool
    {
        return $context->canManageGovernance() || $context->role === 'demo_viewer';
    }

    private function demo(Request $request): bool
    {
        return (bool) $request->attributes->get('demo_mode');
    }

    private function members(EntityContext $context): Collection
    {
        return User::whereHas('entities', fn ($q) => $q->where('entities.id', $context->entity->id)->whereIn('entity_user.role', config('questionnaires.roles')))
            ->orderBy('name')->get()->each(fn ($u) => $u->role_here = $u->roleIn($context->entity->id));
    }
}

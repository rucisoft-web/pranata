<?php
namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\GovernanceAction;
use App\Models\GovernanceAssessment;
use App\Models\GovernanceItem;
use App\Models\User;
use App\Services\ChangeLog;
use App\Services\GovernanceScore;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GovernanceController extends Controller
{
    /** Peran yang dapat ditunjuk sebagai PIC rekomendasi atau tindak lanjut. */
    public const PIC_ROLES = ['admin', 'risk_manager', 'risk_owner', 'compliance_officer', 'compliance_owner', 'audit_manager'];

    public function index(EntityContext $context, GovernanceScore $score)
    {
        $assessments = GovernanceAssessment::with('items')->where('entity_id', $context->entity->id)->orderByDesc('year')->get();
        $scores = $assessments->mapWithKeys(fn ($a) => [$a->id => $score->summarize($a, $a->items)]);
        $members = $this->members($context);
        $nextYear = ($assessments->max('year') ?? (int) now()->format('Y') - 1) + 1;

        return view('governance.index', compact('context', 'assessments', 'scores', 'members', 'nextYear'));
    }

    public function store(Request $request, EntityContext $context, ChangeLog $log)
    {
        abort_unless($context->canManageGovernance(), 403);
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2015', 'max:2100', Rule::unique('governance_assessments')->where('entity_id', $context->entity->id)],
            'coordinator_id' => ['required', 'integer', $this->memberRule($context, ['admin', 'compliance_officer'])],
            'copy_previous' => ['nullable', 'boolean'],
        ], ['year.unique' => 'Penilaian untuk tahun buku ini sudah ada.']);
        $assessment = DB::transaction(function () use ($data, $request, $context, $log) {
            $assessment = GovernanceAssessment::create(['entity_id' => $context->entity->id, 'year' => $data['year'], 'coordinator_id' => $data['coordinator_id'], 'created_by' => $request->user()->id]);
            $previous = ! empty($data['copy_previous'])
                ? GovernanceAssessment::where('entity_id', $context->entity->id)->where('year', '<', $data['year'])->orderByDesc('year')->first()?->items()->get()->keyBy('code')
                : null;
            $eligible = $this->members($context)->pluck('id');
            foreach (array_keys(config('governance.recommendations')) as $code) {
                $old = $previous?->get($code);
                GovernanceItem::create(['entity_id' => $context->entity->id, 'governance_assessment_id' => $assessment->id, 'code' => $code] + ($old ? [
                    'status' => $old->status, 'pic_id' => $eligible->contains($old->pic_id) ? $old->pic_id : null,
                    'implementation' => $old->implementation, 'evidence_note' => $old->evidence_note,
                    'explanation' => $old->explanation, 'alternative_practice' => $old->alternative_practice,
                    'target_date' => $old->target_date?->gt(today()) ? $old->target_date : null,
                ] : []));
            }
            $log->record('governance.assessment_created', $assessment, $request->user());

            return $assessment;
        });

        return redirect()->route('governance.show', $assessment)->with('success', 'Penilaian '.$assessment->year.' dibuat dengan 25 rekomendasi.'.(! empty($data['copy_previous']) ? ' Isian tahun sebelumnya disalin; bukti berkas perlu diunggah ulang.' : ''));
    }

    public function show(Request $request, int $assessment, EntityContext $context, GovernanceScore $score)
    {
        $assessment = $this->assessment($assessment, $context);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(config('governance.statuses')))],
            'review' => ['nullable', Rule::in(array_keys(config('governance.review_statuses')))],
            'aspect' => ['nullable', 'integer', Rule::in(array_keys(config('governance.aspects')))],
            'pic' => ['nullable', 'string', 'max:20'],
        ]);
        $items = $this->items($assessment);
        $summary = $score->summarize($assessment, $items);
        $previous = GovernanceAssessment::where('entity_id', $context->entity->id)->where('year', '<', $assessment->year)->orderByDesc('year')->first();
        $previousItems = $previous ? $previous->items()->get()->keyBy('code') : collect();
        $previousSummary = $previous ? $score->summarize($previous, $previousItems->values()) : null;
        $evidenceCounts = EvidenceFile::where('entity_id', $context->entity->id)->where('subject_type', 'governance')->whereIn('subject_id', $items->pluck('id'))
            ->selectRaw('subject_id, count(*) as total')->groupBy('subject_id')->pluck('total', 'subject_id');
        $visible = $items->filter(fn (GovernanceItem $i) => (! ($filters['status'] ?? null) || $i->status === $filters['status'])
            && (! ($filters['review'] ?? null) || $i->review_status === $filters['review'])
            && (! ($filters['aspect'] ?? null) || $i->aspect() === (int) $filters['aspect'])
            && (! ($filters['pic'] ?? null) || ($filters['pic'] === 'none' ? $i->pic_id === null : $i->pic_id === (int) $filters['pic'])));
        $actions = $items->flatMap->actions;
        $members = $this->members($context);

        return view('governance.show', compact('context', 'assessment', 'items', 'visible', 'summary', 'previous', 'previousItems', 'previousSummary', 'evidenceCounts', 'actions', 'members'));
    }

    public function assign(Request $request, int $assessment, EntityContext $context, ChangeLog $log)
    {
        abort_unless($context->canManageGovernance(), 403);
        $data = $request->validate(['pic' => ['required', 'array'], 'pic.*' => ['nullable', 'integer', $this->memberRule($context, self::PIC_ROLES)]]);
        $changed = DB::transaction(function () use ($assessment, $data, $request, $context, $log) {
            $record = GovernanceAssessment::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($assessment);
            abort_if($record->isLocked(), 409, 'Penilaian telah disetujui dan dikunci.');
            $changed = 0;
            foreach ($record->items()->lockForUpdate()->get() as $item) {
                if (! array_key_exists($item->code, $data['pic'])) { continue; }
                $pic = $data['pic'][$item->code] ? (int) $data['pic'][$item->code] : null;
                if ($pic === $item->pic_id || ! $item->editable()) { continue; }
                $before = $item->toArray();
                $item->pic_id = $pic; $item->revision++; $item->save();
                $log->record('governance.item_updated', $item, $request->user(), $before);
                $changed++;
            }

            return $changed;
        });

        return redirect()->route('governance.show', $assessment)->with('success', $changed.' penugasan PIC diperbarui.');
    }

    public function approve(Request $request, int $assessment, EntityContext $context, ChangeLog $log)
    {
        abort_unless($context->canManageGovernance(), 403);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'note' => ['required', 'string', 'min:10', 'max:10000']]);
        DB::transaction(function () use ($assessment, $data, $request, $context, $log) {
            $record = GovernanceAssessment::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($assessment);
            abort_unless($record->revision === (int) $data['revision'], 409);
            abort_if($record->isLocked(), 409, 'Penilaian sudah disetujui.');
            $pending = $record->items()->where('review_status', '!=', 'verified')->count();
            abort_if($pending > 0, 422, $pending.' rekomendasi belum terverifikasi. Seluruh 25 rekomendasi harus terverifikasi sebelum disetujui.');
            $before = $record->toArray();
            $record->fill(['status' => 'approved', 'approval_note' => $data['note'], 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $record->revision++; $record->save();
            $log->record('governance.assessment_approve', $record, $request->user(), $before);
        });

        return redirect()->route('governance.show', $assessment)->with('success', 'Penilaian disetujui dan dikunci. Tabel pengungkapan siap dipakai untuk laporan tahunan.');
    }

    public function item(Request $request, int $item, EntityContext $context)
    {
        $item = GovernanceItem::with(['assessment', 'pic', 'actions.pic'])->where('entity_id', $context->entity->id)->findOrFail($item);
        $evidence = EvidenceFile::where('entity_id', $context->entity->id)->where('subject_type', 'governance')->where('subject_id', $item->id)->get();
        $previous = GovernanceItem::where('entity_id', $context->entity->id)->where('code', $item->code)
            ->whereHas('assessment', fn ($q) => $q->where('year', '<', $item->assessment->year))
            ->with('assessment')->get()->sortByDesc(fn ($i) => $i->assessment->year)->first();
        $history = \App\Models\AuditEvent::with('actor')->where('entity_id', $context->entity->id)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('subject_type', 'governance_items')->where('subject_id', $item->id))
                ->orWhere(fn ($q) => $q->where('subject_type', 'governance_actions')->whereIn('subject_id', $item->actions->pluck('id')))
                ->orWhere(fn ($q) => $q->where('subject_type', 'evidence_files')->whereIn('subject_id', $evidence->pluck('id'))))
            ->orderByDesc('created_at')->orderByDesc('id')->limit(30)->get();
        $members = $this->members($context);
        $canEdit = $this->canEdit($item, $request->user(), $context);

        return view('governance.item', compact('context', 'item', 'evidence', 'previous', 'history', 'members', 'canEdit'));
    }

    public function update(Request $request, int $item, EntityContext $context, ChangeLog $log)
    {
        $rules = [
            'revision' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(array_keys(config('governance.statuses')))],
            'implementation' => ['nullable', 'string', 'max:10000'], 'evidence_note' => ['nullable', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:10000'], 'alternative_practice' => ['nullable', 'string', 'max:10000'],
            'target_date' => ['nullable', 'date'],
        ];
        if ($context->canManageGovernance()) { $rules['pic_id'] = ['nullable', 'integer', $this->memberRule($context, self::PIC_ROLES)]; }
        $data = $request->validate($rules);
        DB::transaction(function () use ($item, $data, $request, $context, $log) {
            $record = $this->lockItem($item, $context);
            abort_unless($this->canEdit($record, $request->user(), $context), 403);
            abort_unless($record->revision === (int) $data['revision'], 409);
            $before = $record->toArray();
            $record->fill(collect($data)->except('revision')->all()); $record->revision++; $record->save();
            $log->record('governance.item_updated', $record, $request->user(), $before);
        });

        return redirect()->route('governance.items.show', $item)->with('success', 'Penilaian rekomendasi disimpan.');
    }

    public function transition(Request $request, int $item, string $operation, EntityContext $context, ChangeLog $log)
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'note' => [$operation === 'submit' ? 'nullable' : 'required', 'string', 'min:10', 'max:10000']]);
        DB::transaction(function () use ($item, $operation, $data, $request, $context, $log) {
            $record = $this->lockItem($item, $context);
            abort_unless($record->revision === (int) $data['revision'], 409);
            $user = $request->user();
            $before = $record->toArray();
            if ($operation === 'submit') {
                abort_unless($this->canEdit($record, $user, $context), 403);
                $this->assertComplete($record, $context);
                $record->fill(['review_status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => $user->id]);
            } else {
                abort_unless($context->canManageGovernance() && $record->submitted_by !== $user->id && $record->pic_id !== $user->id, 403, 'Verifikasi dilakukan oleh petugas selain PIC dan pengaju.');
                abort_unless($record->review_status === 'submitted', 409);
                $record->fill(['review_status' => $operation === 'verify' ? 'verified' : 'returned', 'review_note' => $data['note'], 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            }
            $record->revision++; $record->save();
            $log->record('governance.item_'.$operation, $record, $user, $before);
        });
        $messages = ['submit' => 'Penilaian diajukan untuk verifikasi.', 'verify' => 'Penilaian diverifikasi.', 'return' => 'Penilaian dikembalikan kepada PIC.'];

        return redirect()->route('governance.items.show', $item)->with('success', $messages[$operation]);
    }

    public function action(Request $request, int $item, EntityContext $context, ChangeLog $log)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:10', 'max:255'], 'due_date' => ['required', 'date', 'after_or_equal:today'],
            'pic_id' => ['required', 'integer', $this->memberRule($context, self::PIC_ROLES)],
        ]);
        DB::transaction(function () use ($item, $data, $request, $context, $log) {
            $record = $this->lockItem($item, $context);
            abort_unless($context->canManageGovernance() || ($record->pic_id === $request->user()->id && $this->isMember($request->user(), $context)), 403);
            $action = GovernanceAction::create($data + ['entity_id' => $context->entity->id, 'governance_item_id' => $record->id, 'created_by' => $request->user()->id]);
            $log->record('governance.action_created', $action, $request->user());
        });

        return redirect()->route('governance.items.show', $item)->with('success', 'Tindak lanjut ditambahkan.');
    }

    public function complete(Request $request, int $action, EntityContext $context, ChangeLog $log)
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:10', 'max:10000']]);
        $record = DB::transaction(function () use ($action, $data, $request, $context, $log) {
            $record = GovernanceAction::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($action);
            abort_unless($record->pic_id === $request->user()->id || $context->canManageGovernance(), 403);
            abort_unless($record->status === 'open', 409);
            $before = $record->toArray();
            $record->fill(['status' => 'done', 'completion_note' => $data['note'], 'completed_at' => now()])->save();
            $log->record('governance.action_done', $record, $request->user(), $before);

            return $record;
        });

        return redirect()->route('governance.items.show', $record->governance_item_id)->with('success', 'Tindak lanjut ditandai selesai.');
    }

    public function disclosure(Request $request, int $assessment, EntityContext $context, GovernanceScore $score)
    {
        $assessment = $this->assessment($assessment, $context);
        $items = $this->items($assessment);
        $summary = $score->summarize($assessment, $items);
        $data = compact('context', 'assessment', 'items', 'summary');
        if ($request->query('format') === 'doc') {
            return response()->view('governance.disclosure-doc', $data)->withHeaders([
                'Content-Type' => 'application/msword; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$this->filename($assessment, 'doc').'"',
            ]);
        }

        return view('governance.disclosure', $data);
    }

    public function export(int $assessment, EntityContext $context)
    {
        $assessment = $this->assessment($assessment, $context);
        $items = $this->items($assessment);
        $rows = [['Kode', 'Aspek', 'Prinsip', 'Rekomendasi', 'Status penerapan', 'Status pengungkapan', 'Penjelasan penerapan', 'Referensi bukti', 'Jumlah berkas bukti', 'Alasan belum diterapkan', 'Praktik alternatif', 'Target penerapan', 'PIC', 'Status verifikasi', 'Tindak lanjut terbuka']];
        $files = EvidenceFile::where('entity_id', $context->entity->id)->where('subject_type', 'governance')->whereIn('subject_id', $items->pluck('id'))
            ->selectRaw('subject_id, count(*) as total')->groupBy('subject_id')->pluck('total', 'subject_id');
        foreach ($items as $item) {
            $rows[] = [$item->code, config('governance.aspects')[$item->aspect()], config('governance.principles')[$item->principle()][1], $item->text(),
                config('governance.statuses')[$item->status], config('governance.disclosure_labels')[$item->status],
                $item->implementation, $item->evidence_note, (int) ($files[$item->id] ?? 0), $item->explanation, $item->alternative_practice,
                $item->target_date?->format('d/m/Y'), $item->pic?->name, config('governance.review_statuses')[$item->review_status],
                $item->actions->where('status', 'open')->count()];
        }
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) { fputcsv($out, array_map(fn ($v) => $this->safeCell($v), $row), ';'); }
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($assessment, 'csv').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function assertComplete(GovernanceItem $item, EntityContext $context): void
    {
        $errors = [];
        if (! $item->pic_id) { $errors[] = 'Tetapkan PIC.'; }
        if ($item->status === 'not_assessed') { $errors[] = 'Pilih status penerapan.'; }
        if (in_array($item->status, ['applied', 'partial'], true) && mb_strlen(trim((string) $item->implementation)) < 20) { $errors[] = 'Uraikan penerapan minimal 20 karakter.'; }
        if (in_array($item->status, ['applied', 'partial'], true) && blank($item->evidence_note)
            && ! EvidenceFile::where('entity_id', $context->entity->id)->where('subject_type', 'governance')->where('subject_id', $item->id)->exists()) {
            $errors[] = 'Lampirkan berkas bukti atau isi referensi bukti.';
        }
        if ($item->needsExplanation() && mb_strlen(trim((string) $item->explanation)) < 20) { $errors[] = 'Jelaskan alasan belum diterapkan minimal 20 karakter (prinsip terapkan atau jelaskan).'; }
        abort_if($errors, 422, implode(' ', $errors));
    }

    private function canEdit(GovernanceItem $item, ?User $user, EntityContext $context): bool
    {
        if (! $user || $context->role === 'demo_viewer' || $item->assessment->isLocked() || ! $item->editable()) { return false; }

        return $context->canManageGovernance() || ($item->pic_id === $user->id && $this->isMember($user, $context));
    }

    private function isMember(User $user, EntityContext $context): bool
    {
        return in_array($context->role, self::PIC_ROLES, true);
    }

    private function lockItem(int $item, EntityContext $context): GovernanceItem
    {
        // Penilaian dikunci lebih dulu agar urutan lock sama dengan persetujuan.
        $stub = GovernanceItem::where('entity_id', $context->entity->id)->findOrFail($item);
        $assessment = GovernanceAssessment::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($stub->governance_assessment_id);
        $record = GovernanceItem::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($item);
        $record->setRelation('assessment', $assessment);

        return $record;
    }

    private function assessment(int $id, EntityContext $context): GovernanceAssessment
    {
        return GovernanceAssessment::with(['coordinator', 'approver'])->where('entity_id', $context->entity->id)->findOrFail($id);
    }

    private function items(GovernanceAssessment $assessment): Collection
    {
        $order = array_flip(array_keys(config('governance.recommendations')));

        return $assessment->items()->with(['pic', 'actions'])->get()->sortBy(fn ($i) => $order[$i->code] ?? 99)->values();
    }

    private function members(EntityContext $context): Collection
    {
        return User::whereHas('entities', fn ($q) => $q->where('entities.id', $context->entity->id)->whereIn('entity_user.role', self::PIC_ROLES))->orderBy('name')->get();
    }

    private function memberRule(EntityContext $context, array $roles)
    {
        return Rule::exists('entity_user', 'user_id')->where(fn ($q) => $q->where('entity_id', $context->entity->id)->whereIn('role', $roles));
    }

    private function filename(GovernanceAssessment $assessment, string $ext): string
    {
        return 'pengungkapan-tata-kelola-'.\Illuminate\Support\Str::slug($assessment->entity->code).'-'.$assessment->year.'.'.$ext;
    }

    /** Cegah formula injection saat CSV dibuka di Excel. */
    private function safeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}

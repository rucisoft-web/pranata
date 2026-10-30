<?php
namespace App\Http\Controllers;

use App\Models\EvidenceFile;
use App\Models\ObligationOccurrence;
use App\Services\ChangeLog;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ComplianceController extends Controller
{
    public function index(Request $request, EntityContext $context)
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(array_keys(config('modules.compliance_statuses')))], 'month' => ['nullable', 'date_format:Y-m'], 'overdue' => ['nullable', Rule::in(['1'])]]);
        $query = ObligationOccurrence::where('entity_id', $context->entity->id);
        $stats = [
            'total' => (clone $query)->count(), 'submitted' => (clone $query)->where('status', 'submitted')->count(),
            'fulfilled' => (clone $query)->where('status', 'fulfilled')->count(),
            'overdue' => (clone $query)->where('status', '!=', 'fulfilled')->whereDate('due_date', '<', today())->count(),
        ];
        $occurrences = $query->with('responsible')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['month'] ?? null, fn ($q, $m) => $q->whereBetween('due_date', [$m.'-01', date('Y-m-t', strtotime($m.'-01'))]))
            ->when($filters['overdue'] ?? null, fn ($q) => $q->where('status', '!=', 'fulfilled')->whereDate('due_date', '<', today()))
            ->orderBy('due_date')->paginate(15)->withQueryString();

        return view('compliance.index', compact('context', 'stats', 'occurrences'));
    }

    public function show(int $occurrence, EntityContext $context)
    {
        $occurrence = ObligationOccurrence::with('responsible')->where('entity_id', $context->entity->id)->findOrFail($occurrence);
        $evidence = EvidenceFile::where('entity_id', $context->entity->id)->where('subject_type', 'compliance')->where('subject_id', $occurrence->id)->get();

        return view('compliance.show', compact('context', 'occurrence', 'evidence'));
    }

    public function transition(Request $request, int $occurrence, string $operation, EntityContext $context, ChangeLog $log)
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'note' => ['required', 'string', 'min:10', 'max:10000']]);
        DB::transaction(function () use ($occurrence, $operation, $data, $request, $context, $log) {
            $record = ObligationOccurrence::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($occurrence);
            abort_unless($record->revision === (int) $data['revision'], 409);
            $before = $record->toArray();
            if ($operation === 'submit') {
                abort_unless($context->canContributeCompliance() && $record->responsible_id === $request->user()->id, 403);
                abort_unless(in_array($record->status, ['open', 'returned'], true), 409);
                abort_unless(EvidenceFile::where('entity_id', $context->entity->id)->where('subject_type', 'compliance')->where('subject_id', $record->id)->exists(), 422, 'Unggah bukti sebelum mengajukan.');
                $record->fill(['status' => 'submitted', 'submission_note' => $data['note'], 'submitted_at' => now(), 'submitted_by' => $request->user()->id]);
            } else {
                abort_unless($context->canManageCompliance() && $record->responsible_id !== $request->user()->id && $record->submitted_by !== $request->user()->id, 403);
                abort_unless($record->status === 'submitted', 409);
                $record->fill(['status' => $operation === 'verify' ? 'fulfilled' : 'returned', 'review_note' => $data['note'], 'reviewed_by' => $request->user()->id, 'fulfilled_at' => $operation === 'verify' ? now() : null]);
            }
            $record->revision++; $record->save();
            $log->record('compliance.'.$operation, $record, $request->user(), $before);
        });

        return redirect()->route('compliance.show', $occurrence)->with('success', 'Status pemenuhan diperbarui. Riwayat tenggat tetap tersimpan.');
    }
}

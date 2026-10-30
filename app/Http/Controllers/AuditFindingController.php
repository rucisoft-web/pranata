<?php
namespace App\Http\Controllers;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\EvidenceFile;
use App\Services\AuditAccess;
use App\Services\ChangeLog;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditFindingController extends Controller
{
    public function index(Request $request, EntityContext $context)
    {
        $findings = AuditFinding::where('entity_id', $context->entity->id)
            ->when($context->role !== 'demo_viewer', fn ($q) => $q->where('responsible_id', $request->user()->id)->where('status', '!=', 'draft'))
            ->orderBy('due_date')->paginate(15);

        return view('audits.actions', compact('context', 'findings'));
    }

    public function show(Request $request, int $finding, EntityContext $context, AuditAccess $access)
    {
        $finding = AuditFinding::with(['engagement', 'responsible'])->where('entity_id', $context->entity->id)->findOrFail($finding);
        abort_unless($access->read($finding->engagement, $request->user(), $context) || ($finding->status !== 'draft' && $access->owner($finding, $request->user(), $context)), 404);
        $evidence = EvidenceFile::where('entity_id', $context->entity->id)->where('subject_type', 'finding')->where('subject_id', $finding->id)->get();

        return view('audits.finding', compact('finding', 'context', 'access', 'evidence'));
    }

    public function transition(Request $request, int $finding, string $operation, EntityContext $context, AuditAccess $access, ChangeLog $log)
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'], 'note' => ['required', 'string', 'min:10', 'max:10000'],
            'action_plan' => [$operation === 'submit' ? 'required' : 'nullable', 'string', 'min:10', 'max:10000'],
        ]);
        DB::transaction(function () use ($finding, $operation, $data, $request, $context, $access, $log) {
            // Engagement before finding is the common lock order for all audit mutations.
            $stub = AuditFinding::where('entity_id', $context->entity->id)->findOrFail($finding);
            $audit = AuditEngagement::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($stub->audit_engagement_id);
            $record = AuditFinding::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($finding);
            $record->setRelation('engagement', $audit);
            abort_unless($record->revision === (int) $data['revision'], 409);
            abort_unless($audit->status === 'finalized', 409);
            $before = $record->toArray();
            if ($operation === 'submit') {
                abort_unless($access->owner($record, $request->user(), $context), 403);
                abort_unless(in_array($record->status, ['issued', 'returned'], true), 409);
                abort_unless(EvidenceFile::where('entity_id', $context->entity->id)->where('subject_type', 'finding')->where('subject_id', $record->id)->exists(), 422, 'Unggah bukti tindakan sebelum pengajuan.');
                $record->fill(['status' => 'submitted', 'action_plan' => $data['action_plan'], 'completion_note' => $data['note'], 'submitted_at' => now(), 'submitted_by' => $request->user()->id]);
            } else {
                abort_unless(($access->lead($audit, $request->user(), $context) || $access->reviewer($audit, $request->user(), $context)) && $record->responsible_id !== $request->user()->id && $record->submitted_by !== $request->user()->id, 403);
                abort_unless($record->status === 'submitted', 409);
                $record->fill(['status' => $operation === 'close' ? 'closed' : 'returned', 'review_note' => $data['note'], 'closed_by' => $operation === 'close' ? $request->user()->id : null, 'closed_at' => $operation === 'close' ? now() : null]);
            }
            $record->revision++; $record->save();
            $log->record('audit.finding_'.$operation, $record, $request->user(), $before);
        });

        return redirect()->route('findings.show', $finding)->with('success', 'Tindak lanjut temuan diperbarui.');
    }
}

<?php
namespace App\Http\Controllers;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditProcedure;
use App\Models\EvidenceFile;
use App\Models\User;
use App\Services\AuditAccess;
use App\Services\ChangeLog;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AuditController extends Controller
{
    public function index(Request $request, EntityContext $context)
    {
        abort_unless($context->canReadAudit(), 403);
        $audits = AuditEngagement::with(['lead', 'reviewer'])->where('entity_id', $context->entity->id)
            ->when(! $context->canManageAudit() && $context->role !== 'demo_viewer', fn ($q) => $q->where('lead_id', $request->user()->id))
            ->latest('id')->paginate(15);

        return view('audits.index', compact('context', 'audits'));
    }

    public function create(EntityContext $context)
    {
        abort_unless($context->canManageAudit(), 403);
        $leads = User::whereHas('entities', fn ($q) => $q->where('entities.id', $context->entity->id)->whereIn('entity_user.role', ['audit_manager', 'auditor']))->orderBy('name')->get();
        $reviewers = User::whereHas('entities', fn ($q) => $q->where('entities.id', $context->entity->id)->whereIn('entity_user.role', ['admin', 'audit_manager']))->orderBy('name')->get();

        return view('audits.form', compact('context', 'leads', 'reviewers'));
    }

    public function store(Request $request, EntityContext $context, ChangeLog $log)
    {
        abort_unless($context->canManageAudit(), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('audit_engagements')->where('entity_id', $context->entity->id)],
            'title' => ['required', 'string', 'max:255'], 'scope' => ['required', 'string', 'max:10000'], 'objectives' => ['required', 'string', 'max:10000'],
            'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'lead_id' => ['required', 'integer', Rule::exists('entity_user', 'user_id')->where(fn ($q) => $q->where('entity_id', $context->entity->id)->whereIn('role', ['audit_manager', 'auditor']))],
            'reviewer_id' => ['required', 'integer', 'different:lead_id', Rule::exists('entity_user', 'user_id')->where(fn ($q) => $q->where('entity_id', $context->entity->id)->whereIn('role', ['admin', 'audit_manager']))],
        ]);
        $audit = DB::transaction(function () use ($data, $request, $context, $log) {
            $audit = AuditEngagement::create($data + ['entity_id' => $context->entity->id, 'created_by' => $request->user()->id]);
            $log->record('audit.planned', $audit, $request->user());

            return $audit;
        });

        return redirect()->route('audits.show', $audit)->with('success', 'Penugasan audit dibuat.');
    }

    public function show(Request $request, int $audit, EntityContext $context, AuditAccess $access)
    {
        $audit = AuditEngagement::with(['lead', 'reviewer', 'procedures', 'findings.responsible'])->where('entity_id', $context->entity->id)->findOrFail($audit);
        abort_unless($access->read($audit, $request->user(), $context), 404);
        $members = User::whereHas('entities', fn ($q) => $q->where('entities.id', $context->entity->id)->whereNotIn('entity_user.role', ['viewer', 'auditor']))
            ->whereNotIn('id', [$audit->lead_id, $audit->reviewer_id])->orderBy('name')->get();
        $evidence = EvidenceFile::where('entity_id', $context->entity->id)->where('subject_type', 'procedure')->whereIn('subject_id', $audit->procedures->pluck('id'))->get()->groupBy('subject_id');

        return view('audits.show', compact('context', 'audit', 'access', 'members', 'evidence'));
    }

    public function transition(Request $request, int $audit, string $operation, EntityContext $context, AuditAccess $access, ChangeLog $log)
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'note' => [in_array($operation, ['submit', 'finalize', 'return', 'close'], true) ? 'required' : 'nullable', 'string', 'min:10', 'max:20000'],
        ]);
        DB::transaction(function () use ($audit, $operation, $data, $request, $context, $access, $log) {
            $record = AuditEngagement::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($audit);
            abort_unless($access->read($record, $request->user(), $context), 404);
            abort_unless($record->revision === (int) $data['revision'], 409);
            $before = $record->toArray();
            if (in_array($operation, ['start', 'submit'], true)) {
                abort_unless($access->lead($record, $request->user(), $context), 403);
                abort_unless($record->status === ($operation === 'start' ? 'draft' : 'fieldwork'), 409);
                if ($operation === 'submit') {
                    abort_unless($record->procedures()->exists(), 422, 'Lengkapi setidaknya satu kertas kerja.');
                    $record->report_summary = $data['note'];
                }
                $record->status = $operation === 'start' ? 'fieldwork' : 'review';
            } else {
                abort_unless($access->reviewer($record, $request->user(), $context), 403);
                abort_unless($record->status === ($operation === 'close' ? 'finalized' : 'review'), 409);
                $record->review_note = $data['note'];
                if ($operation === 'close') {
                    abort_if($record->findings()->where('status', '!=', 'closed')->exists(), 409, 'Masih ada temuan yang belum ditutup.');
                    $record->status = 'closed'; $record->closed_at = now();
                } elseif ($operation === 'finalize') {
                    $record->status = 'finalized'; $record->finalized_at = now();
                    foreach ($record->findings()->lockForUpdate()->get() as $finding) {
                        $old = $finding->toArray(); $finding->status = 'issued'; $finding->revision++; $finding->save();
                        $log->record('audit.finding_issued', $finding, $request->user(), $old);
                    }
                } else { $record->status = 'fieldwork'; }
            }
            $record->revision++; $record->save();
            $log->record('audit.'.$operation, $record, $request->user(), $before);
        });

        return redirect()->route('audits.show', $audit)->with('success', 'Status audit diperbarui.');
    }

    public function procedure(Request $request, int $audit, EntityContext $context, AuditAccess $access, ChangeLog $log)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'criteria' => ['required', 'string', 'max:10000'],
            'procedure' => ['required', 'string', 'max:10000'], 'result' => ['required', 'string', 'max:10000'],
            'conclusion' => ['required', Rule::in(array_keys(config('modules.conclusions')))],
        ]);
        DB::transaction(function () use ($audit, $data, $request, $context, $access, $log) {
            $record = AuditEngagement::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($audit);
            abort_unless($access->lead($record, $request->user(), $context), 403);
            abort_unless($record->status === 'fieldwork', 409);
            $procedure = AuditProcedure::create($data + ['entity_id' => $context->entity->id, 'audit_engagement_id' => $record->id, 'created_by' => $request->user()->id]);
            $log->record('audit.procedure_recorded', $procedure, $request->user());
        });

        return redirect()->route('audits.show', $audit)->with('success', 'Kertas kerja disimpan.');
    }

    public function finding(Request $request, int $audit, EntityContext $context, AuditAccess $access, ChangeLog $log)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'criteria' => ['required', 'string', 'max:10000'], 'condition' => ['required', 'string', 'max:10000'],
            'cause' => ['required', 'string', 'max:10000'], 'impact' => ['required', 'string', 'max:10000'], 'recommendation' => ['required', 'string', 'max:10000'],
            'severity' => ['required', Rule::in(array_keys(config('modules.criticalities')))], 'due_date' => ['required', 'date_format:Y-m-d'],
            'responsible_id' => ['required', 'integer', Rule::exists('entity_user', 'user_id')->where(fn ($q) => $q->where('entity_id', $context->entity->id)->whereNotIn('role', ['viewer', 'auditor']))],
        ]);
        DB::transaction(function () use ($audit, $data, $request, $context, $access, $log) {
            $record = AuditEngagement::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($audit);
            abort_unless($access->lead($record, $request->user(), $context), 403);
            abort_unless($record->status === 'fieldwork', 409);
            abort_if(in_array((int) $data['responsible_id'], [$record->lead_id, $record->reviewer_id], true), 422, 'PIC tindakan harus berbeda dari auditor dan reviewer.');
            $finding = AuditFinding::create($data + ['entity_id' => $context->entity->id, 'audit_engagement_id' => $record->id]);
            $log->record('audit.finding_created', $finding, $request->user());
        });

        return redirect()->route('audits.show', $audit)->with('success', 'Draf temuan dicatat.');
    }
}

<?php
namespace App\Http\Controllers;

use App\Models\Obligation;
use App\Models\ObligationOccurrence;
use App\Models\RegulatorySource;
use App\Models\User;
use App\Services\ChangeLog;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ObligationController extends Controller
{
    public function index(EntityContext $context)
    {
        return view('compliance.obligations', [
            'context' => $context,
            'obligations' => Obligation::with(['source', 'responsible'])->where('entity_id', $context->entity->id)->latest('id')->paginate(15),
            'sources' => RegulatorySource::where('entity_id', $context->entity->id)->latest('id')->get(),
        ]);
    }

    public function source(Request $request, EntityContext $context, ChangeLog $log)
    {
        abort_unless($context->canManageCompliance(), 403);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('regulatory_sources')->where('entity_id', $context->entity->id)],
            'title' => ['required', 'string', 'max:255'], 'issuer' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:255'], 'version' => ['required', 'string', 'max:80'],
            'url' => ['nullable', 'url:http,https', 'max:1000'], 'effective_date' => ['required', 'date_format:Y-m-d'],
        ]);
        DB::transaction(function () use ($data, $request, $context, $log) {
            $source = RegulatorySource::create($data + ['entity_id' => $context->entity->id, 'created_by' => $request->user()->id]);
            $log->record('compliance.source_created', $source, $request->user());
        });

        return redirect()->route('obligations.index')->with('success', 'Versi sumber kewajiban dicatat.');
    }

    public function create(EntityContext $context) { return $this->form(new Obligation, $context); }

    public function edit(int $obligation, EntityContext $context)
    {
        return $this->form(Obligation::where('entity_id', $context->entity->id)->findOrFail($obligation), $context);
    }

    private function form(Obligation $obligation, EntityContext $context)
    {
        abort_unless($context->canManageCompliance(), 403);
        $sources = RegulatorySource::where('entity_id', $context->entity->id)->orderBy('title')->get();
        $members = User::whereHas('entities', fn ($q) => $q->where('entities.id', $context->entity->id)->whereIn('entity_user.role', ['admin', 'compliance_officer', 'compliance_owner']))->orderBy('name')->get();

        return view('compliance.form', compact('obligation', 'context', 'sources', 'members'));
    }

    public function store(Request $request, EntityContext $context, ChangeLog $log) { return $this->save($request, $context, $log); }
    public function update(Request $request, int $obligation, EntityContext $context, ChangeLog $log) { return $this->save($request, $context, $log, $obligation); }

    private function save(Request $request, EntityContext $context, ChangeLog $log, ?int $id = null)
    {
        abort_unless($context->canManageCompliance(), 403);
        if ($id) { Obligation::where('entity_id', $context->entity->id)->findOrFail($id); }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('obligations')->where('entity_id', $context->entity->id)->ignore($id)],
            'regulatory_source_id' => ['required', 'integer', Rule::exists('regulatory_sources', 'id')->where('entity_id', $context->entity->id)],
            'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'], 'clause' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(array_keys(config('modules.frequencies')))],
            'criticality' => ['required', Rule::in(array_keys(config('modules.criticalities')))],
            'applicability' => ['required', Rule::in(array_keys(config('modules.applicabilities')))],
            'applicability_reason' => ['required', 'string', 'max:5000'],
            'responsible_id' => ['required', 'integer', Rule::exists('entity_user', 'user_id')->where(fn ($q) => $q->where('entity_id', $context->entity->id)->whereIn('role', ['admin', 'compliance_officer', 'compliance_owner']))],
        ]);
        $revision = $id ? $request->validate(['revision' => ['required', 'integer', 'min:1']])['revision'] : null;
        $record = DB::transaction(function () use ($id, $revision, $data, $request, $context, $log) {
            if ($id) {
                $record = Obligation::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($id);
                abort_unless($record->revision === (int) $revision, 409, 'Data berubah. Muat ulang halaman.');
                $before = $record->toArray();
                $record->fill($data); $record->revision++; $record->save();
            } else {
                $before = null;
                $record = Obligation::create($data + ['entity_id' => $context->entity->id, 'created_by' => $request->user()->id]);
            }
            $log->record($id ? 'compliance.obligation_updated' : 'compliance.obligation_created', $record, $request->user(), $before);

            return $record;
        });

        return redirect()->route('obligations.show', $record)->with('success', 'Kewajiban disimpan. Periode sebelumnya tetap menggunakan snapshot semula.');
    }

    public function show(int $obligation, EntityContext $context)
    {
        $obligation = Obligation::with(['source', 'responsible'])->where('entity_id', $context->entity->id)->findOrFail($obligation);
        $occurrences = $obligation->occurrences()->orderByDesc('due_date')->get();

        return view('compliance.obligation', compact('obligation', 'occurrences', 'context'));
    }

    public function period(Request $request, int $obligation, EntityContext $context, ChangeLog $log)
    {
        abort_unless($context->canManageCompliance(), 403);
        $record = Obligation::where('entity_id', $context->entity->id)->findOrFail($obligation);
        $data = $request->validate([
            'period' => ['required', 'string', 'max:80', Rule::unique('obligation_occurrences')->where('obligation_id', $record->id)],
            'due_date' => ['required', 'date_format:Y-m-d'], 'revision' => ['required', 'integer', 'min:1'],
        ]);
        $occurrence = DB::transaction(function () use ($record, $data, $request, $context, $log) {
            $record = Obligation::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($record->id);
            abort_unless($record->revision === (int) $data['revision'], 409);
            abort_unless($record->applicability === 'applies', 409, 'Periode hanya dapat dibuat untuk kewajiban yang berlaku.');
            abort_unless($request->user()->entities()->where('entities.id', $context->entity->id)->exists(), 403);
            abort_unless(User::whereKey($record->responsible_id)->whereHas('entities', fn ($q) => $q->where('entities.id', $context->entity->id)->whereIn('entity_user.role', ['admin', 'compliance_officer', 'compliance_owner']))->exists(), 409, 'PIC tidak lagi memiliki akses yang sesuai.');
            $occurrence = ObligationOccurrence::create([
                'entity_id' => $context->entity->id, 'obligation_id' => $record->id,
                'obligation_snapshot' => $record->load('source')->toArray(), 'period' => $data['period'],
                'due_date' => $data['due_date'], 'responsible_id' => $record->responsible_id,
            ]);
            $log->record('compliance.period_created', $occurrence, $request->user());

            return $occurrence;
        });

        return redirect()->route('compliance.show', $occurrence)->with('success', 'Periode pemenuhan dibuat.');
    }
}

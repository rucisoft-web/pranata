<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Risk;
use App\Services\RiskWorkflow;
use App\Support\EntityContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RiskController extends Controller
{
    public function index(Request $request, EntityContext $context)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(config('grc.statuses')))],
        ]);
        $risks = Risk::where('entity_id', $context->entity->id)
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$value.'%')->orWhere('code', 'like', '%'.$value.'%')))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->latest('id')->paginate(15)->withQueryString();

        return view('risks.index', compact('risks', 'context'));
    }

    public function create(EntityContext $context)
    {
        abort_unless($context->canWrite(), 403);

        return view('risks.form', ['risk' => new Risk, 'context' => $context]);
    }

    public function store(Request $request, EntityContext $context, RiskWorkflow $workflow)
    {
        abort_unless($context->canWrite(), 403);
        $risk = $workflow->create($this->validated($request, $context), $request->user(), $context);

        return redirect()->route('risks.show', $risk)->with('success', 'Risiko berhasil dicatat sebagai draf.');
    }

    public function show(int $risk, EntityContext $context)
    {
        $risk = $this->find($risk, $context);
        $events = AuditEvent::where('entity_id', $context->entity->id)->where('subject_type', 'risk')->where('subject_id', $risk->id)->latest('id')->get();

        return view('risks.show', compact('risk', 'events', 'context'));
    }

    public function edit(Request $request, int $risk, EntityContext $context)
    {
        $risk = $this->find($risk, $context);
        abort_unless($context->canWrite() && $risk->owner_id === $request->user()->id, 403);
        abort_unless($risk->status === 'draft', 409);

        return view('risks.form', compact('risk', 'context'));
    }

    public function update(Request $request, int $risk, EntityContext $context, RiskWorkflow $workflow)
    {
        $this->find($risk, $context);
        $revision = $request->validate(['revision' => ['required', 'integer', 'min:1']])['revision'];
        $workflow->change($risk, (int) $revision, 'update', $this->validated($request, $context, $risk), $request->user(), $context);

        return redirect()->route('risks.show', $risk)->with('success', 'Draf risiko diperbarui.');
    }

    public function transition(Request $request, int $risk, string $operation, EntityContext $context, RiskWorkflow $workflow)
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'review_note' => [$operation === 'submit' ? 'nullable' : 'required', 'string', 'max:3000'],
        ]);
        $workflow->change($risk, (int) $data['revision'], $operation, $data, $request->user(), $context);

        return redirect()->route('risks.show', $risk)->with('success', 'Status risiko diperbarui.');
    }

    private function find(int $id, EntityContext $context): Risk
    {
        return Risk::where('entity_id', $context->entity->id)->findOrFail($id);
    }

    private function validated(Request $request, EntityContext $context, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('risks')->where('entity_id', $context->entity->id)->ignore($id)],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(config('grc.categories'))],
            'objective' => ['required', 'string', 'max:500'],
            'process' => ['required', 'string', 'max:200'],
            'cause' => ['required', 'string', 'max:5000'],
            'event' => ['required', 'string', 'max:5000'],
            'impact_description' => ['required', 'string', 'max:5000'],
            'likelihood' => ['required', 'integer', 'between:1,5'],
            'impact' => ['required', 'integer', 'between:1,5'],
            'assessment_reason' => ['required', 'string', 'max:5000'],
            'response' => ['required', Rule::in(array_keys(config('grc.responses')))],
            'treatment_plan' => ['required', 'string', 'max:5000'],
            'review_date' => ['required', 'date_format:Y-m-d'],
        ]);
    }
}

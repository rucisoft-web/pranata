<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Risk;
use App\Models\User;
use App\Support\EntityContext;
use Illuminate\Support\Facades\DB;

class RiskWorkflow
{
    public function create(array $data, User $actor, EntityContext $context): Risk
    {
        abort_unless($context->canWrite(), 403);

        return DB::transaction(function () use ($data, $actor, $context) {
            $risk = Risk::create(array_merge($data, [
                'entity_id' => $context->entity->id,
                'owner_id' => $actor->id,
                'status' => 'draft',
                'methodology_version' => config('grc.methodology_version'),
            ]));
            $this->record('risk.created', $risk, $actor, null);

            return $risk;
        });
    }

    public function change(int $id, int $revision, string $operation, array $data, User $actor, EntityContext $context): Risk
    {
        return DB::transaction(function () use ($id, $revision, $operation, $data, $actor, $context) {
            $risk = Risk::where('entity_id', $context->entity->id)->lockForUpdate()->findOrFail($id);
            abort_unless($risk->revision === $revision, 409, 'Data telah berubah. Muat ulang sebelum melanjutkan.');
            $before = $risk->toArray();

            if (in_array($operation, ['update', 'submit'], true)) {
                abort_unless($context->canWrite() && $risk->owner_id === $actor->id, 403);
                abort_unless($risk->status === 'draft', 409, 'Hanya draf yang dapat diubah atau diajukan.');
                if ($operation === 'update') {
                    $risk->fill($data);
                } else {
                    $risk->status = 'submitted';
                    $risk->review_note = null;
                }
            } else {
                abort_unless(in_array($operation, ['approve', 'return'], true), 422);
                abort_unless($context->canReview() && $risk->owner_id !== $actor->id, 403, 'Peninjau harus berbeda dari pemilik risiko.');
                abort_unless($risk->status === 'submitted', 409, 'Risiko tidak berada pada tahap tinjauan.');
                $risk->status = $operation === 'approve' ? 'approved' : 'draft';
                $risk->review_note = $data['review_note'];
                $risk->approved_by = $operation === 'approve' ? $actor->id : null;
                $risk->approved_at = $operation === 'approve' ? now() : null;
            }
            $risk->revision++;
            $risk->save();
            $this->record('risk.'.$operation, $risk, $actor, $before);

            return $risk;
        });
    }

    private function record(string $action, Risk $risk, User $actor, ?array $before): void
    {
        AuditEvent::create([
            'entity_id' => $risk->entity_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => 'risk',
            'subject_id' => $risk->id,
            'before' => $before,
            'after' => $risk->fresh()->toArray(),
            'created_at' => now(),
        ]);
    }
}

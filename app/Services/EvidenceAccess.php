<?php
namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditProcedure;
use App\Models\ObligationOccurrence;
use App\Models\User;
use App\Support\EntityContext;
use Illuminate\Database\Eloquent\Model;

class EvidenceAccess
{
    public function subject(string $type, int $id, User $user, EntityContext $context, bool $write = false): Model
    {
        if ($type === 'compliance') {
            $q = ObligationOccurrence::where('entity_id', $context->entity->id);
            $record = ($write ? $q->lockForUpdate() : $q)->findOrFail($id);
            if ($write) {
                abort_unless($context->canContributeCompliance() && $record->responsible_id === $user->id, 403);
                abort_unless(in_array($record->status, ['open', 'returned'], true), 409, 'Bukti dikunci saat pengajuan atau setelah verifikasi.');
            }

            return $record;
        }
        abort_unless(in_array($type, ['finding', 'procedure'], true), 404);
        $class = $type === 'finding' ? AuditFinding::class : AuditProcedure::class;
        $stub = $class::where('entity_id', $context->entity->id)->findOrFail($id);
        $q = AuditEngagement::where('entity_id', $context->entity->id);
        $audit = ($write ? $q->lockForUpdate() : $q)->findOrFail($stub->audit_engagement_id);
        $q = $class::where('entity_id', $context->entity->id);
        $record = ($write ? $q->lockForUpdate() : $q)->findOrFail($id);
        $record->setRelation('engagement', $audit);
        $access = app(AuditAccess::class);
        $owner = $type === 'finding' && $record->status !== 'draft' && $access->owner($record, $user, $context);
        abort_unless($access->read($audit, $user, $context) || $owner, 404);
        if ($write) {
            if ($type === 'finding') {
                abort_unless($owner, 403);
                abort_unless($audit->status === 'finalized' && in_array($record->status, ['issued', 'returned'], true), 409);
            } else {
                abort_unless($access->lead($audit, $user, $context), 403);
                abort_unless($audit->status === 'fieldwork', 409);
            }
        }

        return $record;
    }
}

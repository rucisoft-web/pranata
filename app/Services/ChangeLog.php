<?php
namespace App\Services;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
class ChangeLog
{
    public function record(string $action, Model $subject, User $actor, ?array $before = null): void
    {
        AuditEvent::create(['entity_id' => $subject->entity_id, 'actor_id' => $actor->id,
            'action' => $action, 'subject_type' => $subject->getTable(), 'subject_id' => $subject->id,
            'before' => $before, 'after' => $subject->fresh()->toArray(), 'created_at' => now()]);
    }
}

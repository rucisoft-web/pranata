<?php
namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\User;
use App\Support\EntityContext;

class AuditAccess
{
    public function read(AuditEngagement $audit, User $user, EntityContext $context): bool
    {
        return $audit->entity_id === $context->entity->id && $context->canReadAudit()
            && ($context->canManageAudit() || $context->role === 'demo_viewer' || $audit->lead_id === $user->id);
    }

    public function lead(AuditEngagement $audit, User $user, EntityContext $context): bool
    {
        return $this->read($audit, $user, $context) && $audit->lead_id === $user->id;
    }

    public function reviewer(AuditEngagement $audit, User $user, EntityContext $context): bool
    {
        return $this->read($audit, $user, $context) && $context->canManageAudit()
            && $audit->reviewer_id === $user->id && $audit->lead_id !== $user->id;
    }

    public function owner(AuditFinding $finding, User $user, EntityContext $context): bool
    {
        return $finding->entity_id === $context->entity->id && $finding->responsible_id === $user->id
            && ! in_array($context->role, ['viewer', 'auditor', 'demo_viewer'], true)
            && $finding->engagement->lead_id !== $user->id && $finding->engagement->reviewer_id !== $user->id;
    }
}

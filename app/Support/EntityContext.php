<?php

namespace App\Support;

use App\Models\Entity;

class EntityContext
{
    public function __construct(public readonly Entity $entity, public readonly string $role) {}

    public function canWrite(): bool
    {
        return in_array($this->role, ['admin', 'risk_manager', 'risk_owner'], true);
    }

    public function canReview(): bool
    {
        return in_array($this->role, ['admin', 'risk_manager'], true);
    }

    public function canManageCompliance(): bool { return in_array($this->role, ['admin', 'compliance_officer'], true); }
    public function canContributeCompliance(): bool { return in_array($this->role, ['admin', 'compliance_officer', 'compliance_owner'], true); }
    public function canManageAudit(): bool { return in_array($this->role, ['admin', 'audit_manager'], true); }
    public function canReadAudit(): bool { return in_array($this->role, ['admin', 'audit_manager', 'auditor', 'demo_viewer'], true); }
}

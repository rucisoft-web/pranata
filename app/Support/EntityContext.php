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
}

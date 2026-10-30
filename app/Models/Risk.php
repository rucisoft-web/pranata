<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Risk extends Model
{
    protected $fillable = [
        'entity_id', 'owner_id', 'code', 'title', 'category', 'objective', 'process',
        'cause', 'event', 'impact_description', 'likelihood', 'impact', 'assessment_reason',
        'response', 'treatment_plan', 'review_date', 'methodology_version', 'status',
        'approved_by', 'approved_at', 'review_note', 'revision',
    ];

    protected function casts(): array
    {
        return ['review_date' => 'date', 'approved_at' => 'datetime', 'likelihood' => 'integer', 'impact' => 'integer', 'revision' => 'integer'];
    }

    public function getScoreAttribute(): int
    {
        return $this->likelihood * $this->impact;
    }

    public function getLevelAttribute(): string
    {
        return match (true) {
            $this->score >= 20 => 'Kritis',
            $this->score >= 12 => 'Tinggi',
            $this->score >= 6 => 'Sedang',
            default => 'Rendah',
        };
    }
}

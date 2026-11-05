<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GovernanceAssessment extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'year', 'coordinator_id', 'created_by', 'approved_by'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['approved_at' => 'datetime', 'revision' => 'integer']; }
    public function entity() { return $this->belongsTo(Entity::class); }
    public function items() { return $this->hasMany(GovernanceItem::class); }
    public function coordinator() { return $this->belongsTo(User::class, 'coordinator_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function isLocked(): bool { return $this->status === 'approved'; }
}

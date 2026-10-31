<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditFinding extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'audit_engagement_id', 'responsible_id', 'submitted_by', 'closed_by'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['due_date' => 'date', 'submitted_at' => 'datetime', 'closed_at' => 'datetime', 'revision' => 'integer']; }
    public function engagement() { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id'); }
    public function responsible() { return $this->belongsTo(User::class, 'responsible_id'); }
}

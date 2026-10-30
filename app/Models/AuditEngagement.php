<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditEngagement extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['start_date' => 'date', 'end_date' => 'date', 'finalized_at' => 'datetime', 'closed_at' => 'datetime', 'revision' => 'integer']; }
    public function procedures() { return $this->hasMany(AuditProcedure::class); }
    public function findings() { return $this->hasMany(AuditFinding::class); }
    public function lead() { return $this->belongsTo(User::class, 'lead_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
}

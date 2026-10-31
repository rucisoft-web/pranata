<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditProcedure extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'audit_engagement_id', 'created_by'];
    protected $guarded = ['id'];
    public function engagement() { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id'); }
}

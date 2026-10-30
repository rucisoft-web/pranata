<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditProcedure extends Model
{
    protected $guarded = ['id'];
    public function engagement() { return $this->belongsTo(AuditEngagement::class, 'audit_engagement_id'); }
}

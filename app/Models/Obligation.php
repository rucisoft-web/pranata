<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Obligation extends Model
{
    protected $guarded = ['id'];
    public function source() { return $this->belongsTo(RegulatorySource::class, 'regulatory_source_id'); }
    public function responsible() { return $this->belongsTo(User::class, 'responsible_id'); }
    public function occurrences() { return $this->hasMany(ObligationOccurrence::class); }
}

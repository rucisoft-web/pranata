<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Obligation extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'regulatory_source_id', 'responsible_id', 'created_by', 'revision'];
    protected $guarded = ['id'];
    public function source() { return $this->belongsTo(RegulatorySource::class, 'regulatory_source_id'); }
    public function responsible() { return $this->belongsTo(User::class, 'responsible_id'); }
    public function occurrences() { return $this->hasMany(ObligationOccurrence::class); }
}

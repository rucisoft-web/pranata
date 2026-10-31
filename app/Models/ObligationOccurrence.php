<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ObligationOccurrence extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'obligation_id', 'responsible_id', 'submitted_by', 'reviewed_by'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['obligation_snapshot' => 'array', 'due_date' => 'date', 'submitted_at' => 'datetime', 'fulfilled_at' => 'datetime', 'revision' => 'integer']; }
    public function obligation() { return $this->belongsTo(Obligation::class); }
    public function responsible() { return $this->belongsTo(User::class, 'responsible_id'); }
}

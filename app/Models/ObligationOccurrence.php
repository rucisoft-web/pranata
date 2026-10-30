<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ObligationOccurrence extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['obligation_snapshot' => 'array', 'due_date' => 'date', 'submitted_at' => 'datetime', 'fulfilled_at' => 'datetime', 'revision' => 'integer']; }
    public function obligation() { return $this->belongsTo(Obligation::class); }
    public function responsible() { return $this->belongsTo(User::class, 'responsible_id'); }
}

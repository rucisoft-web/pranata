<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GovernanceAction extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'governance_item_id', 'pic_id', 'created_by'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['due_date' => 'date', 'completed_at' => 'datetime']; }
    public function item() { return $this->belongsTo(GovernanceItem::class, 'governance_item_id'); }
    public function pic() { return $this->belongsTo(User::class, 'pic_id'); }
    public function overdue(): bool { return $this->status === 'open' && $this->due_date->lt(today()); }
}

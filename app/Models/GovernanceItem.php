<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GovernanceItem extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'governance_assessment_id', 'pic_id', 'submitted_by', 'reviewed_by'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['target_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'revision' => 'integer']; }
    public function assessment() { return $this->belongsTo(GovernanceAssessment::class, 'governance_assessment_id'); }
    public function pic() { return $this->belongsTo(User::class, 'pic_id'); }
    public function actions() { return $this->hasMany(GovernanceAction::class); }

    /** [prinsip, rekomendasi, contoh bukti, fungsi PIC lazim] dari pedoman. */
    public function definition(): array { return config('governance.recommendations')[$this->code]; }
    public function principle(): int { return $this->definition()[0]; }
    public function aspect(): int { return config('governance.principles')[$this->principle()][0]; }
    public function text(): string { return $this->definition()[1]; }
    public function editable(): bool { return in_array($this->review_status, ['draft', 'returned'], true); }
    public function needsExplanation(): bool { return in_array($this->status, ['partial', 'not_applied'], true); }
}

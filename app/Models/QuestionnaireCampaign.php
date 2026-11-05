<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuestionnaireCampaign extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'year', 'created_by', 'closed_by'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['due_date' => 'date', 'closed_at' => 'datetime', 'anonymous' => 'boolean']; }
    public function assignments() { return $this->hasMany(QuestionnaireAssignment::class); }
    public function responses() { return $this->hasMany(QuestionnaireResponse::class); }
    public function definition(): array { return config('questionnaires.templates')[$this->template]; }
    public function isOpen(): bool { return $this->status === 'open'; }

    /** Seluruh pertanyaan: key => [bagian, jenis, teks, flag]. */
    public function questions(): array
    {
        $out = [];
        foreach ($this->definition()['sections'] as $section => $questions) {
            foreach ($questions as $q) { $out[$q[0]] = [$section, $q[1], $q[2], $q[3] ?? null]; }
        }

        return $out;
    }
}

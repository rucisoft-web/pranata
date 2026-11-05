<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class QuestionnaireResponse extends Model
{
    use HasUuids, \App\Models\Concerns\IntegerKeys;

    public $timestamps = false;
    protected array $integerKeys = ['entity_id', 'questionnaire_campaign_id', 'questionnaire_assignment_id', 'exceptions'];
    protected $guarded = [];
    protected function casts(): array { return ['answers' => 'array']; }
    /** UUID acak (v4), bukan berurutan waktu, agar urutan tanggapan anonim tidak terbaca. */
    public function newUniqueId(): string { return (string) \Illuminate\Support\Str::uuid(); }
    public function assignment() { return $this->belongsTo(QuestionnaireAssignment::class, 'questionnaire_assignment_id'); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuestionnaireAssignment extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'questionnaire_campaign_id', 'user_id'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['submitted_at' => 'datetime']; }
    public function campaign() { return $this->belongsTo(QuestionnaireCampaign::class, 'questionnaire_campaign_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function response() { return $this->hasOne(QuestionnaireResponse::class); }
}

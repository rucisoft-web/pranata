<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\GovernanceAssessment;
use App\Models\GovernanceItem;
use App\Models\QuestionnaireCampaign;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private Entity $entity;
    private User $officer;
    /** @var User[] */
    private array $board = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->entity = Entity::create(['code' => 'UTAMA', 'name' => 'PT Utama Tbk']);
        $this->officer = $this->member($this->entity, 'compliance_officer');
        foreach (range(1, 4) as $i) { $this->board[] = $this->member($this->entity, 'board_member'); }
    }

    private function open(string $template, array $respondents, int $year = 2026): QuestionnaireCampaign
    {
        $this->signIn($this->officer)->post('/questionnaires', ['template' => $template, 'title' => 'Kuesioner '.$template, 'year' => $year,
            'due_date' => today()->addWeek()->toDateString(), 'respondents' => array_map(fn ($u) => $u->id, $respondents)])->assertRedirect();

        return QuestionnaireCampaign::where('template', $template)->latest('id')->firstOrFail();
    }

    private function answers(QuestionnaireCampaign $campaign, int $score = 4, array $override = []): array
    {
        $answers = [];
        foreach ($campaign->questions() as $key => [, $type, , $flag]) {
            $answers[$key] = ['value' => match ($type) { 'scale' => $score, 'yesno' => $flag === 'yes' ? 'no' : 'yes', default => 'Catatan responden '.$key }];
        }

        return array_replace_recursive($answers, $override);
    }

    public function test_anonymous_evaluation_needs_three_respondents_and_hides_identity(): void
    {
        $this->signIn($this->officer)->get('/questionnaires')->assertOk()->assertSee('Self assessment Dewan Komisaris');
        $this->signIn($this->officer)->post('/questionnaires', ['template' => 'board_eval', 'title' => 'Evaluasi Dekom', 'year' => 2026, 'due_date' => today()->addWeek()->toDateString(),
            'respondents' => [$this->board[0]->id, $this->board[1]->id]])->assertSessionHasErrors('respondents');
        $this->signIn($this->board[0])->post('/questionnaires', ['template' => 'board_eval', 'title' => 'Evaluasi Dekom', 'year' => 2026, 'due_date' => today()->addWeek()->toDateString(),
            'respondents' => [$this->board[0]->id]])->assertForbidden();

        $campaign = $this->open('board_eval', array_slice($this->board, 0, 3));
        $this->signIn($this->board[0])->get('/audit-actions')->assertSee('Kuesioner yang perlu diisi');
        $this->get('/questionnaires/'.$campaign->id.'/respond')->assertOk()->assertSee('ANONIM');
        $this->post('/questionnaires/'.$campaign->id.'/respond', ['answers' => []])->assertSessionHasErrors('answers.bc1.value');
        $this->post('/questionnaires/'.$campaign->id.'/respond', ['answers' => $this->answers($campaign, 5)])->assertRedirect('/questionnaires');
        $this->post('/questionnaires/'.$campaign->id.'/respond', ['answers' => $this->answers($campaign, 5)])->assertStatus(409);
        $response = QuestionnaireResponse::firstOrFail();
        $this->assertNull($response->questionnaire_assignment_id);
        $this->assertSame(5, $response->answers['bc1']['value']);
        $this->get('/questionnaires/'.$campaign->id)->assertOk()->assertDontSee('Hasil per pertanyaan');

        $this->signIn($this->officer)->get('/questionnaires/'.$campaign->id)->assertSee('Hasil baru ditampilkan setelah paling sedikit 3');
        $this->get('/questionnaires/'.$campaign->id.'/export')->assertStatus(409);
        $this->signIn($this->board[1])->post('/questionnaires/'.$campaign->id.'/respond', ['answers' => $this->answers($campaign, 3)])->assertRedirect();
        $this->signIn($this->board[2])->post('/questionnaires/'.$campaign->id.'/respond', ['answers' => $this->answers($campaign, 4)])->assertRedirect();
        $this->signIn($this->officer)->get('/questionnaires/'.$campaign->id)->assertSee('4,00')->assertSee('Hasil per pertanyaan')->assertDontSee($this->board[0]->name.'</strong>', false);
        $csv = $this->get('/questionnaires/'.$campaign->id.'/export')->assertOk()->getContent();
        $this->assertStringContainsString('Komposisi dan kompetensi', $csv);

        $outsider = $this->board[3];
        $this->signIn($outsider)->get('/questionnaires/'.$campaign->id)->assertNotFound();
        $this->post('/questionnaires/'.$campaign->id.'/respond', ['answers' => $this->answers($campaign)])->assertNotFound();
    }

    public function test_dno_exceptions_need_explanation_and_become_governance_evidence(): void
    {
        $this->signIn($this->officer)->post('/governance', ['year' => 2026, 'coordinator_id' => $this->officer->id])->assertRedirect();
        $assessment = GovernanceAssessment::firstOrFail();
        $item = GovernanceItem::where('governance_assessment_id', $assessment->id)->where('code', '7.1')->firstOrFail();
        $item->update(['pic_id' => $this->officer->id, 'status' => 'applied', 'implementation' => 'Kebijakan perdagangan saham orang dalam berlaku sejak 2023.']);

        $campaign = $this->open('dno', [$this->board[0]]);
        $this->assertFalse($campaign->anonymous);
        $this->signIn($this->board[0])->post('/questionnaires/'.$campaign->id.'/respond', ['answers' => $this->answers($campaign, 4, ['ns1' => ['value' => 'yes']])])
            ->assertSessionHasErrors('answers.ns1.note');
        $this->post('/questionnaires/'.$campaign->id.'/respond', ['answers' => $this->answers($campaign, 4, ['ns1' => ['value' => 'yes', 'note' => 'Memiliki 10.000 saham, sudah dilaporkan.']])])->assertRedirect();
        $this->assertSame(1, QuestionnaireResponse::firstOrFail()->exceptions);

        $this->signIn($this->officer)->get('/questionnaires/'.$campaign->id)->assertSee('Jawaban yang perlu ditindaklanjuti')->assertSee($this->board[0]->name)->assertSee('Memiliki 10.000 saham');
        // Sebelum ditutup, kuesioner belum menjadi bukti.
        $this->post('/governance/items/'.$item->id.'/submit', ['revision' => $item->fresh()->revision])->assertStatus(422);
        $this->post('/questionnaires/'.$campaign->id.'/close')->assertRedirect();
        $this->signIn($this->board[0])->post('/questionnaires/'.$campaign->id.'/close')->assertForbidden();
        $this->signIn($this->officer)->get('/governance/items/'.$item->id)->assertSee('Bukti dari kuesioner')->assertSee('Kuesioner dno 2026');
        $this->post('/governance/items/'.$item->id.'/submit', ['revision' => $item->fresh()->revision])->assertRedirect();
        $this->assertSame('submitted', $item->fresh()->review_status);
    }

    public function test_board_member_reads_executive_summary_and_guest_reads_demo_results_only(): void
    {
        $this->signIn($this->board[0])->get('/ringkasan')->assertOk();
        $this->seed(DemoSeeder::class);
        $this->assertSame(24, QuestionnaireCampaign::count());
        $campaign = QuestionnaireCampaign::where('template', 'dno')->orderBy('id')->firstOrFail();
        $this->flushSession();
        $this->get('/demo/questionnaires')->assertOk()->assertSee('Kuesioner Direksi dan Dewan Komisaris (D&amp;O)', false);
        $this->get('/demo/questionnaires/'.$campaign->id)->assertOk()->assertSee('Jawaban yang perlu ditindaklanjuti')->assertDontSee('Tutup kuesioner');
        $this->get('/demo/questionnaires/'.$campaign->id.'/respond')->assertNotFound();
        $this->post('/demo/questionnaires/'.$campaign->id.'/respond')->assertNotFound();
        $closedEval = QuestionnaireCampaign::where('template', 'board_eval')->where('status', 'closed')->where('year', now()->year)->orderBy('id')->firstOrFail();
        $item = GovernanceItem::where('entity_id', $closedEval->entity_id)->where('code', '4.1')->whereHas('assessment', fn ($q) => $q->where('year', now()->year))->firstOrFail();
        $this->get('/demo/governance/items/'.$item->id)->assertSee('Bukti dari kuesioner');
    }
}

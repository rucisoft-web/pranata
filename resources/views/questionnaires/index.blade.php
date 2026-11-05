@extends('layouts.app')
@section('title', 'Kuesioner & evaluasi')
@section('content')
@php($manage = $context->canManageGovernance() && ! request()->attributes->get('demo_mode'))
<div class="page-heading"><div><div class="eyebrow">PRANATA GOVERNANCE · KUESIONER</div><h1>Kuesioner & evaluasi</h1><p class="muted">Self assessment Dewan Komisaris, Direksi, dan komite, kuesioner D&O, serta pernyataan kepatuhan. Hasil yang ditutup otomatis menjadi bukti rekomendasi Pedoman Tata Kelola pada tahun buku yang sama.</p></div></div>

@if($mine->isNotEmpty())<section class="panel detail-panel gv-quote"><div class="section-kicker">MENUNGGU JAWABAN ANDA</div>
<ul class="timeline">@foreach($mine as $assignment)<li><a href="{{ route('questionnaires.respond', $assignment->questionnaire_campaign_id) }}"><strong>{{ $assignment->campaign->title }} {{ $assignment->campaign->year }}</strong></a> · tenggat {{ $assignment->campaign->due_date->format('d/m/Y') }}@if($assignment->campaign->due_date->lt(today())) <span class="badge high">Lewat tenggat</span>@endif<small>{{ $assignment->campaign->anonymous ? 'Anonim: jawaban disimpan tanpa identitas.' : 'Jawaban tercatat atas nama Anda.' }}</small></li>@endforeach</ul></section>@endif

<section class="panel"><div class="table-wrap"><table><thead><tr><th>Kuesioner</th><th>Tahun</th><th>Jenis</th><th>Respons</th><th>Tenggat</th><th>Status</th>@if($readAll)<th>Hasil</th>@endif</tr></thead><tbody>
@forelse($campaigns as $campaign)<tr><td><a href="{{ workspace_route('questionnaires.show', $campaign) }}"><strong>{{ $campaign->title }}</strong></a><small class="muted">{{ $campaign->definition()['organ'] }} · {{ $campaign->anonymous ? 'Anonim' : 'Bernama' }}</small></td><td>{{ $campaign->year }}</td><td>{{ $campaign->definition()['kind'] }}</td>
<td><div class="scorebar"><svg viewBox="0 0 100 8" aria-hidden="true"><rect width="100" height="8" rx="4" class="bar-track"></rect><rect width="{{ $campaign->assignments_count ? round($campaign->submitted_count / $campaign->assignments_count * 100) : 0 }}" height="8" rx="4" class="gv-applied"></rect></svg><span>{{ $campaign->submitted_count }}/{{ $campaign->assignments_count }}</span></div></td>
<td>{{ $campaign->due_date->format('d/m/Y') }}</td><td><span class="badge {{ $campaign->isOpen() ? 'submitted' : 'approved' }}">{{ $campaign->isOpen() ? 'Berjalan' : 'Ditutup' }}</span></td>
@if($readAll)<td>@if($s = $scores[$campaign->id] ?? null)@if($s['overall'] !== null)<strong>{{ number_format($s['overall'], 2, ',', '') }}</strong>/5 @endif @if($s['exception_total'])<span class="badge high">{{ $s['exception_total'] }} pengecualian</span>@endif @else <span class="muted">—</span>@endif</td>@endif</tr>
@empty<tr><td colspan="7" class="empty">Belum ada kuesioner.</td></tr>
@endforelse</tbody></table></div></section>

@if($manage)<div class="section-kicker">TEMPLAT</div><h2>Buka kuesioner baru</h2>
<div class="q-templates">@foreach(config('questionnaires.templates') as $key => $template)<article class="panel detail-panel"><div class="eyebrow">{{ strtoupper($template['kind']) }} · {{ $template['anonymous'] ? 'ANONIM' : 'BERNAMA' }}</div><h3>{{ $template['title'] }}</h3><p class="small muted">{{ $template['description'] }}</p><p class="small">{{ collect($template['sections'])->flatten(1)->count() }} pertanyaan · bukti rekomendasi {{ implode(', ', $template['governance']) }}</p><a class="button secondary small" href="{{ route('questionnaires.create', ['template' => $key]) }}">Gunakan templat</a></article>@endforeach</div>@endif
@endsection

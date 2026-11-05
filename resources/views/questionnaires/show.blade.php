@extends('layouts.app')
@section('title', $campaign->title)
@section('content')
@php($submitted = $campaign->assignments->where('status', 'submitted')->count())
<div class="page-heading"><div><div class="eyebrow">KUESIONER {{ $campaign->year }} · {{ strtoupper($campaign->definition()['kind']) }} · {{ $campaign->anonymous ? 'ANONIM' : 'BERNAMA' }}</div><h1>{{ $campaign->title }}</h1>
<p class="muted">{{ $campaign->definition()['organ'] }} · tenggat {{ $campaign->due_date->format('d/m/Y') }} · <span class="badge {{ $campaign->isOpen() ? 'submitted' : 'approved' }}">{{ $campaign->isOpen() ? 'Berjalan' : 'Ditutup '.$campaign->closed_at?->format('d/m/Y') }}</span></p></div>
<div class="exec-actions">@if($mine && $mine->status === 'pending' && $campaign->isOpen())<a class="button small" href="{{ route('questionnaires.respond', $campaign) }}">Isi kuesioner</a>@endif
@if($readAll && $summary && ! $summary['hidden'])<a class="button secondary small" href="{{ workspace_route('questionnaires.export', $campaign) }}">Unduh CSV</a><a class="button secondary small" href="#" data-print>Cetak / simpan PDF</a>@endif</div></div>

@if($mine && ! $readAll)<section class="panel detail-panel"><p>{{ $mine->status === 'submitted' ? 'Jawaban Anda sudah diterima'.($campaign->anonymous ? ' tanpa identitas.' : ' pada '.$mine->submitted_at->format('d/m/Y H:i').'.') : 'Anda belum mengisi kuesioner ini.' }}</p></section>@endif

@if($readAll)
<div class="metrics">
<div class="metric"><span>Tingkat respons</span><strong>{{ $campaign->assignments->count() ? round($submitted / $campaign->assignments->count() * 100) : 0 }}%</strong><small>{{ $submitted }} dari {{ $campaign->assignments->count() }} responden</small></div>
@if($summary['overall'] === null && ! $summary['hidden'])<div class="metric"><span>Responden dengan pengecualian</span><strong>{{ collect($summary['exceptions'])->pluck('user')->unique()->count() }}</strong><small>dari {{ $summary['responses'] }} tanggapan</small></div>@else<div class="metric"><span>Skor rata-rata</span><strong>{{ $summary['hidden'] || $summary['overall'] === null ? '—' : number_format($summary['overall'], 2, ',', '') }}</strong><small>@if($previousSummary && $previousSummary['overall'] && $summary['overall'] && ! $summary['hidden']){{ $summary['overall'] - $previousSummary['overall'] >= 0 ? '+' : '' }}{{ number_format($summary['overall'] - $previousSummary['overall'], 2, ',', '') }} dari {{ $previous->year }} ({{ number_format($previousSummary['overall'], 2, ',', '') }})@else Skala 1–5 @endif</small></div>@endif
<div class="metric"><span>Pengecualian</span><strong class="{{ $summary['exception_total'] ? 'danger' : '' }}">{{ $summary['hidden'] ? '—' : $summary['exception_total'] }}</strong><small>Jawaban yang perlu ditindaklanjuti</small></div>
<div class="metric"><span>Bukti Governance</span><strong class="q-codes">{{ implode(' · ', $campaign->definition()['governance']) }}</strong><small>{{ $campaign->isOpen() ? 'Aktif setelah kuesioner ditutup' : 'Tertaut ke penilaian '.$campaign->year }}</small></div>
</div>

@if($summary['hidden'])<div class="demo-notice"><strong>ANONIM</strong><span>Hasil baru ditampilkan setelah paling sedikit {{ config('questionnaires.min_anonymous_responses') }} tanggapan masuk agar jawaban tidak dapat dikenali. Saat ini {{ $summary['responses'] }} tanggapan.</span></div>
@else
@if(count($summary['sections']))<section class="panel detail-panel"><div class="section-kicker">HASIL PER BAGIAN</div><h2>Rata-rata skor (1–5)</h2>
<svg class="chart q-chart" viewBox="0 0 640 {{ count($summary['sections']) * 34 + 6 }}" role="img" aria-label="Rata-rata skor per bagian">
@foreach($summary['sections'] as $section => $avg)@php($y = $loop->index * 34 + 4)@php($prev = $previousSummary['sections'][$section] ?? null)
<text x="0" y="{{ $y + 15 }}" class="chart-label">{{ \Illuminate\Support\Str::limit($section, 40) }}</text>
<rect x="250" y="{{ $y }}" width="320" height="22" class="bar-track"></rect>
@if($avg)<rect x="250" y="{{ $y }}" width="{{ round($avg / 5 * 320, 1) }}" height="22" class="{{ $avg >= 4 ? 'gv-applied' : ($avg >= 3 ? 'gv-partial' : 'gv-not_applied') }}"><title>{{ $section }}: {{ $avg }}</title></rect>@endif
@if($prev)<rect x="{{ 250 + round($prev / 5 * 320, 1) - 1 }}" y="{{ $y - 3 }}" width="2" height="28" class="q-prev"><title>{{ $previous->year }}: {{ $prev }}</title></rect>@endif
<text x="580" y="{{ $y + 15 }}" class="chart-value">{{ $avg ? number_format($avg, 2, ',', '') : '—' }}</text>
@endforeach</svg>
<ul class="legend inline"><li><i class="swatch gv-applied"></i>≥ 4 baik</li><li><i class="swatch gv-partial"></i>3–3,99 cukup</li><li><i class="swatch gv-not_applied"></i>&lt; 3 perlu perhatian</li>@if($previous)<li><i class="swatch q-prev"></i>Garis = {{ $previous->year }}</li>@endif</ul></section>@endif

@if($summary['exceptions'])<section class="panel"><div class="detail-panel"><div class="section-kicker">PENGECUALIAN</div><h2>Jawaban yang perlu ditindaklanjuti</h2><p class="muted small">Gunakan untuk pengungkapan afiliasi, kepemilikan saham, dan benturan kepentingan di laporan tahunan.</p></div>
<div class="table-wrap"><table><thead><tr><th>Responden</th><th>Pertanyaan</th><th>Penjelasan</th></tr></thead><tbody>@foreach($summary['exceptions'] as $e)<tr><td><strong>{{ $e['user'] }}</strong></td><td>{{ $e['question'] }}<small class="muted">{{ $e['section'] }}</small></td><td>{{ $e['note'] ?: '—' }}</td></tr>@endforeach</tbody></table></div></section>@endif

<section class="panel"><div class="detail-panel"><div class="section-kicker">RINCIAN</div><h2>Hasil per pertanyaan</h2></div><div class="table-wrap"><table class="q-results"><thead><tr><th>Pertanyaan</th><th>Hasil</th><th>Sebaran / jawaban</th></tr></thead><tbody>
@php($last = null)@foreach($summary['questions'] as $q)@if($q['section'] !== $last)@php($last = $q['section'])<tr class="gv-group"><td colspan="3">{{ $last }}</td></tr>@endif
<tr><td class="gv-text">{{ $q['text'] }}</td>
@if($q['type'] === 'scale')<td class="nowrap"><strong>{{ $q['average'] !== null ? number_format($q['average'], 2, ',', '') : '—' }}</strong> <small class="muted">{{ $q['answered'] }} jawaban</small></td><td><div class="q-dist">@foreach($q['distribution'] as $score => $n)<span class="q-d{{ $score }}" title="{{ config('questionnaires.scale')[$score] }}: {{ $n }}">{{ $score }}<b>{{ $n }}</b></span>@endforeach</div></td>
@elseif($q['type'] === 'yesno')<td class="nowrap">Ya {{ $q['yes'] }} · Tidak {{ $q['no'] }}</td><td>@if($q['exceptions'])<span class="badge high">{{ $q['exceptions'] }} pengecualian</span>@else<span class="muted">Tidak ada pengecualian</span>@endif</td>
@else<td class="nowrap">{{ count($q['texts']) }} isian</td><td>@forelse($q['texts'] as $text)<p class="small q-text">“{{ $text }}”</p>@empty<span class="muted">—</span>@endforelse</td>@endif</tr>
@endforeach</tbody></table></div></section>
@endif

<div class="exec-grid"><section class="panel"><div class="detail-panel"><h2>Responden</h2></div><div class="table-wrap"><table><tbody>@foreach($campaign->assignments->sortBy(fn ($a) => $a->user->name) as $assignment)<tr><td>{{ $assignment->user->name }}</td><td>@if($assignment->status === 'submitted')<span class="badge approved">Sudah mengisi</span>@else<span class="badge draft">Belum mengisi</span>@endif</td></tr>@endforeach</tbody></table></div></section>
<section class="panel detail-panel"><h2>Bukti untuk Governance</h2><p class="small muted">Setelah ditutup, hasil kuesioner ini tampil sebagai bukti pada rekomendasi {{ implode(', ', $campaign->definition()['governance']) }} di penilaian tahun buku {{ $campaign->year }}.</p>
<ul class="evidence-list">@forelse($governance as $item)<li><a href="{{ workspace_route('governance.items.show', $item) }}">Rekomendasi {{ $item->code }}</a> <small>{{ \Illuminate\Support\Str::limit($item->text(), 90) }}</small></li>@empty<li class="muted">Belum ada penilaian Governance tahun {{ $campaign->year }}.</li>@endforelse</ul>
@if($manage && $campaign->isOpen())<hr><form method="post" action="{{ route('questionnaires.close', $campaign) }}">@csrf<p class="small muted">Menutup kuesioner menghentikan pengisian. {{ $campaign->assignments->count() - $submitted }} responden belum mengisi.</p><button class="button full">Tutup kuesioner dan tetapkan hasil</button></form>@endif
</section></div>
@endif
<p><a href="{{ workspace_route('questionnaires.index') }}">← Semua kuesioner</a></p>
<script src="{{ asset_v('js/print.js') }}"></script>
@endsection

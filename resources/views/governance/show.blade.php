@extends('layouts.app')
@section('title', 'Governance '.$assessment->year)
@section('content')
@php($manage = $context->canManageGovernance() && ! request()->attributes->get('demo_mode'))
@php($editPic = $manage && ! $assessment->isLocked())
<div class="page-heading"><div><div class="eyebrow">PRANATA GOVERNANCE · TAHUN BUKU {{ $assessment->year }}</div><h1>Matriks 25 rekomendasi</h1>
<p class="muted">{{ config('governance.framework') }} · {{ config('governance.reference') }} · Koordinator {{ $assessment->coordinator->name }} · <span class="badge {{ $assessment->isLocked() ? 'approved' : 'submitted' }}">{{ config('governance.assessment_statuses')[$assessment->status] }}</span></p></div>
<div class="exec-actions"><a class="button small" href="{{ workspace_route('governance.disclosure', $assessment) }}">Tabel pengungkapan</a><a class="button secondary small" href="{{ workspace_route('governance.disclosure', [$assessment, 'format' => 'doc']) }}">Unduh Word</a><a class="button secondary small" href="{{ workspace_route('governance.export', $assessment) }}">Unduh CSV</a></div></div>

<div class="metrics">
<div class="metric"><span>Indeks penerapan</span><strong>{{ $summary['index'] }}%</strong><small>@if($previousSummary){{ $summary['index'] - $previousSummary['index'] >= 0 ? '+' : '' }}{{ $summary['index'] - $previousSummary['index'] }} poin dari {{ $previous->year }} ({{ $previousSummary['index'] }}%)@else Diterapkan = 1, sebagian = 0,5 @endif</small></div>
<div class="metric"><span>Diterapkan</span><strong>{{ $summary['applied'] }}<small class="muted">/25</small></strong><small>{{ $summary['partial'] }} sebagian · {{ $summary['not_applied'] }} belum · {{ $summary['not_assessed'] }} belum dinilai</small></div>
<div class="metric"><span>Terverifikasi</span><strong>{{ $summary['verified'] }}<small class="muted">/25</small></strong><small>{{ $summary['submitted'] }} menunggu verifikasi · {{ $summary['unassigned'] }} tanpa PIC</small></div>
@php($openActions = $actions->where('status', 'open'))<div class="metric"><span>Tindak lanjut terbuka</span><strong class="{{ $openActions->filter->overdue()->count() ? 'danger' : '' }}">{{ $openActions->count() }}</strong><small>{{ $openActions->filter->overdue()->count() }} lewat tenggat · {{ $actions->where('status', 'done')->count() }} selesai</small></div>
</div>

<div class="exec-grid">
<section class="panel detail-panel"><div class="section-kicker">PENERAPAN PER ASPEK</div><h2>Status 25 rekomendasi</h2>
<svg class="chart" viewBox="0 0 400 {{ count($summary['by_aspect']) * 34 + 6 }}" role="img" aria-label="Status penerapan per aspek">
@foreach($summary['by_aspect'] as $aspect => $row)@php($y = ($loop->index) * 34 + 4)@php($x = 140)
<text x="0" y="{{ $y + 15 }}" class="chart-label">{{ $aspect }} · {{ $row['label'] }}</text>
@foreach(['applied', 'partial', 'not_applied', 'not_assessed'] as $status)@if($row[$status])@php($w = $row[$status] / 6 * 220)<rect x="{{ $x }}" y="{{ $y }}" width="{{ round($w, 1) }}" height="22" class="gv-{{ $status }}"><title>{{ $row['label'] }} · {{ config('governance.statuses')[$status] }}: {{ $row[$status] }}</title></rect>@php($x += $w)@endif
@endforeach<text x="{{ $x + 6 }}" y="{{ $y + 15 }}" class="chart-value">{{ $row['index'] }}%</text>
@endforeach</svg>
<ul class="legend inline">@foreach(config('governance.statuses') as $key => $label)<li><i class="swatch gv-{{ $key }}"></i>{{ $label }} <strong>{{ $summary[$key] }}</strong></li>@endforeach</ul></section>

<section class="panel detail-panel"><div class="section-kicker">ALUR PENILAIAN</div><h2>Kesiapan pengungkapan</h2>
<ol class="gv-progress">
<li class="{{ $summary['unassigned'] ? '' : 'done' }}"><strong>PIC ditetapkan</strong><span>{{ 25 - $summary['unassigned'] }}/25 rekomendasi</span></li>
<li class="{{ $summary['not_assessed'] ? '' : 'done' }}"><strong>Status dinilai</strong><span>{{ 25 - $summary['not_assessed'] }}/25 rekomendasi</span></li>
<li class="{{ $summary['verified'] === 25 ? 'done' : '' }}"><strong>Diverifikasi kepatuhan</strong><span>{{ $summary['verified'] }}/25 · {{ $summary['submitted'] }} menunggu</span></li>
<li class="{{ $assessment->isLocked() ? 'done' : '' }}"><strong>Disetujui untuk laporan tahunan</strong><span>{{ $assessment->approved_at ? $assessment->approved_at->format('d/m/Y').' oleh '.$assessment->approver?->name : 'Belum disetujui' }}</span></li>
</ol>
@if($manage && ! $assessment->isLocked())
@if($summary['verified'] === 25)<form method="post" action="{{ route('governance.approve', $assessment) }}">@csrf<input type="hidden" name="revision" value="{{ $assessment->revision }}"><label for="approve_note">Dasar persetujuan (mis. rapat Direksi, tanggal, nomor risalah)</label><textarea id="approve_note" name="note" rows="3" minlength="10" maxlength="10000" required></textarea><button class="button full">Setujui dan kunci penilaian</button></form>
@else<p class="muted small">Persetujuan tersedia setelah seluruh 25 rekomendasi terverifikasi.</p>@endif
@elseif($assessment->approval_note)<p class="small"><strong>Catatan persetujuan:</strong> {{ $assessment->approval_note }}</p>@endif
</section>
</div>

<section class="panel"><form method="get" class="filters">
<div><label for="aspect">Aspek</label><select id="aspect" name="aspect"><option value="">Semua aspek</option>@foreach(config('governance.short_aspects') as $key => $label)<option value="{{ $key }}" @selected(request('aspect') == $key)>{{ $key }} · {{ $label }}</option>@endforeach</select></div>
<div><label for="status">Status penerapan</label><select id="status" name="status"><option value="">Semua</option>@foreach(config('governance.statuses') as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
<div><label for="review">Verifikasi</label><select id="review" name="review"><option value="">Semua</option>@foreach(config('governance.review_statuses') as $key => $label)<option value="{{ $key }}" @selected(request('review') === $key)>{{ $label }}</option>@endforeach</select></div>
<div><label for="pic">PIC</label><select id="pic" name="pic"><option value="">Semua</option><option value="none" @selected(request('pic') === 'none')>Belum ada PIC</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected(request('pic') == $member->id)>{{ $member->name }}</option>@endforeach</select></div>
<button class="button secondary">Terapkan</button>@if(request()->hasAny(['aspect', 'status', 'review', 'pic']))<a href="{{ workspace_route('governance.show', $assessment) }}">Reset</a>@endif</form>
@if($editPic)<form method="post" action="{{ route('governance.assign', $assessment) }}">@csrf @endif
<div class="table-wrap"><table class="gv-matrix"><thead><tr><th>Kode</th><th>Rekomendasi</th><th>Penerapan</th><th>PIC</th><th>Bukti</th><th>Verifikasi</th><th>Tindak lanjut</th></tr></thead><tbody>
@php($lastPrinciple = null)
@forelse($visible as $item)
@if($item->principle() !== $lastPrinciple)@php($lastPrinciple = $item->principle())<tr class="gv-group"><td colspan="7"><span>Aspek {{ $item->aspect() }} · {{ config('governance.short_aspects')[$item->aspect()] }}</span> Prinsip {{ $lastPrinciple }}: {{ config('governance.principles')[$lastPrinciple][1] }}</td></tr>@endif
<tr><td><a href="{{ workspace_route('governance.items.show', $item) }}"><strong>{{ $item->code }}</strong></a></td>
<td class="gv-text"><a href="{{ workspace_route('governance.items.show', $item) }}">{{ $item->text() }}</a>@if($item->needsExplanation() && $item->explanation)<small class="muted">Alasan: {{ \Illuminate\Support\Str::limit($item->explanation, 110) }}</small>@endif</td>
<td>@include('governance._status', ['status' => $item->status])@if($prev = $previousItems->get($item->code))<small class="muted">{{ $previous->year }}: {{ config('governance.statuses')[$prev->status] }}</small>@endif</td>
<td>@if($editPic && $item->editable())<select name="pic[{{ $item->code }}]" aria-label="PIC rekomendasi {{ $item->code }}"><option value="">—</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected($item->pic_id === $member->id)>{{ $member->name }}</option>@endforeach</select>@else<span title="Fungsi lazim: {{ $item->definition()[3] }}">{{ $item->pic ? \Illuminate\Support\Str::before($item->pic->name, ' · ') : '—' }}</span>@endif</td>
<td class="nowrap">{{ (int) ($evidenceCounts[$item->id] ?? 0) }} berkas @if($item->evidence_note)<small class="muted">+ referensi</small>@endif @if(in_array($item->code, $questionnaireCodes))<small class="muted">+ kuesioner</small>@endif</td>
<td><span class="badge {{ ['draft' => 'draft', 'submitted' => 'submitted', 'returned' => 'high', 'verified' => 'approved'][$item->review_status] }}">{{ config('governance.review_statuses')[$item->review_status] }}</span></td>
<td class="nowrap">@php($open = $item->actions->where('status', 'open'))@if($item->actions->isEmpty())<span class="muted">—</span>@else{{ $open->count() }} terbuka @if($open->filter->overdue()->count())<small class="danger">{{ $open->filter->overdue()->count() }} lewat tenggat</small>@endif<small class="muted">{{ $item->actions->where('status', 'done')->count() }} selesai</small>@endif</td></tr>
@empty<tr><td colspan="7" class="empty">Tidak ada rekomendasi yang sesuai filter.</td></tr>
@endforelse</tbody></table></div>
@if($editPic)<div class="gv-assign"><button class="button secondary">Simpan penugasan PIC</button><span class="muted small">Hanya rekomendasi yang belum diajukan atau perlu perbaikan yang dapat dialihkan.</span></div></form>@endif
</section>

@if($openActions->isNotEmpty())<section class="panel"><div class="detail-panel"><div class="section-kicker">TINDAK LANJUT</div><h2>Rencana perbaikan yang masih berjalan</h2></div>
<div class="table-wrap"><table><thead><tr><th>Rekomendasi</th><th>Tindak lanjut</th><th>PIC</th><th>Tenggat</th></tr></thead><tbody>@foreach($openActions->sortBy('due_date') as $action)<tr><td><a href="{{ workspace_route('governance.items.show', $action->governance_item_id) }}">{{ $items->firstWhere('id', $action->governance_item_id)->code }}</a></td><td>{{ $action->title }}</td><td>{{ $members->firstWhere('id', $action->pic_id)?->name ?? '—' }}</td><td>{{ $action->due_date->format('d/m/Y') }}@if($action->overdue()) <span class="badge high">Lewat tenggat</span>@endif</td></tr>@endforeach</tbody></table></div></section>@endif
<p><a href="{{ workspace_route('governance.index') }}">← Semua tahun buku</a></p>
@endsection

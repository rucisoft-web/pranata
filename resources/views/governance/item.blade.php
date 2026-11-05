@extends('layouts.app')
@section('title', 'Rekomendasi '.$item->code)
@section('content')
@php($demo = request()->attributes->get('demo_mode'))
@php($manage = $context->canManageGovernance() && ! $demo)
@php($definition = $item->definition())
<div class="eyebrow">GOVERNANCE {{ $item->assessment->year }} · ASPEK {{ $item->aspect() }} · PRINSIP {{ $item->principle() }}</div>
<h1>Rekomendasi {{ $item->code }}</h1>
<p class="muted">{{ config('governance.aspects')[$item->aspect()] }} · {{ config('governance.principles')[$item->principle()][1] }}</p>
<section class="panel detail-panel gv-quote"><p>{{ $item->text() }}</p><small class="muted">Contoh bukti: {{ $definition[2] }}</small></section>
<p>@include('governance._status', ['status' => $item->status]) <span class="badge {{ ['draft' => 'draft', 'submitted' => 'submitted', 'returned' => 'high', 'verified' => 'approved'][$item->review_status] }}">{{ config('governance.review_statuses')[$item->review_status] }}</span> @if($item->assessment->isLocked())<span class="badge approved">Penilaian dikunci</span>@endif</p>

<div class="detail-grid"><section class="panel detail-panel">
@if($canEdit)
<h2>Penilaian penerapan</h2>
<form method="post" action="{{ route('governance.items.update', $item) }}">@csrf @method('put')<input type="hidden" name="revision" value="{{ $item->revision }}">
<label for="status">Status penerapan</label><select id="status" name="status" required>@foreach(config('governance.statuses') as $key => $label)<option value="{{ $key }}" @selected(old('status', $item->status) === $key)>{{ $label }} ({{ config('governance.disclosure_labels')[$key] }})</option>@endforeach</select>
@if($manage)<label for="pic_id">PIC</label><select id="pic_id" name="pic_id"><option value="">— Belum ditetapkan —</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected(old('pic_id', $item->pic_id) == $member->id)>{{ $member->name }}</option>@endforeach</select><small class="muted">Fungsi yang lazim: {{ $definition[3] }}</small>@endif
<label for="implementation">Uraian penerapan</label><textarea id="implementation" name="implementation" rows="5" maxlength="10000" placeholder="Jelaskan praktik yang sudah berjalan, dasar kebijakannya, dan kapan ditetapkan.">{{ old('implementation', $item->implementation) }}</textarea>
<label for="evidence_note">Referensi bukti</label><textarea id="evidence_note" name="evidence_note" rows="3" maxlength="5000" placeholder="Nomor dan tanggal kebijakan, halaman laporan tahunan, atau tautan situs web.">{{ old('evidence_note', $item->evidence_note) }}</textarea>
<fieldset class="gv-explain"><legend>Wajib bila diterapkan sebagian atau belum diterapkan</legend>
<label for="explanation">Alasan belum diterapkan</label><textarea id="explanation" name="explanation" rows="4" maxlength="10000" placeholder="Alasan yang akan diungkapkan di laporan tahunan.">{{ old('explanation', $item->explanation) }}</textarea>
<label for="alternative_practice">Praktik alternatif</label><textarea id="alternative_practice" name="alternative_practice" rows="3" maxlength="10000" placeholder="Praktik lain yang dijalankan untuk mencapai tujuan rekomendasi.">{{ old('alternative_practice', $item->alternative_practice) }}</textarea>
<label for="target_date">Target penerapan</label><input id="target_date" type="date" name="target_date" value="{{ old('target_date', $item->target_date?->format('Y-m-d')) }}"></fieldset>
<button class="button">Simpan penilaian</button></form>
@else
<h2>Penilaian penerapan</h2>
<dl><dt>Status penerapan</dt><dd>{{ config('governance.statuses')[$item->status] }} · diungkapkan sebagai "{{ config('governance.disclosure_labels')[$item->status] }}"</dd>
<dt>PIC</dt><dd>{{ $item->pic?->name ?? '—' }} <small class="muted">(lazimnya {{ $definition[3] }})</small></dd>
<dt>Uraian penerapan</dt><dd>{{ $item->implementation ?: '—' }}</dd><dt>Referensi bukti</dt><dd>{{ $item->evidence_note ?: '—' }}</dd>
<dt>Alasan belum diterapkan</dt><dd>{{ $item->explanation ?: '—' }}</dd><dt>Praktik alternatif</dt><dd>{{ $item->alternative_practice ?: '—' }}</dd>
<dt>Target penerapan</dt><dd>{{ $item->target_date?->format('d/m/Y') ?? '—' }}</dd></dl>
@endif
<hr>
@if($questionnaires->isNotEmpty())<h2>Bukti dari kuesioner</h2><ul class="evidence-list">@foreach($questionnaires as $campaign)<li><a href="{{ workspace_route('questionnaires.show', $campaign) }}">{{ $campaign->title }} {{ $campaign->year }}</a> <small>{{ $campaign->responses()->count() }} tanggapan · ditutup {{ $campaign->closed_at?->format('d/m/Y') }}</small></li>@endforeach</ul>@endif
@include('components.evidence', ['subjectType' => 'governance', 'subjectId' => $item->id, 'canUpload' => $canEdit])
</section>
<aside class="panel detail-panel"><h2>Verifikasi</h2>
<dl><dt>Diajukan</dt><dd>{{ $item->submitted_at?->format('d/m/Y H:i') ?? '—' }}</dd><dt>Catatan verifikasi</dt><dd>{{ $item->review_note ?? '—' }}</dd><dt>Diverifikasi</dt><dd>{{ $item->reviewed_at?->format('d/m/Y H:i') ?? '—' }}</dd></dl>
@if($canEdit)
<form method="post" action="{{ route('governance.items.transition', [$item, 'submit']) }}">@csrf<input type="hidden" name="revision" value="{{ $item->revision }}"><p class="muted small">Simpan penilaian terlebih dahulu. Pengajuan memeriksa PIC, status, uraian, bukti, dan alasan bila belum diterapkan.</p><button class="button full">Ajukan verifikasi</button></form>
@elseif($manage && $item->review_status === 'submitted' && $item->submitted_by !== auth()->id() && $item->pic_id !== auth()->id())
@foreach(['verify' => 'Verifikasi', 'return' => 'Kembalikan ke PIC'] as $operation => $label)<form method="post" action="{{ route('governance.items.transition', [$item, $operation]) }}">@csrf<input type="hidden" name="revision" value="{{ $item->revision }}"><label for="note_{{ $operation }}">Catatan {{ strtolower($label) }}</label><textarea id="note_{{ $operation }}" name="note" rows="3" minlength="10" maxlength="10000" required></textarea><button class="button {{ $operation === 'return' ? 'secondary' : '' }} full">{{ $label }}</button></form>@endforeach
@elseif($item->review_status === 'submitted')<p class="muted small">Menunggu verifikasi oleh petugas kepatuhan selain PIC dan pengaju.</p>
@endif
@if($previous)<hr><h2>Tahun {{ $previous->assessment->year }}</h2><p>@include('governance._status', ['status' => $previous->status])</p><p class="small">{{ \Illuminate\Support\Str::limit($previous->needsExplanation() ? $previous->explanation : $previous->implementation, 260) ?: '—' }}</p><a href="{{ workspace_route('governance.items.show', $previous) }}">Lihat penilaian {{ $previous->assessment->year }} →</a>@endif
<hr><a href="{{ workspace_route('governance.show', $item->assessment) }}">← Matriks {{ $item->assessment->year }}</a></aside></div>

<section class="panel detail-panel"><div class="section-kicker">TINDAK LANJUT</div><h2>Rencana perbaikan</h2>
<div class="table-wrap"><table><thead><tr><th>Tindak lanjut</th><th>PIC</th><th>Tenggat</th><th>Status</th></tr></thead><tbody>
@forelse($item->actions->sortBy('due_date') as $action)<tr><td>{{ $action->title }}@if($action->completion_note)<small class="muted">Hasil: {{ $action->completion_note }}</small>@endif</td><td>{{ $action->pic->name }}</td><td>{{ $action->due_date->format('d/m/Y') }}@if($action->overdue()) <span class="badge high">Lewat tenggat</span>@endif</td>
<td>{{ config('governance.action_statuses')[$action->status] }}@if($action->completed_at)<small class="muted">{{ $action->completed_at->format('d/m/Y') }}</small>@endif
@if(! $demo && $action->status === 'open' && ($action->pic_id === auth()->id() || $manage))<details><summary>Tandai selesai</summary><form method="post" action="{{ route('governance.actions.done', $action) }}">@csrf<label for="done_{{ $action->id }}">Hasil tindak lanjut</label><textarea id="done_{{ $action->id }}" name="note" rows="2" minlength="10" maxlength="10000" required></textarea><button class="button small">Selesai</button></form></details>@endif</td></tr>
@empty<tr><td colspan="4" class="empty">Belum ada tindak lanjut.@if($item->needsExplanation()) Tambahkan rencana untuk mencapai penerapan penuh.@endif</td></tr>
@endforelse</tbody></table></div>
@if(! $demo && ($manage || ($item->pic_id === auth()->id() && in_array($context->role, \App\Http\Controllers\GovernanceController::PIC_ROLES, true))))
<form method="post" action="{{ route('governance.items.action', $item) }}" class="filters">@csrf<div class="grow"><label for="action_title">Tindak lanjut baru</label><input id="action_title" name="title" minlength="10" maxlength="255" required placeholder="mis. Menyusun kebijakan suksesi Direksi"></div>
<div><label for="action_pic">PIC</label><select id="action_pic" name="pic_id" required>@foreach($members as $member)<option value="{{ $member->id }}" @selected(($item->pic_id ?? auth()->id()) === $member->id)>{{ $member->name }}</option>@endforeach</select></div>
<div><label for="action_due">Tenggat</label><input id="action_due" type="date" name="due_date" min="{{ today()->format('Y-m-d') }}" required></div><button class="button secondary">Tambah</button></form>@endif
</section>

<section class="panel detail-panel"><h2>Riwayat</h2><ul class="timeline">@forelse($history as $event)<li><strong>{{ $event->label }}</strong> · {{ $event->actor?->name ?? 'Sistem' }} <small class="muted">{{ $event->created_at->format('d/m/Y H:i') }}</small></li>@empty<li class="muted">Belum ada riwayat.</li>@endforelse</ul></section>
@endsection

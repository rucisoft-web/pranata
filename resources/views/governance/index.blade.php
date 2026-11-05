@extends('layouts.app')
@section('title', 'PRANATA Governance')
@section('content')
<div class="page-heading"><div><div class="eyebrow">PRANATA GOVERNANCE · TERAPKAN ATAU JELASKAN</div><h1>Penerapan Pedoman Tata Kelola</h1><p class="muted">{{ config('governance.framework') }} ({{ config('governance.reference') }}): 5 aspek, 8 prinsip, dan 25 rekomendasi yang penerapannya diungkapkan di laporan tahunan.</p></div></div>
<section class="panel gv-explainer"><div class="gv-steps">
<div><strong>1 · Tugaskan PIC</strong><span>Setiap rekomendasi punya penanggung jawab, misalnya Sekretaris Perusahaan atau Komite Nominasi dan Remunerasi.</span></div>
<div><strong>2 · Nilai dan lampirkan bukti</strong><span>PIC memilih status penerapan, menguraikan praktiknya, dan melampirkan kebijakan atau dokumen pendukung.</span></div>
<div><strong>3 · Jelaskan yang belum</strong><span>Bila belum atau baru sebagian diterapkan, PIC wajib menjelaskan alasan, praktik alternatif, dan target penerapan.</span></div>
<div><strong>4 · Verifikasi dan setujui</strong><span>Petugas kepatuhan memverifikasi, lalu penilaian disetujui dan dikunci untuk pengungkapan.</span></div>
<div><strong>5 · Ungkapkan dan tindak lanjuti</strong><span>Tabel pengungkapan diekspor ke Word, PDF, atau CSV. Rencana perbaikan dipantau sampai selesai.</span></div>
</div></section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Tahun buku</th><th>Status</th><th>Indeks penerapan</th><th>Diterapkan · sebagian · belum</th><th>Terverifikasi</th><th>Koordinator</th></tr></thead><tbody>
@forelse($assessments as $assessment)@php($s = $scores[$assessment->id])<tr><td><a href="{{ workspace_route('governance.show', $assessment) }}"><strong>{{ $assessment->year }}</strong></a></td><td><span class="badge {{ $assessment->isLocked() ? 'approved' : 'submitted' }}">{{ config('governance.assessment_statuses')[$assessment->status] }}</span></td>
<td><div class="scorebar"><svg viewBox="0 0 100 8" aria-hidden="true"><rect width="100" height="8" rx="4" class="bar-track"></rect><rect width="{{ $s['index'] }}" height="8" rx="4" class="gv-applied"></rect></svg><span>{{ $s['index'] }}%</span></div></td>
<td>{{ $s['applied'] }} · {{ $s['partial'] }} · {{ $s['not_applied'] }}@if($s['not_assessed']) <small class="muted">({{ $s['not_assessed'] }} belum dinilai)</small>@endif</td><td>{{ $s['verified'] }}/{{ $s['total'] }}</td><td>{{ $assessment->coordinator->name }}</td></tr>
@empty<tr><td colspan="6" class="empty">Belum ada penilaian. {{ $context->canManageGovernance() ? 'Buat penilaian tahun buku pertama di bawah.' : 'Petugas kepatuhan atau administrator dapat membuat penilaian.' }}</td></tr>
@endforelse</tbody></table></div></section>
@if($context->canManageGovernance() && ! request()->attributes->get('demo_mode'))
<section class="panel detail-panel"><h2>Buat penilaian tahun buku</h2><p class="muted">Sistem membuat 25 rekomendasi sekaligus. Isian tahun sebelumnya (status, PIC, uraian, alasan) dapat disalin agar penilaian tahunan tidak dimulai dari nol. Berkas bukti tidak ikut disalin.</p>
<form method="post" action="{{ route('governance.store') }}" class="filters">@csrf<div><label for="year">Tahun buku</label><input id="year" type="number" name="year" min="2015" max="2100" value="{{ old('year', $nextYear) }}" required></div>
<div><label for="coordinator_id">Koordinator</label><select id="coordinator_id" name="coordinator_id" required>@foreach($members->filter(fn ($m) => in_array($m->roleIn($context->entity->id), ['admin', 'compliance_officer'])) as $member)<option value="{{ $member->id }}" @selected(old('coordinator_id', auth()->id()) == $member->id)>{{ $member->name }}</option>@endforeach</select></div>
<label class="check"><input type="checkbox" name="copy_previous" value="1" @checked($assessments->isNotEmpty())> Salin isian tahun sebelumnya</label><button class="button">Buat penilaian</button></form></section>
@endif
@endsection

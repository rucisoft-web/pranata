@extends('layouts.app')
@section('title', 'Pengungkapan tata kelola '.$assessment->year)
@section('content')
<div class="page-heading gv-noprint"><div><div class="eyebrow">PRANATA GOVERNANCE · EKSPOR PENGUNGKAPAN</div><h1>Tabel pengungkapan {{ $assessment->year }}</h1><p class="muted">Siap disalin ke bab Tata Kelola Perusahaan pada laporan tahunan.</p></div>
<div class="exec-actions"><a class="button small" href="#" data-print>Cetak / simpan PDF</a><a class="button secondary small" href="{{ workspace_route('governance.disclosure', [$assessment, 'format' => 'doc']) }}">Unduh Word</a><a class="button secondary small" href="{{ workspace_route('governance.export', $assessment) }}">Unduh CSV</a><a class="button secondary small" href="{{ workspace_route('governance.show', $assessment) }}">Kembali ke matriks</a></div></div>
@unless($assessment->isLocked())<div class="gv-draft"><strong>DRAF</strong><span>Penilaian belum disetujui. {{ $summary['verified'] }} dari 25 rekomendasi telah diverifikasi. Gunakan tabel ini sebagai bahan kerja, bukan versi final.</span></div>@endunless
<section class="panel detail-panel gv-sheet">
<div class="eyebrow">{{ strtoupper($context->entity->name) }}</div>
<h2>Penerapan {{ config('governance.framework') }} Tahun Buku {{ $assessment->year }}</h2>
<p>Sesuai {{ config('governance.reference') }}, Perseroan mengungkapkan penerapan 25 rekomendasi dengan pendekatan <em>terapkan atau jelaskan</em>. Pada tahun buku {{ $assessment->year }}, Perseroan telah memenuhi {{ $summary['applied'] }} rekomendasi, memenuhi sebagian {{ $summary['partial'] }} rekomendasi, dan belum memenuhi {{ $summary['not_applied'] }} rekomendasi @if($summary['not_assessed']) ({{ $summary['not_assessed'] }} belum dinilai)@endif. Penjelasan atas rekomendasi yang belum atau baru sebagian dipenuhi disajikan di bawah ini.</p>
@include('governance._disclosure-table')
<p class="muted small">@if($assessment->isLocked())Disetujui {{ $assessment->approved_at->format('d/m/Y') }} oleh {{ $assessment->approver?->name }}. {{ $assessment->approval_note }}@else Draf dibuat {{ now()->format('d/m/Y H:i') }}.@endif</p>
</section>
<script src="{{ asset_v('js/print.js') }}"></script>
@endsection

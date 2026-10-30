@extends('layouts.app')
@section('title', 'Dashboard eksekutif')
@section('content')
<div class="page-heading"><div><div class="eyebrow">PENGAWASAN KORPORASI</div><h1>Dashboard eksekutif</h1><p class="muted">{{ $context->entity->name }} · Gambaran register risiko saat ini</p></div>
@if($context->canWrite())<a class="button" href="{{ route('risks.create') }}">+ Catat risiko</a>@endif</div>
<div class="metrics">
    <div class="metric"><span>Total risiko</span><strong>{{ $stats['total'] }}</strong><small>Seluruh status pencatatan</small></div>
    <div class="metric"><span>Tinggi & kritis</span><strong class="danger">{{ $stats['high'] }}</strong><small>Skor inheren ≥12 · seluruh status</small></div>
    <div class="metric"><span>Menunggu tinjauan</span><strong>{{ $stats['submitted'] }}</strong><small>Memerlukan peninjau independen</small></div>
    <div class="metric"><span>Jadwal review terlewat</span><strong>{{ $stats['overdue'] }}</strong><small>Tanggal review sebelum hari ini (UTC)</small></div>
</div>
<section class="panel"><div class="panel-heading"><h2>Risiko yang terakhir diperbarui</h2><a href="{{ route('risks.index') }}">Lihat register →</a></div>
@include('risks.table', ['risks' => $latest])
</section>
<div class="notice neutral"><strong>Dasar penilaian: PRN-INITIAL-1.</strong> Kemungkinan × dampak, skala 1–5. Rendah 1–5, sedang 6–11, tinggi 12–19, kritis 20–25. Metodologi awal ini perlu disahkan perusahaan; penilaian residual dan agregasi grup belum tersedia.</div>
@endsection

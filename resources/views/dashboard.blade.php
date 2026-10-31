@extends('layouts.app')
@section('title', 'Dashboard eksekutif')
@section('content')
<div class="page-heading"><div><div class="eyebrow">PENGAWASAN KORPORASI</div><h1>Dashboard eksekutif</h1><p class="muted">{{ $context->entity->name }} · Gambaran register risiko saat ini</p></div>
@if($context->canWrite())<a class="button" href="{{ route('risks.create') }}">+ Catat risiko</a>
@endif</div>
<div class="metrics">
    <div class="metric"><span>Total risiko</span><strong>{{ $stats['total'] }}</strong><small>Seluruh status pencatatan</small></div>
    <div class="metric"><span>Tinggi & kritis</span><strong class="danger">{{ $stats['high'] }}</strong><small>Skor inheren ≥12 · seluruh status</small></div>
    <div class="metric"><span>Menunggu tinjauan</span><strong>{{ $stats['submitted'] }}</strong><small>Memerlukan peninjau independen</small></div>
    <div class="metric"><span>Jadwal review terlewat</span><strong>{{ $stats['overdue'] }}</strong><small>Tanggal review sebelum hari ini</small></div>
</div>
<div class="overview-grid"><section class="panel detail-panel"><div class="section-kicker">PROFIL RISIKO</div><h2>Peta risiko inheren</h2><p class="muted small">Jumlah risiko pada setiap sel · seluruh status.</p><div class="heatmap" role="img" aria-label="Matriks kemungkinan dan dampak risiko skala satu sampai lima">
@for($likelihood = 5; $likelihood >= 1; $likelihood--)<span class="axis">{{ $likelihood }}</span>@for($impact = 1; $impact <= 5; $impact++)@php($score = $likelihood * $impact)<div class="heat-cell {{ $score >= 20 ? 'critical' : ($score >= 12 ? 'high' : ($score >= 6 ? 'medium' : 'low')) }}" title="Kemungkinan {{ $likelihood }}, dampak {{ $impact }}">{{ $heatmap->get($likelihood.'-'.$impact)?->total ?? '·' }}</div>
@endfor
@endfor<span></span>@for($i=1;$i<=5;$i++)<span class="axis">{{ $i }}</span>
@endfor</div><p class="muted small">Sumbu vertikal: kemungkinan · horizontal: dampak</p></section>
<section class="panel detail-panel"><div class="section-kicker">PENGAWASAN & TINDAK LANJUT</div><h2>Hal yang memerlukan perhatian</h2><div class="attention-row"><div><strong>Kepatuhan lewat tenggat</strong><small>Belum diverifikasi dipenuhi · dari {{ $complianceStats['total'] }} periode</small></div><span>{{ $complianceStats['late'] }}</span></div><div class="attention-row"><div><strong>Menunggu verifikasi kepatuhan</strong><small>Bukti sudah diajukan oleh PIC</small></div><span>{{ $complianceStats['pending'] }}</span></div><div class="attention-row"><div><strong>Temuan audit terbuka</strong><small>Sesuai lingkup akses · {{ $findingStats['late'] }} lewat tenggat</small></div><span>{{ $findingStats['open'] }}</span></div><a class="text-link" href="{{ workspace_route('compliance.index') }}">Buka kalender kepatuhan →</a></section></div>
<section class="panel"><div class="panel-heading"><h2>Risiko yang terakhir diperbarui</h2><a href="{{ workspace_route('risks.index') }}">Lihat register →</a></div>
@include('risks.table', ['risks' => $latest])
</section>
<div class="notice neutral"><strong>Dasar penilaian: PRN-INITIAL-1.</strong> Kemungkinan × dampak, skala 1–5. Rendah 1–5, sedang 6–11, tinggi 12–19, kritis 20–25. Metodologi awal ini perlu disahkan perusahaan; penilaian residual dan agregasi grup belum tersedia.</div>
@endsection

@extends('layouts.app')
@section('title', 'Ringkasan eksekutif')
@section('content')
@php($h = $headline)
@php($levelClass = ['Kritis' => 'lv-kritis', 'Tinggi' => 'lv-tinggi', 'Sedang' => 'lv-sedang', 'Rendah' => 'lv-rendah'])
<section class="exec-hero panel">
    <div class="exec-hero-text">
        <div class="eyebrow">RINGKASAN EKSEKUTIF · {{ now()->translatedFormat('d F Y') }}</div>
        <h1>{{ $context->entity->name }}</h1>
        <p class="muted">Gambaran risiko, kepatuhan, dan audit internal untuk Direksi, Dewan Komisaris, dan Komite Audit.
            @if($context->entity->is_bumn)<span class="badge neutral">BUMN</span>@endif
            @if($context->entity->is_listed)<span class="badge neutral">Perusahaan terbuka</span>@endif</p>
        <div class="exec-actions"><a class="button secondary small" href="#" data-print>Cetak / simpan PDF</a><a class="button secondary small" href="{{ workspace_route('dashboard') }}">Dashboard operasional</a></div>
    </div>
    <svg class="exec-illustration" viewBox="0 0 320 180" width="300" height="169" role="img" aria-label="Ilustrasi tiga pilar tata kelola: risiko, kepatuhan, dan audit">
        <rect x="0" y="0" width="320" height="180" rx="10" fill="#f1f6f8" class="ill-bg"></rect>
        <path d="M30 150 L290 150" stroke="#c9d6dc" stroke-width="2" class="ill-line"></path>
        <polygon points="160,22 268,62 52,62" fill="#142c38" class="ill-roof"></polygon>
        <rect x="70" y="68" width="22" height="78" rx="3" fill="#b4574a" class="ill-pillar p1"></rect>
        <rect x="149" y="68" width="22" height="78" rx="3" fill="#245b5a" class="ill-pillar p2"></rect>
        <rect x="228" y="68" width="22" height="78" rx="3" fill="#b69759" class="ill-pillar p3"></rect>
        <rect x="58" y="146" width="204" height="8" rx="2" fill="#20353d" class="ill-base"></rect>
        <text x="81" y="170" text-anchor="middle" fill="#506773" class="ill-label">Risiko</text>
        <text x="160" y="170" text-anchor="middle" fill="#506773" class="ill-label">Kepatuhan</text>
        <text x="239" y="170" text-anchor="middle" fill="#506773" class="ill-label">Audit</text>
        <text x="160" y="52" text-anchor="middle" fill="#d7bd88" class="ill-mark">{{ \Illuminate\Support\Str::of($context->entity->name)->replace(['PT ', '(Persero) ', ' Tbk', ' — DEMO'], '')->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}</text>
    </svg>
</section>

<div class="metrics exec-metrics">
    <div class="metric"><span>Risiko tinggi & kritis</span><strong class="danger">{{ $h['risk_high'] }}</strong><small>dari {{ $h['risk_total'] }} risiko · {{ $h['risk_approved'] }} disetujui</small></div>
    <div class="metric"><span>Kepatuhan tepat waktu</span><strong>{{ $h['compliance_rate'] !== null ? $h['compliance_rate'].'%' : '—' }}</strong><small>{{ $h['compliance_overdue'] }} lewat tenggat · {{ $h['compliance_upcoming'] }} jatuh tempo 30 hari</small></div>
    <div class="metric"><span>Temuan audit terbuka</span><strong>{{ $h['finding_open'] }}</strong><small>{{ $h['finding_overdue'] }} lewat tenggat · {{ $h['audit_active'] }} audit berjalan</small></div>
    <div class="metric"><span>Penyelesaian temuan</span><strong>{{ $h['finding_closure'] !== null ? $h['finding_closure'].'%' : '—' }}</strong><small>Temuan diterbitkan yang telah ditutup</small></div>
</div>

<section class="panel detail-panel exec-narrative"><div class="section-kicker">POKOK-POKOK UNTUK PIMPINAN</div><h2>Ringkasan</h2>
<ul>@foreach($narrative as $line)<li>{{ $line }}</li>
@endforeach</ul></section>

<div class="exec-grid">
<section class="panel detail-panel"><div class="section-kicker">PROFIL RISIKO</div><h2>Sebaran level risiko inheren</h2>
@include('executive.donut', ['title' => 'Sebaran level risiko', 'center' => $h['risk_total'], 'centerLabel' => 'risiko',
    'items' => collect($byLevel)->mapWithKeys(fn ($v, $l) => [$l => [$v, $levelClass[$l]]])->all()])
</section>

<section class="panel detail-panel"><div class="section-kicker">PROFIL RISIKO</div><h2>Risiko per kategori</h2>
@php($maxCat = max(1, $byCategory->map->sum()->max() ?? 1))
@if($byCategory->isEmpty())<p class="muted">Belum ada risiko.</p>
@else
<svg class="chart" viewBox="0 0 400 {{ $byCategory->count() * 30 + 10 }}" role="img" aria-label="Jumlah risiko per kategori menurut level">
@foreach($byCategory as $category => $levels)
@php($y = $loop->index * 30 + 6)
@php($x = 140)
<text x="0" y="{{ $y + 14 }}" class="chart-label">{{ \Illuminate\Support\Str::limit($category, 22) }}</text>
@foreach($levels as $level => $count)
@if($count)
@php($w = $count / $maxCat * 230)
<rect x="{{ $x }}" y="{{ $y }}" width="{{ round($w, 1) }}" height="20" class="{{ $levelClass[$level] }}"><title>{{ $category }} · {{ $level }}: {{ $count }}</title></rect>
@php($x += $w)
@endif
@endforeach
<text x="{{ $x + 6 }}" y="{{ $y + 14 }}" class="chart-value">{{ $levels->sum() }}</text>
@endforeach
</svg>
<ul class="legend inline">@foreach($levelClass as $level => $class)<li><i class="swatch {{ $class }}"></i>{{ $level }}</li>
@endforeach</ul>
@endif
</section>
</div>

<section class="panel"><div class="panel-heading"><h2>Risiko utama</h2><a href="{{ workspace_route('risks.index') }}">Register risiko →</a></div>
<div class="table-wrap"><table><thead><tr><th>Risiko</th><th>Kategori</th><th>Skor inheren</th><th>Respons</th><th>Status</th></tr></thead><tbody>
@forelse($topRisks as $risk)
<tr><td><a href="{{ workspace_route('risks.show', $risk) }}"><strong>{{ $risk->code }}</strong><span class="table-title">{{ $risk->title }}</span></a></td><td>{{ $risk->category }}</td>
<td><div class="scorebar"><svg viewBox="0 0 100 8" aria-hidden="true"><rect x="0" y="0" width="100" height="8" rx="4" class="bar-track"></rect><rect x="0" y="0" width="{{ $risk->score * 4 }}" height="8" rx="4" class="{{ $levelClass[$risk->level] }}"></rect></svg><span>{{ $risk->score }} · {{ $risk->level }}</span></div></td>
<td>{{ config('grc.responses')[$risk->response] }}</td><td><span class="badge {{ $risk->status }}">{{ config('grc.statuses')[$risk->status] }}</span></td></tr>
@empty<tr><td colspan="5" class="empty">Belum ada risiko.</td></tr>
@endforelse
</tbody></table></div></section>

<div class="exec-grid">
<section class="panel detail-panel"><div class="section-kicker">KEPATUHAN</div><h2>Tren pemenuhan kewajiban 6 bulan</h2>
@php($maxTrend = max(1, $trend->max('total')))
<svg class="chart" viewBox="0 0 400 210" role="img" aria-label="Jumlah kewajiban jatuh tempo dan yang dipenuhi per bulan">
<line x1="30" y1="170" x2="395" y2="170" class="axis-line"></line>
@foreach($trend as $m)
@php($cx = 40 + $loop->index * 60)
@php($ht = $m['total'] / $maxTrend * 130)
@php($hf = $m['fulfilled'] / $maxTrend * 130)
<rect x="{{ $cx }}" y="{{ 170 - $ht }}" width="20" height="{{ round($ht, 1) }}" class="bar-due"><title>{{ $m['label'] }}: {{ $m['total'] }} jatuh tempo</title></rect>
<rect x="{{ $cx + 22 }}" y="{{ 170 - $hf }}" width="20" height="{{ round($hf, 1) }}" class="st-fulfilled"><title>{{ $m['label'] }}: {{ $m['fulfilled'] }} dipenuhi</title></rect>
<text x="{{ $cx + 21 }}" y="188" text-anchor="middle" class="chart-label">{{ $m['label'] }}</text>
@if($m['rate'] !== null)<text x="{{ $cx + 21 }}" y="{{ 162 - $ht }}" text-anchor="middle" class="chart-value">{{ $m['rate'] }}%</text>
@endif
@endforeach
</svg>
<ul class="legend inline"><li><i class="swatch bar-due"></i>Jatuh tempo</li><li><i class="swatch st-fulfilled"></i>Dipenuhi (terverifikasi)</li></ul>
</section>

<section class="panel detail-panel"><div class="section-kicker">KEPATUHAN</div><h2>Status periode pemenuhan</h2>
@include('executive.donut', ['title' => 'Status periode pemenuhan', 'center' => $complianceStatus->sum(), 'centerLabel' => 'periode',
    'items' => $complianceStatus->mapWithKeys(fn ($v, $k) => [config('modules.compliance_statuses')[$k] => [$v, 'st-'.$k]])->all()])
</section>
</div>

@if($overdueObligations->isNotEmpty())
<section class="panel"><div class="panel-heading"><h2>Kewajiban lewat tenggat</h2><a href="{{ workspace_route('compliance.index', ['overdue' => 1]) }}">Kalender kepatuhan →</a></div>
<div class="table-wrap"><table><thead><tr><th>Kewajiban / periode</th><th>Tenggat</th><th>Status</th></tr></thead><tbody>
@foreach($overdueObligations as $item)<tr><td><a href="{{ workspace_route('compliance.show', $item->id) }}"><strong>{{ $item->obligation?->code }}</strong><span class="table-title">{{ $item->obligation?->title }} · {{ $item->period }}</span></a></td><td>{{ $item->due_date->format('d/m/Y') }}<br><small class="danger">{{ $item->due_date->diffInDays(today()) }} hari lewat</small></td><td>{{ config('modules.compliance_statuses')[$item->status] }}</td></tr>
@endforeach</tbody></table></div></section>
@endif

<div class="exec-grid">
<section class="panel detail-panel"><div class="section-kicker">AUDIT INTERNAL</div><h2>Tahapan penugasan audit</h2>
@php($maxAudit = max(1, $auditStatus->max()))
<div class="pipeline">@foreach($auditStatus as $key => $count)<div class="pipeline-step"><svg viewBox="0 0 40 100" aria-hidden="true"><rect x="8" y="{{ 100 - $count / $maxAudit * 90 }}" width="24" height="{{ $count / $maxAudit * 90 }}" rx="3" class="au-{{ $key }}"></rect></svg><strong>{{ $count }}</strong><small>{{ config('modules.audit_statuses')[$key] }}</small></div>
@endforeach</div>
</section>

<section class="panel detail-panel"><div class="section-kicker">AUDIT INTERNAL</div><h2>Temuan menurut tingkat risiko</h2>
@php($maxFind = max(1, $findingSeverity->map(fn ($s) => $s['open'] + $s['closed'])->max()))
<svg class="chart" viewBox="0 0 400 140" role="img" aria-label="Temuan audit terbuka dan ditutup menurut tingkat risiko">
@foreach($findingSeverity as $key => $s)
@php($y = $loop->index * 32 + 6)
@php($wo = $s['open'] / $maxFind * 230)
@php($wc = $s['closed'] / $maxFind * 230)
<text x="0" y="{{ $y + 14 }}" class="chart-label">{{ config('modules.criticalities')[$key] }}</text>
<rect x="90" y="{{ $y }}" width="{{ round($wo, 1) }}" height="20" class="fd-open"><title>{{ $s['open'] }} terbuka</title></rect>
<rect x="{{ 90 + $wo }}" y="{{ $y }}" width="{{ round($wc, 1) }}" height="20" class="fd-closed"><title>{{ $s['closed'] }} ditutup</title></rect>
<text x="{{ 96 + $wo + $wc }}" y="{{ $y + 14 }}" class="chart-value">{{ $s['open'] }} / {{ $s['open'] + $s['closed'] }}</text>
@endforeach
</svg>
<ul class="legend inline"><li><i class="swatch fd-open"></i>Terbuka</li><li><i class="swatch fd-closed"></i>Ditutup</li></ul>
</section>
</div>

@if($group->isNotEmpty())
<section class="panel"><div class="panel-heading"><h2>Perbandingan perusahaan dalam grup</h2></div>
<div class="table-wrap"><table><thead><tr><th>Perusahaan</th><th>Risiko tinggi & kritis</th><th>Kepatuhan tepat waktu</th><th>Kewajiban lewat tenggat</th><th>Temuan terbuka</th><th>Penyelesaian temuan</th></tr></thead><tbody>
@foreach($group as $row)<tr @class(['current-row' => $row['entity']->id === $context->entity->id])><td><strong>{{ $row['entity']->name }}</strong></td><td>{{ $row['risk_high'] }} / {{ $row['risk_total'] }}</td>
<td><div class="scorebar"><svg viewBox="0 0 100 8" aria-hidden="true"><rect width="100" height="8" rx="4" class="bar-track"></rect><rect width="{{ $row['compliance_rate'] ?? 0 }}" height="8" rx="4" class="st-fulfilled"></rect></svg><span>{{ $row['compliance_rate'] !== null ? $row['compliance_rate'].'%' : '—' }}</span></div></td>
<td>{{ $row['compliance_overdue'] }}</td><td>{{ $row['finding_open'] }}</td><td>{{ $row['finding_closure'] !== null ? $row['finding_closure'].'%' : '—' }}</td></tr>
@endforeach</tbody></table></div></section>
@endif

<p class="muted small">Metodologi {{ config('grc.methodology_version') }}: skor inheren = kemungkinan × dampak (1–25). Kepatuhan tepat waktu = periode yang sudah jatuh tempo dan diverifikasi dipenuhi paling lambat pada tanggal tenggat. Data dihitung saat halaman dibuka.</p>
<script src="{{ asset_v('js/print.js') }}"></script>
@endsection

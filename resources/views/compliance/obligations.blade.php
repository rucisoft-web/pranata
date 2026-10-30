@extends('layouts.app')
@section('title', 'Register kewajiban')
@section('content')
<div class="page-heading"><div><div class="eyebrow">REGULATORY COMPLIANCE</div><h1>Register kewajiban</h1><p class="muted">Sumber, keberlakuan, dan penanggung jawab per perusahaan.</p></div>@if($context->canManageCompliance())<a class="button" href="{{ route('obligations.create') }}">+ Kewajiban</a>
@endif</div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Kewajiban</th><th>Sumber / versi</th><th>PIC</th><th>Keberlakuan</th></tr></thead><tbody>@forelse($obligations as $item)<tr><td><a href="{{ workspace_route('obligations.show', $item) }}"><strong>{{ $item->code }}</strong><span class="table-title">{{ $item->title }}</span></a></td><td>{{ $item->source->reference }}<br><small>{{ $item->source->version }}</small></td><td>{{ $item->responsible->name }}</td><td>{{ config('modules.applicabilities')[$item->applicability] }}</td></tr>
@empty<tr><td colspan="4" class="empty">Belum ada kewajiban.</td></tr>
@endforelse</tbody></table></div><div class="pagination">{{ $obligations->links('components.pagination') }}</div></section>
<section class="panel detail-panel"><h2>Sumber kewajiban</h2><p class="muted small">Versi sumber yang telah dicatat dipertahankan. Perubahan sumber dicatat sebagai versi baru.</p><div class="table-wrap"><table><thead><tr><th>Referensi</th><th>Judul / penerbit</th><th>Versi / berlaku</th></tr></thead><tbody>@forelse($sources as $source)<tr><td>{{ $source->reference }}</td><td>{{ $source->title }}<br><small>{{ $source->issuer }}</small>@if($source->url)<br><a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">Sumber rujukan</a>
@endif</td><td>{{ $source->version }}<br>{{ $source->effective_date->format('d/m/Y') }}</td></tr>
@empty<tr><td colspan="3">Belum ada sumber.</td></tr>
@endforelse</tbody></table></div>
@if($context->canManageCompliance())<details><summary>+ Catat versi sumber</summary><form action="{{ route('sources.store') }}" method="post">@csrf<div class="form-grid">@foreach(['code' => 'Kode versi sumber', 'reference' => 'Nomor / referensi', 'title' => 'Judul sumber', 'issuer' => 'Penerbit', 'version' => 'Versi', 'url' => 'URL rujukan (opsional)'] as $field => $label)<div><label for="source_{{ $field }}">{{ $label }}</label><input id="source_{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" @required($field !== 'url') maxlength="{{ $field === 'url' ? 1000 : ($field === 'code' ? 40 : ($field === 'version' ? 80 : 255)) }}"></div>
@endforeach<div><label for="effective_date">Tanggal berlaku</label><input id="effective_date" name="effective_date" type="date" required></div></div><button class="button">Simpan versi sumber</button></form></details>
@endif
</section>
@endsection

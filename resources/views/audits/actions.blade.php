@extends('layouts.app')
@section('title', 'Tindak lanjut audit')
@section('content')
<div class="eyebrow">TINDAK LANJUT TEMUAN</div><h1>{{ $context->role === 'demo_viewer' ? 'Temuan simulasi' : 'Tindak lanjut saya' }}</h1><p class="muted">{{ $context->role === 'demo_viewer' ? 'Seluruh temuan contoh pada perusahaan yang dipilih.' : 'Temuan yang telah diterbitkan dan ditugaskan kepada akun ini.' }}</p>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Temuan</th><th>Signifikansi</th><th>Tenggat</th><th>Status</th></tr></thead><tbody>@forelse($findings as $finding)<tr><td><a href="{{ workspace_route('findings.show', $finding) }}">{{ $finding->title }}</a></td><td>{{ config('modules.criticalities')[$finding->severity] }}</td><td>{{ $finding->due_date->format('d/m/Y') }}</td><td>{{ config('modules.finding_statuses')[$finding->status] }}</td></tr>@empty<tr><td colspan="4" class="empty">Tidak ada temuan untuk ditindaklanjuti.</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $findings->links('components.pagination') }}</div></section>
@endsection

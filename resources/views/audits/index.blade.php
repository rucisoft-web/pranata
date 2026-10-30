@extends('layouts.app')
@section('title', 'Audit internal')
@section('content')
<div class="page-heading"><div><div class="eyebrow">INDEPENDENT ASSURANCE</div><h1>Audit internal</h1><p class="muted">Penugasan, pekerjaan lapangan, laporan, dan penutupan.</p></div>@if($context->canManageAudit())<a class="button" href="{{ route('audits.create') }}">+ Rencana audit</a>
@endif</div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Penugasan</th><th>Jadwal</th><th>Auditor / reviewer</th><th>Status</th></tr></thead><tbody>@forelse($audits as $item)<tr><td><a href="{{ workspace_route('audits.show', $item) }}"><strong>{{ $item->code }}</strong><span class="table-title">{{ $item->title }}</span></a></td><td>{{ $item->start_date->format('d/m/Y') }}<br>{{ $item->end_date->format('d/m/Y') }}</td><td>{{ $item->lead->name }}<br><small>{{ $item->reviewer->name }}</small></td><td>{{ config('modules.audit_statuses')[$item->status] }}</td></tr>
@empty<tr><td colspan="4" class="empty">Belum ada penugasan yang dapat diakses.</td></tr>
@endforelse</tbody></table></div><div class="pagination">{{ $audits->links('components.pagination') }}</div></section>
@endsection

@extends('layouts.app')
@section('title', 'Pemenuhan kepatuhan')
@section('content')
<div class="eyebrow">{{ $occurrence->obligation_snapshot['code'] }} · {{ $occurrence->period }}</div><h1>{{ $occurrence->obligation_snapshot['title'] }}</h1><p>{{ config('modules.compliance_statuses')[$occurrence->status] }} · Tenggat {{ $occurrence->due_date->format('d/m/Y') }}</p>
<div class="detail-grid"><section class="panel detail-panel"><h2>Snapshot kewajiban periode</h2><dl><dt>Sumber / klausul</dt><dd>{{ $occurrence->obligation_snapshot['source']['reference'] }} · {{ $occurrence->obligation_snapshot['source']['version'] }} · {{ $occurrence->obligation_snapshot['clause'] }}</dd><dt>Uraian</dt><dd>{{ $occurrence->obligation_snapshot['description'] }}</dd><dt>PIC</dt><dd>{{ $occurrence->responsible->name }}</dd><dt>Catatan pemenuhan</dt><dd>{{ $occurrence->submission_note ?? '—' }}</dd><dt>Catatan verifikasi</dt><dd>{{ $occurrence->review_note ?? '—' }}</dd><dt>Waktu pengajuan</dt><dd>{{ $occurrence->submitted_at?->format('d/m/Y H:i') ?? '—' }}</dd><dt>Waktu diverifikasi dipenuhi</dt><dd>{{ $occurrence->fulfilled_at?->format('d/m/Y H:i') ?? '—' }}@if($occurrence->fulfilled_at && $occurrence->fulfilled_at->toDateString() > $occurrence->due_date->toDateString()) · Setelah tenggat
@endif</dd></dl><hr>
@include('components.evidence', ['subjectType' => 'compliance', 'subjectId' => $occurrence->id, 'canUpload' => $context->canContributeCompliance() && $occurrence->responsible_id === auth()->id() && in_array($occurrence->status, ['open', 'returned'])])
</section><aside class="panel detail-panel"><h2>Tindakan</h2>
@if($context->canContributeCompliance() && $occurrence->responsible_id === auth()->id() && in_array($occurrence->status, ['open', 'returned']))<form method="post" action="{{ route('compliance.transition', [$occurrence, 'submit']) }}">@csrf<input type="hidden" name="revision" value="{{ $occurrence->revision }}"><label for="submit_note">Catatan pemenuhan</label><textarea id="submit_note" name="note" rows="5" minlength="10" maxlength="10000" required></textarea><button class="button full">Ajukan verifikasi</button></form>
@elseif($context->canManageCompliance() && $occurrence->responsible_id !== auth()->id() && $occurrence->submitted_by !== auth()->id() && $occurrence->status === 'submitted')
@foreach(['verify' => 'Verifikasi dipenuhi', 'return' => 'Kembalikan'] as $operation => $label)<form method="post" action="{{ route('compliance.transition', [$occurrence, $operation]) }}">@csrf<input type="hidden" name="revision" value="{{ $occurrence->revision }}"><label for="note_{{ $operation }}">Dasar {{ strtolower($label) }}</label><textarea id="note_{{ $operation }}" name="note" rows="4" minlength="10" maxlength="10000" required></textarea><button class="button {{ $operation === 'return' ? 'secondary' : '' }} full">{{ $label }}</button></form>
@endforeach
@else<p class="muted">Tidak ada tindakan tersedia pada tahap ini.</p>
@endif
<hr><a href="{{ workspace_route('compliance.index') }}">← Kalender kepatuhan</a></aside></div>
@endsection

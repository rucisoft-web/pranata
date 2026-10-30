@extends('layouts.app')
@section('title', $audit->code)
@section('content')
<div class="page-heading"><div><div class="eyebrow">{{ $audit->code }} · {{ config('modules.audit_statuses')[$audit->status] }}</div><h1>{{ $audit->title }}</h1><p class="muted">Auditor: {{ $audit->lead->name }} · Reviewer: {{ $audit->reviewer->name }}</p></div><a href="{{ workspace_route('audits.index') }}">← Penugasan audit</a></div>
<section class="panel detail-panel"><dl><dt>Ruang lingkup</dt><dd>{{ $audit->scope }}</dd><dt>Tujuan</dt><dd>{{ $audit->objectives }}</dd><dt>Jadwal</dt><dd>{{ $audit->start_date->format('d/m/Y') }} — {{ $audit->end_date->format('d/m/Y') }}</dd>@if($audit->report_summary)<dt>Ringkasan laporan</dt><dd>{{ $audit->report_summary }}</dd>
@endif @if($audit->review_note)<dt>Catatan reviewer</dt><dd>{{ $audit->review_note }}</dd>
@endif</dl></section>
<section class="panel detail-panel"><h2>Kertas kerja audit</h2>
@forelse($audit->procedures as $procedure)<details class="workpaper"><summary>{{ $procedure->title }} · {{ config('modules.conclusions')[$procedure->conclusion] }}</summary><dl>@foreach(['criteria' => 'Kriteria', 'procedure' => 'Prosedur / metode / sampel', 'result' => 'Hasil pengujian'] as $field => $label)<dt>{{ $label }}</dt><dd>{{ $procedure->$field }}</dd>
@endforeach</dl><hr>@include('components.evidence', ['evidence' => $evidence->get($procedure->id, collect()), 'subjectType' => 'procedure', 'subjectId' => $procedure->id, 'canUpload' => $audit->status === 'fieldwork' && $access->lead($audit, auth()->user(), $context)])</details>
@empty<p class="muted">Belum ada kertas kerja.</p>
@endforelse
@if($audit->status === 'fieldwork' && $access->lead($audit, auth()->user(), $context))<details><summary>+ Catat kertas kerja</summary><form method="post" action="{{ route('audits.procedure', $audit) }}">@csrf<label for="procedure_title">Judul pengujian</label><input id="procedure_title" name="title" maxlength="255" required>@foreach(['criteria' => 'Kriteria', 'procedure' => 'Prosedur, populasi dan sampel', 'result' => 'Hasil dan pengecualian'] as $field => $label)<label for="procedure_{{ $field }}">{{ $label }}</label><textarea id="procedure_{{ $field }}" name="{{ $field }}" rows="3" maxlength="10000" required></textarea>
@endforeach<label for="conclusion">Kesimpulan</label><select id="conclusion" name="conclusion">@foreach(config('modules.conclusions') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>
@endforeach</select><button class="button">Simpan kertas kerja</button></form></details>
@endif
</section>
<section class="panel detail-panel"><h2>Temuan audit</h2><div class="table-wrap"><table><thead><tr><th>Temuan</th><th>Signifikansi</th><th>PIC / tenggat</th><th>Status</th></tr></thead><tbody>@forelse($audit->findings as $finding)<tr><td><a href="{{ workspace_route('findings.show', $finding) }}">{{ $finding->title }}</a></td><td>{{ config('modules.criticalities')[$finding->severity] }}</td><td>{{ $finding->responsible->name }}<br>{{ $finding->due_date->format('d/m/Y') }}</td><td>{{ config('modules.finding_statuses')[$finding->status] }}</td></tr>
@empty<tr><td colspan="4">Belum ada temuan.</td></tr>
@endforelse</tbody></table></div>
@if($audit->status === 'fieldwork' && $access->lead($audit, auth()->user(), $context))<details><summary>+ Catat draf temuan</summary><form method="post" action="{{ route('audits.finding', $audit) }}">@csrf<label for="finding_title">Judul temuan</label><input id="finding_title" name="title" maxlength="255" required>
@foreach(['criteria' => 'Kriteria', 'condition' => 'Kondisi yang ditemukan', 'cause' => 'Penyebab', 'impact' => 'Dampak', 'recommendation' => 'Rekomendasi'] as $field => $label)<label for="finding_{{ $field }}">{{ $label }}</label><textarea id="finding_{{ $field }}" name="{{ $field }}" rows="3" maxlength="10000" required></textarea>
@endforeach
<div class="form-grid"><div><label for="severity">Signifikansi</label><select id="severity" name="severity">@foreach(config('modules.criticalities') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>
@endforeach</select></div><div><label for="responsible_id">PIC tindak lanjut</label><select id="responsible_id" name="responsible_id" required><option value="">Pilih PIC independen dari tim audit</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>
@endforeach</select></div><div><label for="due_date">Tenggat</label><input id="due_date" type="date" name="due_date" required></div></div><button class="button">Simpan temuan</button></form></details>
@endif
</section>
@php($operations = [])
@if($access->lead($audit, auth()->user(), $context))
    @if($audit->status === 'draft')@php($operations = ['start' => 'Mulai pekerjaan lapangan'])
@endif
    @if($audit->status === 'fieldwork')@php($operations = ['submit' => 'Ajukan laporan untuk tinjauan'])
@endif
@endif
@if($access->reviewer($audit, auth()->user(), $context))
    @if($audit->status === 'review')@php($operations = ['finalize' => 'Finalisasi laporan dan terbitkan temuan', 'return' => 'Kembalikan ke auditor'])
@endif
    @if($audit->status === 'finalized')@php($operations = ['close' => 'Tutup penugasan audit'])
@endif
@endif
@if(count($operations))<section class="panel detail-panel"><h2>Persetujuan dan status</h2>@foreach($operations as $operation => $label)<form method="post" action="{{ route('audits.transition', [$audit, $operation]) }}">@csrf<input type="hidden" name="revision" value="{{ $audit->revision }}">@if($operation !== 'start')<label for="audit_note_{{ $operation }}">{{ $operation === 'submit' ? 'Ringkasan laporan audit' : 'Dasar keputusan / verifikasi' }}</label><textarea id="audit_note_{{ $operation }}" name="note" rows="4" minlength="10" maxlength="20000" required></textarea>
@endif<button class="button">{{ $label }}</button></form>
@endforeach</section>
@endif
@endsection

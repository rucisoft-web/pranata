@extends('layouts.app')
@section('title', $risk->code)
@section('content')
<div class="page-heading"><div><div class="eyebrow">{{ $risk->code }} · {{ $risk->category }}</div><h1>{{ $risk->title }}</h1><p><span class="badge {{ $risk->status }}">{{ config('grc.statuses')[$risk->status] }}</span> <span class="badge neutral">Inheren {{ $risk->score }} · {{ $risk->level }}</span></p></div><a href="{{ workspace_route('risks.index') }}">← Register risiko</a></div>
<div class="detail-grid"><section class="panel detail-panel">
<h2>Konteks dan penilaian</h2>
<dl>
@foreach(['objective' => 'Sasaran', 'process' => 'Proses bisnis', 'cause' => 'Penyebab', 'event' => 'Peristiwa', 'impact_description' => 'Dampak', 'assessment_reason' => 'Dasar penilaian', 'treatment_plan' => 'Rencana respons'] as $field => $label)<dt>{{ $label }}</dt><dd>{{ $risk->$field }}</dd>@endforeach
<dt>Respons</dt><dd>{{ config('grc.responses')[$risk->response] }}</dd>
<dt>Kemungkinan × dampak</dt><dd>{{ $risk->likelihood }} × {{ $risk->impact }} = {{ $risk->score }}</dd>
<dt>Metodologi</dt><dd>{{ $risk->methodology_version }}</dd>
<dt>Review berikutnya</dt><dd>{{ $risk->review_date->format('d/m/Y') }}</dd>
@if($risk->review_note)<dt>Catatan peninjau</dt><dd>{{ $risk->review_note }}</dd>@endif
</dl>
</section><aside>
<section class="panel detail-panel"><h2>Tindakan</h2>
@if($risk->status === 'draft' && $context->canWrite() && $risk->owner_id === auth()->id())
<a class="button secondary full" href="{{ route('risks.edit', $risk) }}">Ubah draf</a>
<form method="post" action="{{ route('risks.transition', [$risk, 'submit']) }}">@csrf<input type="hidden" name="revision" value="{{ $risk->revision }}"><button class="button full" type="submit">Ajukan tinjauan</button></form>
@elseif($risk->status === 'submitted' && $context->canReview() && $risk->owner_id !== auth()->id())
<form method="post" action="{{ route('risks.transition', [$risk, 'approve']) }}">@csrf<input type="hidden" name="revision" value="{{ $risk->revision }}"><label for="approval_note">Dasar persetujuan</label><textarea id="approval_note" name="review_note" maxlength="3000" required rows="3"></textarea><button class="button full">Setujui catatan risiko</button></form>
<hr><form method="post" action="{{ route('risks.transition', [$risk, 'return']) }}">@csrf<input type="hidden" name="revision" value="{{ $risk->revision }}"><label for="return_note">Alasan pengembalian</label><textarea id="return_note" name="review_note" maxlength="3000" required rows="3"></textarea><button class="button secondary full">Kembalikan untuk revisi</button></form>
@else<p class="muted">Tidak ada tindakan tersedia untuk akun ini pada tahap sekarang.</p>@endif
</section>
<section class="panel detail-panel"><h2>Riwayat perubahan</h2><ol class="timeline">@foreach($events as $event)<li><strong>{{ $event->action }}</strong><small>{{ $event->created_at->format('d/m/Y H:i') }} UTC · Pengguna #{{ $event->actor_id }}</small></li>@endforeach</ol></section>
</aside></div>
@endsection

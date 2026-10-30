@extends('layouts.app')
@section('title', $risk->exists ? 'Ubah draf risiko' : 'Catat risiko')
@section('content')
<div class="page-heading"><div><div class="eyebrow">IDENTIFIKASI & PENILAIAN</div><h1>{{ $risk->exists ? 'Ubah draf risiko' : 'Catat risiko' }}</h1><p class="muted">Penanggung jawab: {{ auth()->user()->name }} · {{ $context->entity->name }}</p></div></div>
<form class="panel form-panel" method="post" action="{{ $risk->exists ? route('risks.update', $risk) : route('risks.store') }}">
@csrf
@if($risk->exists)@method('PUT')<input type="hidden" name="revision" value="{{ $risk->revision }}">@endif
<h2>01 · Konteks risiko</h2>
<div class="form-grid">
@foreach(['code' => ['Kode risiko', 40], 'title' => ['Judul risiko', 200], 'objective' => ['Sasaran yang terpengaruh', 500], 'process' => ['Proses bisnis', 200]] as $field => [$label, $max])
<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $risk->$field) }}" maxlength="{{ $max }}" required></div>
@endforeach
<div><label for="category">Kategori</label><select id="category" name="category" required><option value="">Pilih kategori</option>@foreach(config('grc.categories') as $category)<option @selected(old('category', $risk->category) === $category)>{{ $category }}</option>@endforeach</select></div>
<div><label for="review_date">Tanggal review berikutnya</label><input id="review_date" type="date" name="review_date" value="{{ old('review_date', $risk->review_date?->format('Y-m-d')) }}" required></div>
</div>
<h2>02 · Penyebab, peristiwa, dan dampak</h2>
@foreach(['cause' => 'Penyebab risiko', 'event' => 'Peristiwa risiko', 'impact_description' => 'Dampak terhadap perusahaan'] as $field => $label)
<label for="{{ $field }}">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" maxlength="5000" rows="3" required>{{ old($field, $risk->$field) }}</textarea>
@endforeach
<h2>03 · Penilaian inheren</h2>
<p class="muted">PRN-INITIAL-1 · Skor = kemungkinan × dampak. Penilaian sebelum memperhitungkan efektivitas kontrol.</p>
<div class="form-grid">
@foreach(['likelihood' => ['Kemungkinan', [1 => 'Sangat jarang', 2 => 'Jarang', 3 => 'Mungkin', 4 => 'Sering', 5 => 'Sangat sering']], 'impact' => ['Dampak', [1 => 'Sangat kecil', 2 => 'Kecil', 3 => 'Sedang', 4 => 'Besar', 5 => 'Sangat besar']]] as $field => [$label, $options])
<div><label for="{{ $field }}">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}" required><option value="">Pilih nilai</option>@foreach($options as $value => $text)<option value="{{ $value }}" @selected((string) old($field, $risk->$field) === (string) $value)>{{ $value }} · {{ $text }}</option>@endforeach</select></div>
@endforeach
</div>
<label for="assessment_reason">Dasar dan alasan penilaian</label><textarea id="assessment_reason" name="assessment_reason" maxlength="5000" rows="3" required>{{ old('assessment_reason', $risk->assessment_reason) }}</textarea>
<h2>04 · Rencana respons</h2>
<label for="response">Respons yang diusulkan</label><select id="response" name="response" required>@foreach(config('grc.responses') as $key => $text)<option value="{{ $key }}" @selected(old('response', $risk->response) === $key)>{{ $text }}</option>@endforeach</select>
<label for="treatment_plan">Rencana penanganan / alasan usulan penerimaan</label><textarea id="treatment_plan" name="treatment_plan" maxlength="5000" rows="4" required>{{ old('treatment_plan', $risk->treatment_plan) }}</textarea>
<p class="muted small">Persetujuan catatan risiko belum merupakan otorisasi formal penerimaan risiko residual.</p>
<div class="form-actions"><button class="button" type="submit">Simpan draf</button><a class="button secondary" href="{{ $risk->exists ? workspace_route('risks.show', $risk) : workspace_route('risks.index') }}">Batal</a></div>
</form>
@endsection

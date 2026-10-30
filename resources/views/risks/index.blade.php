@extends('layouts.app')
@section('title', 'Register risiko')
@section('content')
<div class="page-heading"><div><div class="eyebrow">ENTERPRISE RISK MANAGEMENT</div><h1>Register risiko</h1><p class="muted">Identifikasi, nilai, dan tinjau risiko perusahaan.</p></div>@if($context->canWrite())<a class="button" href="{{ route('risks.create') }}">+ Catat risiko</a>@endif</div>
<section class="panel">
<form method="get" class="filters">
    <div><label for="q">Cari kode atau judul</label><input id="q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Cari risiko…"></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option>@foreach(config('grc.statuses') as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
    <button class="button secondary" type="submit">Terapkan</button><a href="{{ route('risks.index') }}">Reset</a>
</form>
@include('risks.table')
<div class="pagination">{{ $risks->links('components.pagination') }}</div>
</section>
@endsection

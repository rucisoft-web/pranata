@extends('layouts.app')
@section('title', 'Rencana audit')
@section('content')
<h1>Rencana audit internal</h1><p class="muted">Auditor penanggung jawab dan reviewer laporan harus berbeda.</p>
<form class="panel form-panel" method="post" action="{{ route('audits.store') }}">@csrf<div class="form-grid">
@foreach(['code' => 'Kode audit', 'title' => 'Judul penugasan'] as $field => $label)<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" required value="{{ old($field) }}" maxlength="{{ $field === 'code' ? 40 : 255 }}"></div>
@endforeach
@foreach(['start_date' => 'Mulai', 'end_date' => 'Selesai'] as $field => $label)<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="date" required value="{{ old($field) }}"></div>
@endforeach
@foreach(['lead_id' => ['Auditor penanggung jawab', $leads], 'reviewer_id' => ['Reviewer laporan', $reviewers]] as $field => [$label, $people])<div><label for="{{ $field }}">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}" required><option value="">Pilih petugas</option>@foreach($people as $person)<option value="{{ $person->id }}" @selected(old($field) == $person->id)>{{ $person->name }}</option>
@endforeach</select></div>
@endforeach</div>
@foreach(['scope' => 'Ruang lingkup', 'objectives' => 'Tujuan audit'] as $field => $label)<label for="{{ $field }}">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" rows="5" required maxlength="10000">{{ old($field) }}</textarea>
@endforeach
<div class="form-actions"><button class="button">Simpan rencana</button><a href="{{ workspace_route('audits.index') }}">Batal</a></div></form>
@endsection

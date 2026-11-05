@extends('layouts.app')
@section('title', 'Buka kuesioner')
@section('content')
<div class="eyebrow">KUESIONER · {{ strtoupper($definition['kind']) }}</div><h1>{{ $definition['title'] }}</h1><p class="muted">{{ $definition['description'] }}</p>
<div class="detail-grid"><section class="panel detail-panel"><form method="post" action="{{ route('questionnaires.store') }}">@csrf<input type="hidden" name="template" value="{{ $template }}">
<label for="title">Judul</label><input id="title" name="title" value="{{ old('title', $definition['title']) }}" required maxlength="255">
<div class="form-grid"><div><label for="year">Tahun buku</label><input id="year" type="number" name="year" value="{{ old('year', now()->year) }}" min="2015" max="2100" required></div><div><label for="due_date">Tenggat pengisian</label><input id="due_date" type="date" name="due_date" value="{{ old('due_date', today()->addWeeks(2)->format('Y-m-d')) }}" min="{{ today()->format('Y-m-d') }}" required></div></div>
<label for="instructions">Pengantar untuk responden</label><textarea id="instructions" name="instructions" rows="3" maxlength="5000">{{ old('instructions') }}</textarea>
<fieldset class="q-respondents"><legend>Responden @error('respondents')<span class="danger">{{ $message }}</span>@enderror</legend>
@foreach($members as $member)<label class="check"><input type="checkbox" name="respondents[]" value="{{ $member->id }}" @checked(in_array($member->id, old('respondents', [])) || (! old('respondents') && ($template === 'coc' || $member->role_here === 'board_member')))> {{ $member->name }} <small class="muted">{{ config('grc.roles')[$member->role_here] ?? $member->role_here }}</small></label>@endforeach</fieldset>
<button class="button">Buka kuesioner</button></form></section>
<aside class="panel detail-panel"><h2>Isi kuesioner</h2>@foreach($definition['sections'] as $section => $questions)<h3 class="small">{{ $section }}</h3><ol class="small q-preview">@foreach($questions as $q)<li>{{ $q[2] }} <small class="muted">{{ ['scale' => 'skala 1–5', 'yesno' => 'ya/tidak', 'text' => 'isian'][$q[1]] }}</small></li>@endforeach</ol>@endforeach
<p class="small muted">{{ $definition['anonymous'] ? 'Anonim: identitas tidak disimpan bersama jawaban, dan hasil baru tampil setelah minimal '.config('questionnaires.min_anonymous_responses').' tanggapan.' : 'Bernama: jawaban pengecualian ditampilkan beserta nama responden untuk ditindaklanjuti.' }}</p></aside></div>
@endsection

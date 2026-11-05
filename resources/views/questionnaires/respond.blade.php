@extends('layouts.app')
@section('title', $campaign->title)
@section('content')
<div class="eyebrow">KUESIONER {{ $campaign->year }} · TENGGAT {{ $campaign->due_date->format('d/m/Y') }}</div><h1>{{ $campaign->title }}</h1>
<p class="muted">{{ $campaign->instructions ?: $campaign->definition()['description'] }}</p>
<div class="{{ $campaign->anonymous ? 'demo-notice' : 'gv-draft' }}"><strong>{{ $campaign->anonymous ? 'ANONIM' : 'BERNAMA' }}</strong><span>{{ $campaign->anonymous ? 'Jawaban Anda disimpan tanpa nama dan tanpa waktu pengisian. Pengelola hanya melihat bahwa Anda sudah mengisi.' : 'Jawaban tercatat atas nama Anda. Jawaban yang menjadi pengecualian wajib disertai penjelasan.' }}</span></div>
<form method="post" action="{{ route('questionnaires.submit', $campaign) }}" class="q-form">@csrf
@foreach($campaign->definition()['sections'] as $section => $questions)<section class="panel detail-panel"><h2>{{ $section }}</h2>
@foreach($questions as $q)@php([$key, $type, $text] = $q)@php($flag = $q[3] ?? null)<fieldset class="q-question"><legend>{{ $text }}</legend>
@if($type === 'scale')<div class="q-scale">@foreach(config('questionnaires.scale') as $value => $label)<label><input type="radio" name="answers[{{ $key }}][value]" value="{{ $value }}" @checked(old("answers.$key.value") == $value) required><span>{{ $value }}</span><small>{{ $label }}</small></label>@endforeach</div>
@elseif($type === 'yesno')<div class="q-scale q-yesno">@foreach(['yes' => 'Ya', 'no' => 'Tidak'] as $value => $label)<label><input type="radio" name="answers[{{ $key }}][value]" value="{{ $value }}" @checked(old("answers.$key.value") === $value) required><span>{{ $label }}</span></label>@endforeach</div>
@if($flag)<label for="note_{{ $key }}" class="small">Penjelasan (wajib bila menjawab "{{ $flag === 'yes' ? 'Ya' : 'Tidak' }}")</label><textarea id="note_{{ $key }}" name="answers[{{ $key }}][note]" rows="2" maxlength="5000">{{ old("answers.$key.note") }}</textarea>@endif
@else<textarea name="answers[{{ $key }}][value]" rows="3" maxlength="5000" aria-label="{{ $text }}">{{ old("answers.$key.value") }}</textarea>@endif
@foreach(["answers.$key.value", "answers.$key.note"] as $field)@error($field)<p class="danger small">{{ $message }}</p>@enderror @endforeach
</fieldset>@endforeach</section>@endforeach
<section class="panel detail-panel"><p class="small muted">Jawaban tidak dapat diubah setelah dikirim.</p><button class="button">Kirim jawaban</button> <a href="{{ route('questionnaires.index') }}">Batal</a></section></form>
@endsection

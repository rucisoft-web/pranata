<table class="gv-disclosure"><thead><tr><th>No.</th><th>Rekomendasi</th><th>Status</th><th>Penjelasan</th></tr></thead><tbody>
@php($lastPrinciple = null)
@foreach($items as $item)
@if($item->principle() !== $lastPrinciple)@php($lastPrinciple = $item->principle())
@if($loop->first || config('governance.principles')[$lastPrinciple][0] !== $items[$loop->index - 1]->aspect())<tr class="gv-aspect"><td colspan="4">Aspek {{ $item->aspect() }}: {{ config('governance.aspects')[$item->aspect()] }}</td></tr>@endif
<tr class="gv-group"><td colspan="4">Prinsip {{ $lastPrinciple }}: {{ config('governance.principles')[$lastPrinciple][1] }}</td></tr>@endif
<tr><td>{{ $item->code }}</td><td>{{ $item->text() }}</td><td>{{ config('governance.disclosure_labels')[$item->status] }}</td><td>
@if($item->needsExplanation())
{{ $item->explanation ?: 'Penjelasan belum diisi.' }}@if($item->alternative_practice)<br><br>Praktik alternatif: {{ $item->alternative_practice }}@endif @if($item->target_date)<br><br>Target penerapan: {{ $item->target_date->translatedFormat('F Y') }}.@endif
@elseif($item->status === 'applied')
{{ $item->implementation ?: 'Uraian penerapan belum diisi.' }}@if($item->evidence_note)<br><br>Rujukan: {{ $item->evidence_note }}@endif
@else
Belum dinilai.
@endif
</td></tr>
@endforeach
</tbody></table>

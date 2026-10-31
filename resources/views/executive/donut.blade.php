{{-- $items: [label => [value, class]] --}}
@php($total = max(1, collect($items)->sum(fn ($i) => $i[0])))
@php($offset = 25)
<div class="donut-wrap">
<svg viewBox="0 0 42 42" class="donut" role="img" aria-label="{{ $title }}: @foreach($items as $label => [$value]){{ $label }} {{ $value }}{{ $loop->last ? '' : ', ' }}@endforeach">
<circle cx="21" cy="21" r="15.915" fill="none" class="donut-track" stroke-width="6"></circle>
@foreach($items as $label => [$value, $class])
@if($value > 0)
@php($pct = $value / $total * 100)
<circle cx="21" cy="21" r="15.915" fill="none" class="{{ $class }}" stroke-width="6" stroke-dasharray="{{ round($pct, 3) }} {{ round(100 - $pct, 3) }}" stroke-dashoffset="{{ round($offset, 3) }}"><title>{{ $label }}: {{ $value }}</title></circle>
@php($offset -= $pct)
@endif
@endforeach
<text x="21" y="20.5" text-anchor="middle" class="donut-num">{{ $center }}</text>
<text x="21" y="26" text-anchor="middle" class="donut-sub">{{ $centerLabel }}</text>
</svg>
<ul class="legend">@foreach($items as $label => [$value, $class])<li><i class="swatch {{ $class }}"></i>{{ $label }}<strong>{{ $value }}</strong></li>
@endforeach</ul>
</div>

<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title') — PRANATA</title><link rel="stylesheet" href="{{ asset_v('css/pranata.css') }}"></head>
<body class="login-page">
<main class="login-card">
    <div class="eyebrow">PRANATA · {{ $code }}</div>
    <h1>@yield('title')</h1>
    @php($detail = isset($exception) ? trim((string) $exception->getMessage()) : '')
    @php($signedIn = rescue(fn () => auth()->check(), false, false))
    @php($back = rescue(fn () => url()->previous(), url('/'), false))
    <p>{{ $detail !== '' && in_array($code, [403, 409, 422, 503], true) ? $detail : $message }}</p>
    @if($hint ?? null)<p class="muted small">{{ $hint }}</p>
@endif
    <hr>
    <a class="button full" href="{{ $back !== url()->current() ? $back : url('/') }}">← Kembali</a>
    <a class="button secondary full" href="{{ url($signedIn ? '/dashboard' : '/') }}">{{ $signedIn ? 'Ke dashboard' : 'Ke halaman depan' }}</a>
</main>
</body>
</html>

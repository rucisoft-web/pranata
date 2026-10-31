<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title') — PRANATA</title><link rel="stylesheet" href="{{ asset('css/pranata.css') }}"></head>
<body class="login-page">
<main class="login-card">
    <div class="eyebrow">PRANATA · {{ $code }}</div>
    <h1>@yield('title')</h1>
    @php($detail = isset($exception) ? trim((string) $exception->getMessage()) : '')
    <p>{{ $detail !== '' && in_array($code, [403, 409, 422, 503], true) ? $detail : $message }}</p>
    @if($hint ?? null)<p class="muted small">{{ $hint }}</p>
@endif
    <hr>
    <a class="button full" href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">← Kembali</a>
    <a class="button secondary full" href="{{ auth()->check() ? route('dashboard') : route('home') }}">{{ auth()->check() ? 'Ke dashboard' : 'Ke halaman depan' }}</a>
</main>
</body>
</html>

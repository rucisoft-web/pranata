<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Masuk — PRANATA</title><link rel="stylesheet" href="{{ asset('css/pranata.css') }}"></head>
<body class="login-page">
<main class="login-card">
    <div class="eyebrow">RUCISOFT · CORPORATE GOVERNANCE</div>
    <h1>PRANATA<span class="gold">.</span></h1>
    <p class="login-tagline">Tata Kelola Terarah.<br>Risiko Terkendali.</p>
    <hr>
    <h2>Masuk ke ruang kerja</h2>
    <p class="muted">Gunakan akun yang telah diberikan akses perusahaan.</p>
    @if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>
@endif
    <form method="post" action="{{ route('login') }}">
        @csrf
        <label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" maxlength="254" autofocus>
        <label for="password">Kata sandi</label><input id="password" type="password" name="password" required autocomplete="current-password" maxlength="1024">
        <button class="button full" type="submit">Masuk ke PRANATA</button>
    </form>
    @if(config('demo.enabled'))<a class="button secondary full" href="{{ route('demo.dashboard') }}">Masuk sebagai Tamu · Lihat Data Contoh</a>
@endif
    <p class="muted small">Hubungi administrator perusahaan untuk memperoleh akses.</p>
</main>
</body></html>

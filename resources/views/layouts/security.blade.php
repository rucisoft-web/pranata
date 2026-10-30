<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Keamanan — PRANATA</title><link rel="stylesheet" href="{{ asset('css/pranata.css') }}"></head>
<body class="login-page"><main class="login-card security-card"><div class="eyebrow">PRANATA · KEAMANAN AKUN</div>
@if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>
@endif
@yield('content')
<hr><form action="{{ route('logout') }}" method="post">@csrf<button class="button secondary">Keluar</button></form>
</main></body></html>

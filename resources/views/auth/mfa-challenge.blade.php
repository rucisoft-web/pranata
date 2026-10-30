@extends('layouts.security')
@section('content')
<h1>Verifikasi MFA</h1><p>Masukkan kode dari aplikasi authenticator untuk membuka ruang kerja.</p>
<form method="post" action="{{ route('mfa.verify') }}">@csrf<label for="code">Kode 6 digit</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus><button class="button full">Verifikasi</button></form>
<hr><details><summary>Gunakan kode pemulihan</summary><form method="post" action="{{ route('mfa.verify') }}">@csrf<label for="recovery_code">Kode pemulihan sekali pakai</label><input id="recovery_code" name="recovery_code" maxlength="40" autocomplete="off" required><button class="button secondary full">Gunakan kode pemulihan</button></form></details>
<p class="small muted">Kode authenticator yang sudah dipakai tidak dapat digunakan ulang. Tunggu kode berikutnya bila perlu.</p>
@endsection

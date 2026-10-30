@extends('layouts.security')
@section('content')
<h1>Keamanan akun</h1><div class="notice">MFA aktif · {{ $remaining }} kode pemulihan tersisa.</div><p>{{ auth()->user()->email }}</p>
<h2>Ganti kode pemulihan</h2><p class="small muted">Kode lama akan dibatalkan. Sesi perangkat lain harus memverifikasi MFA kembali.</p>
<form method="post" action="{{ route('mfa.rotate') }}">@csrf<label for="current_password">Kata sandi saat ini</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required><label for="code">Kode authenticator baru yang belum digunakan</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required><button class="button full">Ganti kode pemulihan</button></form>
<p class="small muted">Jika perangkat dan kode pemulihan hilang, hubungi operator untuk proses verifikasi identitas dan reset MFA.</p><a href="{{ workspace_route('dashboard') }}">← Kembali ke dashboard</a>
@endsection

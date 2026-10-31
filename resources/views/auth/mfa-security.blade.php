@extends('layouts.security')
@section('content')
<h1>Keamanan akun</h1><p>{{ auth()->user()->email }}</p>
@unless(auth()->user()->mfa_confirmed_at)<div class="notice neutral">MFA belum aktif. Aktifkan agar akun tetap aman walau kata sandi bocor.</div><a class="button full" href="{{ route('mfa.setup') }}">Aktifkan MFA</a>
@else<div class="notice">MFA aktif · {{ $remaining }} kode pemulihan tersisa.</div>
<h2>Ganti kode pemulihan</h2><p class="small muted">Kode lama akan dibatalkan. Sesi perangkat lain harus memverifikasi MFA kembali.</p>
<form method="post" action="{{ route('mfa.rotate') }}">@csrf<label for="current_password">Kata sandi saat ini</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required><label for="code">Kode authenticator baru yang belum digunakan</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required><button class="button full">Ganti kode pemulihan</button></form>
@endunless
<h2>Ganti kata sandi</h2>
<form method="post" action="{{ route('password.change') }}">@csrf<label for="pw_current">Kata sandi saat ini</label><input id="pw_current" type="password" name="current_password" autocomplete="current-password" required><label for="password">Kata sandi baru (min. 12 karakter, huruf besar, kecil, angka, simbol)</label><input id="password" type="password" name="password" autocomplete="new-password" required><label for="password_confirmation">Ulangi kata sandi baru</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required><button class="button full">Simpan kata sandi baru</button></form>
<p class="small muted">Jika perangkat dan kode pemulihan hilang, hubungi administrator perusahaan untuk verifikasi identitas dan reset MFA.</p><a href="{{ workspace_route('dashboard') }}">← Kembali ke dashboard</a>
@endsection

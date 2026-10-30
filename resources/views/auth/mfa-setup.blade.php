@extends('layouts.security')
@section('content')
<h1>Aktifkan MFA</h1><p>Pindai QR menggunakan aplikasi authenticator. QR dibuat di server PRANATA tanpa layanan pihak ketiga.</p>
<img class="mfa-qr" src="{{ $qr }}" width="240" height="240" alt="QR untuk mengatur aplikasi authenticator">
<details><summary>Masukkan kunci secara manual</summary><code class="secret">{{ $secret }}</code><p class="small muted">Jenis: TOTP, 6 digit, periode 30 detik. Jangan bagikan kunci ini.</p></details>
<form method="post" action="{{ route('mfa.enroll') }}">@csrf
<label for="current_password">Kata sandi akun saat ini</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
<label for="code">Kode 6 digit</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
<button class="button full">Verifikasi dan aktifkan</button></form><p class="small muted">Pengaturan berlaku 10 menit. Setelah aktivasi, simpan kode pemulihan yang ditampilkan.</p>
@endsection

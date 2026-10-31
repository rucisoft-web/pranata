@extends('layouts.app')
@section('title', 'Pengguna & akses')
@section('content')
<div class="page-heading"><div><div class="eyebrow">ADMINISTRASI</div><h1>Pengguna & akses</h1><p class="muted">Akun yang memiliki akses ke {{ $context->entity->name }}. Peran berlaku per perusahaan.</p></div></div>
<section class="panel">
<div class="table-wrap"><table>
<thead><tr><th>Nama / email</th><th>Peran di perusahaan ini</th><th>MFA</th><th>Tindakan</th></tr></thead>
<tbody>
@forelse($members as $member)
<tr>
<td><strong>{{ $member->name }}</strong>@if($member->id === auth()->id()) <span class="badge neutral">Anda</span>
@endif<span class="table-title">{{ $member->email }}</span>@if($member->entities->count() > 1)<small class="muted">Juga di: {{ $member->entities->where('id', '!=', $context->entity->id)->pluck('name')->join(', ') }}</small>
@endif</td>
<td><form method="post" action="{{ route('users.update', $member->id) }}" class="inline-form">@csrf @method('PUT')<select name="role" aria-label="Peran {{ $member->name }}">@foreach(config('grc.roles') as $key => $label)<option value="{{ $key }}" @selected($member->role_here === $key)>{{ $label }}</option>
@endforeach</select><button class="button secondary small">Simpan</button></form></td>
<td>@if($member->mfa_confirmed_at)<span class="badge approved">Aktif</span>
@else<span class="badge submitted">Belum</span>
@endif</td>
<td>@if($member->id !== auth()->id())
<details><summary>Kelola</summary>
<form method="post" action="{{ route('users.password', $member->id) }}">@csrf<label for="pw_{{ $member->id }}">Kata sandi sementara baru</label><input id="pw_{{ $member->id }}" name="password" type="text" autocomplete="off" required minlength="12"><button class="button secondary small">Reset kata sandi</button></form>
@if($member->mfa_confirmed_at)<form method="post" action="{{ route('users.mfa', $member->id) }}">@csrf<label for="mfa_{{ $member->id }}">Alasan reset MFA (mis. ponsel hilang)</label><input id="mfa_{{ $member->id }}" name="reason" required minlength="10" maxlength="1000"><button class="button secondary small">Reset MFA</button></form>
@endif
<form method="post" action="{{ route('users.destroy', $member->id) }}">@csrf @method('DELETE')<button class="button secondary small">Cabut akses ke perusahaan ini</button></form>
</details>
@else<span class="muted small">Kelola di menu <a href="{{ route('mfa.security') }}">Keamanan</a></span>
@endif</td>
</tr>
@empty
<tr><td colspan="4" class="empty">Belum ada pengguna.</td></tr>
@endforelse
</tbody></table></div>
</section>
<div class="detail-grid">
<section class="panel form-panel"><h2>Tambah pengguna</h2>
<p class="muted small">Bila email sudah terdaftar (misalnya di perusahaan lain), akun itu cukup diberi akses tambahan; kata sandinya tidak berubah.</p>
<form method="post" action="{{ route('users.store') }}">@csrf
<div class="form-grid">
<div><label for="name">Nama lengkap</label><input id="name" name="name" value="{{ old('name') }}" maxlength="255" required></div>
<div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" maxlength="254" required></div>
<div><label for="role">Peran</label><select id="role" name="role" required>@foreach(config('grc.roles') as $key => $label)<option value="{{ $key }}" @selected(old('role', 'risk_owner') === $key)>{{ $label }}</option>
@endforeach</select></div>
<div><label for="password">Kata sandi sementara (akun baru)</label><input id="password" name="password" type="text" autocomplete="off" minlength="12"></div>
</div>
<p class="muted small">Minimal 12 karakter dengan huruf besar, huruf kecil, angka, dan simbol. Pengguna menggantinya sendiri di menu Keamanan.</p>
<div class="form-actions"><button class="button">Tambah pengguna</button></div>
</form></section>
<aside><section class="panel detail-panel"><h2>Peran</h2><dl>
<dt>Administrator</dt><dd>Semua modul, mengelola pengguna.</dd>
<dt>Manajer / pemilik risiko</dt><dd>Mencatat risiko; manajer meninjau risiko milik orang lain.</dd>
<dt>Petugas / PIC kepatuhan</dt><dd>Petugas mengelola kewajiban dan memverifikasi; PIC mengunggah bukti pemenuhan.</dd>
<dt>Manajer audit / auditor</dt><dd>Manajer merencanakan dan mereview; auditor hanya melihat penugasannya.</dd>
<dt>Pembaca</dt><dd>Hanya melihat register risiko dan kepatuhan.</dd>
</dl></section>
<section class="panel detail-panel"><h2>Tambah perusahaan</h2><p class="muted small">Untuk anak perusahaan atau entitas grup. Anda otomatis menjadi administratornya.</p>
<form method="post" action="{{ route('entities.store') }}">@csrf
<label for="entity_code">Kode perusahaan</label><input id="entity_code" name="entity_code" value="{{ old('entity_code') }}" maxlength="30" required placeholder="mis. ANAK-01">
<label for="entity_name">Nama perusahaan</label><input id="entity_name" name="entity_name" value="{{ old('entity_name') }}" maxlength="255" required>
<label class="check"><input type="checkbox" name="is_bumn" value="1"> BUMN / grup BUMN</label>
<label class="check"><input type="checkbox" name="is_listed" value="1"> Perusahaan terbuka (tercatat di bursa)</label>
<button class="button full">Buat perusahaan</button></form></section></aside>
</div>
@endsection

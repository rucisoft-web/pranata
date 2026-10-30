@extends('layouts.security')
@section('content')
<h1>Kode pemulihan</h1>
@if(count($codes))<p>Simpan di tempat yang aman. Setiap kode hanya dapat digunakan satu kali. Daftar ini ditampilkan sekali.</p><ul class="recovery-codes">@foreach($codes as $code)<li><code>{{ $code }}</code></li>
@endforeach</ul>
@else<div class="notice neutral">Daftar kode sudah ditampilkan atau belum diterbitkan. Kode tersimpan tidak dapat dibaca kembali.</div>
@endif
<a class="button full" href="{{ workspace_route('dashboard') }}">Lanjut ke ruang kerja</a><a href="{{ route('mfa.security') }}">Pengaturan keamanan</a>
@endsection

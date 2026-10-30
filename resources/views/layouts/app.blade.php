<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — PRANATA</title>
    <link rel="stylesheet" href="{{ asset('css/pranata.css') }}">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">P</span> PRANATA</a>
        <p class="brand-sub">GOVERNANCE · RISK · COMPLIANCE</p>
        <div class="nav-label">RUANG KERJA</div>
        <nav aria-label="Menu utama">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard eksekutif</a>
            <a class="{{ request()->routeIs('risks.*') ? 'active' : '' }}" href="{{ route('risks.index') }}">Manajemen risiko</a>
        </nav>
        <div class="sidebar-note">Fondasi GRC korporasi<br><small>Versi awal · risiko inheren</small></div>
        <div class="sidebar-footer">by <strong>Rucisoft</strong><br><small>Tata Kelola Terarah.<br>Risiko Terkendali.</small></div>
    </aside>
    <div class="workspace">
        <header class="topbar">
            <form action="{{ route('entity.switch') }}" method="post" class="entity-switch">
                @csrf
                <label for="entity_id">Perusahaan</label>
                <select id="entity_id" name="entity_id" aria-label="Perusahaan aktif">
                    @foreach(auth()->user()->entities()->orderBy('name')->get() as $entity)
                        <option value="{{ $entity->id }}" @selected($entity->id === $context->entity->id)>{{ $entity->name }}</option>
                    @endforeach
                </select>
                <button class="button secondary small" type="submit">Buka</button>
            </form>
            <div class="user-menu"><span>{{ auth()->user()->name }}<small>{{ str_replace('_', ' ', $context->role) }}</small></span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="button secondary small">Keluar</button></form>
            </div>
        </header>
        <main>
            @if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="notice error" role="alert"><strong>Periksa kembali isian berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
        <footer>PRANATA · Governance, Risk & Compliance <span>Rucisoft</span></footer>
    </div>
</div>
</body>
</html>

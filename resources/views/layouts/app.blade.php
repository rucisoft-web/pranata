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
        <a class="brand" href="{{ workspace_route('dashboard') }}"><span class="brand-mark">P</span> PRANATA</a>
        <p class="brand-sub">GOVERNANCE · RISK · COMPLIANCE</p>
        <div class="nav-label">RUANG KERJA</div>
        <nav aria-label="Menu utama">
            <a class="{{ request()->routeIs('dashboard', 'demo.dashboard') ? 'active' : '' }}" href="{{ workspace_route('dashboard') }}">Dashboard eksekutif</a>
            @if($context->canReadExecutive())<a class="{{ request()->routeIs('summary', 'demo.summary') ? 'active' : '' }}" href="{{ workspace_route('summary') }}">Ringkasan eksekutif</a>
@endif
            <a class="{{ request()->routeIs('risks.*', 'demo.risks.*') ? 'active' : '' }}" href="{{ workspace_route('risks.index') }}">Manajemen risiko</a>
            <a class="{{ request()->routeIs('obligations.*', 'demo.obligations.*') ? 'active' : '' }}" href="{{ workspace_route('obligations.index') }}">Register kewajiban</a>
            <a class="{{ request()->routeIs('compliance.*', 'demo.compliance.*') ? 'active' : '' }}" href="{{ workspace_route('compliance.index') }}">Kalender kepatuhan</a>
            @if($context->canReadAudit())<a class="{{ request()->routeIs('audits.*', 'demo.audits.*') ? 'active' : '' }}" href="{{ workspace_route('audits.index') }}">Audit internal</a>
@endif
            <a class="{{ request()->routeIs('findings.*', 'demo.findings.*') ? 'active' : '' }}" href="{{ workspace_route('findings.index') }}">Tindak lanjut saya</a>
            @if($context->canManageUsers() && ! request()->attributes->get('demo_mode'))<a class="{{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">Pengguna & akses</a>
@endif
        </nav>
        <div class="sidebar-note">GRC korporasi<br><small>Risiko · kepatuhan · audit</small></div>
        <div class="sidebar-footer">by <strong>Rucisoft</strong><br><small>Tata Kelola Terarah.<br>Risiko Terkendali.</small></div>
    </aside>
    <div class="workspace">
        <header class="topbar">
            @if(request()->attributes->get('demo_mode'))
            <form action="{{ route(request()->routeIs('demo.summary') ? 'demo.summary' : 'demo.dashboard') }}" method="get" class="entity-switch"><label for="demo_entity">Perusahaan contoh</label><select id="demo_entity" name="entity">@foreach(\App\Models\Entity::where('is_demo', true)->orderBy('name')->get() as $company)<option value="{{ $company->id }}" @selected($company->id === $context->entity->id)>{{ $company->name }}</option>
@endforeach</select><button class="button secondary small">Buka</button></form><div class="user-menu"><span class="badge demo-badge">Tamu · Hanya baca</span><a href="{{ route('login') }}">Masuk akun</a></div>

@else
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
            <div class="user-menu"><span>{{ auth()->user()->name }}<small>{{ str_replace('_', ' ', $context->role) }} · <a href="{{ route('mfa.security') }}">Keamanan</a></small></span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="button secondary small">Keluar</button></form>
            </div>

@endif
        </header>
        <main>
            @if($context->entity->is_demo)<div class="demo-notice"><strong>DATA CONTOH / SIMULASI</strong><span>Profil mengacu laporan tahunan 2025. Risiko, kewajiban, tenggat, temuan, dan bukti adalah skenario demonstrasi; bukan penilaian atau temuan aktual perusahaan.</span></div>
@endif
            @if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>
@endif
            @if($errors->any())<div class="notice error" role="alert"><strong>Periksa kembali isian berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul></div>
@endif
            @yield('content')
        </main>
        <footer>PRANATA · Governance, Risk & Compliance <span>Rucisoft</span></footer>
    </div>
</div>
</body>
</html>

<!DOCTYPE html>
@php
    use App\Models\CtpRegistration;
    $brandName = content('site','branding','brand_name','CTP-WireLakes');
    $brandLogo = content('site','branding','logo');
    $themeGreen = content('site','branding','theme_green','#066722');
    $themeLime = content('site','branding','theme_lime','#4cb614');
    $me = auth()->user();
    $isCtp = ($member ?? null) instanceof CtpRegistration;
    $code = isset($member) ? ($isCtp ? $member->ctp_code : $member->ef_code) : null;
    $initials = collect(explode(' ', trim($me->name)))->filter()->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');

    // Every item the member's own role is entitled to — this sidebar is the whole interface.
    $nav = [
        ['label' => 'Overview', 'icon' => 'grid', 'route' => 'portal.dashboard', 'active' => ['portal.dashboard']],
    ];
    if ($isCtp) {
        $nav[] = ['label' => 'My training', 'icon' => 'check', 'route' => 'portal.training', 'active' => ['portal.training']];
        $nav[] = ['label' => 'My mobilisation', 'icon' => 'users', 'route' => 'portal.mobilisation', 'active' => ['portal.mobilisation']];
        $nav[] = ['label' => 'My communities', 'icon' => 'globe', 'route' => 'portal.communities', 'active' => ['portal.communities'], 'count' => ($communities ?? collect())->count()];
    } else {
        $nav[] = ['label' => 'Training CTPs', 'icon' => 'award', 'route' => 'portal.trainer', 'active' => ['portal.trainer']];
    }
    $nav[] = ['label' => 'My projects', 'icon' => 'briefcase', 'route' => 'portal.projects', 'active' => ['portal.projects', 'portal.project'], 'count' => ($projects ?? collect())->count()];
    $nav[] = ['label' => 'My applications', 'icon' => 'mail', 'route' => 'portal.applications', 'active' => ['portal.applications'], 'count' => ($applications ?? collect())->count()];
@endphp
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Overview') — {{ $brandName }} Portal</title>
    <link rel="icon" href="{{ $brandLogo ?: asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>:root { --green: {{ $themeGreen }}; --lime: {{ $themeLime }}; }</style>
    <script>if (localStorage.getItem('ctp-theme') === 'dark') document.documentElement.classList.add('dark');</script>
</head>
<body class="member-body admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" id="memberSidebar">
        <a class="admin-brand" href="{{ route('portal.dashboard') }}">
            <img src="{{ $brandLogo ?: asset('logo.png') }}" alt="">
            <span>{{ $brandName }}<small>Member portal</small></span>
        </a>
        <nav class="admin-nav">
            <div class="nav-group">{{ $isCtp ? 'CTP trainee' : 'E-Fundi' }}</div>
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['active']) ? 'active' : '' }}">
                    @include('partials.icon', ['n' => $item['icon']]) {{ $item['label'] }}
                    @if (! empty($item['count']))<span class="nav-badge">{{ $item['count'] }}</span>@endif
                </a>
            @endforeach

            <div class="nav-group">Outside the portal</div>
            <a href="{{ route('home') }}" target="_blank">
                @include('partials.icon', ['n' => 'external']) View public site
            </a>
        </nav>
        <div class="side-foot">
            <span class="side-ver">{{ $me->name }}@if ($code) · {{ $code }}@endif</span>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar member-topbar">
            <button class="menu-toggle" type="button" aria-label="Menu" onclick="document.getElementById('memberSidebar').classList.toggle('open')">
                @include('partials.icon', ['n' => 'menu'])
            </button>
            <h1>@yield('title', 'Overview')</h1>
            <div class="topbar-right">
                @include('partials.theme-toggle')
                <span class="profile">
                    <span class="avatar">{{ $initials }}</span>
                    <span class="who"><b>{{ $me->name }}</b><small>{{ $isCtp ? 'CTP trainee' : 'E-Fundi' }}</small></span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-logout" type="submit" title="Sign out">
                        @include('partials.icon', ['n' => 'logout']) <span>Log out</span>
                    </button>
                </form>
            </div>
        </header>
        <main class="admin-content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>

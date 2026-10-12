<!DOCTYPE html>
@php
    $brandName = content('site','branding','brand_name','CTP-WireLakes');
    $brandLogo = content('site','branding','logo');
    $themeGreen = content('site','branding','theme_green','#066722');
    $themeLime = content('site','branding','theme_lime','#4cb614');
    $me = auth()->user();
    $initials = collect(explode(' ', trim($me->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Member portal') — {{ $brandName }}</title>
    <link rel="icon" href="{{ $brandLogo ?: asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>:root { --green: {{ $themeGreen }}; --lime: {{ $themeLime }}; }</style>
    <script>if (localStorage.getItem('ctp-theme') === 'dark') document.documentElement.classList.add('dark');</script>
</head>
<body class="member-body">
<header class="admin-topbar member-topbar">
    <a class="brand" href="{{ route('portal.dashboard') }}">
        <img src="{{ $brandLogo ?: asset('logo.png') }}" alt="">
        <span>{{ $brandName }}<small style="display:block;font-size:.7rem;font-weight:600;">Member portal</small></span>
    </a>
    <div class="topbar-right">
        @include('partials.theme-toggle')
        <span class="profile">
            <span class="avatar">{{ $initials }}</span>
            <span class="who"><b>{{ $me->name }}</b><small>{{ $me->role === 'fundi' ? 'E-Fundi' : 'CTP' }}</small></span>
        </span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn-logout" type="submit" title="Sign out">
                @include('partials.icon', ['n' => 'logout']) <span>Log out</span>
            </button>
        </form>
    </div>
</header>

<main class="container member-main">
    @include('partials.flash')
    @yield('content')
</main>
<script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>

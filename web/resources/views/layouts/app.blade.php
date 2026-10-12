<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CTP-WireLakes') — CTP-WireLakes</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('logo.png') }}" alt="CTP-WireLakes logo">
            <span>CTP-WireLakes</span>
        </a>
        <nav class="main-nav">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
            <a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'active' : '' }}">About</a>
            <a href="{{ route('what-we-do') }}" class="{{ request()->routeIs('what-we-do') ? 'active' : '' }}">What We Do</a>
            <a href="{{ route('partners') }}" class="{{ request()->routeIs('partners.*') ? 'active' : '' }}">Partners</a>
            <a href="{{ route('donate') }}" class="{{ request()->routeIs('donate.*') ? 'active' : '' }}">Donate</a>
            <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact.*') || request()->routeIs('faq') ? 'active' : '' }}">Contact</a>
        </nav>
        <div class="header-cta">
            <a class="btn btn-outline btn-sm" href="{{ route('register.ctp') }}">Become a CTP</a>
            <a class="btn btn-lime btn-sm" href="{{ route('register.fundi') }}">Become a Fundi</a>
        </div>
    </div>
</header>

<main class="container">
    @include('partials.flash')
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container foot-grid">
        <div>
            <h4>CTP-WireLakes</h4>
            <p>Community Technology Promoters — E-Fundis, Training &amp; Innovation. From a community need, we create trained people, certified fundis, completed projects and lasting jobs.</p>
            <p>Kisumu, Kenya · <a href="mailto:info@ctp-wirelakes.ke">info@ctp-wirelakes.ke</a></p>
        </div>
        <div>
            <h4>Join the programme</h4>
            <p>
                <a href="{{ route('register.ctp') }}">Become a CTP</a><br>
                <a href="{{ route('register.fundi') }}">Become a Fundi</a><br>
                <a href="{{ route('register.hirer') }}">Post a Project (Hirer)</a><br>
                <a href="{{ route('donate') }}">Donate</a>
            </p>
        </div>
        <div>
            <h4>Info</h4>
            <p>
                <a href="{{ route('about') }}">About us</a><br>
                <a href="{{ route('what-we-do') }}">What we do</a><br>
                <a href="{{ route('faq') }}">FAQ</a><br>
                <a href="{{ route('login') }}">Staff login</a>
            </p>
        </div>
    </div>
    <div class="container fine">© {{ date('Y') }} CTP-WireLakes Programme · Designed by James Ombet &amp; Vincent Otieno, Kisumu, Kenya</div>
</footer>
</body>
</html>

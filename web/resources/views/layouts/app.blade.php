<!DOCTYPE html>
@php
    $brandLogo = content('site','branding','logo');
    $brandName = content('site','branding','brand_name','WireLakes Technologies');
    $themeGreen = content('site','branding','theme_green','#066722');
    $themeLime = content('site','branding','theme_lime','#4cb614');
    $siteEmail = content('site','contact','email','info@ctp-wirelakes.ke');
    $heroPhoto = content('site','backgrounds','hero_photo');
    $pagePhoto = content('site','backgrounds','page_photo');
    $photoDark = min(max((float) content('site','backgrounds','photo_darkness','0.66'), 0.3), 0.85);
    // A page that opens on its hero carries the header over that hero, so a message shown there has to clear it.
    $hasFlash = (bool) (session('status') || session('error'));
@endphp
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $brandName) — {{ $brandName }}</title>
    <link rel="icon" href="{{ $brandLogo ?: asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ css_href() }}">
    <style>:root { --green: {{ $themeGreen }}; --lime: {{ $themeLime }}; }
    @if ($heroPhoto)
        .hero { background-image: linear-gradient(rgba(3,61,16,{{ $photoDark }}), rgba(3,61,16,{{ $photoDark }})), url('{{ $heroPhoto }}'); background-size: cover; background-position: center; }
    @endif
    @if ($pagePhoto)
        body { background-image: linear-gradient(rgba(248,251,247,.94), rgba(248,251,247,.94)), url('{{ $pagePhoto }}'); background-size: cover; background-attachment: fixed; }
        html.dark body { background-image: linear-gradient(rgba(16,24,18,.94), rgba(16,24,18,.94)), url('{{ $pagePhoto }}'); }
    @endif
    </style>
    <script>if (localStorage.getItem('ctp-theme') === 'dark') document.documentElement.classList.add('dark');</script>
</head>
<body class="@yield('bodyClass') @if($hasFlash) has-flash @endif">
<header class="site-header">
    <div class="container header-bar">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ $brandLogo ?: asset('logo.png') }}" alt="{{ $brandName }} logo">
            <span>{{ $brandName }}</span>
        </a>
        <nav class="main-nav" id="siteNav">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
            <a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'active' : '' }}">About</a>
            <a href="{{ route('what-we-do') }}" class="{{ request()->routeIs('what-we-do') ? 'active' : '' }}">What We Do</a>
            <a href="{{ route('careers') }}" class="{{ request()->routeIs('careers.*') ? 'active' : '' }}">Careers</a>
            <a href="{{ route('partners') }}" class="{{ request()->routeIs('partners.*') ? 'active' : '' }}">Partners</a>
            <a href="{{ route('donate') }}" class="{{ request()->routeIs('donate.*') ? 'active' : '' }}">Donate</a>
            <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact.*') || request()->routeIs('faq') ? 'active' : '' }}">Contact</a>
            <div class="nav-cta join-menu">
                <button type="button" class="btn btn-primary btn-sm join-toggle" aria-expanded="false" onclick="toggleJoinMenu(this)">Join / Sign up @include('partials.icon', ['n' => 'chevron'])</button>
                <div class="join-drop">
                    <a class="btn btn-outline btn-sm" href="{{ route('register.ctp') }}">Become a student (CTP)</a>
                    <a class="btn btn-lime btn-sm" href="{{ route('register.fundi') }}">Become a Fundi</a>
                    <span class="join-sep"></span>
                    <a class="btn btn-ghost btn-sm nav-login" href="{{ route('login') }}">Log in</a>
                </div>
            </div>
        </nav>
        <div class="header-side">
            @include('partials.theme-toggle')
        </div>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="siteNav" onclick="toggleSiteMenu(this)">
            <span class="mi mi-open">@include('partials.icon', ['n' => 'menu'])</span>
            <span class="mi mi-close">@include('partials.icon', ['n' => 'close'])</span>
        </button>
    </div>
</header>
<script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/password.js') }}"></script>
<script>
function toggleSiteMenu(btn) {
    const nav = document.getElementById('siteNav');
    const open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
    btn.classList.toggle('is-open', open);
    syncHeader();
}
function toggleJoinMenu(btn) {
    const menu = btn.closest('.join-menu');
    const open = menu.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
}
document.addEventListener('click', function (e) {
    document.querySelectorAll('.join-menu.open').forEach(function (m) {
        if (!m.contains(e.target)) {
            m.classList.remove('open');
            m.querySelector('.join-toggle').setAttribute('aria-expanded', 'false');
        }
    });
});
(function () {
    const header = document.querySelector('.site-header');
    if (!header) return;
    const hasHero = document.body.classList.contains('has-hero');
    const flash = document.body.classList.contains('has-flash');
    window.syncHeader = function () {
        const y = window.scrollY;
        const navOpen = !!document.querySelector('.main-nav.open');
        const solid = !hasHero || y > 30 || navOpen || flash;
        header.classList.toggle('solid', solid);
        header.classList.toggle('hidden', y > 160 && window.direction === 'down' && !navOpen);
    };
    let last = window.scrollY;
    window.direction = 'up';
    window.addEventListener('scroll', function () {
        window.direction = window.scrollY > last ? 'down' : 'up';
        last = window.scrollY;
        syncHeader();
    }, { passive: true });
    syncHeader();
})();
</script>

<main class="container">
    @include('partials.flash')
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container foot-grid">
        <div>
            <h4>{{ $brandName }}</h4>
            <p>{{ content('site','footer','about_text','Community Technology Promoters — E-Fundis, Training & Innovation.') }}</p>
            <p>Kisumu, Kenya · <a href="mailto:{{ $siteEmail }}">{{ $siteEmail }}</a></p>
        </div>
        <div>
            <h4>Join the programme</h4>
            <p>
                <a href="{{ route('register.ctp') }}">Become a student (CTP)</a><br>
                <a href="{{ route('register.fundi') }}">Become a Fundi</a><br>
                <a href="{{ route('login') }}">Member &amp; fundi login</a><br>
                <a href="{{ route('donate') }}">Donate</a>
            </p>
        </div>
        <div>
            <h4>Info</h4>
            <p>
                <a href="{{ route('about') }}">About us</a><br>
                <a href="{{ route('what-we-do') }}">What we do</a><br>
                <a href="{{ route('careers') }}">Careers</a><br>
                <a href="{{ route('faq') }}">FAQ</a><br>
                <a href="{{ route('login') }}">Programme staff login</a>
            </p>
        </div>
    </div>
    <div class="container fine">{{ content('site','footer','fine_print','© '.date('Y').' WireLakes Technologies') }}</div>
</footer>
</body>
</html>

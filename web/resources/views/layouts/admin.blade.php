<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — CTP-WireLakes Admin</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script>if (localStorage.getItem('ctp-theme') === 'dark') document.documentElement.classList.add('dark');</script>
</head>
<body>
<header class="admin-header">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
        <a class="brand" href="{{ route('admin.dashboard') }}" style="color:#fff;">
            <img src="{{ asset('logo.png') }}" alt="" style="height:40px;width:40px;">
            <span>CTP-WireLakes · Staff</span>
        </a>
        <div style="display:flex;align-items:center;gap:14px;">
            <button class="theme-toggle" type="button" aria-label="Toggle dark mode" onclick="toggleTheme(this)">🌙</button>
            <span>{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
            <a href="{{ route('home') }}">View site</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
            </form>
        </div>
    </div>
</header>
<main class="container" style="padding-top:26px;">
    @include('partials.flash')
    @yield('content')
</main>
<script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>

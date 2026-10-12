<!DOCTYPE html>
@php
    $brandName = content('site','branding','brand_name','CTP-WireLakes');
    $brandLogo = content('site','branding','logo');
    $onMembership = request()->is('admin/content/membership');
    $navPending = \App\Models\CtpRegistration::where('status', 'pending_review')->count()
        + \App\Models\FundiRegistration::where('status', 'pending_review')->count()
        + \App\Models\HirerRegistration::where('status', 'pending_review')->count();
    $navPartners = \App\Models\Partner::where('is_listed', false)->count();
    $navApplications = \App\Models\ProjectApplication::where('status', 'pending_review')->count();
    $navMessages = \App\Models\ContactMessage::count();
    $me = auth()->user();
    $initials = collect(explode(' ', trim($me->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — {{ $brandName }} Staff</title>
    <link rel="icon" href="{{ $brandLogo ?: asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script>if (localStorage.getItem('ctp-theme') === 'dark') document.documentElement.classList.add('dark');</script>
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <img src="{{ $brandLogo ?: asset('logo.png') }}" alt="">
            <span>{{ $brandName }}<small>Staff portal</small></span>
        </a>
        <nav class="admin-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'grid']) Departments
            </a>

            <div class="nav-group">Module 1 · Public Site &amp; Membership</div>
            <a href="{{ route('admin.registrations.index', 'ctp') }}" class="{{ request()->routeIs('admin.registrations.*') ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'users']) Applicants
                @if ($navPending)<span class="nav-badge">{{ $navPending }}</span>@endif
            </a>
            <a href="{{ route('admin.content.index') }}" class="{{ request()->routeIs('admin.content.*') && ! $onMembership ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'layout']) Site content &amp; pages
            </a>
            <a href="{{ route('admin.content.edit', 'membership') }}" class="{{ $onMembership ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'sliders']) Membership settings
            </a>
            <a href="{{ route('admin.projects.index') }}" class="{{ request()->routeIs('admin.projects.*') ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'briefcase']) Projects
                @if ($navApplications)<span class="nav-badge">{{ $navApplications }}</span>@endif
            </a>
            <a href="{{ route('admin.hirers.index') }}" class="{{ request()->routeIs('admin.hirers.*') ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'building']) Hirers
            </a>
            <a href="{{ route('admin.partners.index') }}" class="{{ request()->routeIs('admin.partners.*') ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'link']) Partners
                @if ($navPartners)<span class="nav-badge">{{ $navPartners }}</span>@endif
            </a>
            <a href="{{ route('admin.donations.index') }}" class="{{ request()->routeIs('admin.donations.*') ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'heart']) Donations
            </a>
            <a href="{{ route('admin.messages.index') }}" class="{{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'mail']) Messages
                <span class="nav-badge gray">{{ $navMessages }}</span>
            </a>

            <div class="nav-group">Administration</div>
            <a href="{{ route('admin.staff.index') }}" class="{{ request()->routeIs('admin.staff.*') ? 'active' : '' }}">
                @include('partials.icon', ['n' => 'key']) Staff &amp; roles
            </a>
            <a href="{{ route('home') }}" target="_blank">
                @include('partials.icon', ['n' => 'globe']) View public site
            </a>
        </nav>
        <div class="side-foot">
            <span class="side-ver">Module 1 · Public Site &amp; Membership</span>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="menu-toggle" type="button" aria-label="Menu" onclick="document.getElementById('adminSidebar').classList.toggle('open')">
                @include('partials.icon', ['n' => 'menu'])
            </button>
            <h1>@yield('title', 'Admin')</h1>
            <div class="topbar-right">
                @include('partials.theme-toggle')
                <span class="profile">
                    <span class="avatar">{{ $initials }}</span>
                    <span class="who"><b>{{ $me->name }}</b><small>{{ $me->role === 'superadmin' ? 'Super admin' : 'Admin' }}</small></span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-logout" type="submit" title="Sign out of the staff portal">
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

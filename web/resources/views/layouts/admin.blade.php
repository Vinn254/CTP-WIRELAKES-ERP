<!DOCTYPE html>
@php
    use App\Support\Departments;
    $brandName = content('site','branding','brand_name','CTP-WireLakes');
    $brandLogo = content('site','branding','logo');
    $route = request()->route();
    $deptSlug = ($route ? $route->parameter('department') : null) ?: Departments::owner($route ? $route->getName() : null);
    $dept = $deptSlug ? Departments::find($deptSlug) : null;
    // A shared record shown as this department's copy: which screen it copies, and who really owns it.
    $owningRoute = Departments::hosted() ? (Departments::HOSTED[$route?->getName()][0] ?? null) : null;
    $ownerSlug = $owningRoute ? Departments::owner($owningRoute) : null;
    $ownerName = $ownerSlug ? (Departments::find($ownerSlug)['name'] ?? $ownerSlug) : null;
    $deptPage = request()->route('page');
    $isActive = function (array $item) use ($deptPage) {
        $target = $item['route'][0];
        if ($target === 'admin.content.index') {
            return request()->routeIs('admin.content.index') || (request()->routeIs('admin.content.edit') && $deptPage !== 'membership');
        }
        if ($target === 'admin.content.edit') {
            return request()->routeIs('admin.content.edit') && $deptPage === 'membership';
        }

        return request()->routeIs(preg_replace('/\.[^.]+$/', '.', $target).'*');
    };
    $me = auth()->user();
    $initials = collect(explode(' ', trim($me->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp
<html lang="en" data-idle-minutes="{{ (int) config('account.idle_minutes') }}">
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
        <a class="admin-brand" href="{{ route('admin.departments') }}">
            <img src="{{ $brandLogo ?: asset('logo.png') }}" alt="">
            <span>{{ $brandName }}<small>Staff portal</small></span>
        </a>
        <nav class="admin-nav">
            <a href="{{ route('admin.departments') }}" class="{{ $dept ? '' : 'active' }}">
                @include('partials.icon', ['n' => 'grid']) All departments
            </a>

            @if ($dept)
                <div class="nav-group">{{ $dept['tag'] }} · {{ $dept['name'] }}</div>
                <a href="{{ route('admin.department', $dept['slug']) }}" class="{{ request()->routeIs('admin.department') ? 'active' : '' }}">
                    @include('partials.icon', ['n' => 'chevron']) Overview
                </a>
                @foreach (Departments::nav($dept['slug']) as $item)
                    @php $badge = Departments::badge($item['badge'] ?? null); @endphp
                    <a href="{{ route(...$item['route']) }}" class="{{ $isActive($item) ? 'active' : '' }}">
                        @include('partials.icon', ['n' => $item['icon']]) {{ $item['label'] }}
                        @if ($badge)<span class="nav-badge">{{ $badge }}</span>@endif
                    </a>
                @endforeach
            @endif

            <div class="nav-group">Outside the portal</div>
            <a href="{{ route('home') }}" target="_blank">
                @include('partials.icon', ['n' => 'globe']) View public site
            </a>
        </nav>
        <div class="side-foot">
            <span class="side-ver">{{ $dept ? $dept['tag'].' · '.$dept['name'] : count(Departments::visibleFor($me)).' department'.(count(Departments::visibleFor($me)) === 1 ? '' : 's').' · 1 programme' }}</span>
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
            @if ($owningRoute)
                <div class="card callout" style="margin-bottom:16px;">
                    <b>{{ $dept['tag'] ?? '' }} · {{ $dept['name'] ?? '' }} copy</b> — this record belongs to
                    {{ $ownerName }}, and reading it here changes nothing.
                    @if (Departments::canSee($me, $ownerSlug))
                        <a href="{{ route($owningRoute, collect($route?->parameters())->except('department')->all()) }}">
                            Open it in {{ $ownerName }}
                        </a>
                    @else
                        Only the staff who run {{ $ownerName }} can change it.
                    @endif
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/admin.js') }}"></script>
<script src="{{ asset('js/lightbox.js') }}"></script>
<script src="{{ asset('js/idle-guard.js') }}"></script>
@if ($owningRoute)
<script>
    // A copy belongs to the office it is shown in: its back link returns there, not to the register of the
    // module that owns the record.
    document.querySelectorAll('main.admin-content p.back a').forEach((a) => {
        a.href = @json(route('admin.department', $deptSlug));
        a.textContent = '\u2190 Back to {{ $dept['name'] ?? 'this department' }}';
    });
</script>
@endif
@if ($owningRoute && ! Departments::canSee($me, $ownerSlug))
<script>
    // The copy is read-only for this staff member. The server refuses the write either way; this saves them the
    // click, and says where the record is actually kept.
    document.querySelectorAll('main.admin-content form[method="post" i] button, main.admin-content form[method="post" i] input[type=submit]')
        .forEach((el) => { el.disabled = true; el.title = 'Read-only copy — the {{ $ownerName }} office changes this record.'; });
    document.querySelectorAll('main.admin-content a[href$="/edit"], main.admin-content a[href*="/edit?"]')
        .forEach((el) => el.remove());
</script>
@endif
</body>
</html>

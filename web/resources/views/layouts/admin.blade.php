<!DOCTYPE html>
@php
    use App\Support\Departments;
    $brandName = content('site','branding','brand_name','WireLakes Technologies');
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
    $isSuper = (bool) $me?->isSuperAdmin();
    $initials = collect(explode(' ', trim($me->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');

    /**
     * The sidebar reads as two columns: a rail of the sections this account works in, and beside it the screens of
     * the section the current page belongs to. A section is a department for a super admin, a job title for anyone
     * else, so the rail never lists a screen the account cannot open.
     */
    $mkItem = function (array $item) use ($isActive) {
        return [
            'label' => $item['label'],
            'icon' => $item['icon'] ?? 'grid',
            'href' => route(...$item['route']),
            'active' => $isActive($item),
            'badge' => \App\Support\Departments::badge($item['badge'] ?? null),
        ];
    };

    $sections = [];

    $acting = $isSuper ? null : \App\Support\Workspace::acting($me);
    // The landing cards, shown once to an account holding more than one job title.
    $choosing = ! $isSuper && ! $acting && request()->routeIs('admin.departments')
        && count(\App\Support\Workspace::cards($me)) > 1;

    // Every account opens the office from the same first icon: the department cards for a super admin, their own
    // workspace overview for anyone holding a job title.
    $office = [
        ['label' => $isSuper ? 'All departments' : ($acting ? \App\Support\Roles::all()[$acting]['name'] : 'My overview'),
            'icon' => 'grid', 'href' => route('admin.departments'),
            'active' => request()->routeIs('admin.departments') && ! $choosing, 'badge' => 0],
    ];

    // The title they chose is not the only one they hold, so the cards stay one click away.
    if ($acting || $choosing) {
        $office[] = ['label' => 'All my roles', 'icon' => 'users', 'href' => route('admin.departments').'?role=',
            'active' => $choosing, 'badge' => 0];
    }

    $sections[] = ['label' => 'Office', 'icon' => 'grid', 'items' => $office];

    if ($isSuper) {
        if (\App\Models\Trainer::forAccount($me)->isNotEmpty()) {
            $sections[] = ['label' => \App\Support\Roles::all()['trainer']['name'], 'icon' => 'award', 'items' => [
                ['label' => 'My teaching', 'icon' => 'award', 'href' => route('admin.teaching.index'),
                    'active' => request()->routeIs('admin.teaching.*'), 'badge' => 0],
                ['label' => 'My time log', 'icon' => 'clock', 'href' => route('admin.mytime.index'),
                    'active' => request()->routeIs('admin.mytime.*'), 'badge' => 0],
            ]];
        }

        if ($dept) {
            $sections[] = ['label' => $dept['name'], 'icon' => Departments::icon($dept['slug']), 'items' => array_merge(
                [['label' => 'Overview', 'icon' => 'layout', 'href' => route('admin.department', $dept['slug']),
                    'active' => request()->routeIs('admin.department'), 'badge' => 0]],
                array_map($mkItem, Departments::nav($dept['slug']))
            )];
        }
    } else {
        foreach (\App\Support\Workspace::groups($me) as $group) {
            $items = array_map($mkItem, $group['items']);
            $sections[] = ['label' => $group['label'], 'icon' => $items[0]['icon'] ?? 'grid', 'items' => $items];
        }
    }

    $sections[] = ['label' => 'Outside the portal', 'icon' => 'globe', 'items' => [
        ['label' => 'View public site', 'icon' => 'globe', 'href' => route('home'), 'target' => '_blank',
            'active' => false, 'badge' => 0],
    ]];

    $openSection = collect($sections)->first(fn ($s) => collect($s['items'])->contains('active', true)) ?? $sections[0];
@endphp
<html lang="en" data-idle-minutes="{{ (int) config('account.idle_minutes') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — {{ $brandName }} Staff</title>
    <link rel="icon" href="{{ $brandLogo ?: asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ css_href() }}">
    <script>if (localStorage.getItem('ctp-theme') === 'dark') document.documentElement.classList.add('dark');</script>
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar has-rail" id="adminSidebar">
        <a class="admin-brand" href="{{ route('admin.departments') }}">
            <img src="{{ $brandLogo ?: asset('logo.png') }}" alt="">
            <span>{{ $brandName }}<small>Staff portal</small></span>
        </a>
        <div class="side-cols">
            <nav class="side-rail" aria-label="Every screen">
                @foreach ($sections as $i => $section)
                    @if ($i > 0)<span class="rail-sep"></span>@endif
                    @foreach ($section['items'] as $item)
                        <a href="{{ $item['href'] }}" @if (! empty($item['target'])) target="{{ $item['target'] }}" @endif
                           title="{{ $item['label'] }} — {{ $section['label'] }}" aria-label="{{ $item['label'] }}"
                           class="{{ $item['active'] ? 'active' : '' }}">
                            @include('partials.icon', ['n' => $item['icon']])
                        </a>
                    @endforeach
                @endforeach
            </nav>
            <nav class="admin-nav">
                <div class="nav-group">{{ $openSection['label'] }}</div>
                @foreach ($openSection['items'] as $item)
                    <a href="{{ $item['href'] }}" @if (! empty($item['target'])) target="{{ $item['target'] }}" @endif
                       class="{{ $item['active'] ? 'active' : '' }}">
                        @include('partials.icon', ['n' => $item['icon']]) {{ $item['label'] }}
                        @if ($item['badge'])<span class="nav-badge">{{ $item['badge'] }}</span>@endif
                    </a>
                @endforeach
            </nav>
        </div>
        <div class="side-foot">
            <span class="profile">
                <span class="avatar">{{ $initials }}</span>
                <span class="who"><b>{{ $me->name }}</b><small>{{ $isSuper
                    ? ($dept ? $dept['name'] : count(Departments::visibleFor($me)).' departments')
                    : ($dept ? $dept['name'] : (\App\Support\Roles::labelsFor($me) ?: 'Department staff')) }}</small></span>
            </span>
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
                    <span class="who"><b>{{ $me->name }}</b><small>{{ $isSuper ? 'Super admin' : (\App\Support\Roles::labelsFor($me) ?: 'Department staff') }}</small></span>
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
                    <b>{{ $dept['name'] ?? '' }} copy</b> — this record belongs to
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

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
    // The photo they uploaded when they joined, if the record carries one.
    $photo = isset($member) && $member->photoPath();

    // The grade the member reads as: a CTP the office moved into a fundi pool is shown as that grade here too.
    $grade = isset($member) ? $member->gradeLabel() : ($isCtp ? 'CTP trainee' : 'E-Fundi');

    // Every item the member's own role is entitled to — this sidebar is the whole interface.
    $nav = [
        ['label' => 'Overview', 'icon' => 'grid', 'route' => 'portal.dashboard', 'active' => ['portal.dashboard']],
    ];
    if ($isCtp) {
        $nav[] = ['label' => 'My training', 'icon' => 'check', 'route' => 'portal.training', 'active' => ['portal.training']];
        $nav[] = ['label' => 'My mobilisation', 'icon' => 'users', 'route' => 'portal.mobilisation', 'active' => ['portal.mobilisation']];
        $nav[] = ['label' => 'My communities', 'icon' => 'globe', 'route' => 'portal.communities', 'active' => ['portal.communities'], 'count' => ($communities ?? collect())->count()];
    } else {
        $nav[] = ['label' => 'My E-Fundi record', 'icon' => 'users', 'route' => 'portal.efundi', 'active' => ['portal.efundi']];
        // §9.4 — the desk puts projects to an E-Fundi; a CTP is never offered this screen.
        $nav[] = ['label' => 'Invited to you', 'icon' => 'mail', 'route' => 'portal.invitations', 'active' => ['portal.invitations'], 'count' => ($inviteCount ?? 0)];
        if (isset($member) && $member->canTrain()) {
            $nav[] = ['label' => 'Training CTPs', 'icon' => 'award', 'route' => 'portal.trainer', 'active' => ['portal.trainer']];
        }
    }
    $nav[] = ['label' => 'E-Waste collection', 'icon' => 'globe', 'route' => 'portal.ewaste', 'active' => ['portal.ewaste']];
    // §10.4 — the worker's own side of a job they were awarded. A CTP only ever sees an attachment order here.
    $nav[] = ['label' => $isCtp ? 'My attachment work' : 'My work orders', 'icon' => 'briefcase', 'route' => 'portal.work', 'active' => ['portal.work', 'portal.work.show'], 'count' => ($workOrderCount ?? 0)];
    // §11.4 step 5 — what the inspector flagged on their site and the worker still owes proof of.
    $nav[] = ['label' => 'Items to put right', 'icon' => 'check', 'route' => 'portal.fixes', 'active' => ['portal.fixes'], 'count' => ($fixCount ?? 0)];
    if (! $isCtp) {
        // §12.7 — repair jobs on technology assets assigned to this fundi to fix.
        $nav[] = ['label' => 'My repair jobs', 'icon' => 'grid', 'route' => 'portal.tech.jobs', 'active' => ['portal.tech.jobs'], 'count' => ($techJobCount ?? 0)];
        // §14.4 step 1 — only an E-Fundi works on a site and supplies, so a CTP is never offered this screen.
        $nav[] = ['label' => 'Purchase request', 'icon' => 'link', 'route' => 'portal.procurement', 'active' => ['portal.procurement']];
        $nav[] = ['label' => 'My earnings', 'icon' => 'briefcase', 'route' => 'portal.earnings', 'active' => ['portal.earnings']];
    }
    $nav[] = ['label' => 'My projects', 'icon' => 'briefcase', 'route' => 'portal.projects', 'active' => ['portal.projects', 'portal.project'], 'count' => ($projects ?? collect())->count()];
    $nav[] = ['label' => 'My applications', 'icon' => 'mail', 'route' => 'portal.applications', 'active' => ['portal.applications'], 'count' => ($applications ?? collect())->count()];
@endphp
<html lang="en" data-idle-minutes="{{ (int) config('account.idle_minutes') }}">
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
            <div class="nav-group">{{ $grade }}</div>
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
                    <span class="avatar{{ $photo ? ' has-photo' : '' }}">
                        @if ($photo)<img src="{{ route('portal.photo') }}" alt="{{ $me->name }} — profile photo">@else{{ $initials }}@endif
                    </span>
                    <span class="who"><b>{{ $me->name }}</b><small>{{ $grade }}</small></span>
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
<script src="{{ asset('js/lightbox.js') }}"></script>
<script src="{{ asset('js/idle-guard.js') }}"></script>
</body>
</html>

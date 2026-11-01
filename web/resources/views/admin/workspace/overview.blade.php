@extends('layouts.admin')
@section('title', 'My workspace')

@php
    use App\Support\Departments;
    $me = auth()->user();
    $queue = array_sum(array_column($waiting, 'waiting'));

    // Their own file is not a department's desk, so it is lifted out of the groups and put beside them.
    $ownDesk = fn (array $group) => ['label' => $group['label'], 'note' => $group['note'],
        'items' => array_values(array_filter($group['items'],
            fn (array $i) => ($i['route'][0] ?? '') !== 'admin.profile.index'))];
    $profile = collect($groups)->flatMap(fn (array $g) => $g['items'])
        ->first(fn (array $i) => ($i['route'][0] ?? '') === 'admin.profile.index');
    $groups = array_values(array_filter(array_map($ownDesk, $groups), fn (array $g) => $g['items'] !== []));

    $pages = array_sum(array_map(fn ($g) => count($g['items']), $groups)) + ($profile ? 1 : 0);
@endphp

@section('content')
<div class="ov">
    <div class="ov-id">
        <h1>{{ $me->name }}</h1>
        <p class="ov-roles">
            @forelse ($roleNames as $r)
                <span class="ov-chip">{{ $r }}</span>
            @empty
                <span class="ov-chip quiet">Department staff</span>
            @endforelse
            @if ($canChange)
                <a class="ov-chip quiet" href="{{ route('admin.departments') }}?role=">Change role</a>
            @endif
        </p>
    </div>
    <div class="ov-nums">
        <div><b>{{ $pages }}</b><span>{{ Str::plural('page', $pages) }}</span></div>
        <div><b>{{ count($groups) }}</b><span>{{ Str::plural('desk', count($groups)) }}</span></div>
        <div class="{{ $queue ? 'hot' : '' }}"><b>{{ $queue }}</b><span>waiting</span></div>
    </div>
</div>

@if ($waiting)
    <div class="ov-queue">
        <span class="ov-queue-label">Waiting on you</span>
        @foreach ($waiting as $w)
            <a href="{{ route(...$w['route']) }}"><i>{{ $w['waiting'] }}</i>{{ $w['label'] }}</a>
        @endforeach
    </div>
@elseif ($groups)
    <div class="ov-queue calm"><span class="ov-queue-label">All clear</span>Nothing is waiting on your desks.</div>
@endif

@if ($profile)
    @include('partials.profile-card')
@endif

@foreach ($groups as $group)
    <section class="ov-desk">
        <header class="ov-desk-head">
            <h2>{{ $group['label'] }}</h2>
            <span>{{ count($group['items']) }} {{ Str::plural('page', count($group['items'])) }}</span>
        </header>
        @if ($group['note'])<p class="ov-desk-note">{{ $group['note'] }}</p>@endif
        <div class="ov-grid">
            @foreach ($group['items'] as $t)
                @php $n = Departments::badge($t['badge'] ?? null); @endphp
                <a class="ov-cell {{ $n ? 'hot' : '' }}" href="{{ route(...$t['route']) }}">
                    <span class="ov-ico">@include('partials.icon', ['n' => $t['icon']])</span>
                    <span class="ov-cell-body">
                        <b>{{ $t['label'] }}</b>
                        @if ($t['note'])<small title="{{ $t['note'] }}">{{ $t['note'] }}</small>@endif
                    </span>
                    @if ($n)<span class="ov-pill">{{ $n }}</span>@endif
                </a>
            @endforeach
        </div>
    </section>
@endforeach

@if (! $groups)
    <div class="card">
        <h3>No desk has been opened for this account yet</h3>
        <p class="help">A super admin assigns your roles and departments on <b>Staff &amp; roles</b>.</p>
    </div>
@endif

<div class="card paybill-card">@include('partials.paybill')</div>
@endsection

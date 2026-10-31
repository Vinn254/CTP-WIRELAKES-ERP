@extends('layouts.admin')
@section('title', "Director's desk")

@php
    use App\Support\Departments;
    use App\Support\Finance;

    $fmt = function (array $tile) {
        return match ($tile['unit'] ?? 'count') {
            'money' => Finance::ksh((float) $tile['value']),
            'kg' => number_format((float) $tile['value'], 1).' kg',
            default => number_format((int) $tile['value']),
        };
    };
    $live = count($modules);
    $open = array_filter($approvals, fn (array $a) => $a['count'] > 0);
@endphp

@section('content')
<div class="ov">
    <div class="ov-id">
        <h1>Director's desk</h1>
        <p class="ov-roles">
            <span class="ov-chip">Programme director</span>
            <a class="ov-chip quiet" href="{{ route('admin.departments') }}">All departments</a>
            <a class="ov-chip quiet" href="{{ route('admin.impacts.index') }}">Impact dashboard</a>
            <a class="ov-chip quiet" href="{{ route('admin.activity.index') }}">Full trail</a>
        </p>
    </div>
    <div class="ov-nums">
        <div><b>{{ $live }}</b><span>{{ Str::plural('module', $live) }}</span></div>
        <div><b>{{ $pages }}</b><span>{{ Str::plural('page', $pages) }}</span></div>
        <div class="{{ $waiting ? 'hot' : '' }}"><b>{{ $waiting }}</b><span>for you</span></div>
    </div>
</div>

@if ($open)
    <div class="ov-queue">
        <span class="ov-queue-label">Waiting on your signature</span>
        @foreach ($open as $a)
            <a href="{{ route(...$a['route']) }}"><i>{{ $a['count'] }}</i>{{ $a['label'] }}</a>
        @endforeach
    </div>
@else
    <div class="ov-queue calm"><span class="ov-queue-label">All clear</span>Nothing is waiting on a director.</div>
@endif

<section class="ov-desk">
    <header class="ov-desk-head">
        <h2>Approvals for the director</h2>
        <span>{{ count($open) }} of {{ count($approvals) }} waiting</span>
    </header>
    <p class="ov-desk-note">Each card opens the desk where you give the signature.</p>
    <div class="ov-grid">
        @foreach ($approvals as $a)
            <a class="ov-cell wrap {{ $a['count'] ? 'hot' : '' }}" href="{{ route(...$a['route']) }}">
                <span class="ov-ico">@include('partials.icon', ['n' => $a['icon']])</span>
                <span class="ov-cell-body">
                    <b>{{ $a['label'] }}</b>
                    <small>{{ $a['note'] }}@isset($a['aside']) — {{ $a['aside'] }}@endisset</small>
                </span>
                @if ($a['count'])<span class="ov-pill">{{ $a['count'] }}</span>@endif
            </a>
        @endforeach
    </div>
</section>

<section class="ov-desk">
    <header class="ov-desk-head">
        <h2>Every module</h2>
        <span>{{ $live }} {{ Str::plural('module', $live) }} live</span>
    </header>
    <p class="ov-desk-note">The count is what that office is holding right now.</p>
    <div class="ov-grid">
        @foreach ($modules as $m)
            <a class="ov-cell wrap {{ $m['waiting'] ? 'hot' : '' }}" href="{{ $m['href'] }}">
                <span class="ov-ico">@include('partials.icon', ['n' => $m['icon']])</span>
                <span class="ov-cell-body">
                    <b>{{ $m['name'] }}</b>
                    <small>{{ $m['pages'] }} {{ Str::plural('page', $m['pages']) }} · {{ $m['desc'] }}</small>
                </span>
                @if ($m['waiting'])<span class="ov-pill">{{ $m['waiting'] }}</span>@endif
            </a>
        @endforeach
    </div>
</section>

<section class="ov-desk">
    <header class="ov-desk-head">
        <h2>The numbers behind a decision</h2>
        <span>counted live</span>
    </header>
    <div class="stat-row">
        @foreach ($numbers as $n)
            <div class="stat">
                <small>{{ $n['label'] }}</small>
                <b>{{ $fmt($n) }}</b>
            </div>
        @endforeach
    </div>
    <div class="stat-row">
        @foreach ($money as $n)
            <div class="stat">
                <small>{{ $n['label'] }}</small>
                <b>{{ $fmt($n) }}</b>
                @if ($n['note'])<span class="hint">{{ $n['note'] }}</span>@endif
            </div>
        @endforeach
    </div>
    <p class="hint">By county, by sector and by month: <a href="{{ route('admin.impacts.index') }}">impact dashboard</a>,
        <a href="{{ route('admin.gis.map') }}">county map</a>, <a href="{{ route('admin.reports.index') }}">reports</a>.</p>
</section>

<section class="ov-desk">
    <header class="ov-desk-head">
        <h2>Tasks performed</h2>
        <span>last 30 days</span>
    </header>
    <div class="stat-row">
        @foreach ($performed as $p)
            <div class="stat">
                <small>{{ $p['label'] }}</small>
                <b>{{ number_format($p['value']) }}</b>
            </div>
        @endforeach
    </div>
    <table class="data">
        <thead>
        <tr><th>When</th><th>Who</th><th>Did</th><th>Record</th></tr>
        </thead>
        <tbody>
        @forelse ($movement as $line)
            <tr>
                <td class="hint">{{ $line->created_at->format('d M Y, H:i') }}</td>
                <td><b>{{ $line->actor_name ?: $line->actor?->name ?: '—' }}</b></td>
                <td>{{ $line->actionLabel() }}</td>
                <td>
                    {{ \App\Models\ActivityLog::recordName($line->subject_type) }}
                    @if ($line->subject_label)<span class="hint">· {{ $line->subject_label }}</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty-cell">Nothing recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="hint"><a href="{{ route('admin.activity.index') }}">Open the full trail</a> — every entry, filterable by person, record and action.</p>
</section>

<div class="card paybill-card">@include('partials.paybill')</div>
@endsection

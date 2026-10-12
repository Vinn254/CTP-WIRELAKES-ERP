@extends('layouts.admin')
@section('title', 'Departments')

@section('content')
<h1>Departments</h1>
<p class="sub" style="color:var(--muted);">Welcome, {{ auth()->user()->name }}. Open a department to manage it.
    @foreach ($pending as $t => $n)
        @if ($n > 0)<span class="tag gold">{{ $n }} pending {{ strtoupper($t) }} application{{ $n > 1 ? 's' : '' }}</span>@endif
    @endforeach
    @if ($pendingTotal === 0)<span class="tag">Review queue is clear</span>@endif
</p>

<div class="workspace" id="module-1">
    <div class="ws-head">
        <div>
            <span class="mod">Module 1 · Live</span>
            <h2>Public Site &amp; Membership</h2>
        </div>
        <a class="btn btn-outline btn-sm" href="{{ route('home') }}" target="_blank">View public site ↗</a>
    </div>
    <div class="ws-grid">
        @foreach ($workspace as $w)
            <a class="ws-tile {{ ($w['alert'] ?? false) ? 'alert' : '' }}" href="{{ route(...$w['route']) }}">
                <b>{{ $w['label'] }}</b>
                <small>{{ $w['meta'] }}</small>
            </a>
        @endforeach
    </div>
</div>

<h2 class="sec-title">All departments</h2>
<div class="dept-grid">
    @foreach ($departments as $d)
        <div class="dept-card {{ $d['live'] ? 'live' : 'soon' }}">
            @if ($d['live'])
                <span class="badge tag">Active</span>
            @else
                <span class="badge tag gray">Phase 2</span>
            @endif
            <div class="mod">Module {{ $d['module'] }}</div>
            <h3>{{ $d['name'] }}</h3>
            <p>{{ $d['desc'] }}</p>
            @if ($d['live'])
                <a class="btn btn-primary btn-sm" href="#module-1">Open workspace</a>
                <a class="btn btn-ghost btn-sm" href="{{ route('admin.registrations.index', 'ctp') }}">
                    Applications @if ($pendingTotal > 0) ({{ $pendingTotal }}) @endif
                </a>
            @else
                <span class="btn btn-ghost btn-sm" style="pointer-events:none;">Coming in next phase</span>
            @endif
        </div>
    @endforeach
</div>
@endsection

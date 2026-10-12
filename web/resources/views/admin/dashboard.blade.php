@extends('layouts.admin')
@section('title', 'Departments')

@section('content')
<h1>Departments</h1>
<p class="sub" style="color:var(--muted);">Welcome, {{ auth()->user()->name }}. Click a department to manage it.
@foreach ($pending as $t => $n)
    @if ($n > 0)<span class="tag gold">{{ $n }} pending {{ strtoupper($t) }} application{{ $n > 1 ? 's' : '' }}</span> @endif
@endforeach
@if ($pendingTotal === 0)<span class="tag">Review queue is clear</span>@endif
</p>

<div class="dept-grid" style="margin-top:22px;">
    @foreach ($departments as $d)
        <div class="dept-card {{ $d['live'] ? 'live' : 'soon' }}">
            @if ($d['live'])
                <span class="badge tag">Active</span>
            @else
                <span class="badge tag gray">Phase 2</span>
            @endif
            <div class="mod">MODULE {{ $d['module'] }}</div>
            <h3>{{ $d['name'] }}</h3>
            <p>{{ $d['desc'] }}</p>
            @if ($d['live'])
                <a class="btn btn-primary btn-sm" href="{{ route('admin.registrations.index', 'ctp') }}">
                    Open review queue
                    @php $n = $pending['ctp'] + $pending['fundi'] + $pending['hirer']; @endphp
                    @if ($n > 0) ({{ $n }}) @endif
                </a>
            @else
                <span class="btn btn-ghost btn-sm" style="pointer-events:none;">Coming in next phase</span>
            @endif
        </div>
    @endforeach
</div>
@endsection

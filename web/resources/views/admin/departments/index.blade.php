@extends('layouts.admin')
@section('title', 'Departments')

@php
    use App\Support\Departments;
@endphp

@section('content')
<h1>Departments</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 20px;">
    Welcome, {{ auth()->user()->name }}. Each department is one module of the programme — open a department to reach
    the screens that belong to it.
</p>

<div class="dept-grid">
    @foreach ($departments as $d)
        @php $waiting = Departments::waiting($d['slug']); @endphp
        <div class="dept-card {{ $d['live'] ? 'live' : 'soon' }}">
            <span class="badge tag {{ $d['live'] ? '' : 'gray' }}">{{ $d['live'] ? 'Active' : 'Phase 2' }}</span>
            <div class="mod">{{ $d['tag'] }}</div>
            <h3>{{ $d['name'] }}</h3>
            <p>{{ $d['desc'] }}</p>
            @if ($d['live'])
                <a class="btn btn-primary btn-sm" href="{{ route('admin.department', $d['slug']) }}">Open department</a>
                @if ($waiting)<span class="tag gold">{{ $waiting }} waiting</span>@endif
            @else
                <span class="btn btn-ghost btn-sm" style="pointer-events:none;">Coming in next phase</span>
            @endif
        </div>
    @endforeach
</div>
@endsection

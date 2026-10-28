@extends('layouts.admin')
@section('title', 'Departments')

@php
    use App\Support\Departments;
@endphp

@section('content')
<h1>Departments</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 20px;">
    Welcome, {{ auth()->user()->name }}.
    @if ($isSuper)
        You are a <b>super admin</b> — every one of the {{ count($departments) }} live modules is open to you.
    @else
        These are the {{ count($departments) }} department{{ count($departments) === 1 ? '' : 's' }} assigned to you. Each is one module — open it to reach the screens that belong to it.
    @endif
</p>

@if ($teaching ?? null)
    <section class="ov-desk" style="margin:0 0 20px;">
        <header class="ov-desk-head">
            <h2>{{ \App\Support\Roles::all()['trainer']['name'] }}</h2>
            <span>1 page</span>
        </header>
        <p class="ov-desk-note">{{ \App\Support\Roles::all()['trainer']['desc'] }}</p>
        <div class="ov-grid">
            <a class="ov-cell" href="{{ route('admin.teaching.index') }}">
                <span class="ov-ico">@include('partials.icon', ['n' => 'award'])</span>
                <span class="ov-cell-body">
                    <b>My teaching</b>
                    <small>The cohorts you take and the marks you record for them</small>
                </span>
            </a>
        </div>
    </section>
@endif

<div class="dept-grid">
    @foreach ($departments as $d)
        @php $waiting = Departments::waiting($d['slug']); @endphp
        <div class="dept-card {{ $d['live'] ? 'live' : 'soon' }}">
            <span class="badge tag {{ $d['live'] ? '' : 'gray' }}">{{ $d['live'] ? 'Active' : 'Phase 2' }}</span>
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

<div class="card paybill-card">@include('partials.paybill')</div>
@endsection

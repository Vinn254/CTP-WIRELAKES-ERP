@extends('layouts.admin')
@section('title', 'My roles')

@php
    $me = auth()->user();
    $queue = array_sum(array_column($cards, 'waiting'));
@endphp

@section('content')
<div class="ov">
    <div class="ov-id">
        <h1>{{ $me->name }}</h1>
        <p class="ov-roles">
            @foreach ($cards as $c)
                <span class="ov-chip">{{ $c['label'] }}</span>
            @endforeach
        </p>
    </div>
    <div class="ov-nums">
        <div><b>{{ count($cards) }}</b><span>{{ Str::plural('role', count($cards)) }}</span></div>
        <div class="{{ $queue ? 'hot' : '' }}"><b>{{ $queue }}</b><span>waiting</span></div>
    </div>
</div>

<p class="page-lede">Open the role you are working as today.</p>

<div class="dept-grid">
    @foreach ($cards as $c)
        <div class="dept-card live">
            @if ($c['waiting'])<span class="badge tag gold">{{ $c['waiting'] }}</span>@endif
            <h3>{{ $c['label'] }}</h3>
            <p>{{ $c['note'] }}</p>
            <p class="ah-meta"><span>{{ $c['pages'] }} {{ Str::plural('page', $c['pages']) }}</span></p>
            <a class="btn btn-primary btn-sm" href="{{ route('admin.departments') }}?role={{ $c['slug'] }}">
                Open workspace
            </a>
        </div>
    @endforeach
</div>

<div class="card paybill-card">@include('partials.paybill')</div>
@endsection

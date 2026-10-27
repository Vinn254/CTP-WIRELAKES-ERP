@extends('layouts.admin')
@section('title', $department['name'])

@php
    use App\Support\Departments;
@endphp

@section('content')
<div class="workspace">
    <div class="ws-head">
        <div>
            <span class="mod">{{ auth()->user()->isSuperAdmin() ? $department['tag'].' · Live' : 'Live' }}</span>
            <h2>{{ $department['name'] }}</h2>
        </div>
        @if ($department['slug'] === 'membership')
            <a class="btn btn-outline btn-sm" href="{{ route('home') }}" target="_blank">View public site ↗</a>
        @endif
    </div>
    <p style="color:var(--muted);margin:0 0 14px;">{{ $department['desc'] }}</p>

    <div class="ws-grid">
        @foreach ($tiles as $t)
            @php $waiting = Departments::badge($t['badge'] ?? null); @endphp
            <a class="ws-tile {{ $waiting > 0 ? 'alert' : '' }}" href="{{ route(...$t['route']) }}">
                <b>{{ $t['label'] }}</b>
                <small>{{ $t['note'] }}</small>
                @if ($waiting)<small><span class="tag gold">{{ $waiting }} waiting</span></small>@endif
            </a>
        @endforeach
    </div>
</div>
@endsection

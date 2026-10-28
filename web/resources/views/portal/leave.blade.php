@extends('layouts.member')
@section('title', 'My leave')

@php
    use App\Support\Leave;

    $byType = collect($balances)->keyBy('type');
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">The days you have left of each type, and an application for Human Resources to answer.</p>
    <div class="member-stats">
        <div class="stat"><small>Annual leave left</small><b>{{ Leave::format($byType['annual']['left']) }}</b></div>
        <div class="stat"><small>Sick leave left</small><b>{{ Leave::format($byType['sick']['left']) }}</b></div>
        <div class="stat"><small>Waiting on the desk</small>
            <b>{{ $history->where('status', 'pending')->count() }}</b></div>
        <div class="stat"><small>Working since</small><b>{{ $joined->format('M Y') }}</b></div>
    </div>
</div>

@include('partials.leave-board')
@endsection

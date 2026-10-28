@extends('layouts.admin')
@section('title', 'My leave')

@php
    use App\Support\Leave;
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>My leave</h1>
        <p class="ah-meta">
            <span><b>{{ $worker->name }}</b></span>
            <span>{{ $worker->role === 'fundi' ? 'E-Fundi' : ($worker->isSuperAdmin() ? 'Super admin' : 'Office staff') }}</span>
            <span>working since {{ $joined->format('d M Y') }}</span>
            <span>{{ count($balances) }} leave types</span>
            <span>{{ $months }} {{ Str::plural('month', $months) }} accrued</span>
        </p>
    </div>
    <div class="head-actions">
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

@include('partials.leave-board')
@endsection

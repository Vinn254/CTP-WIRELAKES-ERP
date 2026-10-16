@extends('layouts.app')
@section('title', 'Not accepted')

@php
    $isMember = auth()->check() && in_array(auth()->user()->role, ['ctp', 'fundi'], true);
    $backUrl = $isMember ? route('portal.dashboard') : route('home');
    $backLabel = $isMember ? 'Back to my projects' : 'Back to home';
@endphp

@section('content')
<section class="section">
    <span class="tag">Not accepted</span>
    <h1>This step is not open yet</h1>
    <p class="sub">{{ $exception->getMessage() ?: 'The record is not at the point where that step is allowed. '
        .'Nothing was changed — open the record and read where it stands.' }}</p>
    <p><a class="btn btn-primary" href="{{ $backUrl }}">{{ $backLabel }}</a></p>
</section>
@endsection

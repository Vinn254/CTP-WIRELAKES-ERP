@extends('layouts.app')
@section('title', 'Page not found')

@php
    $isMember = auth()->check() && in_array(auth()->user()->role, ['ctp', 'fundi'], true);
    $backUrl = $isMember ? route('portal.dashboard') : route('home');
    $backLabel = $isMember ? 'Back to my projects' : 'Back to home';
@endphp

@section('content')
<section class="section">
    <span class="tag">404</span>
    <h1>That page doesn’t exist</h1>
    <p class="sub">The link may be out of date, or the page it pointed to has been removed.</p>
    <p><a class="btn btn-primary" href="{{ $backUrl }}">{{ $backLabel }}</a></p>
</section>
@endsection

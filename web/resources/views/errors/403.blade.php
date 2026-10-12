@extends('layouts.app')
@section('title', 'Not available')

@php
    $isMember = auth()->check() && in_array(auth()->user()->role, ['ctp', 'fundi'], true);
    $backUrl = $isMember ? route('portal.dashboard') : route('home');
    $backLabel = $isMember ? 'Back to my projects' : 'Back to home';
@endphp

@section('content')
<section class="section">
    <span class="tag">Restricted</span>
    <h1>You can’t open this page</h1>
    <p class="sub">{{ $exception->getMessage() ?: 'This page is only for the members or staff it was issued to.' }}</p>
    <p><a class="btn btn-primary" href="{{ $backUrl }}">{{ $backLabel }}</a></p>
</section>
@endsection

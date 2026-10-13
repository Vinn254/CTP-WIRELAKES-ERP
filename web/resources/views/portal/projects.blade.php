@extends('layouts.member')
@section('title', 'My projects')

@php
    $level = $member instanceof App\Models\CtpRegistration
        ? $member->poolLabel()
        : 'your E-Fundi level';
@endphp

@section('content')
<div class="page-intro">
    <p style="margin:0;">You see projects for <b>{{ $level }}</b>. Only work posted at your level appears here.</p>
    <a class="btn btn-sm btn-outline" href="{{ route('portal.applications') }}">My applications</a>
</div>
@include('portal.partials.project-list')
@endsection

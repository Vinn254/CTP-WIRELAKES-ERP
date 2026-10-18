@extends('layouts.member')
@section('title', 'My projects')

@php
    $isCtp = $member instanceof App\Models\CtpRegistration;
    $level = $isCtp ? $member->gradeLabel() : 'your E-Fundi level';
@endphp

@section('content')
<div class="page-intro">
    @if ($isCtp)
        <p style="margin:0;">You are a <b>trainee</b>: every project the programme posts is open for you to read, so you
            know what work is coming to your area. You apply to <b>CTP attachment</b> projects only — the rest are for
            E-Fundis at that level, and you reach them after your training.</p>
    @else
        <p style="margin:0;">You see projects for <b>{{ $level }}</b>. Only work posted at your level appears here.</p>
    @endif
    <a class="btn btn-sm btn-outline" href="{{ route('portal.applications') }}">My applications</a>
</div>
@include('portal.partials.project-list')
@endsection

@extends('layouts.member')
@section('title', 'Projects & applications')

@php
    use App\Models\FundiRegistration;
    use App\Models\Project;
    use App\Models\ProjectApplication;
    use Illuminate\Support\Str;
    $isFundi = $member instanceof FundiRegistration;
    $code = $isFundi ? $member->ef_code : $member->ctp_code;
    $levelLabel = $isFundi
        ? FundiRegistration::tierLabel($member->tier)
        : ($member->fundi_tier ? $member->poolLabel() : 'CTP ('.Str::headline($member->track ?? '').')');
    $openApps = $applications->where('status', 'pending_review');
    $won = $applications->where('status', 'approved');
@endphp

@section('content')
<div class="member-hero">
    <div>
        <span class="tag">{{ $isFundi ? 'E-Fundi' : 'CTP' }}@if ($code) · {{ $code }}@endif</span>
        <h1>Hello, {{ Str::before($member->full_name, ' ') }}.</h1>
        <p class="sub">You are registered as <b>{{ $levelLabel }}</b>. Only projects at your level appear here.</p>
    </div>
    <div class="member-stats">
        <div class="stat"><small>Open for you</small><b>{{ $projects->count() }}</b></div>
        <div class="stat"><small>Waiting review</small><b>{{ $openApps->count() }}</b></div>
        <div class="stat"><small>Awarded</small><b>{{ $won->count() }}</b></div>
    </div>
</div>

@include('portal.partials.projects')
@endsection

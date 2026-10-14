@extends('layouts.member')
@section('title', 'Trainee — '.$enrollment->ctp->full_name)

@php
    use App\Models\Enrollment;

    $cohort = $enrollment->cohort;
    $cert = $enrollment->certification;
    $held = $enrollment->state === 'approved';
    $attendance = $enrollment->attendancePct();
    $pct = $enrollment->trackPct();
@endphp

@section('content')
<p class="back"><a href="{{ route('portal.trainer') }}">&larr; {{ $cohort?->reference }} — your students</a></p>

<div class="page-intro">
    <h1>{{ $enrollment->ctp->full_name }}
        <span class="status {{ $enrollment->state === 'approved' ? 'active' : 'pending_review' }}">{{ $enrollment->stateLabel() }}</span>
        <span class="tag">{{ $enrollment->stageLabel() }}</span>
        <span class="tag gold">{{ $pct }}% of the track</span>
    </h1>
    <p class="sub">{{ $enrollment->ctp->ctp_code }} · {{ $cohort?->courseLabel() }} ({{ $cohort?->reference }}) ·
        {{ $cohort?->dateLabel() }} · at {{ $cohort?->venue?->name ?? $cohort?->placeLabel() }}</p>
    <div class="member-stats">
        <div class="stat"><small>Track</small><b>{{ $pct }}%</b></div>
        <div class="stat"><small>Attendance</small><b>{{ $attendance === null ? 'not marked' : $attendance.'%' }}</b></div>
        <div class="stat"><small>Records</small><b>{{ $enrollment->entries->count() }}</b></div>
        <div class="stat"><small>Modules marked</small>
            <b>{{ $enrollment->moduleMarks->count() }}{{ $cohort?->training?->modules?->count() ? ' / '.$cohort->training->modules->count() : '' }}</b></div>
        <div class="stat"><small>Documents</small><b>{{ $enrollment->documents->count() }}</b></div>
    </div>
</div>

@if (! $held)
    <div class="card">
        <h3>Their seat is {{ $enrollment->stateLabel() }}</h3>
        <p class="help">The programme office confirms a seat before anything can be recorded on it. Until then this page
            stays read-only — their application, the reason and any note are what the office acts on.</p>
    </div>
@endif

@if ($attendance !== null && $attendance < Enrollment::ATTENDANCE_MINIMUM)
    <div class="card callout">
        <h3>Attendance is under the rule</h3>
        <p class="help" style="margin:0;">{{ $enrollment->ctp->full_name }} has been on
            {{ $attendance }}% of the days marked. A trainee under {{ Enrollment::ATTENDANCE_MINIMUM }}% cannot be
            certified, whatever the scores say — mark every day they turn up, and tell the office if the absences have
            a reason behind them.</p>
    </div>
@endif

<div class="card">
    @include('partials.progress', ['enrollment' => $enrollment, 'mode' => 'trainer', 'open' => true])
</div>

<div class="card">
    <div class="section-head"><h3>Their track</h3><span class="tag">{{ $pct }}%</span></div>
    @include('partials.track', ['enrollment' => $enrollment])
</div>

@if ($held)
    <div class="card">
        @include('partials.modules', ['enrollment' => $enrollment, 'mode' => 'trainer'])
    </div>

    <div class="card">
        @include('partials.documents', [
            'enrollment' => $enrollment,
            'mode' => 'trainer',
            'modules' => $cohort?->training?->modules ?? collect(),
            'documentKinds' => $documentKinds,
        ])
    </div>
@endif

@if ($cert)
    <div class="card">
        <h3>Certification</h3>
        <p class="hint">{{ $cert->certificate_code ?: 'no code yet' }} —
            <b>{{ $cert->statusLabel() }}</b>@if ($cert->reason) · {{ $cert->reason }} @endif</p>
        <p class="hint">A trainer only records what happened. The office verifies the track and a programme executive
            signs the certificate.</p>
    </div>
@endif

<p class="hint">Everything you put on this page goes straight onto {{ $enrollment->ctp->full_name }}’s own training
    record and onto the office’s track page for them — same lines, same moment.</p>
@endsection

@extends('layouts.admin')
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
<p class="back"><a href="{{ route('admin.teaching.index') }}">&larr; My teaching</a></p>

<div class="head-row">
    <div>
        <h1>{{ $enrollment->ctp->full_name }}
            <span class="status {{ $held ? 'active' : 'pending_review' }}">{{ $enrollment->stateLabel() }}</span>
            <span class="tag gold">{{ $pct }}% of the track</span>
        </h1>
        <p class="ah-meta">
            <span><b>{{ $enrollment->ctp->ctp_code }}</b></span>
            <span>{{ $cohort?->reference }} — {{ $cohort?->courseLabel() }}</span>
            <span>{{ $cohort?->dateLabel() }}</span>
            <span>{{ $cohort?->venue?->name ?? $cohort?->placeLabel() }}</span>
            <span class="tag gray">{{ $enrollment->stageLabel() }}</span>
            <span class="tag">{{ $attendance === null ? 'attendance not marked' : $attendance.'% attendance' }}</span>
        </p>
    </div>
</div>

@if (! $held)
    <div class="card">
        <h3>This seat is {{ $enrollment->stateLabel() }}</h3>
        <p class="help">The programme office confirms a seat before anything can be recorded on it. Until then this page
            stays read-only.</p>
    </div>
@endif

@if ($attendance !== null && $attendance < Enrollment::ATTENDANCE_MINIMUM)
    <div class="card callout">
        <h3>Attendance is under the rule</h3>
        <p class="help" style="margin:0;">{{ $enrollment->ctp->full_name }} has been on {{ $attendance }}% of the days
            marked. A trainee under {{ Enrollment::ATTENDANCE_MINIMUM }}% cannot be certified, whatever the scores say.</p>
    </div>
@endif

<div class="card">
    @include('partials.progress', ['enrollment' => $enrollment, 'mode' => 'teaching', 'open' => true])
</div>

<div class="card">
    <div class="section-head"><h3>Their track</h3><span class="tag">{{ $pct }}%</span></div>
    @include('partials.track', ['enrollment' => $enrollment])
</div>

@if ($held)
    <div class="card">
        @include('partials.modules', ['enrollment' => $enrollment, 'mode' => 'teaching'])
    </div>

    <div class="card">
        @include('partials.documents', [
            'enrollment' => $enrollment,
            'mode' => 'teaching',
            'modules' => $cohort?->training?->modules ?? collect(),
            'documentKinds' => $documentKinds,
        ])
    </div>
@endif

@if ($cert)
    <div class="card">
        <h3>Certification</h3>
        <p class="hint">{{ $cert->certificate_code ?: 'no code yet' }} — <b>{{ $cert->statusLabel() }}</b>
            @if ($cert->reason) · {{ $cert->reason }} @endif</p>
        <p class="hint">A trainer records what happened. The office verifies the track and a programme executive signs
            the certificate.</p>
    </div>
@endif
@endsection

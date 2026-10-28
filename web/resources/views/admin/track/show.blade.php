@extends('layouts.admin')
@section('title', 'Track — '.$enrollment->ctp->full_name)

@php
    $cohort = $enrollment->cohort;
    $cert = $enrollment->certification;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.track.index') }}">&larr; Track</a></p>
<div class="head-row">
    <div>
        <h1>{{ $enrollment->ctp->full_name }}
            <span class="status {{ $enrollment->stage }}">{{ $enrollment->stageLabel() }}</span>
            <span class="tag">{{ $pct }}% of the track</span>
        </h1>
        <p class="ah-meta">
            <span><b>{{ $enrollment->ctp->ctp_code ?: 'no CTP ID yet' }}</b></span>
            <span>{{ $cohort?->reference }} — {{ $cohort?->courseLabel() }}</span>
            <span>{{ $cohort?->training?->name }}</span>
            <span>{{ $cohort?->dateLabel() }}</span>
            <span>{{ $cohort?->placeLabel() }}</span>
            @if ($cohort?->venue?->type === 'partner_school')<span class="tag gold">placed at a partner school</span>@endif
        </p>
    </div>
<div class="ah-contact">
    <span class="tag gray">{{ $enrollment->stateLabel() }}</span>
    @if ($cohort?->trainer)<span class="tag">trainer {{ $cohort->trainer->personName() }}</span>@endif
</div>
</div>

@if ($cohort?->venue?->type === 'partner_school')
    <div class="card callout">
        <h3>This CTP is learning at a partner school</h3>
        <p class="help" style="margin:0;">The school keeps its own marks, so the office uploads what it sends —
            a report, an assessment paper, a competency sheet. One upload covers the evidence part of the track;
            the modules and the scores are still marked here so the trainee can reach 100%.</p>
    </div>
@endif

<div class="grid-2">
    <div class="card">
        <h3>How far the track has come</h3>
        @include('partials.track', ['enrollment' => $enrollment])
    </div>

    <div class="card">
        <h3>Certification</h3>
        @if ($cert)
            <div class="row-line">
                <b>{{ $cert->certificate_code ?: 'no code until an executive signs' }}</b>
                <span class="status {{ $cert->status === 'approved' ? 'active' : ($cert->status === 'recommended' ? 'pending_review' : 'closed') }}">{{ $cert->statusLabel() }}</span>
            </div>
            @if ($cert->reason)<p class="hint">{{ $cert->reason }}</p>@endif
            <p class="hint">Theory {{ $cert->theory_score ?? '—' }} · Practical {{ $cert->practical_score ?? '—' }}
                · Attendance {{ $cert->attendance_pct !== null ? $cert->attendance_pct.'%' : '—' }}
                @if ($cert->recommender) · put forward by {{ $cert->recommender->name }} @endif</p>
            <p style="margin:12px 0 0;"><a class="btn btn-sm btn-outline" href="{{ route('admin.certification.index') }}">Certification queue</a></p>
        @elseif ($pct >= 100)
            <p class="help">Their track is whole. They are put forward the moment the last part is recorded —
                if this is an older record, put them forward now.</p>
            <form method="POST" action="{{ route('admin.certification.recommend', $enrollment) }}" class="fee-form"
                  data-confirm="Put {{ $enrollment->ctp->full_name }} forward for certification?" data-title="Put forward">
                @csrf
                <button class="btn btn-sm btn-lime" type="submit">Put forward</button>
            </form>
        @else
            <p class="hint">Nothing can be put forward until the track reaches 100%. What is left:
                @foreach ($parts as $p)
                    @if (! $p['done'])<div class="row-line"><b>{{ $p['label'] }}</b><span class="hint">{{ $p['detail'] }}</span></div>@endif
                @endforeach
            </p>
        @endif
    </div>
</div>

<div class="card">
    @include('partials.modules', [
        'enrollment' => $enrollment,
        'board' => $board,
        'mode' => 'admin',
    ])
</div>

<div class="card">
    @include('partials.progress', ['enrollment' => $enrollment, 'mode' => 'admin', 'open' => true])
</div>

<div class="card">
    @include('partials.documents', [
        'enrollment' => $enrollment,
        'mode' => 'admin',
        'modules' => $board['modules'],
        'documentKinds' => $documentKinds,
    ])
</div>

<div class="card">
    <h3>Where the seat stands</h3>
    <dl class="kv">
        <dt>Seat</dt><dd>{{ $enrollment->stateLabel() }}
            @if ($enrollment->approved_on)<span class="hint">since {{ $enrollment->approved_on->format('d M Y') }}</span>@endif</dd>
        <dt>Placed by</dt><dd>{{ $enrollment->source === 'office' ? 'the office' : 'they applied themselves' }}</dd>
        <dt>Trainer</dt><dd>{{ $cohort?->trainer?->personName() ?? 'No trainer named on the cohort' }}</dd>
        <dt>Site</dt><dd>{{ $cohort?->placeLabel() ?: '—' }}</dd>
        <dt>Period</dt><dd>{{ $cohort?->dateLabel() ?: '—' }}</dd>
        <dt>Attendance</dt><dd>{{ $enrollment->attendancePct() === null ? 'not marked' : $enrollment->attendancePct().'% of the days marked' }}</dd>
    </dl>
    @if ($enrollment->note)<p class="hint">“{{ $enrollment->note }}”</p>@endif
</div>
@endsection

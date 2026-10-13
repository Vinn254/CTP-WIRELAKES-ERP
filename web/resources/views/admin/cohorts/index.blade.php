@extends('layouts.admin')
@section('title', 'Cohorts')

@php
    use App\Models\Cohort;
    $tabs = ['all' => 'All'] + Cohort::STATUSES;
    $openCount = $counts['open'] ?? 0;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.department', 'training') }}">&larr; Training &amp; Certification</a></p>
<h1>Cohorts</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    A cohort is one intake of a training the programme runs, held at one place. <b>Posted as open, CTPs apply for a seat</b> or the office puts a
    CTP straight in; progress is recorded until the trainee is certified.
</p>

<div class="stat-row">
    <div class="stat"><small>Open for applications</small><b>{{ $openCount }}</b></div>
    <div class="stat"><small>In training</small><b>{{ $counts['in_progress'] ?? 0 }}</b></div>
    <div class="stat"><small>Seats awaiting you</small><b>{{ $pendingSeats }}</b></div>
    <div class="stat"><small>Cohorts</small><b>{{ $counts->sum() }}</b></div>
</div>

<form method="GET" class="filter-bar">
    <button class="btn btn-sm btn-primary" type="button" onclick="location.href='{{ route('admin.cohorts.create') }}'">Post a training</button>
    <span class="hint" style="margin-left:8px;">A cohort starts as a draft — only an open one reaches the CTP portal.</span>
</form>

<div class="tabs">
    @foreach ($tabs as $s => $lbl)
        <a class="tab {{ $status === $s ? 'on' : '' }}" href="{{ route('admin.cohorts.index', ['status' => $s]) }}">
            {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
        </a>
    @endforeach
</div>

<table class="data">
    <thead>
        <tr>
            <th>Ref</th><th>Course</th><th>Where</th><th>Trainer</th><th>When</th>
            <th>Seats</th><th>Trainees</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($cohorts as $c)
            @php
                $pending = $c->enrollments->where('state', 'pending')->count();
                $approved = $c->enrollments->where('state', 'approved')->count();
            @endphp
            <tr>
                <td><b>{{ $c->reference }}</b></td>
                <td>
                    <a href="{{ route('admin.cohorts.show', $c) }}"><b>{{ $c->courseLabel() }}</b></a>
                    <div class="hint">{{ $c->training?->name }} · {{ $c->hoursLabel() }}</div>
                </td>
                <td>{{ $c->venue?->name ?: ($c->trainer ? 'Trainer site' : 'Community site') }}
                    <div class="hint">{{ collect([$c->ward, $c->sub_county, $c->county])->filter()->join(', ') }}</div></td>
                <td>{{ $c->trainer?->fundi?->full_name ?: '—' }}
                    @if ($c->venue)<div class="hint">{{ $c->venue->typeLabel() }}</div>@endif</td>
                <td>{{ $c->dateLabel() }}<div class="hint">KSh {{ number_format((float) $c->fee_ksh) }}</div></td>
                <td>{{ $approved + $pending }} / {{ $c->seats }}</td>
                <td>
                    {{ $approved }} training
                    @if ($pending)<div class="hint"><b>{{ $pending }}</b> seat{{ $pending === 1 ? '' : 's' }} awaiting you</div>@endif
                </td>
                <td><span class="status {{ $c->status }}">{{ $c->statusLabel() }}</span>
                    @if ($c->status === 'open' && $c->isFull())<div class="hint">full</div>@endif</td>
                <td style="text-align:right;"><a class="btn btn-sm btn-outline" href="{{ route('admin.cohorts.show', $c) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:28px;">
                No cohorts in this view yet. Post a training, then open it for CTPs to apply.
            </td></tr>
        @endforelse
    </tbody>
</table>
@endsection

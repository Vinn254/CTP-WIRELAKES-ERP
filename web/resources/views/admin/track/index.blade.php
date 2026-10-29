@extends('layouts.admin')
@section('title', 'Track')

@section('content')
<h1>Track</h1>
<p class="page-lede">Shows each trainee's training progress and what is still missing.</p>

<div class="stat-row">
    <div class="stat"><small>Trainees</small><b>{{ $counts['all'] }}</b></div>
    <div class="stat"><small>Still training</small><b>{{ $counts['open'] }}</b></div>
    <div class="stat"><small>Track not complete</small><b>{{ $counts['short'] }}</b></div>
    <div class="stat"><small>At 100%</small><b>{{ $counts['complete'] }}</b></div>
    <div class="stat"><small>In certification</small><b>{{ $counts['certified'] }}</b></div>
</div>

<form method="GET" action="{{ route('admin.track.index') }}" class="fee-form" style="margin:0 0 14px;">
    <div class="form-grid-2">
        <div class="field">
            <label>Track status</label>
            <select name="f" onchange="this.form.submit()">
                @foreach ($filters as $key => $label)
                    <option value="{{ $key }}" @selected($filter === $key)>{{ $label }} ({{ $counts[$key] }})</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Cohort</label>
            <select name="cohort">
                <option value="">Every cohort</option>
                @foreach ($cohorts as $c)
                    <option value="{{ $c->id }}" @selected($cohortId === $c->id)>{{ $c->reference }} — {{ $c->training?->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="align-self:end;">
            <button class="btn btn-outline btn-block" type="submit">Filter</button>
        </div>
    </div>
</form>

@php
    // The same trainees, filed under the course they are sitting, so one programme reads on its own.
    $groups = collect($rows)
        ->groupBy(fn (array $row) => $row['e']->cohort?->courseLabel() ?: 'Cohort removed')
        ->sortKeys();
@endphp

@forelse ($groups as $label => $block)
<h4 class="dl-group">{{ $label }} — {{ $block->count() }} {{ Str::plural('trainee', $block->count()) }}</h4>
<table class="data">
    <thead>
        <tr>
            <th>Trainee</th><th>CTP ID</th><th>Cohort</th><th>Stage</th>
            <th>Track</th><th>What is missing</th><th>Certificate</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($block as $row)
            @php
                $e = $row['e'];
                $missing = $e->state === 'approved' && $row['pct'] < 100
                    ? collect($e->trackParts())->reject(fn ($p) => $p['done'])->pluck('label')->join(', ')
                    : '';
            @endphp
            <tr>
                <td><b>{{ $e->ctp->full_name }}</b>
                    <div class="hint">{{ collect([$e->ctp->ward, $e->ctp->county])->filter()->join(', ') }}</div></td>
                <td>{{ $e->ctp->ctp_code ?: '—' }}</td>
                <td>{{ $e->cohort->reference ?? '—' }}
                    <div class="hint">{{ $e->cohort->courseLabel() ?? 'Cohort removed' }}
                        @if ($e->cohort?->venue)<div class="hint">{{ $e->cohort->venue->name }} ({{ $e->cohort->venue->typeLabel() }})</div>@endif</div></td>
                <td><span class="status {{ $e->stage }}">{{ $e->stageLabel() }}</span>
                    @if ($e->state !== 'approved')<div class="hint">{{ $e->stateLabel() }}</div>@endif</td>
                <td style="min-width:150px;">
                    <div class="milestone" style="margin:0;">
                        <div class="ms-top"><b>{{ $row['pct'] }}%</b></div>
                        <div class="ms-bar"><i style="width: {{ min(100, $row['pct']) }}%"></i></div>
                    </div>
                </td>
                <td>@if ($missing !== ''){{ $missing }}
                    @elseif ($row['pct'] >= 100)<span class="hint">nothing — the track is whole</span>
                    @else <span class="hint">—</span>@endif</td>
                <td>@if ($e->certification)
                        <span class="status {{ $e->certification->status === 'approved' ? 'active' : ($e->certification->status === 'recommended' ? 'pending_review' : 'closed') }}">{{ $e->certification->statusLabel() }}</span>
                        <div class="hint">{{ $e->certification->certificate_code ?: 'no code until an executive signs' }}</div>
                    @else<span class="hint">not put forward</span>@endif</td>
                <td class="actions">
                    <a class="btn btn-sm btn-outline" href="{{ route('admin.track.show', $e) }}">Open track</a>
                    <a class="btn btn-sm btn-ghost" href="{{ route('admin.students.show', $e->ctp) }}">Student record</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty-cell">
                Nobody is on a training seat yet. Seats come from a cohort — <b>Cohorts</b> → give a CTP a seat,
                or a CTP applies themselves from <b>My training</b>.
            </td></tr>
        @endforelse
    </tbody>
</table>
@empty
<table class="data">
    <tbody>
        <tr><td class="empty-cell">
            Nobody is on a training seat yet. Seats come from a cohort — <b>Cohorts</b> → give a CTP a seat,
            or a CTP applies themselves from <b>My training</b>.
        </td></tr>
    </tbody>
</table>
@endforelse

<p class="hint" style="margin-top:14px;">
    A track is worked out from what has actually been recorded on the seat — an application that has not been
    given a seat scores nothing. Marks and uploads must fall inside the period the office set for the cohort.
</p>
@endsection

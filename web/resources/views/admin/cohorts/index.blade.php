@extends('layouts.admin')
@section('title', 'Cohorts')

@php
    use App\Models\Cohort;
    $tabs = ['all' => 'All'] + Cohort::STATUSES;
    $openCount = $counts['open'] ?? 0;
@endphp

@section('content')
<h1>Cohorts</h1>
<p class="page-lede">All training intakes and where each one stands.</p>

<div class="stat-row">
    <div class="stat"><small>Open for applications</small><b>{{ $openCount }}</b></div>
    <div class="stat"><small>In training</small><b>{{ $counts['in_progress'] ?? 0 }}</b></div>
    <div class="stat"><small>Seats awaiting you</small><b>{{ $pendingSeats }}</b></div>
    <div class="stat"><small>Cohorts</small><b>{{ $counts->sum() }}</b></div>
</div>

<form method="GET" class="filter-bar">
    <button class="btn btn-sm btn-primary" type="button" data-sheet="post-training">Post a training</button>
    <a class="btn btn-sm btn-outline" href="{{ route('admin.cohorts.results') }}">Cohort results</a>
    @if ($year)
        <input type="hidden" name="year" value="{{ $year }}">
    @endif
    <label class="field">
        <span>Cohort status</span>
        <select name="status" onchange="this.form.submit()">
            @foreach ($tabs as $s => $lbl)
                <option value="{{ $s }}" @selected($status === $s)>{{ $lbl }}@if ($s !== 'all') ({{ $counts[$s] ?? 0 }})@endif</option>
            @endforeach
        </select>
    </label>
    <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
    <span class="hint" style="margin-left:8px;">A cohort starts as a draft — only an open one reaches the CTP portal.</span>
</form>

@if ($years->count() > 1 || $year)
    <div class="tabs year-tabs">
        <a class="tab {{ $year ? '' : 'on' }}" href="{{ route('admin.cohorts.index', ['status' => $status]) }}">Every year</a>
        @foreach ($years as $y)
            <a class="tab {{ $year === (int) $y ? 'on' : '' }}"
               href="{{ route('admin.cohorts.index', ['status' => $status, 'year' => $y]) }}">{{ $y }}</a>
        @endforeach
    </div>
@endif

<table class="data">
    <thead>
        <tr>
            <th>Ref</th><th>Course</th><th>Period</th><th>Where</th><th>Trainer</th><th>When</th>
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
                <td><span class="tag">{{ $c->periodLabel() }}</span></td>
                <td>{{ $c->venue?->name ?: ($c->trainer ? 'Trainer site' : 'Community site') }}
                    <div class="hint">{{ collect([$c->ward, $c->sub_county, $c->county])->filter()->join(', ') }}</div></td>
                <td>{{ $c->trainer?->personName() ?: '—' }}
                    @if ($c->venue)<div class="hint">{{ $c->venue->typeLabel() }}</div>@endif</td>
                <td>{{ $c->dateLabel() }}<div class="hint">KSh {{ number_format((float) $c->fee_ksh) }}</div></td>
                <td>{{ $approved + $pending }} / {{ $c->seats }}</td>
                <td>
                    {{ $approved }} training
                    @if ($pending)<div class="hint"><b>{{ $pending }}</b> seat{{ $pending === 1 ? '' : 's' }} awaiting you</div>@endif
                </td>
                <td><span class="status {{ $c->status }}">{{ $c->statusLabel() }}</span>
                    @if ($c->status === 'open' && $c->isFull())<div class="hint">full</div>@endif</td>
                <td class="actions"><a class="btn btn-sm btn-outline" href="{{ route('admin.cohorts.show', $c) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty-cell">
                No cohorts in this view yet. Post a training, then open it for CTPs to apply.
            </td></tr>
        @endforelse
    </tbody>
</table>

@include('partials.sheet-start', ['name' => 'post-training', 'title' => 'Post a training',
    'sub' => 'Set the training, venue, trainer and seats for this intake.'])
    <form method="POST" action="{{ route('admin.cohorts.store') }}">
        @csrf
        <input type="hidden" name="_sheet" value="post-training">
        @include('admin.cohorts.fields', [
            'cohort' => new \App\Models\Cohort(['theory_hours' => 40, 'practical_hours' => 60, 'seats' => 25]),
            'editing' => false,
        ])
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-primary" type="submit">Create as draft</button>
        </div>
    </form>
@include('partials.sheet-end')
@endsection

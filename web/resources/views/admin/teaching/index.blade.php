@extends('layouts.admin')
@section('title', 'My teaching')

@php
    $held = $cohorts->sum(fn ($c) => $c->enrollments->where('state', 'approved')->count());
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>My teaching</h1>
        @php $first = $trainers->first(); @endphp
        <p class="ah-meta">
            <span><b>{{ $first?->personName() ?? auth()->user()->name }}</b></span>
            <span>{{ $first?->holderLabel() }}</span>
            <span>{{ $first?->holderCode() ?: 'no ID yet' }}</span>
            <span>{{ $trainers->count() }} {{ Str::plural('approved site', $trainers->count()) }}</span>
            <span>{{ $cohorts->count() }} {{ Str::plural('cohort', $cohorts->count()) }}</span>
        </p>
    </div>
    <div>
        <a class="btn btn-sm btn-outline" href="{{ route('admin.mytime.index') }}">My time log</a>
    </div>
</div>

@if ($trainers->isEmpty())
    <div class="card">
        <h3>No approved site yet</h3>
        <p class="help">The programme office approves a trainer site by site. Until a site is approved under your name,
            no cohort is handed over and nothing can be recorded. The office can approve you at
            <b>Trainers</b> inside Training.</p>
    </div>
@endif

@foreach ($trainers as $t)
    <div class="card">
        <div class="section-head">
            <h3>{{ $t->venue->name ?? $t->placeLabel() }}</h3>
            <span class="tag">{{ $t->teachingLabel() ?: 'No training listed' }}</span>
        </div>
        <p class="hint">{{ $t->placeLabel() }}@if ($t->weekly_hours) · {{ $t->weekly_hours }} h a week @endif</p>

        @forelse ($t->cohorts as $c)
            <h4 class="dl-group">{{ $c->reference }} — {{ $c->courseLabel() }} · {{ $c->dateLabel() }}
                @if ($c->status !== 'draft')
                    <a class="btn btn-sm btn-lime" style="margin-left:10px"
                       href="{{ route('admin.teaching.register', ['cohort' => $c->id]) }}">Class papers</a>
                @endif
            </h4>
            <div class="table-scroll">
                <table class="data">
                    <thead>
                        <tr>
                            <th>CTP</th><th>Seat</th><th>Track</th><th>Modules</th><th>Records</th><th>Documents</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($c->enrollments as $e)
                            <tr>
                                <td><b>{{ $e->ctp->full_name }}</b> <span class="hint">{{ $e->ctp->ctp_code }}</span></td>
                                <td><span class="status {{ $e->state === 'approved' ? 'active' : 'pending_review' }}">{{ $e->stateLabel() }}</span></td>
                                <td>{{ $e->trackPct() }}%</td>
                                <td>{{ $e->moduleMarks->count() }}{{ $c->training?->modules?->count() ? ' / '.$c->training->modules->count() : '' }}</td>
                                <td>{{ $e->entries->count() }}</td>
                                <td>{{ $e->documents->count() }}</td>
                                <td><a class="btn btn-sm btn-outline" href="{{ route('admin.teaching.trainee', $e) }}">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-cell">No CTP holds a seat in this cohort yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @empty
            <p class="hint">This site has no cohort running on it yet.</p>
        @endforelse
    </div>
@endforeach

@if ($held > 0)
    <p class="hint">Everything you record here lands on the CTP’s own training record and on the office’s track page —
        the same lines, the same moment. {{ $held }} {{ Str::plural('held seat', $held) }} open to you.</p>
@endif
@endsection

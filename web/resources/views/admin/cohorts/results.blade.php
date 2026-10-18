@extends('layouts.admin')
@section('title', 'Cohort results')

@php
    $open = fn ($e) => route('admin.track.show', $e);
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.cohorts.index') }}">&larr; Cohorts</a></p>
<h1>Cohort results</h1>
<p class="sub page-lede">
    Choose a cohort and read every trainee in it on one table: attendance, the modules they got through, their
    theory, practical and assessment scores, the track they have reached and where their certificate stands.
    <b>The numbers come from the trainee's own record</b> — nothing is typed twice, and the trainer sees this same table
    for the cohorts they teach.
</p>

@if ($cohorts->isEmpty())
    <div class="card">
        <h3>There is no cohort to read yet</h3>
        <p class="help">Results appear once a cohort is posted and a CTP holds a seat in it.
            <a href="{{ route('admin.cohorts.create') }}">Post a training</a> to start one.</p>
    </div>
@else
    <form method="GET" action="{{ route('admin.cohorts.results') }}" class="filter-bar">
        <div class="form-grid-2">
            <div>
                <label for="cohort">Cohort</label>
                <select name="cohort" id="cohort">
                    @foreach ($cohorts->groupBy(fn ($c) => $c->yearLabel() ?: 'year not set') as $year => $yearRuns)
                        <optgroup label="{{ $year }}">
                            @foreach ($yearRuns as $c)
                                <option value="{{ $c->id }}" @selected($cohort && $cohort->id === $c->id)>
                                    {{ $c->termLabel() ?: 'no term' }} · {{ $c->reference }} — {{ $c->courseLabel() }}
                                    ({{ $c->enrollments->where('state', 'approved')->count() }} seated)</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div style="align-self:end;">
                <button class="btn btn-sm btn-primary" type="submit">Show results</button>
            </div>
        </div>
    </form>

    @if ($cohort)
        <div class="card">
            <div class="section-head">
                <h3>{{ $cohort->courseLabel() }}</h3>
                <span class="tag">{{ $cohort->training?->name }}</span>
                <span class="tag gold">{{ $cohort->reference }}</span>
                <span class="tag">{{ $cohort->periodLabel() }}</span>
                <span class="status {{ $cohort->status }}">{{ $cohort->statusLabel() }}</span>
            </div>
            <p class="hint">
                {{ $cohort->dateLabel() }} · {{ $cohort->hoursLabel() }} · {{ $cohort->placeLabel() }} ·
                trainer {{ $cohort->trainer?->fundi?->full_name ?: 'not assigned' }}
            </p>
            <p style="margin:10px 0 0;">
                <a class="btn btn-sm btn-outline" href="{{ route('admin.cohorts.show', $cohort) }}">Open the cohort</a>
                <a class="btn btn-sm btn-ghost" href="{{ route('admin.certification.index') }}">Certification queue</a>
            </p>
        </div>

        <div class="card">
            <h4 class="dl-group">Results — {{ $cohort->reference }} ({{ $cohort->enrollments->count() }} on the roll)</h4>
            @include('partials.results', [
                'enrollments' => $enrollments,
                'open' => $open,
                'whoMarks' => 'The trainer at the site and the office both write these lines, so a trainee has one record, not two.',
            ])
        </div>

        @include('partials.runs', [
            'runs' => $runs,
            'current' => $cohort,
            'title' => 'This course across the years',
            'url' => fn ($r) => route('admin.cohorts.results', ['cohort' => $r->id]),
            'note' => 'Choose another run in the box above to read its results. Each term and each year keeps its own '
                .'roll and its own marks — nothing from an earlier run is rewritten by a later one.',
        ])
    @endif
@endif
@endsection

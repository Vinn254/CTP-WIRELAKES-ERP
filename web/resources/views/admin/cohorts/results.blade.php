@extends('layouts.admin')
@section('title', 'Cohort results')

@php
    $open = fn ($e) => route('admin.track.show', $e);
    // Clicking a programme narrows the cohort box to the intakes that course has actually run.
    $intakes = $programme ? $programmeRuns : $cohorts;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.cohorts.index') }}">&larr; Cohorts</a></p>
<h1>Cohort results</h1>
<p class="page-lede">Every trainee's marks and progress, by cohort or by programme.</p>

@if ($cohorts->isEmpty())
    <div class="card">
        <h3>There is no cohort to read yet</h3>
        <p class="help">Results appear once a cohort is posted and a CTP holds a seat in it.
            <a href="{{ route('admin.cohorts.create') }}">Post a training</a> to start one.</p>
    </div>
@else
    <div class="card">
        <div class="section-head">
            <h3>Programmes</h3>
            <span class="hint">{{ $programmes->count() }} course{{ $programmes->count() === 1 ? '' : 's' }} taught on these intakes</span>
        </div>
        <div class="chips runs-chips">
            <a class="chip {{ $programme ? '' : 'on' }}" href="{{ route('admin.cohorts.results') }}">All programmes</a>
            @foreach ($programmes as $p)
                <a class="chip {{ $programme && $programme->id === $p['training']->id ? 'on' : '' }}"
                   href="{{ route('admin.cohorts.results', ['training' => $p['training']->id]) }}"
                   title="{{ $p['training']->code }}">{{ $p['training']->name }} — {{ $p['runs'] }}
                    intake{{ $p['runs'] === 1 ? '' : 's' }}, {{ $p['seats'] }} seated</a>
            @endforeach
        </div>
        @if ($programme)
            <p class="hint">Reading {{ $programme->name }} across {{ $programmeRuns->count() }}
                intake{{ $programmeRuns->count() === 1 ? '' : 's' }} — {{ $enrollments->count() }}
                enrolled, {{ $enrollments->where('state', 'approved')->count() }} holding a seat.</p>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.cohorts.results') }}" class="filter-bar">
        @if ($programme)<input type="hidden" name="training" value="{{ $programme->id }}">@endif
        <div class="form-grid-2">
            <div>
                <label for="cohort">{{ $programme ? 'Intake on this programme' : 'Cohort' }}</label>
                <select name="cohort" id="cohort">
                    @foreach ($intakes->groupBy(fn ($c) => $c->yearLabel() ?: 'year not set') as $year => $yearRuns)
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
                trainer {{ $cohort->trainer?->personName() ?: 'not assigned' }}
            </p>
            <p style="margin:10px 0 0;">
                <a class="btn btn-sm btn-outline" href="{{ route('admin.cohorts.show', $cohort) }}">Open the cohort</a>
                <a class="btn btn-sm btn-ghost" href="{{ route('admin.certification.index') }}">Certification queue</a>
            </p>
        </div>

        <div class="card">
            <h4 class="dl-group">
                @if ($programme)
                    Results — {{ $programme->name }} across {{ $programmeRuns->count() }}
                    intake{{ $programmeRuns->count() === 1 ? '' : 's' }}
                    ({{ $enrollments->where('state', 'approved')->count() }} seated)
                @else
                    Results — {{ $cohort->reference }} ({{ $cohort->enrollments->count() }} on the roll)
                @endif
            </h4>
            @include('partials.results', [
                'enrollments' => $enrollments,
                'open' => $open,
                'canCorrect' => $canCorrect,
                'studentRecord' => true,
                'intake' => (bool) $programme,
                'whoMarks' => $programme
                    ? 'Every intake that ran the course is on this table, so a trainee appears once for each intake they were seated on.'
                    : 'The trainer at the site and the office both write these lines, so a trainee has one record, not two.',
            ])
        </div>

        <div class="card">
            <h4 class="dl-group">
                @if ($programme)
                    Grading and marks by unit — {{ $programme->name }} (every intake)
                @else
                    Grading and marks by unit — {{ $cohort->reference }}
                @endif
            </h4>
            <p class="hint">
                @if ($programme)
                    Every unit the course teaches, the marks its sheets carry on average over all the intakes that ran
                    it, and what they come to weighed on the grading the Academic Registrar set — computed here, intake
                    after intake. Edit changes the weights and the pass mark for the unit; Marks opens the sheet of the
                    intake picked above so a mark a lecturer did not send in can be typed in here.
                @else
                    Every unit taught on this cohort, the marks its sheets carry on average, and what they come to
                    weighed on the grading the Academic Registrar set. This is where the grading is set: Edit changes the
                    weights and the pass mark, Marks opens the sheet so a mark a lecturer did not send in can be typed in
                    here.
                @endif
            </p>
            @include('partials.unit-marks', [
                'board' => $board,
                'cohort' => $cohort,
                'term' => $cohort->periodLabel() ?: '—',
                'canCorrect' => $canCorrect,
                'marksUrl' => fn ($unit) => route('admin.teaching.marks', [$cohort, 'training_module_id' => $unit->id]),
            ])
        </div>

        @include('partials.runs', [
            'runs' => $runs,
            'current' => $cohort,
            'title' => $programme ? $programme->name.' across the years' : 'This course across the years',
            'url' => fn ($r) => route('admin.cohorts.results', $programme
                ? ['training' => $programme->id, 'cohort' => $r->id]
                : ['cohort' => $r->id]),
            'note' => 'Choose another run in the box above to read its results. Each term and each year keeps its own '
                .'roll and its own marks — nothing from an earlier run is rewritten by a later one.',
        ])
    @endif
@endif
@endsection

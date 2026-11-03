@extends('layouts.admin')
@section('title', 'Programme timetable — '.$training->name)

@section('content')
<div class="head-row">
    <div>
        <h1>{{ $training->name }} — the week</h1>
        <p class="ah-meta">
            <span>{{ $units->count() }} {{ Str::plural('unit', $units->count()) }} on this programme</span>
            <span>{{ $cohorts->count() }} {{ Str::plural('intake', $cohorts->count()) }} running it</span>
            @if ($week)<span>Set {{ $week->updated_at?->format('d M Y') }}</span>@endif
        </p>
    </div>
    <div class="head-actions">
        <a class="btn btn-sm btn-outline" href="{{ route('admin.programme_timetables.index') }}">Every programme</a>
    </div>
</div>

@include('partials.errors')

@if ($cohorts->isNotEmpty())
    <div class="chips runs-chips">
        @foreach ($cohorts as $c)
            <a class="chip" href="{{ route('admin.cohorts.show', $c->id) }}">{{ $c->reference }}</a>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="section-head">
        <h3>Classes the programme keeps to</h3>
        @if ($week)<span class="status {{ $week->statusClass() }}">{{ $week->statusLabel() }}</span>@endif
    </div>

    <form method="POST" action="{{ route('admin.programme_timetables.update', $training) }}" class="fee-form">
        @csrf

        <div class="record-head">
            <h4>The week</h4>
            <button class="btn btn-primary btn-sm" type="button" data-slot-add>+ Create a class</button>
        </div>

        @include('partials.week-table', ['grid' => $grid, 'days' => $days, 'kinds' => $kinds,
            'units' => $units, 'lecturers' => $lecturers,
            'footHint' => 'of class a week for this programme'])

        <div class="field">
            <label>Note on the week</label>
            <input type="text" name="note" maxlength="500" placeholder="Anything the trainers should read"
                   value="{{ old('note', $week?->note) }}">
        </div>

        <div class="module-foot">
            <button class="btn btn-outline btn-sm" type="button" data-slot-add>+ Create a class</button>
            <button class="btn btn-lime btn-sm" type="submit">Save the week</button>
        </div>

        @unless ($lecturers->isEmpty())
            <p class="hint">Put the lecturer a class belongs to beside it. The week reaches that person's own
                Timetable page and every student on an intake of this programme, whether or not a cohort has been
                booked under them yet.</p>
        @else
            <p class="hint">No trainer is registered for this programme yet, so the Lecturer column is empty. Approve
                one under <a href="{{ route('admin.trainers.index') }}">Trainers</a> for this programme and they are
                offered here.</p>
        @endunless

        <p class="hint">Saving publishes the week to every trainer on this programme at once, and replaces it whole —
            a class you take off here does not stay behind the rest. Only the units of {{ $training->name }} are
            offered; a unit is added under <a href="{{ route('admin.trainings.index') }}">Training</a> first.</p>
    </form>
</div>
@endsection

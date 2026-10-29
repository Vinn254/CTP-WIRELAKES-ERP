@extends('layouts.admin')
@section('title', 'Teaching papers')

@php
    // What one line of the queue reads as, so the four papers are listed in the same shape.
    $said = function (string $kind, $paper): array {
        $cohort = $paper->cohort;

        return match ($kind) {
            'register' => [
                'Attendance — ' . $paper->dateLabel(),
                $paper->kindLabel() . ' · ' . $paper->presentCount() . ' of ' . $paper->marks()->count() . ' in',
            ],
            'marks' => [
                'Marks — ' . $paper->unitLabel(),
                $paper->dateLabel() . ' · ' . $paper->marks()->count() . ' CTPs marked',
            ],
            'timetable' => [
                'Timetable — ' . ($cohort?->reference ?? 'a cohort'),
                $paper->slots()->count() . ' classes a week',
            ],
            default => [
                'Lesson plan — ' . ($cohort?->reference ?? 'a cohort'),
                $paper->lessonCount() . ' ' . \Illuminate\Support\Str::plural('lesson', $paper->lessonCount()),
            ],
        };
    };
@endphp

@section('content')
<h1>Teaching papers</h1>
<p class="page-lede">Registers, mark sheets, timetables and lesson plans trainers have handed in, waiting to be
    approved or sent back.</p>

<div class="stat-row">
    <div class="stat"><small>Waiting on you</small><b>{{ $waiting }}</b></div>
    @foreach ($tabs as $tab)
        <div class="stat"><small>{{ $tab['label'] }}</small><b>{{ $tab['count'] }}</b></div>
    @endforeach
</div>

<div class="tabs">
    <a class="tab {{ $kind ? '' : 'on' }}" href="{{ route('admin.registers.index') }}">All papers</a>
    @foreach ($tabs as $tab)
        <a class="tab {{ $kind === $tab['key'] ? 'on' : '' }}"
           href="{{ route('admin.registers.index', ['kind' => $tab['key']]) }}">
            {{ $tab['label'] }} <span class="n">{{ $tab['count'] }}</span></a>
    @endforeach
</div>

@forelse ($rows as $row)
    @php [$head, $sub] = $said($row['kind'], $row['paper']); $cohort = $row['paper']->cohort; @endphp
    <div class="card">
        <div class="section-head">
            <h3>{{ $head }}</h3>
            <span class="tag">{{ $cohort?->reference }}</span>
            <span class="tag">{{ $cohort?->courseLabel() }}</span>
            <span class="status {{ $row['paper']->statusClass() }}">{{ $row['paper']->statusLabel() }}</span>
        </div>
        <p class="hint">{{ $sub }} · from {{ $cohort?->venue?->name ?: 'no venue named' }}
            · {{ $cohort?->trainer?->personName() ?: 'the office' }}
            · handed in {{ $row['paper']->submitted_at?->diffForHumans() }}</p>
        <div class="head-acts">
            <a class="btn btn-sm btn-primary"
               href="{{ route('admin.registers.show', ['kind' => $row['kind'], 'id' => $row['paper']->id]) }}">
                Read and answer</a>
        </div>
    </div>
@empty
    <div class="card">
        <h3>Nothing is waiting</h3>
        <p class="help">Every paper handed in by a trainer appears here. A paper only reaches a CTP’s record when it
            is approved on this desk.</p>
    </div>
@endforelse

<p class="hint">Approving writes the paper onto every trainee it names; sending it back leaves their records
    untouched and returns the paper to the trainer to fix.</p>
@endsection

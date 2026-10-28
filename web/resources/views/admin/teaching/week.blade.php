@extends('layouts.admin')
@section('title', 'My week sheet')

@php
    use App\Models\TimeLog;

    $weekOf = $sheet['start']->toDateString();
    $self = route('admin.teaching.week', ['trainer' => $trainer->id, 'week' => $weekOf]);
    $link = fn ($w) => route('admin.teaching.week', ['trainer' => $trainer->id, 'week' => $w]);
    $inWeek = today()->between($sheet['start'], $sheet['start']->copy()->addDays(6));
    $defaultDay = request('day') ?: old('log_date')
        ?: ($inWeek ? today()->toDateString() : $sheet['start']->copy()->addDays(4)->toDateString());
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>My week sheet</h1>
        <p class="ah-meta">
            <span><b>{{ $sheet['label'] }}</b></span>
            <span>{{ $trainer->personName() }}</span>
            <span>{{ $trainer->placeLabel() ?: 'no site named' }}</span>
            <span>{{ number_format($sheet['hours'], 2) }} hours</span>
            <span>{{ (int) $sheet['units'] }} units</span>
        </p>
    </div>
    <div>
        <a class="btn btn-sm btn-outline" href="{{ $link($back) }}">Previous week</a>
        <a class="btn btn-sm btn-outline" href="{{ $link($today) }}">This week</a>
        <a class="btn btn-sm btn-outline" href="{{ $link($onward) }}">Next week</a>
        <button class="btn btn-sm btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

@if ($postings->count() > 1)
    <div class="tabs">
        @foreach ($postings as $p)
            <a class="tab {{ $p->id === $trainer->id ? 'on' : '' }}"
               href="{{ route('admin.teaching.week', ['trainer' => $p->id, 'week' => $weekOf]) }}">{{ $p->placeLabel() ?: 'A site' }}</a>
        @endforeach
    </div>
@endif

@if ($sheet['status'] === 'returned')
    <p class="flash-error" style="margin:0 0 18px"><b>The Training desk sent this week back.</b>
        {{ $sheet['logs']->pluck('approval_note')->filter()->first() }}</p>
@endif

@include('admin.timelogs.sheet', [
    'sheet' => $sheet,
    'editing' => $canEdit,
    'editLink' => fn ($date) => $link($weekOf).'?day='.$date,
])

@if ($canEdit)
    @include('partials.week-day', [
        'action' => route('admin.teaching.day'),
        'self' => $self,
        'defaultDay' => $defaultDay,
        'trainings' => $trainings,
        'cohorts' => $cohorts,
    ])
@else
    <p class="hint">This week is {{ TimeLog::STATUSES[$sheet['status']] }} — the sheet is closed until the Training
        desk answers it. They return it with a note if a day must be corrected.</p>
@endif

<div class="card">
    <h3>Sign the week</h3>
    @if ($sheet['logs']->isEmpty())
        <p class="hint">Nothing to sign yet. Write at least one day above.</p>
    @elseif (! $canEdit)
        <p class="hint">{{ TimeLog::STATUSES[$sheet['status']] }}.</p>
    @else
        <p class="hint">{{ $sheet['logs']->count() }} {{ Str::plural('day', $sheet['logs']->count()) }} on this sheet,
            {{ number_format($sheet['hours'], 2) }} hours, {{ (int) $sheet['units'] }} units. Signing sends the whole
            week to the Training desk and locks the days.</p>
        <form method="POST" action="{{ route('admin.teaching.sign') }}"
              data-confirm="Sign the week of {{ $sheet['label'] }} and send it to the Training desk?" data-title="Sign week">
            @csrf
            <input type="hidden" name="trainer" value="{{ $trainer->id }}">
            <input type="hidden" name="week" value="{{ $weekOf }}">
            <button class="btn btn-primary" type="submit">Sign and send to the office</button>
        </form>
    @endif
</div>

@if ($history->isNotEmpty())
    <div class="card">
        <h3>Weeks you have signed</h3>
        <div class="table-scroll">
            <table class="data">
                <thead>
                    <tr><th>Week</th><th class="num">Days</th><th class="num">Hours</th><th class="num">Units</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($history as $past)
                        <tr>
                            <td><a href="{{ $link($past['start']->toDateString()) }}">{{ $past['label'] }}</a></td>
                            <td class="num">{{ $past['days'] }}</td>
                            <td class="num">{{ number_format((float) $past['hours'], 2) }}</td>
                            <td class="num">{{ (int) $past['units'] }}</td>
                            <td><span class="status {{ ['draft' => 'closed', 'submitted' => 'pending_review',
                                    'approved' => 'active', 'returned' => 'suspended'][$past['status']] ?? 'closed' }}">
                                    {{ TimeLog::STATUSES[$past['status']] ?? $past['status'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

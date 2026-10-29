@extends('layouts.member')
@section('title', 'My class')

@section('content')
<div class="head-row">
    <div>
        <h1>My class</h1>
        <p class="ah-meta">
            <span>{{ $seats->count() }} {{ Str::plural('class', $seats->count()) }} on your roll</span>
        </p>
    </div>
</div>

@if ($seats->isEmpty())
    <div class="card">
        <h3>No class yet</h3>
        <p class="help">The office has not put you on a cohort's roll yet. When a seat is approved, the week of
            classes, the units and the material the trainer hands out appear here.</p>
        <p class="hint"><a href="{{ route('portal.training') }}">My training</a> lists the trainings open for you to apply to.</p>
    </div>
@else
    @foreach ($seats as $seat)
        @php $c = $seat->cohort; @endphp
        <div class="card">
            <div class="section-head">
                <h3>{{ $c?->reference }} — {{ $c?->courseLabel() }}</h3>
                <span class="tag">{{ $c?->periodLabel() }}</span>
            </div>
            <p class="hint">{{ $c?->venue?->name ?: 'No venue set' }} · {{ $c?->dateLabel() }}</p>
            <div class="head-acts" style="margin-bottom:14px;">
                <a class="btn btn-sm btn-lime" href="{{ route('portal.class.cohort', $c?->id) }}">Open the class</a>
                <span class="hint">The week of classes, the units you do and the material handed out.</span>
            </div>
        </div>
    @endforeach
@endif
@endsection

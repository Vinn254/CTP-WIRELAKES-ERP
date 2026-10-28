@extends('layouts.member')
@section('title', 'My time log')

@php
    $trainer = $trainer ?? null;
@endphp

@section('content')
    @if ($postings->count() > 1)
        <div class="tabs">
            @foreach ($postings as $p)
                <a class="tab {{ $p->id === $trainer->id ? 'on' : '' }}"
                   href="{{ route('portal.trainer.week', ['trainer' => $p->id, 'month' => $sheet['month'], 'week' => $sheet['week_no']]) }}">
                    {{ $p->placeLabel() ?: 'A site' }}</a>
            @endforeach
        </div>
    @endif

    @include('partials.week-desk', [
        'heading' => 'My time log',
        'self' => route('portal.trainer.week'),
        'dayAction' => route('portal.trainer.week.day'),
        'signAction' => route('portal.trainer.week.sign'),
        'carry' => ['trainer' => $trainer->id],
        'returnedTo' => 'the office',
    ])
@endsection

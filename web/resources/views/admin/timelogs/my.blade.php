@extends('layouts.admin')
@section('title', 'My time log')

@section('content')
    @include('partials.week-desk', [
        'heading' => 'My time log',
        'self' => route('admin.mytime.index'),
        'dayAction' => route('admin.mytime.day'),
        'weekAction' => route('admin.mytime.week'),
        'paper' => fn ($week) => route('admin.mytime.sheet', ['week' => $week]),
        'carry' => [],
        'returnedTo' => 'Human Resources',
    ])
@endsection

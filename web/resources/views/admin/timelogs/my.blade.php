@extends('layouts.admin')
@section('title', 'My time log')

@section('content')
    @include('partials.week-desk', [
        'heading' => 'My time log',
        'self' => route('admin.mytime.index'),
        'dayAction' => route('admin.mytime.day'),
        'signAction' => route('admin.mytime.sign'),
        'carry' => [],
        'returnedTo' => 'Human Resources',
    ])
@endsection

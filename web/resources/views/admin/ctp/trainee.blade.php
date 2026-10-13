@extends('layouts.member')
@section('title', 'Trainee interface — '.$member->full_name)

@section('content')
@include('portal.partials.trainee')
@endsection

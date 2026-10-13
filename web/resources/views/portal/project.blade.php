@extends('layouts.member')
@section('title', $project->title)

@php
    $backUrl = route('portal.projects');
@endphp

@section('content')
@include('portal.partials.project-detail')
@endsection

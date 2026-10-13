@extends('layouts.member')
@section('title', $project->title)

@php
    $backUrl = $member instanceof App\Models\CtpRegistration
        ? route('portal.dashboard', ['tab' => 'projects'])
        : route('portal.dashboard');
    $isPreview = false;
@endphp

@section('content')
@include('portal.partials.project-detail')
@endsection

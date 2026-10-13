@extends('layouts.member')
@section('title', $project->title)

@php
    $backUrl = $previewBase;
    $isPreview = true;
@endphp

@section('content')
@include('portal.partials.project-detail')
@endsection

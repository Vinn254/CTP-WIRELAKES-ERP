@extends('layouts.admin')
@section('title', $isNew ? 'New technology project' : $project->reference)

@php
    use App\Support\Technology;

    $action = $isNew ? route('admin.technology.project.store') : route('admin.technology.project.update', $project);
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.technology.index', ['tab' => 'projects']) }}">&larr; Technology projects</a></p>
<h1>{{ $isNew ? 'New technology project' : $project->title }}</h1>
<p class="page-lede">Record a technology build and its delivery stages.</p>

@if ($errors->any())
    <div class="card" style="border-left:4px solid var(--danger);">
        <ul style="margin:0;padding-left:18px;">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

@if (! $isNew)
    <p class="hint" style="margin-bottom:12px;">{{ $project->reference }} · recorded {{ $project->created_at?->format('d M Y') }}</p>
@endif

<div class="card">
    <form method="POST" action="{{ $action }}" class="fee-form" data-title="{{ $isNew ? 'Add project' : 'Update project' }}">
        @csrf
        @include('admin.technology.project-fields', [
            'project' => $project,
            'categories' => $categories,
            'fundis' => $fundis,
            'communities' => $communities,
            'projects' => $projects,
        ])
        <button class="btn btn-sm btn-primary" type="submit">{{ $isNew ? 'Record the project' : 'Save changes' }}</button>
        <a class="btn btn-sm btn-ghost" href="{{ route('admin.technology.index', ['tab' => 'projects']) }}">Cancel</a>
    </form>
</div>
@endsection

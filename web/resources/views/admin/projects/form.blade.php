@extends('layouts.admin')
@section('title', $project->exists ? 'Edit project' : 'Post a project')

@php
    use App\Models\Project;
    $editing = $project->exists;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.projects.index') }}">&larr; Projects</a></p>
<h1>{{ $editing ? 'Edit '.$project->reference : 'Post a new project' }}</h1>
<p class="page-lede">Set the project details and who can apply.</p>

@include('partials.errors')

<div class="card">
 <form method="POST" action="{{ $editing ? route('admin.projects.update', $project) : route('admin.projects.store') }}">
 @csrf
 @if ($editing) @method('PATCH') @endif

 @include('admin.projects.fields', [
 'project' => $project,
 'editing' => $editing,
 'counties' => $counties,
 'hirers' => $hirers,
 'communities' => $communities,
 ])

 <div class="save-bar">
 <button class="btn btn-primary btn-lg" type="submit">{{ $editing ? 'Save changes' : 'Post project' }}</button>
 @if ($editing)
 <a class="btn btn-ghost btn-lg" href="{{ route('admin.projects.show', $project) }}">Back to project</a>
 @endif
 </div>
 </form>
</div>
@endsection

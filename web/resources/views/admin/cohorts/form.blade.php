@extends('layouts.admin')
@section('title', $title)

@php $editing = $cohort->exists; @endphp

@section('content')
<p class="back"><a href="{{ $editing ? route('admin.cohorts.show', $cohort) : route('admin.cohorts.index') }}">&larr; {{ $editing ? $cohort->reference : 'Cohorts' }}</a></p>
<h1>{{ $editing ? 'Edit '.$cohort->reference : 'Post a training' }}</h1>
<p class="page-lede">Set the training, venue, trainer and seats for this intake.</p>

<form method="POST" action="{{ $editing ? route('admin.cohorts.update', $cohort) : route('admin.cohorts.store') }}" class="card">
    @csrf
    @if ($editing) @method('PATCH') @endif

    @include('admin.cohorts.fields', ['cohort' => $cohort, 'editing' => $editing])

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Save cohort' : 'Create as draft' }}</button>
        <a class="btn btn-ghost" href="{{ $editing ? route('admin.cohorts.show', $cohort) : route('admin.cohorts.index') }}">Cancel</a>
    </div>
</form>
@endsection

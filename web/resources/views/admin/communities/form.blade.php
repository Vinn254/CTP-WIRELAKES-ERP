@extends('layouts.admin')
@section('title', $community->exists ? 'Edit community' : 'Add community')

@php $editing = $community->exists; @endphp

@section('content')
<p class="back"><a href="{{ $editing ? route('admin.communities.show', $community) : route('admin.communities.index') }}">&larr; {{ $editing ? $community->name : 'Community register' }}</a></p>
<h1>{{ $editing ? 'Edit '.$community->name : 'Add a community' }}</h1>
<p class="page-lede">Record a community's name, location levels and size.</p>

<form method="POST" action="{{ $editing ? route('admin.communities.update', $community) : route('admin.communities.store') }}" class="card">
    @csrf
    @if ($editing) @method('PATCH') @endif

    @include('admin.communities.fields')

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Save community' : 'Add community' }}</button>
        <a class="btn btn-ghost" href="{{ $editing ? route('admin.communities.show', $community) : route('admin.communities.index') }}">Cancel</a>
    </div>
</form>
@endsection

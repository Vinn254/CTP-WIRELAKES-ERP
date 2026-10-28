@extends('layouts.admin')
@section('title', 'Add an E-Fundi from the office')

@section('content')
<p class="back"><a href="{{ route('admin.fundis.index') }}">&larr; E-Fundi register</a></p>
<h1>Add an E-Fundi from the office</h1>
<p class="page-lede">Enter an E-Fundi application at the counter for someone offline.</p>

@include('partials.errors')

<form method="POST" action="{{ route('admin.fundis.store') }}" enctype="multipart/form-data" class="card">
    @csrf

    @include('admin.fundi.fields')

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Add E-Fundi application</button>
        <a class="btn btn-ghost" href="{{ route('admin.fundis.index') }}">Cancel</a>
    </div>
</form>
@endsection

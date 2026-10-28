@extends('layouts.admin')
@section('title', 'Add a CTP from the office')

@section('content')
<p class="back"><a href="{{ route('admin.promoters.index') }}">&larr; CTP profiles</a></p>
<h1>Add a CTP from the office</h1>
<p class="page-lede">Enter a CTP application at the counter for someone offline.</p>

@include('partials.errors')

<form method="POST" action="{{ route('admin.promoters.store') }}" enctype="multipart/form-data" class="card">
    @csrf

    @include('admin.ctp.fields')

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Add CTP application</button>
        <a class="btn btn-ghost" href="{{ route('admin.promoters.index') }}">Cancel</a>
    </div>
</form>
@endsection

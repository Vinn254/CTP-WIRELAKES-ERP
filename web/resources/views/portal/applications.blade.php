@extends('layouts.member')
@section('title', 'My applications')

@section('content')
<div class="page-intro">
    <p style="margin:0;">One worker is assigned per project. The programme office reviews every application and the
        outcome is shown here.</p>
    <a class="btn btn-sm btn-outline" href="{{ route('portal.projects') }}">Browse open projects</a>
</div>
@include('portal.partials.applications')
@endsection

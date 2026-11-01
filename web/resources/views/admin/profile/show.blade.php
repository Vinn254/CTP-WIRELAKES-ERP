@extends('layouts.admin')
@section('title', 'My profile')

@section('content')
<div class="head-row">
    <div>
        <h1>My profile</h1>
        <p class="page-lede">What {{ $office }} holds on you. Send a document in to be checked, or ask for a change —
            nothing on this page is written onto your record by itself.</p>
    </div>
</div>

@include('partials.profile-board')
@endsection

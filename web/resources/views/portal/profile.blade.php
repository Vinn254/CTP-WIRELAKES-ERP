@extends('layouts.member')
@section('title', 'My profile')

@section('content')
<div class="page-intro">
    <p class="sub">What the programme holds on you, the documents you send in to be checked, and the changes you ask
        for. Nothing here reaches your record until {{ $office }} approves it.</p>
</div>

@include('partials.profile-board', ['addPhoto' => true])
@endsection

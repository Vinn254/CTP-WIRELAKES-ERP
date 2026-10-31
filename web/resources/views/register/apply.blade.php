@extends('layouts.app')
@section('title', 'Apply to join')

@section('content')
<section class="section" style="max-width:900px;margin:34px auto;">
    <span class="tag">Application</span>
    <h1>Apply to join the programme</h1>
    <p class="sub">One application — pick which one you are.</p>

    <div class="grid-2">
        <div class="card">
            <h3>Student (CTP)</h3>
            <a class="btn btn-outline btn-block" href="{{ route('register.ctp') }}">Apply as a student</a>
        </div>
        <div class="card">
            <h3>Fundi / Trainer</h3>
            <a class="btn btn-lime btn-block" href="{{ route('register.fundi') }}">Apply</a>
        </div>
    </div>
</section>
@endsection

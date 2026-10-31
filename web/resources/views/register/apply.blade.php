@extends('layouts.app')
@section('title', 'Apply to join')

@section('content')
<section class="section" style="max-width:900px;margin:34px auto;">
    <span class="tag">Application</span>
    <h1>Apply to join the programme</h1>
    <p class="sub">One application for both. Say which of these you are and you are taken to that form — you only
        ever fill one of them.</p>

    <div class="grid-2">
        <div class="card">
            <h3>Student (CTP)</h3>
            <p class="help">You want to be trained and to mobilise your community. The short form: your name, National
                ID, phone, email, date of birth, where you live and your photo. The office records your track,
                education and papers when it reviews you.</p>
            <a class="btn btn-outline btn-block" href="{{ route('register.ctp') }}">Apply as a student</a>
        </div>
        <div class="card">
            <h3>E-Fundi</h3>
            <p class="help">You already have a trade and want project work, or you train students at a site. The full
                form: your grade, specialisations, the counties you serve, your photo and National ID scan.</p>
            <a class="btn btn-lime btn-block" href="{{ route('register.fundi') }}">Apply as an E-Fundi</a>
        </div>
    </div>

    <p class="help">You do not need both accounts. A student who gains a trade is moved up to an E-Fundi grade on the
        same record, and an E-Fundi who enrolls for training is read on that one file.</p>
</section>
@endsection

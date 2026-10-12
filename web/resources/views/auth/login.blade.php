@extends('layouts.app')
@section('title', 'Staff login')

@section('content')
<section class="section" style="max-width:440px;margin:60px auto;">
    <div class="card">
        <h1 style="font-size:1.4rem;">Programme staff login</h1>
        <p class="sub">Directors and Admins only. Applicants do not log in here — your application status is sent to you by email/SMS.</p>
        @include('partials.errors')
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div class="field">
                <label>Password <span class="req">*</span></label>
                <input type="password" name="password" required>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in</button>
        </form>
    </div>
</section>
@endsection

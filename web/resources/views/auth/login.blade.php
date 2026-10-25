@extends('layouts.app')
@section('title', 'Log in')

@section('content')
<section class="section login-section">
    <div class="card login-card">
        <span class="login-badge">🔒</span>
        <h1>Staff &amp; member login</h1>
        <p class="help">Programme staff reach the admin console. Approved CTPs and E-Fundis reach their project portal.</p>
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
            <button class="btn-signin" type="submit">Sign in <span class="arr">→</span></button>
        </form>
        <p class="back"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
        <p class="help">
            @if (config('account.otp.enabled'))
                Programme staff also type a code we email them, every time they sign in.
            @endif
            Anyone left idle for {{ $idleMinutes }} minutes is signed out.
        </p>
    </div>
</section>
@endsection

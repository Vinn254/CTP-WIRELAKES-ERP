@extends('layouts.app')
@section('title', 'Forgot password')

@section('content')
<section class="section login-section">
    <div class="card login-card">
        <span class="login-badge">🔑</span>
        <h1>Forgot your password?</h1>
        <p class="help">
            Give us the email you sign in with. We post a link that opens once and stops working after
            {{ $minutes }} minutes. One address can be sent {{ config('account.reset.per_day') }} links a day.
        </p>
        @include('partials.errors')
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <button class="btn-signin" type="submit">Email me a reset link <span class="arr">→</span></button>
        </form>
        <p class="back"><a href="{{ route('login') }}">← Back to login</a></p>
    </div>
</section>
@endsection

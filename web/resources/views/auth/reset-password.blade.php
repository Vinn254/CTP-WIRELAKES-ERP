@extends('layouts.app')
@section('title', 'Choose a new password')

@section('content')
<section class="section login-section">
    <div class="card login-card">
        <span class="login-badge">🔑</span>
        <h1>Choose a new password</h1>
        <p class="help">
            This link has <strong>{{ $minutes }} minutes</strong> left and opens the door once.
            Sign in afterwards with {{ $email }} — an office account still confirms itself by email.
        </p>
        @include('partials.errors')
        <form method="POST" action="{{ route('password.update', $token) }}">
            @csrf
            <div class="field">
                <label>New password <span class="req">*</span></label>
                <input type="password" name="password" required autofocus autocomplete="new-password">
            </div>
            <div class="field">
                <label>Confirm the new password <span class="req">*</span></label>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn-signin" type="submit">Change my password <span class="arr">→</span></button>
        </form>
        <p class="back"><a href="{{ route('login') }}">← Back to login</a></p>
    </div>
</section>
@endsection

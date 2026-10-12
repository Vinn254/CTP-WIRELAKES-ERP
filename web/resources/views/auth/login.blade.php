@extends('layouts.app')
@section('title', 'Staff login')

@section('content')
<section class="section login-section">
    <div class="card login-card">
        <span class="login-badge">🔒</span>
        <h1>Staff login</h1>
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
    </div>
</section>
@endsection

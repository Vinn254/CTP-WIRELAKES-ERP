@extends('layouts.app')
@section('title', 'Confirm it is you')

@section('content')
<section class="section login-section">
    <div class="card login-card">
        <span class="login-badge">✉️</span>
        <h1>Check your email</h1>
        <p class="help">
            We sent a {{ $minutes }}-minute code to <strong>{{ $email }}</strong>.
            {{ $purpose === 'login'
                ? 'Office accounts confirm every sign-in — the password alone does not open the console.'
                : 'Type it here to wake up your new account. Nothing is enabled until you do.' }}
        </p>
        @include('partials.errors')
        <form method="POST" action="{{ route('otp.confirm') }}">
            @csrf
            <div class="field">
                <label>Confirmation code <span class="req">*</span></label>
                <input class="otp-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                       maxlength="{{ config('account.otp.digits') }}" required autofocus placeholder="······">
            </div>
            <button class="btn-signin" type="submit">Confirm <span class="arr">→</span></button>
        </form>
        <div class="otp-actions">
            <form method="POST" action="{{ route('otp.resend') }}">
                @csrf
                <button class="btn-quiet" type="submit">Send me a new code</button>
            </form>
            <form method="POST" action="{{ route('otp.cancel') }}">
                @csrf
                <button class="btn-quiet btn-quiet-plain" type="submit">Stop and go back to the login</button>
            </form>
        </div>
        <p class="help">Nobody at the programme will ever ask you for this code. Do not share it.</p>
    </div>
</section>
@endsection

@extends('layouts.app')
@section('title', 'Activate your member account')

@php $brandName = content('site','branding','brand_name','WireLakes Technologies'); @endphp

@section('content')
<section class="section login-section">
    <div class="card login-card">
        @if (! $invite)
            <h1>This activation link cannot be used</h1>
            <p style="color:var(--muted);">The link is expired, already accepted or was revoked. Contact the {{ $brandName }} programme office and ask for a new one.</p>
            <a class="btn btn-outline" href="{{ route('login') }}">Go to login</a>
        @else
            <h1>Welcome, {{ $invite->name }}</h1>
            <p style="color:var(--muted);">
                Your application has been approved as
                <span class="tag">{{ $invite->member_type === 'fundi' ? 'E-Fundi' : 'CTP' }}</span>.<br>
                Set a password to open your member portal — where projects at your level are posted.
                @if ($invite->expires_at)<span class="hint"> · link expires {{ $invite->expires_at->format('d M Y') }}</span>@endif
            </p>
            @include('partials.errors')
            <form method="POST" action="{{ route('activate.accept', $invite->token) }}">
                @csrf
                <div class="field">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" value="{{ $invite->email }}" readonly>
                    <div class="help">This is the email you registered with and your login name.</div>
                </div>
                <div class="field">
                    <label>Choose a password <span class="req">*</span></label>
                    <input type="password" name="password" minlength="8" required autofocus autocomplete="new-password">
                    <div class="help">At least 8 characters.</div>
                </div>
                <div class="field">
                    <label>Confirm password <span class="req">*</span></label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Create my account</button>
            </form>
        @endif
    </div>
</section>
@endsection

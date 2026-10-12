@extends('layouts.app')
@section('title', 'Activate your staff account')

@php $brandName = content('site','branding','brand_name','CTP-WireLakes'); @endphp

@section('content')
<section class="section login-section">
    <div class="card login-card">
        <span class="login-badge">✉️</span>

        @if (! $invite)
            <h1>This invitation cannot be used</h1>
            <p style="color:var(--muted);">The link is expired, already accepted or was revoked. Please ask a {{ $brandName }} super admin to send a new invitation.</p>
            <a class="btn btn-outline" href="{{ route('login') }}">Go to staff login</a>
        @else
            <h1>Set up your account</h1>
            <p style="color:var(--muted);">
                You have been invited as <b>{{ $invite->name }}</b><br>
                Role: <span class="tag">{{ \App\Models\StaffInvite::ROLES[$invite->role] ?? $invite->role }}</span>
                @if ($invite->expires_at)<span class="hint"> · link expires {{ $invite->expires_at->format('d M Y') }}</span>@endif
            </p>
            @include('partials.errors')
            <form method="POST" action="{{ route('invite.accept', $invite->token) }}">
                @csrf
                <div class="field">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" value="{{ $invite->email }}" readonly>
                    <div class="help">Your login email is fixed by the invitation.</div>
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
                <button class="btn-signin" type="submit">Create my account <span class="arr">→</span></button>
            </form>
        @endif
    </div>
</section>
@endsection

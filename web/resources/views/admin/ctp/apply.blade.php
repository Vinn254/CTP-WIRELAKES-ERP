@extends('layouts.admin')
@section('title', 'Add a CTP from the office')

@php
    use App\Models\CtpRegistration;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.promoters.index') }}">&larr; CTP profiles</a></p>
<h1>Add a CTP from the office</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    The same application a person fills on the website, typed in for them at the counter — usually by a CTP who
    mobilised them on the ground. The record enters the same lifecycle:
    <b>KSh {{ number_format($fee) }} one-time fee</b>, then review, then the CTP ID and a portal login.
    A promoter a partner is sponsoring pays nothing and goes straight to review.
</p>

@include('partials.errors')

<form method="POST" action="{{ route('admin.promoters.store') }}" enctype="multipart/form-data" class="card">
    @csrf

    <h3>Personal details</h3>
    <div class="form-grid">
        <div class="field">
            <label>Full name (as on ID) <span class="req">*</span></label>
            <input type="text" name="full_name" value="{{ old('full_name') }}" required>
        </div>
        <div class="field">
            <label>National ID / birth certificate no. <span class="req">*</span></label>
            <input type="text" name="national_id" value="{{ old('national_id') }}" required maxlength="20">
        </div>
        <div class="field">
            <label>Phone <span class="req">*</span></label>
            <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="20" placeholder="07XX XXX XXX">
        </div>
        <div class="field">
            <label>Email <span class="req">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="field">
            <label>Date of birth <span class="req">*</span></label>
            <input type="date" name="dob" value="{{ old('dob') }}" required>
        </div>
        <div class="field">
            <label>Gender <span class="req">*</span></label>
            <select name="gender" required>
                @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $key => $label)
                    <option value="{{ $key }}" @selected(old('gender') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <h3 style="margin-top:20px;">Location</h3>
    <div class="form-grid">
        <div class="field">
            <label>County <span class="req">*</span></label>
            <select name="county" required>
                <option value="">Select county…</option>
                @foreach ($counties as $name)
                    <option value="{{ $name }}" @selected(old('county') === $name)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Sub-county <span class="req">*</span></label>
            <input type="text" name="sub_county" value="{{ old('sub_county') }}" required>
        </div>
        <div class="field">
            <label>Ward <span class="req">*</span></label>
            <input type="text" name="ward" value="{{ old('ward') }}" required>
        </div>
        <div class="field">
            <label>Village / estate <span class="req">*</span></label>
            <input type="text" name="village" value="{{ old('village') }}" required>
        </div>
    </div>

    <h3 style="margin-top:20px;">Track &amp; education</h3>
    <div class="form-grid">
        <div class="field">
            <label>Highest education level <span class="req">*</span></label>
            <select name="education_level" required>
                @foreach (CtpRegistration::EDUCATION_LEVELS as $key => $label)
                    <option value="{{ $key }}" @selected(old('education_level') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Track <span class="req">*</span></label>
            <select name="track" required>
                @foreach ($tracks as $key => $tr)
                    <option value="{{ $key }}">{{ $tr['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Mobilising CTP code (if any)</label>
            <input type="text" name="referral_code" value="{{ old('referral_code') }}" maxlength="20" placeholder="CTP-0001">
            <div class="help">The promoter who brought this person in. Counts toward their 15 / 30 credit; self-referral is dropped.</div>
        </div>
        <div class="field">
            <label>Sponsoring partner (if a partner covers the fee)</label>
            <select name="sponsor_id">
                <option value="">None — they pay the KSh {{ number_format($fee) }} fee</option>
                @foreach ($partners as $p)
                    <option value="{{ $p->id }}" @selected((int) old('sponsor_id') === $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <div class="help">A sponsored CTP has no fee to pay and enters the review queue straight away.</div>
        </div>
    </div>

    <h3 style="margin-top:20px;">Documents</h3>
    <div class="form-grid-3">
        <div class="field">
            <label>National ID scan</label>
            <input type="file" name="id_scan" accept=".jpg,.jpeg,.png,.pdf">
        </div>
        <div class="field">
            <label>School certificate</label>
            <input type="file" name="certificate" accept=".jpg,.jpeg,.png,.pdf">
        </div>
        <div class="field">
            <label>Profile photo</label>
            <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png">
        </div>
    </div>
    <p class="help">
        Optional here — attach the papers on the profile as soon as they are in hand. A public application cannot be
        submitted without the ID scan and a passport photo.
    </p>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Add CTP application</button>
        <a class="btn btn-ghost" href="{{ route('admin.promoters.index') }}">Cancel</a>
    </div>
</form>
@endsection

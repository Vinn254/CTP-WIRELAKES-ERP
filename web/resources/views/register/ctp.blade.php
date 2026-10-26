@extends('layouts.app')
@section('title', 'Become a student (CTP)')

@section('content')
<section class="section" style="max-width:860px;margin:34px auto;">
    <span class="tag">CTP Registration</span>
    <h1>Become a Community Technology Promoter</h1>
    <div class="steps">
        <span class="step now">1 · Fill the form</span>
        <span class="step">2 · KSh {{ number_format((int) $fee) }} membership fee (free if a partner sponsors you)</span>
        <span class="step">3 · Director/Admin review</span>
        <span class="step">4 · Get your CTP ID + portal login</span>
    </div>
    @include('partials.errors')
    <div class="card">
        <form method="POST" action="{{ route('register.ctp.store') }}" enctype="multipart/form-data">
            @csrf
            <fieldset>
                <legend>Personal details</legend>
                <div class="form-grid">
                    <div class="field">
                        <label>Full name (as on ID) <span class="req">*</span></label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" required>
                    </div>
                    <div class="field">
                        <label>National ID / birth certificate no. <span class="req">*</span></label>
                        <input type="text" name="national_id" value="{{ old('national_id') }}" required maxlength="20">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label>Phone <span class="req">*</span></label>
                        <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="20" placeholder="07XX XXX XXX">
                    </div>
                    <div class="field">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label>Date of birth <span class="req">*</span></label>
                        <input type="date" name="dob" value="{{ old('dob') }}" required>
                    </div>
                    <div class="field">
                        <label>Gender <span class="req">*</span></label>
                        <select name="gender" required>
                            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $v)
                                <option value="{{ $k }}" @selected(old('gender') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Location</legend>
                <div class="form-grid">
                    <div class="field">
                        <label>County <span class="req">*</span></label>
                        <select name="county" required>
                            <option value="">Select county…</option>
                            @foreach ($counties as $c)
                                <option value="{{ $c }}" @selected(old('county') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Sub-county <span class="req">*</span></label>
                        <input type="text" name="sub_county" value="{{ old('sub_county') }}" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label>Ward <span class="req">*</span></label>
                        <input type="text" name="ward" value="{{ old('ward') }}" required>
                    </div>
                    <div class="field">
                        <label>Village / estate <span class="req">*</span></label>
                        <input type="text" name="village" value="{{ old('village') }}" required>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Track &amp; education</legend>
                <div class="field">
                    <label>Highest education level <span class="req">*</span></label>
                    <select name="education_level" required>
                        @foreach (['none' => 'None', 'primary' => 'Primary', 'secondary' => 'Secondary (KCSE)', 'certificate' => 'Certificate', 'diploma' => 'Diploma', 'degree' => 'Degree', 'postgraduate' => 'Postgraduate'] as $k => $v)
                            <option value="{{ $k }}" @selected(old('education_level') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <label style="display:block;margin-bottom:8px;">Choose your track <span class="req">*</span></label>
                @foreach ($tracks as $key => $tr)
                    <label class="tier-option">
                        <input type="radio" name="track" value="{{ $key }}" @checked(old('track') === $key) required>
                        <span><b>{{ $tr['label'] }}</b>@if($tr['desc'])<small>{{ $tr['desc'] }}</small>@endif</span>
                    </label>
                @endforeach
                <div class="field" style="margin-top:14px;">
                    <label>Referral code (if a CTP referred you)</label>
                    <input type="text" name="referral_code" value="{{ old('referral_code', $refPrefill ?? '') }}" maxlength="20" placeholder="CTP-0001">
                    <div class="help">Self-referral is blocked. Each referred CTP counts once for its referrer.</div>
                    @if (! empty($refPrefill))
                        <div class="help">Filled from the link you opened — the CTP who invited you.</div>
                    @endif
                </div>
            </fieldset>

            <fieldset>
                <legend>Membership fee</legend>
                <p class="help">A one-time fee of <b>KSh {{ number_format((int) $fee) }}</b> is due after you submit, before our
                    team reviews your documents. Pay by M-Pesa or bank — our team will send the details.</p>
                <div class="field">
                    <label>Is a partner organisation sponsoring you?</label>
                    <select name="sponsor_id">
                        <option value="">No — I will pay the KSh {{ number_format((int) $fee) }} fee</option>
                        @foreach ($partners as $p)
                            <option value="{{ $p->id }}" @selected((int) old('sponsor_id') === $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <div class="help">A CTP sponsored by one of our partners pays no membership fee. The office confirms it with
                        the partner first, so keep the fee details handy in the meantime.</div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Documents</legend>
                <div class="form-grid-3">
                    <div class="field">
                        <label>National ID scan <span class="req">*</span></label>
                        <input type="file" name="id_scan" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <div class="field">
                        <label>School certificate (if any)</label>
                        <input type="file" name="certificate" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <div class="field">
                        <label>Profile photo <span class="req">*</span></label>
                        <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png" required>
                        <p class="help">A clear front photo of your face — it goes on your CTP profile and is what the office and your sites identify you by.</p>
                    </div>
                </div>
                <p class="help">Files are stored securely and are visible only to programme Directors and Admins.</p>
            </fieldset>

            <button class="btn btn-primary btn-lg btn-block" type="submit">Submit CTP application</button>
        </form>
    </div>
</section>
@endsection

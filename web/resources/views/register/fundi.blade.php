@extends('layouts.app')
@section('title', 'Become a Fundi')

@section('content')
<section class="section" style="max-width:860px;margin:34px auto;">
    <span class="tag">E-Fundi Registration</span>
    <h1>Become a verified E-Fundi</h1>
    <p class="sub">You do NOT need to have started as a CTP. Skilled workers apply directly. After approval you get a Fundi ID (e.g. EF-0001) and portal access to the project marketplace.</p>
    <div class="steps">
        <span class="step now">1 · Fill the form</span>
        <span class="step">2 · Pay membership fee (M-Pesa — coming online)</span>
        <span class="step">3 · Review + referee check</span>
        <span class="step">4 · Get your EF ID + portal login</span>
    </div>
    @include('partials.errors')
    <div class="card">
        <form method="POST" action="{{ route('register.fundi.store') }}" enctype="multipart/form-data">
            @csrf
            <fieldset>
                <legend>Personal details</legend>
                <div class="form-grid">
                    <div class="field">
                        <label>Full name (as on ID) <span class="req">*</span></label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" required>
                    </div>
                    <div class="field">
                        <label>National ID <span class="req">*</span></label>
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
                <legend>Which category describes you? <span class="req">*</span></legend>
                @foreach ($tiers as $key => $label)
                    <label class="tier-option">
                        <input type="radio" name="tier" value="{{ $key }}" @checked(old('tier') === $key) required>
                        <span><b>{{ Str::before($label, ' —') }}</b><small>{{ Str::after($label, ' — ') }}</small></span>
                    </label>
                @endforeach
            </fieldset>

            <fieldset>
                <legend>Business information</legend>
                <div class="form-grid-3">
                    <div class="field">
                        <label>Business name</label>
                        <input type="text" name="business_name" value="{{ old('business_name') }}">
                    </div>
                    <div class="field">
                        <label>Business registration no.</label>
                        <input type="text" name="business_reg_no" value="{{ old('business_reg_no') }}">
                    </div>
                    <div class="field">
                        <label>Years in business</label>
                        <input type="number" name="years_in_business" value="{{ old('years_in_business') }}" min="0" max="70">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Specialisations <span class="req">*</span></legend>
                <div class="chip-grid">
                    @foreach ($specialisations as $s)
                        <label>
                            <input type="checkbox" name="specialisations[]" value="{{ $s }}"
                                @checked(in_array($s, old('specialisations', [])))>
                            {{ $s }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend>Counties you serve <span class="req">*</span></legend>
                <div class="help" style="margin-bottom:8px;">Select one or more — hold Ctrl (or Cmd) to pick several.</div>
                <select name="service_counties[]" multiple size="8" required style="height:auto;">
                    @foreach ($counties as $c)
                        <option value="{{ $c }}" @selected(in_array($c, old('service_counties', [])))>{{ $c }}</option>
                    @endforeach
                </select>
            </fieldset>

            <fieldset>
                <legend>Where you work <span class="req">*</span></legend>
                <p class="help">Your own area, not just the counties you travel to. The programme counts the work
                    and the mobilisation credit in the community you registered in.</p>
                <div class="form-grid-3">
                    <div class="field">
                        <label>County <span class="req">*</span></label>
                        <input type="text" name="county" list="fundi-counties" value="{{ old('county') }}" required>
                        <datalist id="fundi-counties">
                            @foreach ($counties as $c)<option value="{{ $c }}"></option>@endforeach
                        </datalist>
                    </div>
                    <div class="field">
                        <label>Sub-county <span class="req">*</span></label>
                        <input type="text" name="sub_county" value="{{ old('sub_county') }}" required>
                    </div>
                    <div class="field">
                        <label>Ward <span class="req">*</span></label>
                        <input type="text" name="ward" value="{{ old('ward') }}" required>
                    </div>
                </div>
                <div class="field">
                    <label>CTP code of the person who mobilised you
                        <span class="hint">(optional — ask them for their code, e.g. CTP-0001)</span></label>
                    <input type="text" name="mobiliser_code" value="{{ old('mobiliser_code', $refPrefill) }}" maxlength="20">
                    <div class="hint">Whoever brought you into the programme is credited with your registration once
                        the office approves it. Leave it empty if nobody mobilised you.</div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Referee (recommendation) <span class="req">*</span></legend>
                <p class="help">One credible person who can vouch for your skill and character — a previous employer, a senior fundi, a partner organisation or a community leader. Admins contact the referee before approval.</p>
                <div class="form-grid-3">
                    <div class="field">
                        <label>Referee name <span class="req">*</span></label>
                        <input type="text" name="referee_name" value="{{ old('referee_name') }}" required>
                    </div>
                    <div class="field">
                        <label>Referee phone <span class="req">*</span></label>
                        <input type="tel" name="referee_phone" value="{{ old('referee_phone') }}" required maxlength="20">
                    </div>
                    <div class="field">
                        <label>Referee role / relationship <span class="req">*</span></label>
                        <input type="text" name="referee_role" value="{{ old('referee_role') }}" required placeholder="e.g. Former employer at J&B Electricals">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Documents &amp; portfolio</legend>
                <div class="form-grid">
                    <div class="field">
                        <label>National ID scan <span class="req">*</span></label>
                        <input type="file" name="id_scan" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <div class="field">
                        <label>Business certificate (if any)</label>
                        <input type="file" name="business_cert" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                </div>
                <div class="field">
                    <label>Trade / professional certificates (up to 6)</label>
                    <input type="file" name="trade_certs[]" multiple accept=".jpg,.jpeg,.png,.pdf">
                </div>
                <div class="field">
                    <label>Portfolio photos of your work (up to 6)</label>
                    <input type="file" name="portfolio[]" multiple accept=".jpg,.jpeg,.png">
                </div>
            </fieldset>

            <button class="btn btn-primary btn-lg btn-block" type="submit">Submit Fundi application</button>
        </form>
    </div>
</section>
@endsection

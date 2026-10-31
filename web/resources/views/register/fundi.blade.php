@extends('layouts.app')
@section('title', 'Become a Fundi')

@section('content')
<section class="section" style="max-width:860px;margin:34px auto;">
    <span class="tag">E-Fundi Registration</span>
    <h1>Become a verified E-Fundi</h1>
    <p class="sub">Skilled workers and trainers apply on this form. After approval you get an EF ID and your portal login.</p>
    <div class="steps">
        <span class="step now">1 · Fill the form</span>
        <span class="step">2 · Review by the office</span>
        <span class="step">3 · Get your EF ID + portal login</span>
    </div>
    <p class="back"><a href="{{ route('apply') }}">&larr; Not what you came for? Choose another application</a></p>
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
                <legend>Field of training you teach</legend>
                <p class="help">Tick the trainings you take at a site. They do not have to be one of your skills above —
                    a fundi who trains CTPs in Mathematics is still a Mathematics trainer. Tick at least one if you
                    chose <b>Trainer</b>; leave it empty if you are here for project work.</p>
                <div class="chip-grid">
                    @foreach ($trainings as $t)
                        <label>
                            <input type="checkbox" name="training_field_ids[]" value="{{ $t->id }}"
                                @checked(in_array($t->id, array_map('intval', (array) old('training_field_ids', [])), true))>
                            {{ $t->name }}
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
                {{-- Came on a CTP's own link: the credit rides with the application, the office records it. --}}
                @if ($refPrefill !== '')
                    <input type="hidden" name="mobiliser_code" value="{{ $refPrefill }}">
                @endif
            </fieldset>

            <fieldset>
                <legend>Referee <span class="hint">(optional)</span></legend>
                <p class="help">One person who can vouch for your skill and character — a previous employer, a
                    senior fundi, a partner organisation or a community leader. Leave it blank if you have nobody
                    to name yet.</p>
                <div class="form-grid-3">
                    <div class="field">
                        <label>Referee name <span class="hint">(optional)</span></label>
                        <input type="text" name="referee_name" value="{{ old('referee_name') }}">
                    </div>
                    <div class="field">
                        <label>Referee phone <span class="hint">(optional)</span></label>
                        <input type="tel" name="referee_phone" value="{{ old('referee_phone') }}" maxlength="20">
                    </div>
                    <div class="field">
                        <label>Referee role / relationship <span class="hint">(optional)</span></label>
                        <input type="text" name="referee_role" value="{{ old('referee_role') }}" placeholder="e.g. Former employer at J&B Electricals">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Documents</legend>
                <p class="help">Only your profile photo and National ID scan are needed. A CV is optional.</p>
                <div class="form-grid">
                    <div class="field">
                        <label>Profile photo <span class="req">*</span></label>
                        <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png" required>
                        <p class="help">A clear front photo of your face — it goes on your E-Fundi profile and is what sites and the office identify you by. JPG or PNG, up to 5 MB.</p>
                    </div>
                    <div class="field">
                        <label>National ID scan <span class="req">*</span></label>
                        <input type="file" name="id_scan" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <div class="field">
                        <label>CV <span class="hint">(optional)</span></label>
                        <input type="file" name="cv" accept=".jpg,.jpeg,.png,.pdf">
                        <p class="help">Your work history, if you have one written down. JPG, PNG or PDF, up to 3 MB.</p>
                    </div>
                </div>
            </fieldset>

            <button class="btn btn-primary btn-lg btn-block" type="submit">Submit Fundi application</button>
        </form>
    </div>
</section>
@endsection

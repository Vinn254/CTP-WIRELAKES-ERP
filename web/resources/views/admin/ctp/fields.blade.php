@php
    use App\Models\CtpRegistration;
@endphp

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
        <label>Programme (the training they want)</label>
        <select name="training_id">
            <option value="">Not chosen — the office will place them</option>
            @foreach ($trainings as $t)
                <option value="{{ $t->id }}" @selected((string) old('training_id') === (string) $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
        <div class="help">From the trainings the programme runs. A student who chose nothing on their form is placed
            here or at the Registrar's desk.</div>
    </div>
    <div class="field">
        <label>Mobilising CTP code (if any)</label>
        <input type="text" name="referral_code" value="{{ old('referral_code') }}" maxlength="20" placeholder="CTP-0001">
        <div class="help">The promoter who brought this person in. Counts toward their 15 / 30 credit; self-referral is dropped.</div>
    </div>
</div>

<h3 style="margin-top:20px;">Sponsor</h3>
@include('partials.sponsor-fields', ['partners' => $partners, 'office' => true])
<p class="help">A CTP a partner is sponsoring pays nothing and goes into the review queue at once; a sponsor
    written in by hand is confirmed with that person first.</p>

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

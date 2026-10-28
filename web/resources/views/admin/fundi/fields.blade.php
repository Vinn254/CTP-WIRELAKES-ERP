@php
    $oldSkills = (array) old('specialisations', []);
    $oldCounties = (array) old('service_counties', []);
@endphp

<h3>Personal details</h3>
<div class="form-grid-2">
    <div class="field">
        <label>Full name (as on ID) <span class="req">*</span></label>
        <input type="text" name="full_name" value="{{ old('full_name') }}" required>
    </div>
    <div class="field">
        <label>National ID / birth certificate no. <span class="req">*</span></label>
        <input type="text" name="national_id" value="{{ old('national_id') }}" required maxlength="20">
        <div class="help">If this person is already on the register, open their record instead — one fundi keeps one row for life.</div>
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

<h3 style="margin-top:20px;">Grade &amp; trade</h3>
<div class="form-grid-2">
    <div class="field">
        <label>Grade <span class="req">*</span></label>
        <select name="tier" required>
            @foreach ($tiers as $key => $label)
                <option value="{{ $key }}" @selected(old('tier') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="help">The grade decides what this fundi can be handed — only a <b>senior fundi</b> can train CTPs.</div>
    </div>
    <div class="field">
        <label>Years trading</label>
        <input type="number" name="years_in_business" value="{{ old('years_in_business') }}" min="0" max="80">
    </div>
    <div class="field">
        <label>Skills <span class="req">*</span></label>
        <div class="chip-grid">
            @foreach ($specialisations as $s)
                <label><input type="checkbox" name="specialisations[]" value="{{ $s }}" @checked(in_array($s, $oldSkills, true))> {{ $s }}</label>
            @endforeach
        </div>
        <div class="help">Only the trades the programme recognises — the office extends the list in the CMS.</div>
    </div>
    <div class="field">
        <label>Counties they will work in <span class="req">*</span></label>
        <select name="service_counties[]" multiple size="8" required>
            @foreach ($counties as $c)
                <option value="{{ $c }}" @selected(in_array($c, $oldCounties, true))>{{ $c }}</option>
            @endforeach
        </select>
        <div class="help">Hold Ctrl (or Cmd) to pick several. Matching a fundi to a job reads this list.</div>
    </div>
</div>

<h3 style="margin-top:20px;">Where they work</h3>
<div class="form-grid-2">
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
        <label>Sub-county</label>
        <input type="text" name="sub_county" value="{{ old('sub_county') }}">
    </div>
    <div class="field">
        <label>Ward</label>
        <input type="text" name="ward" value="{{ old('ward') }}">
    </div>
    <div class="field">
        <label>Mobilising CTP code (if any)</label>
        <input type="text" name="mobiliser_code" value="{{ old('mobiliser_code') }}" maxlength="20" placeholder="CTP-0001">
        <div class="help">The promoter who brought this person in — the credit belongs to them.</div>
    </div>
    <div class="field">
        <label>Business name</label>
        <input type="text" name="business_name" value="{{ old('business_name') }}">
    </div>
    <div class="field">
        <label>Business registration no.</label>
        <input type="text" name="business_reg_no" value="{{ old('business_reg_no') }}" maxlength="60">
    </div>
</div>

<h3 style="margin-top:20px;">Referee</h3>
<div class="form-grid-3">
    <div class="field">
        <label>Name <span class="req">*</span></label>
        <input type="text" name="referee_name" value="{{ old('referee_name') }}" required>
    </div>
    <div class="field">
        <label>Phone <span class="req">*</span></label>
        <input type="tel" name="referee_phone" value="{{ old('referee_phone') }}" required maxlength="20">
    </div>
    <div class="field">
        <label>Role / relationship <span class="req">*</span></label>
        <input type="text" name="referee_role" value="{{ old('referee_role') }}" required>
    </div>
</div>

<h3 style="margin-top:20px;">Documents</h3>
<div class="form-grid-3">
    <div class="field">
        <label>Profile photo</label>
        <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png">
    </div>
    <div class="field">
        <label>National ID scan</label>
        <input type="file" name="id_scan" accept=".jpg,.jpeg,.png,.pdf">
    </div>
    <div class="field">
        <label>Business registration</label>
        <input type="file" name="business_cert" accept=".jpg,.jpeg,.png,.pdf">
    </div>
    <div class="field">
        <label>Trade certificates (up to 6)</label>
        <input type="file" name="trade_certs[]" multiple accept=".jpg,.jpeg,.png,.pdf">
    </div>
    <div class="field">
        <label>Portfolio photos (up to 6)</label>
        <input type="file" name="portfolio[]" multiple accept=".jpg,.jpeg,.png">
    </div>
</div>
<p class="help">All optional here — attach what is in hand now and finish the file on the record. A public
    application cannot be submitted without the ID scan and a profile photo.</p>

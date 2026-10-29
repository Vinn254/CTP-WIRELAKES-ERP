@php
    // One card writes a staff file, whether it starts from an applicant or amends a person already on the register.
    $val = function (string $key) use ($owner) {
        $held = $owner->{$key} ?? null;

        if ($key === 'started_on' && $held) {
            $held = \Illuminate\Support\Carbon::parse($held)->toDateString();
        }

        return old($key, $held ?? '');
    };
@endphp
<div class="form-grid-2">
    <div class="field full">
        <label>Profile photo <span class="req">*</span></label>
        @if ($owner instanceof \App\Models\StaffRecord && $owner->hasPhoto())
            <span class="avatar-lg has-photo"><a href="{{ route('admin.directory.photo', $owner) }}" data-zoom
                aria-label="Open the profile photo"><img src="{{ route('admin.directory.photo', $owner) }}"
                alt="{{ $owner->name }} — profile photo"></a></span>
        @endif
        <input type="file" name="photo" accept=".jpg,.jpeg,.png">
        <div class="hint">A clear face picture, up to 2 MB — the one that shows on their directory row.
            A new one replaces the photo on record.</div>
    </div>
    <div class="field">
        <label>Name <span class="req">*</span></label>
        <input type="text" name="name" maxlength="150" required value="{{ $val('name') }}">
    </div>
    <div class="field">
        <label>Email <span class="req">*</span></label>
        <input type="email" name="email" maxlength="160" required value="{{ $val('email') }}">
        <div class="hint">The activation link for the login goes here.</div>
    </div>
    <div class="field">
        <label>Phone <span class="req">*</span></label>
        <input type="text" name="phone" maxlength="30" required value="{{ $val('phone') }}">
    </div>
    <div class="field">
        <label>Job title</label>
        <input type="text" name="job_title" maxlength="120" value="{{ $val('job_title') }}" placeholder="e.g. Finance Officer">
    </div>
    <div class="field">
        <label>First day</label>
        <input type="date" name="started_on" value="{{ $val('started_on') }}">
    </div>
    <div class="field">
        <label>Monthly pay (KSh)</label>
        <input type="number" name="salary" min="0" max="99999999" step="0.01" value="{{ $val('salary') }}">
        <div class="hint">Only this desk sees it — never the person’s own screen.</div>
    </div>
    <div class="field">
        <label>ID number</label>
        <input type="text" name="id_number" maxlength="40" value="{{ $val('id_number') }}">
    </div>
    <div class="field">
        <label>KRA PIN</label>
        <input type="text" name="kra_pin" maxlength="40" value="{{ $val('kra_pin') }}">
    </div>
    <div class="field">
        <label>NSSF number</label>
        <input type="text" name="nssf_number" maxlength="40" value="{{ $val('nssf_number') }}">
    </div>
    <div class="field">
        <label>NHIF number</label>
        <input type="text" name="nhif_number" maxlength="40" value="{{ $val('nhif_number') }}">
    </div>
    <div class="field">
        <label>Bank</label>
        <input type="text" name="bank_name" maxlength="90" value="{{ $val('bank_name') }}">
    </div>
    <div class="field">
        <label>Branch</label>
        <input type="text" name="bank_branch" maxlength="90" value="{{ $val('bank_branch') }}">
    </div>
    <div class="field">
        <label>Account number</label>
        <input type="text" name="bank_account" maxlength="40" value="{{ $val('bank_account') }}">
    </div>
    <div class="field full">
        <label>Physical address</label>
        <input type="text" name="address" maxlength="180" value="{{ $val('address') }}">
    </div>
    <div class="field full">
        <b>C14 — next of kin</b>
        <div class="hint">Who the office calls if anything happens to this member of staff, and who is paid.
            The first is required; the second is optional.</div>
    </div>
    @foreach ([1 => 'First next of kin', 2 => 'Second next of kin (optional)'] as $n => $kinLabel)
        @php $p = $n === 1 ? 'next_of_kin' : 'next_of_kin_2'; $req = $n === 1; @endphp
        <div class="field">
            <label>{{ $kinLabel }} — name @if ($req)<span class="req">*</span>@endif</label>
            <input type="text" name="{{ $p }}_name" maxlength="150" @required($req) value="{{ $val($p.'_name') }}">
        </div>
        <div class="field">
            <label>{{ $kinLabel }} — contact @if ($req)<span class="req">*</span>@endif</label>
            <input type="text" name="{{ $p }}_phone" maxlength="30" @required($req)
                   placeholder="07xx xxx xxx" value="{{ $val($p.'_phone') }}">
        </div>
        <div class="field">
            <label>{{ $kinLabel }} — relationship @if ($req)<span class="req">*</span>@endif</label>
            <input type="text" name="{{ $p }}_relation" maxlength="60" @required($req)
                   placeholder="e.g. Spouse, Parent, Sibling" value="{{ $val($p.'_relation') }}">
        </div>
    @endforeach
</div>

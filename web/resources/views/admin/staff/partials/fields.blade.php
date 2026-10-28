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
    <div class="field">
        <label>Next of kin <span class="req">*</span></label>
        <input type="text" name="next_of_kin_name" maxlength="150" value="{{ $val('next_of_kin_name') }}">
    </div>
    <div class="field">
        <label>Relationship</label>
        <input type="text" name="next_of_kin_relation" maxlength="60" value="{{ $val('next_of_kin_relation') }}">
    </div>
    <div class="field">
        <label>Their phone</label>
        <input type="text" name="next_of_kin_phone" maxlength="30" value="{{ $val('next_of_kin_phone') }}">
    </div>
    <div class="field full">
        <label>Physical address</label>
        <input type="text" name="address" maxlength="180" value="{{ $val('address') }}">
    </div>
    <div class="field full">
        <label>C14 document</label>
        <input type="file" name="c14" accept=".pdf,.jpg,.jpeg,.png">
        <div class="hint">PDF or a clear photo, up to 5 MB. A new one replaces the file on record.</div>
    </div>
</div>

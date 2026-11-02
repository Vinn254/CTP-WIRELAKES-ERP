@extends('layouts.app')
@section('title', 'Student application (CTP)')

@section('content')
<section class="section" style="max-width:860px;margin:34px auto;">
    <span class="tag">Student (CTP)</span>
    <h1>Apply as a student</h1>
    <p class="sub">A short form. Choose the training you want a place on. After approval you get a CTP ID and a portal
        login; the office records your track and your papers as it reviews you.</p>
    <div class="steps">
        <span class="step now">1 · Fill the form</span>
        <span class="step">2 · Review by the office</span>
        <span class="step">3 · Get your CTP ID + portal login</span>
    </div>
    <p class="back"><a href="{{ route('apply') }}">&larr; Not what you came for? Choose another application</a></p>
    @include('partials.errors')
    <div class="card">
        <form method="POST" action="{{ route('register.ctp.store') }}" enctype="multipart/form-data">
            @csrf
            <fieldset>
                <legend>Your details</legend>
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
                <div class="field">
                    <label>Date of birth <span class="req">*</span></label>
                    <input type="date" name="dob" value="{{ old('dob') }}" required>
                </div>
            </fieldset>

            <fieldset>
                <legend>Where you live</legend>
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
                <div class="field">
                    <label>Ward <span class="req">*</span></label>
                    <input type="text" name="ward" value="{{ old('ward') }}" required>
                </div>
            </fieldset>

            <fieldset>
                <legend>The training you want</legend>
                <div class="field">
                    <label>Programme</label>
                    <select name="training_id">
                        <option value="">Not sure yet — let the office place me</option>
                        @foreach ($trainings as $t)
                            <option value="{{ $t->id }}" @selected((string) old('training_id') === (string) $t->id)>
                                {{ $t->name }}</option>
                        @endforeach
                    </select>
                    <p class="help">Pick the training you want a place on. These are the programmes the college runs
                        with its partner sites. If you are not sure, leave it — the Academic Registrar or the System
                        Administrator will place you on one when the office reviews your application.</p>
                </div>
            </fieldset>

            <fieldset>
                <legend>Your photo and ID</legend>
                <div class="form-grid">
                    <div class="field">
                        <label>Profile photo <span class="req">*</span></label>
                        <input type="file" name="passport_photo" accept=".jpg,.jpeg,.png" required>
                        <p class="help">A clear front photo of your face — it goes on your student profile and is what
                            the office and your sites identify you by. JPG or PNG, up to 5 MB.</p>
                    </div>
                    <div class="field">
                        <label>National ID scan <span class="hint">(optional)</span></label>
                        <input type="file" name="id_scan" accept=".jpg,.jpeg,.png,.pdf">
                        <p class="help">Attach it if you have a photo of your ID. The office can also take it at the counter.</p>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>Who sent you</legend>
                <div class="field">
                    <label>Referral code (if a CTP referred you) <span class="hint">(optional)</span></label>
                    <input type="text" name="referral_code" value="{{ old('referral_code', $refPrefill ?? '') }}" maxlength="20" placeholder="CTP-0001">
                    <div class="help">The code of the promoter who invited you. It is how they get credit for
                        bringing you into the programme.</div>
                    @if (! empty($refPrefill))
                        <div class="help">Filled from the link you opened — the CTP who invited you.</div>
                    @endif
                </div>
            </fieldset>

            <fieldset>
                <legend>Who is sponsoring you? <span class="hint">(optional)</span></legend>
                @include('partials.sponsor-fields', ['partners' => $partners])
                <p class="help">A partner or a person who pays your membership fee for you. Leave it blank if you are
                    paying yourself.</p>
            </fieldset>

            @php $fee = \App\Models\CtpRegistration::feeAmount(); $paybill = \App\Support\Finance::paybill(); @endphp
            <p class="help" style="margin:0 0 16px;"><b>Student registration fee:
                {{ \App\Support\Finance::ksh($fee) }}</b> — one time, paid after you submit on the Paybill
                <b>{{ $paybill['number'] }}</b>, account number <b>{{ $paybill['account'] }}</b>. Once the payment is
                in, the System Administrator checks it and approves your application. A sponsor named above covers the
                fee for you.</p>

            <button class="btn btn-primary btn-lg btn-block" type="submit">Submit application</button>
        </form>
    </div>
</section>
@endsection

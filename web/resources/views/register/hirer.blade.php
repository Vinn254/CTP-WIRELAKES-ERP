@extends('layouts.app')
@section('title', 'Post a Project')

@section('content')
<section class="section" style="max-width:720px;margin:34px auto;">
    <span class="tag">Hirer Registration</span>
    <h1>Register to post a project</h1>
    <p class="sub">Registration is <strong>free</strong> for organisations, schools, businesses and community groups. An admin verifies every organisation to prevent fraud — after that you get a Hirer ID (e.g. HIR-0001) and can post projects to the marketplace.</p>
    <div class="steps">
        <span class="step now">1 · Register</span>
        <span class="step">2 · Admin verification</span>
        <span class="step">3 · Post your project</span>
    </div>
    @include('partials.errors')
    <div class="card">
        <form method="POST" action="{{ route('register.hirer.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Your contact name <span class="req">*</span></label>
                    <input type="text" name="contact_name" value="{{ old('contact_name') }}" required>
                </div>
                <div class="field">
                    <label>Organisation / school / business name <span class="req">*</span></label>
                    <input type="text" name="organisation" value="{{ old('organisation') }}" required>
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
                    <label>County <span class="req">*</span></label>
                    <select name="county" required>
                        <option value="">Select county…</option>
                        @foreach ($counties as $c)
                            <option value="{{ $c }}" @selected(old('county') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Type of organisation</label>
                    <select name="org_type">
                        <option value="">Select…</option>
                        @foreach ($orgTypes as $k => $v)
                            <option value="{{ $k }}" @selected(old('org_type') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="field">
                <label>ID or organisation document</label>
                <input type="file" name="org_doc" accept=".jpg,.jpeg,.png,.pdf">
                <div class="help">Certificate of registration, school letterhead, ID — anything that proves the organisation is real.</div>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">Submit hirer registration</button>
        </form>
    </div>
</section>
@endsection

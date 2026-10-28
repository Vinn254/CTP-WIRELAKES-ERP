@extends('layouts.app')
@section('title', $opening->title)

@section('content')
<section class="section" style="max-width:900px;margin:34px auto;">
    <span class="tag">{{ $opening->kindLabel() }}</span>
    @if ($opening->area)<span class="tag gray">{{ $opening->area }}</span>@endif
    <h1>{{ $opening->title }}</h1>
    <p class="sub">{{ $opening->description }}</p>

    <div class="card">
        <ul class="delivered-meta">
            <li>Reference {{ $opening->reference }}</li>
            @if ($opening->county)<li>Where: {{ $opening->county }}</li>@endif
            <li>{{ $opening->positions }} position{{ $opening->positions == 1 ? '' : 's' }}</li>
            <li>{{ $opening->closingLabel() }}</li>
            <li>Posted {{ $opening->created_at->format('d M Y') }}</li>
        </ul>
        @if ($opening->requirements)
            <h3 style="margin-top:16px;">What we are looking for</h3>
            <p>{{ $opening->requirements }}</p>
        @endif
    </div>

    @if ($opening->isOpen())
        <div class="card" id="apply" style="margin-top:22px;">
            <h2>Apply for this opening</h2>
            <p class="sub">You do not need an account. Fill this in and the programme office reads it with your CV —
                they will call or email you back. Fields marked <span class="req">*</span> are needed to reach you.</p>
            @include('partials.errors')
            <form method="POST" action="{{ route('careers.apply', $opening) }}" enctype="multipart/form-data">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label>Full name <span class="req">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" maxlength="150" required>
                    </div>
                    <div class="field">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" maxlength="150" required>
                    </div>
                    <div class="field">
                        <label>Phone <span class="req">*</span></label>
                        <input type="text" name="phone" value="{{ old('phone') }}" maxlength="30" placeholder="07XX XXX XXX" required>
                    </div>
                    <div class="field">
                        <label>National ID / passport number</label>
                        <input type="text" name="id_number" value="{{ old('id_number') }}" maxlength="40">
                    </div>
                    <div class="field">
                        <label>Town you are in</label>
                        <input type="text" name="town" value="{{ old('town') }}" maxlength="90">
                    </div>
                    <div class="field">
                        <label>Years of experience</label>
                        <input type="text" name="experience" value="{{ old('experience') }}" maxlength="90" placeholder="e.g. 6 years wiring solar systems">
                    </div>
                </div>
                <div class="field">
                    <label>Your CV or a certificate (PDF, Word or a photo — up to 5 MB)</label>
                    <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>
                <div class="field">
                    <label>Tell them why you fit this work</label>
                    <textarea name="cover_note" rows="4" maxlength="1000">{{ old('cover_note') }}</textarea>
                </div>
                <button class="btn btn-lime btn-lg" type="submit">Send my application</button>
                <p class="hint">Sent on this page only — your details go to the programme office and nobody else.</p>
            </form>
        </div>
    @else
        <div class="card" style="margin-top:22px;">
            <h3>Applications for this opening are closed</h3>
            <p>{{ $opening->closingLabel() }}. If you applied, the office will contact you on the number or email you
                gave. Other openings are on the <a href="{{ route('careers') }}">careers page</a>.</p>
        </div>
    @endif

    <p style="margin-top:22px;"><a href="{{ route('careers') }}">← All openings</a></p>
</section>
@endsection

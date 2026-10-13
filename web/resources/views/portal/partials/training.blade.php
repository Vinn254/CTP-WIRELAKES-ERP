@php
    use App\Models\CtpRegistration;
    use Illuminate\Support\Str;

    $doneUpto = match ($member->status) {
        'pending_payment' => 1,
        'pending_review' => 2,
        'active' => 3,
        'graduated' => 6,
        'deployed' => 7,
        'suspended' => 3,
        default => 0,
    };
    $steps = $member->track === 'junior'
        ? ['Application submitted', 'Membership fee paid', 'Approved and CTP ID issued',
           'Practical check (graded)', 'Certification check on your certificates',
           'Registered as a junior E-Fundi', 'Deployed on projects']
        : ['Application submitted', 'Membership fee paid', 'Approved and CTP ID issued',
           'Enrolled in a training cohort', 'Theory and practical sessions',
           'Assessment and certificate', 'Added to the E-Fundi pool', 'Deployed on projects'];
@endphp

<div class="card">
    <h3>Where you are on the {{ Str::lower($member->trackLabel()) }}</h3>
    <ol class="steps">
        @foreach ($steps as $i => $step)
            <li class="{{ $i < $doneUpto ? 'done' : ($i === $doneUpto ? 'current' : '') }}">
                <b>{{ $step }}</b>
                @if ($i === $doneUpto)<span class="tag gold">You are here</span>@endif
            </li>
        @endforeach
    </ol>
    @if ($member->status === 'suspended')
        <p class="help">Your account is suspended — {{ $member->status_note ?: 'speak to your County Coordinator to put it right.' }}</p>
    @elseif ($member->track === 'junior')
        <p class="help">Junior CTPs skip the full cohort. You sit a graded <b>practical check</b> and a
            <b>certification check</b> on the certificates you hold. Pass both and you enter the fundi pool at junior level.</p>
    @else
        <p class="help">Cohort dates, session attendance and your assessment results appear here once the
            training module (Module 4) opens enrolment for your intake.</p>
    @endif
</div>

<div class="card">
    <h3>Your training record</h3>
    <dl class="kv">
        <dt>Highest education</dt><dd>{{ CtpRegistration::EDUCATION_LEVELS[$member->education_level] ?? $member->education_level }}</dd>
        <dt>Year left school</dt><dd>{{ $member->year_left_school ?: '—' }}</dd>
        <dt>Certificates held</dt>
        <dd>@if (count((array) $member->certificates_held))<span class="chips">@foreach ($member->certificates_held as $c)<span class="chip">{{ $c }}</span>@endforeach</span>@else<span class="hint">None recorded</span>@endif</dd>
        <dt>CTP ID</dt><dd>{{ $member->ctp_code ?: 'Issued when your application is approved' }}</dd>
        <dt>Projects you can see</dt><dd>{{ $member->poolLabel() }}</dd>
    </dl>
</div>

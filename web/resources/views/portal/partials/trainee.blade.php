@php
    use App\Models\CtpRegistration;
    use App\Models\Project;
    use Illuminate\Support\Str;

    $isPreview = isset($previewBase);
    $tabUrl = fn ($t) => $isPreview
        ? $previewBase.'?tab='.$t
        : route('portal.dashboard', ['tab' => $t]);
    $term = CtpRegistration::currentTerm();
    $thisTerm = $member->verifiedReferralsThisTerm();
    $lifetime = $member->verifiedReferrals();
    $next = $member->nextMilestone() ?? 30;
    $share = $member->referralLink();
    $whats = 'https://wa.me/?text='.urlencode('Join CTP-WireLakes as a CTP — use my referral link '.$share);

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

<div class="member-hero ctp-hero">
    <div>
        <span class="tag">CTP @if ($member->ctp_code)· {{ $member->ctp_code }}@endif</span>
        <h1>Hello, {{ Str::before($member->full_name, ' ') }}.</h1>
        <p class="sub">{{ $member->stageLabel() }}</p>
        <p class="sub ctp-term">Current term: <b>{{ $term['label'] }}</b> · {{ $member->trackLabel() }}</p>
    </div>
    <div class="member-stats">
        <div class="stat"><small>Mobilised this term</small><b>{{ $thisTerm }}</b></div>
        <div class="stat"><small>Lifetime referrals</small><b>{{ $lifetime }}</b></div>
        <div class="stat"><small>Projects open for you</small><b>{{ $projects->count() }}</b></div>
        <div class="stat"><small>Awarded to you</small><b>{{ $applications->where('status', 'approved')->count() }}</b></div>
    </div>
</div>

<div class="tabs ctp-tabs">
    @foreach (['training' => 'My training', 'mobilisation' => 'My mobilisation', 'communities' => 'My communities', 'projects' => 'My projects'] as $key => $lbl)
        <a class="tab {{ $tab === $key ? 'on' : '' }}" href="{{ $tabUrl($key) }}">{{ $lbl }}</a>
    @endforeach
</div>

@if ($tab === 'training')
    <div class="card">
        <h3>Where you are on the {{ Str::lower($member->trackLabel()) }}</h3>
        <ol class="steps">
            @foreach ($steps as $i => $step)
                <li class="{{ $i < $doneUpto ? 'done' : ($i === $doneUpto ? 'current' : '') }}">
                    <b>{{ $step }}</b>
                    @if ($i === $doneUpto)
                        <span class="tag gold">You are here</span>
                    @endif
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
@endif

@if ($tab === 'mobilisation')
    <div class="card">
        <h3>Bring young people in — your referral code</h3>
        <div class="ref-code-box">
            <div>
                <small>Your code</small>
                <b>{{ $member->ctp_code ?: 'Issued on approval' }}</b>
            </div>
            <div class="ref-actions">
                <button class="btn btn-sm btn-outline" type="button"
                        onclick="copyText('{{ $share }}', this)">Copy link</button>
                <a class="btn btn-sm btn-lime" href="{{ $whats }}" target="_blank" rel="noopener">Share on WhatsApp</a>
            </div>
        </div>
        <div class="link-box">
            <input type="text" readonly value="{{ $share }}" onclick="this.select()">
        </div>
        <p class="hint">Drop the link in a WhatsApp or community chat. Anyone who joins through it is counted for you.</p>

        <div class="milestone">
            <div class="ms-top"><b>{{ $thisTerm }} of {{ $next }}</b><span class="hint">verified referrals this term ({{ $term['label'] }})</span></div>
            <div class="ms-bar"><i style="width: {{ min(100, round($thisTerm / $next * 100)) }}%"></i></div>
            @if ($thisTerm >= 15)
                <p class="reward">You have earned <b>{{ $member->discountLabel() }}</b> on this term's training fee.</p>
            @else
                <p class="hint">{{ 15 - $thisTerm }} more to unlock 50% off this term’s fee, {{ 30 - $thisTerm }} more for the full sponsorship credit.</p>
            @endif
        </div>

        <h4 class="dl-group">People you have brought in</h4>
        @forelse ($referrals as $r)
            <div class="row-line">
                <b>{{ $r->full_name }}</b>
                <span class="hint">{{ $r->county }} · joined {{ $r->created_at->format('d M Y') }}</span>
                <span class="status {{ $r->status }}">{{ $r->statusLabel() }}</span>
            </div>
        @empty
            <p class="hint">Nobody yet. Share your link — referrals count once the person has paid and been approved.</p>
        @endforelse

        <h4 class="dl-group">How counting works</h4>
        <ul class="rules">
            <li>Only CTPs and E-Fundis who <b>paid and were approved</b> count.</li>
            <li>Only referrals made <b>inside the current term</b> count toward the discount.</li>
            <li>The discount is a <b>credit on this term’s fee</b> — never a refund.</li>
            <li>You cannot refer yourself, and each person counts for only one mobiliser.</li>
            <li>If someone you referred is later rejected, they drop off your count.</li>
        </ul>
    </div>
@endif

@if ($tab === 'communities')
    <div class="card">
        <h3>Where you work</h3>
        <dl class="kv">
            <dt>Home location</dt><dd>{{ $member->locationLabel() ?: '—' }}</dd>
            <dt>GPS pin</dt>
            <dd>@if ($member->gps_lat && $member->gps_lng){{ number_format($member->gps_lat, 5) }}, {{ number_format($member->gps_lng, 5) }}@else<span class="hint">not captured yet</span>@endif</dd>
            <dt>Counties you serve</dt>
            <dd>@if (count((array) $member->service_counties))<span class="chips">@foreach ($member->service_counties as $sc)<span class="chip">{{ $sc }}</span>@endforeach</span>@else<span class="hint">Home county ({{ $member->county }})</span>@endif</dd>
            <dt>Available for mobilisation drives</dt><dd>{{ $member->available_for_mobilisation ? 'Yes' : 'No' }}</dd>
            <dt>Trainer opt-in</dt><dd>{{ $member->trainer_opt_in ? 'Yes — once rated 3.5 or better' : 'Not opted in' }}</dd>
        </dl>
        @if ($member->profileGaps() && ! $isPreview)
            <p class="help">Still to capture: {{ collect($member->profileGaps())->join(', ') }}. Ask your County Coordinator to add these —
                the GPS pin is what puts your field work on the impact map.</p>
        @endif
    </div>

    <div class="card">
        <h3>Community field work</h3>
        <p class="help">As a community agent you run needs assessments, register beneficiaries, spot projects and
            lead e-waste collection drives. Every visit is logged with a photo and GPS pin.</p>
        <ul class="rules">
            <li>Community assignments — <span class="soon">opens with Module 3</span></li>
            <li>Needs assessment and beneficiary forms — <span class="soon">opens with Module 3</span></li>
            <li>E-waste collection drives — <span class="soon">opens with Module 10</span></li>
            <li>Beneficiaries and visits counted for your county — <span class="soon">opens with Module 12</span></li>
        </ul>
    </div>
@endif

@if ($tab === 'projects')
    <div class="card level-card">
        <h3>Your project level</h3>
        <p class="help">You currently see projects for <b>{{ $member->poolLabel() }}</b>.
            {{ Project::CATEGORIES[$member->fundi_tier && isset(Project::CATEGORIES[$member->fundi_tier]) ? $member->fundi_tier : 'attachment']['desc'] ?? '' }}</p>
    </div>
    @include('portal.partials.projects')
@endif

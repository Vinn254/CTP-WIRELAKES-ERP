@php
    use App\Models\Cohort;
    use App\Models\CtpRegistration;
    use App\Models\Enrollment;
    use Illuminate\Support\Str;

    $canWork = in_array($member->status, CtpRegistration::WORKING, true);
    $holding = $mine->where('state', 'approved')->count();
    $awaiting = $mine->where('state', 'pending')->count();
    $certified = collect($mine->pluck('certification')->filter()->all())->where('status', 'approved')->count();

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

<div class="page-intro">
    <p class="sub">This is your training record. You apply for an intake that is open, the programme office confirms
        the seat, and every session your trainer marks — or the school or the office records on their behalf —
        lands here. Nobody changes what has been recorded without you seeing it.</p>
    <div class="member-stats">
        <div class="stat"><small>Open intakes</small><b>{{ $open->count() }}</b></div>
        <div class="stat"><small>Seats held</small><b>{{ $holding }}</b></div>
        <div class="stat"><small>Awaiting a seat</small><b>{{ $awaiting }}</b></div>
        <div class="stat"><small>Certificates issued</small><b>{{ $certified }}</b></div>
    </div>
</div>

@if (! $canWork)
    <div class="card">
        <h3>Intakes open once your CTP ID is active</h3>
        <p class="help">Your status is <b>{{ $member->statusLabel() }}</b>. Training seats are held by active,
            graduated or deployed CTPs — finish the steps below and the open intakes become applyable.</p>
    </div>
@endif

<div class="card">
    <h3>Trainings open in your area ({{ $nearby->count() }})</h3>
    <p class="hint">You are in <b>{{ $member->county ?: 'an area that is not set on your record' }}</b>.
        These are the intakes the programme is running there. Your job as a trainee is to bring the other CTPs in
        your area to them — they apply here and the programme office confirms the seats.</p>
    @forelse ($nearby as $c)
        @include('portal.partials.intake-card', ['c' => $c, 'inArea' => true])
    @empty
        <p class="hint">No intake is open in {{ $member->county ?: 'your county' }} right now. The office posts
            cohorts under Training &amp; Certification — at a partner school or in a community it works with.</p>
    @endforelse
</div>

@if ($elsewhere->isNotEmpty())
    <div class="card">
        <h3>Open in other areas ({{ $elsewhere->count() }})</h3>
        <p class="hint">The programme will move you only if the office says so — apply if you can travel there.</p>
        @foreach ($elsewhere as $c)
            @include('portal.partials.intake-card', ['c' => $c, 'inArea' => false])
        @endforeach
    </div>
@endif

<div class="card">
    <p class="hint" style="margin:0;">The office can also give you a seat directly — a cohort booked for your
        community arrives in your list whether or not you applied for it.</p>
</div>

@php $myRuns = $mine->concat($history)->filter(fn ($e) => $e->cohort); @endphp
@if ($myRuns->isNotEmpty())
    <div class="card">
        <div class="section-head">
            <h3>Your training, term by term</h3>
            <span class="hint">{{ $myRuns->count() }} intake{{ $myRuns->count() === 1 ? '' : 's' }}
                across {{ $myRuns->pluck('cohort.academic_year')->filter()->unique()->count() }} academic year{{ $myRuns->pluck('cohort.academic_year')->filter()->unique()->count() === 1 ? '' : 's' }}</span>
        </div>
        <table class="data">
            <thead>
                <tr><th>Year</th><th>Term</th><th>Course</th><th>Intake</th><th>Where</th><th>Seat</th><th>Stage</th><th>Certificate</th></tr>
            </thead>
            <tbody>
                @foreach ($myRuns->sortByDesc(fn ($e) => (($e->cohort->academic_year ?: $e->cohort->starts_on?->year) ?: 0).'.'.($e->cohort->term ?: '')) as $e)
                    @php $c = $e->cohort; @endphp
                    <tr>
                        <td><b>{{ $c->yearLabel() ?: '—' }}</b></td>
                        <td>{{ $c->termLabel() ?: '—' }}</td>
                        <td>{{ $c->courseLabel() }}<div class="hint">{{ $c->dateLabel() }}</div></td>
                        <td>{{ $c->reference }}</td>
                        <td>{{ $c->venue?->name ?: ($c->trainer?->fundi?->full_name ? $c->trainer->fundi->full_name.'’s site' : '—') }}</td>
                        <td><span class="status {{ $e->state === 'approved' ? 'active' : ($e->state === 'pending' ? 'pending_review' : 'closed') }}">{{ $e->stateLabel() }}</span></td>
                        <td><span class="tag">{{ $e->stageLabel() }}</span></td>
                        <td>{{ $e->certification?->certificate_code ?: ($e->certification ? $e->certification->statusLabel() : '—') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="hint">Every run stays on your file — taking a course again in a later term does not overwrite what you
            already completed. The office and your trainer see the same lines.</p>
    </div>
@endif

@if ($mine->isNotEmpty())
    @foreach ($mine as $e)
        @php
            $c = $e->cohort;
            $attendance = $e->attendancePct();
            $marks = $e->entries->where('kind', 'attendance');
        @endphp
        <div class="card">
            <div class="section-head">
                <h3>{{ $c->courseLabel() }}</h3>
                <span class="tag">{{ $c->periodLabel() }}</span>
                <span class="tag gold">{{ $c->reference }}</span>
                <span class="status {{ $e->state === 'approved' ? 'active' : 'pending_review' }}">{{ $e->stateLabel() }}</span>
                @if ($e->source === 'office')<span class="tag">seat given by the office</span>@endif
            </div>
            <p class="hint">{{ $c->dateLabel() }} · {{ $c->hoursLabel() }} · {{ $c->placeLabel() }}
                @if ($c->trainer?->fundi) · trainer {{ $c->trainer->fundi->full_name }} @endif</p>

            <h4 class="dl-group">Course fee</h4>
            <div class="row-line">
                <span class="fee-tag {{ ['paid' => 'paid', 'waived' => 'waived', 'awaiting_payment' => 'unpaid'][$e->fee_status] ?? 'unpaid' }}">{{ $e->feeLabel() }}</span>
                @if ($e->feePaid())
                    <b>{{ number_format((float) $e->fee_amount, 2) }}</b> KSH
                    received {{ $e->fee_paid_at?->format('d M Y') ?? 'date not recorded' }}
                    <span class="hint">by {{ \App\Models\FinanceEntry::METHODS[$e->fee_method] ?? 'another route' }}
                        @if ($e->fee_reference) · reference {{ $e->fee_reference }}@endif</span>
                @elseif ($e->fee_status !== 'waived' && $e->feeDueKsh() > 0)
                    <span class="hint">{{ number_format($e->feeDueKsh(), 2) }} KSH for this intake. The programme
                        office records the payment when the money reaches them — it then appears on the programme's
                        money register under {{ $c->reference }}. Never pay a fee to an individual; ask the office for
                        the receipt if you have paid and this still says otherwise.</span>
                @else
                    <span class="hint">This intake carries no course fee.</span>
                @endif
            </div>

            <h4 class="dl-group">Where you are in the {{ $c->training?->name ?? 'training' }} track</h4>
            @php $track = collect(Enrollment::STAGES)->except('failed'); $here = array_search($e->stage, array_keys($track->all()), true); @endphp
            <ol class="steps">
                @foreach ($track as $stage => $label)
                    <li class="{{ $stage === $e->stage ? 'current' : ($here !== false && array_search($stage, array_keys($track->all()), true) < $here ? 'done' : '') }}">
                        <b>{{ $label }}</b>
                        @if ($stage === $e->stage)<span class="tag gold">You are here</span>@endif
                    </li>
                @endforeach
            </ol>
            @if ($e->stage === 'failed')
                <p class="help"><b>Not completed.</b> {{ $e->note ?: 'The office recorded that this run did not complete — ask them what you need to redo.' }}</p>
            @endif

            <div class="member-stats">
                <div class="stat"><small>Attendance</small>
                    <b>{{ $attendance === null ? '—' : $attendance.'%' }}</b></div>
                <div class="stat"><small>Sessions marked</small><b>{{ $marks->count() }}</b></div>
                <div class="stat"><small>Theory average</small>
                    <b>{{ $e->avgScore('theory') !== null ? $e->avgScore('theory').'/100' : '—' }}</b></div>
                <div class="stat"><small>Practical average</small>
                    <b>{{ $e->avgScore('practical') !== null ? $e->avgScore('practical').'/100' : '—' }}</b></div>
            </div>

            @if ($attendance !== null && $attendance < Enrollment::ATTENDANCE_MINIMUM)
                <p class="help"><b>Below {{ Enrollment::ATTENDANCE_MINIMUM }}% attendance.</b>
                    A certificate cannot be issued under that rule — speak to your trainer or the programme office
                    about the days you missed.</p>
            @endif

            <h4 class="dl-group">Your track to 100%</h4>
            <p class="hint">This is what is counted on you while the training runs. When it reaches 100% the
                record goes to the certification queue by itself — the office verifies it and a programme
                executive signs the certificate.</p>
            @include('partials.track', ['enrollment' => $e])

            @include('partials.modules', ['enrollment' => $e])
            @include('partials.documents', ['enrollment' => $e, 'modules' => $c?->training?->modules ?? collect()])

            @include('partials.progress', ['enrollment' => $e])

            @if ($e->certification)
                @php $cert = $e->certification; @endphp
                <h4 class="dl-group">Certificate</h4>
                <div class="row-line">
                    <b>{{ $cert->certificate_code ?: 'No code yet' }}</b>
                    <span class="status {{ $cert->status === 'approved' ? 'active' : ($cert->status === 'recommended' ? 'pending_review' : 'closed') }}">{{ $cert->statusLabel() }}</span>
                    @if ($cert->approved_on)<span class="hint">issued {{ $cert->approved_on->format('d M Y') }}</span>@endif
                </div>
                @if ($cert->reason)<p class="hint">{{ $cert->reason }}</p>@endif
                @if ($cert->status === 'recommended')
                    <p class="hint">The office has put you forward. A programme executive signs it off — until then there is no certificate number to show.</p>
                @endif
            @endif

            @unless (in_array($e->stage, ['completed', 'failed'], true))
                <form method="POST" action="{{ route('portal.training.withdraw', $e) }}" class="mini-form" style="margin-top:12px;"
                      data-confirm="Withdraw from {{ $c->reference }}?" data-title="Withdraw">
                    @csrf
                    <button class="btn btn-sm btn-ghost" type="submit">Withdraw from this intake</button>
                </form>
            @else
                <p class="hint">This run is closed — the record and any certificate stay on your file.</p>
            @endunless
        </div>
    @endforeach
@endif

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
    @endif
</div>

<div class="card">
    <h3>Your training record</h3>
    <dl class="kv">
        <dt>Highest education</dt><dd>{{ CtpRegistration::EDUCATION_LEVELS[$member->education_level] ?? $member->education_level }}</dd>
        <dt>Year left school</dt><dd>{{ $member->year_left_school ?: '—' }}</dd>
        <dt>Certificates held</dt>
        <dd>@if (count((array) $member->certificates_held))<span class="chips">@foreach ($member->certificates_held as $c)<span class="chip">{{ $c }}</span>@endforeach</span>@else<span class="hint">None recorded</span>@endif</dd>
        <dt>Certificates issued by this programme</dt>
        <dd>@php $codes = $mine->pluck('certification')->filter()->where('status', 'approved')->pluck('certificate_code')->concat($history->pluck('certification')->filter()->where('status', 'approved')->pluck('certificate_code'))->filter()->unique(); @endphp
            @if ($codes->isEmpty())<span class="hint">None yet</span>@else<span class="chips">@foreach ($codes as $code)<span class="chip">{{ $code }}</span>@endforeach</span>@endif</dd>
        <dt>CTP ID</dt><dd>{{ $member->ctp_code ?: 'Issued when your application is approved' }}</dd>
        <dt>Projects you can see</dt><dd>{{ $member->poolLabel() }}</dd>
    </dl>
    <p class="help">An approved certificate is what the office uses to register you as an E-Fundi. That step is
        confirmed separately — holding a certificate does not put you in the fundi pool on its own.</p>
</div>

@if ($history->isNotEmpty())
    <div class="card">
        <h3>Intakes you applied to or left</h3>
        @foreach ($history as $e)
            <div class="row-line">
                <b>{{ $e->cohort?->courseLabel() ?? 'Cohort removed' }}</b>
                <span class="tag gold">{{ $e->cohort->reference ?? '—' }}</span>
                <span class="status {{ $e->state === 'declined' ? 'closed' : ($e->state === 'withdrawn' ? 'pending_review' : 'closed') }}">{{ $e->stateLabel() }}</span>
                <span class="hint">{{ $e->stageLabel() }}</span>
                @if ($e->certification)
                    <span class="hint">certificate {{ $e->certification->certificate_code ?: $e->certification->statusLabel() }}</span>
                @endif
            </div>
        @endforeach
        <p class="hint">A declined or withdrawn application never blocks a seat — you can apply again while the intake is open.</p>
    </div>
@endif

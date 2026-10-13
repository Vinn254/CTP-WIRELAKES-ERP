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
    <h3>Open training you can apply for ({{ $open->count() }})</h3>
    @forelse ($open as $c)
        @php $already = $appliedTo->contains($c->id); @endphp
        <div class="community-card" style="border-top:1px solid var(--line);padding-top:12px;margin-top:12px;">
            <div class="section-head">
                <h4 style="margin:0;">{{ $c->course }}</h4>
                <span class="tag">{{ $c->family }}</span>
                <span class="tag gold">{{ $c->reference }}</span>
                @if ($c->seats > 0)
                    <span class="fee-tag {{ $c->seatsLeft() > 0 ? '' : 'paid' }}">{{ $c->seatsLeft() }} of {{ $c->seats }} seats left</span>
                @endif
            </div>
            <p class="hint">{{ $c->dateLabel() }} · {{ $c->hoursLabel() }}
                · {{ $c->placeLabel() }}
                @if ($c->trainer?->fundi) · trainer {{ $c->trainer->fundi->full_name }} @endif
                @if ((float) $c->fee_ksh > 0) · KSH {{ number_format((float) $c->fee_ksh, 2) }} @else · no training fee @endif
            </p>
            @if ($c->summary)<p class="hint">{{ $c->summary }}</p>@endif
            @if ($c->enrol_closes_on)
                <p class="hint">Applications close {{ $c->enrol_closes_on->format('d M Y') }}.</p>
            @endif

            @if ($already)
                <p class="help">You have already put yourself forward for this intake — it is in “Your training” below.</p>
            @elseif ($canWork)
                <form method="POST" action="{{ route('portal.training.apply') }}"
                      data-confirm="Apply for {{ $c->reference }} — {{ $c->course }}?" data-title="Apply for training">
                    @csrf
                    <input type="hidden" name="cohort_id" value="{{ $c->id }}">
                    <div class="field">
                        <label>Why you want this intake (optional)</label>
                        <textarea name="note" rows="2" maxlength="1000"
                                  placeholder="e.g. I have the tools and can attend every day at the school."></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">Apply for a seat</button>
                </form>
            @else
                <p class="hint">The office confirms seats for active CTPs.</p>
            @endif
        </div>
    @empty
        <p class="hint">No intake is open for applications right now. The programme office posts cohorts under
            Training &amp; Certification — including intakes run at a partner school or in your own community.</p>
    @endforelse
    <p class="help">The office can also give you a seat directly — a cohort booked for your community arrives in
        your list whether or not you applied for it.</p>
</div>

@if ($mine->isNotEmpty())
    @foreach ($mine as $e)
        @php
            $c = $e->cohort;
            $attendance = $e->attendancePct();
            $marks = $e->entries->where('kind', 'attendance');
        @endphp
        <div class="card">
            <div class="section-head">
                <h3>{{ $c->course }}</h3>
                <span class="tag gold">{{ $c->reference }}</span>
                <span class="status {{ $e->state === 'approved' ? 'active' : 'pending_review' }}">{{ $e->stateLabel() }}</span>
                @if ($e->source === 'office')<span class="tag">seat given by the office</span>@endif
            </div>
            <p class="hint">{{ $c->dateLabel() }} · {{ $c->hoursLabel() }} · {{ $c->placeLabel() }}
                @if ($c->trainer?->fundi) · trainer {{ $c->trainer->fundi->full_name }} @endif</p>

            <h4 class="dl-group">Where you are in the {{ $c->family }} track</h4>
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

            <h4 class="dl-group">Training record ({{ $e->entries->count() }})</h4>
            @forelse ($e->entries->take(20) as $entry)
                <div class="row-line">
                    <b>{{ $entry->kindLabel() }}</b>
                    <span class="hint">{{ $entry->entry_date->format('d M Y') }}</span>
                    @if ($entry->resultLabel() !== '')<span class="tag">{{ $entry->resultLabel() }}</span>@endif
                    <span class="hint">{{ $entry->recorderLabel() }}</span>
                    @if ($entry->note)<span class="hint">{{ $entry->note }}</span>@endif
                </div>
            @empty
                <p class="hint">Nothing has been recorded on you yet. Your trainer's first mark appears here the day it is entered.</p>
            @endforelse

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
                <b>{{ $e->cohort->course ?? 'Cohort removed' }}</b>
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

@extends('layouts.admin')
@section('title', $cohort->reference)

@php
    use App\Models\Enrollment;
    $pending = $enrollments->where('state', 'pending');
    $seated = $enrollments->where('state', 'approved');
    $others = $enrollments->reject(fn ($e) => in_array($e->state, ['pending', 'approved'], true));
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.cohorts.index') }}">&larr; Cohorts</a></p>
<div class="head-row">
    <div>
        <h1>{{ $cohort->courseLabel() }} <span class="status {{ $cohort->status }}">{{ $cohort->statusLabel() }}</span></h1>
        <p class="ah-meta">
            <span><b>{{ $cohort->reference }}</b></span>
            <span>{{ $cohort->training?->name }}</span>
            <span>{{ $cohort->dateLabel() }}</span>
            <span>{{ $cohort->placeLabel() }}</span>
            <span>{{ $seated->count() }} of {{ $cohort->seats }} seats held</span>
        </p>
    </div>
    <div class="ah-contact">
        <a class="btn btn-sm btn-outline" href="{{ route('admin.cohorts.edit', $cohort) }}">Edit cohort</a>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>How this intake runs</h3>
        <dl class="kv">
            <dt>Where</dt><dd>{{ $cohort->venue?->name ?? 'No fixed venue' }}
                @if ($cohort->venue)<span class="hint">({{ $cohort->venue->typeLabel() }})</span>@endif
                <div class="hint">{{ collect([$cohort->ward, $cohort->sub_county, $cohort->county])->filter()->join(', ') }}</div></dd>
            <dt>Trainer</dt><dd>{{ $cohort->trainer?->fundi?->full_name ?? 'Not assigned' }}
                @if ($cohort->trainer)
                    <span class="chip">{{ $cohort->trainer->statusLabel() }}</span>
                    @if ($cohort->trainer->rate_ksh > 0)<div class="hint">KSh {{ number_format((float) $cohort->trainer->rate_ksh) }} — Module 11 invoices this</div>@endif
                @endif</dd>
            <dt>Community</dt><dd>@if ($cohort->community)<a href="{{ route('admin.communities.show', $cohort->community) }}">{{ $cohort->community->name }}</a>@else<span class="hint">not tied to one</span>@endif</dd>
            <dt>Sessions</dt><dd>{{ $cohort->hoursLabel() }}</dd>
            <dt>Fee per trainee</dt><dd>KSh {{ number_format((float) $cohort->fee_ksh) }}</dd>
            <dt>Applications close</dt><dd>{{ $cohort->enrol_closes_on?->format('d M Y') ?? 'until you close them' }}</dd>
            <dt>Seats taken</dt><dd>{{ $cohort->seatsUsed() }} of {{ $cohort->seats }}@if ($cohort->isFull())<span class="tag gold">full</span>@endif</dd>
        </dl>

        <form method="POST" action="{{ route('admin.cohorts.status', $cohort) }}" class="fee-form"
              data-confirm="Change this cohort's status?" data-title="Set status">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        @foreach (\App\Models\Cohort::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected($cohort->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="align-self:end;">
                    <button class="btn btn-lime btn-block" type="submit">Save status</button>
                </div>
            </div>
            <p class="hint">Opening it is what puts it in front of CTPs in <b>/portal → My training</b>.</p>
        </form>
    </div>

    <div class="card">
        <h3>Put a CTP straight into a seat</h3>
        <p class="help">A CTP can also apply themselves — this is for the one you send, the transfer you agreed, or the
            intake a community needs filled.</p>
        <form method="POST" action="{{ route('admin.cohorts.enrol', $cohort) }}" class="fee-form"
              data-confirm="Give this CTP a seat in {{ $cohort->reference }}?" data-title="Enrol CTP">
            @csrf
            <div class="field">
                <label>CTP <span class="req">*</span></label>
                <select name="ctp_id" required>
                    <option value="">Choose a CTP</option>
                    @foreach ($candidates as $c)
                        <option value="{{ $c->id }}">{{ $c->full_name }} — {{ $c->ctp_code }}
                            ({{ collect([$c->ward, $c->county])->filter()->join(', ') ?: 'no area set' }})</option>
                    @endforeach
                </select>
                <div class="hint">Only an active, graduated or deployed CTP can hold a seat. {{ $candidates->count() }} available.</div>
            </div>
            <div class="field" style="margin-top:10px;">
                <label>Why you are placing them</label>
                <input type="text" name="note" maxlength="1000" placeholder="e.g. Referred by the Nyanda intake drive">
            </div>
            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Give the seat</button>
        </form>
    </div>
</div>

<div class="card">
    <h3>Seat applications ({{ $pending->count() }})</h3>
    @forelse ($pending as $e)
        <div class="row-line">
            <a href="{{ route('admin.promoters.show', $e->ctp_id) }}"><b>{{ $e->ctp->full_name }}</b></a>
            <span class="tag">{{ $e->ctp->ctp_code }}</span>
            <span class="hint">{{ collect([$e->ctp->ward, $e->ctp->county])->filter()->join(', ') }}</span>
            <span class="status {{ $e->ctp->status }}">{{ $e->ctp->statusLabel() }}</span>
            <span class="hint">{{ $e->source === 'self' ? 'applied themselves' : 'put forward by staff' }}</span>
            @if ($e->note)<span class="hint">“{{ $e->note }}”</span>@endif
            <form method="POST" action="{{ route('admin.enrollments.decide', $e) }}" class="mini-form">
                @csrf
                <input type="hidden" name="decision" value="approved">
                <button class="btn btn-sm btn-primary" type="submit">Give seat</button>
            </form>
            <form method="POST" action="{{ route('admin.enrollments.decide', $e) }}" class="mini-form">
                @csrf
                <input type="hidden" name="decision" value="declined">
                <button class="btn btn-sm btn-ghost" type="submit">Decline</button>
            </form>
            <form method="POST" action="{{ route('admin.enrollments.delete', $e) }}" class="mini-form"
                  data-confirm="Delete this application?" data-title="Remove application">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-ghost" type="submit">Delete</button>
            </form>
        </div>
    @empty
        <p class="hint">No CTP is waiting for a seat in this cohort.</p>
    @endforelse
</div>

<h2 class="section-head" style="margin-top:26px;">Trainees ({{ $seated->count() }})</h2>
@forelse ($seated as $e)
    @php
        $attendance = $e->attendancePct();
        $cert = $e->certification;
        $pct = $e->trackPct();
    @endphp
    <div class="card" style="margin-bottom:14px;">
        <h3>{{ $e->ctp->full_name }}
            <span class="tag">{{ $e->ctp->ctp_code }}</span>
            <span class="status {{ $e->stage }}">{{ $e->stageLabel() }}</span>
            <span class="fee-tag {{ $pct >= 100 ? 'paid' : 'waived' }}">track {{ $pct }}%</span>
            @if ($attendance !== null)
                <span class="tag {{ $e->isBelowAttendance() ? 'gold' : 'gray' }}">{{ $attendance }}% attendance</span>
            @endif
            @if ($cert)<span class="status {{ $cert->status }}">{{ $cert->statusLabel() }}</span>@endif
            <a class="btn btn-sm btn-ghost" href="{{ route('admin.track.show', $e) }}">Open track</a>
        </h3>
        <p class="help" style="margin:0 0 10px;">
            {{ $e->ctp->county }}@if ($e->ctp->ward) · {{ $e->ctp->ward }}@endif
            · seat given {{ $e->approved_on?->format('d M Y') ?? '—' }} ({{ $e->source === 'self' ? 'they applied' : 'placed by the office' }})
            @if ($e->note) · “{{ $e->note }}” @endif
            @if ($attendance !== null && $e->isBelowAttendance())
                — <b>below the {{ Enrollment::ATTENDANCE_MINIMUM }}% needed to certify.</b>
            @endif
        </p>

        <div class="form-grid-3">
            <form method="POST" action="{{ route('admin.enrollments.stage', $e) }}" class="mini-form fee-form">
                @csrf
                <label class="hint">Stage</label>
                <select name="stage">
                    @foreach ($stages as $key => $label)
                        <option value="{{ $key }}" @selected($e->stage === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline" type="submit" style="margin-top:6px;">Save stage</button>
            </form>

            @if ($e->canBeCertified())
                <form method="POST" action="{{ route('admin.certification.recommend', $e) }}" class="mini-form fee-form"
                      data-confirm="Put {{ $e->ctp->full_name }} forward for certification?" data-title="Recommend for certification">
                    @csrf
                    <label class="hint">Certification</label>
                    <p class="help" style="margin:0 0 6px;">Theory {{ $e->avgScore('theory') ?? '—' }} · Practical {{ $e->avgScore('practical') ?? '—' }} · Attendance {{ $attendance ?? 'not marked' }}%</p>
                    <button class="btn btn-sm btn-primary" type="submit">Put forward</button>
                </form>
            @elseif ($cert)
                <div class="fee-form">
                    <span class="hint">Certificate</span>
                    <p style="margin:6px 0 0;"><b>{{ $cert->certificate_code ?: 'awaiting an executive' }}</b>
                        — {{ $cert->statusLabel() }}</p>
                    @if ($cert->reason)<p class="hint">{{ $cert->reason }}</p>@endif
                    <a class="btn btn-sm btn-ghost" href="{{ route('admin.certification.index') }}">Certification queue</a>
                </div>
            @endif
        </div>

        <h4 class="dl-group" style="margin-top:16px;">Track</h4>
        @include('partials.track', ['enrollment' => $e])

        @include('partials.modules', ['enrollment' => $e, 'mode' => 'admin'])

        @include('partials.documents', ['enrollment' => $e, 'mode' => 'admin', 'modules' => $modules, 'documentKinds' => $documentKinds])

        @include('partials.progress', ['enrollment' => $e, 'mode' => 'admin'])

        @unless (in_array($e->stage, ['completed', 'failed'], true))
            <form method="POST" action="{{ route('admin.enrollments.decide', $e) }}" class="mini-form" style="margin-top:12px;"
                  data-confirm="Take {{ $e->ctp->full_name }}'s seat back?" data-title="Take the seat back">
                @csrf
                <input type="hidden" name="decision" value="declined">
                <button class="btn btn-sm btn-ghost" type="submit">Take the seat back</button>
            </form>
        @endunless
    </div>
@empty
    <div class="card"><p class="hint" style="margin:0;">No CTP holds a seat yet. Give one above, or wait for applications
        once the cohort is open.</p></div>
@endforelse

@if ($others->isNotEmpty())
    <div class="card">
        <h3>Not on the roll ({{ $others->count() }})</h3>
        @foreach ($others as $e)
            <div class="row-line">
                {{ $e->ctp->full_name }}
                <span class="status {{ $e->state }}">{{ $e->stateLabel() }}</span>
                <span class="hint">{{ $e->source === 'self' ? 'they applied' : 'placed by the office' }}</span>
                @if ($e->note)<span class="hint">“{{ $e->note }}”</span>@endif
            </div>
        @endforeach
    </div>
@endif

<p class="hint" style="margin-top:18px;">
    The chain Module 4 works: CTP applies or is placed → office confirms the seat → trainer or office records
    attendance, theory, practical and assessment → the office puts the trainee forward → an executive approves the
    certificate and its code is issued. Attendance below {{ Enrollment::ATTENDANCE_MINIMUM }}% cannot be certified.
</p>
@endsection

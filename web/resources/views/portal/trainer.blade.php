@extends('layouts.member')
@section('title', 'Training CTPs')

@php
    use App\Models\FundiRegistration;
    use App\Models\Trainer;

    $canTeach = in_array($member->tier, Trainer::TEACHING_TIERS, true);
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">A senior fundi can train CTPs and be paid for it. Your E-Fundi record stays yours — this page is
        only the teaching role: the site you teach at, which of the programme's trainings you take, the hours you give and the
        trainees you mark. Training never takes away your right to apply for open projects.</p>
    <div class="member-stats">
        <div class="stat"><small>Grade</small><b>{{ \Illuminate\Support\Str::limit(FundiRegistration::tierLabel($member->tier), 18) }}</b></div>
        <div class="stat"><small>Trainer status</small><b>{{ $trainer ? $trainer->statusLabel() : 'Not on the list' }}</b></div>
        <div class="stat"><small>Cohorts teaching</small><b>{{ $trainer?->cohorts->count() ?? 0 }}</b></div>
        <div class="stat"><small>Trainees marked</small>
            <b>{{ $trainer ? $trainer->cohorts->sum(fn ($c) => $c->enrollments->where('state', 'approved')->count()) : 0 }}</b></div>
    </div>
</div>

@if (! $trainer)
    <div class="card">
        <h3>Ask to train CTPs</h3>
        @if (! $canTeach)
            <p class="help">You are registered as <b>{{ FundiRegistration::tierLabel($member->tier) }}</b>. Only a
                junior or senior fundi can train CTPs — an intern or a school attachment sits the training, not teaches it.
                The programme office can still put you forward when your grade changes.</p>
        @else
            <p class="help">Tell the office where you can teach and what you are strong in. They approve the trainer
                before a cohort is handed over, and record the rate you are paid.</p>
            <form method="POST" action="{{ route('portal.trainer.apply') }}" class="fee-form"
                  data-confirm="Send this to the programme office?" data-title="Apply to train">
                @csrf
                <div class="form-grid-2">
                    <div class="field">
                        <label>Site you would teach at</label>
                        <select name="venue_id">
                            <option value="">No particular venue — I teach in an area</option>
                            @foreach ($venues as $v)
                                <option value="{{ $v->id }}">{{ $v->name }} — {{ $v->county }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>County <span class="req">*</span></label>
                        <input type="text" name="county" list="trainer-counties" required value="{{ old('county', $member->county) }}">
                    </div>
                    <div class="field">
                        <label>Sub-county</label>
                        <input type="text" name="sub_county" value="{{ old('sub_county', $member->sub_county) }}">
                    </div>
                    <div class="field">
                        <label>Ward</label>
                        <input type="text" name="ward" value="{{ old('ward', $member->ward) }}">
                    </div>
                    <div class="field">
                        <label>Hours you can give a week</label>
                        <input type="number" name="weekly_hours" min="0" max="80" value="{{ old('weekly_hours') }}" placeholder="e.g. 15">
                    </div>
                </div>
                <div class="field" style="margin-top:12px;">
                    <label>What you can teach <span class="req">*</span></label>
                    <div style="display:flex;flex-wrap:wrap;gap:2px 18px;margin:8px 0;">
                        @foreach ($trainings as $tr)
                            <label class="check-row" style="margin:0 14px 0 0;"><input type="checkbox" name="trainings[]" value="{{ $tr->id }}"
                                @checked(in_array($tr->id, array_map('intval', (array) old('trainings', [])), true))> {{ $tr->name }}</label>
                        @endforeach
                    </div>
                    <div class="help">Pick the trainings you can actually deliver — a cohort is only handed to a trainer listed for it. Only the trainings the programme runs are on this list.</div>
                </div>
                <div class="field" style="margin-top:12px;">
                    <label>What you have actually built or fixed</label>
                    <textarea name="note" rows="3" maxlength="500" placeholder="Years on the job, the tools you have, sites you have worked."></textarea>
                </div>
                <datalist id="trainer-counties">@foreach ($counties as $name)<option value="{{ $name }}"></option>@endforeach</datalist>
                <button class="btn btn-primary" type="submit" style="margin-top:12px;">Send to the programme office</button>
            </form>
        @endif
    </div>
@else
    <div class="card">
        <div class="section-head">
            <h3>Your trainer record</h3>
            <span class="status {{ $trainer->status === 'approved' ? 'active' : ($trainer->status === 'applied' ? 'pending_review' : 'closed') }}">{{ $trainer->statusLabel() }}</span>
        </div>
        <dl class="kv">
            <dt>Teaching at</dt><dd>{{ $trainer->placeLabel() ?: '—' }}</dd>
            <dt>Trainings you take</dt><dd>{{ $trainer->teachingLabel() ?: '—' }}</dd>
            <dt>Hours a week</dt><dd>{{ $trainer->weekly_hours ?: 'Not set' }}</dd>
            <dt>Rate</dt><dd>{{ $trainer->rate_ksh > 0 ? 'KSH '.number_format((float) $trainer->rate_ksh, 2) : 'The office has not set your rate yet' }}</dd>
            <dt>Your note</dt><dd>{{ $trainer->note ?: '—' }}</dd>
        </dl>
        <p class="hint">The office holds the posting, the rate and the approval — ask them for a change and they edit the record.</p>
        @if ($trainer->status === 'applied')
            <p class="help">Waiting on the programme office to approve you. You can mark trainees once you are approved.</p>
        @elseif ($trainer->status !== 'approved')
            <p class="help">Your teaching role is <b>{{ $trainer->statusLabel() }}</b> — the office can restore it.
                Your E-Fundi membership is unaffected.</p>
        @endif
    </div>

    @if ($trainer->status === 'approved')
        @forelse ($trainer->cohorts as $c)
            <div class="card">
                <div class="section-head">
                    <h3>{{ $c->courseLabel() }}</h3>
                    <span class="tag">{{ $c->training?->name }}</span>
                    <span class="tag gold">{{ $c->reference }}</span>
                    <span class="status {{ $c->status === 'completed' ? 'closed' : 'active' }}">{{ $c->statusLabel() }}</span>
                </div>
                <p class="hint">{{ $c->dateLabel() }} · {{ $c->hoursLabel() }} · {{ $c->placeLabel() }}
                    · {{ $c->enrollments->count() }} on the roll</p>

                @if ($c->training && $c->training->modules->isNotEmpty())
                    <h4 class="dl-group">What you are expected to deliver — {{ $c->training->name }}</h4>
                    @foreach ($c->training->modules as $m)
                        <div class="row-line">
                            <b>{{ $m->position }}. {{ $m->title }}</b>
                            <span class="hint">{{ $m->hoursLabel() }}</span>
                            @if ($m->outcome)<span class="hint">{{ $m->outcome }}</span>@endif
                        </div>
                    @endforeach
                    <p class="hint">The office set these. Mark each one on a trainee below as they get through it —
                        that mark is what moves their track, and the office sees the same line you enter.</p>
                @endif

                <h4 class="dl-group">Mark what happened</h4>
                @forelse ($c->enrollments as $e)
                    @php $attendance = $e->attendancePct(); @endphp
                    <div class="row-line">
                        <b>{{ $e->ctp->full_name }}</b>
                        <span class="hint">{{ $e->ctp->ctp_code }}</span>
                        <span class="status {{ $e->state === 'approved' ? 'active' : 'pending_review' }}">{{ $e->stateLabel() }}</span>
                        <span class="tag">{{ $e->stageLabel() }}</span>
                        <span class="hint">{{ $attendance === null ? 'no marks yet' : $attendance.'% attendance' }}</span>
                    </div>
                    @if ($e->state === 'approved')
                        @include('partials.progress', ['enrollment' => $e, 'mode' => 'trainer'])

                        <h4 class="dl-group" style="margin-top:14px;">Their track</h4>
                        @include('partials.track', ['enrollment' => $e])

                        @include('partials.modules', ['enrollment' => $e, 'mode' => 'trainer'])

                        @include('partials.documents', [
                            'enrollment' => $e,
                            'mode' => 'trainer',
                            'modules' => $c->training?->modules ?? collect(),
                            'documentKinds' => $documentKinds,
                        ])
                    @else
                        <p class="hint">Their seat is not confirmed yet — the office has to approve it before you can mark them.</p>
                    @endif
                @empty
                    <p class="hint">Nobody is on this cohort’s roll yet.</p>
                @endforelse
                <p class="help">Everything you record here goes straight on the CTP’s training page. The office sees the
                    same lines, and uses them when they put a trainee forward for a certificate.</p>
            </div>
        @empty
            <div class="card">
                <h3>No cohort handed to you yet</h3>
                <p class="help">The programme office posts a cohort and names you as its trainer. Until then your
                    record sits here ready — and you can still apply for open projects under My projects.</p>
            </div>
        @endforelse
    @endif
@endif
@endsection

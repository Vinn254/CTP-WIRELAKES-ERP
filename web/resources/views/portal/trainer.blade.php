@extends('layouts.member')
@section('title', 'Training CTPs')

@php
    use App\Models\FundiRegistration;
    use App\Models\Trainer;

    $canTeach = in_array($member->tier, Trainer::TEACHING_TIERS, true);
    $cohorts = $trainers->flatMap(fn ($t) => $t->cohorts);
    $heldSites = $trainers->pluck('venue_id')->map(fn ($v) => (int) $v);
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">A senior fundi can train CTPs and be paid for it. Your E-Fundi record stays yours — this page is
        only the teaching role: the site you teach at, which of the programme's trainings you take, the hours you give and the
        trainees you mark. The office approves a trainer <b>site by site</b>, so an approval at one venue is not an approval
        at another. Training never takes away your right to apply for open projects.</p>
    <div class="member-stats">
        <div class="stat"><small>Grade</small><b>{{ \Illuminate\Support\Str::limit(FundiRegistration::tierLabel($member->tier), 18) }}</b></div>
        <div class="stat"><small>Sites applied</small><b>{{ $trainers->count() }}</b></div>
        <div class="stat"><small>Approved at</small><b>{{ $trainers->where('status', 'approved')->count() }}</b></div>
        <div class="stat"><small>Cohorts teaching</small><b>{{ $cohorts->count() }}</b></div>
        <div class="stat"><small>Trainees marked</small>
            <b>{{ $cohorts->sum(fn ($c) => $c->enrollments->where('state', 'approved')->count()) }}</b></div>
    </div>
</div>

@if (! $canTeach)
    <div class="card">
        <h3>Training CTPs</h3>
        <p class="help">You are registered as <b>{{ FundiRegistration::tierLabel($member->tier) }}</b>. Only a
            <b>senior fundi</b> trains CTPs — a junior fundi, an intern or an attachment applicant works on the job under a
            senior, and sits the training rather than teaching it. When the office moves you up to senior fundi, this page
            opens for a site application.</p>
        <p class="hint">Everything else your grade carries stays open: the projects you apply for and the e-waste you
            register. See <a href="{{ route('portal.efundi') }}">My E-Fundi record</a>.</p>
    </div>
@endif

@if ($trainers->isNotEmpty())
    <div class="card">
        <h3>Your trainer records — one per site</h3>
        @foreach ($trainers as $t)
            <div class="row-line">
                <b>{{ $t->venue->name ?? 'A site' }}</b>
                <span class="hint">{{ $t->venue->county ?? '' }}</span>
                <span class="status {{ $t->status === 'approved' ? 'active' : ($t->status === 'applied' ? 'pending_review' : 'closed') }}">{{ $t->statusLabel() }}</span>
            </div>
            <dl class="kv">
                <dt>Trainings you take</dt><dd>{{ $t->teachingLabel() ?: '—' }}</dd>
                <dt>Hours a week</dt><dd>{{ $t->weekly_hours ?: 'Not set' }}</dd>
                <dt>Rate</dt><dd>{{ $t->rate_ksh > 0 ? 'KSH '.number_format((float) $t->rate_ksh, 2) : 'The office has not set your rate yet' }}</dd>
                <dt>Your note</dt><dd>{{ $t->note ?: '—' }}</dd>
                <dt>Cohorts on this record</dt><dd>{{ $t->cohorts->count() }}</dd>
            </dl>
            @if ($t->status === 'applied')
                <p class="help">Waiting on the programme office to approve this site. You can mark trainees once it is approved.</p>
            @elseif ($t->status !== 'approved')
                <p class="help">This site is <b>{{ $t->statusLabel() }}</b> — the office can restore it, or you can send the
                    application in again below. Your E-Fundi membership is unaffected.</p>
            @endif
        @endforeach
        <p class="hint">The office holds the posting, the rate and the approval — ask them for a change and they edit the record.</p>
    </div>
@endif

@if ($canTeach)
    <details class="card add-record">
        <summary>+ Apply to train at a site</summary>
        <p class="help">Tell the office which site you can teach at and what you are strong in. Each site is its own
            application and its own approval — a cohort is only handed to a trainer the office approved at that venue.</p>
        <form method="POST" action="{{ route('portal.trainer.apply') }}" class="fee-form"
              data-confirm="Send this to the programme office?" data-title="Apply to train">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label>Site you would teach at <span class="req">*</span></label>
                    <select name="venue_id" required>
                        <option value="">Choose the venue</option>
                        @foreach ($venues as $v)
                            <option value="{{ $v->id }}" @disabled($heldSites->contains((int) $v->id))>
                                {{ $v->name }} — {{ $v->county }}{{ $heldSites->contains((int) $v->id) ? ' (you already have a record here)' : '' }}</option>
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
    </details>
@endif

@forelse ($trainers->where('status', 'approved') as $t)
    @forelse ($t->cohorts as $c)
        @php $roll = $c->enrollments; @endphp
        <div class="card">
            <div class="section-head">
                <h3>{{ $c->courseLabel() }}</h3>
                <span class="tag">{{ $c->training?->name }}</span>
                <span class="tag gold">{{ $c->reference }}</span>
                <span class="status {{ $c->status === 'completed' ? 'closed' : 'active' }}">{{ $c->statusLabel() }}</span>
            </div>
            <p class="hint">{{ $c->dateLabel() }} · {{ $c->hoursLabel() }} · teaching at {{ $t->venue->name ?? $c->placeLabel() }}
                · {{ $roll->count() }} on the roll, {{ $roll->where('state', 'approved')->count() }} holding a seat</p>

            <h4 class="dl-group">Your students — open one to record their performance</h4>
            <table class="data">
                <thead>
                    <tr>
                        <th>CTP</th><th>Seat</th><th>Stage</th><th>Attendance</th><th>Modules</th>
                        <th>Track</th><th>Records</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roll as $e)
                        @php
                            $attendance = $e->attendancePct();
                            $board = $e->moduleBoard();
                        @endphp
                        <tr>
                            <td><b>{{ $e->ctp->full_name }}</b><div class="hint">{{ $e->ctp->ctp_code }}</div></td>
                            <td><span class="status {{ $e->state === 'approved' ? 'active' : 'pending_review' }}">{{ $e->stateLabel() }}</span></td>
                            <td><span class="tag">{{ $e->stageLabel() }}</span></td>
                            <td>{{ $attendance === null ? 'not marked' : $attendance.'%' }}</td>
                            <td>{{ $board['modules']->isEmpty() ? '—' : $board['marked']->count().' of '.$board['modules']->count() }}</td>
                            <td><b>{{ $e->trackPct() }}%</b></td>
                            <td>{{ $e->entries->count() }}</td>
                            <td><a class="btn btn-sm btn-outline" href="{{ route('portal.trainer.trainee', $e) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:22px;">
                            Nobody is on this cohort’s roll yet. The office places CTPs into an intake, or a CTP applies for a seat themselves.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="hint">Open a student to add their sessions, mark the modules they got through and upload their work.
                Attendance under {{ \App\Models\Enrollment::ATTENDANCE_MINIMUM }}% keeps a trainee out of certification, so mark the days.</p>

            @if ($c->training && $c->training->modules->isNotEmpty())
                <h4 class="dl-group">What you are expected to deliver — {{ $c->training->name }}</h4>
                <table class="data">
                    <thead><tr><th>#</th><th>Module</th><th>Hours</th><th>What the trainee ends up able to do</th></tr></thead>
                    <tbody>
                        @foreach ($c->training->modules as $m)
                            <tr>
                                <td>{{ $m->position }}</td>
                                <td><b>{{ $m->title }}</b></td>
                                <td>{{ $m->hoursLabel() }}</td>
                                <td class="hint">{{ $m->outcome ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="hint">The office set these. Tick each one on a student’s own page as they get through it —
                    that mark is what moves their track, and the office sees the same line you enter.</p>
            @endif
        </div>
    @empty
        <div class="card">
            <h3>No cohort at {{ $t->venue->name ?? 'your site' }} yet</h3>
            <p class="help">You are approved for this site. The programme office posts a cohort and names its trainer —
                until then your record sits here ready, and you can still apply for open projects under My projects.</p>
        </div>
    @endforelse
@empty
    @if ($canTeach)
        <div class="card">
            <h3>You are not on the trainer list yet</h3>
            <p class="help">Pick a site above and send it in. The office approves a trainer for that site, and only then
                can a cohort booked there be handed to you.</p>
        </div>
    @endif
@endforelse
@endsection

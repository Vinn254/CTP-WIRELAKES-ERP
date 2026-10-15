@extends('layouts.member')
@section('title', 'Invited to you')

@php
    use App\Models\Project;
    use App\Models\ProjectMatch;
    use App\Support\Finance;

    $pill = ['invited' => 'pending_review', 'accepted' => 'active', 'awarded' => 'awarded',
        'declined' => 'rejected', 'no_reply' => 'rejected', 'shortlisted' => 'pending_review'];
    $groups = $invites->groupBy(fn (ProjectMatch $m) => match (true) {
        $m->status === 'invited' => 'Waiting on your answer',
        $m->status === 'accepted' => 'You took it — waiting on the office',
        $m->status === 'awarded' => 'Yours',
        default => 'Closed',
    });
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">The programme office puts a posted project to the E-Fundis whose grade, skills, county and
        availability fit it (§9.4). When it reaches you, you have <b>{{ $window }} hours</b> to take it or turn it
        down — the clock starts when the office sends the ask, not when you read it. Taking it is not the award:
        the office assigns the project, and it then appears under <a href="{{ route('portal.projects') }}">My projects</a>
        with the work order to follow. Silence is recorded as no answer, and the project goes to someone else.</p>
    <div class="member-stats">
        <div class="stat"><small>Waiting on your answer</small><b>{{ $counts['outstanding'] }}</b></div>
        <div class="stat"><small>You took, office to assign</small><b>{{ $counts['accepted'] }}</b></div>
        <div class="stat"><small>Awarded to you</small><b>{{ $counts['awarded'] }}</b></div>
        <div class="stat"><small>Declined or closed</small><b>{{ $counts['closed'] }}</b></div>
    </div>
</div>

@if ($invites->isEmpty())
    <div class="card">
        <p style="margin:0;color:var(--muted);">Nothing has been put to you yet. The desk searches the register as
            projects are posted, so the two things that bring a project to you are your record being
            <b>active</b>, your availability set to <b>available</b> under
            <a href="{{ route('portal.efundi') }}">My E-Fundi record</a>, and the skills and counties you keep there
            matching the work.</p>
    </div>
@endif

@foreach ($groups as $label => $rows)
    <h2 style="margin-top:26px;">{{ $label }} <span class="tag">{{ $rows->count() }}</span></h2>

    @foreach ($rows as $match)
        @php $project = $match->project; @endphp
        <div class="card">
            <div class="section-head">
                <h3>{{ $project->title }}</h3>
                <span class="status {{ $pill[$match->status] ?? 'pending_review' }}">{{ $match->statusLabel() }}</span>
            </div>
            <p class="hint" style="margin:0 0 10px;">
                {{ $project->reference }} · {{ $project->trade ?: 'trade not typed' }} ·
                {{ $project->categoryLabel() }} ·
                {{ collect([$project->county, $project->ward])->filter()->implode(', ') ?: 'place not set' }} ·
                {{ $project->hirer->organisation ?? 'hirer not stated' }}
            </p>
            <p class="hint" style="margin:0 0 10px;">
                {{ $project->deadline ? 'Must be done by '.$project->deadline->format('d M Y') : 'No deadline on the posting' }}
                · {{ $project->duration ?: 'duration not stated' }}
                @if ($project->budget !== null) · agreed for the job {{ Finance::ksh((float) $project->budget) }} @endif
            </p>

            <p style="white-space:pre-line;margin:0 0 12px;">{{ str($project->description)->limit(420) }}</p>

            @if ($project->providedMaterials->isNotEmpty())
                <h4 class="dl-group">Materials the programme supplies on this job</h4>
                <table class="data money-table">
                    <thead><tr><th>Material</th><th>Quantity</th><th>Cost each</th><th>Value</th><th>Handed over</th></tr></thead>
                    <tbody>
                        @foreach ($project->providedMaterials as $m)
                            <tr>
                                <td>{{ $m->name }}</td>
                                <td>{{ $m->quantityLabel() }}</td>
                                <td>{{ $m->unitCostLabel() }}</td>
                                <td><b>{{ $m->totalLabel() }}</b></td>
                                <td>{{ $m->isSupplied() ? 'given '.$m->supplied_on->format('d M Y') : 'at the store, on assignment' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="hint">Whatever is recorded as handed over is deducted from your payout (§14.6) — you are paid
                    for the work, not for materials the programme bought.</p>
            @endif

            <h4 class="dl-group">Why this came to you</h4>
            <p class="hint" style="margin:0 0 4px;">Round {{ $match->round }} of the search — scored
                <b>{{ $match->score }}%</b> against the {{ ProjectMatch::WINDOW_HOURS }}-hour rule.</p>
            <ul class="hint" style="margin:0;padding-left:18px;">
                @foreach ($match->matchedLines() as $line)<li>{{ $line }}</li>@endforeach
            </ul>

            @if ($match->status === 'invited')
                <div class="invite-actions" style="margin-top:14px;">
                    <p class="hint" style="margin:0 0 8px;"><b>{{ $match->timeLabel() }}</b>
                        — sent {{ $match->invited_on->format('d M Y, H:i') }}, due {{ $match->dueOn()->format('d M Y, H:i') }}.</p>
                    <form method="POST" action="{{ route('portal.invite.respond', $match) }}" class="fee-form"
                          data-confirm="Take {{ $project->reference }}? The office assigns it to you next." data-title="Take this project">
                        @csrf
                        <input type="hidden" name="answer" value="accept">
                        <div class="field"><label>Anything the office should know</label>
                            <input type="text" name="note" maxlength="200"
                                   placeholder="e.g. Can start Monday, I have my own ladder and tester."></div>
                        <button class="btn btn-lime btn-block" type="submit">I take this project</button>
                    </form>
                    <form method="POST" action="{{ route('portal.invite.respond', $match) }}" class="fee-form" style="margin-top:10px;"
                          data-confirm="Turn down {{ $project->reference }}?" data-title="Decline this project">
                        @csrf
                        <input type="hidden" name="answer" value="decline">
                        <div class="field"><label>Why, so the office can put the next one elsewhere</label>
                            <input type="text" name="note" maxlength="200"
                                   placeholder="e.g. I am on another job until the end of the month."></div>
                        <button class="btn btn-ghost btn-block" type="submit">I cannot take it</button>
                    </form>
                </div>
            @else
                @if ($match->note)<p class="hint" style="margin:10px 0 0;">Your answer: “{{ $match->note }}”</p>@endif
                @if ($match->responded_on)
                    <p class="hint" style="margin:4px 0 0;">You answered {{ $match->responded_on->format('d M Y, H:i') }}.</p>
                @endif
                @if ($match->status === 'no_reply')
                    <p class="hint" style="margin:4px 0 0;">The {{ ProjectMatch::WINDOW_HOURS }} hours ran out. Ask the
                        programme office to bring you forward on the next one.</p>
                @endif
                @if ($match->status === 'awarded')
                    <p class="hint" style="margin:4px 0 0;">
                        <a href="{{ route('portal.project', $project) }}">Open the project</a> — the materials list and
                        the payout follow from there.
                    </p>
                @endif
                @if ($match->status === 'accepted')
                    <p class="hint" style="margin:4px 0 0;">
                        {{ Project::STATUSES[$project->status] ?? $project->status }} on the register. When the office
                        assigns it to you, it appears under <a href="{{ route('portal.projects') }}">My projects</a>.
                    </p>
                @endif
            @endif
        </div>
    @endforeach
@endforeach
@endsection

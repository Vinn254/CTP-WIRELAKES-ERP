@extends('layouts.member')
@section('title', 'My work orders')

@php
    use App\Models\WorkOrder;
    use App\Support\Finance;

    $pill = ['issued' => 'pending_review', 'accepted' => 'pending_review', 'in_progress' => 'awarded',
        'completion_submitted' => 'awarded', 'confirmed' => 'active', 'disputed' => 'rejected',
        'closed' => 'active', 'cancelled' => 'rejected'];
@endphp

@section('content')
<div class="page-intro">
    <p class="sub">When the programme gives you a project, it becomes a <b>work order</b> in your name.
        This is where you sign it, see what the store is giving you, log your days on site, ask for the completion to
        be inspected, and answer a hirer who is not happy. Nothing here is typed by you except your own work —
        the office records the site visits, the milestones and the money.</p>
    <div class="member-stats">
        <div class="stat"><small>Waiting for your signature</small><b>{{ $waiting }}</b></div>
        <div class="stat"><small>Working now</small><b>{{ $working }}</b></div>
        <div class="stat"><small>Questions to answer</small><b>{{ $questions }}</b></div>
        <div class="stat"><small>Finished or stopped</small><b>{{ $done }}</b></div>
    </div>
</div>

@if ($orders->isEmpty())
    <div class="card">
        <div class="section-head"><h3>No work order yet</h3></div>
        <p class="help">A work order is generated the moment the programme awards you a project. Two things bring one
            to you: an active record on the <a href="{{ route('portal.efundi') }}">E-Fundi register</a>, and the
            matching desk putting a project to you — answer it on
            <a href="{{ route('portal.invitations') }}">Invited to you</a> within the 48 hours.</p>
    </div>
@else
    @foreach ($orders as $o)
        <div class="card">
            <div class="section-head">
                <h3><a href="{{ route('portal.work.show', $o) }}">{{ $o->reference }}</a> — {{ $o->project->title }}</h3>
                <span class="status {{ $pill[$o->status] ?? 'pending_review' }}">{{ $o->statusLabel() }}</span>
            </div>
            <table class="data">
                <tbody>
                    <tr>
                        <td>Site</td>
                        <td>{{ collect([$o->project->county, $o->project->ward])->filter()->implode(', ') ?: 'not stated' }}
                            · for {{ $o->project->hirer->organisation ?: 'the hirer named on the project' }}</td>
                        <td>Agreed for the job</td><td><b>{{ $o->agreedLabel() }}</b></td>
                    </tr>
                    <tr>
                        <td>Dates</td>
                        <td>{{ $o->start_on?->format('d M Y') ?? 'not confirmed' }}
                            → {{ $o->deadline?->format('d M Y') ?? 'no deadline' }}</td>
                        <td>Progress</td><td>{{ $o->percent_complete }}%</td>
                    </tr>
                    <tr>
                        <td>Payment schedule</td>
                        <td>{{ WorkOrder::SCHEDULES[$o->schedule] ?? $o->schedule }}</td>
                        <td>Stages signed</td>
                        <td>{{ $o->milestones->where('status', \App\Models\WorkOrderMilestone::SIGNED)->count() }}
                            of {{ $o->milestones->count() }}</td>
                    </tr>
                    <tr>
                        <td>Materials</td>
                        <td>{{ $o->project->providedMaterials->count() }} line(s) listed —
                            {{ Finance::ksh($o->materialsIssuedKsh()) }} issued so far</td>
                        <td>Opened</td><td>{{ $o->created_at->format('d M Y') }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="hint">
                @if ($o->status === 'issued')
                    <b>Your signature is what starts this job.</b> Open the order, accept it and confirm the day you start.
                @elseif ($o->status === 'disputed')
                    <b>A hirer raised an issue.</b> You have 48 hours from the day it was recorded to answer it —
                    open the order to read it and reply.
                @elseif ($o->status === 'completion_submitted')
                    You asked for the completion to be inspected; the office is arranging the hirer's visit.
                @elseif ($o->status === 'confirmed')
                    The hirer confirmed the work. The payout is Finance's to calculate and release.
                @elseif ($o->status === 'cancelled')
                    {{ $o->closure_note ?: 'The order was stopped.' }}
                @else
                    {{ $o->percent_complete }}% logged. Keep your days on the log so the supervisor can see the sequence.
                @endif
                <a href="{{ route('portal.work.show', $o) }}">Open the order →</a>
            </p>
        </div>
    @endforeach
@endif

<div class="card">
    <div class="section-head"><h3>How a job runs, and who does each part</h3></div>
    <ol class="steps">
        <li><b>You accept and sign the order</b><div class="hint"> — with the day you start</div></li>
        <li><b>The store issues your materials</b><div class="hint"> — what they hand over is deducted from your payout</div></li>
        <li><b>You log each day</b><div class="hint"> — text, photos and the percentage</div></li>
        <li><b>A supervisor signs stages off</b><div class="hint"> — the split on your order decides the stages</div></li>
        <li><b>You request completion</b><div class="hint"> — with the final photos</div></li>
        <li><b>The hirer inspects</b><div class="hint"> — the office records what they say; if they raise an issue you get 48 hours to answer</div></li>
        <li><b>Finance pays you</b><div class="hint"> — the payout appears on <a href="{{ route('portal.earnings') }}">My earnings</a> with its own ID</div></li>
    </ol>
</div>
@endsection

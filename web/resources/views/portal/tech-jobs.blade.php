@extends('layouts.member')
@section('title', 'My repair jobs')

@section('content')
<div class="page-intro">
    <p class="sub">When the office opens a fault on a device and assigns it to you, it comes here.
        You answer it with what you did to fix it. The office then confirms the fix and closes the ticket — only they
        can close it, and while it is open the asset stays flagged on the register.</p>
    <div class="member-stats">
        <div class="stat"><small>Waiting on you</small><b>{{ $jobs->whereIn('status', ['assigned', 'in_progress'])->count() }}</b></div>
        <div class="stat"><small>Recorded, awaiting confirm</small><b>{{ $jobs->where('status', 'resolved')->count() }}</b></div>
    </div>
</div>

@if ($jobs->isEmpty())
    <div class="card">
        <div class="section-head"><h3>No repair job is assigned to you</h3></div>
        <p class="help">A technology fault appears here only when the office assigns the ticket to your E-Fundi record.
            Keep your trade and availability up to date, and the office can send the right job to you.</p>
    </div>
@else
    <div class="card">
        <div class="section-head"><h3>Faults assigned to you</h3></div>
        @foreach ($jobs as $job)
            <div class="card gaps" style="border-left:4px solid {{ $job->status === 'resolved' ? 'var(--muted)' : 'var(--danger)' }};margin-bottom:14px;">
                <div>
                    <b>{{ $job->title }}</b>
                    <span class="tag {{ $job->priority === 'urgent' ? '' : 'gold' }}">{{ $job->priorityLabel() }}</span>
                    <div class="hint">
                        {{ $job->asset->name ?? 'an asset' }} ({{ $job->asset->asset_code ?? '—' }})
                        · ticket {{ $job->reference }}
                        · opened {{ $job->created_at?->format('d M Y') }}
                    </div>
                </div>
                <p style="margin:8px 0 4px;"><b>The fault:</b> {{ $job->fault }}</p>

                @if ($job->status === 'resolved')
                    <p class="hint">You recorded the fix — "{{ $job->resolution }}" on {{ $job->resolved_on?->format('d M Y H:i') }}.
                        Waiting for the office to confirm and close it.</p>
                @else
                    <form method="POST" action="{{ route('portal.tech.resolve', $job) }}" class="fee-form" data-title="Record the fix">
                        @csrf
                        <div class="field">
                            <label>What you did to fix it <span class="req">*</span></label>
                            <textarea name="resolution" rows="2" required maxlength="5000"
                                      placeholder="Exactly the repair, so the office can confirm it">{{ $job->resolution }}</textarea>
                        </div>
                        <button class="btn btn-sm btn-primary" type="submit">Send the fix</button>
                        <span class="hint">The asset returns to service when the fix is recorded.</span>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="section-head"><h3>How a repair ticket runs</h3></div>
    <ol class="steps">
        <li><b>The office opens the fault</b><div class="hint">on the device or licence it sits on</div></li>
        <li><b>They assign it to you</b><div class="hint">the ticket appears here</div></li>
        <li><b>You record the fix</b><div class="hint">what you did, so it can be checked</div></li>
        <li><b>The office confirms and closes</b><div class="hint">only they close a job — your entry marks it fixed, not closed</div></li>
    </ol>
    <p class="hint">Work on programme projects stays on <a href="{{ route('portal.work') }}">My work orders</a> — this screen is only for technology faults assigned to you.</p>
</div>
@endsection

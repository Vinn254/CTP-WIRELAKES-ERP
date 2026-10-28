@extends('layouts.admin')
@section('title', 'Certification')

@php
    use App\Models\Certification;
    use App\Models\Enrollment;
    $tabs = ['all' => 'All'] + Certification::STATUSES;
@endphp

@section('content')
<h1>Certification</h1>
<p class="page-lede">Verify trainees at 100% and issue or revoke their certificates.</p>

<div class="stat-row">
    <div class="stat"><small>At 100%, not queued yet</small><b>{{ $ready->count() }}</b></div>
    <div class="stat"><small>Awaiting an executive</small><b>{{ $counts['recommended'] ?? 0 }}</b></div>
    <div class="stat"><small>Issued</small><b>{{ $counts['approved'] ?? 0 }}</b></div>
    <div class="stat"><small>Revoked</small><b>{{ $counts['revoked'] ?? 0 }}</b></div>
</div>

@if (! $isExecutive)
    <p class="hint" style="margin:0 0 14px;">
        You are signed in as <b>{{ auth()->user()->role }}</b>, so you can put trainees forward but the
        approve / revoke buttons are closed to you — a certificate is signed off by a programme executive.
    </p>
@endif

@if ($ready->isNotEmpty())
    <div class="card">
        <h3>Their track is at 100% and nothing is waiting on an executive</h3>
        <p class="hint">A track that reaches 100% normally queues the trainee here by itself. These are the ones
            that need the office to put forward — a recommendation that was declined, or a record the office is
            re-opening. Putting them forward snapshots their theory score, practical score and attendance.
            The {{ Enrollment::ATTENDANCE_MINIMUM }}% attendance rule is enforced here.</p>
        <table class="data">
            <thead>
                <tr><th>Trainee</th><th>CTP ID</th><th>Cohort</th><th>Stage</th><th>Attendance</th><th>Scores</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($ready as $e)
                    @php $attendance = $e->attendancePct(); @endphp
                    <tr>
                        <td><b>{{ $e->ctp->full_name }}</b>
                            <div class="hint"><a href="{{ route('admin.track.show', $e) }}">track {{ $e->trackPct() }}%</a></div></td>
                        <td>{{ $e->ctp->ctp_code }}</td>
                        <td><a href="{{ route('admin.cohorts.show', $e->cohort_id) }}">{{ $e->cohort->reference }}</a>
                            <div class="hint">{{ $e->cohort->courseLabel() }}</div></td>
                        <td>{{ $e->stageLabel() }}</td>
                        <td>
                            @if ($attendance === null)
                                <span class="hint">nothing marked</span>
                            @else
                                <span class="status {{ $attendance < Enrollment::ATTENDANCE_MINIMUM ? 'closed' : 'active' }}">{{ $attendance }}%</span>
                            @endif
                        </td>
                        <td>{{ $e->avgScore('theory') !== null ? $e->avgScore('theory').'/100' : 'theory —' }}
                            <div class="hint">{{ $e->avgScore('practical') !== null ? $e->avgScore('practical').'/100' : 'practical —' }}</div></td>
                        <td class="actions">
                            <form method="POST" action="{{ route('admin.certification.recommend', $e) }}" class="mini-form"
                                  data-confirm="Put {{ $e->ctp->full_name }} forward for certification?" data-title="Put forward">
                                @csrf
                                <button class="btn btn-sm btn-lime" type="submit"
                                    @disabled($attendance !== null && $attendance < Enrollment::ATTENDANCE_MINIMUM)>Put forward</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<form method="GET" action="{{ route('admin.certification.index') }}" class="filter-bar inline-filter">
    <label class="field">
        <span>Certificate status</span>
        <select name="status" onchange="this.form.submit()">
            @foreach ($tabs as $s => $lbl)
                <option value="{{ $s }}" @selected($status === $s)>{{ $lbl }}@if ($s !== 'all') ({{ $counts[$s] ?? 0 }})@endif</option>
            @endforeach
        </select>
    </label>
    <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
</form>

<table class="data">
    <thead>
        <tr>
            <th>Certificate</th><th>Trainee</th><th>Cohort</th><th>Track now</th><th>Attendance</th>
            <th>Theory</th><th>Practical</th><th>Put forward by</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($certifications as $c)
            <tr>
                <td><b>{{ $c->certificate_code ?: '—' }}</b>
                    @if (! $c->certificate_code)<div class="hint">issued on approval</div>@endif</td>
                <td>{{ $c->enrollment->ctp->full_name }}
                    <div class="hint">{{ $c->enrollment->ctp->ctp_code }}</div></td>
                <td><a href="{{ route('admin.cohorts.show', $c->enrollment->cohort_id) }}">{{ $c->enrollment->cohort->reference }}</a>
                    <div class="hint">{{ $c->enrollment->cohort->courseLabel() }}</div></td>
                <td><a class="fee-tag {{ $c->enrollment->trackPct() >= 100 ? 'paid' : 'waived' }}"
                       href="{{ route('admin.track.show', $c->enrollment) }}">{{ $c->enrollment->trackPct() }}%</a></td>
                <td>{{ $c->attendance_pct !== null ? $c->attendance_pct.'%' : '—' }}</td>
                <td>{{ $c->theory_score ?? '—' }}</td>
                <td>{{ $c->practical_score ?? '—' }}</td>
                <td>{{ $c->recommender?->name ?: '—' }}
                    @if ($c->approver)<div class="hint">{{ $c->status === 'approved' ? 'signed by '.$c->approver->name.' · '.$c->approved_on?->format('d M Y') : $c->approver->name }}</div>@endif</td>
                <td><span class="status {{ $c->status === 'approved' ? 'active' : ($c->status === 'recommended' ? 'pending_review' : 'closed') }}">{{ $c->statusLabel() }}</span>
                    @if ($c->reason)<div class="hint">{{ \Illuminate\Support\Str::limit($c->reason, 60) }}</div>@endif</td>
                <td class="actions">
                    @if ($c->status === 'recommended')
                        <form method="POST" action="{{ route('admin.certification.approve', $c) }}" class="mini-form"
                              data-confirm="Approve and issue {{ $c->enrollment->ctp->full_name }}'s certificate?" data-title="Issue certificate">
                            @csrf
                            <button class="btn btn-sm btn-lime" type="submit" @disabled(! $isExecutive)>Approve</button>
                        </form>
                        <details class="need-form" style="display:inline-block;">
                            <summary class="btn btn-sm btn-ghost" @if (! $isExecutive) style="pointer-events:none;opacity:.5;" @endif>Decline</summary>
                            <form method="POST" action="{{ route('admin.certification.decline', $c) }}" class="fee-form" style="text-align:left;">
                                @csrf
                                <div class="field">
                                    <label>Why this is not being certified <span class="req">*</span></label>
                                    <textarea name="reason" rows="3" maxlength="1000" required></textarea>
                                </div>
                                <button class="btn btn-outline btn-block" type="submit" style="margin-top:10px;">Decline recommendation</button>
                            </form>
                        </details>
                    @elseif ($c->status === 'approved')
                        <details class="need-form" style="display:inline-block;">
                            <summary class="btn btn-sm btn-ghost" @if (! $isExecutive) style="pointer-events:none;opacity:.5;" @endif>Revoke</summary>
                            <form method="POST" action="{{ route('admin.certification.revoke', $c) }}" class="fee-form" style="text-align:left;">
                                @csrf
                                <div class="field">
                                    <label>Reason for revoking {{ $c->certificate_code }} <span class="req">*</span></label>
                                    <textarea name="reason" rows="3" maxlength="1000" required></textarea>
                                </div>
                                <button class="btn btn-outline btn-block" type="submit" style="margin-top:10px;">Revoke certificate</button>
                            </form>
                        </details>
                    @else
                        <span class="hint">closed</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty-cell">
                Nothing has been put forward yet. A trainee reaches this queue from their
                <a href="{{ route('admin.track.index') }}">track</a> the moment it reaches 100%.
            </td></tr>
        @endforelse
    </tbody>
</table>

<p class="hint" style="margin-top:14px;">
    An approved certificate is the qualification that opens <b>E-Fundi registration</b> — the office
    confirms that separately in Membership, so a certified CTP is not made a fundi behind their back.
</p>
@endsection

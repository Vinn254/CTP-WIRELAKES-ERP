@php
    /* The results of one cohort on one table — the office and the trainer read the same lines. */
    $seated = $enrollments->where('state', 'approved');
    $rows = $seated->values()->map(function ($e) {
        $board = $e->moduleBoard();

        return [
            'e' => $e,
            'attendance' => $e->attendancePct(),
            'modules' => $board['modules']->isEmpty() ? null : $board['marked']->count().' / '.$board['modules']->count(),
            'theory' => $e->avgScore('theory'),
            'practical' => $e->avgScore('practical'),
            'assessment' => $e->avgScore('assessment'),
            'track' => $e->trackPct(),
        ];
    });
    $average = fn (string $key) => $rows->pluck($key)->filter(fn ($v) => is_numeric($v))->avg();
@endphp

<div class="stat-row result-stats">
    <div class="stat"><small>On the roll</small><b>{{ $enrollments->count() }}</b></div>
    <div class="stat"><small>Holding a seat</small><b>{{ $seated->count() }}</b></div>
    <div class="stat"><small>Average track</small><b>{{ $average('track') === null ? '—' : round($average('track'), 1).'%' }}</b></div>
    <div class="stat"><small>Track complete</small><b>{{ $rows->where('track', '>=', 100)->count() }}</b></div>
    <div class="stat"><small>Below {{ \App\Models\Enrollment::ATTENDANCE_MINIMUM }}% attendance</small>
        <b>{{ $rows->filter(fn ($r) => $r['attendance'] !== null && $r['attendance'] < \App\Models\Enrollment::ATTENDANCE_MINIMUM)->count() }}</b></div>
    <div class="stat"><small>Certified</small><b>{{ $seated->filter(fn ($e) => $e->certification && $e->certification->status === 'issued')->count() }}</b></div>
</div>

<table class="data result-table">
    <thead>
        <tr>
            <th>CTP</th><th>Seat</th><th>Stage</th><th>Attendance</th><th>Modules</th>
            <th>Theory</th><th>Practical</th><th>Assessment</th><th>Track</th><th>Certificate</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $r)
            @php $e = $r['e']; $cert = $e->certification; @endphp
            <tr>
                <td>
                    <b>{{ $e->ctp->full_name }}</b>
                    <div class="hint">{{ $e->ctp->ctp_code }}</div>
                </td>
                <td><span class="status {{ $e->state === 'approved' ? 'active' : ($e->state === 'pending' ? 'pending_review' : 'closed') }}">{{ $e->stateLabel() }}</span></td>
                <td><span class="tag">{{ $e->stageLabel() }}</span></td>
                <td @if ($r['attendance'] !== null && $r['attendance'] < \App\Models\Enrollment::ATTENDANCE_MINIMUM) class="flag-bad" @endif>
                    {{ $r['attendance'] === null ? 'not marked' : $r['attendance'].'%' }}
                </td>
                <td>{{ $r['modules'] ?? '—' }}</td>
                <td>{{ $r['theory'] === null ? '—' : $r['theory'] }}</td>
                <td>{{ $r['practical'] === null ? '—' : $r['practical'] }}</td>
                <td>{{ $r['assessment'] === null ? '—' : $r['assessment'] }}</td>
                <td class="{{ $r['track'] >= 100 ? 'flag-good' : 'flag-partial' }}"><b>{{ $r['track'] }}%</b></td>
                <td>
                    @if ($cert)
                        <span class="status {{ $cert->status }}">{{ $cert->statusLabel() }}</span>
                        @if ($cert->certificate_code)<div class="hint">{{ $cert->certificate_code }}</div>@endif
                    @elseif ($e->canBeCertified())
                        <span class="tag gold">ready to put forward</span>
                    @else
                        <span class="hint">not yet</span>
                    @endif
                </td>
                <td class="actions"><a class="btn btn-sm btn-outline" href="{{ $open($e) }}">Open</a>
                    @if ($studentRecord ?? false)
                        <a class="btn btn-sm btn-ghost" href="{{ route('admin.students.show', $e->ctp_id) }}">Student record</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="11" class="empty-cell">
                Nobody holds a seat in this cohort yet, so there are no results to show. The office gives a seat, or a CTP
                applies for one while the cohort is open.
            </td></tr>
        @endforelse
    </tbody>
</table>
<p class="hint">Scores are the mean of what has been recorded on each trainee’s own page — theory, practical and the final
    assessment are out of 100. {{ $whoMarks }}</p>

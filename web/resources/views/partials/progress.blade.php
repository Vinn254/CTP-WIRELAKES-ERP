@php
    use App\Models\ProgressEntry;

    $mode = $mode ?? null;
    $canRecord = in_array($mode, ['admin', 'trainer'], true) && $enrollment->state === 'approved';
    $action = $mode === 'admin' ? route('admin.track.progress', $enrollment) : route('portal.trainer.progress');
    $days = ($entries ?? $enrollment->entries)->sortBy([['entry_date', 'asc'], ['id', 'asc']]);
    $attendance = $days->where('kind', 'attendance');

    // A record must fall inside the cohort period, so the form never opens with a date the guard will refuse.
    $starts = $enrollment->cohort?->starts_on;
    $ends = $enrollment->cohort?->ends_on;
    $defaultDate = now()->toDateString();
    if ($starts && $defaultDate < $starts->toDateString()) {
        $defaultDate = $starts->toDateString();
    } elseif ($ends && $defaultDate > $ends->toDateString()) {
        $defaultDate = $ends->toDateString();
    }
    $outsideWindow = $defaultDate !== now()->toDateString();
@endphp

<div class="section-head record-head">
    <h4 class="dl-group" style="margin:0;">Training record ({{ $days->count() }})</h4>
    @if ($canRecord)
        <details class="need-form add-record" @if (($open ?? false) && $errors->any()) open @endif>
            <summary class="btn btn-sm btn-lime">+ Add a record for {{ $enrollment->ctp->full_name }}</summary>
            <form method="POST" action="{{ $action }}" class="fee-form"
                  data-confirm="Record this on {{ $enrollment->ctp->full_name }}'s track?" data-title="Record progress">
                @csrf
                @if ($mode === 'trainer')<input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">@endif
                <div class="form-grid-3">
                    <div class="field">
                        <label>What happened <span class="req">*</span></label>
                        <select name="kind" required>
                            @foreach (ProgressEntry::KINDS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Date <span class="req">*</span></label>
                        <input type="date" name="entry_date" required
                               min="{{ $starts?->toDateString() }}"
                               max="{{ $ends?->toDateString() }}"
                               value="{{ old('entry_date', $defaultDate) }}">
                    </div>
                    <div class="field">
                        <label>Came in? (attendance only)</label>
                        <select name="present">
                            <option value="">Not an attendance mark</option>
                            <option value="1">Yes — present</option>
                            <option value="0">No — absent</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Theory / practical / assessment score /100</label>
                        <input type="number" name="score" min="0" max="100" step="0.5">
                    </div>
                    <div class="field">
                        <label>Hours covered in the session</label>
                        <input type="number" name="hours" min="0" max="400">
                    </div>
                    <div class="field">
                        <label>What they did</label>
                        <input type="text" name="note" maxlength="1000"
                               placeholder="e.g. Wired the training board end to end">
                    </div>
                </div>
                <p class="hint">Inside the cohort period only — {{ $enrollment->cohort?->dateLabel() }}.
                    @if ($outsideWindow)
                        Today is outside that period, so the date is set to the nearest day the record allows.
                    @endif
                    {{ $mode === 'trainer' ? 'What you record goes straight on the CTP’s track and the office sees it the same moment.' : 'Use this for the marks a partner school sent in — the office records them on the school’s behalf.' }}</p>
                <button class="btn btn-lime btn-block" type="submit" style="margin-top:10px;">
                    {{ $mode === 'trainer' ? 'Record and send to the office' : 'Record on the track' }}
                </button>
            </form>
        </details>
    @endif
</div>

<table class="data">
    <thead>
        <tr>
            <th>Date</th><th>What happened</th><th>Counts toward</th><th>Score /100</th><th>Hours</th>
            <th>Attendance</th><th>What they did</th><th>Recorded by</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($days as $entry)
            <tr>
                <td>{{ $entry->entry_date->format('d M Y') }}</td>
                <td><b>{{ $entry->kindLabel() }}</b></td>
                <td><span class="tag gray">{{ $entry->countsToward() }}</span></td>
                <td>{{ $entry->score !== null ? $entry->score : '—' }}</td>
                <td>{{ $entry->hours ? $entry->hours.' h' : '—' }}</td>
                <td>
                    @if ($entry->kind === 'attendance')
                        <span class="status {{ $entry->present ? 'active' : 'closed' }}">{{ $entry->present ? 'Present' : 'Absent' }}</span>
                    @else
                        <span class="hint">—</span>
                    @endif
                </td>
                <td>{{ $entry->note ?: '—' }}</td>
                <td><span class="hint">{{ $entry->recorderLabel() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:22px;">
                Nothing has been recorded on this trainee yet.
                {{ $canRecord ? 'Use “+ Add a record” for the first session, mark or school report.' : 'Their trainer or the office will put the first line in.' }}
            </td></tr>
        @endforelse
    </tbody>
</table>

@if ($attendance->isNotEmpty())
    <p class="hint">{{ $attendance->where('present', true)->count() }} of {{ $attendance->count() }}
        days marked were attended — {{ $enrollment->attendancePct() }}% against the
        {{ \App\Models\Enrollment::ATTENDANCE_MINIMUM }}% rule.</p>
@endif

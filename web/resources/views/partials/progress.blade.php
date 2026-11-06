@php
    use App\Models\Enrollment;
    use App\Models\ProgressEntry;

    $mode = $mode ?? null;
    $canRecord = in_array($mode, ['admin', 'trainer', 'teaching'], true) && $enrollment->state === 'approved';
    $action = match ($mode) {
        'admin' => route('admin.track.progress', $enrollment),
        'teaching' => route('admin.teaching.progress', $enrollment),
        default => route('portal.trainer.progress'),
    };
    // The Marks button opens the unit's own sheet on the surface this record is being read from.
    $marksRoute = match ($mode) {
        'admin', 'teaching' => 'admin.teaching.marks',
        'trainer' => 'portal.trainer.marks',
        default => null,
    };
    $byTrainer = in_array($mode, ['trainer', 'teaching'], true);
    $days = ($entries ?? $enrollment->entries)->sortBy([['entry_date', 'asc'], ['id', 'asc']]);
    $attendance = $days->where('kind', 'attendance');
    $units = $units ?? $enrollment->unitList();

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

@once
@include('partials.sheet-start', ['name' => 'add-progress', 'title' => 'Add a record',
    'sub' => 'One line on the trainee’s track — a session, a mark, an attendance day or a school report.'])
 <div class="sheet-body" hidden></div>
@include('partials.sheet-end')
@endonce

<div class="section-head record-head">
    <h4 class="dl-group" style="margin:0;">Marks sheet ({{ $units->count() }}
        {{ Str::plural('unit', $units->count()) }} · {{ $days->count() }}
        {{ Str::plural('record', $days->count()) }})</h4>
    @if ($canRecord)
        <button class="btn btn-sm btn-lime" type="button" data-sheet="add-progress"
                data-sheet-form="#progress-form-{{ $enrollment->id }}">+ Add a record</button>
        <div hidden id="progress-form-{{ $enrollment->id }}">
            <form method="POST" action="{{ $action }}" class="fee-form"
                  data-confirm="Record this on {{ $enrollment->ctp->full_name }}'s track?" data-title="Record progress">
                @csrf
                <input type="hidden" name="_sheet" value="add-progress">
                @if ($mode === 'trainer')<input type="hidden" name="enrollment_id" value="{{ $enrollment->id }}">@endif
                <div class="field">
                    <label>Whose track <span class="req">*</span></label>
                    <input type="text" value="{{ $enrollment->ctp->full_name }}" disabled>
                </div>
                <div class="form-grid-3" style="margin-top:10px;">
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
                    {{ $byTrainer ? 'What you record goes straight on the CTP’s track and the office sees it the same moment.' : 'Use this for the marks a partner school sent in — the office records them on the school’s behalf.' }}</p>
                <div class="sheet-actions">
                    <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                    <button class="btn btn-sm btn-lime" type="submit">
                        {{ $byTrainer ? 'Record and send to the office' : 'Record on the track' }}
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>

@include('partials.marks-sheet', [
    'enrollment' => $enrollment,
    'units' => $units,
    'head' => false,
    'marksRoute' => $marksRoute,
])

@if ($attendance->isNotEmpty())
    <p class="hint">{{ $attendance->where('present', true)->count() }} of {{ $attendance->count() }}
        days marked were attended — {{ $enrollment->attendancePct() }}% against the
        {{ Enrollment::ATTENDANCE_MINIMUM }}% rule.</p>
@endif

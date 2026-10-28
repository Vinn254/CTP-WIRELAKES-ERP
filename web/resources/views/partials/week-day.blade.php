@php
    use App\Models\TimeLog;

    // The day being written: the one clicked on the sheet, the one last posted, or the day the page offered.
    $picked = request('day', old('log_date', $defaultDay));
    $row = $sheet['logs']->first(fn ($r) => $r->log_date->toDateString() === $picked);
@endphp
<details class="card need-form" @if ($row || old('log_date') || $errors->any()) open @endif>
    <summary>{{ $row ? 'Correct '.$picked.' on the sheet' : 'Write a day on the sheet' }}</summary>
    <form method="POST" action="{{ $action }}" class="fee-form"
          data-confirm="Save this day to the week sheet?" data-title="Save day">
        @csrf
        <input type="hidden" name="trainer" value="{{ $trainer->id }}">
        <div class="form-grid-3">
            <div class="field">
                <label>Date <span class="req">*</span></label>
                <input type="date" name="log_date" required value="{{ $picked }}"
                       max="{{ now()->toDateString() }}">
            </div>
            <div class="field">
                <label>Time in</label>
                <input type="time" name="time_in" value="{{ old('time_in', $row?->clockIn() === '-' ? '' : $row?->clockIn()) }}">
            </div>
            <div class="field">
                <label>Time out</label>
                <input type="time" name="time_out" value="{{ old('time_out', $row?->clockOut() === '-' ? '' : $row?->clockOut()) }}">
                <div class="help">Hours are counted from these two. A day caps at 8 hours.</div>
            </div>
        </div>
        <div class="field">
            <label>Tasks accomplished <span class="req">*</span></label>
            <textarea name="task" rows="2" maxlength="500" required
                      placeholder="What you actually did that day">{{ old('task', $row?->task) }}</textarea>
        </div>
        <div class="form-grid-3">
            <div class="field">
                <label>Initials <span class="req">*</span></label>
                <input type="text" name="initials" maxlength="10" required value="{{ old('initials', $row?->initials) }}"
                       placeholder="e.g. JMK">
            </div>
            <div class="field">
                <label>Dept</label>
                <input type="text" name="department" maxlength="90" value="{{ old('department', $row?->department) }}"
                       placeholder="e.g. Training">
            </div>
            <div class="field">
                <label>Units</label>
                <input type="number" name="units" min="0" max="99" value="{{ old('units', $row?->units) }}"
                       placeholder="Sessions or modules delivered">
            </div>
        </div>
        @if ($trainings->isNotEmpty())
            <div class="form-grid-2">
                <div class="field">
                    <label>Programme</label>
                    <select name="training_id">
                        <option value="">Not one of the programmes</option>
                        @foreach ($trainings as $training)
                            <option value="{{ $training->id }}" @selected((int) old('training_id', $row?->training_id) === $training->id)>
                                {{ $training->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="help">Only the programmes this posting is licensed for.</div>
                </div>
                <div class="field">
                    <label>Cohort</label>
                    <select name="cohort_id">
                        <option value="">No single cohort</option>
                        @foreach ($cohorts as $cohort)
                            <option value="{{ $cohort->id }}" @selected((int) old('cohort_id', $row?->cohort_id) === $cohort->id)>
                                {{ $cohort->reference }} — {{ $cohort->courseLabel() }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
        <button class="btn btn-primary" type="submit">{{ $row ? 'Save the correction' : 'Write the day' }}</button>
        @if ($row)
            <a class="btn btn-sm btn-outline" href="{{ $self }}">Back to the week</a>
        @endif
    </form>
    <p class="hint">{{ TimeLog::STATUSES['draft'] }} until you sign the whole week. A day you have already written is
        corrected here, never added a second time.</p>
</details>

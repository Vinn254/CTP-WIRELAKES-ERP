@php
    use App\Models\TimeLog;
    use App\Support\Departments;

    /** @var array $day */
    $row = $day['row'];
    $name = 'day-'.$day['date'];
    $picked = old('log_date', $day['date']);
    $dept = old('department', $row?->department ?: ($defaults['department'] ?? ''));
    $depts = collect(Departments::all())->pluck('name')->when($dept !== '', fn ($q) => $q->push($dept)->unique());
    $today = now()->toDateString();
@endphp
@include('partials.sheet-start', ['name' => $name,
    'title' => ($row ? 'Correct ' : 'Write ').$day['day'].' '.\Illuminate\Support\Carbon::parse($day['date'])->format('d M Y')])
<form method="POST" action="{{ $action }}" class="fee-form"
      data-confirm="Save this day to the week sheet?" data-title="Save day">
    @csrf
    <input type="hidden" name="_sheet" value="{{ $name }}">
    @foreach ($carry as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <div class="form-grid-3">
        <div class="field">
            <label>Day of this week <span class="req">*</span></label>
            <select name="log_date" required>
                @foreach ($sheet['days'] as $option)
                    <option value="{{ $option['date'] }}" @selected($picked === $option['date'])>
                        {{ $option['day'] }} — {{ \Illuminate\Support\Carbon::parse($option['date'])->format('d M Y') }}
                    </option>
                @endforeach
            </select>
            <div class="help">Monday to Friday of {{ $sheet['label'] }}.</div>
        </div>
        <div class="field">
            <label>Time in</label>
            <input type="time" name="time_in" value="{{ old('time_in', $row?->clockIn() === '-' ? '' : $row?->clockIn()) }}"
                   max="{{ $today }}">
        </div>
        <div class="field">
            <label>Time out</label>
            <input type="time" name="time_out" value="{{ old('time_out', $row?->clockOut() === '-' ? '' : $row?->clockOut()) }}"
                   max="{{ $today }}">
            <div class="help">Hours are counted from these two. A day caps at 8 hours.</div>
        </div>
    </div>
    <div class="field">
        <label>Tasks accomplished <span class="req">*</span></label>
        <textarea name="task" rows="3" maxlength="500" required
                  placeholder="What you actually did that day">{{ old('task', $row?->task) }}</textarea>
    </div>
    <div class="form-grid-3">
        <div class="field">
            <label>Initials <span class="req">*</span></label>
            <input type="text" name="initials" maxlength="10" required
                   value="{{ old('initials', $row?->initials ?: ($defaults['initials'] ?? '')) }}" placeholder="e.g. JMK">
        </div>
        <div class="field">
            <label>Dept</label>
            <select name="department">
                <option value="">Not named</option>
                @foreach ($depts as $option)
                    <option value="{{ $option }}" @selected($dept === $option)>{{ $option }}</option>
                @endforeach
            </select>
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
                <div class="help">Only the programmes this account is licensed for.</div>
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
    <div class="sheet-actions">
        <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
        <button class="btn btn-primary" type="submit">{{ $row ? 'Save the correction' : 'Write the day' }}</button>
    </div>
</form>
<p class="hint">{{ TimeLog::STATUSES['draft'] }} until you sign the whole week. A day already on the sheet is
    corrected here, never added a second time.</p>
@include('partials.sheet-end')

@php
    use App\Models\TimeLog;

    /** @var array $day */
    $row = $day['row'];
    $name = 'day-'.$day['date'];
    $picked = old('log_date', $day['date']);
    $depts = collect($defaults['departments'] ?? ['Training'])->values();
    $dept = old('department', $row?->department ?: ($defaults['department'] ?? ''));
    if (! $depts->contains($dept)) {
        $dept = $depts->first();
    }
    $today = now()->toDateString();
@endphp
@include('partials.sheet-start', ['name' => $name,
    'title' => ($row ? 'Correct ' : 'Write ').$day['day'].' '.\Illuminate\Support\Carbon::parse($day['date'])->format('d M Y')])
<form method="POST" action="{{ $action }}" class="fee-form" data-clock
      data-confirm="Save this day to the week sheet?" data-title="Save day">
    @csrf
    <input type="hidden" name="_sheet" value="{{ $name }}">
    <input type="hidden" name="month" value="{{ $sheet['month'] }}">
    <input type="hidden" name="week" value="{{ $sheet['week_no'] }}">
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
            <div class="help">An hour for lunch comes off these two.</div>
        </div>
        <div class="field">
            <label>Total hours</label>
            <input type="text" data-clock-hours readonly
                   value="{{ $row && $row->hours ? number_format((float) $row->hours, 2, '.', '') : '' }}">
            <div class="help">Counts itself — time out minus time in, less one hour for lunch. Caps at 8.</div>
        </div>
    </div>
    <div class="field">
        <label>Tasks accomplished <span class="req">*</span></label>
        <textarea name="task" rows="3" maxlength="500" required
                  placeholder="What you actually did that day">{{ old('task', $row?->task) }}</textarea>
    </div>
    <div class="form-grid-3">
        <div class="field">
            <label>Recorded as</label>
            <input type="text" value="{{ $defaults['initials'] }}" disabled>
            <div class="help">Your account's initials, put on the sheet by itself.</div>
        </div>
        <div class="field">
            <label>Department</label>
            @if ($depts->count() > 1)
                <select name="department">
                    @foreach ($depts as $option)
                        <option value="{{ $option }}" @selected($dept === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <div class="help">Only the desks your account sits at.</div>
            @else
                <input type="text" value="{{ $dept }}" disabled>
                <div class="help">Taken from your account.</div>
            @endif
        </div>
        <div class="field">
            <label>Units</label>
            <input type="number" name="units" min="0" max="99" readonly
                   value="{{ old('units', $row?->units) }}">
            <div class="help">Counts itself — two units for every hour on the day.</div>
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

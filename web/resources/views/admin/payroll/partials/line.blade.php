@php
    use App\Models\PayrollLine;
    use App\Support\Departments;

    if (! ($canEdit ?? false)) {
        return;
    }

    $money = fn ($v) => number_format((float) $v, 2, '.', '');
    $name = 'pay-'.$line->id;
    $holdName = 'hold-'.$line->id;
    $depts = collect(Departments::all())->pluck('name')->when($line->department, fn ($q) => $q->push($line->department)->unique());
@endphp
@include('partials.sheet-start', ['name' => $name, 'title' => $line->name.' — '.$run->periodLabel()])
<form method="POST" action="{{ route('admin.payroll.line', $line) }}" class="fee-form"
      data-confirm="Save this month's pay for {{ $line->name }}?" data-title="Set pay">
    @csrf
    <input type="hidden" name="_sheet" value="{{ $name }}">
    <div class="form-grid-3">
        <div class="field">
            <label>Person <span class="req">*</span></label>
            <input type="text" name="name" maxlength="150" required value="{{ old('name', $line->name) }}">
        </div>
        <div class="field">
            <label>Job title</label>
            <input type="text" name="job_title" maxlength="120" value="{{ old('job_title', $line->job_title) }}">
        </div>
        <div class="field">
            <label>Department</label>
            <select name="department">
                <option value="">Not named</option>
                @foreach ($depts as $option)
                    <option value="{{ $option }}" @selected(old('department', $line->department) === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <h4>Pay and allowances</h4>
    <div class="form-grid-3">
        @foreach (array_slice(array_keys(PayrollLine::COLUMNS), 0, 4) as $column)
            <div class="field">
                <label>{{ PayrollLine::COLUMNS[$column] }} (KSh)</label>
                <input type="number" step="0.01" min="0" max="99999999" name="{{ $column }}"
                       value="{{ old($column, $money($line->{$column})) }}" placeholder="0.00">
            </div>
        @endforeach
    </div>
    <div class="field">
        <label>What the other allowance is</label>
        <input type="text" name="other_allowance_note" maxlength="90"
               value="{{ old('other_allowance_note', $line->other_allowance_note) }}" placeholder="e.g. Field allowance">
    </div>

    <h4>Deductions</h4>
    <div class="form-grid-3">
        @foreach (array_slice(array_keys(PayrollLine::COLUMNS), 4) as $column)
            <div class="field">
                <label>{{ PayrollLine::COLUMNS[$column] }} (KSh)</label>
                <input type="number" step="0.01" min="0" max="99999999" name="{{ $column }}"
                       value="{{ old($column, $money($line->{$column})) }}" placeholder="0.00">
            </div>
        @endforeach
    </div>
    <div class="field">
        <label>What the other deduction is</label>
        <input type="text" name="other_deduction_note" maxlength="90"
               value="{{ old('other_deduction_note', $line->other_deduction_note) }}" placeholder="e.g. Salary advance">
    </div>

    <div class="sheet-actions">
        <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
        <button class="btn btn-primary" type="submit">Save the figures</button>
    </div>
</form>
<p class="hint">Gross is basic pay plus the allowances; net is gross less every deduction. Saving replaces what was on
    the line — the sheet never shows two figures for the same month.</p>
@include('partials.sheet-end')

@if ($line->status === 'unpaid')
    @include('partials.sheet-start', ['name' => $holdName, 'title' => 'Hold '.$line->name.' back this month'])
    <form method="POST" action="{{ route('admin.payroll.line.hold', $line) }}" class="fee-form"
          data-confirm="Hold this person off this month's pay?" data-title="Hold">
        @csrf
        <input type="hidden" name="_sheet" value="{{ $holdName }}">
        <div class="field">
            <label>Why they are held back <span class="req">*</span></label>
            <textarea name="hold_reason" rows="3" maxlength="300" required
                      placeholder="e.g. Unpaid leave for the month"></textarea>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-primary" type="submit">Hold the line</button>
        </div>
    </form>
    <p class="hint">A held line stays on the sheet with its reason and is not paid — it is never removed.</p>
    @include('partials.sheet-end')
@endif

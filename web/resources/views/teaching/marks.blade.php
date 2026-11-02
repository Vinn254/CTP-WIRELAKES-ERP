@extends($layout)
@section('title', 'Marks — '.$cohort->reference)

@php
    use App\Models\Enrollment;
    use App\Models\UnitGrading;
    use App\Models\UnitMarkSheet;
    use App\Support\Grading;

    $self = $url('marks', ['cohort' => $cohort->id]);
    $openUnit = (int) request('training_module_id', $sheet?->training_module_id ?? 0);
    $openDay = request('covered_on', $sheet?->covered_on?->toDateString() ?? '');
    // The weights are the Registrar's own for this unit, not a fixed table — the grade a sheet produces is their
    // arithmetic. A unit they have not set yet is graded on the default weights.
    $weights = $grading?->weights() ?? UnitGrading::firstOrNew([])->fill(Grading::DEFAULTS)->weights();
    $passMark = $grading?->pass_pct ?? Grading::DEFAULTS['pass_pct'];
    $byCell = [];

    foreach ($sheet?->marks ?? [] as $mark) {
        $byCell[$mark->enrollment_id][$mark->kind] = $mark;
    }

    $kindAverage = function (string $kind) use ($sheet) {
        $scores = collect($sheet?->marks ?? [])->filter(fn ($m) => $m->kind === $kind)->pluck('score')->filter();

        return $scores->isEmpty() ? null : round($scores->avg(), 1);
    };
@endphp

@section('content')
@include('teaching.head')

<form method="GET" action="{{ $self }}" class="filter-bar">
    <div class="field">
        <label>Unit</label>
        <select name="training_module_id" required>
            <option value="">Pick the unit the marks are for</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected($openUnit === $unit->id)>{{ $unit->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Day the unit was taken</label>
        <input type="date" name="covered_on" value="{{ $openDay }}" max="{{ today()->toDateString() }}"
               min="{{ $cohort->starts_on?->toDateString() }}" required>
    </div>
    <button class="btn btn-sm btn-primary" type="submit">Open the sheet</button>
    <span class="hint">Every mark is out of {{ $outOf }} — the same scale the CTP's track is counted on.</span>
</form>

@if ($sheet && $roll->isEmpty())
    <p class="flash-error">No CTP holds an approved seat in this cohort, so there is nobody to mark.</p>
@endif

@if ($sheet && $roll->isNotEmpty())
    <div class="card">
        <div class="section-head">
            <h3>{{ $sheet->unitLabel() }} — {{ $sheet->dateLabel() }}</h3>
            <span class="status {{ $sheet->statusClass() }}">{{ $sheet->statusLabel() }}</span>
        </div>

        @if ($sheet->exists && $sheet->status === 'returned')
            <p class="flash-error" style="margin:0 0 14px"><b>Sent back by the Registrar.</b>
                {{ $sheet->review_note }}</p>
        @endif

        <div class="weight-row">
            @foreach ($kinds as $key => $label)
                <div class="weight-card {{ $weights[$key] > 0 ? '' : 'gray' }}">
                    <small>{{ $label }} weight</small>
                    <b>{{ $weights[$key] }}%</b>
                    <span>{{ $weights[$key] > 0 ? 'Counts on this unit’s grade.' : 'Not used on this unit.' }}</span>
                    <em>average on this sheet: {{ $kindAverage($key) ?? '—' }}</em>
                </div>
            @endforeach
            <div class="weight-card gray">
                <small>Attendance weight</small>
                <b>{{ $weights['attendance'] }}%</b>
                <span>Ticked on the day's register, not on this sheet.</span>
                <em>{{ Enrollment::ATTENDANCE_MINIMUM }}% of the days marked is needed</em>
            </div>
            <div class="weight-card gray">
                <small>Pass mark</small>
                <b>{{ $passMark }}</b>
                <span>The grade a student has to reach on this unit.</span>
                <em><a href="{{ route('admin.cohorts.results', ['cohort' => $cohort->id]) }}">
                    Set by the Registrar on the cohort’s results</a></em>
            </div>
        </div>

        @if ($sheet->isEditable() || $canCorrect)
            <form method="POST" action="{{ $url('sheet.save', ['cohort' => $cohort->id]) }}" class="fee-form" data-ask>
                @csrf
                <input type="hidden" name="training_module_id" value="{{ $sheet->training_module_id ?: $openUnit }}">
                <input type="hidden" name="covered_on" value="{{ $sheet->covered_on?->toDateString() ?? $openDay }}">
                <div class="table-scroll">
                    <table class="data pin-first pin-last mark-sheet">
                        <thead>
                            <tr>
                                <th>Reg. no.</th><th>CTP</th>
                                @foreach ($kinds as $key => $label)
                                    <th class="num">{{ $label }} / {{ $outOf }} <small>({{ $weights[$key] }}%)</small></th>
                                @endforeach
                                <th class="num">Weighed on this sheet</th><th class="num">Attendance</th>
                                <th class="num">Unit grade</th><th class="num">Track</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roll as $seat)
                                @php
                                    $row = $byCell[$seat->id] ?? [];
                                    $typed = collect($kinds)->mapWithKeys(fn ($l, $k) => [$k => ($row[$k] ?? null)?->score])->filter();
                                    $attendance = $seat->attendancePct();
                                    $track = $seat->trackPct();
                                    $grade = $sheet->module ? Grading::compile($seat, $sheet->module) : null;
                                @endphp
                                <tr>
                                    <td class="nowrap">{{ $seat->ctp->ctp_code }}</td>
                                    <td class="student"><b>{{ $seat->ctp->full_name }}</b></td>
                                    @foreach ($kinds as $key => $label)
                                        @php $mark = $row[$key] ?? null; @endphp
                                        <td class="cell">
                                            <input type="number" step="0.5" min="0" max="{{ $outOf }}" class="num"
                                                   data-weight="{{ $weights[$key] }}"
                                                   name="marks[{{ $seat->id }}][{{ $key }}]" placeholder="—"
                                                   value="{{ old('marks.'.$seat->id.'.'.$key, $mark?->score) }}">
                                            @if ($mark?->note)<span class="hint">{{ $mark->note }}</span>@endif
                                        </td>
                                    @endforeach
                                    <td class="num" data-continuous data-attendance="{{ $attendance }}"
                                        data-attweight="{{ $weights['attendance'] }}">
                                        {{ $typed->isEmpty() ? '—' : round(collect($typed)->map(fn ($s, $k) => $s * $weights[$k] / 100)->sum(), 1).'%' }}
                                    </td>
                                    <td class="num {{ $attendance !== null && $attendance < Enrollment::ATTENDANCE_MINIMUM ? 'flag-bad' : '' }}">
                                        {{ $attendance === null ? 'not marked' : $attendance.'%' }}
                                    </td>
                                    <td class="num {{ $grade === null ? '' : ($grade['passed'] ? 'flag-good' : 'flag-bad') }}">
                                        @if ($grade && $grade['marked'])
                                            <b>{{ $grade['pct'] }}</b> {{ $grade['letter'] }}
                                            <div class="hint">{{ $grade['remark'] }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="num {{ $track >= 100 ? 'flag-good' : 'flag-partial' }}"><b>{{ $track }}%</b></td>
                                    <td class="actions">
                                        <a class="btn btn-sm btn-ghost" href="{{ $url('trainee', ['enrollment' => $seat->id]) }}"
                                           title="The CTP's own record">Record</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="field">
                    <label>Note on the sheet</label>
                    <input type="text" name="note" maxlength="500"
                           placeholder="Marked on the day, invigilated by …" value="{{ old('note', $sheet->note) }}">
                </div>

                <p class="hint" data-locked-note @if ($sheet->exists) hidden @endif>
                    The attendance, unit grade and track columns are counted from what the Registrar has already
                    approved — they move when this sheet is approved, not while it is being filled in.</p>

                <div class="module-foot">
                    <button class="btn btn-lime btn-sm" type="submit" data-save>
                        {{ $sheet->status === 'approved' ? 'Save the correction' : 'Save the marks' }}</button>
                    @if ($sheet->exists && $sheet->isEditable())
                        <button class="btn btn-primary btn-sm" type="submit" name="forward" value="1" data-save
                                data-confirm="Send these marks to the Registrar? They reach each CTP's record when the Registrar approves them.">
                            Save and submit to the Registrar</button>
                    @endif
                </div>
            </form>
        @else
            <p class="hint">This sheet is {{ Str::lower($sheet->statusLabel()) }} — marks already with the Registrar are
                answered by them. A sheet sent back opens for correcting here again.</p>
        @endif
    </div>
@endif

<h3 class="dl-group">Sheets handed in for this cohort</h3>
<div class="table-scroll">
    <table class="data result-table">
        <thead>
            <tr><th>Unit</th><th>Day taken</th><th>Marks on the sheet</th><th>Average</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($sheets as $paper)
                @php
                    $scores = $paper->marks->pluck('score')->filter();
                    $written = $paper->marks->whereNotNull('score')->count();
                @endphp
                <tr>
                    <td><b>{{ $paper->unitLabel() }}</b>@if ($paper->note)<div class="hint">{{ $paper->note }}</div>@endif</td>
                    <td class="nowrap">{{ $paper->dateLabel() }}</td>
                    <td>{{ $written }} of {{ $roll->count() * count($kinds) }}</td>
                    <td class="num">{{ $scores->isEmpty() ? '—' : round($scores->avg(), 1).' / '.$outOf }}</td>
                    <td><span class="status {{ $paper->statusClass() }}">{{ $paper->statusLabel() }}</span></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-outline"
                           href="{{ $self }}?training_module_id={{ $paper->training_module_id }}&covered_on={{ $paper->covered_on?->toDateString() }}">
                            {{ $paper->isEditable() || $canCorrect ? 'Open and correct' : 'Open the sheet' }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-cell">
                    No mark sheet written for this cohort yet. Pick the unit and the day above to write the first one.
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
    // The weighed figure is live as the marks are typed: each box carries the weight the Registrar set for it, and
    // attendance comes in at its own weight from the register. The unit grade and track columns stay on what the
    // Registrar has already approved.
    document.addEventListener('input', function (e) {
        const cell = e.target.closest('.mark-sheet input[type=number]');
        if (! cell) return;

        const row = cell.closest('tr');
        const out = row.querySelector('[data-continuous]');
        let total = 0, any = false;

        row.querySelectorAll('.cell input').forEach(function (i) {
            const weight = Number(i.dataset.weight) || 0;
            if (i.value === '' || weight === 0) return;
            any = true;
            total += Number(i.value) * weight / 100;
        });

        const att = out.dataset.attendance === '' ? null : Number(out.dataset.attendance);
        const attWeight = Number(out.dataset.attweight) || 0;

        if (att !== null && attWeight > 0) {
            any = true;
            total += att * attWeight / 100;
        }

        out.textContent = any ? total.toFixed(1) + '%' : '—';
    });
</script>
@endsection

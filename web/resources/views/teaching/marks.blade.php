@extends($layout)
@section('title', 'Marks — '.$cohort->reference)

@php
    use App\Models\Enrollment;
    use App\Models\UnitMarkSheet;

    $self = $url('marks', ['cohort' => $cohort->id]);
    $openUnit = (int) request('training_module_id', $sheet?->training_module_id ?? 0);
    $openDay = request('covered_on', $sheet?->covered_on?->toDateString() ?? '');
    $weights = UnitMarkSheet::weightNotes();
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
                @php $part = $weights[$key]['part']; @endphp
                <div class="weight-card">
                    <small>{{ $label }} weight</small>
                    <b>{{ Enrollment::TRACK_WEIGHTS[$part] }}%</b>
                    <span>{{ $weights[$key]['note'] }}</span>
                    <em>average on this sheet: {{ $kindAverage($key) ?? '—' }}</em>
                </div>
            @endforeach
            <div class="weight-card gray">
                <small>Attendance weight</small>
                <b>{{ Enrollment::TRACK_WEIGHTS['attendance'] }}%</b>
                <span>Ticked on the day's register, not on this sheet.</span>
                <em>{{ Enrollment::ATTENDANCE_MINIMUM }}% of the days marked is needed</em>
            </div>
        </div>

        @if ($sheet->isEditable())
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
                                    <th class="num">{{ $label }} / {{ $outOf }}</th>
                                @endforeach
                                <th class="num">On this sheet</th><th class="num">Attendance</th>
                                <th class="num">Track</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roll as $seat)
                                @php
                                    $row = $byCell[$seat->id] ?? [];
                                    $typed = collect($kinds)->keys()->map(fn ($k) => $row[$k]?->score)->filter();
                                    $attendance = $seat->attendancePct();
                                    $track = $seat->trackPct();
                                @endphp
                                <tr>
                                    <td class="nowrap">{{ $seat->ctp->ctp_code }}</td>
                                    <td class="student"><b>{{ $seat->ctp->full_name }}</b></td>
                                    @foreach ($kinds as $key => $label)
                                        @php $mark = $row[$key] ?? null; @endphp
                                        <td class="cell">
                                            <input type="number" step="0.5" min="0" max="{{ $outOf }}" class="num"
                                                   name="marks[{{ $seat->id }}][{{ $key }}]" placeholder="—"
                                                   value="{{ old('marks.'.$seat->id.'.'.$key, $mark?->score) }}">
                                            @if ($mark?->note)<span class="hint">{{ $mark->note }}</span>@endif
                                        </td>
                                    @endforeach
                                    <td class="num" data-continuous>{{ $typed->isEmpty() ? '—' : round($typed->avg(), 1).'%' }}</td>
                                    <td class="num {{ $attendance !== null && $attendance < Enrollment::ATTENDANCE_MINIMUM ? 'flag-bad' : '' }}">
                                        {{ $attendance === null ? 'not marked' : $attendance.'%' }}
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
                    The attendance and track columns are counted from what the Registrar has already approved — they
                    move when this sheet is approved, not while it is being filled in.</p>

                <div class="module-foot">
                    <button class="btn btn-lime btn-sm" type="submit" data-save>Save the marks</button>
                    @if ($sheet->exists)
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
                            {{ $paper->isEditable() ? 'Open and correct' : 'Open the sheet' }}</a>
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
    // The sheet's own average is live as the marks are typed; the track columns stay on what is already approved.
    document.addEventListener('input', function (e) {
        const cell = e.target.closest('.mark-sheet input[type=number]');
        if (! cell) return;

        const row = cell.closest('tr');
        const out = row.querySelector('[data-continuous]');
        const scores = Array.from(row.querySelectorAll('.cell input'))
            .map(function (i) { return i.value === '' ? null : Number(i.value); })
            .filter(function (v) { return v !== null && ! Number.isNaN(v); });

        out.textContent = scores.length ? (scores.reduce(function (a, b) { return a + b; }, 0) / scores.length).toFixed(1) + '%' : '—';
    });
</script>
@endsection

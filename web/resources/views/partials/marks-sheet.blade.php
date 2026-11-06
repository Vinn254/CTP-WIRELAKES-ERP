@php
    use App\Models\Enrollment;
    use App\Models\UnitMarkSheet;
    use App\Support\Grading;

    $kinds = UnitMarkSheet::KINDS;
    $outOf = UnitMarkSheet::OUT_OF;
    $units = $units ?? ($enrollment->cohort?->unitList() ?? collect());
    $attendance = $enrollment->attendancePct();
    $track = $enrollment->trackPct();
    $open = $marksRoute ?? null;
@endphp

<div class="section-head">
    <h3>Marks sheet</h3>
    <span class="hint">{{ $units->count() }} {{ Str::plural('unit', $units->count()) }} on this intake</span>
</div>

@if ($units->isEmpty())
    <p class="hint">This intake has no units registered on it yet, so there is nothing to mark. The Registrar ticks the
        units on <b>Units and trainers</b> and each one appears here.</p>
@else
    <div class="table-scroll">
        <table class="data pin-first mark-sheet">
            <thead>
                <tr>
                    <th>Unit</th>
                    @foreach ($kinds as $label)
                        <th class="num">{{ $label }} / {{ $outOf }}</th>
                    @endforeach
                    <th class="num">Weighed on this sheet</th><th class="num">Attendance</th>
                    <th class="num">Unit grade</th><th class="num">Track</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($units as $unit)
                    @php
                        $grade = Grading::compile($enrollment, $unit);
                        $onSheet = round(collect($grade['parts'])->except('attendance')->sum('got'), 1);
                    @endphp
                    <tr>
                        <td class="student"><b>{{ $unit->title }}</b>
                            <div class="hint">{{ Grading::weightLine(Grading::for($unit)) }}</div>
                        </td>
                        @foreach (array_keys($kinds) as $kind)
                            @php $part = $grade['parts'][$kind]; @endphp
                            <td class="num">
                                {{ $part['score'] === null ? '—' : $part['score'] }}
                                <small>({{ $part['weight'] }}%)</small>
                            </td>
                        @endforeach
                        <td class="num">{{ $grade['marked'] ? $onSheet.'%' : '—' }}</td>
                        <td class="num {{ $attendance !== null && $attendance < Enrollment::ATTENDANCE_MINIMUM ? 'flag-bad' : '' }}">
                            {{ $attendance === null ? 'not marked' : $attendance.'%' }}
                        </td>
                        <td class="num {{ $grade['marked'] ? ($grade['passed'] ? 'flag-good' : 'flag-bad') : '' }}">
                            @if ($grade['marked'])
                                <b>{{ $grade['pct'] }}</b> {{ $grade['letter'] }}
                                <div class="hint">{{ $grade['remark'] }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="num {{ $track >= 100 ? 'flag-good' : 'flag-partial' }}"><b>{{ $track }}%</b></td>
                        <td class="actions">
                            @if ($open)
                                <a class="btn btn-sm btn-ghost"
                                   href="{{ route($open, ['cohort' => $enrollment->cohort_id, 'training_module_id' => $unit->id]) }}">
                                    Marks</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="hint">Every mark is out of {{ $outOf }} and weighed the way the Registrar set the unit — the figure lands
        here once the Registrar approves the sheet, and a corrected sheet replaces it.</p>
@endif

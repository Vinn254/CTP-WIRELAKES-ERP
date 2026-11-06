@php
    use App\Models\Enrollment;
    use App\Models\UnitGrading;
    use App\Support\Grading;

    $units = $units ?? $enrollment->unitList();
    $attendance = $enrollment->attendancePct();
    $track = $enrollment->trackPct();
    $open = $marksRoute ?? null;
    $title = $title ?? 'Marks sheet';
    $head = $head ?? true;
@endphp

@if ($head)
    <div class="section-head">
        <h3>{{ $title }}</h3>
        <span class="hint">{{ $units->count() }} {{ Str::plural('unit', $units->count()) }} on this intake ·
            {{ $track }}% of the track</span>
    </div>
@endif

@if ($units->isEmpty())
    <p class="hint">This intake has no units registered on it yet, so there is nothing to mark. The Registrar ticks the
        units on <b>Units and trainers</b> and each one appears here.</p>
@else
    <div class="table-scroll">
        <table class="data pin-first mark-sheet">
            <thead>
                <tr>
                    <th>Unit</th><th>Semester</th>
                    @foreach (UnitGrading::PARTS as $label)
                        <th class="num">{{ $label }}</th>
                    @endforeach
                    <th class="num">Weighed grade</th><th class="num">Pass mark</th><th>Remark</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($units as $unit)
                    @php
                        $grade = Grading::compile($enrollment, $unit);
                        $g = Grading::for($unit);
                        $onSheet = round(collect($grade['parts'])->except('attendance')->sum('got'), 1);
                    @endphp
                    <tr>
                        <td class="student"><b>{{ $unit->title }}</b>
                            @if ($g->note)<div class="hint">{{ $g->note }}</div>@endif
                        </td>
                        <td>{{ $unit->termLabel() }}</td>
                        @foreach (array_keys(UnitGrading::PARTS) as $part)
                            @php $item = $grade['parts'][$part]; @endphp
                            <td class="num">
                                {{ $item['score'] === null ? '—' : $item['score'] }}
                                <small>{{ $item['weight'] }}%</small>
                            </td>
                        @endforeach
                        <td class="num {{ $grade['marked'] ? ($grade['passed'] ? 'flag-good' : 'flag-bad') : '' }}">
                            @if ($grade['marked'])
                                <b>{{ $grade['pct'] }}</b> {{ $grade['letter'] }}
                                @if ($attendance !== null && $onSheet !== $grade['pct'])
                                    <div class="hint">{{ $onSheet }}% before attendance</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="num">{{ $g->pass_pct }}</td>
                        <td>{{ $grade['remark'] }}</td>
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
    <p class="hint">Every mark is out of {{ \App\Models\UnitMarkSheet::OUT_OF }} and weighed the way the Registrar set
        the unit. A trainee under {{ Enrollment::ATTENDANCE_MINIMUM }}% attendance cannot be certified whatever the
        marks say.</p>
@endif

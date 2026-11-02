{{-- One cohort's marks on one table, unit by unit: what each part carries, what it is weighed at, the grade it comes
     to and the mark a student has to reach. The Registrar edits the grading from the same row. --}}
@php
    use App\Models\UnitGrading;
    use App\Support\Grading;
@endphp
<div class="table-scroll">
    <table class="data">
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
            @forelse ($board as $row)
                @php $g = $row['grading']; @endphp
                <tr>
                    <td><b>{{ $row['unit']->title }}</b>
                        @if ($g->note)<div class="hint">{{ $g->note }}</div>@endif</td>
                    <td>{{ $row['term'] ?? ($term ?? '—') }}</td>
                    @foreach (array_keys(UnitGrading::PARTS) as $part)
                        @php
                            $score = $part === 'attendance'
                                ? $row['attendance']
                                : ($row['marks'][$part] ?? null);
                        @endphp
                        <td class="num">
                            {{ $score === null ? '—' : $score }}
                            <small>{{ $g->{"{$part}_pct"} }}%</small>
                        </td>
                    @endforeach
                    <td class="num">
                        {{ $row['pct'] === null ? '—' : $row['pct'] }}
                        @if ($row['pct'] !== null)
                            <div class="hint">{{ Grading::letter($row['pct']) }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $g->pass_pct }}</td>
                    <td>
                        {{ $row['pct'] === null ? 'Not marked yet' : ($row['pct'] >= (float) $g->pass_pct ? 'Pass' : 'Below the pass mark') }}
                        <div class="hint">{{ $row['graded'] }} {{ Str::plural('trainee', $row['graded']) }} marked</div>
                    </td>
                    <td class="actions">
                        @if ($canCorrect)
                            <button class="btn btn-sm btn-outline" type="button"
                                    data-sheet="grade-{{ $row['unit']->id }}">Edit</button>
                            <a class="btn btn-sm btn-ghost" href="{{ $marksUrl($row['unit']) }}">Marks</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="empty-cell">
                    This programme has no units yet, so there is nothing to mark. The office puts units under a
                    programme on <a href="{{ route('admin.trainings.index') }}">Training</a> first.
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($canCorrect)
    @foreach ($board as $row)
        @include('partials.grading-sheet', ['unit' => $row['unit'], 'grading' => $row['grading']])
    @endforeach
@endif

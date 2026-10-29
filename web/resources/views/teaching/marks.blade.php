@extends($layout)
@section('title', 'Marks — '.$cohort->reference)

@php
    $self = $url('marks', ['cohort' => $cohort->id]);
    $openUnit = (int) request('training_module_id', $sheet?->training_module_id ?? 0);
    $openDay = request('covered_on', $sheet?->covered_on?->toDateString() ?? '');
    $byCell = [];
    foreach ($sheet?->marks ?? [] as $mark) {
        $byCell[$mark->enrollment_id][$mark->kind] = $mark;
    }
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

@if ($sheet)
    <div class="card">
        <div class="section-head">
            <h3>{{ $sheet->unitLabel() }} — {{ $sheet->dateLabel() }}</h3>
            <span class="status {{ $sheet->statusClass() }}">{{ $sheet->statusLabel() }}</span>
        </div>
        @if ($sheet->exists && $sheet->status === 'returned')
            <p class="flash-error" style="margin:0 0 14px"><b>Sent back by the Registrar.</b>
                {{ $sheet->review_note }}</p>
        @endif

        @if ($sheet->isEditable())
            <form method="POST" action="{{ $url('sheet.save', ['cohort' => $cohort->id]) }}" class="fee-form">
                @csrf
                <input type="hidden" name="training_module_id" value="{{ $sheet->training_module_id }}">
                <input type="hidden" name="covered_on" value="{{ $sheet->covered_on?->toDateString() ?? $openDay }}">
                <div class="table-scroll">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>CTP</th>
                                @foreach ($kinds as $key => $label)
                                    <th>{{ $label }} / {{ $outOf }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roll as $seat)
                                <tr>
                                    <td><b>{{ $seat->ctp->full_name }}</b>
                                        <span class="hint">{{ $seat->ctp->ctp_code }}</span>
                                    </td>
                                    @foreach ($kinds as $key => $label)
                                        @php $mark = $byCell[$seat->id][$key] ?? null; @endphp
                                        <td>
                                            <input type="number" step="0.5" min="0" max="{{ $outOf }}"
                                                   name="marks[{{ $seat->id }}][{{ $key }}]"
                                                   placeholder="—"
                                                   value="{{ old('marks.'.$seat->id.'.'.$key, $mark?->score) }}">
                                            <input type="text" name="mark_note[{{ $seat->id }}][{{ $key }}]"
                                                   maxlength="190" placeholder="note"
                                                   value="{{ old('mark_note.'.$seat->id.'.'.$key, $mark?->note) }}">
                                        </td>
                                    @endforeach
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
                <div class="module-foot">
                    <button class="btn btn-lime btn-sm" type="submit">Save the marks</button>
                </div>
            </form>

            @if ($sheet->exists)
                <form method="POST" action="{{ $url('sheet.forward', ['cohort' => $cohort->id, 'sheet' => $sheet->id]) }}"
                      data-confirm="Send these marks to the Registrar?">
                    @csrf
                    <button class="btn btn-sm btn-primary" type="submit">Forward to the Registrar</button>
                </form>
            @endif
        @else
            <p class="hint">This sheet is {{ Str::lower($sheet->statusLabel()) }} — marks already with the Registrar are
                answered by them. A sheet sent back opens for correcting here again.</p>
        @endif
    </div>
@endif

<h3 class="dl-group">Sheets handed in for this cohort</h3>
@if ($sheets->isEmpty())
    <p class="hint">Nothing on this unit yet. Pick the unit and the day above to write the first sheet.</p>
@endif
@foreach ($sheets as $paper)
    @php
        $scores = $paper->marks->pluck('score')->filter();
        $written = $paper->marks->whereNotNull('score')->count();
    @endphp
    <div class="card">
        <div class="section-head">
            <h3>{{ $paper->unitLabel() }}</h3>
            <span class="status {{ $paper->statusClass() }}">{{ $paper->statusLabel() }}</span>
        </div>
        <p class="ah-meta">
            <span>{{ $paper->dateLabel() }}</span>
            <span>{{ $written }} {{ Str::plural('mark', $written) }} on the sheet</span>
            @if ($scores->isNotEmpty())<span>average {{ round($scores->avg(), 1) }} / {{ $outOf }}</span>@endif
        </p>
        @if ($paper->note)<p class="hint">{{ $paper->note }}</p>@endif
        <a class="btn btn-sm btn-outline"
           href="{{ $self }}?training_module_id={{ $paper->training_module_id }}&covered_on={{ $paper->covered_on?->toDateString() }}">
            {{ $paper->isEditable() ? 'Open and correct' : 'Open the sheet' }}</a>
    </div>
@endforeach

@if ($roll->isEmpty())
    <p class="hint">A mark sheet needs CTPs on the roll before it can be filled in.</p>
@endif
@endsection

@extends($layout)
@section('title', 'Timetable — '.$cohort->reference)

@php
    $paperRows = $paper?->slots ?? collect();
    $editing = ! $paper || $paper->isEditable();
    $grid = old('slots', $paperRows->map(fn ($s) => [
        'day' => $s->day,
        'starts_at' => substr((string) $s->starts_at, 0, 5),
        'ends_at' => substr((string) $s->ends_at, 0, 5),
        'training_module_id' => $s->training_module_id,
        'kind' => $s->kind,
        'room' => $s->room,
    ])->values()->all());
    $grid = collect($grid)->map(fn (array $r) => array_intersect_key($r, array_flip(
        ['day', 'starts_at', 'ends_at', 'training_module_id', 'kind', 'room'])))->values()->all();
    if ($grid === []) {
        $grid = [['day' => 'monday', 'starts_at' => '', 'ends_at' => '', 'training_module_id' => null,
            'kind' => 'theory', 'room' => '']];
    }
@endphp

@section('content')
@include('teaching.head')

@if ($programmeWeek && $programmeWeek->slots->isNotEmpty())
    <div class="card">
        <div class="section-head">
            <h3>The programme's week — set by the Registrar</h3>
            <span class="hint">{{ $cohort->training?->name }}</span>
        </div>

        <div class="table-scroll">
            <table class="data timetable">
                <thead><tr><th>Day</th><th>Time</th><th>Length</th><th>Class</th><th>Unit</th><th>Room</th></tr></thead>
                <tbody>
                    @foreach ($programmeWeek->week() as $dayRow)
                        @foreach ($dayRow['slots'] as $slot)
                            <tr>
                                <td class="lead">{{ $dayRow['label'] }}</td>
                                <td class="ref">{{ $slot->timeLabel() }}</td>
                                <td class="length">{{ $slot->hoursLabel() }}</td>
                                <td>{{ $slot->kindLabel() }}</td>
                                <td>{{ $slot->module?->title ?? '—' }}</td>
                                <td>{{ $slot->room ?: '—' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($programmeWeek->note)
            <p class="hint"><b>The Registrar's note:</b> {{ $programmeWeek->note }}</p>
        @endif

        <p class="hint">The office lays this out for the whole programme. The week below is yours: write it for
            {{ $cohort->reference }} and forward it to the Registrar as always.</p>
    </div>
@endif

<div class="card">
    <div class="section-head">
        <h3>The week this cohort runs on</h3>
        @if ($paper)<span class="status {{ $paper->statusClass() }}">{{ $paper->statusLabel() }}</span>@endif
    </div>

    @if ($paper && $paper->status === 'returned')
        <p class="flash-error" style="margin:0 0 14px"><b>Sent back by the Registrar.</b> {{ $paper->review_note }}</p>
    @endif

    @if (! $editing)
        <p class="hint">This timetable is {{ Str::lower($paper->statusLabel()) }} — the Registrar answers it, so the
            week below is read-only. A timetable sent back opens for correcting here again.</p>

        <div class="table-scroll">
            <table class="data timetable">
                <thead><tr><th>Day</th><th>Time</th><th>Length</th><th>Class</th><th>Unit</th><th>Room</th></tr></thead>
                <tbody>
                    @forelse ($paper->week() as $dayRow)
                        @foreach ($dayRow['slots'] as $slot)
                            <tr>
                                <td class="lead">{{ $dayRow['label'] }}</td>
                                <td class="ref">{{ $slot->timeLabel() }}</td>
                                <td class="length">{{ $slot->hoursLabel() }}</td>
                                <td>{{ $slot->kindLabel() }}</td>
                                <td>{{ $slot->module?->title ?? '—' }}</td>
                                <td>{{ $slot->room ?: '—' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="6" class="empty-cell">No class on the week yet.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr>
                    <td colspan="2"><b>Total</b></td>
                    <td colspan="4"><span class="length" data-total>{{ \App\Models\TimetableSlot::clocked($paperRows->sum(fn ($s) => $s->hours())) ?: '0' }} h</span>
                        <span class="hint">of class a week</span></td>
                </tr></tfoot>
            </table>
        </div>
    @else
        <form method="POST" action="{{ $url('timetable.save', ['cohort' => $cohort->id]) }}" class="fee-form">
            @csrf
            <div class="record-head">
                <h4>Classes on the week</h4>
                <button class="btn btn-primary btn-sm" type="button" data-slot-add>+ Create a class</button>
            </div>

            @include('partials.week-table', ['grid' => $grid, 'days' => $days, 'kinds' => $kinds,
                'units' => $units, 'footHint' => 'of class — the attendance register counts its hours from this'])

            <div class="field">
                <label>Note on the week</label>
                <input type="text" name="note" maxlength="500" placeholder="Anything the Registrar should read"
                       value="{{ old('note', $paper?->note) }}">
            </div>
            <div class="module-foot">
                <button class="btn btn-outline btn-sm" type="button" data-slot-add>+ Create a class</button>
                <button class="btn btn-lime btn-sm" type="submit">Save the week</button>
                @if ($paper?->exists && $paperRows->isNotEmpty())
                    <button class="btn btn-primary btn-sm" type="submit" name="forward" value="1"
                            data-confirm="Send the timetable to the Registrar for approval?">Save and forward to the Registrar</button>
                @endif
            </div>
            <p class="hint">Saving replaces the whole week, so a class you remove here is gone from the timetable — it is
                never left sitting behind the rest. A class that is not filled in is not added.</p>
        </form>
    @endif
</div>
@endsection


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
    $weekHours = collect($grid)->sum(fn (array $r) => \App\Models\TimetableSlot::between(
        $r['starts_at'] ?? null, $r['ends_at'] ?? null));
@endphp

@section('content')
@include('teaching.head')

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

            <div class="table-scroll">
                <table class="data timetable">
                    <thead><tr>
                        <th>Day <span class="req">*</span></th>
                        <th>Starts <span class="req">*</span></th>
                        <th>Ends <span class="req">*</span></th>
                        <th>Length</th>
                        <th>Class</th>
                        <th>Unit</th>
                        <th>Room</th>
                        <th></th>
                    </tr></thead>
                    <tbody id="slot-rows">
                        @foreach ($grid as $i => $row)
                            <tr class="module-row" data-index="{{ $i }}">
                                <td>
                                    <select name="slots[{{ $i }}][day]" aria-label="Day">
                                        @foreach ($days as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['day'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="time" name="slots[{{ $i }}][starts_at]" value="{{ $row['starts_at'] ?? '' }}"
                                           aria-label="Starts"></td>
                                <td><input type="time" name="slots[{{ $i }}][ends_at]" value="{{ $row['ends_at'] ?? '' }}"
                                           aria-label="Ends"></td>
                                <td class="length" data-length>@php $clocked = \App\Models\TimetableSlot::clocked(
                                    \App\Models\TimetableSlot::between($row['starts_at'] ?? null, $row['ends_at'] ?? null));
                                    @endphp{{ $clocked ? $clocked.' h' : '—' }}</td>
                                <td>
                                    <select name="slots[{{ $i }}][kind]" aria-label="Class">
                                        @foreach ($kinds as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['kind'] ?? 'theory') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="slots[{{ $i }}][training_module_id]" aria-label="Unit">
                                        <option value="">No unit named</option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}" @selected((int) ($row['training_module_id'] ?? 0) === $unit->id)>
                                                {{ $unit->title }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="slots[{{ $i }}][room]" maxlength="120" placeholder="Workshop, lab…"
                                           aria-label="Room" value="{{ $row['room'] ?? '' }}"></td>
                                <td class="actions">
                                    <button class="btn btn-sm btn-ghost" type="button" data-slot-remove>Remove</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr>
                        <td colspan="3"><b>{{ count($grid) }} {{ Str::plural('class', count($grid)) }} on the week</b></td>
                        <td colspan="5"><span class="length" data-total>{{ \App\Models\TimetableSlot::clocked($weekHours) ?: '0' }} h</span>
                            <span class="hint">of class — the attendance register counts its hours from this</span></td>
                    </tr></tfoot>
                </table>
            </div>

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

@if ($editing)
    <script>
        // The week is a table of classes: + drops another row, a row's length is counted from its own two times.
        document.addEventListener('DOMContentLoaded', function () {
            const box = document.getElementById('slot-rows');
            if (! box) return;

            const seed = box.querySelector('.module-row').cloneNode(true);

            function hours(row) {
                const from = row.querySelector('[name$="[starts_at]"]').value;
                const to = row.querySelector('[name$="[ends_at]"]').value;
                if (! from || ! to) return 0;

                const mins = (+to.slice(0, 2) * 60 + +to.slice(3, 5)) - (+from.slice(0, 2) * 60 + +from.slice(3, 5));

                return mins > 0 ? Math.round(mins / 30) / 2 : 0;
            }

            function clocked(value) {
                return value ? Math.round(value * 10) / 10 : 0;
            }

            function recount() {
                let total = 0;
                box.querySelectorAll('.module-row').forEach(function (row) {
                    const made = hours(row);
                    total += made;
                    row.querySelector('[data-length]').textContent = made ? clocked(made) + ' h' : '—';
                });

                const foot = document.querySelector('[data-total]');
                if (foot) foot.textContent = clocked(total) + ' h';
            }

            function renumber() {
                box.querySelectorAll('.module-row').forEach(function (row, i) {
                    row.dataset.index = i;
                    row.querySelectorAll('[name]').forEach(function (input) {
                        input.name = input.name.replace(/slots\[\d+\]/, 'slots[' + i + ']');
                    });
                });

                const count = box.querySelectorAll('.module-row').length;
                const lead = document.querySelector('.timetable tfoot b');
                if (lead) lead.textContent = count + (count === 1 ? ' class' : ' classes') + ' on the week';
            }

            document.addEventListener('click', function (e) {
                if (e.target.closest('[data-slot-add]')) {
                    const row = seed.cloneNode(true);
                    row.querySelectorAll('select').forEach(function (input) { input.selectedIndex = 0; });
                    row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                    row.querySelector('[data-length]').textContent = '—';
                    box.append(row);
                    renumber();
                    recount();
                    row.querySelector('select').focus();
                    return;
                }

                if (e.target.closest('[data-slot-remove]')) {
                    e.target.closest('.module-row').remove();
                    if (! box.querySelector('.module-row')) {
                        box.append(seed.cloneNode(true));
                    }
                    renumber();
                    recount();
                }
            });

            box.addEventListener('change', function (e) {
                if (e.target.matches('input[type="time"]')) recount();
            });
        });
    </script>
@endif
@endsection

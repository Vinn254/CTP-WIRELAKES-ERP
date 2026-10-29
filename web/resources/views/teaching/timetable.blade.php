@extends($layout)
@section('title', 'Timetable — '.$cohort->reference)

@php
    $rows = $paper?->slots ?? collect();
    $editing = ! $paper || $paper->isEditable();
    $grid = old('slots', $rows->map(fn ($s) => [
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

<div class="card">
    <div class="section-head">
        <h3>The week this cohort runs on</h3>
        @if ($paper)<span class="status {{ $paper->statusClass() }}">{{ $paper->statusLabel() }}</span>@endif
    </div>

    @if ($paper && $paper->status === 'returned')
        <p class="flash-error" style="margin:0 0 14px"><b>Sent back by the Registrar.</b> {{ $paper->review_note }}</p>
    @endif

    @if (! $editing)
        <p class="hint">This timetable is {{ Str::lower($paper->statusLabel()) }} — the Registrar answers it. A week
            sent back opens for correcting here again.</p>
    @else
        <form method="POST" action="{{ $url('timetable.save', ['cohort' => $cohort->id]) }}" class="fee-form">
            @csrf
            <div class="module-rows" id="slot-rows">
                @foreach ($grid as $i => $row)
                    <div class="module-row" data-index="{{ $i }}">
                        <div class="form-grid-3">
                            <div class="field">
                                <label><span data-module-label>Class {{ $i + 1 }}</span> — day <span class="req">*</span></label>
                                <select name="slots[{{ $i }}][day]">
                                    @foreach ($days as $value => $label)
                                        <option value="{{ $value }}" @selected(($row['day'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label>Starts <span class="req">*</span></label>
                                <input type="time" name="slots[{{ $i }}][starts_at]" value="{{ $row['starts_at'] ?? '' }}">
                            </div>
                            <div class="field">
                                <label>Ends <span class="req">*</span></label>
                                <input type="time" name="slots[{{ $i }}][ends_at]" value="{{ $row['ends_at'] ?? '' }}">
                            </div>
                            <div class="field">
                                <label>Unit taught</label>
                                <select name="slots[{{ $i }}][training_module_id]">
                                    <option value="">No unit named</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" @selected((int) ($row['training_module_id'] ?? 0) === $unit->id)>
                                            {{ $unit->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label>Class</label>
                                <select name="slots[{{ $i }}][kind]">
                                    @foreach ($kinds as $value => $label)
                                        <option value="{{ $value }}" @selected(($row['kind'] ?? 'theory') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label>Room</label>
                                <input type="text" name="slots[{{ $i }}][room]" maxlength="120"
                                       placeholder="Workshop, lab…" value="{{ $row['room'] ?? '' }}">
                            </div>
                        </div>
                        <button class="btn btn-sm btn-ghost module-drop" type="button" data-module-remove
                                @if ($i === 0) hidden @endif>Remove this class</button>
                    </div>
                @endforeach
            </div>
            <div class="field">
                <label>Note on the week</label>
                <input type="text" name="note" maxlength="500" placeholder="Anything the Registrar should read"
                       value="{{ old('note', $paper?->note) }}">
            </div>
            <div class="module-foot">
                <button class="btn btn-outline btn-sm" type="button" data-module-add="slot-rows">+ Add another class</button>
                <button class="btn btn-lime btn-sm" type="submit">Save the week</button>
            </div>
        </form>
        <p class="hint">Saving replaces the whole week, so a class you remove here is gone from the timetable — it is
            never left sitting behind the rest. Leave a row blank and it is not added.</p>
    @endif

    @if ($paper?->exists)
        @if ($editing && $rows->isNotEmpty())
            <form method="POST" action="{{ $url('timetable.forward', ['cohort' => $cohort->id, 'timetable' => $paper->id]) }}"
                  data-confirm="Send the timetable to the Registrar for approval?">
                @csrf
                <button class="btn btn-sm btn-primary" type="submit">Forward to the Registrar</button>
            </form>
        @endif

        <h4 class="dl-group">As it stands</h4>
        <div class="table-scroll">
            <table class="data">
                <thead><tr><th>Day</th><th>Time</th><th>Class</th><th>Unit</th><th>Room</th></tr></thead>
                <tbody>
                    @forelse ($paper->week() as $dayRow)
                        @foreach ($dayRow['slots'] as $slot)
                            <tr>
                                <td>{{ $dayRow['label'] }}</td>
                                <td>{{ $slot->timeLabel() }}</td>
                                <td>{{ $slot->kindLabel() }}</td>
                                <td>{{ $slot->module?->title ?? '—' }}</td>
                                <td>{{ $slot->room ?: '—' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="empty-cell">No class on the week yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
    // The class rows are a repeater: + drops another row so the whole week goes in with one submit.
    document.addEventListener('click', function (e) {
        function relabel(box) {
            box.querySelectorAll('.module-row').forEach(function (row, i) {
                const label = row.querySelector('[data-module-label]');
                if (label) label.textContent = 'Class ' + (i + 1);
            });
        }

        const add = e.target.closest('[data-module-add]');
        if (add) {
            const box = document.getElementById(add.dataset.moduleAdd);
            const rows = box.querySelectorAll('.module-row');
            const last = rows[rows.length - 1];
            const next = Math.max(...Array.from(rows, r => Number(r.dataset.index) || 0)) + 1;
            const row = last.cloneNode(true);

            row.dataset.index = next;
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/slots\[\d+\]/, 'slots[' + next + ']');
                if (input.tagName === 'SELECT') input.selectedIndex = 0; else input.value = '';
            });
            row.querySelector('[data-module-remove]').hidden = false;

            box.append(row);
            relabel(box);
            row.querySelector('select').focus();
            return;
        }

        const drop = e.target.closest('[data-module-remove]');
        if (drop) {
            const box = drop.closest('.module-rows');
            drop.closest('.module-row').remove();
            box.querySelectorAll('.module-row').forEach(function (row, i) {
                row.querySelectorAll('[name]').forEach(function (input) {
                    input.name = input.name.replace(/slots\[\d+\]/, 'slots[' + i + ']');
                });
                row.dataset.index = i;
            });
            relabel(box);
        }
    });
</script>
@endsection

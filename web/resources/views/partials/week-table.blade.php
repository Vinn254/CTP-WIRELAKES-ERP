@php
    /** A class week written as a table: a trainer's own cohort week and the Registrar's programme week share this. */
    $weekHours = collect($grid)->sum(fn (array $r) => \App\Models\TimetableSlot::between(
        $r['starts_at'] ?? null, $r['ends_at'] ?? null));
    // Only the Registrar's week names a lecturer: a trainer writes their own cohort's week, not who teaches it.
    $lecturers ??= collect();
    $span = 5 + ($lecturers->isNotEmpty() ? 1 : 0);
@endphp
<div class="table-scroll">
    <table class="data timetable">
        <thead><tr>
            <th>Day <span class="req">*</span></th>
            <th>Starts <span class="req">*</span></th>
            <th>Ends <span class="req">*</span></th>
            <th>Length</th>
            <th>Class</th>
            <th>Unit</th>
            @if ($lecturers->isNotEmpty())<th>Lecturer</th>@endif
            <th>Room</th>
            <th></th>
        </tr></thead>
        <tbody id="slot-rows">
            @foreach ($grid as $i => $row)
                @php $made = \App\Models\TimetableSlot::between($row['starts_at'] ?? null, $row['ends_at'] ?? null); @endphp
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
                    <td class="length" data-length>{{ $made ? \App\Models\TimetableSlot::clocked($made).' h' : '—' }}</td>
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
                    @if ($lecturers->isNotEmpty())
                        <td>
                            <select name="slots[{{ $i }}][trainer_id]" aria-label="Lecturer">
                                <option value="">Not assigned</option>
                                @foreach ($lecturers as $lecturer)
                                    <option value="{{ $lecturer->id }}"
                                            @selected((int) ($row['trainer_id'] ?? 0) === $lecturer->id)>
                                        {{ $lecturer->personName() }}</option>
                                @endforeach
                            </select>
                        </td>
                    @endif
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
            <td colspan="{{ $span }}"><span class="length" data-total>{{ \App\Models\TimetableSlot::clocked($weekHours) ?: '0' }} h</span>
                <span class="hint">{{ $footHint }}</span></td>
        </tr></tfoot>
    </table>
</div>

@once
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

            const foot = box.closest('table').querySelector('[data-total]');
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
            const lead = box.closest('table').querySelector('tfoot b');
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

        // A typed clock fires its change only when the control is left, so the length is counted on both beats —
        // the row says its own hours the moment the second time is complete.
        ['change', 'input', 'keyup'].forEach(function (event) {
            box.addEventListener(event, function (e) {
                if (e.target.matches('input[type="time"]')) recount();
            });
        });
    });
</script>
@endonce

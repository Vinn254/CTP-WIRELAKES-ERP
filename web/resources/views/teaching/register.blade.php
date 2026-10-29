@extends($layout)
@section('title', 'Attendance — '.$cohort->reference)

@php
    use App\Models\CohortSession;

    $pickedMonth = substr($week['month'], 5);
    $pickedYear = substr($week['month'], 0, 4);
    $self = $url('register', ['cohort' => $cohort->id]);

    $marked = collect($classes)->filter(fn (array $row) => $row['session'] !== null);
    $toForward = collect($classes)->filter(fn (array $row) => $row['session'] && $row['session']->isEditable());
@endphp

@section('content')
@include('teaching.head')

<form method="GET" action="{{ $self }}" class="filter-bar">
    @include('partials.month-pick', ['month' => $pickedMonth, 'year' => $pickedYear, 'years' => $years])
    <div class="field">
        <label>Week of the month</label>
        <select name="week">
            @foreach ($week['weeks'] as $n => $option)
                <option value="{{ $n + 1 }}" @selected($week['week_no'] === $n + 1)>
                    {{ $option['label'] }} ({{ $option['dates'] }})
                </option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-sm btn-outline" type="submit">Open the week</button>
    @if ($roll->isNotEmpty())
        <button class="btn btn-sm btn-primary" type="button" data-sheet="day" data-class="{{ json_encode(['pick' => true]) }}">
            Class not on the timetable</button>
    @endif
</form>

<div class="stat-row">
    <div class="stat"><small>Classes this week</small><b>{{ count($classes) }}</b></div>
    <div class="stat"><small>Registers written</small><b>{{ $marked->count() }}</b></div>
    <div class="stat"><small>Still with the trainer</small><b>{{ $toForward->count() }}</b></div>
    <div class="stat"><small>On the roll</small><b>{{ $roll->count() }}</b></div>
</div>

<div class="table-scroll">
    <table class="data result-table">
        <thead>
            <tr>
                <th>Day</th><th>Time</th><th>Length</th><th>Class</th><th>Unit</th><th>Room</th>
                <th>Attendance</th><th>Register</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($classes as $row)
                @php
                    $session = $row['session'];
                    $editable = ! $session || $session->isEditable();
                    $onTimetable = empty($row['off_timetable']);
                    $payload = [
                        'date' => $row['date'],
                        'time' => $row['time'],
                        'line' => trim($row['day']->format('D d M Y').($row['time'] ? ' · '.$row['time'] : '')),
                        'slot_hours' => $row['hours'],
                        'fixed_hours' => $onTimetable,
                        'kind' => $row['kind'],
                        'unit_id' => $row['unit_id'],
                        'unit' => $row['unit'],
                        'room' => $row['room'],
                        'topic' => $session?->topic,
                        'hours' => $session?->hours,
                        'note' => $session?->note,
                        'editable' => $editable,
                        'locked' => $session && ! $editable
                            ? 'This register is '.Str::lower($session->statusLabel()).' — the Registrar answers it, and one sent back opens here again.'
                            : null,
                        'ticks' => $session?->marks->pluck('present', 'enrollment_id') ?? [],
                        'tick_notes' => $session?->marks->pluck('note', 'enrollment_id') ?? [],
                    ];
                @endphp
                <tr>
                    <td class="nowrap">{{ $row['day']->format('D') }}
                        <div class="hint">{{ $row['day']->format('d M') }}@if (! empty($row['off_timetable']))
                            · off the timetable @endif</div></td>
                    <td class="nowrap">{{ $row['time'] ?? '—' }}</td>
                    <td class="nowrap">@php $clocked = \App\Models\TimetableSlot::clocked($row['hours']); @endphp
                        {{ $clocked ? $clocked.' h' : '—' }}
                        @if ($row['hours'] && $onTimetable)<div class="hint">from the timetable</div>@endif
                        @if ($row['hours'] && ! $onTimetable)<div class="hint">as written</div>@endif</td>
                    <td>{{ CohortSession::KINDS[$row['kind']] ?? Str::headline((string) $row['kind']) }}</td>
                    <td>{{ $row['unit'] ?? '—' }}</td>
                    <td>{{ $row['room'] ?: '—' }}</td>
                    <td class="nowrap">
                        @if ($session)
                            <b>{{ $session->presentCount() }}</b> of {{ $roll->count() }} present
                        @else
                            <span class="hint">not marked</span>
                        @endif
                    </td>
                    <td class="nowrap">
                        @if ($session)
                            <span class="status {{ $session->statusClass() }}">{{ $session->statusLabel() }}</span>
                            @if ($session->status === 'returned')
                                <div class="hint">{{ $session->review_note }}</div>
                            @endif
                            @if ($session->sheet_path)
                                @php $sheetUrl = $url('day.sheet', ['cohort' => $cohort->id, 'session' => $session->id]); @endphp
                                <div class="hint"><a href="{{ $sheetUrl }}">signed sheet</a>
                                    · <a href="{{ download_href($sheetUrl) }}">Download</a></div>
                            @endif
                        @else
                            <span class="hint">nothing written</span>
                        @endif
                    </td>
                    <td class="actions">
                        @if ($roll->isEmpty())
                            <span class="hint">no seats on the roll</span>
                        @else
                            <button class="btn btn-sm {{ $session ? 'btn-outline' : 'btn-lime' }}" type="button"
                                    data-sheet="day" data-class="{{ json_encode($payload) }}">
                                {{ $session ? 'Correct' : 'Tick attendance' }}</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-cell">
                    {{ $onTheWeek
                        ? 'No class is timetabled for this week. Move to another week, or add a class that is not on the timetable.'
                        : 'This cohort has no timetable yet, so there are no classes to tick. Put the week together on the Timetable tab first.' }}
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('partials.sheet-start', ['name' => 'day', 'title' => 'Attendance',
    'sub' => 'The class from the timetable, the CTPs on the roll, and the sheet the class signed.'])
<form method="POST" action="{{ $url('day.save', ['cohort' => $cohort->id]) }}" enctype="multipart/form-data"
      class="fee-form" data-day-form data-ask data-title="Submit the register">
    @csrf
    <input type="hidden" name="_sheet" value="day">

    <div data-on-timetable>
        <input type="hidden" name="held_on" data-fill="held_on">
        <input type="hidden" name="kind" data-fill="kind">
        <input type="hidden" name="training_module_id" data-fill="training_module_id">
        <p class="sheet-note" data-class-line>Open a class on the week to mark it.</p>
    </div>

    <div data-off-timetable hidden>
        <div class="form-grid-3">
            <div class="field">
                <label>Day <span class="req">*</span></label>
                <select name="held_on">
                    @foreach ($week['days'] as $option)
                        <option value="{{ $option->toDateString() }}">{{ $option->format('l d M Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Class <span class="req">*</span></label>
                <select name="kind">
                    @foreach ($kinds as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Unit</label>
                <select name="training_module_id">
                    <option value="">No unit named</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="hint">Only for a class the timetable does not carry — a day moved, a second sitting, an extra
            practical. The rest of the week is opened from the table above.</p>
    </div>

    <div class="form-grid-2">
        <div class="field">
            <label>What was covered</label>
            <input type="text" name="topic" maxlength="190" data-fill="topic"
                   placeholder="e.g. Stripping a unit safely" value="{{ old('topic') }}">
        </div>
        <div class="field">
            <label>Hours taught</label>
            <input type="number" step="0.5" min="0" max="16" name="hours" data-fill="hours" data-hours
                   value="{{ old('hours', 2) }}">
            <div class="help" data-hours-help>Taken from the class's start and end time on the timetable.</div>
        </div>
    </div>
    <div class="field">
        <label>Note for the Registrar</label>
        <input type="text" name="note" maxlength="500" data-fill="note"
               placeholder="Anything about the day" value="{{ old('note') }}">
    </div>

    <h4 class="dl-group">Who was in class</h4>
    <div class="table-scroll roll-scroll">
        <table class="data roll-table">
            <thead><tr><th>Reg. no.</th><th>CTP</th><th>Present</th><th>Reason absent</th></tr></thead>
            <tbody>
                @foreach ($roll as $seat)
                    <tr>
                        <td class="nowrap">{{ $seat->ctp->ctp_code }}</td>
                        <td><b>{{ $seat->ctp->full_name }}</b></td>
                        <td class="mid"><input type="checkbox" name="ticks[{{ $seat->id }}]" value="1"
                                               @checked(old('ticks.'.$seat->id)) data-roll="tick"></td>
                        <td><input type="text" name="tick_note[{{ $seat->id }}]" maxlength="190" data-roll="note"
                                   placeholder="Absent without notice, sick…" value="{{ old('tick_note.'.$seat->id) }}"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="field">
        <label>Signed sheet</label>
        <input type="file" name="sheet" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
        <div class="help">The paper the class signed, photographed or scanned. The ticks typed above stay on the register.</div>
    </div>

    <p class="hint" data-locked hidden>This register is with the Registrar.</p>

    <div class="sheet-actions">
        <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
        <button class="btn btn-outline" type="submit" data-save-buttons>Save the register</button>
        <button class="btn btn-primary" type="submit" name="forward" value="1" data-save-buttons
                data-confirm="Send this register to the Registrar? The marks on it reach each CTP's record when the Registrar approves.">
            Save and submit to the Registrar</button>
    </div>
</form>
<p class="hint">Attendance only lands on a CTP's track when the Registrar approves the register.</p>
@include('partials.sheet-end')

<script>
    // One popup serves every class on the week: the row's button carries the class, the ticks already on it, and
    // whether it is still the trainer's to write.
    document.addEventListener('click', function (e) {
        const open = e.target.closest('[data-class]');
        if (! open) return;

        const sheet = document.getElementById('sheet-day');
        const form = sheet.querySelector('[data-day-form]');
        const data = JSON.parse(open.dataset.class || '{}');
        const free = !! data.pick;

        const onPanel = sheet.querySelector('[data-on-timetable]');
        const offPanel = sheet.querySelector('[data-off-timetable]');
        onPanel.hidden = free;
        offPanel.hidden = ! free;
        sheet.querySelector('.sheet-head h3').textContent = free ? 'Class not on the timetable' : 'Attendance';
        sheet.querySelector('.sheet-sub').textContent = free
            ? 'A day the timetable does not carry — a class moved, or an extra sitting.'
            : 'The CTPs on the roll, the paper they signed, and the Registrar.';

        if (! free) {
            const kind = {theory: 'Theory class', practical: 'Practical class', assessment: 'Assessment'}[data.kind]
                || data.kind;
            onPanel.querySelector('[data-class-line]').textContent =
                [data.line, kind, data.unit, data.room ? 'Room ' + data.room : null]
                    .filter(Boolean).join(' · ');

            const values = {held_on: data.date, kind: data.kind, training_module_id: data.unit_id || '',
                topic: data.topic || '', hours: data.hours || data.slot_hours || '', note: data.note || ''};
            Object.keys(values).forEach(function (name) {
                const field = onPanel.querySelector('[data-fill="' + name + '"]')
                    || form.querySelector('[data-fill="' + name + '"]');
                if (field) field.value = values[name];
            });
        } else {
            // The shared fields are the class's own — a blank class must not open wearing the last one's words.
            ['topic', 'note'].forEach(function (name) {
                const field = form.querySelector('[data-fill="' + name + '"]');
                if (field) field.value = '';
            });
            form.querySelector('[data-fill="hours"]').value = 2;
        }

        // A class the timetable carries is taught for the length the timetable set: the box shows it, it is not typed.
        const hours = form.querySelector('[data-hours]');
        const fixed = !! data.fixed_hours;
        hours.readOnly = fixed;
        hours.classList.toggle('locked', fixed);
        sheet.querySelector('[data-hours-help]').textContent = fixed
            ? 'From the timetable: ' + data.time + ' sits ' + (data.slot_hours || data.hours || 0)
                + ' h. The register takes that length — it is not typed here.'
            : 'How long the class sat — half hours count. A class on the timetable takes its hours from the week.';

        const ticks = data.ticks || {};
        const notes = data.tick_notes || {};
        form.querySelectorAll('[data-roll]').forEach(function (input) {
            const id = (input.name.match(/\[(\d+)]/) || [])[1];
            if (input.type === 'checkbox') {
                input.checked = !! ticks[id];
            } else {
                input.value = notes[id] || '';
            }
        });

        const editable = data.editable !== false;
        const locked = sheet.querySelector('[data-locked]');
        locked.hidden = editable;
        if (! editable) locked.textContent = data.locked;
        sheet.querySelectorAll('[data-save-buttons]').forEach(function (b) { b.hidden = ! editable; });

        // Whatever is not on show cannot be submitted: a hidden panel would otherwise send a second day and kind.
        form.querySelectorAll('input, select, textarea').forEach(function (input) {
            const panel = input.closest('[data-on-timetable], [data-off-timetable]');
            input.disabled = ! editable || (!! panel && panel.hidden);
        });
    });
</script>
@endsection

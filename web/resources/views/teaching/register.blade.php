@extends($layout)
@section('title', 'Attendance — '.$cohort->reference)

@php
    use App\Models\CohortSession;

    $pickedMonth = substr($week['month'], 5);
    $pickedYear = substr($week['month'], 0, 4);
    $self = $url('register', ['cohort' => $cohort->id]);
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
        <button class="btn btn-sm btn-primary" type="button" data-sheet="day">Mark attendance for a class day</button>
    @endif
</form>

@foreach ($week['days'] as $day)
    @php
        $date = $day->toDateString();
        $sessions = $held->get($date, collect());
    @endphp
    <div class="card">
        <div class="section-head">
            <h3>{{ $day->format('l') }} {{ $day->format('d M Y') }}</h3>
            <span class="tag">{{ $sessions->count() }} {{ Str::plural('class', $sessions->count()) }} marked</span>
        </div>

        @foreach ($sessions as $session)
            @php $ticks = $session->marks->keyBy('enrollment_id'); @endphp
            <h4 class="dl-group">{{ $session->kindLabel() }} — {{ $session->topic ?: 'no topic written' }}
                @if ($session->hours) · {{ $session->hours }} h @endif</h4>
            <p class="hint">
                <span class="status {{ $session->statusClass() }}">{{ $session->statusLabel() }}</span>
                {{ $session->presentCount() }} of {{ $roll->count() }} present
                @if ($session->note) · {{ $session->note }} @endif
                @if ($session->sheet_path)
                    · <a href="{{ $url('day.sheet', ['cohort' => $cohort->id, 'session' => $session->id]) }}">signed sheet</a>
                @endif
                @if ($session->status === 'returned')
                    — sent back: {{ $session->review_note }}
                @endif
            </p>

            @if ($session->isEditable())
                <form method="POST" action="{{ $url('day.save', ['cohort' => $cohort->id]) }}" class="fee-form">
                    @csrf
                    <input type="hidden" name="held_on" value="{{ $date }}">
                    <input type="hidden" name="kind" value="{{ $session->kind }}">
                    <input type="hidden" name="topic" value="{{ $session->topic }}">
                    <input type="hidden" name="hours" value="{{ $session->hours }}">
                    <input type="hidden" name="note" value="{{ $session->note }}">
                    <div class="table-scroll">
                        <table class="data">
                            <thead>
                                <tr><th>CTP</th><th>Present</th><th>Reason absent</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($roll as $seat)
                                    @php $tick = $ticks->get($seat->id); @endphp
                                    <tr>
                                        <td><b>{{ $seat->ctp->full_name }}</b>
                                            <span class="hint">{{ $seat->ctp->ctp_code }}</span></td>
                                        <td>
                                            <input type="checkbox" name="ticks[{{ $seat->id }}]" value="1"
                                                   @checked(old('ticks.'.$seat->id, $tick?->present))>
                                        </td>
                                        <td>
                                            <input type="text" name="tick_note[{{ $seat->id }}]" maxlength="190"
                                                   placeholder="Absent without notice, sick…"
                                                   value="{{ old('tick_note.'.$seat->id, $tick?->note) }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="module-foot">
                        <button class="btn btn-sm btn-outline" type="button" data-sheet="sheet-{{ $session->id }}">
                            Attach the signed sheet</button>
                        <button class="btn btn-lime btn-sm" type="submit">Save the day</button>
                    </div>
                </form>

                <form method="POST" action="{{ $url('day.forward', ['cohort' => $cohort->id, 'session' => $session->id]) }}"
                      data-confirm="Send this day's register to the Registrar?">
                    @csrf
                    <button class="btn btn-sm btn-primary" type="submit">Forward to the Registrar</button>
                </form>

                @include('partials.sheet-start', ['name' => 'sheet-'.$session->id,
                    'title' => 'Signed sheet — '.$day->format('D d M Y')])
                <form method="POST" action="{{ $url('day.save', ['cohort' => $cohort->id]) }}"
                      enctype="multipart/form-data" class="fee-form">
                    @csrf
                    <input type="hidden" name="_sheet" value="sheet-{{ $session->id }}">
                    <input type="hidden" name="held_on" value="{{ $date }}">
                    <input type="hidden" name="kind" value="{{ $session->kind }}">
                    <input type="hidden" name="topic" value="{{ $session->topic }}">
                    <input type="hidden" name="hours" value="{{ $session->hours }}">
                    <input type="hidden" name="note" value="{{ $session->note }}">
                    <div class="field">
                        <label>The paper the class signed</label>
                        <input type="file" name="sheet" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="help">A photograph or scan of the sheet. The ticks you typed stay on the register.</div>
                    </div>
                    <div class="sheet-actions">
                        <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
                        <button class="btn btn-primary" type="submit">Attach the sheet</button>
                    </div>
                </form>
                @include('partials.sheet-end')
            @else
                <p class="hint">This register is {{ Str::lower($session->statusLabel()) }}@if ($session->submitted_at)
                    since {{ $session->submitted_at->format('d M Y') }}@endif — the Registrar answers it, and a
                    register sent back opens here again.</p>
            @endif
        @endforeach

        @if ($roll->isNotEmpty() && $sessions->every(fn ($s) => $s->isEditable()))
            <button class="btn btn-sm btn-outline" type="button" data-sheet="day" data-day="{{ $date }}">
                Add another class to this day</button>
        @endif
    </div>
@endforeach

@include('partials.sheet-start', ['name' => 'day', 'title' => 'Mark attendance',
    'sub' => 'The class that sat, and who was in it.'])
<form method="POST" action="{{ $self }}" enctype="multipart/form-data" class="fee-form"
      data-confirm="Write this day to the register?">
    @csrf
    <input type="hidden" name="_sheet" value="day">
    <input type="hidden" name="month" value="{{ $pickedMonth }}">
    <input type="hidden" name="week" value="{{ $week['week_no'] }}">
    <div class="form-grid-3">
        <div class="field">
            <label>Day of this week <span class="req">*</span></label>
            <select name="held_on" data-day-select required>
                @foreach ($week['days'] as $option)
                    <option value="{{ $option->toDateString() }}"
                        @selected(old('held_on', $option->toDateString()) === $option->toDateString())>
                        {{ $option->format('l d M Y') }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Class <span class="req">*</span></label>
            <select name="kind" required>
                @foreach ($kinds as $value => $label)
                    <option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="help">A theory, a practical or an assessment sitting on the day.</div>
        </div>
        <div class="field">
            <label>Hours taught</label>
            <input type="number" name="hours" min="0" max="16" value="{{ old('hours', 2) }}">
        </div>
    </div>
    <div class="form-grid-2">
        <div class="field">
            <label>What was covered</label>
            <input type="text" name="topic" maxlength="190" placeholder="e.g. Stripping a unit safely"
                   value="{{ old('topic') }}">
        </div>
        <div class="field">
            <label>Note on the day</label>
            <input type="text" name="note" maxlength="500" placeholder="Anything the Registrar should read"
                   value="{{ old('note') }}">
        </div>
    </div>
    <div class="field">
        <label>Signed sheet</label>
        <input type="file" name="sheet" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
        <div class="help">Optional — the paper register photographed, or tick the class below instead.</div>
    </div>

    <h4 class="dl-group">Who was in class</h4>
    <div class="table-scroll">
        <table class="data">
            <thead><tr><th>CTP</th><th>Present</th><th>Reason absent</th></tr></thead>
            <tbody>
                @foreach ($roll as $seat)
                    <tr>
                        <td><b>{{ $seat->ctp->full_name }}</b>
                            <span class="hint">{{ $seat->ctp->ctp_code }}</span></td>
                        <td><input type="checkbox" name="ticks[{{ $seat->id }}]" value="1"
                                   @checked(old('ticks.'.$seat->id, $date === now()->toDateString()))></td>
                        <td><input type="text" name="tick_note[{{ $seat->id }}]" maxlength="190"
                                   value="{{ old('tick_note.'.$seat->id) }}"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="sheet-actions">
        <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
        <button class="btn btn-primary" type="submit">Write the day</button>
    </div>
</form>
<p class="hint">A day is forwarded on its own once the ticks are in. Attendance only reaches a CTP's record when the
    Registrar approves the register.</p>
@include('partials.sheet-end')

<script>
    // The button on a day's card pre-selects that day in the shared form before it opens.
    document.addEventListener('click', function (e) {
        const open = e.target.closest('[data-day]');
        if (! open) return;
        const select = document.querySelector('[data-day-select]');
        if (select) select.value = open.dataset.day;
    });
</script>
@endsection

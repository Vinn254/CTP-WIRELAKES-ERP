@extends($layout)
@section('title', 'Lesson plan — '.$cohort->reference)

@php
    $editing = ! $plan || $plan->isEditable();
    $next = $lines->count() + 1;
    $planHours = $lines->sum(fn ($l) => \App\Models\TimetableSlot::between($l->starts_at, $l->ends_at));
@endphp

@section('content')
@include('teaching.head')

<div class="card">
    <div class="section-head">
        <h3>The plan for {{ $cohort->periodLabel() }}</h3>
        @if ($plan)<span class="status {{ $plan->statusClass() }}">{{ $plan->statusLabel() }}</span>@endif
    </div>

    @if ($plan && $plan->status === 'returned')
        <p class="flash-error" style="margin:0 0 14px"><b>Sent back by the Registrar.</b> {{ $plan->review_note }}</p>
    @endif

    @if (! $editing)
        <p class="hint">This plan is {{ Str::lower($plan->statusLabel()) }} — the Registrar answers it. Open a lesson to
            read it; a plan sent back opens for correcting here again.</p>
    @else
        <div class="record-head">
            <h4>Lessons on the plan</h4>
            <button class="btn btn-primary btn-sm" type="button" data-sheet="line" data-line="">
                + Write lesson {{ $next }}</button>
        </div>
    @endif

    <div class="table-scroll">
        <table class="data lessons">
            <thead><tr>
                <th>Lesson</th>
                <th>Day</th>
                <th>Time</th>
                <th>Length</th>
                <th>Unit</th>
                <th>What is taught</th>
                <th>Handouts</th>
                <th></th>
            </tr></thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr>
                        <td class="lead">{{ $line->position }}</td>
                        <td class="nowrap">{{ $line->dateLabel() }}</td>
                        <td class="ref">{{ $line->timeLabel() ?: '—' }}</td>
                        <td class="length">@php $clocked = \App\Models\TimetableSlot::clocked(
                            \App\Models\TimetableSlot::between($line->starts_at, $line->ends_at)); @endphp
                            {{ $clocked ? $clocked.' h' : '—' }}</td>
                        <td>{{ $line->module?->title ?? '—' }}</td>
                        <td class="text">{{ $line->topic }}</td>
                        <td>{{ $line->materials->count() }}</td>
                        <td class="actions">
                            <button class="btn btn-sm btn-outline" type="button" data-lesson-read="{{ $line->id }}"
                                    data-lesson-read-for="{{ $line->id }}" aria-expanded="false">Read</button>
                            @if ($editing)
                                <button class="btn btn-sm btn-ghost" type="button" data-sheet="line" data-line="{{ json_encode([
                                        'position' => $line->position, 'training_module_id' => $line->training_module_id,
                                        'planned_on' => $line->planned_on?->toDateString(),
                                        'starts_at' => $line->starts_at ? substr($line->starts_at, 0, 5) : null,
                                        'ends_at' => $line->ends_at ? substr($line->ends_at, 0, 5) : null,
                                        'topic' => $line->topic, 'summary' => $line->summary,
                                        'objectives' => $line->objectives, 'activities' => $line->activities,
                                        'resources' => $line->resources, 'assessment_note' => $line->assessment_note,
                                        'action' => $url('lesson.update', ['cohort' => $cohort->id, 'line' => $line->id]),
                                    ]) }}">Correct</button>
                                <form method="POST" action="{{ $url('lesson.remove', ['cohort' => $cohort->id, 'line' => $line->id]) }}"
                                      data-confirm="Take lesson {{ $line->position }} off the plan? The lessons behind it move up.">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ghost" type="submit">Take off</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    <tr class="sub-row" data-lesson-body="{{ $line->id }}" hidden>
                        <td colspan="8">
                            @foreach (['summary' => 'Summary and content', 'objectives' => 'Objectives',
                                       'activities' => 'Activities', 'resources' => 'Resources',
                                       'assessment_note' => 'How the class is assessed'] as $field => $label)
                                @if ($line->$field)
                                    <h5 class="dl-group">{{ $label }}</h5>
                                    <p>{!! nl2br(e($line->$field)) !!}</p>
                                @endif
                            @endforeach
                            @if ($line->materials->isNotEmpty())
                                <h5 class="dl-group">Handouts given out</h5>
                                <p>@foreach ($line->materials as $paper){{ $paper->title }}@if (! $loop->last), @endif @endforeach</p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-cell">Nothing on the plan yet. Lesson 1 is written first, then the
                        lessons follow in order to the last one of the teaching period.</td></tr>
                @endforelse
            </tbody>
            @if ($lines->isNotEmpty())
                <tfoot><tr>
                    <td colspan="3"><b>{{ $lines->count() }} {{ Str::plural('lesson', $lines->count()) }} ·
                        {{ $cohort->training?->modules?->count() ?? 0 }} {{ Str::plural('unit', (int) ($cohort->training?->modules?->count() ?? 0)) }} on the programme</b></td>
                    <td class="length" colspan="5">{{ \App\Models\TimetableSlot::clocked($planHours) ?: '0' }} h
                        <span class="hint">of lesson time planned</span></td>
                </tr></tfoot>
            @endif
        </table>
    </div>

    @if ($editing && $plan?->exists && $lines->isNotEmpty())
        <div class="module-foot">
            <button class="btn btn-outline btn-sm" type="button" data-sheet="line" data-line="">+ Write another lesson</button>
            <form method="POST" action="{{ $url('lesson.forward', ['cohort' => $cohort->id, 'plan' => $plan->id]) }}"
                  data-confirm="Send the whole lesson plan to the Registrar?">
                @csrf
                <button class="btn btn-primary btn-sm" type="submit">Forward the plan ({{ $lines->count() }} lessons)</button>
            </form>
        </div>
        <p class="hint">Correcting a lesson overwrites it on the plan — the old wording does not stay beside it.</p>
    @endif
</div>

@if ($editing)
    @include('partials.sheet-start', ['name' => 'line', 'title' => 'Lesson', 'sub' => 'What the class is taught, from the
        summary down to how it is assessed.'])
    <form method="POST" action="{{ $url('lesson.save', ['cohort' => $cohort->id]) }}" class="fee-form" data-line-form
          data-confirm="Save this lesson on the plan?">
        @csrf
        <input type="hidden" name="_sheet" value="line">
        <div class="form-grid-3">
            <div class="field">
                <label>Unit taught</label>
                <select name="training_module_id">
                    <option value="">No unit named</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Day of the lesson</label>
                <input type="date" name="planned_on" min="{{ $cohort->starts_on?->toDateString() }}"
                       max="{{ ($cohort->ends_on ?? today())->toDateString() }}">
                <div class="help">Optional — a plan can be written for the whole term before the dates are fixed.</div>
            </div>
            <div class="field">
                <label>Starts / ends</label>
                <div class="form-grid-2">
                    <input type="time" name="starts_at">
                    <input type="time" name="ends_at">
                </div>
            </div>
        </div>
        <div class="field">
            <label>What is taught <span class="req">*</span></label>
            <input type="text" name="topic" maxlength="190" required
                   placeholder="e.g. Reading a wiring diagram">
        </div>
        <div class="field">
            <label>Summary and content <span class="req">*</span></label>
            <textarea name="summary" rows="7" maxlength="20000" required
                      placeholder="What the lesson covers, point by point, from the first thing said to the last.">{{ old('summary') }}</textarea>
            <div class="help">This is the lesson itself — everything the class is taken through, lesson 1 to the last.</div>
        </div>
        <div class="form-grid-2">
            <div class="field">
                <label>Objectives</label>
                <textarea name="objectives" rows="3" maxlength="5000"
                          placeholder="What the trainee can do by the end">{{ old('objectives') }}</textarea>
            </div>
            <div class="field">
                <label>Activities</label>
                <textarea name="activities" rows="3" maxlength="5000"
                          placeholder="What the class actually does">{{ old('activities') }}</textarea>
            </div>
            <div class="field">
                <label>Resources</label>
                <textarea name="resources" rows="3" maxlength="5000"
                          placeholder="Tools, machines, handouts needed">{{ old('resources') }}</textarea>
            </div>
            <div class="field">
                <label>How the class is assessed</label>
                <textarea name="assessment_note" rows="3" maxlength="5000"
                          placeholder="The CAT, task or demonstration that closes it">{{ old('assessment_note') }}</textarea>
            </div>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-primary" type="submit" data-line-save>Write the lesson</button>
        </div>
    </form>

    <script>
        // One form serves both jobs: the button that opens it carries the lesson's own fields and its save url.
        document.addEventListener('click', function (e) {
            const open = e.target.closest('[data-line]');
            if (! open) return;

            const form = document.querySelector('[data-line-form]');
            const sheet = document.getElementById('sheet-line');
            const data = open.dataset.line ? JSON.parse(open.dataset.line) : {};
            const title = sheet.querySelector('.sheet-head h3');
            const sub = sheet.querySelector('.sheet-sub');
            const save = sheet.querySelector('[data-line-save]');

            form.action = data.action || '{{ $url("lesson.save", ["cohort" => $cohort->id]) }}';
            title.textContent = data.position ? 'Correct lesson ' + data.position : 'Write lesson {{ $next }}';
            sub.textContent = data.position
                ? 'The lesson is rewritten where it stands.'
                : 'Everything the class is taken through, in order.';
            save.textContent = data.position ? 'Save the lesson' : 'Write the lesson';

            form.querySelectorAll('[name]').forEach(function (input) {
                if (input.name === '_sheet') return;
                input.value = data[input.name] === null || data[input.name] === undefined ? '' : data[input.name];
            });

        });

        // A lesson's full wording opens under its own row, so the plan reads as one table.
        document.querySelectorAll('[data-lesson-read]').forEach(function (button) {
            button.addEventListener('click', function () {
                const body = document.querySelector('[data-lesson-body="' + button.dataset.lessonRead + '"]');
                const shown = body.hasAttribute('hidden');
                body.toggleAttribute('hidden', ! shown);
                button.textContent = shown ? 'Close' : 'Read';
                button.setAttribute('aria-expanded', shown ? 'true' : 'false');
            });
        });
    </script>
@endif
@endsection

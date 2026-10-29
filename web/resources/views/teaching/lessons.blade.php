@extends($layout)
@section('title', 'Lesson plan — '.$cohort->reference)

@php
    $editing = ! $plan || $plan->isEditable();
    $next = $lines->count() + 1;
@endphp

@section('content')
@include('teaching.head')

<div class="card">
    <div class="section-head">
        <h3>The plan for {{ $cohort->periodLabel() }}</h3>
        @if ($plan)<span class="status {{ $plan->statusClass() }}">{{ $plan->statusLabel() }}</span>@endif
    </div>
    <p class="ah-meta">
        <span>{{ $lines->count() }} {{ Str::plural('lesson', $lines->count()) }} written</span>
        <span>{{ $cohort->training?->modules?->count() ?? 0 }} {{ Str::plural('unit', (int) ($cohort->training?->modules?->count() ?? 0)) }} on the programme</span>
    </p>

    @if ($plan && $plan->status === 'returned')
        <p class="flash-error" style="margin:0 0 14px"><b>Sent back by the Registrar.</b> {{ $plan->review_note }}</p>
    @endif

    @if (! $editing)
        <p class="hint">This plan is {{ Str::lower($plan->statusLabel()) }} — the Registrar answers it. A plan sent
            back opens for correcting here again.</p>
    @else
        <div class="module-foot">
            <button class="btn btn-sm btn-primary" type="button" data-sheet="line" data-line="">Write lesson {{ $next }}</button>
            @if ($plan?->exists && $lines->isNotEmpty())
                <form method="POST" action="{{ $url('lesson.forward', ['cohort' => $cohort->id, 'plan' => $plan->id]) }}"
                      data-confirm="Send the whole lesson plan to the Registrar?">
                    @csrf
                    <button class="btn btn-sm btn-outline" type="submit">Forward the plan ({{ $lines->count() }} lessons)</button>
                </form>
            @endif
        </div>
    @endif

    @if ($lines->isEmpty())
        <p class="hint">Nothing on the plan yet. Lesson 1 is written first, then the lessons follow in order to the
            last one of the teaching period.</p>
    @endif
</div>

@foreach ($lines as $line)
    <div class="card">
        <div class="section-head">
            <h3>Lesson {{ $line->position }} — {{ $line->topic }}</h3>
            @if ($line->module)<span class="tag">{{ $line->module->title }}</span>@endif
        </div>
        <p class="ah-meta">
            <span>{{ $line->dateLabel() }}</span>
            @if ($line->timeLabel())<span>{{ $line->timeLabel() }}</span>@endif
            <span>{{ $line->materials->count() }} {{ Str::plural('handout', $line->materials->count()) }} given out</span>
        </p>

        <h4 class="dl-group">Summary and content</h4>
        <p>{!! nl2br(e($line->summary)) !!}</p>

        @foreach (['objectives' => 'Objectives', 'activities' => 'Activities', 'resources' => 'Resources',
                   'assessment_note' => 'How the class is assessed'] as $field => $label)
            @if ($line->$field)
                <h4 class="dl-group">{{ $label }}</h4>
                <p>{!! nl2br(e($line->$field)) !!}</p>
            @endif
        @endforeach

        @if ($editing)
            <div class="module-foot">
                <button class="btn btn-sm btn-outline" type="button" data-sheet="line" data-line="{{ json_encode([
                        'position' => $line->position, 'training_module_id' => $line->training_module_id,
                        'planned_on' => $line->planned_on?->toDateString(),
                        'starts_at' => $line->starts_at ? substr($line->starts_at, 0, 5) : null,
                        'ends_at' => $line->ends_at ? substr($line->ends_at, 0, 5) : null,
                        'topic' => $line->topic, 'summary' => $line->summary,
                        'objectives' => $line->objectives, 'activities' => $line->activities,
                        'resources' => $line->resources, 'assessment_note' => $line->assessment_note,
                        'action' => $url('lesson.update', ['cohort' => $cohort->id, 'line' => $line->id]),
                    ]) }}">Correct lesson {{ $line->position }}</button>
                <form method="POST" action="{{ $url('lesson.remove', ['cohort' => $cohort->id, 'line' => $line->id]) }}"
                      data-confirm="Take lesson {{ $line->position }} off the plan? The lessons behind it move up.">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-ghost" type="submit">Take off the plan</button>
                </form>
            </div>
        @endif
    </div>
@endforeach

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
    <p class="hint">Correcting a lesson overwrites it on the plan — the old wording does not stay beside it.</p>
    @include('partials.sheet-end')

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
    </script>
@endif
@endsection

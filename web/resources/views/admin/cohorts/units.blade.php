@extends('layouts.admin')
@section('title', 'Units and trainers')

@php
    $chosenUnits = collect(old('module_ids', $chosenUnits))->map(fn ($id) => (int) $id)->all();
    // The hands as the office last left them: a list of trainer ids on each unit, the lead name first.
    $postedHands = collect(old('unit_trainers', []))
        ->map(fn ($ids) => collect($ids ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all())->all()
        ?: $unitHands;
    $named = collect($postedHands)->filter()->count();
    $seated = $cohort->enrollments()->whereIn('state', ['pending', 'approved'])->count();
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.cohorts.show', $cohort) }}">&larr; {{ $cohort->reference }}</a></p>
<h1>Units and trainers — {{ $cohort->reference }}</h1>
<p class="page-lede">Register the units each programme brings, then name the hands that teach them.</p>

<div class="card">
    <ol class="step-list">
        <li><b>Programmes</b> — {{ $cohort->programmeIds() ? count($cohort->programmeIds()) : 0 }}
            <a href="{{ route('admin.cohorts.edit', $cohort) }}">change</a></li>
        <li class="current"><b>Units and trainers</b> — {{ count($chosenUnits) }} ticked, {{ $named }} named</li>
        <li><b>Students</b> — {{ $seated }} seated
            <a href="{{ route('admin.cohorts.show', $cohort) }}">open the roster</a></li>
    </ol>
</div>

<form method="POST" action="{{ route('admin.cohorts.units.save', $cohort) }}">
    @csrf

    @foreach ($programmes as $programme)
        @php
            $units = $programmeUnits[$programme->id] ?? collect();
            $options = collect($eligible[$programme->id] ?? []);
        @endphp
        <div class="card">
            <div class="section-head">
                <h3>{{ $programme->name }}@if ($programme->code) <span class="tag">{{ $programme->code }}</span>@endif</h3>
                <span class="tag">{{ $units->count() }} {{ Str::plural('unit', $units->count()) }}</span>
            </div>
            <p class="help">A student registered under {{ $programme->name }} sees exactly the units you tick here.
                A unit can carry more than one trainer.</p>

            @if ($units->isEmpty())
                <p class="flash-error" style="margin:0">This programme has no units yet, so its trainees have
                    nothing registered on them. Add one with <b>Add a unit</b> below, or under
                    <a href="{{ route('admin.trainings.index') }}">Training</a>.</p>
            @elseif (! $options->count())
                <p class="flash-error" style="margin:0">No approved trainer is registered to teach
                    {{ $programme->name }}. Approve one under <a href="{{ route('admin.trainers.index') }}">Trainers</a>
                    before naming a hand on these units.</p>
            @endif

            @if ($units->isNotEmpty())
                <div class="unit-rows">
                    <div class="unit-row unit-head">
                        <span>Unit taught on this intake</span><span>Trainers on the unit</span>
                    </div>
                    @foreach ($units as $u)
                        <div class="unit-row">
                            <label class="unit-pick"><input type="checkbox" name="module_ids[]" value="{{ $u->id }}"
                                  @checked(in_array($u->id, $chosenUnits, true))>
                                {{ $u->position }}. {{ $u->title }}
                                <span class="hint">{{ $u->hoursLabel() }} · {{ $u->termLabel() }}</span>
                                @if ($u->hasSyllabus())
                                    <a class="hint" href="{{ route('admin.units.scheme', $u) }}" target="_blank">
                                        {{ $u->syllabusLabel() }}</a>
                                @endif
                            </label>

                            <div class="unit-hands" data-hands>
                                @foreach (array_values($postedHands[$u->id] ?? []) + [null] as $k => $held)
                                    <div class="hand-line">
                                        <select name="unit_trainers[{{ $u->id }}][]"
                                                aria-label="Trainer {{ $k + 1 }} on {{ $u->title }}">
                                            <option value="">No trainer named</option>
                                            @foreach ($options as $tr)
                                                <option value="{{ $tr->id }}" @selected($held === $tr->id)>
                                                    {{ $tr->personName() }} — {{ $tr->placeLabel() }}</option>
                                            @endforeach
                                        </select>
                                        @if ($k > 0)
                                            <button class="hand-x" type="button" data-hand-remove
                                                    aria-label="Remove this trainer">&#10005;</button>
                                        @endif
                                    </div>
                                @endforeach
                                <button class="hand-add" type="button" data-hand-add>+ Add another trainer</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Save units and trainers</button>
        <a class="btn btn-ghost" href="{{ route('admin.cohorts.show', $cohort) }}">Back to the intake</a>
    </div>
</form>

@foreach ($programmes as $programme)
    @php
        $options = collect($eligible[$programme->id] ?? []);
        $sheet = 'unit-'.$programme->id;
    @endphp

    <button class="btn btn-sm btn-outline" type="button" data-sheet="{{ $sheet }}"
            style="margin:0 0 8px">+ Add unit to {{ $programme->name }}</button>

    {{-- The unit the programme is short of, registered here and put on this intake in one move. --}}
    @include('partials.sheet-start', ['name' => $sheet,
        'title' => 'Add a unit to this programme',
        'sub' => 'Add this unit to '.$programme->name.' and name a trainer on it now (you can add more hands on '
            .'the unit row after saving).'])
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.cohorts.units.add', $cohort) }}">
        @csrf
        <input type="hidden" name="_sheet" value="{{ $sheet }}">
        <input type="hidden" name="training_id" value="{{ $programme->id }}">

        <div class="field">
            <label>Programme</label>
            <input type="text" value="{{ $programme->name }}" readonly>
            <div class="help">The unit is registered under this programme, on this intake.</div>
        </div>

        <div class="field">
            <label>Unit <span class="req">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" maxlength="150" required
                   placeholder="Name the unit as the syllabus does">
        </div>

        <div class="form-grid-2">
            <div class="field">
                <label for="theory-{{ $programme->id }}">Theory hours</label>
                <input type="number" id="theory-{{ $programme->id }}" name="theory_hours" min="0" max="2000"
                       value="{{ old('theory_hours') }}">
            </div>
            <div class="field">
                <label for="practical-{{ $programme->id }}">Practical hours</label>
                <input type="number" id="practical-{{ $programme->id }}" name="practical_hours" min="0" max="2000"
                       value="{{ old('practical_hours') }}">
            </div>
        </div>

        <div class="field">
            <label>Term</label>
            <select name="term">
                @foreach (\App\Models\TrainingModule::termChoices() as $key => $label)
                    <option value="{{ $key }}" @selected(old('term', 'general') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="outcome-{{ $programme->id }}">What the trainee must be able to do (optional)</label>
            <textarea id="outcome-{{ $programme->id }}" name="outcome" rows="3" maxlength="500"
                      placeholder="Type what a trainee must be able to do after this unit, or leave empty.">{{ old('outcome') }}</textarea>
        </div>

        <div class="field">
            <label for="syllabus-{{ $programme->id }}">Scheme of work / syllabus (optional)</label>
            <input type="file" id="syllabus-{{ $programme->id }}" name="syllabus"
                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip">
            <div class="help">The paper this unit is taught from. It opens on the site for the office, the trainers
                named on the unit and the students seated on it — 20 MB at most.</div>
        </div>

        <div class="field">
            <label>Trainers on this unit</label>
            <div class="unit-hands" data-hands>
                @foreach (collect(old('new_trainers', []))->filter()->values()->all() + [null] as $k => $held)
                    <div class="hand-line">
                        <select name="new_trainers[]" aria-label="Trainer {{ $k + 1 }} on the new unit">
                            <option value="">No trainer named</option>
                            @foreach ($options as $tr)
                                <option value="{{ $tr->id }}" @selected($held === $tr->id)>
                                    {{ $tr->personName() }} — {{ $tr->placeLabel() }}</option>
                            @endforeach
                        </select>
                        @if ($k > 0)
                            <button class="hand-x" type="button" data-hand-remove
                                    aria-label="Remove this trainer">&#10005;</button>
                        @endif
                    </div>
                @endforeach
                <button class="hand-add" type="button" data-hand-add>+ Add another trainer</button>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Add unit and trainer</button>
            <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
        </div>
    </form>
    @include('partials.sheet-end')
@endforeach

<script>
    // One repeater for every trainer list on the page: a row is cloned empty, and the cross takes it back off.
    document.addEventListener('click', function (e) {
        var add = e.target.closest('[data-hand-add]');
        if (add) {
            var box = add.closest('[data-hands]');
            var rows = box.querySelectorAll('.hand-line');
            if (! rows.length) { return; }

            var line = rows[rows.length - 1].cloneNode(true);
            line.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });

            var cross = document.createElement('button');
            cross.type = 'button';
            cross.className = 'hand-x';
            cross.setAttribute('data-hand-remove', '');
            cross.setAttribute('aria-label', 'Remove this trainer');
            cross.innerHTML = '&#10005;';
            line.appendChild(cross);

            box.insertBefore(line, add);

            return;
        }

        var cut = e.target.closest('[data-hand-remove]');
        if (cut) { cut.closest('.hand-line').remove(); }
    });
</script>
@endsection

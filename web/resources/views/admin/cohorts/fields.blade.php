@php
    // Step one of an intake: which programmes it teaches, where it happens and when it runs. No trainer is named
    // here and no unit is ticked here — the units are registered on the intake once it exists, and the hands go
    // onto those units. The lead programme (the first ticked) is what the papers are filed under.
    $chosenProgrammes = collect(old('training_ids', $cohort->exists ? $cohort->programmeIds() : []))
        ->map(fn ($id) => (int) $id)->all();
@endphp

<h3>Programmes taught on this intake</h3>
<p class="help">Tick every programme this group of trainees will be taught. Each one brings its own units, and a unit
    is later handed to the trainer who teaches it. A student registered under one of these programmes is fetched onto
    this intake and sees that programme's units on their own side.</p>
<div class="chip-grid">
    @foreach ($trainings as $t)
        <label><input type="checkbox" name="training_ids[]" value="{{ $t->id }}"
                       @checked(in_array($t->id, $chosenProgrammes, true))>
            {{ $t->name }}@if ($t->code) <span class="hint">{{ $t->code }}</span>@endif</label>
    @endforeach
</div>
<div class="help">Running something new? Add it under
    <a href="{{ route('admin.trainings.index') }}">Training</a> first — a programme is not typed here.</div>

<h3 style="margin-top:20px;">Where this intake happens</h3>
<div class="form-grid">
    <div class="field">
        <label>Venue</label>
        <select name="venue_id">
            <option value="">No fixed venue</option>
            @foreach ($venues as $v)
                <option value="{{ $v->id }}" @selected(old('venue_id', $cohort->venue_id) == $v->id)>
                    {{ $v->name }} — {{ $v->typeLabel() }}@if ($v->capacity), {{ $v->capacity }} seats @endif
                </option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Community it serves</label>
        <select name="community_id">
            <option value="">Not tied to a community</option>
            @foreach ($communities as $c)
                <option value="{{ $c->id }}" @selected(old('community_id', $cohort->community_id) == $c->id)>
                    {{ $c->name }} — {{ $c->county }}
                </option>
            @endforeach
        </select>
        <div class="help">Linking it puts the intake on that community's timeline.</div>
    </div>
    <div class="field">
        <label>County <span class="req">*</span></label>
        <input type="text" name="county" list="cohort-counties" required value="{{ old('county', $cohort->county) }}">
    </div>
    <div class="field">
        <label>Sub-county</label>
        <input type="text" name="sub_county" value="{{ old('sub_county', $cohort->sub_county) }}">
    </div>
    <div class="field">
        <label>Ward</label>
        <input type="text" name="ward" value="{{ old('ward', $cohort->ward) }}">
    </div>
</div>

<h3 style="margin-top:20px;">Seats</h3>
<div class="form-grid">
    <div class="field">
        <label>Seats <span class="req">*</span></label>
        <input type="number" name="seats" min="1" max="500" required value="{{ old('seats', $cohort->seats) }}">
        <div class="help">A seat is held once the office confirms a CTP — applications alone do not fill it.</div>
    </div>
    <div class="field">
        <label>Training fee (KSh)</label>
        <input type="number" name="fee_ksh" min="0" step="0.01" value="{{ old('fee_ksh', $cohort->fee_ksh) }}">
        <div class="help">What a trainee owes for this intake. The 15/30 mobilisation credit is not applied yet.</div>
    </div>
    <div class="field">
        <label>Theory hours</label>
        <input type="number" name="theory_hours" min="0" max="2000" value="{{ old('theory_hours', $cohort->theory_hours) }}">
    </div>
    <div class="field">
        <label>Practical hours</label>
        <input type="number" name="practical_hours" min="0" max="2000" value="{{ old('practical_hours', $cohort->practical_hours) }}">
    </div>
</div>

<h3 style="margin-top:20px;">Running period</h3>
<p class="help">The dates the intake runs from and to. CTPs can put themselves forward from the day you post it until
    the end date passes, and you can hand out seats over the same window — a run whose end date has gone by takes no
    more seats.</p>
<div class="form-grid">
    <div class="field">
        <label>Runs from <span class="req">*</span></label>
        <input type="date" name="starts_on" required value="{{ old('starts_on', $cohort->starts_on?->toDateString()) }}">
    </div>
    <div class="field">
        <label>Runs to <span class="req">*</span></label>
        <input type="date" name="ends_on" required value="{{ old('ends_on', $cohort->ends_on?->toDateString()) }}">
    </div>
    <div class="field">
        <label>Applications close</label>
        <input type="date" name="enrol_closes_on" value="{{ old('enrol_closes_on', $cohort->enrol_closes_on?->toDateString()) }}">
        <div class="help">Leave empty to keep the intake open until you close it here.</div>
    </div>
    <div class="field">
        <label>Academic year</label>
        <input type="number" name="academic_year" min="2000" max="2100" step="1"
               value="{{ old('academic_year', $cohort->academic_year ?: $cohort->starts_on?->year) }}">
        <div class="help">The year this run belongs to — a course that runs for years is read back year by year.</div>
    </div>
    <div class="field">
        <label>Term or semester</label>
        <select name="term">
            @foreach (\App\Models\Cohort::TERMS as $key => $label)
                <option value="{{ $key }}" @selected((old('term', $cohort->term ?: \App\Models\Cohort::guessTerm($cohort->starts_on ?? today())) === $key))>{{ $label }}</option>
            @endforeach
        </select>
        <div class="help">Taken from the start date if you leave it — change it when the intake sits elsewhere in the calendar.</div>
    </div>
</div>

<div class="field" style="margin-top:18px;">
    <label>What CTPs should know about this intake</label>
    <textarea name="summary" rows="4" maxlength="2000"
              placeholder="Tools provided, where they meet, what they leave with.">{{ old('summary', $cohort->summary) }}</textarea>
</div>

<datalist id="cohort-counties">@foreach ($counties as $name)<option value="{{ $name }}"></option>@endforeach</datalist>

@php
    // Step one of an intake: which programmes it teaches, where it happens and when it runs. No trainer is named
    // here and no unit is ticked here — the units are registered on the intake once it exists, and the hands go
    // onto those units. The lead programme (the first ticked) is what the papers are filed under.
    $chosenProgrammes = collect(old('training_ids', $cohort->exists ? $cohort->programmeIds() : []))
        ->map(fn ($id) => (int) $id)->all();

    // Counties the intake reaches: every county, or the list ticked below. On a row that was saved before the list
    // existed, the county printed on it is the one ticked.
    $countyScope = old('county_scope', $cohort->exists ? ($cohort->coversAllCounties() ? 'all' : 'listed') : 'listed');
    $chosenCounties = collect(old('county_ids', $cohort->exists
        ? $cohort->coversAllCounties() ? [] : $cohort->countyNames()->map(
            fn ($name) => $counties->firstWhere('name', $name)?->id)->filter()->values()->all() : []))
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
        <label>Community it serves <span class="hint">(optional)</span></label>
        <select name="community_id">
            <option value="">All communities — not tied to one</option>
            @foreach ($communities as $c)
                <option value="{{ $c->id }}" @selected(old('community_id', $cohort->community_id) == $c->id)>
                    {{ $c->name }} — {{ $c->county }}
                </option>
            @endforeach
        </select>
        <div class="help">Not a condition for the intake. Leave it on "All communities" to take trainees from anywhere,
            or pick one so the intake shows on that community's own page. Students are fetched by the
            <b>programme</b> they are registered under, never by their community.</div>
    </div>
    <div class="field">
        <label>Sub-county <span class="hint">(optional)</span></label>
        <input type="text" name="sub_county" value="{{ old('sub_county', $cohort->sub_county) }}"
               placeholder="Leave empty if the run is not tied to one">
    </div>
    <div class="field">
        <label>Ward <span class="hint">(optional)</span></label>
        <input type="text" name="ward" value="{{ old('ward', $cohort->ward) }}"
               placeholder="Leave empty if the run is not tied to one">
    </div>
</div>

<h3 style="margin-top:20px;">Counties it is offered in</h3>
<p class="help">An intake can be open in every county, in a few of them, or in one. CTPs see the intake on their own
    side when it is open in their county, or when it is open in all of them.</p>
<div class="chip-grid">
    <label><input type="radio" name="county_scope" value="all" @checked($countyScope === 'all')> All counties</label>
    <label><input type="radio" name="county_scope" value="listed" @checked($countyScope === 'listed')>
        Only these counties</label>
</div>
<div class="chip-grid" style="margin-top:12px;max-height:190px;overflow:auto;">
    @foreach ($counties as $cty)
        <label><input type="checkbox" name="county_ids[]" value="{{ $cty->id }}"
                       @checked(in_array($cty->id, $chosenCounties, true))> {{ $cty->name }}</label>
    @endforeach
</div>
<div class="help">Tick one county to run in that county only, or several to run the same intake across them.</div>

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
        <label>Sessions</label>
        <div class="help" style="margin-top:6px;">{{ $cohort->exists ? $cohort->hoursLabel()
            : 'The theory and practical hours are typed on each unit you register — a welder\'s unit and a solar unit '
              .'do not run over the same hours. This intake adds them up.' }}</div>
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

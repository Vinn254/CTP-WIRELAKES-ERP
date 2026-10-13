@extends('layouts.admin')
@section('title', $title)

@php $editing = $cohort->exists; @endphp

@section('content')
<p class="back"><a href="{{ $editing ? route('admin.cohorts.show', $cohort) : route('admin.cohorts.index') }}">&larr; {{ $editing ? $cohort->reference : 'Cohorts' }}</a></p>
<h1>{{ $editing ? 'Edit '.$cohort->reference : 'Post a training' }}</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    {{ $editing
        ? 'The training, the place and the seats. Applications, progress and certification are worked on the cohort page.'
        : 'Save it as a draft, check the venue and trainer, then open it — only an open cohort reaches the CTP portal.' }}
    A cohort is one intake of a training the programme already runs, so it is picked from that list and never typed in.
    It needs a venue, a trainer or a community to run in, and a trainer must be registered for that training.
</p>

<form method="POST" action="{{ $editing ? route('admin.cohorts.update', $cohort) : route('admin.cohorts.store') }}" class="card">
    @csrf
    @if ($editing) @method('PATCH') @endif

    <h3>Training &amp; seats</h3>
    <div class="form-grid">
        <div class="field">
            <label>Training <span class="req">*</span></label>
            <select name="training_id" required>
                <option value="">Choose a training the programme runs</option>
                @foreach ($trainings as $t)
                    <option value="{{ $t->id }}" @selected((int) old('training_id', $cohort->training_id) === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
            <div class="help">Running something new? Add it under
                <a href="{{ route('admin.trainings.index') }}">Trainings on offer</a> first — it is not typed here.</div>
        </div>
        <div class="field">
            <label>Theory hours</label>
            <input type="number" name="theory_hours" min="0" max="2000" value="{{ old('theory_hours', $cohort->theory_hours) }}">
        </div>
        <div class="field">
            <label>Practical hours</label>
            <input type="number" name="practical_hours" min="0" max="2000" value="{{ old('practical_hours', $cohort->practical_hours) }}">
        </div>
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
    </div>

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
            <label>Trainer (an approved fundi)</label>
            <select name="trainer_id">
                <option value="">No trainer assigned</option>
                @foreach ($trainers as $t)
                    <option value="{{ $t->id }}" @selected(old('trainer_id', $cohort->trainer_id) == $t->id)>
                        {{ $t->fundi->full_name }} — {{ $t->teachingLabel() ?: 'no trainings set' }}
                    </option>
                @endforeach
            </select>
            <div class="help">Only a fundi registered for the training you picked can be handed this cohort.</div>
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
            <div class="help">Linking it puts the cohort on that community's timeline (Module 3).</div>
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

    <h3 style="margin-top:20px;">Dates</h3>
    <div class="form-grid">
        <div class="field">
            <label>Starts <span class="req">*</span></label>
            <input type="date" name="starts_on" required value="{{ old('starts_on', $cohort->starts_on?->toDateString()) }}">
        </div>
        <div class="field">
            <label>Ends <span class="req">*</span></label>
            <input type="date" name="ends_on" required value="{{ old('ends_on', $cohort->ends_on?->toDateString()) }}">
        </div>
        <div class="field">
            <label>Applications close</label>
            <input type="date" name="enrol_closes_on" value="{{ old('enrol_closes_on', $cohort->enrol_closes_on?->toDateString()) }}">
            <div class="help">Leave empty to keep the intake open until you close it here.</div>
        </div>
    </div>

    <div class="field" style="margin-top:18px;">
        <label>What CTPs should know about this intake</label>
        <textarea name="summary" rows="4" maxlength="2000"
                  placeholder="Tools provided, where they meet, what they leave with.">{{ old('summary', $cohort->summary) }}</textarea>
    </div>

    <datalist id="cohort-counties">@foreach ($counties as $name)<option value="{{ $name }}"></option>@endforeach</datalist>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">{{ $editing ? 'Save cohort' : 'Create as draft' }}</button>
        <a class="btn btn-ghost" href="{{ $editing ? route('admin.cohorts.show', $cohort) : route('admin.cohorts.index') }}">Cancel</a>
    </div>
</form>
@endsection

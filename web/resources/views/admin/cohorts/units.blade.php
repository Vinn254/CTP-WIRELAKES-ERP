@extends('layouts.admin')
@section('title', 'Units and trainers')

@php
    $chosenUnits = collect(old('module_ids', $chosenUnits))->map(fn ($id) => (int) $id)->all();
    $postedTrainers = old('unit_trainers', $unitTrainers) ?: [];
    $named = collect($postedTrainers)->filter()->count();
    $seated = $cohort->enrollments()->whereIn('state', ['pending', 'approved'])->count();
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.cohorts.show', $cohort) }}">&larr; {{ $cohort->reference }}</a></p>
<h1>Units and trainers — {{ $cohort->reference }}</h1>
<p class="page-lede">Register the units each programme brings, then name a trainer on each.</p>

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
        @php $units = $programmeUnits[$programme->id] ?? collect(); @endphp
        <div class="card">
            <h3>{{ $programme->name }}@if ($programme->code) <span class="tag">{{ $programme->code }}</span>@endif</h3>
            <p class="help">{{ $units->count() }} {{ Str::plural('unit', $units->count()) }} listed on this programme.
                A student registered under {{ $programme->name }} sees exactly the units you tick here.</p>

            @if ($units->isEmpty())
                <p class="flash-error" style="margin:0">This programme has no units yet, so its trainees have
                    nothing registered on them. Add units under
                    <a href="{{ route('admin.trainings.index') }}">Training</a> and tick them here.</p>
            @elseif(! count($eligible[$programme->id] ?? []))
                <p class="flash-error" style="margin:0">No approved trainer is registered to teach
                    {{ $programme->name }}. Approve one under <a href="{{ route('admin.trainers.index') }}">Trainers</a>
                    before naming a hand on these units.</p>
            @endif

            @if ($units->isNotEmpty())
                <div class="unit-rows">
                    <div class="unit-row unit-head">
                        <span>Unit taught on this intake</span><span>Trainer on the unit</span>
                    </div>
                    @foreach ($units as $u)
                        @php $options = collect($eligible[$programme->id] ?? []); @endphp
                        <div class="unit-row">
                            <label class="unit-pick"><input type="checkbox" name="module_ids[]" value="{{ $u->id }}"
                                  @checked(in_array($u->id, $chosenUnits, true))>
                                {{ $u->position }}. {{ $u->title }}</label>
                            <select name="unit_trainers[{{ $u->id }}]" aria-label="Trainer on {{ $u->title }}">
                                <option value="">No trainer named</option>
                                @foreach ($options as $tr)
                                    <option value="{{ $tr->id }}"
                                            @selected((int) ($postedTrainers[$u->id] ?? 0) === $tr->id)>
                                        {{ $tr->personName() }} — {{ $tr->placeLabel() }}</option>
                                @endforeach
                            </select>
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
@endsection

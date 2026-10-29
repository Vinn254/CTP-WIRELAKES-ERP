@extends($layout)
@section('title', 'Material — '.$cohort->reference)

@php
    $loose = $papers->where('lesson_line_id', null);
    $tied = $papers->whereNotNull('lesson_line_id')
        ->sortBy(fn ($p) => $p->lesson?->position ?? 999)->groupBy('lesson_line_id');
@endphp

@section('content')
@include('teaching.head')

<div class="card">
    <div class="section-head">
        <h3>What the class is given</h3>
        <span class="tag">{{ $papers->count() }} {{ Str::plural('handout', $papers->count()) }} on the shelf</span>
    </div>
    <p class="hint">Every CTP on this cohort opens what you put here — the note, the slide, the assignment, the
        practical sheet — against the lesson it was given out for.</p>
    <div class="module-foot">
        <button class="btn btn-sm btn-primary" type="button" data-sheet="material" data-material="">
            Put material on the shelf</button>
    </div>
</div>

@foreach ($tied as $lessonId => $group)
    @php $lesson = $group->first()->lesson; @endphp
    <h4 class="dl-group">Lesson {{ $lesson?->position }} — {{ $lesson?->topic ?: 'no lesson named' }}</h4>
    @foreach ($group as $paper)
        @include('teaching.material-row')
    @endforeach
@endforeach

@if ($loose->isNotEmpty())
    <h4 class="dl-group">On the unit, not tied to one lesson</h4>
    @foreach ($loose as $paper)
        @include('teaching.material-row')
    @endforeach
@endif

@if ($papers->isEmpty())
    <p class="hint">Nothing given out yet.</p>
@endif

@include('partials.sheet-start', ['name' => 'material', 'title' => 'Material',
    'sub' => 'The paper the class reads, filed against its unit and its lesson.'])
<form method="POST" action="{{ $url('material.store', ['cohort' => $cohort->id]) }}" enctype="multipart/form-data"
      class="fee-form" data-material-form data-confirm="Put this on the cohort's shelf?">
    @csrf
    <input type="hidden" name="_sheet" value="material">
    <input type="hidden" name="_method" value="POST" data-method>
    <div class="form-grid-2">
        <div class="field">
            <label>What it is <span class="req">*</span></label>
            <input type="text" name="title" maxlength="190" required placeholder="e.g. Unit 2 — wiring diagram notes">
        </div>
        <div class="field">
            <label>Kind</label>
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
        <div class="field">
            <label>Lesson it was given for</label>
            <select name="lesson_line_id">
                <option value="">Not one lesson</option>
                @foreach ($lessons as $lesson)
                    <option value="{{ $lesson->id }}">
                        Lesson {{ $lesson->position }} — {{ $lesson->topic }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Given out on</label>
            <input type="date" name="given_on" max="{{ today()->toDateString() }}"
                   min="{{ $cohort->starts_on?->toDateString() }}">
        </div>
        <div class="field">
            <label>The file <span class="req" data-file-req>*</span></label>
            <input type="file" name="file" data-file accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip">
            <div class="help">Up to 20 MB. Correcting an item replaces the file the trainees open.</div>
        </div>
    </div>
    <div class="field">
        <label>Note to the class</label>
        <input type="text" name="note" maxlength="500"
               placeholder="Read pages 1–8 before the practical.">
    </div>
    <div class="sheet-actions">
        <button class="btn btn-ghost" type="button" data-close-sheet>Cancel</button>
        <button class="btn btn-primary" type="submit" data-material-save>Put it on the shelf</button>
    </div>
</form>
@include('partials.sheet-end')

<script>
    // One form serves both jobs: the button that opens it carries the item's own fields and its save url.
    document.addEventListener('click', function (e) {
        const open = e.target.closest('[data-material]');
        if (! open) return;

        const form = document.querySelector('[data-material-form]');
        const sheet = document.getElementById('sheet-material');
        let data = {};
        try { data = JSON.parse(open.dataset.material || '{}'); } catch (err) { data = {}; }

        const correcting = Boolean(data.correct);
        form.action = data.action || '{{ $url("material.store", ["cohort" => $cohort->id]) }}';
        form.querySelector('[data-method]').value = correcting ? 'PATCH' : 'POST';
        sheet.querySelector('.sheet-head h3').textContent = correcting ? 'Correct the item' : 'Material';
        sheet.querySelector('[data-material-save]').textContent = correcting
            ? 'Save the item' : 'Put it on the shelf';
        // A new item needs its file; correcting one may keep the file already on record.
        form.querySelector('[data-file]').required = ! correcting;
        sheet.querySelector('[data-file-req]').hidden = correcting;

        form.querySelectorAll('[name]').forEach(function (input) {
            if (input.name === '_sheet' || input.type === 'file') return;
            const value = data[input.name];
            input.value = value === undefined || value === null ? '' : value;
        });
    });
</script>
@endsection

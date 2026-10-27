@extends('layouts.admin')
@section('title', $project->exists ? 'Edit project' : 'Post a project')

@php
    use App\Models\Project;
    $editing = $project->exists;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.projects.index') }}">&larr; Projects</a></p>
<h1>{{ $editing ? 'Edit '.$project->reference : 'Post a new project' }}</h1>
<p class="page-lede">Set the project details and who can apply.</p>

@include('partials.errors')

<div class="card">
 <form method="POST" action="{{ $editing ? route('admin.projects.update', $project) : route('admin.projects.store') }}">
 @csrf
 @if ($editing) @method('PATCH') @endif

 <div class="form-grid">
 <div class="field" style="grid-column:1 / -1;">
 <label>Project title <span class="req">*</span></label>
 <input type="text" name="title" maxlength="160" value="{{ old('title', $project->title) }}" required placeholder="e.g. Solar wiring for Nyakach primary school">
 </div>

 <div class="field" style="grid-column:1 / -1;">
 <label>Description <span class="req">*</span></label>
 <textarea name="description" rows="6" maxlength="8000" required placeholder="Scope of work, site conditions, what a good finish looks like.">{{ old('description', $project->description) }}</textarea>
 </div>

 <div class="field" style="grid-column:1 / -1;">
 <label>Materials required</label>
 <textarea name="materials" rows="4" maxlength="4000" placeholder="List what the job needs — e.g. 40 m of 2.5mm cable, 6 socket outlets, 1 DB board with breakers, conduit and clips.">{{ old('materials', $project->materials) }}</textarea>
 <div class="hint">Shown to members on the project page, so they know what the job comes with.</div>
 </div>

 @if (! $project->exists)
 <div class="field" style="grid-column:1 / -1;">
 <label>Materials the programme provides — and what each costs</label>
 <div class="help">Put a quantity and a cost on every item the organisation is handing over for this job. The fundi sees this list, and the total of what is actually given is deducted from their payout (§14.6 step 6). Anything the job needs that the fundi brings themselves is not listed here.</div>
 <table class="data">
 <thead>
 <tr><th>Material</th><th style="width:120px;">Quantity</th><th style="width:150px;">Unit</th><th style="width:170px;">Cost each (KSh)</th></tr>
 </thead>
 <tbody>
 @foreach (range(0, 4) as $i)
 <tr>
 <td><input type="text" name="material[{{ $i }}][name]" maxlength="190" placeholder="e.g. Cable, 2.5 mm single core (coil)" value="{{ old('material.'.$i.'.name') }}" aria-label="Material name, line {{ $i + 1 }}"></td>
 <td><input type="number" name="material[{{ $i }}][quantity]" step="0.01" min="0" placeholder="0" value="{{ old('material.'.$i.'.quantity') }}" aria-label="Quantity, line {{ $i + 1 }}"></td>
 <td>
 <select name="material[{{ $i }}][unit]" aria-label="Unit, line {{ $i + 1 }}">
 @foreach (\App\Models\InventoryItem::UNITS as $key => $label)
 <option value="{{ $key }}">{{ $label }}</option>
 @endforeach
 </select>
 </td>
 <td><input type="number" name="material[{{ $i }}][cost]" step="0.01" min="0" placeholder="0" value="{{ old('material.'.$i.'.cost') }}" aria-label="Cost each in shillings, line {{ $i + 1 }}"></td>
 </tr>
 @endforeach
 </tbody>
 </table>
 <div class="hint">Leave the rows you do not need empty. Lines can be added, or taken from a stock card, on the project page after posting.</div>
 </div>
 @else
 <div class="field" style="grid-column:1 / -1;">
 <label>Materials the programme provides</label>
 <div class="help">{{ $project->providedMaterials()->count() }} line(s) on {{ $project->reference }}, worth {{ \App\Support\Finance::ksh($project->materialsPlannedKsh()) }} planned and {{ \App\Support\Finance::ksh($project->materialsSuppliedKsh()) }} handed over. Add, cost or hand over a line on <a href="{{ route('admin.projects.show', $project) }}">the project page</a> — the fundi sees the same table.</div>
 </div>
 @endif

 <div class="field">
 <label>Who can apply <span class="req">*</span></label>
 <select name="category" required>
 @foreach (Project::CATEGORIES as $k => $cat)
 <option value="{{ $k }}" @selected(old('category', $project->category) === $k)>{{ $cat['label'] }}</option>
 @endforeach
 </select>
 <div class="hint" id="catHint">{{ old('category', $project->category) ? Project::CATEGORIES[old('category', $project->category)]['hint'] : 'Select a level to see who it opens the project to.' }}</div>
 </div>

 <div class="field">
 <label>Hirer / organisation</label>
 <select name="hirer_id">
 <option value="">No hirer recorded</option>
 @foreach ($hirers as $h)
 <option value="{{ $h->id }}" @selected((int) old('hirer_id', $project->hirer_id) === $h->id)>{{ $h->organisation }}@if ($h->county) — {{ $h->county }}@endif</option>
 @endforeach
 </select>
 <div class="hint">Record hirers in <a href="{{ route('admin.hirers.index') }}">Hirers &amp; organisations</a>.</div>
 </div>

 <div class="field">
 <label>Trade / skill</label>
 <input type="text" name="trade" maxlength="80" value="{{ old('trade', $project->trade) }}" placeholder="e.g. Solar, Electrical">
 </div>

 <div class="field">
 <label>Budget (KSh)</label>
 <input type="number" step="0.01" min="0" name="budget" value="{{ old('budget', $project->budget) }}">
 </div>

 <div class="field">
 <label>County</label>
 <select name="county">
 <option value="">Not specified</option>
 @foreach ($counties as $c)
 <option value="{{ $c }}" @selected(old('county', $project->county) === $c)>{{ $c }}</option>
 @endforeach
 </select>
 </div>

 <div class="field">
 <label>Ward / location</label>
 <input type="text" name="ward" maxlength="80" value="{{ old('ward', $project->ward) }}">
 </div>

 <div class="field">
 <label>Community</label>
 <select name="community_id">
 <option value="">Not tied to a community</option>
 @foreach ($communities as $c)
 <option value="{{ $c->id }}" @selected((int) old('community_id', $project->community_id) === $c->id)>{{ $c->community_code ?: $c->name }} — {{ $c->name }}, {{ $c->county }}</option>
 @endforeach
 </select>
 <div class="help">Ties the project to a Module 3 community: it lands on that community&rsquo;s timeline and counts toward its investment value.</div>
 </div>

 <div class="field">
 <label>Duration</label>
 <input type="text" name="duration" maxlength="60" value="{{ old('duration', $project->duration) }}" placeholder="e.g. 3 weeks">
 </div>

 <div class="field">
 <label>Application deadline</label>
 <input type="date" name="deadline" value="{{ old('deadline', $project->deadline?->format('Y-m-d')) }}">
 </div>

 <div class="field">
 <label>Visibility</label>
 <select name="status" @disabled($editing && $project->status === 'awarded')>
 <option value="open" @selected(old('status', $project->status ?? 'open') === 'open')>Open — members can apply now</option>
 <option value="draft" @selected(old('status', $project->status) === 'draft')>Draft — hidden from members</option>
 </select>
 @if ($editing && $project->status === 'awarded')
 <div class="hint">Already awarded — release the award on the project page to change this.</div>
 @endif
 </div>
 </div>

 <div class="save-bar">
 <button class="btn btn-primary btn-lg" type="submit">{{ $editing ? 'Save changes' : 'Post project' }}</button>
 @if ($editing)
 <a class="btn btn-ghost btn-lg" href="{{ route('admin.projects.show', $project) }}">Back to project</a>
 @endif
 </div>
 </form>
</div>

<script>
const cats = @json(collect(Project::CATEGORIES)->map(fn ($c) => $c['hint']));
document.querySelector('[name=category]').addEventListener('change', function () {
 document.getElementById('catHint').textContent = cats[this.value] ?? '';
});
</script>
@endsection

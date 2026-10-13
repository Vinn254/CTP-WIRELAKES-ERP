@extends('layouts.admin')
@section('title', 'Trainings on offer')

@section('content')
<p class="back"><a href="{{ route('admin.department', 'training') }}">&larr; Training &amp; Certification</a></p>
<h1>Trainings on offer</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    This is what the programme teaches — nothing else. <b>A cohort is an intake of one of these</b> and
    <b>a fundi is registered to teach these</b>; nobody types a course name anywhere. Start a new trade here and
    it appears on both forms straight away.
</p>

<div class="stat-row">
    <div class="stat"><small>Trainings</small><b>{{ $trainings->count() }}</b></div>
    <div class="stat"><small>On offer</small><b>{{ $trainings->where('status', 'active')->count() }}</b></div>
    <div class="stat"><small>Intakes run</small><b>{{ $trainings->sum('cohorts_count') }}</b></div>
    <div class="stat"><small>Trainer listings</small><b>{{ $trainings->sum('trainers_count') }}</b></div>
</div>

<details class="card need-form" @if ($errors->any()) open @endif>
    <summary>Add a training the programme will run</summary>
    <form method="POST" action="{{ route('admin.trainings.store') }}" class="fee-form"
          data-confirm="Add this training to the programme list?" data-title="Add training">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label>Training name <span class="req">*</span></label>
                <input type="text" name="name" required maxlength="120" value="{{ old('name') }}"
                       placeholder="e.g. Solar Installation">
            </div>
            <div class="field">
                <label>Short code</label>
                <input type="text" name="code" maxlength="30" value="{{ old('code') }}" placeholder="Left empty it is made from the name">
                <div class="help">Used in certificate codes — letters and numbers only.</div>
            </div>
            <div class="field">
                <label>Status</label>
                <select name="status">
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', 'active') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field" style="margin-top:12px;">
            <label>What the trainee learns</label>
            <textarea name="summary" rows="2" maxlength="500">{{ old('summary') }}</textarea>
        </div>
        <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Add training</button>
    </form>
</details>

<table class="data">
    <thead>
        <tr>
            <th>Training</th><th>Code</th><th>What it covers</th>
            <th>Intakes</th><th>Trainers</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($trainings as $t)
            <tr>
                <td><b>{{ $t->name }}</b></td>
                <td><span class="tag">{{ $t->code }}</span></td>
                <td>{{ $t->summary ?: '—' }}</td>
                <td>{{ $t->cohorts_count }}
                    <div class="hint">
                        <a href="{{ route('admin.cohorts.index') }}">Cohorts</a>
                    </div>
                </td>
                <td>{{ $t->trainers_count }}
                    <div class="hint"><a href="{{ route('admin.trainers.index') }}">Fundi-trainers</a></div>
                </td>
                <td><span class="status {{ $t->status === 'active' ? 'active' : 'closed' }}">{{ $t->status === 'active' ? 'on offer' : 'off the list' }}</span></td>
                <td style="text-align:right;white-space:nowrap;">
                    <form method="POST" action="{{ route('admin.trainings.toggle', $t) }}" class="mini-form">
                        @csrf
                        <button class="btn btn-sm btn-ghost" type="submit">{{ $t->status === 'active' ? 'Take off' : 'Put on' }}</button>
                    </form>
                    <details class="need-form" style="display:inline-block;">
                        <summary class="btn btn-sm btn-outline">Edit</summary>
                        <form method="POST" action="{{ route('admin.trainings.update', $t) }}" class="fee-form" style="text-align:left;">
                            @csrf @method('PATCH')
                            <div class="form-grid">
                                <div class="field"><label>Name <span class="req">*</span></label>
                                    <input type="text" name="name" value="{{ $t->name }}" required maxlength="120"></div>
                                <div class="field"><label>Short code</label>
                                    <input type="text" name="code" value="{{ $t->code }}" maxlength="30"></div>
                                <div class="field"><label>Status</label>
                                    <select name="status">
                                        @foreach ($statuses as $key => $label)
                                            <option value="{{ $key }}" @selected($t->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select></div>
                            </div>
                            <div class="field" style="margin-top:12px;"><label>What the trainee learns</label>
                                <textarea name="summary" rows="2" maxlength="500">{{ $t->summary }}</textarea></div>
                            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Save training</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('admin.trainings.delete', $t) }}" class="mini-form"
                          data-confirm="Remove {{ $t->name }} from the programme list?" data-title="Remove training">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-ghost" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:28px;">
                No trainings on the list. Add the trades the programme runs before posting a cohort.
            </td></tr>
        @endforelse
    </tbody>
</table>

<p class="sub" style="color:var(--muted);margin:14px 0 0;">
    A training that has already run an intake or been given to a trainer cannot be deleted — take it off the list
    instead, so those records keep what they were.
</p>
@endsection

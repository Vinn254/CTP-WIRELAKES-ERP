@extends('layouts.admin')
@section('title', 'Training')

@section('content')
<p class="back"><a href="{{ route('admin.department', 'training') }}">&larr; Training &amp; Certification</a></p>
<h1>Training</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    This is what the programme teaches — nothing else. <b>A cohort is an intake of one of these</b> and
    <b>a fundi is registered to teach these</b>; nobody types a course name anywhere. Start a new trade here and
    it appears on both forms straight away. Under each training you set the <b>modules</b> a trainee has to get
    through — optional, and the moment you add one, the trainer sees it as what they must deliver and the CTP
    sees it on their record.
</p>

@include('partials.errors')

<div class="stat-row">
    <div class="stat"><small>Trainings</small><b>{{ $trainings->count() }}</b></div>
    <div class="stat"><small>Listed</small><b>{{ $trainings->where('status', 'active')->count() }}</b></div>
    <div class="stat"><small>Modules set</small><b>{{ $trainings->sum('modules_count') }}</b></div>
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
            <th>Modules</th><th>Intakes</th><th>Trainers</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($trainings as $t)
            <tr>
                <td><b>{{ $t->name }}</b></td>
                <td><span class="tag">{{ $t->code }}</span></td>
                <td>{{ $t->summary ?: '—' }}</td>
                <td>{{ $t->modules_count }}
                    <div class="hint"><a href="#modules-{{ $t->id }}">Set the modules</a></div>
                </td>
                <td>{{ $t->cohorts_count }}
                    <div class="hint">
                        <a href="{{ route('admin.cohorts.index') }}">Cohorts</a>
                    </div>
                </td>
                <td>{{ $t->trainers_count }}
                    <div class="hint"><a href="{{ route('admin.trainers.index') }}">Fundi-trainers</a></div>
                </td>
                <td><span class="status {{ $t->status === 'active' ? 'active' : 'closed' }}">{{ $t->statusLabel() }}</span></td>
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
            <tr id="modules-{{ $t->id }}" class="sub-row">
                <td colspan="8">
                    <b class="hint">Modules under {{ $t->name }}</b>
                    <p class="hint">What the trainer delivers and what the trainee must be marked on before they
                        reach 100% and can be certified. A training with no modules still runs — adding them is
                        the office's call.</p>

                    @foreach ($t->modules as $m)
                        <div class="row-line">
                            <b>{{ $m->position }}. {{ $m->title }}</b>
                            <span class="hint">{{ $m->hoursLabel() }}</span>
                            @if ($m->outcome)<span class="hint">Expected: {{ $m->outcome }}</span>@endif
                            <span class="tag gray">{{ $m->completions()->count() }} marked</span>
                            <details class="need-form" style="display:inline-block;">
                                <summary class="btn btn-sm btn-ghost">Edit</summary>
                                <form method="POST" action="{{ route('admin.trainings.modules.update', [$t, $m]) }}"
                                      class="fee-form" style="text-align:left;">
                                    @csrf @method('PATCH')
                                    <div class="form-grid-2">
                                        <div class="field"><label>Module <span class="req">*</span></label>
                                            <input type="text" name="title" value="{{ $m->title }}" required maxlength="150"></div>
                                        <div class="field"><label>Hours</label>
                                            <input type="number" name="hours" min="0" max="2000" value="{{ $m->hours }}"></div>
                                        <div class="field"><label>Order</label>
                                            <input type="number" name="position" min="0" max="999" value="{{ $m->position }}"></div>
                                        <div class="field"><label>What the trainee must be able to do</label>
                                            <input type="text" name="outcome" value="{{ $m->outcome }}" maxlength="500"></div>
                                    </div>
                                    <button class="btn btn-lime btn-block" type="submit" style="margin-top:10px;">Save module</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('admin.trainings.modules.delete', [$t, $m]) }}" class="mini-form"
                                  data-confirm="Remove {{ $m->title }} from {{ $t->name }}?" data-title="Remove module">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-ghost" type="submit">Remove</button>
                            </form>
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('admin.trainings.modules.store', $t) }}" class="fee-form"
                          data-confirm="Add the modules you have typed to {{ $t->name }}?" data-title="Add modules">
                        @csrf
                        @php
                            // On a validation miss the rows they typed come back, so nothing they wrote is lost.
                            $typed = collect(old('modules', []))
                                ->filter(fn ($row) => is_array($row) && filled($row['title'] ?? null))
                                ->values();
                            if ($typed->isEmpty()) $typed = collect([[]]);
                        @endphp
                        <div class="module-rows" id="module-rows-{{ $t->id }}">
                            @foreach ($typed as $i => $row)
                                <div class="module-row form-grid-3" data-index="{{ $i }}">
                                    <div class="field">
                                        <label><span data-module-label>{{ $i === 0 ? 'Module' : 'Module '.($i + 1) }}</span>
                                            @if ($i === 0) <span class="req">*</span> @endif</label>
                                        <input type="text" name="modules[{{ $i }}][title]" maxlength="150"
                                               value="{{ $row['title'] ?? '' }}"
                                               placeholder="e.g. Diagnosing and replacing a failed power supply">
                                    </div>
                                    <div class="field">
                                        <label>Hours</label>
                                        <input type="number" name="modules[{{ $i }}][hours]" min="0" max="2000"
                                               value="{{ $row['hours'] ?? '' }}" placeholder="e.g. 20">
                                    </div>
                                    <div class="field">
                                        <label>What the trainee must be able to do</label>
                                        <input type="text" name="modules[{{ $i }}][outcome]" maxlength="500"
                                               value="{{ $row['outcome'] ?? '' }}"
                                               placeholder="e.g. Strip a unit safely, find the dead rail and put a working one back.">
                                    </div>
                                    <button class="btn btn-sm btn-ghost module-drop" type="button" data-module-remove
                                            @if ($i === 0) hidden @endif>Remove this row</button>
                                </div>
                            @endforeach
                        </div>
                        <div class="module-foot">
                            <button class="btn btn-outline btn-sm" type="button" data-module-add="module-rows-{{ $t->id }}">+ Add another module</button>
                            <button class="btn btn-lime btn-sm" type="submit">Save modules</button>
                        </div>
                        <p class="hint">Use <b>+ Add another module</b> to put several in at once — they are numbered
                            in the order you type them. Leave a row blank and it is not added.</p>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:28px;">
                No trainings on the list. Add the trades the programme runs before posting a cohort.
            </td></tr>
        @endforelse
    </tbody>
</table>

<p class="sub" style="color:var(--muted);margin:14px 0 0;">
    A training that has already run an intake or been given to a trainer cannot be deleted — take it off the list
    instead, so those records keep what they were. The same rule holds for a module that has been marked on a
    trainee's record.
</p>

<script>
    // The module rows are a repeater: + drops another row so several modules go in with one submit.
    document.addEventListener('click', function (e) {
        function relabel(box) {
            box.querySelectorAll('.module-row').forEach(function (row, i) {
                const label = row.querySelector('[data-module-label]');
                if (label) label.textContent = i === 0 ? 'Module' : 'Module ' + (i + 1);
            });
        }

        const add = e.target.closest('[data-module-add]');
        if (add) {
            const box = document.getElementById(add.dataset.moduleAdd);
            const rows = box.querySelectorAll('.module-row');
            const last = rows[rows.length - 1];
            const next = Math.max(...Array.from(rows, r => Number(r.dataset.index) || 0)) + 1;
            const row = last.cloneNode(true);

            row.dataset.index = next;
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/modules\[\d+\]/, 'modules[' + next + ']');
                input.value = '';
            });
            row.querySelector('[data-module-remove]').hidden = false;

            box.append(row);
            relabel(box);
            row.querySelector('input').focus();
            return;
        }

        const drop = e.target.closest('[data-module-remove]');
        if (drop) {
            const box = drop.closest('.module-rows');
            drop.closest('.module-row').remove();
            relabel(box);
        }
    });
</script>
@endsection

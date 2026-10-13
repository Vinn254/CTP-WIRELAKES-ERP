@extends('layouts.admin')
@section('title', 'Fundi-trainers')

@php
    use App\Models\Trainer;
    $tabs = ['all' => 'All'] + Trainer::STATUSES;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.department', 'training') }}">&larr; Training &amp; Certification</a></p>
<h1>Fundi-trainers</h1>
<p class="sub" style="color:var(--muted);margin:6px 0 16px;">
    A trainer stays a <b>Member &rsaquo; E-Fundi record</b> — this list only holds the teaching role: where they
    teach, which skill families they can teach, the hours they give and the rate they are paid.
    Only a <b>junior or senior fundi</b> can train CTPs, and only an <b>approved</b> trainer can be handed a cohort.
    A senior fundi can still apply for open projects — training does not take that away.
</p>

<div class="stat-row">
    <div class="stat"><small>On the list</small><b>{{ $counts->sum() }}</b></div>
    <div class="stat"><small>Awaiting decision</small><b>{{ $counts['applied'] ?? 0 }}</b></div>
    <div class="stat"><small>Approved</small><b>{{ $counts['approved'] ?? 0 }}</b></div>
    <div class="stat"><small>Eligible fundis not yet here</small><b>{{ $fundis->count() }}</b></div>
</div>

<div class="tabs">
    @foreach ($tabs as $s => $lbl)
        <a class="tab {{ $status === $s ? 'on' : '' }}" href="{{ route('admin.trainers.index', ['status' => $s]) }}">
            {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
        </a>
    @endforeach
</div>

<details class="card need-form" @if ($errors->any() && old('fundi_id')) open @endif>
    <summary>Add a trainer — put a working fundi on the teaching list</summary>
    @if ($fundis->isEmpty())
        <p class="hint" style="margin:10px 0 0;">Every junior or senior fundi in the pool is already on this list.
            A fundi can also apply to train from their own portal, and lands here as “Applied”.</p>
    @else
        <form method="POST" action="{{ route('admin.trainers.store') }}" class="fee-form"
              data-confirm="Add this fundi to the trainer list?" data-title="Add trainer">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Fundi <span class="req">*</span></label>
                    <select name="fundi_id" required>
                        <option value="">Choose a junior or senior fundi</option>
                        @foreach ($fundis as $f)
                            <option value="{{ $f->id }}" @selected(old('fundi_id') == $f->id)>
                                {{ $f->full_name }} — {{ $f->ef_code }} ({{ \App\Models\FundiRegistration::tierLabel($f->tier) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Posted at a venue</label>
                    <select name="venue_id">
                        <option value="">Not posted at a venue — teach in an area</option>
                        @foreach ($venues as $v)
                            <option value="{{ $v->id }}" @selected(old('venue_id') == $v->id)>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>County <span class="req">*</span></label>
                    <input type="text" name="county" list="trainer-counties" required value="{{ old('county') }}">
                </div>
                <div class="field">
                    <label>Sub-county</label>
                    <input type="text" name="sub_county" value="{{ old('sub_county') }}">
                </div>
                <div class="field">
                    <label>Ward</label>
                    <input type="text" name="ward" value="{{ old('ward') }}">
                </div>
                <div class="field">
                    <label>Hours a week</label>
                    <input type="number" name="weekly_hours" min="0" max="80" value="{{ old('weekly_hours') }}" placeholder="e.g. 15">
                </div>
                <div class="field">
                    <label>Rate (KSH)</label>
                    <input type="number" name="rate_ksh" min="0" step="0.01" value="{{ old('rate_ksh') }}" placeholder="What they are paid for the training">
                </div>
            </div>
            <div class="field" style="margin-top:12px;">
                <label>What they can teach <span class="req">*</span></label>
                <div style="display:flex;flex-wrap:wrap;gap:2px 18px;margin:8px 0;">
                    @foreach ($families as $family)
                        <label class="check-row" style="margin:0 16px 0 0;"><input type="checkbox" name="teaches[]" value="{{ $family }}"
                            @checked(in_array($family, (array) old('teaches', []), true))> {{ $family }}</label>
                    @endforeach
                </div>
                <div class="help">A cohort can only be handed to a trainer who is approved <b>and</b> listed for that family.</div>
            </div>
            <div class="field" style="margin-top:12px;">
                <label>What you know of their work</label>
                <textarea name="note" rows="3" maxlength="500">{{ old('note') }}</textarea>
            </div>
            <datalist id="trainer-counties">@foreach ($counties as $name)<option value="{{ $name }}"></option>@endforeach</datalist>
            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Add trainer</button>
        </form>
    @endif
</details>

<table class="data">
    <thead>
        <tr>
            <th>Trainer</th><th>Grade</th><th>Can teach</th><th>Where</th>
            <th>Hours / week</th><th>Rate</th><th>Cohorts</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($trainers as $t)
            <tr>
                <td>
                    <b>{{ $t->fundi->full_name }}</b>
                    <div class="hint">{{ $t->fundi->ef_code }} · {{ $t->fundi->phone }}</div>
                </td>
                <td>{{ \App\Models\FundiRegistration::tierLabel($t->fundi->tier) }}</td>
                <td>{{ collect((array) $t->teaches)->join(', ') ?: '—' }}</td>
                <td>{{ $t->placeLabel() }}</td>
                <td>{{ $t->weekly_hours ?: '—' }}</td>
                <td>{{ $t->rate_ksh > 0 ? 'KSH '.number_format((float) $t->rate_ksh, 2) : 'No rate set' }}</td>
                <td>{{ $t->cohorts->count() }}
                    <div class="hint">{{ $t->cohorts->whereIn('status', ['open', 'in_progress'])->count() }} running now</div>
                </td>
                <td><span class="status {{ $t->status === 'approved' ? 'active' : ($t->status === 'applied' ? 'pending_review' : 'closed') }}">{{ $t->statusLabel() }}</span>
                    @if ($t->note)<div class="hint">{{ \Illuminate\Support\Str::limit($t->note, 50) }}</div>@endif</td>
                <td style="text-align:right;white-space:nowrap;">
                    @if ($t->status !== 'approved')
                        <form method="POST" action="{{ route('admin.trainers.decide', $t) }}" class="mini-form"
                              data-confirm="Approve {{ $t->fundi->full_name }} to train CTPs?" data-title="Approve trainer">
                            @csrf
                            <input type="hidden" name="decision" value="approved">
                            <button class="btn btn-sm btn-lime" type="submit">Approve</button>
                        </form>
                    @endif
                    @if ($t->status !== 'suspended' && $t->status !== 'declined')
                        <details class="need-form" style="display:inline-block;">
                            <summary class="btn btn-sm btn-ghost">Decline / suspend</summary>
                            <form method="POST" action="{{ route('admin.trainers.decide', $t) }}" class="fee-form" style="text-align:left;">
                                @csrf
                                <div class="field">
                                    <label>Decision <span class="req">*</span></label>
                                    <select name="decision" required>
                                        <option value="declined">Decline the application</option>
                                        <option value="suspended">Suspend an approved trainer</option>
                                    </select>
                                </div>
                                <div class="field" style="margin-top:10px;">
                                    <label>Reason <span class="req">*</span></label>
                                    <textarea name="note" rows="3" maxlength="500" required></textarea>
                                </div>
                                <button class="btn btn-outline btn-block" type="submit" style="margin-top:10px;">Save decision</button>
                            </form>
                        </details>
                    @endif
                    <details class="need-form" style="display:inline-block;">
                        <summary class="btn btn-sm btn-outline">Edit</summary>
                        <form method="POST" action="{{ route('admin.trainers.update', $t) }}" class="fee-form" style="text-align:left;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="fundi_id" value="{{ $t->fundi_id }}">
                            <div class="form-grid">
                                <div class="field"><label>Posted at a venue</label>
                                    <select name="venue_id">
                                        <option value="">Not posted at a venue — teach in an area</option>
                                        @foreach ($venues as $v)
                                            <option value="{{ $v->id }}" @selected($t->venue_id === $v->id)>{{ $v->name }}</option>
                                        @endforeach
                                    </select></div>
                                <div class="field"><label>County <span class="req">*</span></label>
                                    <input type="text" name="county" value="{{ $t->county }}" list="trainer-counties" required></div>
                                <div class="field"><label>Sub-county</label>
                                    <input type="text" name="sub_county" value="{{ $t->sub_county }}"></div>
                                <div class="field"><label>Ward</label>
                                    <input type="text" name="ward" value="{{ $t->ward }}"></div>
                                <div class="field"><label>Hours a week</label>
                                    <input type="number" name="weekly_hours" min="0" max="80" value="{{ $t->weekly_hours ?: '' }}"></div>
                                <div class="field"><label>Rate (KSH)</label>
                                    <input type="number" name="rate_ksh" min="0" step="0.01" value="{{ $t->rate_ksh ?: '' }}"></div>
                            </div>
                            <div class="field" style="margin-top:12px;">
                                <label>Can teach <span class="req">*</span></label>
                                <div style="display:flex;flex-wrap:wrap;gap:2px 18px;margin:8px 0;">
                                    @foreach ($families as $family)
                                        <label class="check-row" style="margin:0;"><input type="checkbox" name="teaches[]" value="{{ $family }}"
                                            @checked(in_array($family, (array) $t->teaches, true))> {{ $family }}</label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="field" style="margin-top:12px;"><label>Note</label>
                                <textarea name="note" rows="3" maxlength="500">{{ $t->note }}</textarea></div>
                            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Save trainer</button>
                        </form>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:28px;">
                No trainers in this view. A fundi must be junior or senior grade before they can train CTPs.
            </td></tr>
        @endforelse
    </tbody>
</table>

<p class="hint" style="margin-top:14px;">
    A fundi who applies to train from their own portal arrives here as <b>Applied</b> with the site they offered —
    the office still decides. Approved trainers are the only ones the cohort form will accept.
</p>
@endsection

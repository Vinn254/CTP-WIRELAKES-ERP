@extends('layouts.admin')
@section('title', 'Fundi-trainers')

@php
    use App\Models\Trainer;
    $tabs = ['all' => 'All'] + Trainer::STATUSES;
@endphp

@section('content')
<h1>Fundi-trainers</h1>
<p class="page-lede">Fundi-trainers listed per site, with licences, hours and rates.</p>

<div class="stat-row">
    <div class="stat"><small>Site records</small><b>{{ $counts->sum() }}</b></div>
    <div class="stat"><small>Awaiting decision</small><b>{{ $counts['applied'] ?? 0 }}</b></div>
    <div class="stat"><small>Approved site records</small><b>{{ $counts['approved'] ?? 0 }}</b></div>
    <div class="stat"><small>Eligible fundis in the pool</small><b>{{ $fundis->count() }}</b></div>
</div>

<div class="tabs">
    @foreach ($tabs as $s => $lbl)
        <a class="tab {{ $status === $s ? 'on' : '' }}" href="{{ route('admin.trainers.index', ['status' => $s]) }}">
            {{ $lbl }}@if ($s !== 'all')<span class="n">{{ $counts[$s] ?? 0 }}</span>@endif
        </a>
    @endforeach
</div>

<details class="card need-form" @if ($errors->any() && old('fundi_id')) open @endif>
    <summary>Add a trainer — put a working fundi on the teaching list <b>for one site</b></summary>
    @if ($fundis->isEmpty())
        <p class="hint" style="margin:10px 0 0;">There is no active senior fundi in the pool to put forward.
            A fundi can also apply to train from their own portal, and lands here as “Applied”.</p>
    @else
        <form method="POST" action="{{ route('admin.trainers.store') }}" class="fee-form"
              data-confirm="Open a trainer application for this fundi at the site you chose?" data-title="Add trainer">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label>Fundi <span class="req">*</span></label>
                    <select name="fundi_id" required>
                        <option value="">Choose a senior fundi</option>
                        @foreach ($fundis as $f)
                            <option value="{{ $f->id }}" @selected(old('fundi_id') == $f->id)>
                                {{ $f->full_name }} — {{ $f->ef_code }} ({{ \App\Models\FundiRegistration::tierLabel($f->tier) }})
                                @if ($f->trainers->isNotEmpty())
                                    · already at {{ $f->trainers->map(fn ($t) => ($t->venue->name ?? 'a site').': '.$t->statusLabel())->join(', ') }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Site they would train at <span class="req">*</span></label>
                    <select name="venue_id" required>
                        <option value="">Choose the venue</option>
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
            @include('admin.partials.teachings', ['chosen' => old('trainings', [])])
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
            <th>Trainer</th><th>Grade</th><th>Can teach</th><th>Site this record covers</th>
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
                <td>{{ $t->teachingLabel() ?: '—' }}</td>
                <td>{{ $t->venue->name ?? 'No venue named' }}
                    <div class="hint">{{ collect([$t->ward, $t->sub_county, $t->county])->filter()->join(' · ') ?: '—' }}</div></td>
                <td>{{ $t->weekly_hours ?: '—' }}</td>
                <td>{{ $t->rate_ksh > 0 ? 'KSH '.number_format((float) $t->rate_ksh, 2) : 'No rate set' }}</td>
                <td>{{ $t->cohorts->count() }}
                    <div class="hint">{{ $t->cohorts->whereIn('status', ['open', 'in_progress'])->count() }} running now</div>
                </td>
                <td><span class="status {{ $t->status === 'approved' ? 'active' : ($t->status === 'applied' ? 'pending_review' : 'closed') }}">{{ $t->statusLabel() }}</span>
                    @if ($t->note)<div class="hint">{{ \Illuminate\Support\Str::limit($t->note, 50) }}</div>@endif</td>
                <td class="actions">
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
                            <input type="hidden" name="venue_id" value="{{ $t->venue_id }}">
                            <div class="form-grid">
                                <div class="field"><label>Site this record covers</label>
                                    <input type="text" value="{{ $t->venue->name ?? '—' }}" readonly
                                        title="A site is its own approval. To train elsewhere, add a new record for that venue.">
                                    <span class="help">This record is an approval for one site only. Moving the trainer to
                                        another venue would change what the approval covers, so add a separate record instead.</span></div>
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
                            @include('admin.partials.teachings', ['chosen' => $t->trainings->pluck('id')->all()])
                            <div class="field" style="margin-top:12px;"><label>Note</label>
                                <textarea name="note" rows="3" maxlength="500">{{ $t->note }}</textarea></div>
                            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Save trainer</button>
                        </form>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty-cell">
                No trainer records in this view. Only a senior fundi can train CTPs.
            </td></tr>
        @endforelse
    </tbody>
</table>

<p class="hint" style="margin-top:14px;">
    A fundi who applies to train from their own portal arrives here as <b>Applied</b> with the site they offered —
    the office still decides. Approved trainers are the only ones the cohort form will accept.
</p>
@endsection

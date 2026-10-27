@extends('layouts.admin')
@section('title', 'Training venues')

@php
    use App\Models\TrainingVenue;
@endphp

@section('content')
<p class="back"><a href="{{ route('admin.department', 'training') }}">&larr; Training &amp; Certification</a></p>
<h1>Training venues</h1>
<p class="sub page-lede">
    Where a cohort is delivered: a <b>partner school</b> using its facilities, a <b>community site</b>, or a
    <b>WireLakes Technologies centre</b>. A venue linked to a partner keeps the public partner wall (Module 1) in step.
</p>

<div class="stat-row">
    <div class="stat"><small>Venues</small><b>{{ $venues->count() }}</b></div>
    <div class="stat"><small>Partner schools</small><b>{{ $venues->where('type', 'partner_school')->count() }}</b></div>
    <div class="stat"><small>Open for cohorts</small><b>{{ $venues->where('status', 'active')->count() }}</b></div>
    <div class="stat"><small>Seats on site</small><b>{{ number_format($venues->where('status', 'active')->sum('capacity')) }}</b></div>
</div>

<form method="GET" class="filter-bar">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search venue, county or ward" aria-label="Search venue, county or ward">
    <button class="btn btn-sm btn-outline" type="submit">Search</button>
    @if ($search)<a class="btn btn-sm btn-ghost" href="{{ route('admin.venues.index') }}">Clear</a>@endif
</form>

<details class="card need-form" @if ($errors->any()) open @endif>
    <summary>Add a venue — a school, a site or a centre</summary>
    <form method="POST" action="{{ route('admin.venues.store') }}" class="fee-form"
          data-confirm="Add this training venue?" data-title="Add venue">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label>Venue name <span class="req">*</span></label>
                <input type="text" name="name" required maxlength="150" placeholder="e.g. Nyanda Technical School">
            </div>
            <div class="field">
                <label>What it is <span class="req">*</span></label>
                <select name="type" required>
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>County <span class="req">*</span></label>
                <input type="text" name="county" list="venue-counties" required value="{{ old('county') }}">
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
                <label>Trainees it can hold</label>
                <input type="number" name="capacity" min="0" max="2000" placeholder="e.g. 40">
            </div>
            <div class="field">
                <label>Linked partner</label>
                <select name="partner_id">
                    <option value="">Not a listed partner</option>
                    @foreach ($partners as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Held in a community</label>
                <select name="community_id">
                    <option value="">Not tied to a community</option>
                    @foreach ($communities as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} — {{ $c->county }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Who to contact there</label>
                <input type="text" name="contact_name" maxlength="150">
            </div>
            <div class="field">
                <label>Their phone</label>
                <input type="text" name="contact_phone" maxlength="30">
            </div>
            <div class="field">
                <label>Their email</label>
                <input type="email" name="contact_email" maxlength="190">
            </div>
        </div>
        <div class="field" style="margin-top:12px;">
            <label>What is taught here, what equipment exists</label>
            <textarea name="summary" rows="3" maxlength="2000"></textarea>
        </div>
        <datalist id="venue-counties">@foreach ($counties as $name)<option value="{{ $name }}"></option>@endforeach</datalist>
        <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Add venue</button>
    </form>
</details>

<table class="data">
    <thead>
        <tr>
            <th>Venue</th><th>Type</th><th>Location</th><th>Capacity</th>
            <th>Cohorts</th><th>Partner</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($venues as $v)
            <tr>
                <td><b>{{ $v->name }}</b>@if ($v->summary)<div class="hint">{{ \Illuminate\Support\Str::limit($v->summary, 60) }}</div>@endif</td>
                <td>{{ $v->typeLabel() }}</td>
                <td>{{ $v->county }}<div class="hint">{{ $v->placeLabel() === $v->county ? '' : $v->placeLabel() }}</div>
                    @if ($v->community)<div class="hint">{{ $v->community->name }}</div>@endif</td>
                <td>{{ $v->capacity ?: '—' }}</td>
                <td>{{ $v->cohorts->count() }}<div class="hint">{{ $v->cohorts->whereIn('status', ['open', 'in_progress'])->count() }} running now</div></td>
                <td>{{ $v->partner?->name ?: '—' }}</td>
                <td><span class="status {{ $v->status === 'active' ? 'active' : 'closed' }}">{{ $v->status === 'active' ? 'open' : 'closed' }}</span></td>
                <td class="actions">
                    <form method="POST" action="{{ route('admin.venues.toggle', $v) }}" class="mini-form">
                        @csrf
                        <button class="btn btn-sm btn-ghost" type="submit">{{ $v->status === 'active' ? 'Close' : 'Open' }}</button>
                    </form>
                    <details class="need-form" style="display:inline-block;">
                        <summary class="btn btn-sm btn-outline">Edit</summary>
                        <form method="POST" action="{{ route('admin.venues.update', $v) }}" class="fee-form" style="text-align:left;">
                            @csrf @method('PATCH')
                            <div class="form-grid">
                                <div class="field"><label>Name <span class="req">*</span></label>
                                    <input type="text" name="name" value="{{ $v->name }}" required maxlength="150"></div>
                                <div class="field"><label>What it is <span class="req">*</span></label>
                                    <select name="type" required>
                                        @foreach ($types as $key => $label)
                                            <option value="{{ $key }}" @selected($v->type === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select></div>
                                <div class="field"><label>County <span class="req">*</span></label>
                                    <input type="text" name="county" value="{{ $v->county }}" list="venue-counties" required></div>
                                <div class="field"><label>Sub-county</label>
                                    <input type="text" name="sub_county" value="{{ $v->sub_county }}"></div>
                                <div class="field"><label>Ward</label>
                                    <input type="text" name="ward" value="{{ $v->ward }}"></div>
                                <div class="field"><label>Trainees it can hold</label>
                                    <input type="number" name="capacity" min="0" max="2000" value="{{ $v->capacity ?: '' }}"></div>
                                <div class="field"><label>Linked partner</label>
                                    <select name="partner_id">
                                        <option value="">Not a listed partner</option>
                                        @foreach ($partners as $p)
                                            <option value="{{ $p->id }}" @selected($v->partner_id === $p->id)>{{ $p->name }}</option>
                                        @endforeach
                                    </select></div>
                                <div class="field"><label>Held in a community</label>
                                    <select name="community_id">
                                        <option value="">Not tied to a community</option>
                                        @foreach ($communities as $c)
                                            <option value="{{ $c->id }}" @selected($v->community_id === $c->id)>{{ $c->name }} — {{ $c->county }}</option>
                                        @endforeach
                                    </select></div>
                                <div class="field"><label>Contact</label>
                                    <input type="text" name="contact_name" value="{{ $v->contact_name }}" maxlength="150"></div>
                                <div class="field"><label>Phone</label>
                                    <input type="text" name="contact_phone" value="{{ $v->contact_phone }}" maxlength="30"></div>
                                <div class="field"><label>Email</label>
                                    <input type="email" name="contact_email" value="{{ $v->contact_email }}" maxlength="190"></div>
                            </div>
                            <div class="field" style="margin-top:12px;"><label>Notes</label>
                                <textarea name="summary" rows="3" maxlength="2000">{{ $v->summary }}</textarea></div>
                            <button class="btn btn-lime btn-block" type="submit" style="margin-top:12px;">Save venue</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('admin.venues.delete', $v) }}" class="mini-form"
                          data-confirm="Remove {{ $v->name }} from the venue list?" data-title="Remove venue">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-ghost" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty-cell">
                No venues yet. Add the school or site a cohort will run at, then post the cohort.
            </td></tr>
        @endforelse
    </tbody>
</table>
@endsection

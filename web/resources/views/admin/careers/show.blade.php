@extends('layouts.admin')
@section('title', $opening->title)

@php
    use App\Models\JobApplication;
    use App\Models\JobOpening;
    use Illuminate\Support\Str;
@endphp

@section('content')
@include('partials.errors')
<div class="applicant-head card">
    <div class="ah-main">
        <h1>{{ $opening->title }}
            <span class="status {{ $opening->status === 'open' ? 'active' : ($opening->status === 'draft' ? 'pending_review' : 'closed') }}">
                {{ $opening->statusLabel() }}</span></h1>
        <p class="ah-meta">
            <span class="tag">{{ $opening->reference }}</span>
            <span>{{ $opening->kindLabel() }}</span>
            @if ($opening->area)<span>{{ $opening->area }}</span>@endif
            @if ($opening->county)<span>{{ $opening->county }}</span>@endif
            <span>{{ $opening->positions }} position{{ $opening->positions == 1 ? '' : 's' }}</span>
            <span>{{ $opening->closingLabel() }}</span>
            <span>Posted by {{ $opening->creator->name ?? 'the office' }} {{ $opening->created_at->format('d M Y') }}</span>
        </p>
    </div>
    <div class="ah-contact">
        <a class="btn btn-sm btn-outline" href="{{ route('careers.opening', $opening) }}" target="_blank">See the public page</a>
        <button class="btn btn-sm btn-ghost" type="button" data-sheet="amend">Amend the posting</button>
    </div>
</div>

<div class="stat-row">
    <div class="stat"><small>Applications</small><b>{{ $applications->count() }}</b></div>
    <div class="stat"><small>Waiting to be read</small><b class="{{ $newApplications ? 'flag-bad' : '' }}">{{ $newApplications }}</b></div>
    <div class="stat"><small>Shortlisted</small><b>{{ $applications->where('status', 'shortlisted')->count() }}</b></div>
    <div class="stat"><small>Contacted back</small><b>{{ $applications->where('status', 'contacted')->count() }}</b></div>
</div>

<div class="card">
    <div class="section-head"><h3>What the public page says</h3></div>
    <p>{{ $opening->description }}</p>
    @if ($opening->requirements)
        <h4 class="dl-group">What we are looking for</h4>
        <p class="hint">{{ $opening->requirements }}</p>
    @endif
</div>

<div class="card">
    <div class="section-head"><h3>Applications ({{ $applications->count() }})</h3></div>
    <p class="hint">A person applies without an account, so their contacts live only on this record. Move each one
        through review and write down when you called or emailed them back — that is what answers a person who says
        they never heard from us.</p>

    @if ($applications->isEmpty())
        <p class="help">Nothing has come in yet. Once the posting is open it is on the public careers page, and what
            arrives shows up here the same moment.</p>
    @endif

    @foreach ($applications as $a)
        <div class="dl-group">{{ $a->name }} — applied {{ $a->created_at->format('d M Y') }}
            <button class="btn btn-sm btn-lime" type="button" data-sheet="review-{{ $a->id }}">Review</button>
        </div>
        <table class="data">
            <tbody>
                <tr>
                    <td>Contact</td>
                    <td><a href="mailto:{{ $a->email }}">{{ $a->email }}</a><div class="hint">{{ $a->phone }}</div></td>
                    <td>Status</td>
                    <td><span class="status {{ $a->status === 'contacted' ? 'active' : ($a->status === 'declined' ? 'closed' : 'pending_review') }}">
                        {{ $a->statusLabel() }}</span>
                        @if ($a->contacted_on)<div class="hint">contacted {{ $a->contacted_on->format('d M Y') }}</div>@endif</td>
                </tr>
                <tr>
                    <td>ID / town</td>
                    <td>{{ $a->id_number ?: '—' }}@if ($a->town) · {{ $a->town }}@endif</td>
                    <td>Experience</td><td>{{ $a->experience ?: '—' }}</td>
                </tr>
                <tr>
                    <td>CV</td>
                    <td>@if ($a->hasCv())
                            <a href="{{ route('admin.careers.cv', $a) }}" target="_blank">{{ $a->cv_name ?: 'open the file' }}</a>
                            <span class="hint">({{ number_format(max(1, (int) $a->cv_size / 1024), 1) }} KB)</span>
                        @else
                            <span class="hint">none attached</span>
                        @endif</td>
                    <td>Read by</td>
                    <td>{{ $a->reviewer->name ?? 'nobody yet' }}</td>
                </tr>
                @if ($a->cover_note)
                    <tr><td>What they said</td><td colspan="3">{{ $a->cover_note }}</td></tr>
                @endif
                @if ($a->review_note)
                    <tr><td>Our note</td><td colspan="3">{{ $a->review_note }}</td></tr>
                @endif
            </tbody>
        </table>
        @include('partials.sheet-start', ['name' => 'review-'.$a->id, 'title' => 'Review '.$a->name,
            'sub' => 'Write down where this application stands and what was said.'])
            <form method="POST" action="{{ route('admin.careers.review', $a) }}" class="fee-form"
                  data-confirm="Save this review?" data-title="Review application">
                @csrf
                <input type="hidden" name="_sheet" value="review-{{ $a->id }}">
                <div class="form-grid-2">
                    <div class="field">
                        <label>Where this application is</label>
                        <select name="status">
                            @foreach (JobApplication::STATUSES as $key => $label)
                                @continue($key === 'onboarded' && $a->status !== $key)
                                <option value="{{ $key }}" @selected($a->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="hint">A person is onboarded from the Human Resources desk, not here.</div>
                    </div>
                    <div class="field">
                        <label>Contacted on</label>
                        <input type="date" name="contacted_on" value="{{ $a->contacted_on?->format('Y-m-d') }}">
                        <div class="hint">Fill it in when you called or emailed them.</div>
                    </div>
                    <div class="field full">
                        <label>Note on the record</label>
                        <textarea name="review_note" rows="2" maxlength="1000"
                                  placeholder="Why they were shortlisted, what was said on the call, what is missing.">{{ $a->review_note }}</textarea>
                    </div>
                </div>
                <div class="sheet-actions">
                    <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
                    <button class="btn btn-sm btn-lime" type="submit">Save the review</button>
                </div>
            </form>
        @include('partials.sheet-end')
    @endforeach
</div>

<div class="two-col">
    <div class="card">
        <div class="section-head"><h3>Put it on the public page</h3></div>
        <p class="hint">A draft stays inside the office. Open puts it on <b>/careers</b> with its form. Closing takes
            the form down but keeps the posting and every application to it.</p>
        <div class="head-acts">
            @foreach (JobOpening::STATUSES as $key => $label)
                @continue($opening->status === $key)
                <form method="POST" action="{{ route('admin.careers.status', $opening) }}" class="fee-form"
                      data-confirm="Make this posting {{ Str::lower($label) }}?" data-title="Change status">
                    @csrf
                    <input type="hidden" name="status" value="{{ $key }}">
                    <button class="btn btn-sm {{ $key === 'open' ? 'btn-lime' : 'btn-outline' }}" type="submit">
                        {{ $label }}</button>
                </form>
            @endforeach
        </div>
    </div>

    <div class="card" id="amend">
        <div class="section-head"><h3>Amend the posting</h3></div>
        <p class="hint">The reference never changes and people who already applied keep their application.</p>
        <button class="btn btn-sm btn-lime" type="button" data-sheet="amend">Amend the posting</button>
    </div>
</div>

@include('partials.sheet-start', ['name' => 'amend', 'title' => 'Amend the posting',
    'sub' => 'The reference never changes and every application already made stays.'])
    <form method="POST" action="{{ route('admin.careers.update', $opening) }}" class="fee-form"
          data-confirm="Save these changes to the posting?" data-title="Amend posting">
        @csrf
        <input type="hidden" name="_sheet" value="amend">
        <div class="form-grid-2">
            <div class="field full">
                <label>Title <span class="req">*</span></label>
                <input type="text" name="title" required maxlength="190" value="{{ old('title', $opening->title) }}">
            </div>
            <div class="field">
                <label>Kind</label>
                <select name="kind">
                    @foreach (JobOpening::KINDS as $key => $label)
                        <option value="{{ $key }}" @selected($opening->kind === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Area of work</label>
                <input type="text" name="area" maxlength="90" value="{{ old('area', $opening->area) }}">
            </div>
            <div class="field">
                <label>County</label>
                <input type="text" name="county" maxlength="90" value="{{ old('county', $opening->county) }}">
            </div>
            <div class="field">
                <label>Positions</label>
                <input type="number" name="positions" min="1" max="999" value="{{ old('positions', $opening->positions) }}">
            </div>
            <div class="field">
                <label>Applications close</label>
                <input type="date" name="closing_on" value="{{ $opening->closing_on?->format('Y-m-d') }}">
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>What the work is <span class="req">*</span></label>
            <textarea name="description" rows="4" required maxlength="1000">{{ old('description', $opening->description) }}</textarea>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>What we are looking for</label>
            <textarea name="requirements" rows="3" maxlength="1000">{{ old('requirements', $opening->requirements) }}</textarea>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-sm btn-ghost" type="button" data-close-sheet>Cancel</button>
            <button class="btn btn-sm btn-lime" type="submit">Save changes</button>
        </div>
    </form>
@include('partials.sheet-end')
@endsection

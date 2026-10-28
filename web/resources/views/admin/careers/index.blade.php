@extends('layouts.admin')
@section('title', 'Careers')

@php
    use App\Models\JobOpening;
@endphp

@section('content')
@include('partials.errors')
<div class="head-row">
    <div>
        <h1>Careers</h1>
        <p class="page-lede">Openings the programme puts out for a job or project work, and the people who applied.</p>
    </div>
    <div class="head-acts">
        <a class="btn btn-sm btn-lime" href="#post">+ Post an opening</a>
        <a class="btn btn-sm btn-outline" href="{{ route('careers') }}" target="_blank">View the public page</a>
    </div>
</div>

<div class="stat-row">
    <div class="stat"><small>Open for applications</small><b>{{ $counts['open'] ?? 0 }}</b></div>
    <div class="stat"><small>Waiting to be posted</small><b>{{ $counts['draft'] ?? 0 }}</b></div>
    <div class="stat"><small>Applications to read</small><b class="{{ $newApplications ? 'flag-bad' : '' }}">{{ $newApplications }}</b></div>
    <div class="stat"><small>Postings on the desk</small><b>{{ $counts['all'] }}</b></div>
</div>

<div class="tabs">
    @foreach (['all' => 'Every posting', 'open' => 'Open', 'draft' => 'Draft', 'closed' => 'Closed'] as $key => $label)
        <a class="tab {{ $status === $key ? 'on' : '' }}" href="{{ route('admin.careers.index', ['f' => $key]) }}">
            {{ $label }}<span class="n">{{ $counts[$key] ?? 0 }}</span>
        </a>
    @endforeach
</div>

<table class="data">
    <thead>
        <tr>
            <th>Reference</th><th>Opening</th><th>Kind</th><th>Where</th><th>Positions</th>
            <th>Applications</th><th>Closes</th><th>Status</th><th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($openings as $o)
            <tr>
                <td><b>{{ $o->reference }}</b></td>
                <td>{{ $o->title }}@if ($o->area)<div class="hint">{{ $o->area }}</div>@endif</td>
                <td>{{ $o->kindLabel() }}</td>
                <td>{{ $o->county ?: '—' }}</td>
                <td>{{ $o->positions }}</td>
                <td>{{ $o->applications_count }}</td>
                <td>{{ $o->closing_on?->format('d M Y') ?: 'until closed' }}</td>
                <td><span class="status {{ $o->status === 'open' ? 'active' : ($o->status === 'draft' ? 'pending_review' : 'closed') }}">
                    {{ $o->statusLabel() }}</span></td>
                <td><a class="btn btn-sm btn-outline" href="{{ route('admin.careers.show', $o) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty-cell">Nothing is posted yet. Use “Post an opening” below.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $openings->links() }}

<div class="card entry-card" id="post">
    <h3>Post an opening</h3>
    <p class="hint">This is for work the programme is hiring for — a trainer for another project, a technician, a
        piece of project work. It is not one of the trainings on the catalogue: those run through CTP and E-Fundi
        registration. Save it as a draft to write it up, then open it when you want the public page to carry it.</p>
    <form method="POST" action="{{ route('admin.careers.store') }}" class="fee-form"
          data-confirm="Post this opening to the careers page?" data-title="Post an opening">
        @csrf
        <div class="form-grid-2">
            <div class="field full">
                <label>Title on the public page <span class="req">*</span></label>
                <input type="text" name="title" required maxlength="190" value="{{ old('title') }}"
                       placeholder="e.g. Robotics trainer for the Kisumu cohorts">
            </div>
            <div class="field">
                <label>What kind of opening <span class="req">*</span></label>
                <select name="kind" required>
                    @foreach (JobOpening::KINDS as $key => $label)
                        <option value="{{ $key }}" @selected(old('kind', 'job') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Area of work</label>
                <input type="text" name="area" maxlength="90" value="{{ old('area') }}" placeholder="e.g. Training, robotics, construction">
            </div>
            <div class="field">
                <label>County</label>
                <input type="text" name="county" maxlength="90" value="{{ old('county') }}">
            </div>
            <div class="field">
                <label>Positions</label>
                <input type="number" name="positions" min="1" max="999" value="{{ old('positions', 1) }}">
            </div>
            <div class="field">
                <label>Applications close</label>
                <input type="date" name="closing_on" value="{{ old('closing_on') }}">
                <div class="hint">Leave blank and it stays open until you close it.</div>
            </div>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>What the work is <span class="req">*</span></label>
            <textarea name="description" rows="4" required maxlength="1000"
                      placeholder="What the person will do, for whom, and on what terms.">{{ old('description') }}</textarea>
        </div>
        <div class="field" style="margin-top:10px;">
            <label>What we are looking for</label>
            <textarea name="requirements" rows="3" maxlength="1000"
                      placeholder="Skills, certificates, experience — anything a person must bring.">{{ old('requirements') }}</textarea>
        </div>
        <div class="head-acts" style="margin-top:14px;">
            <button class="btn btn-sm btn-outline" type="submit" name="publish" value="0">Save as draft</button>
            <button class="btn btn-sm btn-lime" type="submit" name="publish" value="1">Post it</button>
        </div>
    </form>
</div>

<p class="hint">A closed posting stays on the public page without its form, and what people applied to is never
    deleted — amend a posting on its own page and the applications already in stay as they were.</p>
@endsection

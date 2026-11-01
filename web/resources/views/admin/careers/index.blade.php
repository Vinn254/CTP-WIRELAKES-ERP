@extends('layouts.admin')
@section('title', 'Careers')

@section('content')
@include('partials.errors')
<div class="head-row">
    <div>
        <h1>Careers</h1>
        <p class="page-lede">Openings the programme puts out for a job or project work, and the people who applied.</p>
    </div>
    <div class="head-acts">
        <button class="btn btn-sm btn-lime" type="button" data-sheet="post">+ Post an opening</button>
        <a class="btn btn-sm btn-outline" href="{{ route('careers') }}" target="_blank">View the public page</a>
    </div>
</div>

<div class="stat-row">
    <div class="stat"><small>Open for applications</small><b>{{ $counts['open'] ?? 0 }}</b></div>
    <div class="stat"><small>Waiting to be posted</small><b>{{ $counts['draft'] ?? 0 }}</b></div>
    <div class="stat"><small>Applications to read</small><b class="{{ $newApplications ? 'flag-bad' : '' }}">{{ $newApplications }}</b></div>
    <div class="stat"><small>Postings on the desk</small><b>{{ $counts['all'] }}</b></div>
</div>

<form method="GET" action="{{ route('admin.careers.index') }}" class="filter-bar inline-filter">
    <label class="field">
        <span>Posting status</span>
        <select name="f" onchange="this.form.submit()">
            @foreach (['all' => 'Every posting', 'open' => 'Open', 'draft' => 'Draft', 'closed' => 'Closed'] as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
            @endforeach
        </select>
    </label>
    <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
</form>

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

@include('partials.sheet-start', ['name' => 'post', 'title' => 'Post an opening',
    'sub' => 'Work the programme is hiring for. Save a draft, then open it when the public page should carry it.'])
    <form method="POST" action="{{ route('admin.careers.store') }}" class="fee-form"
          data-confirm="Post this opening to the careers page?" data-title="Post an opening">
        @csrf
        <input type="hidden" name="_sheet" value="post">
        @include('admin.careers.partials.fields', ['o' => null, 'sheet' => 'post'])
        <div class="sheet-actions">
            <button class="btn btn-sm btn-outline" type="submit" name="publish" value="0">Save as draft</button>
            <button class="btn btn-sm btn-lime" type="submit" name="publish" value="1">Post it</button>
        </div>
    </form>
@include('partials.sheet-end')

<p class="hint">A closed posting stays on the public page without its form, and what people applied to is never
    deleted.</p>
@endsection

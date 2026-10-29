@extends('layouts.admin')
@section('title', 'Service requests')

@php
    $read = \App\Support\Departments::hosted();
@endphp

@section('content')
<div class="head-row">
    <div>
        <h1>Service requests</h1>
        <p class="page-lede">Every request that came in from a public area page, and what this desk did about it.</p>
    </div>
    <div class="head-acts">
        <a class="btn btn-sm btn-outline" href="{{ route('what-we-do') }}" target="_blank">View the public pages</a>
    </div>
</div>

<div class="stat-row">
    <div class="stat"><small>Waiting to be read</small><b class="{{ $waiting ? 'flag-bad' : '' }}">{{ $waiting }}</b></div>
    <div class="stat"><small>Contacted back</small><b>{{ $counts['contacted'] ?? 0 }}</b></div>
    <div class="stat"><small>Taken up</small><b>{{ $counts['taken'] ?? 0 }}</b></div>
    <div class="stat"><small>On the register</small><b>{{ $counts['all'] }}</b></div>
</div>

<form method="GET" action="{{ dept_link('admin.applications.index') }}" class="filter-bar inline-filter">
    <label class="field">
        <span>Stage</span>
        <select name="f" onchange="this.form.submit()">
            <option value="all" @selected($status === 'all')>Every request ({{ $counts['all'] }})</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>
            @endforeach
        </select>
    </label>
    <label class="field">
        <span>Area</span>
        <select name="area" onchange="this.form.submit()">
            <option value="all" @selected($area === 'all')>Every area</option>
            @foreach ($areas as $slug => $meta)
                <option value="{{ $slug }}" @selected($area === $slug)>{{ $meta['name'] }}</option>
            @endforeach
        </select>
    </label>
    <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
</form>

@if ($read)
    <p class="hint">Read-only here — Website &amp; Membership answers a request that is not in your area.</p>
@endif

<div class="table-scroll">
    <table class="data">
        <thead>
            <tr>
                <th>Reference</th><th>Who asked</th><th>Area</th><th>Service</th>
                <th>Town</th><th>Received</th><th>Status</th><th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $a)
                <tr>
                    <td><b>{{ $a->reference }}</b></td>
                    <td>{{ $a->name }}<div class="hint">{{ $a->phone }}@if ($a->whatsapp) · WA {{ $a->whatsapp }}@endif</div></td>
                    <td>{{ $a->areaLabel() }}</td>
                    <td>{{ $a->category ?: 'Not listed' }}</td>
                    <td>{{ $a->town ?: '—' }}</td>
                    <td>{{ $a->created_at->format('d M Y') }}</td>
                    <td><span class="status {{ $a->status === 'received' ? 'pending_review' : ($a->status === 'taken' ? 'active' : ($a->status === 'closed' ? 'closed' : '')) }}">
                        {{ $a->statusLabel() }}</span></td>
                    <td><a class="btn btn-sm btn-outline" href="{{ dept_link('admin.applications.show', ['application' => $a]) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-cell">No request has come in from the area pages yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $applications->links() }}
@endsection
